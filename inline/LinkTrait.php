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
 * Addes links and images as well as url markers.
 *
 * This trait conflicts with the HtmlTrait. If both are used together,
 * you have to define a resolution, by defining the HtmlTrait::parseInlineHtml
 * as private so it is not used directly:
 *
 * ```php
 * use block\HtmlTrait {
 *     parseInlineHtml as private parseInlineHtml;
 * }
 * ```
 *
 * If the method exists it is called internally by this trait.
 *
 * Also make sure to reset references on prepare():
 *
 * ```php
 * protected function prepare()
 * {
 *     // reset references
 *     $this->references = [];
 * }
 * ```
 */
trait LinkTrait
{
	/**
	 * @var array a list of defined references in this document.
	 */
	protected $references = [];

	/**
	 * Remove backslash from escaped characters
	 * @param $text
	 * @return string
	 */
	protected function replaceEscape($text)
	{
		$strtr = [];
		foreach($this->escapeCharacters as $char) {
			$strtr["\\$char"] = $char;
		}
		return strtr($text, $strtr);
	}

	/**
	 * Parses a link indicated by `[`.
	 * @marker [
	 */
	protected function parseLink($markdown)
	{
		if (!in_array('parseLink', array_slice($this->context, 1)) && ($parts = $this->parseLinkOrImage($markdown)) !== false) {
			list($text, $url, $title, $offset, $key) = $parts;
			return [
				[
					'link',
					'text' => $this->parseInline($text),
					'url' => $url,
					'title' => $title,
					'refkey' => $key,
					'orig' => substr($markdown, 0, $offset),
				],
				$offset
			];
		} else {
			// remove all starting [ markers to avoid next one to be parsed as link
			$result = '[';
			$i = 1;
			while (isset($markdown[$i]) && $markdown[$i] === '[') {
				$result .= '[';
				$i++;
			}
			return [['text', $result], $i];
		}
	}

	/**
	 * Parses an image indicated by `![`.
	 * @marker ![
	 */
	protected function parseImage($markdown)
	{
		if (($parts = $this->parseLinkOrImage(substr($markdown, 1))) !== false) {
			list($text, $url, $title, $offset, $key) = $parts;

			return [
				[
					'image',
					'text' => $text,
					'url' => $url,
					'title' => $title,
					'refkey' => $key,
					'orig' => substr($markdown, 0, $offset + 1),
				],
				$offset + 1
			];
		} else {
			// remove all starting [ markers to avoid next one to be parsed as link
			$result = '!';
			$i = 1;
			while (isset($markdown[$i]) && $markdown[$i] === '[') {
				$result .= '[';
				$i++;
			}
			return [['text', $result], $i];
		}
	}

	protected function parseLinkOrImage($markdown)
	{
		$start = $this->inlineOffset($markdown);
		$subject = $this->inlineSubject();

		// the first balanced pair of brackets in the text
		if (($brackets = $this->inlineBalanced('[', ']', $start)) === false) {
			return false;
		}
		$text = substr($subject, $brackets[0] + 1, $brackets[1] - $brackets[0] - 1);
		$offset = $brackets[1] - $brackets[0] + 1;

		if (($target = $this->parseLinkTarget($subject, $start + $offset)) !== false) {
			// inline link
			return [
				$text,
				$this->replaceEscape($target[0]), // url
				empty($target[1]) ? null : $target[1], // title
				$offset + $target[2], // offset
				null, // reference key
			];
		}

		// reference style link
		preg_match('/\G([ \n]?\[(.*?)\])?/s', $subject, $refMatches, 0, $start + $offset);
		if (empty($refMatches[2])) {
			$key = strtolower($text);
		} else {
			$key = strtolower($refMatches[2]);
		}
		return [
			$text,
			null, // url
			null, // title
			$offset + strlen($refMatches[0]), // offset
			$key,
		];
	}

