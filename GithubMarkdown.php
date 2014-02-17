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
	/**
	 * @var boolean whether to interpret newlines as `<br />`-tags.
	 * This feature is useful for comments where newlines are often meant to be real new lines.
	 */
	public $enableNewlines = false;

	protected $inlineMarkers = [
		// original markdown
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
		// GFM
		'http'  => 'parseUrl',
		'ftp'   => 'parseUrl',
		'~~'    => 'parseStrike',
	];

	public function prepare()
	{
		parent::prepare();

		if ($this->enableNewlines) {
			$this->inlineMarkers["\n"] = 'parseDirectNewline';
		} else {
			unset($this->inlineMarkers["\n"]);
		}
	}

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

		// TODO allow more than 3 `, also support ~?

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


	protected function parseStrike($markdown)
	{
		if (preg_match('/^~~(.+?)~~/', $markdown, $matches)) {
			return [
				'<del>' . $this->parseInline($matches[1]) . '</del>',
				strlen($matches[0])
			];
		}
		return [$markdown[0] . $markdown[1], 2];
	}

	protected function parseUrl($markdown)
	{
		if (preg_match('/^((https?|ftp):\/\/[^ ]+)/', $markdown, $matches)) {
			$url = htmlspecialchars($matches[1], ENT_COMPAT | ENT_HTML401, 'UTF-8');
			$text = htmlspecialchars(urldecode($matches[1]), ENT_NOQUOTES, 'UTF-8');
			return [
				'<a href="' . $url . '">' . $text . '</a>',
				strlen($matches[0])
			];
		}
		return [substr($markdown, 0, 4), 4];
	}

	/**
	 * Parses a newline indicated by a direct line break. This is only used when `enableNewlines` is true.
	 */
	protected function parseDirectNewline($markdown)
	{
		return [
			$this->html5 ? "<br>\n" : "<br />\n",
			1
		];
	}
}