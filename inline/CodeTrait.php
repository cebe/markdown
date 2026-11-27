<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\inline;

/**
 * Adds inline code elements
 */
trait CodeTrait
{
	/**
	 * Parses an inline code span `` ` ``.
	 * @marker `
	 */
	protected function parseInlineCode($text)
	{
		$ticks = strspn($text, '`');
		if ($ticks > 1 && ($end = $this->findInlineCodeEnd($text, $ticks)) !== false) { // code with enclosed backtick
			return [
				[
					'inlineCode',
					substr($text, $ticks + 1, $end - $ticks - 1),
				],
				$end + 1 + $ticks
			];
		} elseif (isset($text[2]) && ($end = strpos($text, '`', 2)) !== false) {
			return [
				[
					'inlineCode',
					substr($text, 1, $end - 1),
				],
				$end + 1
			];
		}
		return [['text', $text[0]], 1];
	}

	/**
	 * Finds the end of an inline code span that is enclosed in multiple backticks: ``` `` code `` ```.
	 *
	 * The opening backticks are followed by whitespace and the code, which is not empty. The code
	 * ends at the first whitespace that is followed by the same number of backticks.
	 *
	 * @param string $text the text starting with the opening backticks.
	 * @param integer $ticks the number of opening backticks.
	 * @return integer|false the offset of the whitespace that ends the code in `$text`, `false` if
	 * the text does not start with such a code span.
	 */
	private function findInlineCodeEnd($text, $ticks)
	{
		$whitespace = $this->inlineWhitespace();
		if (!isset($text[$ticks]) || strpos($whitespace, $text[$ticks]) === false) {
			return false;
		}
		$start = $this->inlineOffset($text);
		$from = $start + $ticks + 2;

		if ($ticks <= 65535) {
			$end = $this->inlineFind('/\s`{' . $ticks . '}/', $from);

			return $end === false ? false : $end[0][1] - $start;
		}

		// the number of backticks exceeds the limit of a quantifier in a regular expression
		$subject = $this->inlineSubject();
		$fence = str_repeat('`', $ticks);
		$from++;
		while ($from < strlen($subject) && ($pos = strpos($subject, $fence, $from)) !== false) {
			if (strpos($whitespace, $subject[$pos - 1]) !== false) {
				return $pos - 1 - $start;
			}
			$from = $pos + strspn($subject, '`', $pos);
		}

		return false;
	}

	protected function renderInlineCode($block)
	{
		return '<code>' . htmlspecialchars($block[1], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
	}
}
