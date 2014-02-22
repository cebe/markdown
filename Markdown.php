<?php

namespace cebe\markdown;

// work around https://github.com/facebook/hhvm/issues/1120
defined('ENT_HTML401') || define('ENT_HTML401', 0);

/**
 * Markdown parser for the [initial markdown spec](http://daringfireball.net/projects/markdown/syntax).
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */
class Markdown extends Parser
{
	/**
	 * @var bool whether to format markup according to HTML5 spec.
	 * Defaults to `false` which means that markup is formatted as HTML4.
	 */
	public $html5 = false;

	/**
	 * @var array these are "escapeable" characters. When using one of these prefixed with a
	 * backslash, the character will be outputted without the backslash and is not interpreted
	 * as markdown.
	 */
	protected $escapeCharacters = [
		'\\', // backslash
		'`', // backtick
		'*', // asterisk
		'_', // underscore
		'{', '}', // curly braces
		'[', ']', // square brackets
		'(', ')', // parentheses
		'#', // hash mark
		'+', // plus sign
		'-', // minus sign (hyphen)
		'.', // dot
		'!', // exclamation mark
		'<', '>',
	];
	/**
	 * @var array a list of defined references in this document.
	 */
	protected $references = [];

	// http://www.w3.org/wiki/HTML/Elements#Text-level_semantics
	protected $inlineHtmlElements = [
		'a', 'abbr', 'acronym',
		'b', 'basefont', 'bdo', 'big', 'br', 'button', 'blink',
		'cite', 'code',
		'del', 'dfn',
		'em',
		'font',
		'i', 'img', 'ins', 'input', 'iframe',
		'kbd',
		'label', 'listing',
		'map', 'mark',
		'nobr',
		'object',
		'q',
		'rp', 'rt', 'ruby',
		's', 'samp', 'script', 'select', 'small', 'spacer', 'span', 'strong', 'sub', 'sup',
		'tt', 'var',
		'u',
		'wbr',
		'time',
	];

	protected $selfClosingHtmlElements = [
		'br', 'hr', 'img', 'input', 'nobr',
	];

