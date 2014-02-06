<?php
/**
 * @author Carsten Brandt <mail@cebe.cc>
 */

namespace cebe\markdown\tests;

use cebe\markdown\Markdown;

require_once(__DIR__ . '/../Parser.php');
require_once(__DIR__ . '/../Markdown.php');

class MarkdownTest extends \PHPUnit_Framework_TestCase
{
	protected $dataPath = 'markdown-data';

	/**
	 * @dataProvider dataFiles
	 */
	public function testParagraph($file)
	{
		list($markdown, $html) = $this->getTestData($file);

		$m = new Markdown();
		$this->assertEquals($html, $m->parse($markdown));
	}

	public function getTestData($file)
	{
		return [
			file_get_contents(__DIR__ . '/' . $this->dataPath . '/' . $file . '.md'),
			file_get_contents(__DIR__ . '/' . $this->dataPath . '/' . $file . '.html'),
		];
	}

	public function dataFiles()
	{
		$src = __DIR__ . '/' . $this->dataPath;

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
