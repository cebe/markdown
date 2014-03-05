<?php
/*
 * Copyright (c) 2014 Carsten Brandt <mail@cebe.cc>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */


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