	/**
	 * @inheritDoc
	 */
	protected function inlineMarkers()
	{
		return [
			"  \n"  => 'parseNewline',
			'&'     => 'parseEntity',
			'!['    => 'parseImage',
			'*'     => 'parseEmphStrong',
			'_'     => 'parseEmphStrong',
			'<'     => 'parseLt',
			'>'     => 'parseGt',
			'['     => 'parseLink',
			'\\'    => 'parseEscape',
			'`'     => 'parseCode',
		];
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
	 * @inheritDoc
	 */
	protected function identifyLine($lines, $current)
	{
		$line = $lines[$current];
		switch($line[0])
		{
			case '<': // HTML block

				if (isset($line[1]) && $line[1] == ' ') {
					break; // no html tag
				}
				if (strncmp($line, '<!--', 4) === 0) {
					return 'html'; // a html comment
				}

				$gtPos = strpos($lines[$current], '>');
				$spacePos = strpos($lines[$current], ' ');
				if ($gtPos === false && $spacePos === false) {
					break; // no html tag
				} elseif ($spacePos === false) {
					$tag = rtrim(substr($line, 1, $gtPos - 1), '/');
				} else {
					$tag = rtrim(substr($line, 1, min($gtPos, $spacePos) - 1), '/');
				}

				if (!ctype_alnum($tag) || in_array(strtolower($tag), $this->inlineHtmlElements)) {
					break; // no html tag or inline html tag
				}

				return 'html';
			case '>': // quote
				if (!isset($line[1]) || $line[1] == ' ' || $line[1] == "\t") {
					return 'quote';
				}
				break;
			case '_':
				// at least 3 of -, * or _ on one line make a hr
				if (preg_match('/^(_)\s*\1\s*\1(\1|\s)*$/', $line)) {
					return 'hr';
				}
				break;
			case '-':
			case '+':
			case '*':
				// at least 3 of -, * or _ on one line make a hr
				if (preg_match('/^([\-\*])\s*\1\s*\1(\1|\s)*$/', $line)) {
					return 'hr';
				}

				if (isset($line[1]) && ($line[1] == ' ' || $line[1] == "\t")) {
					return 'ul';
				}
				break;
			case '#':
				return 'headline';
			case '[': // reference

				if (preg_match('/^\[(.+?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*$/', $line)) {
					return 'reference';
				}
				break;
			case "\t":
				return 'code';
			case ' ':
				// indentation >= 4 is code
				if (strncmp($line, '    ', 4) === 0) {
					return 'code';
				}

				// at least 3 of -, * or _ on one line make a hr
				if (preg_match('/^ {0,3}([\-\*_])\s*\1\s*\1(\1|\s)*$/', $line)) {
					return 'hr';
				}

				// could be indented list
				if (preg_match('/^ {0,3}[\-\+\*] /', $line)) {
					return 'ul';
				}

				// could be indented reference
				if (preg_match('/^ {0,3}\[(.+?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*$/', $line)) {
					return 'reference';
				}

			// no break;
			default:
				if (preg_match('/^ {0,3}\d+\.[ \t]/', $line)) {
					return 'ol';
				}
		}
		
		if (!empty($lines[$current + 1]) && ($lines[$current + 1][0] === '=' || $lines[$current + 1][0] === '-') &&
			preg_match('/^(\-+|=+)\s*$/', $lines[$current + 1])) {

			return 'headline';
		}

		return 'paragraph';
	}

	/**
	 * Consume lines for a paragraph
	 */
	public function consumeParagraph($lines, $current)
	{
		// consume until newline or intended line

		$block = [
			'type' => 'paragraph',
			'content' => [],
		];
		for($i = $current, $count = count($lines); $i < $count; $i++) {
			if (ltrim($lines[$i]) !== '' && $lines[$i][0] != "\t" && strncmp($lines[$i], '    ', 4) !== 0) {
				$block['content'][] = $lines[$i];
			} else {
				break;
			}
		}

		return [$block, --$i];
	}

	/**
	 * Consume lines for a blockquote element
	 */
	protected function consumeQuote($lines, $current)
	{
		// consume until newline

		$block = [
			'type' => 'quote',
			'content' => [],
			'simple' => true,
		];
		for($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (ltrim($line) !== '') {
				if ($line[0] == '>' && !isset($line[1])) {
					$line = '';
				} elseif (strncmp($line, '> ', 2) === 0) {
					$line = substr($line, 2);
				}
				$block['content'][] = $line;
			} else {
				break;
			}
		}

		return [$block, $i];
	}

	/**
	 * Consume lines for a code block element
	 */
	protected function consumeCode($lines, $current)
	{
		// consume until newline

		$block = [
			'type' => 'code',
			'content' => [],
		];
		for($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];

			// a line is considered to belong to this code block as long as it is intended by 4 spaces or a tab
			if (isset($line[0]) && ($line[0] === "\t" || strncmp($lines[$i], '    ', 4) === 0)) {
				$line = $line[0] === "\t" ? substr($line, 1) : substr($line, 4);
				$block['content'][] = $line;
			// but also if it is empty and the next line is intended by 4 spaces or a tab
			} elseif ((empty($line) || rtrim($line) === '') && isset($lines[$i + 1][0]) &&
					  ($lines[$i + 1][0] === "\t" || strncmp($lines[$i + 1], '    ', 4) === 0)) {
				if (!empty($line)) {
					$line = $line[0] === "\t" ? substr($line, 1) : substr($line, 4);
				}
				$block['content'][] = $line;
			} else {
				break;
			}
		}

		return [$block, --$i];
	}

	/**
	 * Consume lines for an ordered list
	 */
	protected function consumeOl($lines, $current)
	{
		// consume until newline

		$block = [
			'type' => 'list',
			'list' => 'ol',
			'items' => [],
		];
		return $this->consumeList($lines, $current, $block, 'ol');
	}

	/**
	 * Consume lines for an unordered list
	 */
	protected function consumeUl($lines, $current)
	{
		// consume until newline

		$block = [
			'type' => 'list',
			'list' => 'ul',
			'items' => [],
		];
		return $this->consumeList($lines, $current, $block, 'ul');
	}

