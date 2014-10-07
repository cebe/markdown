<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown;

use cebe\markdown\block\TableTrait;

/**
 * Markdown parser for github flavored markdown.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class GithubMarkdown extends Markdown
{
	use TableTrait;

	/**
	 * @var boolean whether to interpret newlines as `<br />`-tags.
	 * This feature is useful for comments where newlines are often meant to be real new lines.
	 */
	public $enableNewlines = false;

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
		// added by GithubMarkdown
		':', // colon
		'|', // pipe
	];


	// block parsing


	public function identifyFencedCode($line)
	{
		return strncmp($line, '```', 3) === 0 || strncmp($line, '~~~', 3) === 0;
	}

	/**
	 * Consume lines for a fenced code block
	 */
	protected function consumeFencedCode($lines, $current)
	{
		// consume until ```
		$block = [
			'code',
			'content' => [],
		];
		$line = rtrim($lines[$current]);
		$fence = substr($line, 0, $pos = strrpos($line, $line[0]) + 1);
		$language = substr($line, $pos);
		if (!empty($language)) {
			$block['language'] = $language;
		}
		for ($i = $current + 1, $count = count($lines); $i < $count; $i++) {
			if (rtrim($line = $lines[$i]) !== $fence) {
				$block['content'][] = $line;
			} else {
				break;
			}
		}
		$block['content'] = implode("\n", $block['content']);

		return [$block, $i];
	}


	// inline parsing


	/**
	 * Parses the strikethrough feature.
	 * @marker ~~
	 */
	protected function parseStrike($markdown)
	{
		if (preg_match('/^~~(.+?)~~/', $markdown, $matches)) {
			return [
				[
					'strike',
					$this->parseInline($matches[1])
				],
				strlen($matches[0])
			];
		}
		return [['text', $markdown[0] . $markdown[1]], 2];
	}

	protected function renderStrike($block)
	{
		return '<del>' . $this->renderAbsy($block[1]) . '</del>';
	}

	/**
	 * Parses urls and adds auto linking feature.
	 * @marker http
	 * @marker ftp
	 */
	protected function parseUrl($markdown)
	{
		$pattern = <<<REGEXP
			/(?(R) # in case of recursion match parentheses
				 \(((?>[^\s()]+)|(?R))*\)
			|      # else match a link with title
				^(https?|ftp):\/\/(([^\s()]+)|(?R))+(?<![\.,:;\'"!\?\s])
			)/x
REGEXP;

		if (!in_array('parseLink', $this->context) && preg_match($pattern, $markdown, $matches)) {
			$href = htmlspecialchars($matches[0], ENT_COMPAT | ENT_HTML401, 'UTF-8');
			$text = htmlspecialchars(urldecode($matches[0]), ENT_NOQUOTES, 'UTF-8');
			return [
				['text', "<a href=\"$href\">$text</a>"], // TODO
				strlen($matches[0])
			];
		}
		return [['text', substr($markdown, 0, 4)], 4];
	}

	/**
	 * @inheritdocs
	 *
	 * Parses a newline indicated by two spaces on the end of a markdown line.
	 */
	protected function renderText($text)
	{
		if ($this->enableNewlines) {
			return preg_replace("/(  \n|\n)/", $this->html5 ? "<br>\n" : "<br />\n", $text[1]);
		} else {
			return parent::renderText($text);
		}
	}
}
