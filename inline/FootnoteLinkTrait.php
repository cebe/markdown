<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\inline;

/**
 * Adds inline footnote link elements
 */
trait FootnoteLinkTrait {

	/**
	 * Parses a footnote link indicated by `[^`.
	 * @marker [^
	 */
	protected function parseFootnoteLink($text)
	{
		if (preg_match('/^\[\^(.+?)\]/', $text, $matches)) {
			return [
				['footnoteLink', $matches[1]],
				strlen($matches[0])
			];
		}
		return [['text', $text[0]], 1];
	}

	protected function renderFootnoteLink($block)
	{
		$text = htmlspecialchars($block[1], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return '<sup id="fnref:' . $text . '"><a href="#fn:' . $text . '" class="footnote-ref" rel="footnote">' . $text . '</a></sup>';
	}

}