	/**
	 * Parses the URL and the title of an inline link: `(url "title")`.
	 *
	 * The URL consists of characters other than whitespace, where parentheses must be balanced.
	 *
	 * @param string $subject the inline subject.
	 * @param integer $offset the offset of the opening parenthesis.
	 * @return array|false the URL, the title, which is `null` if there is none, and the length of
	 * the parsed text. `false` if the text is not the target of an inline link.
	 */
	private function parseLinkTarget($subject, $offset)
	{
		if (!isset($subject[$offset]) || $subject[$offset] !== '(') {
			return false;
		}
		$whitespace = $this->inlineWhitespace();
		$length = strlen($subject);
		$urlStart = $offset + 1 + strspn($subject, $whitespace, $offset + 1);

		for ($urlEnd = $urlStart; $urlEnd < $length; ) {
			$char = $subject[$urlEnd];
			if ($char === '(') {
				$group = $this->inlineBalanced('(', ')', $urlEnd, true);
				if ($group === false || $group[0] !== $urlEnd) {
					$urlEnd = false;
					break;
				}
				$urlEnd = $group[1] + 1;
			} elseif ($char === ')' || strpos($whitespace, $char) !== false) {
				break;
			} else {
				$urlEnd += strcspn($subject, $whitespace . '()', $urlEnd);
			}
		}

		if ($urlEnd !== false && $urlEnd < $length) {
			$url = substr($subject, $urlStart, $urlEnd - $urlStart);
			$next = $urlEnd + strspn($subject, $whitespace, $urlEnd);
			if ($next > $urlEnd && ($title = $this->parseLinkTitle($subject, $next)) !== false) {
				return [$url, $title[0], $title[1] - $offset];
			}
			if (isset($subject[$next]) && $subject[$next] === ')') {
				return [$url, null, $next + 1 - $offset];
			}
		}

		// a title may also be given without a URL
		if ($urlStart > $offset + 1 && ($title = $this->parseLinkTitle($subject, $urlStart)) !== false) {
			return ['', $title[0], $title[1] - $offset];
		}

		return false;
	}

	/**
	 * Parses the title of an inline link including the closing parenthesis: `"title")`.
	 *
	 * @param string $subject the inline subject.
	 * @param integer $offset the offset of the opening quote.
	 * @return array|false the title and the offset after the closing parenthesis, `false` if the text
	 * is not a title.
	 */
	private function parseLinkTitle($subject, $offset)
	{
		if (!isset($subject[$offset]) || $subject[$offset] !== '"') {
			return false;
		}
		// the title ends at the first quote that is followed by the closing parenthesis
		if (($end = $this->inlineFind('/"\s*\)/', $offset + 1)) === false) {
			return false;
		}
		// the title must not span multiple lines
		$newline = $this->inlineFind('/\n/', $offset + 1);
		if ($newline !== false && $newline[0][1] < $end[0][1]) {
			return false;
		}

		return [
			substr($subject, $offset + 1, $end[0][1] - $offset - 1),
			$end[0][1] + strlen($end[0][0]),
		];
	}

	/**
	 * Parses inline HTML.
	 * @marker <
	 */
	protected function parseLt($text)
	{
		if (strpos($text, '>') !== false) {
			if (!in_array('parseLink', $this->context)) { // do not allow links in links
				if (($length = $this->matchEmailLink($text)) !== false) {
					// email address
					return [
						['email', $this->replaceEscape(substr($text, 1, $length - 2))],
						$length
					];
				} elseif (($length = $this->matchUrlLink($text)) !== false) {
					// URL
					return [
						['url', $this->replaceEscape(substr($text, 1, $length - 2))],
						$length
					];
				}
			}
			// try inline HTML if it was neither a URL nor email if HtmlTrait is included.
			if (method_exists($this, 'parseInlineHtml')) {
				return $this->parseInlineHtml($text);
			}
		}
		return [['text', '&lt;'], 1];
	}

	/**
	 * Matches an email address enclosed in angle brackets at the beginning of the text: `<user@example.com>`.
	 *
	 * @param string $text the text starting with `<`.
	 * @return integer|false the length of the match including the angle brackets, `false` if the
	 * text does not start with an email address.
	 */
	private function matchEmailLink($text)
	{
		$start = $this->inlineOffset($text);

		// the address has an @ before the first whitespace and the first closing bracket
		$at = $this->inlineFind('/[\s>@]/', $start + 1);
		if ($at === false || $at[0][0] !== '@') {
			return false;
		}
		// it ends at the first top level domain that is followed by the closing bracket
		if (($end = $this->inlineFind('/\.\w+>/', $at[0][1] + 1)) === false) {
			return false;
		}
		$whitespace = $this->inlineFind('/\s/', $start + 1);
		if ($whitespace !== false && $whitespace[0][1] < $end[0][1]) {
			return false;
		}

		return $end[0][1] + strlen($end[0][0]) - $start;
	}

