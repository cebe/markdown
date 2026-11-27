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
	 * @var array the current context the parser is in.
	 * TODO remove in favor of absy
	 */
	protected $context = [];
	/**
	 * @var array these are "escapeable" characters. When using one of these prefixed with a
	 * backslash, the character will be outputted without the backslash and is not interpreted
	 * as markdown.
	 */
	protected $escapeCharacters = [
		'\\', // backslash
	];

	private $_depth = 0;
	/**
	 * @var string the inline text handled by the innermost running `parseInline()` call.
	 */
	private $_inlineSubject = '';
	/**
	 * @var string the part of the inline subject that was passed to the current inline parser method.
	 */
	private $_inlineRest = '';
	/**
	 * @var integer the offset of `$_inlineRest` in the inline subject.
	 */
	private $_inlineOffset = 0;
	/**
	 * @var array scan results memoized for the inline subject.
	 */
	private $_inlineCache = [];
	/**
	 * @var string|null the characters matched by `\s` in a regular expression.
	 */
	private $_whitespace;


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

		if (ltrim($text) === '') {
			return '';
		}

		$text = str_replace(["\r\n", "\n\r", "\r"], "\n", $text);

		$this->prepareMarkers($text);
		$this->resetInlineState();

		$absy = $this->parseBlocks(explode("\n", $text));
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

		if (ltrim($text) === '') {
			return '';
		}

		$text = str_replace(["\r\n", "\n\r", "\r"], "\n", $text);

		$this->prepareMarkers($text);
		$this->resetInlineState();

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

	private $_blockTypes;

	/**
	 * @return array a list of block element types available.
	 */
	protected function blockTypes()
	{
		if ($this->_blockTypes === null) {
			// detect block types via "identify" functions
			$reflection = new \ReflectionClass($this);
			$this->_blockTypes = array_filter(array_map(function($method) {
				$name = $method->getName();
				return strncmp($name, 'identify', 8) === 0 ? strtolower(substr($name, 8)) : false;
			}, $reflection->getMethods(ReflectionMethod::IS_PROTECTED)));

			sort($this->_blockTypes);
		}
		return $this->_blockTypes;
	}

	/**
	 * Given a set of lines and an index of a current line it uses the registed block types to
	 * detect the type of this line.
	 * @param array $lines
	 * @param integer $current
	 * @return string name of the block type in lower case
	 */
	protected function detectLineType($lines, $current)
	{
		$line = $lines[$current];
		$blockTypes = $this->blockTypes();
		foreach($blockTypes as $blockType) {
			if ($this->{'identify' . $blockType}($line, $lines, $current)) {
				return $blockType;
			}
		}
		// consider the line a normal paragraph if no other block type matches
		return 'paragraph';
	}

	/**
	 * Parse block elements by calling `detectLineType()` to identify them
	 * and call consume function afterwards.
	 */
	protected function parseBlocks($lines)
	{
		if ($this->_depth >= $this->maximumNestingLevel) {
			// maximum depth is reached, do not parse input
			return [['text', implode("\n", $lines)]];
		}
		$this->_depth++;

		$blocks = [];

		// convert lines to blocks
		for ($i = 0, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if ($line !== '' && rtrim($line) !== '') { // skip empty lines
				// identify a blocks beginning and parse the content
				list($block, $i) = $this->parseBlock($lines, $i);
				if ($block !== false) {
					$blocks[] = $block;
				}
			}
		}

		$this->_depth--;

		return $blocks;
	}

	/**
	 * Parses the block at current line by identifying the block type and parsing the content
	 * @param $lines
	 * @param $current
	 * @return array Array of two elements, the first element contains the block,
	 * the second contains the next line index to be parsed.
	 */
	protected function parseBlock($lines, $current)
	{
		// identify block type for this line
		$blockType = $this->detectLineType($lines, $current);

		// call consume method for the detected block type to consume further lines
		return $this->{'consume' . $blockType}($lines, $current);
	}

	protected function renderAbsy($blocks)
	{
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
	 * @var array the set of inline markers to use in different contexts.
	 */
	private $_inlineMarkers = [];

	/**
	 * Returns a map of inline markers to the corresponding parser methods.
	 *
	 * This array defines handler methods for inline markdown markers.
	 * When a marker is found in the text, the handler method is called with the text
	 * starting at the position of the marker.
	 *
	 * Note that markers starting with whitespace may slow down the parser,
	 * you may want to use [[renderText]] to deal with them.
	 *
	 * You may override this method to define a set of markers and parsing methods.
	 * The default implementation looks for protected methods starting with `parse` that
	 * also have an `@marker` annotation in PHPDoc.
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
	protected function prepareMarkers($text)
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
	 * @return array
	 */
	protected function parseInline($text)
	{
		if ($this->_depth >= $this->maximumNestingLevel) {
			// maximum depth is reached, do not parse input
			return [['text', $text]];
		}
		$this->_depth++;

		$markers = implode('', array_keys($this->_inlineMarkers));

		$outerState = [$this->_inlineSubject, $this->_inlineRest, $this->_inlineOffset, $this->_inlineCache];
		$this->_inlineSubject = $text;
		$this->_inlineCache = [];

		$paragraph = [];
		$length = strlen($text);
		$offset = 0;

		while (!empty($markers) && $offset < $length && ($pos = $offset + strcspn($text, $markers, $offset)) < $length) {

			// add the text up to next marker to the paragraph
			if ($pos !== $offset) {
				$paragraph[] = ['text', substr($text, $offset, $pos - $offset)];
			}
			$rest = substr($text, $pos);

			$parsed = false;
			foreach ($this->_inlineMarkers[$rest[0]] as $marker => $method) {
				if (strncmp($rest, $marker, strlen($marker)) === 0) {
					// parse the marker
					$this->_inlineRest = $rest;
					$this->_inlineOffset = $pos;
					array_unshift($this->context, $method);
					list($output, $consumed) = $this->$method($rest);
					array_shift($this->context);

					$paragraph[] = $output;
					$offset = $pos + $consumed;
					$parsed = true;
					break;
				}
			}
			if (!$parsed) {
				$paragraph[] = ['text', $rest[0]];
				$offset = $pos + 1;
			}
		}

		$paragraph[] = ['text', $offset < $length ? substr($text, $offset) : ''];

		list($this->_inlineSubject, $this->_inlineRest, $this->_inlineOffset, $this->_inlineCache) = $outerState;

		$this->_depth--;

		return $paragraph;
	}

	/**
	 * Parses and renders inline elements of text that is discovered while rendering.
	 *
	 * The call counts as one nesting level, so that text which keeps producing such calls is rendered
	 * as plain text once [[maximumNestingLevel]] is reached.
	 *
	 * @param string $text the inline text to parse.
	 * @return string the rendered markup.
	 */
	protected function renderNestedInline($text)
	{
		$this->_depth++;
		$output = $this->renderAbsy($this->parseInline($text));
		$this->_depth--;

		return $output;
	}

	/**
	 * Returns the inline text that the offsets of [[inlineOffset()]], [[inlineFind()]] and
	 * [[inlineBalanced()]] refer to.
	 *
	 * @return string the inline subject.
	 */
	protected function inlineSubject()
	{
		return $this->_inlineSubject;
	}

	/**
	 * Returns the offset of the given text in the inline subject.
	 *
	 * Inline parser methods receive the text starting at their marker. This method maps that text
	 * back to the inline subject, which allows scan results to be shared between the markers of the
	 * same text instead of scanning the remaining text for each marker again.
	 *
	 * Text that is not the end of the inline subject becomes the new inline subject.
	 *
	 * @param string $text the text passed to an inline parser method.
	 * @return integer the offset of `$text` in the inline subject.
	 */
	protected function inlineOffset($text)
	{
		if ($text === $this->_inlineRest) {
			return $this->_inlineOffset;
		}

		$length = strlen($text);
		$offset = strlen($this->_inlineSubject) - $length;
		if ($length === 0 || $offset < 0 || substr_compare($this->_inlineSubject, $text, $offset) !== 0) {
			$this->_inlineSubject = $text;
			$this->_inlineCache = [];
			$offset = 0;
		}
		$this->_inlineRest = $text;
		$this->_inlineOffset = $offset;

		return $offset;
	}

	/**
	 * Finds the first match of a regular expression at or after an offset of the inline subject.
	 *
	 * The result is memoized per pattern. Searching the same pattern from increasing offsets
	 * scans each part of the inline subject only once.
	 *
	 * @param string $pattern the regular expression. Its result must not depend on the text before the
	 * position where a match starts.
	 * @param integer $offset the offset in the inline subject to start searching at.
	 * @return array|false the matches as returned by `preg_match()` with `PREG_OFFSET_CAPTURE`,
	 * `false` if there is no match.
	 */
	protected function inlineFind($pattern, $offset)
	{
		if (isset($this->_inlineCache['find'][$pattern])) {
			list($from, $matches) = $this->_inlineCache['find'][$pattern];
			if ($from <= $offset && ($matches === false || $matches[0][1] >= $offset)) {
				return $matches;
			}
		}

		$matches = [];
		if ($offset > strlen($this->_inlineSubject)
			|| !preg_match($pattern, $this->_inlineSubject, $matches, PREG_OFFSET_CAPTURE, $offset)) {
			$matches = false;
		}
		$this->_inlineCache['find'][$pattern] = [$offset, $matches];

		return $matches;
	}

	/**
	 * Finds the first balanced pair of delimiters that opens at or after an offset of the inline subject.
	 *
	 * An opening delimiter belongs to a pair when a closing delimiter follows it and all delimiters
	 * in between form pairs themselves.
	 *
	 * @param string $open the opening delimiter, a single character.
	 * @param string $close the closing delimiter, a single character.
	 * @param integer $offset the offset in the inline subject to start searching at.
	 * @param boolean $breakOnWhitespace whether a pair must not contain whitespace.
	 * @return array|false the offsets of the opening and the closing delimiter, `false` if there is
	 * no balanced pair.
	 */
	protected function inlineBalanced($open, $close, $offset, $breakOnWhitespace = false)
	{
		$key = $open . $close . ($breakOnWhitespace ? ' ' : '');
		if (!isset($this->_inlineCache['balanced'][$key])) {
			$this->_inlineCache['balanced'][$key] = $this->scanBalanced(
				$open,
				$close,
				$breakOnWhitespace ? $this->inlineWhitespace() : ''
			);
		}
		list($starts, $ends) = $this->_inlineCache['balanced'][$key];

		// binary search for the first pair starting at or after the offset
		$low = 0;
		$high = count($starts);
		while ($low < $high) {
			$middle = ($low + $high) >> 1;
			if ($starts[$middle] < $offset) {
				$low = $middle + 1;
			} else {
				$high = $middle;
			}
		}

		return isset($starts[$low]) ? [$starts[$low], $ends[$low]] : false;
	}

	/**
	 * Returns a store for scan results that an inline parser method memoizes for the inline subject.
	 *
	 * The store is discarded together with the inline subject.
	 *
	 * @param string $name the name that identifies the store.
	 * @return \ArrayObject the store.
	 */
	protected function inlineMemo($name)
	{
		if (!isset($this->_inlineCache['memo'][$name])) {
			$this->_inlineCache['memo'][$name] = new \ArrayObject();
		}

		return $this->_inlineCache['memo'][$name];
	}

	/**
	 * Returns the characters that `\s` matches in a regular expression.
	 *
	 * @return string the whitespace characters, usable as a mask for `strspn()` and `strcspn()`.
	 */
	protected function inlineWhitespace()
	{
		if ($this->_whitespace === null) {
			$this->_whitespace = preg_replace('/\S/', '', implode('', array_map('chr', range(0, 255))));
		}

		return $this->_whitespace;
	}

	/**
	 * Collects all balanced pairs of delimiters of the inline subject in a single pass.
	 *
	 * @param string $open the opening delimiter.
	 * @param string $close the closing delimiter.
	 * @param string $breaks the characters that discard all delimiters that are still open.
	 * @return array the offsets of the opening delimiters in ascending order and the offsets of their
	 * closing delimiters.
	 */
	private function scanBalanced($open, $close, $breaks)
	{
		$subject = $this->_inlineSubject;
		$length = strlen($subject);
		$mask = $open . $close . $breaks;

		$opened = [];
		$closed = [];
		$stack = [];
		for ($i = strcspn($subject, $mask); $i < $length; $i += 1 + strcspn($subject, $mask, $i + 1)) {
			$char = $subject[$i];
			if ($char === $open) {
				$stack[] = count($opened);
				$opened[] = $i;
				$closed[] = -1;
			} elseif ($char !== $close) {
				$stack = [];
			} elseif (!empty($stack)) {
				$closed[array_pop($stack)] = $i;
			}
		}

		$starts = [];
		$ends = [];
		foreach ($closed as $index => $end) {
			if ($end !== -1) {
				$starts[] = $opened[$index];
				$ends[] = $end;
			}
		}

		return [$starts, $ends];
	}

	/**
	 * Discards the inline scan state of a previous run.
	 */
	private function resetInlineState()
	{
		$this->_inlineSubject = '';
		$this->_inlineRest = '';
		$this->_inlineOffset = 0;
		$this->_inlineCache = [];
		$this->_whitespace = null;
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

	/**
	 * This function renders plain text sections in the markdown text.
	 * It can be used to work on normal text sections for example to highlight keywords or
	 * do special escaping.
	 */
	protected function renderText($block)
	{
		return $block[1];
	}
}
