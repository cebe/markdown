A super fast, highly extensible markdown parser for PHP
=======================================================

Already supported:

- Default Markdown according to: <http://daringfireball.net/projects/markdown/syntax>
- Any mixed Markdown flavor you like because of its highly extensible structure.
- Will allow you to add additional language elements by directly hooking into the parser.
  No possible error-prone post or pre-processing needed.

WIP:

- Github flavored Markdown: <https://help.github.com/articles/github-flavored-markdown>
- Markdown Extra: <http://michelf.ca/projects/php-markdown/extra/>


Installation
------------

Install via composer by adding the following to your `composer.json` `require` section:

    "cebe/markdown": "*"

Run `composer update` afterwards.

Alternatively you can clone this repository and use the classes directly.
In this case you have to include the `Parser.php` and `Markdown.php` files yourself.


Usage
-----

### In your PHP project

    $parser = new \cebe\markdown\Markdown();
    $parser->parse($markdown);


### The command line script

You can use it to render this readme:

    bin/markdown README.md > README.html

or convert the original markdown description to html using the unix pipe:

    curl http://daringfireball.net/projects/markdown/syntax.text | bin/markdown > md.html


Extending
---------

TBD


FAQ
---

### Why another markdown parser?

Will answer this soon :)

Contact
-------

Feel free to contact me using [email](mailto:mail@cebe.cc) or [twitter](https://twitter.com/cebe_cc).