	/**
	 * Matches a URL enclosed in angle brackets at the beginning of the text: `<http://example.com>`.
	 *
	 * @param string $text the text starting with `<`.
	 * @return integer|false the length of the match including the angle brackets, `false` if the
	 * text does not start with a URL.
	 */
	private function matchUrlLink($text)
	{
		if (!preg_match('/^<[a-z]{3,}:\/\//', $text, $scheme)) {
			return false;
		}
		$start = $this->inlineOffset($text);

		// the URL is not empty and ends at the first closing bracket
		if (($end = $this->inlineFind('/>/', $start + strlen($scheme[0]) + 1)) === false) {
			return false;
		}
		$whitespace = $this->inlineFind('/\s/', $start + 1);
		if ($whitespace !== false && $whitespace[0][1] < $end[0][1]) {
			return false;
		}

		return $end[0][1] + 1 - $start;
	}

	protected function renderEmail($block)
	{
		$email = htmlspecialchars($block[1], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return "<a href=\"mailto:$email\">$email</a>";
	}

	protected function renderUrl($block)
	{
		$url = htmlspecialchars($block[1], ENT_COMPAT | ENT_HTML401, 'UTF-8');
		$decodedUrl = urldecode($block[1]);
		$secureUrlText = preg_match('//u', $decodedUrl) ? $decodedUrl : $block[1];
		$text = htmlspecialchars($secureUrlText, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return "<a href=\"$url\">$text</a>";
	}

	protected function lookupReference($key)
	{
		$normalizedKey = preg_replace('/\s+/', ' ', $key);
		if (isset($this->references[$key]) || isset($this->references[$key = $normalizedKey])) {
			return $this->references[$key];
		}
		return false;
	}

	protected function renderLink($block)
	{
		if (isset($block['refkey'])) {
			if (($ref = $this->lookupReference($block['refkey'])) !== false) {
				$block = array_merge($block, $ref);
			} else {
				if (strncmp($block['orig'], '[', 1) === 0) {
					return '[' . $this->renderNestedInline(substr($block['orig'], 1));
				}
				return $block['orig'];
			}
		}
		return '<a href="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
			. (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
			. '>' . $this->renderAbsy($block['text']) . '</a>';
	}

	protected function renderImage($block)
	{
		if (isset($block['refkey'])) {
			if (($ref = $this->lookupReference($block['refkey'])) !== false) {
				$block = array_merge($block, $ref);
			} else {
				if (strncmp($block['orig'], '![', 2) === 0) {
					return '![' . $this->renderNestedInline(substr($block['orig'], 2));
				}
				return $block['orig'];
			}
		}
		return '<img src="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
			. ' alt="' . htmlspecialchars($block['text'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"'
			. (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
			. ($this->html5 ? '>' : ' />');
	}

	// references

	protected function identifyReference($line)
	{
		return isset($line[0]) && ($line[0] === ' ' || $line[0] === '[') && preg_match('/^ {0,3}\[[^\[](.*?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*$/', $line);
	}

	/**
	 * Consume link references
	 */
	protected function consumeReference($lines, $current)
	{
		while (isset($lines[$current]) && preg_match('/^ {0,3}\[(.+?)\]:\s*(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*$/', $lines[$current], $matches)) {
			$label = strtolower($matches[1]);

			$this->references[$label] = [
				'url' => $this->replaceEscape($matches[2]),
			];
			if (isset($matches[3])) {
				$this->references[$label]['title'] = $matches[3];
			} else {
				// title may be on the next line
				if (isset($lines[$current + 1]) && preg_match('/^\s+[\(\'"](.+?)[\)\'"]\s*$/', $lines[$current + 1], $matches)) {
					$this->references[$label]['title'] = $matches[1];
					$current++;
				}
			}
			$current++;
		}
		return [false, --$current];
	}

	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
