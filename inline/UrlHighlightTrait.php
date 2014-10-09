<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\inline;


trait UrlHighlightTrait
{
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
} 