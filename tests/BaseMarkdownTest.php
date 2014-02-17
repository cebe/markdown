<?php

namespace cebe\markdown\tests;

use cebe\markdown\Markdown;

/**
 * @author Carsten Brandt <mail@cebe.cc>
 */
abstract class BaseMarkdownTest extends \PHPUnit_Framework_TestCase
{
	abstract public function getDataPaths();

	abstract public function createMarkdown();

	/**
	 * @dataProvider dataFiles
	 */
	public function testParse($path, $file)
	{
		list($markdown, $html) = $this->getTestData($path, $file);
		// Different OS line endings should not affect test
		$html = preg_replace('~\r\n?~', "\n", $html);

		$m = $this->createMarkdown();
		$this->assertEquals($html, $m->parse($markdown));
	}

	public function getTestData($path, $file)
	{
		return [
			file_get_contents($this->getDataPaths()[$path] . '/' . $file . '.md'),
			file_get_contents($this->getDataPaths()[$path] . '/' . $file . '.html'),
		];
	}

	public function dataFiles()
	{
		$files = [];
		foreach($this->getDataPaths() as $name => $src) {
			$handle = opendir($src);
			if ($handle === false) {
				throw new \Exception('Unable to open directory: ' . $src);
			}
			while (($file = readdir($handle)) !== false) {
				if ($file === '.' || $file === '..') {
					continue;
				}

				if (substr($file, -3, 3) === '.md' && file_exists($src . '/' . substr($file, 0, -3) .  '.html')) {
					$files[] = [$name, substr($file, 0, -3)];
				}
			}
			closedir($handle);
		}
		return $files;
	}
}
