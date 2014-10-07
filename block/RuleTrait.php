<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\block;


trait RuleTrait
{
	protected function identify1Hr($line, $lines, $current)
	{
		// at least 3 of -, * or _ on one line make a hr
		return ($line[0] === ' ' || $line[0] === '-' || $line[0] === '*' || $line[0] === '_') && preg_match('/^ {0,3}([\-\*_])\s*\1\s*\1(\1|\s)*$/', $line);
	}

	/**
	 * Consume a horizontal rule
	 */
	protected function consume1Hr($lines, $current)
	{
		$block = ['hr'];
		return [$block, $current];
	}



	/**
	 * Renders a horizontal rule
	 */
	protected function renderHr($block)
	{
		return $this->html5 ? "<hr>\n" : "<hr />\n";
	}

} 