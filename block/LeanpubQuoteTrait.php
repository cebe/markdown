<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\block;

use Exception;

/**
 * Adds the leanpub-style quote blocks
 */
trait LeanpubQuoteTrait {

	protected $types = [
		'centeredparagraph' => 'C',
		'aside' => 'A',
		'warning' => 'W',
		'tip' => 'T',
		'error' => 'E',
		'information' => 'I',
		'question' => 'Q',
		'discussion' => 'D',
		'exercise' => 'X',
		'generic' => 'G',
	];


	public function __call($method, $args)
	{
		$type = lcfirst(preg_replace('/^consume/', '', $method));
		if (isset($this->types[$type])) {
			$args[2] = $type;
			$args[3] = $this->types[$type];
			return call_user_func_array([&$this, '_consumeQuote'], $args);
		}

		$type = preg_replace('/^render/', '', $method);
		if (isset($this->types[$type])) {
			$args[1] = $type;
			return call_user_func_array([&$this, '_renderQuote'], $args);
		}

		throw new Exception(sprintf('Method missing %s : %s', $method, $type));
	}

	protected function identifyCenteredparagraph($line) {
		return $this->_identifyQuote($line, 'C');
	}

	protected function identifyAside($line) {
		return $this->_identifyQuote($line, 'A');
	}

	protected function identifyWarning($line) {
		return $this->_identifyQuote($line, 'W');
	}

	protected function identifyTip($line) {
		return $this->_identifyQuote($line, 'T');
	}

	protected function identifyError($line) {
		return $this->_identifyQuote($line, 'E');
	}

	protected function identifyInformation($line) {
		return $this->_identifyQuote($line, 'I');
	}

	protected function identifyQuestion($line) {
		return $this->_identifyQuote($line, 'Q');
	}

	protected function identifyDiscussion($line) {
		return $this->_identifyQuote($line, 'D');
	}

	protected function identifyExercise($line) {
		return $this->_identifyQuote($line, 'X');
	}

	protected function identifyGeneric($line) {
		return $this->_identifyQuote($line, 'G');
	}

	/**
	 * Consume lines for a paragraph type
	 */
	protected function _consumeQuote($lines, $current, $type, $token)
	{
		// consume until newline
		$content = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (ltrim($line) !== '') {
				if ($line[0] == $token && isset($line[1]) && $line[1] == '>' && !isset($line[2])) {
					$line = '';
				} elseif (strncmp($line, $token . '> ', 3) === 0) {
					$line = substr($line, 3);
				}
				$content[] = $line;
			} else {
				break;
			}
		}

		$block = [
			$type,
			'content' => $this->parseBlocks($content),
			'simple' => true,
		];
		return [$block, $i];
	}

	/**
	 * Render a paragraph type block
	 *
	 * @param $block
	 * @param $type
	 * @return string
	 */
	protected function _renderQuote($block, $type)
	{
		return '<blockquote class="notquote ' . $type . '" data-type="' . $type . '">' . $this->renderAbsy($block['content']) . '</blockquote>';
	}

	/**
	 * identify a line as the beginning of a block quote.
	 */
	protected function _identifyQuote($line, $token)
	{
		return $line[0] ===  $token && $line[1] === '>' && (!isset($line[2]) || ($l1 = $line[2]) === ' ' || $l1 === "\t");
	}
}
