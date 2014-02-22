<?php

namespace cebe\markdown;

/**
 * A generic parser for markdown-like languages.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */
class Parser
{
	/**
	 * @var int the maximum nesting level for language elements.
	 */
	public $maximumNestingLevel = 32;

	private $_inlineMarkers = [];

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
		// add all markers that are present in markdown
		// check is done to avoid iterations in parseInline(), good for huge markdown files
		foreach($this->inlineMarkers() as $marker => $method) {
			if (strpos($text, $marker) !== false) {
				$this->_inlineMarkers[$marker] = $method;
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

	private $depth = 0;

	/**
	 * Parse block elements by calling `identifyLine()` to identify them
	 * and call consume function afterwards.
	 * The blocks are then rendered by the corresponding rendering methods.
	 */
	protected function parseBlocks($lines)
	{
		if ($this->depth++ > $this->maximumNestingLevel) {
			// maximum depth is reached, do not parse input
			return implode("\n", $lines);
		}

		$blocks = [];

		// convert lines to blocks

		for($i = 0, $count = count($lines); $i < $count; $i++) {
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
		foreach($blocks as $block) {
			$output .= $this->{'render' . $block['type']}($block) . "\n";
		}

		$this->depth--;

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
		for($i = $current, $count = count($lines); $i < $count; $i++) {
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
		$markers = $this->_inlineMarkers;

		$paragraph = '';

		while(!empty($markers)) {
			$closest = null;
			$cpos = 0;
			foreach($markers as $marker => $method) {
				if (($pos = strpos($text, $marker)) === false) {
					unset($markers[$marker]);
					continue;
				}

				if ($closest === null || $pos < $cpos || ($pos === $cpos && strlen($marker) > strlen($closest))) {
					$closest = $marker;
					$cpos = $pos;
				}
			}

			// add the text up to next marker to the paragraph
			if ($cpos !== 0) {
				$paragraph .= substr($text, 0, $cpos);
				$text = substr($text, $cpos);
			}

			// parse the marker
			if ($closest !== null) {
				$method = $markers[$closest];
				list($output, $offset) = $this->$method($text);
				$paragraph .= $output;
				$text = substr($text, $offset);
			}
		}

		$paragraph .= $text;

		return $paragraph;
	}
}