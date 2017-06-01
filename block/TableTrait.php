<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds the table blocks
 */
trait TableTrait
{
	/**
	 * identify a line as the beginning of a table block.
	 */
	protected function identifyTable($line, $lines, $current)
	{
		return strpos($line, '|') !== false && isset($lines[$current + 1])
			&& preg_match('~^\\s*\\|?(\\s*:?-[\\-\\s]*:?\\s*\\|\\s*:?-[\\-\\s]*:?\\s*)+\\|?\\s*$~', $lines[$current + 1]);
	}

	/**
	 * Consume lines for a table
	 */
	protected function consumeTable($lines, $current)
	{
		// consume until newline

		$block = [
			'table',
			'cols' => [],
			'rows' => [],
		];
		$beginsWithPipe = $lines[$current][0] === '|';
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = rtrim($lines[$i]);

			// extract alignment from second line
			if ($i == $current+1) {
				$cols = explode('|', trim($line, ' |'));
				foreach($cols as $col) {
					$col = trim($col);
					if (empty($col)) {
						$block['cols'][] = '';
						continue;
					}
					$l = ($col[0] === ':');
					$r = (substr($col, -1, 1) === ':');
					if ($l && $r) {
						$block['cols'][] = 'center';
					} elseif ($l) {
						$block['cols'][] = 'left';
					} elseif ($r) {
						$block['cols'][] = 'right';
					} else {
						$block['cols'][] = '';
					}
				}

				continue;
			}
			if ($line === '' || $beginsWithPipe && $line[0] !== '|') {
				break;
			}
			if ($line[0] === '|') {
				$line = substr($line, 1);
			}
			if (substr($line, -1, 1) === '|' && (substr($line, -2, 2) !== '\\|' || substr($line, -3, 3) === '\\\\|')) {
				$line = substr($line, 0, -1);
			}

			array_unshift($this->context, 'table');
			$row = $this->parseInline($line);
			array_shift($this->context);

			$r = count($block['rows']);
			$c = 0;
			$block['rows'][] = [];
			foreach ($row as $absy) {
				if (!isset($block['rows'][$r][$c])) {
					$block['rows'][$r][] = [];
				}
				if ($absy[0] === 'boundary') {
					$c++;
				} else {
					$block['rows'][$r][$c][] = $absy;
				}
			}
		}

		return [$block, --$i];
	}

	/**
	 * render a table block
	 */
	protected function renderTable($block)
	{
		$content = '';
		$cols = $block['cols'];
		$first = true;
		foreach($block['rows'] as $row) {
			$cellTag = $first ? 'th' : 'td';
			$content .= '<tr>';
			foreach ($row as $c => $cell) {
				$align = empty($cols[$c]) ? '' : ' align="' . $cols[$c] . '"';
				$content .= "<$cellTag$align>" . trim($this->renderAbsy($cell)) . "</$cellTag>";
			}
			$content .= "</tr>\n";
			if ($first) {
				$content .= "</thead>\n<tbody>\n";
			}
			$first = false;
		}
		return "<table>\n<thead>\n$content</tbody>\n</table>\n";
	}

	/**
	 * @marker |
	 */
	protected function parseTd($markdown)
	{
		if (isset($this->context[1]) && $this->context[1] === 'table') {
			return [['boundary'], isset($markdown[1]) && $markdown[1] === ' ' ? 2 : 1];
		}
		return [['text', $markdown[0]], 1];
	}

	abstract protected function parseInline($text);
	abstract protected function renderAbsy($absy);
}
