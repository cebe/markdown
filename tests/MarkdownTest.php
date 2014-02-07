<?php

namespace cebe\markdown\tests;

use cebe\markdown\Markdown;

/**
 * @author Carsten Brandt <mail@cebe.cc>
 */
class MarkdownTest extends BaseMarkdownTest
{
	public function createMarkdown()
	{
		return new Markdown();
	}

	public function getDataPath()
	{
		return 'markdown-data';
	}
}
