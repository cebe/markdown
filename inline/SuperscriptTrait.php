<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\inline;

/**
 * Adds inline superscript elements
 */
trait SuperscriptTrait {

	/**
	 * Parses the superscript feature
	 * @marker ^
	 */
	protected function parseSuperscript($text)
	{
		if (preg_match('/^\^(.+?)\^/', $text, $matches)) {
			return [
				['superscript', $matches[1]],
				strlen($matches[0])
			];
		}
		return [['text', $text[0]], 1];
	}

	protected function renderSuperscript($block)
	{
		return '<sup>' . htmlspecialchars($block[1], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</sup>';
	}

}
