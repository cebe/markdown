A super fast, highly extensible markdown parser for PHP
=======================================================

[![Total Downloads](https://poser.pugx.org/cebe/markdown/downloads.png)](https://packagist.org/packages/cebe/markdown)
[![Build Status](https://secure.travis-ci.org/cebe/markdown.png)](http://travis-ci.org/cebe/markdown)
[![Code Coverage](https://scrutinizer-ci.com/g/cebe/markdown/badges/coverage.png?s=db6af342d55bea649307ef311fbd536abb9bab76)](https://scrutinizer-ci.com/g/cebe/markdown/)
<!-- [![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/cebe/markdown/badges/quality-score.png?s=17448ca4d140429fd687c58ff747baeb6568d528)](https://scrutinizer-ci.com/g/cebe/markdown/) -->

What is this?
-------------

A set of [PHP][] classes, each representing a [Markdown][] flavor, and a command line tool
for converting markdown files to HTML files.

The implementation focus is to be **fast** (see [benchmark][]) and **extensible**. You are able to add additional language elements by
directly hooking into the parser - no (possibly error-prone) post- or pre-processing is needed to extend the language.

Currently the following markdown flavors are supported:

- **The original Markdown** according to <http://daringfireball.net/projects/markdown/syntax>.
- **Github flavored Markdown** according to <https://help.github.com/articles/github-flavored-markdown> (currently does not support tables).
- Any mixed Markdown flavor you like because of its highly extensible structure (See documentation below).

Future plans are to support:

- Markdown Extra according to <http://michelf.ca/projects/php-markdown/extra/>.
- Smarty Pants <http://daringfireball.net/projects/smartypants/>
- ... (Feel free to [suggest](https://github.com/cebe/markdown/issues/new) further additions!)


Installation
------------

PHP 5.4 or higher is required to use it.

Installation is recommended to be done via [composer][] by adding the following to the `require` section in your `composer.json`:

```json
"cebe/markdown": "*"
```

Run `composer update` afterwards.

Alternatively you can clone this repository and use the classes directly.
In this case you have to include the `Parser.php` and `Markdown.php` files yourself.


Usage
-----

### In your PHP project

To use the parser as is, you just create an instance of a provided flavor and call the `parse()`-
or `parseParagraph()`-method:

```php
// default markdown and parse full text
$parser = new \cebe\markdown\Markdown();
$parser->parse($markdown);

// use github
$parser = new \cebe\markdown\GithubMarkdown();
$parser->parse($markdown);

// parse only inline elements (useful for one-line descriptions)
$parser = new \cebe\markdown\GithubMarkdown();
$parser->parseParagraph($markdown);
```

### The command line script

You can use it to render this readme:

    bin/markdown README.md > README.html

Using github flavored markdown:

    bin/markdown --flavor=gfm README.md > README.html

or convert the original markdown description to html using the unix pipe:

    curl http://daringfireball.net/projects/markdown/syntax.text | bin/markdown > md.html


Extending the language
----------------------

Markdown consists of two types of language elements, I'll call them block and inline elements simlar to what you have in
HTML with `<div>` and `<span>`. Block elements are normally spreads over several lines and are separated by blank lines.
The most basic block element is a paragraph (`<p>`).
Inline elements are elements that are added inside of block elements i.e. inside of text.

This markdown parser allows you to extend the markdown language by changing existing elements behavior and also adding
new block and inline elements. You do this by extending from the parser class and adding/overriding class methods and
properties. For the different element types there are different ways to extend them as you will see in the following sections.

### Adding block elements

The markdown is parsed line by line to identify each non-empty line as one of the block element types.
This job is performed by the `indentifyLine()` method which takes the array of lines and the number of the current line
to identify as an argument. This method returns the name of the identified block element which will then be used to parse it.
In the following example we will implement support for [fenced code blocks][] which are part of the github flavored markdown.

[fenced code blocks]: https://help.github.com/articles/github-flavored-markdown#fenced-code-blocks
                      "Fenced code block feature of github flavored markdown"

```php
<?php

class MyMarkdown extends \cebe\markdown\Markdown
{
	protected function identifyLine($lines, $current)
	{
		// if a line starts with at least 3 backticks it is identified as a fenced code block
		if (strncmp($lines[$current], '```', 3) === 0) {
			return 'fencedCode';
		}
		return parent::identifyLine($lines, $current);
	}

	// ...
}
```

Parsing of a block element is done in two steps:

1. "consuming" all the lines belonging to it. In most cases this is iterating over the lines starting from the identified
   line until a blank line occurs. This step is implemented by a method named `consume{blockName}()` where `{blockName}`
   will be replaced by the name we returned in the `identifyLine()`-method. The consume method also takes the lines array
   and the number of the current line. It will return two arguments: an array representing the block element and the line
   number to parse next. In our example we will implement it like this:

   ```php
	protected function consumeFencedCode($lines, $current)
	{
		// create block array
		$block = [
			'type' => 'fencedCode',
			'content' => [],
		];
		$line = rtrim($lines[$current]);

		// detect language and fence length (can be more than 3 backticks)
		$fence = substr($line, 0, $pos = strrpos($line, '`') + 1);
		$language = substr($line, $pos);
		if (!empty($language)) {
			$block['language'] = $language;
		}

		// consume all lines until ```
		for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
			if (rtrim($line = $lines[$i]) !== $fence) {
				$block['content'][] = $line;
			} else {
				// stop consuming when code block is over
				break;
			}
		}
		return [$block, $i];
	}
	```

2. "rendering" the element. After all blocks have been consumed, they are beeing rendered using the `render{blockName}()`
   method:

   ```php
	protected function renderFencedCode($block)
	{
		$class = isset($block['language']) ? ' class="language-' . $block['language'] . '"' : '';
		return "<pre><code$class>" . htmlspecialchars(implode("\n", $block['content']) . "\n", ENT_NOQUOTES, 'UTF-8') . '</code></pre>';
	}
   ```

   You may also add code highlighting here. In general it would also be possible to render ouput in a different language than
   HTML for example LaTeX.


### Adding inline elements

Adding inline elements is different from block elements as they are directly parsed in the text where they occur.
An inline element is identified by a marker that marks the beginning of an inline element (e.g. `[` will mark a possible
beginning of a link or `` ` `` will mark inline code).

Inline markers are declared in the `inlineMarkers()`-method which returns a map from marker to parser method. That method
will then be called when a marker is found in the text. As an argument it takes the text starting at the position of the marker.
The parser method will return an array containing the text to append to the parsed markup and an offset of text it has
parsed from the input markdown. All text up to this offset will be removed from the markdown before the next marker will be searched.

As an example, we will add support for the [strikethrough][] feature of github flavored markdown:

[strikethrough]: https://help.github.com/articles/github-flavored-markdown#strikethrough "Strikethrough feature of github flavored markdown"

```php
<?php

class MyMarkdown extends \cebe\markdown\Markdown
{
	protected function inlineMarkers()
	{
		$markers = [
			'~~'    => 'parseStrike',
		];
		// merge new markers with existing ones from parent class
		return array_merge(parent::inlineMarkers(), $markers);
	}

	protected function parseStrike($markdown)
	{
		// check whether the marker really represents a strikethrough (i.e. there is a closing ~~)
		if (preg_match('/^~~(.+?)~~/', $markdown, $matches)) {
			return [
			    // return the parsed tag with its content and call `parseInline()` to allow
			    // other inline markdown elements inside this tag
				'<del>' . $this->parseInline($matches[1]) . '</del>',
				// return the offset of the parsed text
				strlen($matches[0])
			];
		}
		// in case we did not find a closing ~~ we just return the marker and skip 2 characters
		return [$markdown[0] . $markdown[1], 2];
	}
}
```


FAQ
---

### Why another markdown parser?

Inventing the wheel is not a good idea in general but as I found the need for a markdown parser that runs fast and is open to
extensions while all current implementations either lack extensibility or are too slow I decided to start my own implementation
based on what I had seen in other libraries taking the good points of both.
Inspiration on the implementation design and line based parsing has mostly come from [Parsedown][] which seems to be the [fastest][benchmark]
markdown parser in the PHP world right now, it is just very hard to extend it.


Contact
-------

Feel free to contact me using [email](mailto:mail@cebe.cc) or [twitter](https://twitter.com/cebe_cc).


[PHP]: http://php.net/ "PHP is a popular general-purpose scripting language that is especially suited to web development."
[Markdown]: http://en.wikipedia.org/wiki/Markdown "Markdown on Wikipedia"
[composer]: https://getcomposer.org/ "The PHP package manager"
[Parsedown]: http://parsedown.org/ "The Parsedown PHP Markdown parser"
[benchmark]: https://github.com/kzykhys/Markbench#readme "kzykhys/Markbench on github"
