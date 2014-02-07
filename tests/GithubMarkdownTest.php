<?php

namespace cebe\markdown\tests;

if (!class_exists('cebe\markdown\tests\MarkdownTest')) {
	require(__DIR__ . '/MarkdownTest.php');
}

/**
 * @author Carsten Brandt <mail@cebe.cc>
 */
class GithubMarkdownTest extends MarkdownTest
{
	protected $dataPath = 'github-data';
}