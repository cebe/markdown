<?php

namespace cebe\markdown\tests;
use cebe\markdown\GithubMarkdown;

/**
 * @author Carsten Brandt <mail@cebe.cc>
 */
class GithubMarkdownTest extends BaseMarkdownTest
{
	public function createMarkdown()
	{
		return new GithubMarkdown();
	}

	public function getDataPaths()
	{
		return [
			'markdown-data' => __DIR__ . '/markdown-data',
			'github-data' => __DIR__ . '/github-data',
		];
	}

	public function testNewlines()
	{
		$markdown = $this->createMarkdown();
		$this->assertEquals("This is text<br />\nnewline\nnewline.", $markdown->parseParagraph("This is text  \nnewline\nnewline."));
		$markdown->enableNewlines = true;
		$this->assertEquals("This is text<br />\nnewline<br />\nnewline.", $markdown->parseParagraph("This is text  \nnewline\nnewline."));
	}
}