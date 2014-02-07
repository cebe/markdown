<?php

namespace cebe\markdown\tests;

use cebe\markdown\Markdown;

/**
 * @author Carsten Brandt <mail@cebe.cc>
 */
abstract class BaseMarkdownTest extends \PHPUnit_Framework_TestCase
{
	abstract public function getDataPath();

	abstract public function createMarkdown();

	/**
	 * @dataProvider dataFiles
	 */
	public function testParse($file)
	{
		list($markdown, $html) = $this->getTestData($file);
		// Different OS line endings should not affect test
		$html = preg_replace('~\r\n?~', "\n", $html);

		$m = $this->createMarkdown();
		$this->assertEquals($html, $m->parse($markdown));
	}

	public function getTestData($file)
	{
		return [
			file_get_contents(__DIR__ . '/' . $this->getDataPath() . '/' . $file . '.md'),
			file_get_contents(__DIR__ . '/' . $this->getDataPath() . '/' . $file . '.html'),
		];
	}

	public function dataFiles()
	{
		$src = __DIR__ . '/' . $this->getDataPath();

		$files = [];

		$handle = opendir($src);
		if ($handle === false) {
			throw new \Exception('Unable to open directory: ' . $src);
		}
		while (($file = readdir($handle)) !== false) {
			if ($file === '.' || $file === '..') {
				continue;
			}

			if (substr($file, -3, 3) === '.md' && file_exists($src . '/' . substr($file, 0, -3) .  '.html')) {
				$files[] = [substr($file, 0, -3)];
			}
		}
		closedir($handle);

		return $files;
	}
}
