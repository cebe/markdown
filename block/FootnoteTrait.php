<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds the footnote blocks
 */
trait FootnoteTrait {

	/**
	 * identify a line as the beginning of a footnote block
	 */
	protected function identifyFootnote($line)
	{
		return preg_match('/^\[\^(\w+?)\]:/', $line);
	}

	/**
	 * Consume lines for a footnote
	 */
	protected function consumeFootnote($lines, $current)
	{
		$item = 0;
		$indent = '';
		$len = 0;
		$block = ['footnote', 'items' => []];
		// track the indentation of list markers, if indented more than previous element
		// a list marker is considered to be long to a lower level
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];

			// match list marker on the beginning of the line
			if (preg_match('/^\[\^(\w+?)\]:[ \t]+/', $line, $matches)) {
				if (($len = substr_count($matches[0], "\t")) > 0) {
					$indent = str_repeat("\t", $len);
					$line = substr($line, strlen($matches[0]));
				} else {
					$len = strlen($matches[0]);
					$indent = str_repeat(' ', $len);
					$line = substr($line, $len);
				}

				$block['items'][++$item][] = [
                    'content' => [$line],
                    'backref' => $matches[1]
                ];
			} elseif (ltrim($line) === '') {
				// next line after empty one is also a list or indented -> lazy list
				if (isset($lines[$i + 1][0]) && (
					$this->identifyFootnote($lines[$i + 1], $lines, $i + 1) ||
					(strncmp($lines[$i + 1], $indent, $len) === 0 || !empty($lines[$i + 1]) && $lines[$i + 1][0] == "\t"))) {
					$block['items'][$item][]['content'][] = $line;
					$block['lazyItems'][$item] = true;
				} else {
					break;
				}
			} else {
				if ($line[0] === "\t") {
					$line = substr($line, 1);
				} elseif (strncmp($line, $indent, $len) === 0) {
					$line = substr($line, $len);
				}
				$block['items'][$item][]['content'][] = $line;
			}
		}

		// make last item lazy if item before was lazy
		if (isset($block['lazyItems'][$item - 1])) {
			$block['lazyItems'][$item] = true;
		}

		foreach ($block['items'] as $itemId => $item) {
			$backref = null;
			$itemLines = [];
			foreach ($item as $content) {
				$itemLines += $content['content'];
				if (isset($content['backref'])) {
					$backref = $content['backref'];
				}
			}
			$content = [];
			if (!isset($block['lazyItems'][$itemId])) {
				$firstPar = [];
				while (!empty($itemLines) && rtrim($itemLines[0]) !== '' && $this->detectLineType($itemLines, 0) === 'paragraph') {
					$firstPar[] = array_shift($itemLines);
				}
				$content = $this->parseInline(implode("\n", $firstPar));
			}
			if (!empty($itemLines)) {
				$content = array_merge($content, $this->parseBlocks($itemLines));
			}
			$block['items'][$itemId] = [
				'backref' => $backref,
				'content' => $content,
			];
		}

		return [$block, $i];
	}

	/**
	 * Render a footnote block
	 *
	 * @param $block
	 * @return string
	 */
	protected function renderFootnote($block)
	{
		$type = 'ol';

		if (!empty($block['attr'])) {
			$output = "<$type " . $this->generateHtmlAttributes($block['attr']) . ">\n";
		} else {
			$output = "<$type>\n";
		}

		foreach ($block['items'] as $item) {
			$backlink = '';
			if (!empty($item['backref'])) {
				$backlink = '&nbsp;<a href="#fnref:' . $item['backref'] . '" rev="footnote" class="footnote-backref" title="Jump back to footnote ' . $item['backref'] . ' in the text">&#8617;</a>';
			}

			$itemLineOutput = trim($this->renderAbsy($item['content']));
			if (!empty($backlink) && preg_match('/<\/p>$/', $itemLineOutput)) {
				$itemLineOutput = substr($itemLineOutput, 0, -4) . $backlink . '</p>';
			}

			$output .= '<li id="fn:' . $item['backref'] . '">' . $itemLineOutput . "</li>\n";
		}

		return '<footnotes>' .$output . "</ol></footnotes>\n";
	}

}
