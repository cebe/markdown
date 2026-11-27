<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\inline;

/**
 * Adds inline emphasizes and strong elements
 */
trait EmphStrongTrait
{
	/**
	 * Parses emphasized and strong elements.
	 * @marker _
	 * @marker *
	 */
	protected function parseEmphStrong($text)
	{
		$marker = $text[0];

		if (!isset($text[1])) {
			return [['text', $text[0]], 1];
		}

		if ($marker == $text[1]) { // strong
			// check whether the end marker exists in the text
			if (strpos($text, $marker . $marker, 2) === false) {
				return [['text', $text[0] . $text[1]], 2];
			}

			if (($end = $this->findEmphStrongEnd($text, true)) !== false) {
				return [
					[
						'strong',
						$this->parseInline(substr($text, 2, $end - 2)),
					],
					$end + 2
				];
			}
		} else { // emph
			// check whether the end marker exists in the text
			if (strpos($text, $marker, 1) === false) {
				return [['text', $text[0]], 1];
			}

			if (($end = $this->findEmphStrongEnd($text, false)) !== false) {
				$content = substr($text, 1, $end - 1);
				// if only a single whitespace or nothing is contained in an emphasis, do not consider it valid
				if ($content === '' || $content === ' ') {
					return [['text', $text[0]], 1];
				}
				return [
					[
						'emph',
						$this->parseInline($content),
					],
					$end + 1
				];
			}
		}
		return [['text', $text[0]], 1];
	}

	/**
	 * Finds the marker that closes the emphasized or strong element at the beginning of the text.
	 *
	 * The content of an element consists of escaped markers, characters other than the marker and
	 * nested elements of the other kind. It ends at the first marker that is not part of these.
	 * An emphasized element is not closed by a marker that opens a strong element, and an underscore
	 * closes it only at the end of a word.
	 *
	 * The result depends only on the position where the content is continued, so it is memoized for
	 * the positions that were passed. This keeps the parsing time linear when many elements are opened
	 * that are never closed.
	 *
	 * @param string $text the text starting with the opening marker.
	 * @param boolean $strong whether to find the end of a strong element, which uses a double marker.
	 * @return integer|false the offset of the closing marker in `$text`, `false` if the element is
	 * not closed.
	 */
	private function findEmphStrongEnd($text, $strong)
	{
		$marker = $text[0];
		if ($marker !== '*' && ($marker !== '_' || !$this->isUtf8Text($text))) {
			return false;
		}
		$start = $this->inlineOffset($text);
		$subject = $this->inlineSubject();
		$length = strlen($subject);
		$memo = $this->inlineMemo('emphStrong' . $marker . ($strong ? 2 : 1));

		$end = false;
		$passed = [];
		$first = true;
		for ($i = $start + ($strong ? 2 : 1); $i < $length; ) {
			if (!$first) {
				// the element can not end before the next marker
				$i += strcspn($subject, $marker . '\\', $i);
				if ($i >= $length) {
					break;
				}
				if (isset($memo[$i])) {
					$end = $memo[$i];
					break;
				}
				$passed[] = $i;
				if ($subject[$i] === $marker && $this->closesEmphStrong($subject, $i, $strong)) {
					$end = $i;
					break;
				}
			}
			$first = false;

			if ($subject[$i] !== $marker) {
				// an escaped marker or another character
				$i += $subject[$i] === '\\' && isset($subject[$i + 1]) && $subject[$i + 1] === $marker ? 2 : 1;
				continue;
			}

			// a nested element of the other kind
			$width = $strong ? 1 : 2;
			if (!$strong && (!isset($subject[$i + 1]) || $subject[$i + 1] !== $marker)) {
				break;
			}
			$nested = $i + $width < $length ? strcspn($subject, $marker, $i + $width) : 0;
			$close = $i + $width + $nested;
			if ($close + $width > $length || !$strong && ($subject[$close + 1] !== $marker || $nested === 0 && $marker === '*')) {
				break;
			}
			$i = $close + $width;
		}

		foreach ($passed as $position) {
			$memo[$position] = $end;
		}

		return $end === false ? false : $end - $start;
	}

	/**
	 * Returns whether the marker at the given offset closes an emphasized or strong element.
	 *
	 * @param string $subject the inline subject.
	 * @param integer $offset the offset of the marker.
	 * @param boolean $strong whether the element is a strong element.
	 * @return boolean whether the marker closes the element.
	 */
	private function closesEmphStrong($subject, $offset, $strong)
	{
		$marker = $subject[$offset];
		$next = isset($subject[$offset + 1]) ? $subject[$offset + 1] : '';
		if ($strong) {
			return $next === $marker;
		}
		// a marker that opens a strong element does not close the emphasized element
		if ($next === $marker && isset($subject[$offset + 2]) && $subject[$offset + 2] !== $marker) {
			return false;
		}
		if ($marker !== '_' || $next === '') {
			return true;
		}

		// an underscore closes the element only at the end of a word, the next character is checked on
		// its own to keep the cost independent of the length of the text
		$byte = ord($next);
		$width = $byte < 0x80 ? 1 : ($byte < 0xE0 ? 2 : ($byte < 0xF0 ? 3 : 4));

		return !preg_match('/^\w/u', substr($subject, $offset + 1, $width));
	}

	/**
	 * Returns whether the text is valid UTF-8.
	 *
	 * The result is derived from a single validation of the inline subject. Validating the text itself
	 * for each marker makes the parsing time grow quadratically with the number of markers.
	 *
	 * @param string $text the text passed to the inline parser method.
	 * @return boolean whether the text is valid UTF-8.
	 */
	private function isUtf8Text($text)
	{
		$start = $this->inlineOffset($text);
		if ($this->inlineFind('//u', 0) !== false) {
			return true;
		}

		// the inline subject is not valid, find the first invalid byte in the text
		$valid = '[\x00-\x7F]++|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
			. '|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}';

		return $this->inlineFind('/(?:' . $valid . ')(*SKIP)(*FAIL)|[\x80-\xFF]/', $start) === false;
	}

	protected function renderStrong($block)
	{
		return '<strong>' . $this->renderAbsy($block[1]) . '</strong>';
	}

	protected function renderEmph($block)
	{
		return '<em>' . $this->renderAbsy($block[1]) . '</em>';
	}

    abstract protected function parseInline($text);
    abstract protected function renderAbsy($blocks);
}
