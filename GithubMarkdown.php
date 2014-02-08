<?php
/**
 * @author Carsten Brandt <mail@cebe.cc>
 */

namespace cebe\markdown;

/**
 * Markdown parser for github flavored markdown
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class GithubMarkdown extends Markdown
{
	protected $inlineMarkers = [
		// original markdown
		"  \n"  => 'parseNewline',
		'&'     => 'parseEntity',
		'!['    => 'parseImage',
		'*'     => 'parseEmphStrong',
		'_'     => 'parseEmphStrong',
		'<'     => 'parseLt',
		'['     => 'parseLink',
		'\\'    => 'parseEscape',
		'`'     => 'parseCode',
		// GFM
		'http'  => 'parseUrl',
		'~~'    => 'parseStrike',
	];

	protected function identifyLine($lines, $current)
	{
		if (strncmp($lines[$current], '```', 3) === 0) {
			return 'fencedCode';
		}
		return parent::identifyLine($lines, $current);
	}


	// block parsing


	protected function consumeFencedCode($lines, $current)
	{
		// consume until ```

		$block = [
			'type' => 'code',
			'content' => [],
		];
		$language = substr($lines[$current], 3);
		if (!empty($language)) {
			$block['language'] = $language;
		}
		for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (rtrim($line, ' ') !== '```') {
				$block['content'][] = $line;
			} else {
				break;
			}
		}

		return [$block, $i];
	}


	// inline parsing


	protected function parseStrike($text)
	{
		if (preg_match('/^~~(.+?)~~/', $text, $matches)) {
			return [
				'<del>' . $this->parseInline($matches[1]) . '</del>',
				strlen($matches[0])
			];
		}
		return [$text[0] . $text[1], 2];
	}

	protected function parseUrl($text)
	{
		if (preg_match('/^((https?|ftp):\/\/[^ ]+)/', $text, $matches)) {
			return [
				'<a href="' . $matches[1] . '">' . $matches[1] . '</a>', // TODO html encode
				strlen($matches[0])
			];
		}
		return [substr($text, 0, 4), 4];
	}
}