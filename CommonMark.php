<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown;

/**
 * Markdown parser for [CommonMark](http://commonmark.org/).
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class CommonMark extends Parser
{
	// include block element parsing using traits
	use block\FencedCodeTrait;
	use block\HeadlineTrait;
	use block\HtmlTrait {
		parseInlineHtml as private;
	}
	use block\ListTrait {
		// Check Ul List before headline
		identifyUl as protected identifyBUl;
		consumeUl as protected consumeBUl;
	}
	use block\RuleTrait {
		// Check Hr before checking lists
		identifyHr as protected identifyAHr;
		consumeHr as protected consumeAHr;
	}

	// include inline element parsing using traits
	use inline\EmphStrongTrait;
	use inline\LinkTrait;

	/**
	 * @var boolean whether to format markup according to HTML5 spec.
	 * Defaults to `false` which means that markup is formatted as HTML4.
	 */
	public $html5 = false;

	/**
	 * @var array these are "escapeable" characters. When using one of these prefixed with a
	 * backslash, the character will be outputted without the backslash and is not interpreted
	 * as markdown.
	 */
	protected $escapeCharacters = [
		// http://spec.commonmark.org/0.12/#backslash-escapes
		'!', '"', '#', '$', '%', '&', "'", '(', ')', '*', '+',
		',', '-', '.', '/', ':', ';', '<', '=', '>', '?', '@',
		'[', '\\', ']', '^', '_', '`', '{', '|', '}', '~',
	];


	/**
	 * @inheritdoc
	 */
	protected function normalizeText($text)
	{
		$text = rtrim($text);
		if (strpos($text, "\t") !== false) {
			return implode("\n", array_map(function($line) {
					while(($pos = mb_strpos($line, "\t", 0, 'UTF-8')) !== false)  {
						$line = mb_substr($line, 0, $pos, 'UTF-8') . str_repeat(' ', 4 - $pos % 4) . mb_substr($line, $pos + 1, null, 'UTF-8');
					}
					return $line;
				},
				explode("\n", str_replace(["\r\n", "\n\r", "\r"], "\n", $text))
			));
		}
		return parent::normalizeText($text);
	}

	/**
	 * @inheritDoc
	 */
	protected function prepare()
	{
		// reset references
		$this->references = [];
	}

	/**
	 * Consume lines for a paragraph
	 *
	 * Allow headlines and code to break paragraphs
	 */
	protected function consumeParagraph($lines, $current)
	{
		// consume until newline
		$content = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (empty($line)
				|| ltrim($line) === ''
				|| !ctype_alpha($line[0]) && (
//					$this->identifyQuote($line, $lines, $i) ||
//					$this->identifyCode($line, $lines, $i) ||
//					$this->identifyFencedCode($line, $lines, $i) ||
					$this->identifyUl($line, $lines, $i) ||
					$this->identifyOl($line, $lines, $i) ||
					$this->identifyHr($line, $lines, $i)
				)
//				|| $this->identifyHeadline($line, $lines, $i)
			)
			{
				break;
			} else {
				$content[] = ltrim($line);
			}
		}
		$block = [
			'paragraph',
			'content' => $this->parseInline(implode("\n", $content)),
		];
		return [$block, --$i];
	}

	/**
	 * identify a line as a headline
	 */
	protected function identifyHeadline($line, $lines, $current)
	{
		return (
			// heading with #
			($line[0] === '#' || $line[0] === ' ') && preg_match('/^ {0,3}#{1,6}( .+|$)/', $line)
			||
			// underlined headline
			!empty($lines[$current + 1]) &&
			(($l = $lines[$current + 1][0]) === '=' || $l === '-' || $l === ' ') &&
			preg_match('/^ {0,3}(\-+|=+)\s*$/', $lines[$current + 1])
		);
	}

	/**
	 * Consume lines for a code block element
	 */
	protected function consumeCode($lines, $current)
	{
		// consume until newline

		$content = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];

			// a line is considered to belong to this code block as long as it is intended by 4 spaces or a tab
			if (isset($line[0]) && ($line[0] === "\t" || strncmp($lines[$i], '    ', 4) === 0)) {
				$line = $line[0] === "\t" ? substr($line, 1) : substr($line, 4);
				$content[] = $line;
			// but also if it is empty and the next line is intended by 4 spaces or a tab
			// TODO http://spec.commonmark.org/0.12/#example-63
			} elseif ((empty($line) || rtrim($line) === '') && isset($lines[$i + 1][0]) &&
				      ($lines[$i + 1][0] === "\t" || strncmp($lines[$i + 1], '    ', 4) === 0)) {
				if (!empty($line)) {
					$line = $line[0] === "\t" ? substr($line, 1) : substr($line, 4);
				}
				$content[] = $line;
			} else {
				break;
			}
		}
		// http://spec.commonmark.org/0.12/#example-69
		$lastLine = end($content);
		if (ltrim($lastLine) === '') {
			unset($content[key($content)]);
		}

		$block = [
			'code',
			'content' => implode("\n", $content),
		];
		return [$block, --$i];
	}

	/**
	 * Consume lines for a fenced code block
	 */
	protected function consumeFencedCode($lines, $current)
	{
		// TODO allow indented fenced code blocks
		// http://spec.commonmark.org/0.12/#example-81

		// consume until ```
		$line = rtrim($lines[$current]);
		$fence = substr($line, 0, $pos = strrpos($line, $line[0]) + 1);
		$fenceLength = strlen($fence);
		$language = substr($line, $pos);
		$content = [];
		for ($i = $current + 1, $count = count($lines); $i < $count; $i++) {
			if (strncmp($line = $lines[$i], $fence, $fenceLength) !== 0) {
				$content[] = $line;
			} else {
				break;
			}
		}
		$block = [
			'code',
			'content' => implode("\n", $content),
		];
		if (!empty($language)) {
			$block['language'] = $language;
		}
		return [$block, $i];
	}

	/**
	 * Renders a code block
	 */
	protected function renderCode($block)
	{
		$class = isset($block['language']) ? ' class="language-' . $block['language'] . '"' : '';
		return "<pre><code$class>"
			. (!empty($block['content']) ? htmlspecialchars($block['content'] . "\n", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '')
			. "</code></pre>\n";
	}

	/**
	 * Parses an & or a html entity definition.
	 * @marker &
	 */
	protected function parseEntity($text)
	{
		// TODO entities to utf8 ?
		// http://spec.commonmark.org/0.12/#name-entities

		// html entities e.g. &copy; &#169; &#x00A9;
		if (preg_match('/^&#?[\w\d]+;/', $text, $matches)) {
			return [['inlineHtml', $matches[0]], strlen($matches[0])];
		} else {
			return [['text', '&'], 1];
		}
	}

	/**
	 * Parses inline HTML.
	 * @marker <
	 */
	protected function parseInlineHtml($text)
	{
		if (strpos($text, '>') !== false) {
			if (preg_match('~^</?(\w+\d?)( .*?)?>~', $text, $matches)) {
				// HTML tags
				return [['inlineHtml', $matches[0]], strlen($matches[0])];
			} elseif (preg_match('~^<!--.*?-->~', $text, $matches)) {
				// HTML comments
				return [['inlineHtml', $matches[0]], strlen($matches[0])];
			}
		}
		return [['text', '<'], 1];
	}

	/**
	 * Escapes `>` characters.
	 * TODO remove this method, let renderText just escape it
	 * @marker >
	 */
	protected function parseGt($text)
	{
		return [['text', '>'], 1];
	}

	// TODO indented HTML blocks
	// TODO markdown in HTML blocks

	protected function identifyReference($line)
	{
		// TODO allow URL on second line
		return ($line[0] === ' ' || $line[0] === '[') && preg_match('/^ {0,3}\[(.+?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*$/', $line);
	}

	/**
	 * Consume link references
	 */
	protected function consumeReference($lines, $current)
	{
		while (isset($lines[$current]) && preg_match('/^ {0,3}\[(.+?)\]:\s*(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*$/', $lines[$current], $matches)) {
			$label = mb_strtolower($matches[1], 'UTF-8');

			$ref = [
				'url' => $matches[2],
			];
			if (isset($matches[3])) {
				$ref['title'] = $matches[3];
			} else {
				// title may be on the next line
				if (isset($lines[$current + 1]) && preg_match('/^\s+[\(\'"](.+?)[\)\'"]\s*$/', $lines[$current + 1], $matches)) {
					$ref['title'] = $matches[1];
					$current++;
				}
			}
			if (!isset($this->references[$label])) {
				$this->references[$label] = $ref;
			}

			$current++;
		}
		return [false, --$current];
	}

	/**
	 * identify a line as the beginning of a block quote.
	 */
	protected function identifyBlockQuote($line)
	{
		return $line[0] === '>' || $line[0] === ' ' && preg_match('/^ {1,3}>/', $line);
	}

	/**
	 * Consume lines for a blockquote element
	 */
	protected function consumeBlockQuote($lines, $current)
	{
		// TODO do not allow lazy block elements

		// consume until newline
		$content = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if ($line[0] === ' ' && preg_match('/^ {1,3}>(.*)/', $line, $m)) {
				$line = $m[1];
			} elseif (ltrim($line) !== '') {
				if ($line[0] == '>') {
					$line = substr($line, 1);
				}
			} else {
				break;
			}
			$content[] = $line;
		}

		$block = [
			'quote',
			'content' => $this->parseBlocks($content),
			'simple' => true,
		];
		return [$block, $i];
	}

	// TODO lists


	/**
	 * Renders a blockquote
	 */
	protected function renderQuote($block)
	{
		return '<blockquote>' . $this->renderAbsy($block['content']) . "</blockquote>\n";
	}

	/**
	 * Parses escaped special characters.
	 * @marker \
	 */
	protected function parseEscape($text)
	{
		if (isset($text[1]) && in_array($text[1], $this->escapeCharacters)) {
			return [['text', $text[1]], 2];
		} elseif (!isset($text[1]) || $text[1] === "\n") {
			return [['newline'], 1];
		}
		return [['text', $text[0]], 1];
	}

	public function renderNewline()
	{
		return $this->html5 ? "<br>" : "<br />";
	}

	/**
	 * Parses an inline code span `` ` ``.
	 * @marker `
	 */
	protected function parseInlineCode($text)
	{
		if (preg_match('/^(?>(`+))(.*?[^`])\1(?!`)/s', $text, $matches)) { // code with enclosed backtick
			return [
				[
					'inlineCode',
					trim(preg_replace('/\s+/', ' ', $matches[2])),
				],
				strlen($matches[0])
			];
		}
		// remove all starting ` markers to avoid next one to be parsed as code
		$result = '`';
		$i = 1;
		while (isset($text[$i]) && $text[$i] == '`') {
			$result .= '`';
			$i++;
		}
		return [['text', $result], $i];
	}

	protected function renderInlineCode($block)
	{
		return '<code>' . htmlspecialchars($block[1], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
	}

	/**
	 * @inheritdocs
	 *
	 * Parses a newline indicated by two spaces on the end of a markdown line.
	 */
	protected function renderText($text)
	{
		return preg_replace("/ + \n/", $this->html5 ? "<br>\n" : "<br />\n", htmlspecialchars($text[1], ENT_COMPAT | ENT_QUOTES, 'UTF-8'));
	}
}
