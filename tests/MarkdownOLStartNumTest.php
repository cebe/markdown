<?php

namespace cebe\markdown\tests;

use cebe\markdown\Markdown;

/**
 * Test support ordered lists at arbitrary number(`start` html attribute)
 * @author Maxim Hodyrew <maximkou@gmail.com>
 */
class MarkdownOLStartNumTest extends BaseMarkdownTest
{
	public function createMarkdown()
	{
		$markdown = new Markdown();
		$markdown->keepListStartNumber = true;
		return $markdown;
	}

	public function getDataPaths()
	{
		return [
			'markdown-data' => __DIR__ . '/markdown-ol-start-num-data',
		];
	}
}
