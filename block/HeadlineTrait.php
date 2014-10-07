<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\block;


trait HeadlineTrait
{
	protected function identifyHeadline($line, $lines, $current)
	{
		return (
			// heading with #
			$line[0] === '#'
			||
			// underlined heading
			!empty($lines[$current + 1]) &&
			($lines[$current + 1][0] === '=' || $lines[$current + 1][0] === '-') &&
			preg_match('/^(\-+|=+)\s*$/', $lines[$current + 1])
		);
	}

	/**
	 * Consume lines for a headline
	 */
	protected function consumeHeadline($lines, $current)
	{
		if ($lines[$current][0] === '#') {
			$level = 1;
			while (isset($lines[$current][$level]) && $lines[$current][$level] === '#' && $level < 6) {
				$level++;
			}
			$block = [
				'headline',
				'content' => $this->parseInline(trim($lines[$current], "# \t")),
				'level' => $level,
			];
			return [$block, $current];
		}

		$block = [
			'headline',
			'content' => $this->parseInline($lines[$current]),
			'level' => $lines[$current + 1][0] === '=' ? 1 : 2,
		];
		return [$block, $current + 1];
	}

	/**
	 * Renders a headline
	 */
	protected function renderHeadline($block)
	{
		$tag = 'h' . $block['level'];
		return "<$tag>" . $this->renderAbsy($block['content']) . "</$tag>\n";
	}
}
