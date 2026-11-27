<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\inline;

// work around https://github.com/facebook/hhvm/issues/1120
defined('ENT_HTML401') || define('ENT_HTML401', 0);

/**
 * Adds auto linking for URLs
 */
trait UrlLinkTrait
{
	/**
	 * Parses urls and adds auto linking feature.
	 * @marker http
	 * @marker ftp
	 */
	protected function parseUrl($markdown)
	{
		if (!in_array('parseLink', $this->context) && ($length = $this->matchUrl($markdown)) !== false) {
			return [
				['autoUrl', substr($markdown, 0, $length)],
				$length
			];
		}
		return [['text', substr($markdown, 0, 4)], 4];
	}

	/**
	 * Matches a URL at the beginning of the text.
	 *
	 * The URL consists of characters other than whitespace and angle brackets, where parentheses must
	 * be balanced. Punctuation at the end of the URL is not part of it.
	 *
	 * @param string $markdown the text starting with the URL scheme.
	 * @return integer|false the length of the URL, `false` if the text does not start with a URL.
	 */
	private function matchUrl($markdown)
	{
		if (!preg_match('/^(?:https?|ftp):\/\//', $markdown, $scheme)) {
			return false;
		}
		$start = $this->inlineOffset($markdown);
		$subject = $this->inlineSubject();
		$length = strlen($subject);
		$mask = $this->inlineWhitespace() . '<>()';

		$bodyStart = $start + strlen($scheme[0]);
		$wordStart = false;
		for ($end = $bodyStart; $end < $length; ) {
			if ($subject[$end] === '(') {
				$group = $this->inlineBalanced('(', ')', $end, true);
				if ($group === false || $group[0] !== $end) {
					break;
				}
				$wordStart = false;
				$end = $group[1] + 1;
			} elseif (($wordLength = strcspn($subject, $mask, $end)) > 0) {
				$wordStart = $end;
				$end += $wordLength;
			} else {
				break;
			}
		}

		if ($wordStart !== false) {
			// the URL does not end with punctuation
			$end = $wordStart + strlen(rtrim(substr($subject, $wordStart, $end - $wordStart), '.,:;\'"!?'));
		}

		return $end > $bodyStart ? $end - $start : false;
	}

	protected function renderAutoUrl($block)
	{
		$href = htmlspecialchars($block[1], ENT_COMPAT | ENT_HTML401, 'UTF-8');
		$decodedUrl = urldecode($block[1]);
		$secureUrlText = preg_match('//u', $decodedUrl) ? $decodedUrl : $block[1];
		$text = htmlspecialchars($secureUrlText, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return "<a href=\"$href\">$text</a>";
	}
}