	private function consumeList($lines, $current, $block, $type)
	{
		$item = 0;
		$indent = '';
		$len = 0;
		for($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];

			// match list marker on the beginning of the line
			if (preg_match($type == 'ol' ? '/^ {0,3}\d+\.\s+/' : '/^ {0,3}[\-\+\*]\s+/', $line, $matches)) {
				if (($len = substr_count($matches[0], "\t")) > 0) {
					$indent = str_repeat("\t", $len);
					$line = substr($line, strlen($matches[0]));
				} else {
					$len = strlen($matches[0]);
					$indent = str_repeat(' ', $len);
					$line = substr($line, $len);
				}

				$block['items'][++$item][] = $line;
			} elseif (ltrim($line) === '') {
				// next line after empty one is also a list or indented -> lazy list
				if (isset($lines[$i + 1][0]) && (
					$this->identifyLine($lines, $i + 1) === $type ||
					(strncmp($lines[$i + 1], $indent, $len) === 0 || !empty($lines[$i + 1]) && $lines[$i + 1][0] == "\t"))) {
					$block['items'][$item][] = $line;
					$block['lazyItems'][$item] = true;
				} else {
					break;
				}
			} else {
				if ($line[0] === "\t") {
					$line = substr($line, 1);
				} elseif (strncmp($line, $indent, $len) === 0) {
					$line = substr($line, $len);
				}
				$block['items'][$item][] = $line;
			}
		}

		// make last item lazy if item before was lazy
		if (isset($block['lazyItems'][$item - 1])) {
			$block['lazyItems'][$item] = true;
		}

