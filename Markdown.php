<?php

namespace cebe\markdown;

/**
 * Markdown parser for the [initial markdown spec](http://daringfireball.net/projects/markdown/syntax)
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class Markdown extends Parser
{
	public $html5 = false;

	protected $inlineMarkers = [
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
//		'http'  => 'parseUrl', // GFM
//		'~~'    => 'parseStrike', // GFM
	];

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
	];


	// block parsing


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

	protected function identifyLine($lines, $current)
	{
		if (empty($lines[$current]) || trim($lines[$current], ' ') === '') {
			return 'empty';
		}
		$line = $lines[$current];
		switch($line[0])
		{
			case '<': // HTML block

				if (isset($line[1]) && $line[1] == ' ') {
					break; // no html tag
				}
				$gtPos = strpos($lines[$current], '>');
				$spacePos = strpos($lines[$current], ' ');
				if ($gtPos === false && $spacePos === false) {
					break; // no html tag
				}

				$tag = substr($line, 1, min($gtPos, $spacePos) - 1);
				if (!ctype_alnum($tag) || in_array(strtolower($tag), $this->inlineHtmlElements)) {
					break; // no html tag or inline html tag
				}

				return 'html';
			case '>': // quote
				if (!isset($line[1]) || $line[1] == ' ') {
					return 'quote';
				}
				break;
			case '-':
			case '+':
			case '*':
				// at least 3 of - or * on one line make a hr
				// * * *
				// ***
				// *****
				// - - -
				// -------------------------------
				if (preg_match('/^([-*])\s*\1\s*\1[\1\s]*$/', $line)) {
					return 'hr';
				}

				if (isset($line[1]) && $line[1] == ' ') {
					return 'ul';
				}
				break;
			case '#':
				return 'headline';
			case ' ':
				// indentation >= 4 is code
				if (strncmp($line, '    ', 4) === 0) {
					return 'code';
				}

				// could be indented list
				if (preg_match('/^ {0,3}[\-\+\*] /', $line)) {
					return 'ul';
				}

				// no break;
			default:
				if (preg_match('/^ {0,3}\d+\. /', $line)) {
					return 'ol';
				}
		}

		// TODO improve
		if (isset($lines[$current + 1]) && !empty($lines[$current + 1]) && ($lines[$current + 1][0] === '=' || $lines[$current + 1][0] === '-')) {
			if (preg_match('/^(\-+|=+)\s*$/', $lines[$current + 1])) {
				return 'headline';
			}
		}

		return 'paragraph';
	}

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
			if (ltrim($line, ' ') !== '') {
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

	protected function consumeCode($lines, $current)
	{
		// consume until newline

		$block = [
			'type' => 'code',
			'content' => [],
		];
		for($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (ltrim($line, ' ') !== '' || $this->identifyLine($lines, $i + 1) === 'code') {
				$line = substr($line, 4);
				$block['content'][] = $line;
			} else {
				break;
			}
		}

		return [$block, $i];
	}

	protected function consumeOl($lines, $current)
	{
		// consume until newline

		$block = [
			'type' => 'list',
			'list' => 'ol',
			'items' => [],
			'lazy' => false, // TODO support lazy list
		];
		$item = 0;
		$indent = '';
		$len = 0;
		for($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];

			if (preg_match('/^ {0,3}\d+\. +/', $line, $matches)) {
				$len = strlen($matches[0]);
				$indent = str_repeat(' ', $len);

				$line = substr($line, $len);
				$block['items'][++$item][] = $line;
			} elseif (ltrim($line, ' ') !== '') {
				if (strncmp($line, $indent, $len) === 0) {
					$line = substr($line, $len);
				}
				$block['items'][$item][] = $line;
			} else {
				break;
			}
		}

		return [$block, $i];
	}

	protected function consumeUl($lines, $current)
	{
		// consume until newline

		$block = [
			'type' => 'list',
			'list' => 'ul',
			'items' => [],
			'lazy' => false, // TODO support lazy list
		];
		$item = 0;
		$indent = '';
		$len = 0;
		for($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];

			if (preg_match('/^ {0,3}[\-\+\*] +/', $line, $matches)) {
				$len = strlen($matches[0]);
				$indent = str_repeat(' ', $len);

				$line = substr($line, $len);
				$block['items'][++$item][] = $line;
			} elseif (ltrim($line, ' ') !== '') {
				if (strncmp($line, $indent, $len) === 0) {
					$line = substr($line, $len);
				}
				$block['items'][$item][] = $line;
			} else {
				break;
			}
		}

		return [$block, $i];
	}

	protected function consumeHeadline($lines, $current)
	{
		if ($lines[$current][0] === '#') {
			$level = 1;
			while(isset($lines[$current][$level]) && $lines[$current][$level] === '#') {
				$level++;
			}
			$block = [
				'type' => 'headline',
				'content' => trim($lines[$current], '# '),
				'level' => $level,
			];
			return [$block, ++$current];
		}

		$block = [
			'type' => 'headline',
			'content' => $lines[$current],
			'level' => $lines[$current + 1][0] === '=' ? 1 : 2,
		];
		return [$block, $current + 2];
	}

	protected function consumeHtml($lines, $current)
	{
		$block = [
			'type' => 'html',
			'content' => [],
		];
		$level = 0;
		$tag = substr($lines[$current], 1, min(strpos($lines[$current], '>'), strpos($lines[$current] . ' ', ' ')) - 1);
		for($i = $current, $count = count($lines); $i < $lines; $i++) {
			$line = $lines[$i];
			$block['content'][] = $line;
			$level += substr_count($line, "<$tag") - substr_count($line, "</$tag>");
			if ($level <= 0) {
				break;
			}
		}
		return [$block, $i];
	}

	protected function consumeHr($lines, $current)
	{
		$block = [
			'type' => 'hr',
		];
		return [$block, $current + 1];
	}

	// rendering

	protected function renderQuote($block)
	{
		return '<blockquote>' . $this->parseBlocks($block['content']) . '</blockquote>';
	}

	protected function renderCode($block)
	{
		$class = isset($block['language']) ? ' class="language-' . $block['language'] . '"' : '';
		return "<pre><code$class>" . htmlspecialchars(implode("\n", $block['content']) . "\n", ENT_NOQUOTES, 'UTF-8') . '</code></pre>';
	}

	protected function renderList($block)
	{
		$type = $block['list'];
		$output = "<$type>\n";
		foreach($block['items'] as $itemLines) {
			$output .= '<li>';
			if (!$block['lazy']) {
				$firstPar = [];
				while(!empty($itemLines) && $this->identifyLine($itemLines, 0) === 'paragraph') {
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

	protected function renderHeadline($block)
	{
		$tag = 'h' . $block['level'];
		return "<$tag>" . $this->parseInline($block['content']) . "</$tag>";
	}

	protected function renderHtml($block)
	{
		return implode("\n", $block['content']);
	}

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
	 * Parses inline html
	 */
	protected function parseLt($text)
	{
		if (strpos($text, '>') !== false)
		{
			if (preg_match('/^<(.*?@.*?\.\w+?)>/', $text, $matches)) { // TODO improve patterns
				$email = htmlspecialchars($matches[1], ENT_NOQUOTES, 'UTF-8');
				return [
					"<a href=\"mailto:$email\">$email</a>", // TODO encode mail with entities
					strlen($matches[0])
				];
			} elseif (preg_match('/^<([a-z]{3,}:\/\/.+?)>/', $text, $matches)) { // TODO improve patterns
				$url = htmlspecialchars($matches[1], ENT_NOQUOTES, 'UTF-8');
				return ["<a href=\"$url\">$url</a>", strlen($matches[0])];
			} elseif (preg_match('/^<\/?\w.*?>/', $text, $matches)) {
				return [$matches[0], strlen($matches[0])];
			}
		}
		return ['&lt;', 1];
	}

	/**
	 * Escape >
	 */
	protected function parseGt($text)
	{
		return ['&gt;', 1];
	}

	private $specialCharacters = [
		'\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '>', '#', '+', '-', '.', '!',
	];

	/**
	 * Parses escaped special characters
	 */
	protected function parseEscape($text)
	{
		if (in_array($text[1], $this->specialCharacters)) {
			return [$text[1], 2];
		}
		return [$text[0], 1];
	}

	protected function parseLink($text)
	{
		if (preg_match('/^\[(.*?)\]\(([^\s]+)( ".*?")?\)/', $text, $matches)) {
			$url = htmlspecialchars($matches[2], ENT_NOQUOTES, 'UTF-8');
			$link = "<a href=\"$url\"";
			if (!empty($matches[3])) {
				$title = htmlspecialchars($matches[3], ENT_NOQUOTES, 'UTF-8');
				$link .= " title=\"$title\"";
			}
			$link .= '>' . $this->parseInline($matches[1]) . '</a>';

			return [$link, strlen($matches[0])];
		}
		// TODO support references
		return [$text[0], 1];
	}

	protected function parseImage($text)
	{
		if (preg_match('/^!\[(.*?)\]\(([^\s]+)( ".*?")\)', $text, $matches)) {
			$link = "<img src=\"{$matches[2]}\"";
			if (!empty($matches[3])) {
				$link .= " title=\"{$matches[3]}\"";
			}
			$link .= '>' . $matches[1] . '</a>';

			return [$link, strlen($matches[0])];
		}
		// TODO support references
		return [$text[0], 1];
	}

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

	protected function parseEmphStrong($text)
	{
		$marker = $text[0];

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