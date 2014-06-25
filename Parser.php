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
abstract class Parser
{
	/**
	 * @var integer the maximum nesting level for language elements.
	 */
	public $maximumNestingLevel = 32;

	/**
	 * @var array the set of inline markers to use in different contexts.
	 */
	private $_inlineMarkers = [];
	/**
	 * @var string the current context the parser is in.
	 */
	protected $context = [];


	/**
	 * Parses the given text considering the full language.
	 *
	 * This includes parsing block elements as well as inline elements.
	 *
	 * @param string $text the text to parse
	 * @return string parsed markup
	 */
	public function parse($text)
	{
		$this->prepare();
		
		if (empty($text)) {
			return '';
		}

		$text = preg_replace('~\r\n?~', "\n", $text);

		$this->prepareMarkers($text);

		$lines = explode("\n", $text);
		$markup = $this->parseBlocks($lines);

		$this->cleanup();
		return $markup;
	}

	/**
	 * Parses a paragraph without block elements (block elements are ignored).
	 *
	 * @param string $text the text to parse
	 * @return string parsed markup
	 */
	public function parseParagraph($text)
	{
		$this->prepare();

		if (empty($text)) {
			return '';
		}

		$this->prepareMarkers($text);

		$markup = $this->parseInline($text);

		$this->cleanup();
		return $markup;
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


	// block parsing


	private $_depth = 0;

	/**
	 * Parse block elements by calling `identifyLine()` to identify them
	 * and call consume function afterwards.
	 * The blocks are then rendered by the corresponding rendering methods.
	 */
	protected function parseBlocks($lines)
	{
		if ($this->_depth >= $this->maximumNestingLevel) {
			// maximum depth is reached, do not parse input
			return implode("\n", $lines);
		}
		$this->_depth++;

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
			array_unshift($this->context, $block['type']);
			$output .= $this->{'render' . $block['type']}($block) . "\n";
			array_shift($this->context);
		}

		$this->_depth--;

		return $output;
	}

	/**
	 * Identifies a line as a block type.
	 *
	 * @param $lines
	 * @param $current
	 * @return string the detected block type (e.g. 'paragraph').
	 */
	protected abstract function identifyLine($lines, $current);

	/**
	 * Consume lines for a paragraph
	 *
	 * @param $lines
	 * @param $current
	 * @return array
	 */
	protected function consumeParagraph($lines, $current)
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


	// inline parsing


	/**
	 * Returns a map of inline markers to the corresponding parser methods.
	 *
	 * This array defines handler methods for inline markdown markers.
	 * When a marker is found in the text, the handler method is called with the text
	 * starting at the position of the marker.
	 *
	 * Note that markers starting with whitespace may slow down the parser,
	 * you may want to use [[parsePlainText]] to deal with them.
	 *
	 * @return array a map of markers to parser methods
	 */
	protected abstract function inlineMarkers();

	/**
	 * Prepare markers that are used in the text to parse
	 *
	 * Add all markers that are present in markdown.
	 * Check is done to avoid iterations in parseInline(), good for huge markdown files
	 * @param string $text
	 */
	private function prepareMarkers($text)
	{
		$this->_inlineMarkers = [];
		foreach ($this->inlineMarkers() as $marker => $method) {
			if (strpos($text, $marker) !== false) {
				$m = $marker[0];
				// put the longest marker first
				if (isset($this->_inlineMarkers[$m])) {
					reset($this->_inlineMarkers[$m]);
					if (strlen($marker) > strlen(key($this->_inlineMarkers[$m]))) {
						$this->_inlineMarkers[$m] = array_merge([$marker => $method], $this->_inlineMarkers[$m]);
						continue;
					}
				}
				$this->_inlineMarkers[$m][$marker] = $method;
			}
		}
	}

	/**
	 * Parses inline elements of the language.
	 *
	 * @param string $text the inline text to parse.
	 * @return string
	 */
	protected function parseInline($text)
	{
		if ($this->_depth >= $this->maximumNestingLevel) {
			// maximum depth is reached, do not parse input
			return $text;
		}
		$this->_depth++;

		$markers = implode('', array_keys($this->_inlineMarkers));

		$paragraph = '';

		while (!empty($markers) && ($found = strpbrk($text, $markers)) !== false) {

			$pos = strpos($text, $found);

			// add the text up to next marker to the paragraph
			if ($pos !== 0) {
				$paragraph .= $this->parsePlainText(substr($text, 0, $pos));
			}
			$text = $found;

			$parsed = false;
			foreach ($this->_inlineMarkers[$text[0]] as $marker => $method) {
				if (strncmp($text, $marker, strlen($marker)) === 0) {
					// parse the marker
					array_unshift($this->context, $method);
					list($output, $offset) = $this->$method($text);
					array_shift($this->context);

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

		$paragraph .= $this->parsePlainText($text);

		$this->_depth--;

		return $paragraph;
	}

	/**
	 * This function gets called for each plain text section in the markdown text.
	 * It can be used to work on normal text section for example to highlight keywords or
	 * do special escaping.
	 */
	protected function parsePlainText($text)
	{
		return $text;
	}
}
