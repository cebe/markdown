<?php

namespace cebe\markdown;

/**
 * Markdown parser for github flavored markdown
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */
class GithubMarkdown extends Markdown
{
	/**
	 * @var boolean whether to interpret newlines as `<br />`-tags.
	 * This feature is useful for comments where newlines are often meant to be real new lines.
	 */
	public $enableNewlines = false;

	/**
	 * @inheritDoc
	 */
	protected function inlineMarkers()
	{
		$markers = [
			'http'  => 'parseUrl',
			'ftp'   => 'parseUrl',
			'~~'    => 'parseStrike',
		];

		if ($this->enableNewlines) {
			$markers["\n"] = 'parseDirectNewline';
		}

		return array_merge(parent::inlineMarkers(), $markers);
	}


	// block parsing


	/**
	 * @inheritDoc
	 */
	protected function identifyLine($lines, $current)
	{
		if (isset($lines[$current]) && strncmp($lines[$current], '```', 3) === 0) {
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
		$fence = substr($line, 0, $pos = strrpos($line, '`') + 1);
		$language = substr($line, $pos);
		if (!empty($language)) {
			$block['language'] = $language;
		}
		for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
			if (rtrim($line = $lines[$i]) !== $fence) {
				$block['content'][] = $line;
			} else {
				break;
			}
		}
		return [$block, $i];
	}


	// inline parsing


	/**
	 * Parses the strikethrough feature.
	 */
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

	/**
	 * Parses urls and adds auto linking feature.
	 */
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