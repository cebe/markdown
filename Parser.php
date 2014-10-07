<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown;
use ReflectionMethod;

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
	 * @var string the current context the parser is in.
	 */
	protected $context = [];
	/**
	 * @var array these are "escapeable" characters. When using one of these prefixed with a
	 * backslash, the character will be outputted without the backslash and is not interpreted
	 * as markdown.
	 */
	protected $escapeCharacters = [
		'\\', // backslash
		'`', // backtick
		'*', // asterisk
		'_', // underscore
		'{', '}', // curly braces
		'[', ']', // square brackets
		'(', ')', // parentheses
		'#', // hash mark
		'+', // plus sign
		'-', // minus sign (hyphen)
		'.', // dot
		'!', // exclamation mark
		'<', '>',
	];

	/**
	 * @var array the set of inline markers to use in different contexts.
	 */
	private $_inlineMarkers = [];


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
		$absy = $this->parseBlocks($lines);
		$markup = $this->renderAbsy($absy);

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

		$absy = $this->parseInline($text);
		$markup = $this->renderAbsy($absy);

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

	private function getBlocktypes()
	{
		// detect block types via "identify" functions
		$reflection = new \ReflectionClass($this);
		$lineIdentifiers = array_filter(array_map(function($method) {
			$name = $method->getName();
			return strncmp($name, 'identify', 8) === 0 ? substr($name, 8) : false;
		}, $reflection->getMethods(ReflectionMethod::IS_PROTECTED)));

		sort($lineIdentifiers);
		return $lineIdentifiers;
	}

	protected function getLineType($lines, $current)
	{
		$line = $lines[$current];
		$lineIdentifiers = $this->getBlocktypes();
		foreach($lineIdentifiers as $blockType) {
			if ($this->{'identify' . $blockType}($line, $lines, $current)) {
				return $blockType;
			}
		}
		return 'paragraph';
	}

	/**
	 * Parse block elements by calling `identifyLine()` to identify them
	 * and call consume function afterwards.
	 * The blocks are then rendered by the corresponding rendering methods.
	 */
	protected function parseBlocks($lines)
	{
		if ($this->_depth >= $this->maximumNestingLevel) {
			// maximum depth is reached, do not parse input
			return [['text', implode("\n", $lines)]];
		}
		$this->_depth++;

		$blocks = [];

		$lineIdentifiers = $this->getBlocktypes();

		// convert lines to blocks
		for ($i = 0, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (!empty($line) && rtrim($line) !== '') { // skip empty lines
				// identify a blocks beginning
				$identified = false;
				foreach($lineIdentifiers as $blockType) {
					if ($this->{'identify' . $blockType}($line, $lines, $i)) {
						// call consume method for the detected block type to consume further lines
						list($block, $i) = $this->{'consume' . $blockType}($lines, $i);
						if ($block !== false) {
							$blocks[] = $block;
						}
						$identified = true;
						break 1;
					}
				}
				// consider the line a normal paragraph
				if (!$identified) {
					list($block, $i) = $this->consumeParagraph($lines, $i);
					$blocks[] = $block;
				}
			}
		}

		$this->_depth--;

		return $blocks;
	}

	protected function renderAbsy($blocks)
	{
//		print_r($blocks);
		$output = '';
		foreach ($blocks as $block) {
			array_unshift($this->context, $block[0]);
			$output .= $this->{'render' . $block[0]}($block);
			array_shift($this->context);
		}
		return $output;
	}

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
		$content = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			if (ltrim($lines[$i]) !== '') {
				$content[] = $lines[$i];
			} else {
				break;
			}
		}
		$block = [
			'paragraph',
			'content' => $this->parseInline(implode("\n", $content)),
		];
		return [$block, --$i];
	}

	/**
	 * Render a paragraph block
	 *
	 * @param $block
	 * @return string
	 */
	protected function renderParagraph($block)
	{
		return '<p>' . $this->renderAbsy($block['content']) . "</p>\n";
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
	protected function inlineMarkers()
	{
		$markers = [];
		// detect "parse" functions
		$reflection = new \ReflectionClass($this);
		foreach($reflection->getMethods(ReflectionMethod::IS_PROTECTED) as $method) {
			$methodName = $method->getName();
			if (strncmp($methodName, 'parse', 5) === 0) {
				preg_match_all('/@marker ([^\s]+)/', $method->getDocComment(), $matches);
				foreach($matches[1] as $match) {
					$markers[$match] = $methodName;
				}
			}
		}
		return $markers;
	}

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
			return ['text', $text];
		}
		$this->_depth++;

		$markers = implode('', array_keys($this->_inlineMarkers));

		$paragraph = [];

		while (!empty($markers) && ($found = strpbrk($text, $markers)) !== false) {

			$pos = strpos($text, $found);

			// add the text up to next marker to the paragraph
			if ($pos !== 0) {
				$paragraph[] = $this->parsePlainText(substr($text, 0, $pos));
			}
			$text = $found;

			$parsed = false;
			foreach ($this->_inlineMarkers[$text[0]] as $marker => $method) {
				if (strncmp($text, $marker, strlen($marker)) === 0) {
					// parse the marker
					array_unshift($this->context, $method);
					list($output, $offset) = $this->$method($text);
					array_shift($this->context);

					$paragraph[] = $output;
					$text = substr($text, $offset);
					$parsed = true;
					break;
				}
			}
			if (!$parsed) {
				$paragraph[] = ['text', substr($text, 0, 1)];
				$text = substr($text, 1);
			}
		}

		$paragraph[] = $this->parsePlainText($text);

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
		return ['text', $text];
	}

	/**
	 * Parses escaped special characters.
	 * @marker \
	 */
	protected function parseEscape($text)
	{
		if (isset($text[1]) && in_array($text[1], $this->escapeCharacters)) {
			return [['text', $text[1]], 2];
		}
		return [['text', $text[0]], 1];
	}

	protected function renderText($block)
	{
		return $block[1];
	}
}
