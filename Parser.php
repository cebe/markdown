<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown;

/**
 * A generic parser for markdown-like languages.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class Parser
{
	/**
	 * @var int the maximum nesting level for language elements.
	 */
	public $maximumNestingLevel = 32;

	private $_inlineMarkers = [];

	private $_whitespaceInlineMarkers = [];

	/**
	 * Parses the given text considering the full language.
	 *
	 * @param string $text the text to parse
	 * @return string parsed markup
	 */
	public function parse($text)
	{
		$this->prepare();

		$text = preg_replace('~\r\n?~', "\n", $text);

		$this->prepareMarkers($text);

		$lines = explode("\n", $text);
		$markup = $this->parseBlocks($lines);

		$this->cleanup();
		return $markup;
	}

	/**
	 * Parses a paragraph without block elements (block elements are ignored.
	 *
	 * @param string $text the text to parse
	 * @return string parsed markup
	 */
	public function parseParagraph($text)
	{
		$this->prepare();

		$this->prepareMarkers($text);

		$markup = $this->parseInline($text);

		$this->cleanup();
		return $markup;
	}

	/**
	 * @param string $text
	 */
	private function prepareMarkers($text)
	{
		$this->_inlineMarkers = [];
		$this->_whitespaceInlineMarkers = [];
		// add all markers that are present in markdown
		// check is done to avoid iterations in parseInline(), good for huge markdown files
		foreach ($this->inlineMarkers() as $marker => $method) {
			if (strpos($text, $marker) !== false) {
				$m = substr($marker, 0, 1);
				// markers beginning with whitespace are handled differently
				if ($m !== ' ') {
					// put the longest marker first
					if (isset($this->_inlineMarkers[$m]) && strlen($marker) > strlen(reset($this->_inlineMarkers[$m]))) {
						$this->_inlineMarkers[$m] = array_merge([$marker => $method], $this->_inlineMarkers[$m]);
						break;
					}
					$this->_inlineMarkers[$m][$marker] = $method;
				} else {
					// put the longest marker first
					if (!empty($this->_whitespaceInlineMarkers) && strlen($marker) > strlen(reset($this->_whitespaceInlineMarkers))) {
						$this->_whitespaceInlineMarkers = array_merge([$marker => $method], $this->_whitespaceInlineMarkers);
						break;
					}
					$this->_whitespaceInlineMarkers[$marker] = $method;
				}
			}
		}
	}

	/**
	 * This method will be called before `parse()` and `parseParagraph()`.
	 * You can override it to do some initialization work.
	 */
	protected function prepare()
	{
	}

	/**
	 * This method will be called after `parse()` and `parseParagraph()`.
	 * You can override it to do cleanup.
	 */
	protected function cleanup()
	{
	}

	private $_depth = 0;

	/**
	 * Parse block elements by calling `identifyLine()` to identify them
	 * and call consume function afterwards.
	 * The blocks are then rendered by the corresponding rendering methods.
	 */
	protected function parseBlocks($lines)
	{
		if ($this->_depth++ > $this->maximumNestingLevel) {
			// maximum depth is reached, do not parse input
			return implode("\n", $lines);
		}

		$blocks = [];

		// convert lines to blocks

		for ($i = 0, $count = count($lines); $i < $count; $i++) {
			if (!empty($lines[$i]) && rtrim($lines[$i]) !== '') { // skip empty lines
				// identify a blocks beginning
				$blockType = $this->identifyLine($lines, $i);

				// call consume method for the detected block type to consume further lines
				list($block, $i) = $this->{'consume' . $blockType}($lines, $i);
				if ($block !== false) {
					$blocks[] = $block;
				}
			}
		}

		// convert blocks to markup

		$output = '';
		foreach ($blocks as $block) {
			$output .= $this->{'render' . $block['type']}($block) . "\n";
		}

		$this->_depth--;

		return $output;
	}

	/**
	 * Identifies a line as a block type.
	 *
	 * @param $lines
	 * @param $current
	 * @return string the detected block type
	 */
	protected function identifyLine($lines, $current)
	{
		return 'paragraph';
	}

	/**
	 * Consume lines for a paragraph
	 *
	 * @param $lines
	 * @param $current
	 * @return array
	 */
	public function consumeParagraph($lines, $current)
	{
		// consume until newline

		$block = [
			'type' => 'paragraph',
			'content' => [],
		];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			if (ltrim($lines[$i]) !== '') {
				$block['content'][] = $lines[$i];
			} else {
				break;
			}
		}

		return [$block, $i];
	}

	/**
	 * Render a paragraph block
	 *
	 * @param $block
	 * @return string
	 */
	protected function renderParagraph($block)
	{
		return '<p>' . $this->parseInline(implode("\n", $block['content'])) . '</p>';
	}

	/**
	 * Returns a map of inline markers to the corresponding parser methods.
	 *
	 * This array defines handler methods for inline markdown markers.
	 * When a marker is found in the text, the handler method is called with the text
	 * starting at the position of the marker.
	 *
	 * @return array a map of markers to parser methods
	 */
	protected function inlineMarkers()
	{
		return [];
	}

	/**
	 * Parses inline elements of the language.
	 *
	 * @param $text
	 * @return string
	 */
	protected function parseInline($text)
	{
		// markers beginning with a whitespace are handled differently
		// because of too many false-positive matches of strpbrk
		if (!empty($this->_whitespaceInlineMarkers) && $this->matchNearestWhitespaceMarker($text) !== false) {
			return $this->parseInlineWithWhitespace($text);
		}

		$markers = implode('', array_keys($this->_inlineMarkers));

		$paragraph = '';

		while (!empty($markers) && ($found = strpbrk($text, $markers)) !== false) {

			$pos = strpos($text, $found);

			// add the text up to next marker to the paragraph
			if ($pos !== 0) {
				$paragraph .= substr($text, 0, $pos);
			}
			$text = $found;

			$parsed = false;
			foreach ($this->_inlineMarkers[$text[0]] as $marker => $method) {
				if (strncmp($text, $marker, strlen($marker)) === 0) {
					// parse the marker
					list($output, $offset) = $this->$method($text);
					$paragraph .= $output;
					$text = substr($text, $offset);
					$parsed = true;
					break;
				}
			}
			if (!$parsed) {
				$paragraph .= substr($text, 0, 1);
				$text = substr($text, 1);
			}
		}

		$paragraph .= $text;

		return $paragraph;
	}

	/**
	 * Parses inline elements of the language.
	 *
	 * @param $text
	 * @return string
	 */
	private function parseInlineWithWhitespace($text)
	{
		$markers = implode('', array_keys($this->_inlineMarkers));

		$paragraph = '';

		while (true) {
			if (!empty($markers)) {
				$found = strpbrk($text, $markers);
			} else {
				$found = false;
			}
			$wpos = $this->matchNearestWhitespaceMarker($text);

			if ($found === false && $wpos === false) {
				break;
			}
			// switch between found whitespace or marker
			if ($found !== false) {
				$pos = strpos($text, $found);
				$matchedMarkers = $this->_inlineMarkers[$found[0]];
			}
			if ($wpos !== false && ($found === false || $wpos < $pos)) {
				$pos = $wpos;
				$found = substr($text, $wpos);
				$matchedMarkers = $this->_whitespaceInlineMarkers;
			}

			// add the text up to next marker to the paragraph
			if ($pos !== 0) {
				$paragraph .= substr($text, 0, $pos);
			}
			$text = $found;

			$parsed = false;
			foreach ($matchedMarkers as $marker => $method) {
				if (strncmp($text, $marker, strlen($marker)) === 0) {
					// parse the marker
					list($output, $offset) = $this->$method($text);
					$paragraph .= $output;
					$text = substr($text, $offset);
					$parsed = true;
					break;
				}
			}
			if (!$parsed) {
				$paragraph .= substr($text, 0, 1);
				$text = substr($text, 1);
			}
		}

		$paragraph .= $text;

		return $paragraph;
	}

	private function matchNearestWhitespaceMarker($text)
	{
		$pos = false;
		foreach ($this->_whitespaceInlineMarkers as $marker => $method) {
			if (($wpos = strpos($text, $marker)) !== false && ($pos === false || $pos > $wpos)) {
				$pos = $wpos;
			}
		}
		return $pos;
	}
}
