<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\tests;

/**
 * Unit tests for {@see \cebe\markdown\Parser} parsing input that is built to maximize the parsing time.
 *
 * Each input repeats a short sequence of markers. The expected markup repeats the markup of that sequence, so the
 * tests do not depend on timing. Without the scan results that the parser memoizes, parsing these inputs takes
 * minutes or exhausts the memory limit.
 */
class PathologicalInputTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Number of times a sequence is repeated.
	 */
	const REPETITIONS = 5000;

	/**
	 * @dataProvider repeatedSequences
	 */
	public function testParseRepeatedSequence($class, $head, $sequence, $tail, $expectedHead, $expectedSequence, $expectedTail)
	{
		$class = 'cebe\\markdown\\' . $class;
		$parser = new $class();

		$this->assertSame(
			$expectedHead . str_repeat($expectedSequence, self::REPETITIONS) . $expectedTail,
			$parser->parse($head . str_repeat($sequence, self::REPETITIONS) . $tail),
			'Markup must repeat the markup of the sequence.'
		);
	}

	public function repeatedSequences()
	{
		return [
			'unbalanced opening brackets' => ['Markdown', '', '[', 'a]', '<p>', '[', "a]</p>\n"],
			'opening brackets closed once' => ['Markdown', '', '[a ', ']', '<p>', '[a ', "]</p>\n"],
			'links without closing parenthesis' => ['Markdown', '', '[a](', '', '<p>', '[a](', "</p>\n"],
			'images without closing bracket' => ['Markdown', '', '![', '', '<p>', '![', "</p>\n"],
			'emphasis with asterisk' => ['Markdown', '', '*a* ', '', '<p>', '<em>a</em> ', "</p>\n"],
			'emphasis with underscore' => ['Markdown', '', '_a_ ', '', '<p>', '<em>a</em> ', "</p>\n"],
			'emphasis that is never closed' => ['Markdown', '', '*\\\\', '', '<p>', '*\\', "</p>\n"],
			'code without closing backticks' => ['Markdown', '', "``\ta`x", '', '<p>', "<code>`\ta</code>x", "</p>\n"],
			'email without closing bracket' => ['Markdown', '', '<@.', ' >', '<p>', '&lt;@.', " &gt;</p>\n"],
			'url without closing bracket' => ['Markdown', '', '<abc://x', ' >', '<p>', '&lt;abc://x', " &gt;</p>\n"],
			'html comment that is never closed' => ['Markdown', 'a ', '<!-- ', '>', '<p>a', ' &lt;!--', " &gt;</p>\n"],
			'indented lines in html tag' => ['Markdown', "<a x\n", "    b\n", '', '<p>&lt;a x', "\n    b", "</p>\n"],
			'url scheme without url' => ['GithubMarkdown', '', 'http ', '', '<p>', 'http ', "</p>\n"],
			'url with unbalanced parenthesis' => [
				'GithubMarkdown', '', 'http://a(', '', '<p>', '<a href="http://a">http://a</a>(', "</p>\n",
			],
			'url followed by punctuation' => [
				'GithubMarkdown', '', 'http://a.................... ', '',
				'<p>', '<a href="http://a">http://a</a>.................... ', "</p>\n",
			],
			'strikeout' => ['GithubMarkdown', '', '~~a~~ ', '', '<p>', '<del>a</del> ', "</p>\n"],
			'invalid table delimiter row' => [
				'GithubMarkdown', '', "a|b\n|----------------------------------------x\n\n", '',
				'', "<p>a|b\n|----------------------------------------x</p>\n", '',
			],
		];
	}

	/**
	 * @dataProvider nestedBrackets
	 */
	public function testParseNestedBracketsWithoutReference($class, $open)
	{
		$class = 'cebe\\markdown\\' . $class;
		$parser = new $class();
		$markdown = str_repeat($open, self::REPETITIONS) . 'a' . str_repeat(']', self::REPETITIONS);

		$this->assertSame(
			"<p>$markdown</p>\n", 
			$parser->parse($markdown), 
			'Brackets must be kept as text.',
		);
	}

	public function nestedBrackets()
	{
		return [
			'links' => ['Markdown', '['],
			'images' => ['Markdown', '!['],
			'links with special attributes' => ['MarkdownExtra', '['],
			'images with special attributes' => ['MarkdownExtra', '!['],
		];
	}

	public function testParseNestedBracketsWithReferenceBelowNestingLevel()
	{
		$parser = new \cebe\markdown\Markdown();
		$markdown = str_repeat('[', 5) . 'a' . str_repeat(']', 5) . "\n\n[a]: /url";

		$this->assertSame(
			"<p>[[[[<a href=\"/url\">a</a>]]]]</p>\n",
			$parser->parse($markdown),
			'Innermost brackets must be rendered as link.'
		);
	}
}
