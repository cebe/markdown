<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\tests;

/**
 * Base class for all Test cases.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
abstract class BaseMarkdownTest extends \PHPUnit_Framework_TestCase
{
	protected $outputFileExtension = '.html';

	abstract public function getDataPaths();

	abstract public function createMarkdown();

	/**
	 * @dataProvider dataFiles
	 */
	public function testParse($path, $file)
	{
		list($markdown, $html) = $this->getTestData($path, $file);
		// Different OS line endings should not affect test
		$html = preg_replace('~\R~', "\n", $html);

		$m = $this->createMarkdown();
		$this->assertEquals($html, $m->parse($markdown));
	}

	public function testInvalidUtf8()
	{
		$m = $this->createMarkdown();
		$this->assertEquals('<code>�</code>', $m->parseParagraph("`\x80`"));
	}

	public function getTestData($path, $file)
	{
		return [
			file_get_contents($this->getDataPaths()[$path] . '/' . $file . '.md'),
			file_get_contents($this->getDataPaths()[$path] . '/' . $file . $this->outputFileExtension),
		];
	}

	public function dataFiles()
	{
		$files = [];
		foreach ($this->getDataPaths() as $name => $src) {
			$handle = opendir($src);
			if ($handle === false) {
				throw new \Exception('Unable to open directory: ' . $src);
			}
			while (($file = readdir($handle)) !== false) {
				if ($file === '.' || $file === '..') {
					continue;
				}

				if (substr($file, -3, 3) === '.md' && file_exists($src . '/' . substr($file, 0, -3) .  $this->outputFileExtension)) {
					$files[] = [$name, substr($file, 0, -3)];
				}
			}
			closedir($handle);
		}
		return $files;
	}
}
