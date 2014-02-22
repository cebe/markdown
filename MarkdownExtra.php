<?php

namespace cebe\markdown;

/**
 * Markdown parser for the [markdown extra](http://michelf.ca/projects/php-markdown/extra/) flavor.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */
class MarkdownExtra extends Markdown
{
	/**
	 * @var array these are "escapeable" characters. When using one of these prefixed with a
	 * backslash, the character will be outputted without the backslash and is not interpreted
	 * as markdown.
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


	// TODO allow HTML intended 3 spaces

	// TODO add markdown inside HTML blocks

	// TODO allow following elements to have id and class attributes:
	/*

    headers,
    fenced code blocks,
    links, and
    images.

	*/

	// block parsing


	/**
	 * @inheritDoc
	 */
	protected function identifyLine($lines, $current)
	{
		if (isset($lines[$current]) && (strncmp($lines[$current], '~~~', 3) === 0 || strncmp($lines[$current], '```', 3) === 0)) {
			return 'fencedCode';
		}
		return parent::identifyLine($lines, $current);
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
		// TODO this is not language but additional information
//		$language = substr($line, $pos);
//		if (!empty($language)) {
//			$block['language'] = $language;
//		}
		for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
			if (rtrim($line = $lines[$i]) !== $fence) {
				$block['content'][] = $line;
			} else {
				break;
			}
		}
		return [$block, $i];
	}

	// TODO implement tables

	// TODO implement definition lists

	// TODO implement footnotes

	// TODO implement Abbreviations
}