		return [$block, $i];
	}

	/**
	 * Consume lines for a headline
	 */
	protected function consumeHeadline($lines, $current)
	{
		if ($lines[$current][0] === '#') {
			$level = 1;
			while(isset($lines[$current][$level]) && $lines[$current][$level] === '#' && $level < 6) {
				$level++;
			}
			$block = [
				'type' => 'headline',
				'content' => trim($lines[$current], "# \t"),
				'level' => $level,
			];
			return [$block, $current];
		}

		$block = [
			'type' => 'headline',
			'content' => $lines[$current],
			'level' => $lines[$current + 1][0] === '=' ? 1 : 2,
		];
		return [$block, $current + 1];
	}

	/**
	 * Consume lines for an HTML block
	 */
	protected function consumeHtml($lines, $current)
	{
		$block = [
			'type' => 'html',
			'content' => [],
		];
		if (strncmp($lines[$current], '<!--', 4) === 0) { // html comment
			for($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$block['content'][] = $line;
				if (strpos($line, '-->') !== false) {
					break;
				}
			}
		} else {
			$tag = rtrim(substr($lines[$current], 1, min(strpos($lines[$current], '>'), strpos($lines[$current] . ' ', ' ')) - 1), '/');
			$level = 0;
			if (in_array($tag, $this->selfClosingHtmlElements)) {
				$level--;
			}
			for($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$block['content'][] = $line;
				$level += substr_count($line, "<$tag") - substr_count($line, "</$tag>");
				if ($level <= 0) {
					break;
				}
			}
		}
		return [$block, $i];
	}

	/**
	 * Consume a horizontal rule
	 */
	protected function consumeHr($lines, $current)
	{
		$block = [
			'type' => 'hr',
		];
		return [$block, $current];
	}

	/**
	 * Consume link references
	 */
	protected function consumeReference($lines, $current)
	{
		while (isset($lines[$current]) && preg_match('/^ {0,3}\[(.+?)\]:\s*(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*$/', $lines[$current], $matches)) {
			$label = strtolower($matches[1]);

			$this->references[$label] = [
				'url' => $matches[2],
			];
			if (isset($matches[3])) {
				$this->references[$label]['title'] = $matches[3];
			} else {
				// title may be on the next line
				if (isset($lines[$current + 1]) && preg_match('/^\s+[\(\'"](.+?)[\)\'"]\s*$/', $lines[$current + 1], $matches)) {
					$this->references[$label]['title'] = $matches[1];
					$current++;
				}
			}
			$current++;
		}
		return [false, --$current];
	}


	// rendering


	/**
	 * Renders a blockquote
	 */
	protected function renderQuote($block)
	{
		return '<blockquote>' . $this->parseBlocks($block['content']) . '</blockquote>';
	}

	/**
	 * Renders a code block
	 */
	protected function renderCode($block)
	{
		$class = isset($block['language']) ? ' class="language-' . $block['language'] . '"' : '';
		return "<pre><code$class>" . htmlspecialchars(implode("\n", $block['content']) . "\n", ENT_NOQUOTES, 'UTF-8') . '</code></pre>';
	}

	/**
	 * Renders a list
	 */
	protected function renderList($block)
	{
		$type = $block['list'];
		$output = "<$type>\n";
		foreach($block['items'] as $item => $itemLines) {
			$output .= '<li>';
			if (!isset($block['lazyItems'][$item])) {
				$firstPar = [];
				while(!empty($itemLines) && rtrim($itemLines[0]) !== '' && $this->identifyLine($itemLines, 0) === 'paragraph') {
					$firstPar[] = array_shift($itemLines);
				}
				$output .= $this->parseInline(implode("\n", $firstPar));
			}
			if (!empty($itemLines)) {
				$output .= $this->parseBlocks($itemLines);
			}
			$output .= "</li>\n";
		}
		return $output . "</$type>";
	}

	/**
	 * Renders a headline
	 */
	protected function renderHeadline($block)
	{
		$tag = 'h' . $block['level'];
		return "<$tag>" . $this->parseInline($block['content']) . "</$tag>";
	}

	/**
	 * Renders an HTML block
	 */
	protected function renderHtml($block)
	{
		return implode("\n", $block['content']);
	}

	/**
	 * Renders a horizontal rule
	 */
	protected function renderHr($block)
	{
		return $this->html5 ? '<hr>' : '<hr />';
	}


	// inline parsing


	/**
	 * Parses a newline indicated by two spaces on the end of a markdown line.
	 */
	protected function parseNewline($text)
	{
		return [
			$this->html5 ? "<br>\n" : "<br />\n",
			3
		];
	}

	/**
	 * Parses an & or a html entity definition.
	 */
	protected function parseEntity($text)
	{
		// html entities e.g. &copy; &#169; &#x00A9;
		if (preg_match('/^&#?[\w\d]+;/', $text, $matches)) {
			return [$matches[0], strlen($matches[0])];
		} else {
			return ['&amp;', 1];
		}
	}

	/**
	 * Parses inline HTML.
	 */
	protected function parseLt($text)
	{
		if (strpos($text, '>') !== false) {
			if (preg_match('/^<(.*?@.*?\.\w+?)>/', $text, $matches)) { // TODO improve patterns
				$email = htmlspecialchars($matches[1], ENT_NOQUOTES, 'UTF-8');
				return [
					"<a href=\"mailto:$email\">$email</a>", // TODO encode mail with entities
					strlen($matches[0])
				];
			} elseif (preg_match('/^<([a-z]{3,}:\/\/.+?)>/', $text, $matches)) { // TODO improve patterns
				$url = htmlspecialchars($matches[1], ENT_COMPAT | ENT_HTML401, 'UTF-8');
				$text = htmlspecialchars(urldecode($matches[1]), ENT_NOQUOTES, 'UTF-8');
				return ["<a href=\"$url\">$text</a>", strlen($matches[0])];
			} elseif (preg_match('~^</?(\w+\d?)( .*?)?>~', $text, $matches)) {
				// HTML tags
				return [$matches[0], strlen($matches[0])];
			} elseif (preg_match('~^<!--.*?-->~', $text, $matches)) {
				// HTML comments
				return [$matches[0], strlen($matches[0])];
			}
		}
		return ['&lt;', 1];
	}

	/**
	 * Escapes `>` characters.
	 */
	protected function parseGt($text)
	{
		return ['&gt;', 1];
	}

	/**
	 * Parses escaped special characters.
	 */
	protected function parseEscape($text)
	{
		if (isset($text[1]) && in_array($text[1], $this->escapeCharacters)) {
			return [$text[1], 2];
		}
		return [$text[0], 1];
	}

	/**
	 * Parses a link indicated by `[`.
	 */
	protected function parseLink($markdown)
	{
		if (($parts = $this->parseLinkOrImage($markdown)) !== false) {
			list($text, $url, $title, $offset) = $parts;

			$link = '<a href="' . htmlspecialchars($url, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
				. (empty($title) ? '' : ' title="' . htmlspecialchars($title, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"')
				. '>' . $this->parseInline($text) . '</a>';

			return [$link, $offset];
	    } else {
		    // remove all starting [ markers to avoid next one to be parsed as link
		    $result = '[';
		    $i = 1;
		    while(isset($markdown[$i]) && $markdown[$i] == '[') {
			    $result .= '[';
			    $i++;
		    }
		    return [$result, $i];
	    }
	}

	/**
	 * Parses an image indicated by `![`.
	 */
	protected function parseImage($markdown)
	{
		if (($parts = $this->parseLinkOrImage(substr($markdown, 1))) !== false) {
			list($text, $url, $title, $offset) = $parts;

			$image = '<img src="' . htmlspecialchars($url, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
				. ' alt="' . htmlspecialchars($text, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
				. (empty($title) ? '' : ' title="' . htmlspecialchars($title, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"')
				. ($this->html5 ? '>' : ' />');

			return [$image, $offset + 1];
		} else {
			// remove all starting [ markers to avoid next one to be parsed as link
			$result = '!';
			$i = 1;
			while(isset($markdown[$i]) && $markdown[$i] == '[') {
				$result .= '[';
				$i++;
			}
			return [$result, $i];
		}
	}

	private function parseLinkOrImage($markdown)
	{
		if (strpos($markdown, ']') !== false && preg_match('/\[((?:[^][]|(?R))*)\]/', $markdown, $textMatches)) { // TODO improve bracket regex
			$text = $textMatches[1];
			$offset = strlen($textMatches[0]);
			$markdown = substr($markdown, $offset);

			if (preg_match('/^\(([^\s]*?)(\s+"(.*?)")?\)/', $markdown, $refMatches)) {
				// inline link
				return [
					$text,
					$refMatches[1], // url
					empty($refMatches[3]) ? null: $refMatches[3], // title
					$offset + strlen($refMatches[0]), // offset
				];
			} elseif (preg_match('/^[ \n]?\[(.*?)\]/', $markdown, $refMatches)) {
				// reference style link
				if (empty($refMatches[1])) {
					$key = strtolower($text);
				} else {
					$key = strtolower($refMatches[1]);
				}
				if (isset($this->references[$key])) {
					return [
						$text,
						$this->references[$key]['url'], // url
						empty($this->references[$key]['title']) ? null: $this->references[$key]['title'], // title
						$offset + strlen($refMatches[0]), // offset
					];
				}
			}
		}
		return false;
	}

	/**
	 * Parses an inline code span `` ` ``.
	 */
	protected function parseCode($text)
	{
		if (preg_match('/^(`+) (.+?) \1/', $text, $matches)) { // code with enclosed backtick
			return [
				'<code>' . htmlspecialchars($matches[2], ENT_NOQUOTES, 'UTF-8') . '</code>',
				strlen($matches[0])
			];
		} elseif (preg_match('/^`(.+?)`/', $text, $matches)) {
			return [
				'<code>' . htmlspecialchars($matches[1], ENT_NOQUOTES, 'UTF-8') . '</code>',
				strlen($matches[0])
			];
		}
		return [$text[0], 1];
	}

	/**
	 * Parses empathized and strong elements.
	 */
	protected function parseEmphStrong($text)
	{
		$marker = $text[0];

		if (!isset($text[1])) {
			return [$text[0], 1];
		}

		if ($marker == $text[1]) { // strong
			if ($marker == '*' && preg_match('/^[*]{2}((?:[^*]|[*][^*]*[*])+?)[*]{2}(?![*])/s', $text, $matches) ||
				$marker == '_' && preg_match('/^__((?:[^_]|_[^_]*_)+?)__(?!_)/us', $text, $matches)) {

				return ['<strong>' . $this->parseInline($matches[1]) . '</strong>', strlen($matches[0])];
			}
		} else { // emph
			if ($marker == '*' && preg_match('/^[*]((?:[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s', $text, $matches) ||
				$marker == '_' && preg_match('/^_((?:[^_]|__[^_]*__)+?)_(?!_)\b/us', $text, $matches)) {
				return ['<em>' . $this->parseInline($matches[1]) . '</em>', strlen($matches[0])];
			}
		}
		return [$text[0], 1];
	}
}