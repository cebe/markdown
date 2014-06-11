<?php

namespace cebe\markdown;

use cebe\markdown\block\TableTrait;

/**
 * Markdown parser for the [markdown extra](http://michelf.ca/projects/php-markdown/extra/) flavor.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */
class MarkdownExtra extends Markdown
{
	use TableTrait;

	/**
	 * @var bool whether special attributes on code blocks should be applied on the `<pre>` element.
	 * The default behavior is to put them on the `<code>` element.
	 */
	public $codeAttributesOnPre = false;

	/**
	 * @inheritDoc
	 */
	protected $escapeCharacters = [
		// from Markdown
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
		// added by MarkdownExtra
		':', // colon
		'|', // pipe
	];

	private $_specialAttributesRegex = '\{(([#\.][A-z0-9-_]+\s*)+)\}';

	// TODO allow HTML intended 3 spaces

	// TODO add markdown inside HTML blocks

	// TODO implement definition lists

	// TODO implement footnotes

	// TODO implement Abbreviations


	protected function inlineMarkers()
	{
		return parent::inlineMarkers() + [
			'|' => 'parseTd',
		];
	}


	// block parsing


	/**
	 * @inheritDoc
	 */
	protected function identifyLine($lines, $current)
	{
		if (isset($lines[$current]) && (strncmp($lines[$current], '~~~', 3) === 0 || strncmp($lines[$current], '```', 3) === 0)) {
			return 'fencedCode';
		}
		if (preg_match('/^ {0,3}\[(.+?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*('.$this->_specialAttributesRegex.')?\s*$/', $lines[$current])) {
			return 'reference';
		}
		if ($this->identifyTable($lines, $current)) {
			return 'table';
		}
		return parent::identifyLine($lines, $current);
	}

	/**
	 * Consume lines for a headline
	 */
	protected function consumeHeadline($lines, $current)
	{
		list($block, $nextLine) = parent::consumeHeadline($lines, $current);

		if (($pos = strpos($block['content'], '{')) !== false && preg_match("~$this->_specialAttributesRegex~", $block['content'], $matches)) {
			$block['content'] = substr($block['content'], 0, $pos);
			$block['content'] = trim($block['content'], "# \t");
			$block['attributes'] = $matches[1];
		}
		return [$block, $nextLine];
	}

	/**
	 * Consume lines for a fenced code block
	 */
	protected function consumeFencedCode($lines, $current)
	{
		// consume until ```
		$block = [
			'type' => 'code',
			'content' => [],
		];
		$line = rtrim($lines[$current]);
		if (($pos = strrpos($line, '`')) === false) {
			$pos = strrpos($line, '~');
		}
		$fence = substr($line, 0, $pos + 1);
		$block['attributes'] = substr($line, $pos);
		for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
			if (rtrim($line = $lines[$i]) !== $fence) {
				$block['content'][] = $line;
			} else {
				break;
			}
		}
		return [$block, $i];
	}

	/**
	 * Consume link references
	 */
	protected function consumeReference($lines, $current)
	{
		while (isset($lines[$current]) && preg_match('/^ {0,3}\[(.+?)\]:\s*(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*('.$this->_specialAttributesRegex.')?\s*$/', $lines[$current], $matches)) {
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
			if (isset($matches[5])) {
				$this->references[$label]['attributes'] = $matches[5];
			}
			$current++;
		}
		return [false, --$current];
	}

	protected function renderCode($block)
	{
		$attributes = $this->renderAttributes($block);
		return ($this->codeAttributesOnPre ? "<pre$attributes><code>" : "<pre><code$attributes>")
			. htmlspecialchars(implode("\n", $block['content']) . "\n", ENT_NOQUOTES, 'UTF-8')
			. '</code></pre>';
	}

	protected function renderHeadline($block)
	{
		$tag = 'h' . $block['level'];
		$attributes = $this->renderAttributes($block);
		return "<$tag$attributes>" . $this->parseInline($block['content']) . "</$tag>";
	}


	protected function renderAttributes($block)
	{
		$html = [];
		if (isset($block['attributes'])) {
			$attributes = preg_split('/\s+/', $block['attributes'], -1, PREG_SPLIT_NO_EMPTY);
			foreach($attributes as $attribute) {
				if ($attribute[0] === '#') {
					$html['id'] = substr($attribute, 1);
				} else {
					$html['class'][] = substr($attribute, 1);
				}
			}
		}
		$result = '';
		foreach($html as $attr => $value) {
			if (is_array($value)) {
				$value = trim(implode(' ', $value));
			}
			if (!empty($value)) {
				$result .= " $attr=\"$value\"";
			}
		}
		return $result;
	}


	// inline parsing


	/**
	 * Parses a link indicated by `[`.
	 */
	protected function parseLink($markdown)
	{
		if (!in_array('parseLink', array_slice($this->context, 1)) && ($parts = $this->parseLinkOrImage($markdown)) !== false) {
			list($text, $url, $title, $offset, $refKey) = $parts;

			$attributes = '';
			if (isset($this->references[$refKey])) {
				$attributes = $this->renderAttributes($this->references[$refKey]);
			}
			if (isset($markdown[$offset]) && $markdown[$offset] === '{' && preg_match("~^$this->_specialAttributesRegex~", substr($markdown, $offset), $matches)) {
				$attributes = $this->renderAttributes(['attributes' => $matches[1]]);
				$offset += strlen($matches[0]);
			}

			$link = '<a href="' . htmlspecialchars($url, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
				. (empty($title) ? '' : ' title="' . htmlspecialchars($title, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"')
				. $attributes . '>' . $this->parseInline($text) . '</a>';

			return [$link, $offset];
		} else {
			return parent::parseLink($markdown);
		}
	}

	/**
	 * Parses an image indicated by `![`.
	 */
	protected function parseImage($markdown)
	{
		if (($parts = $this->parseLinkOrImage(substr($markdown, 1))) !== false) {
			list($text, $url, $title, $offset, $refKey) = $parts;

			$attributes = '';
			if (isset($this->references[$refKey])) {
				$attributes = $this->renderAttributes($this->references[$refKey]);
			}
			if (isset($markdown[$offset + 1]) && $markdown[$offset + 1] === '{' && preg_match("~^$this->_specialAttributesRegex~", substr($markdown, $offset + 1), $matches)) {
				$attributes = $this->renderAttributes(['attributes' => $matches[1]]);
				$offset += strlen($matches[0]);
			}

			$image = '<img src="' . htmlspecialchars($url, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
				. ' alt="' . htmlspecialchars($text, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
				. (empty($title) ? '' : ' title="' . htmlspecialchars($title, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"')
				. $attributes . ($this->html5 ? '>' : ' />');

			return [$image, $offset + 1];
		} else {
			return parent::parseImage($markdown);
		}
	}
}