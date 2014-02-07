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

	public function getDataPath()
	{
		return 'github-data';
	}
}