A *simple* footnote[^1] and one with a label.[^label] Labels can be anything.[^✳&|^"]

[^1]: The *first* footnote, with [inline](https://example.org/) formatting.
[^third]: Out of order
    and with
    multiple lines.
[^label]: Labelled footnote (number 2)
also with
multiple lines.
[^✳&|^"]: Any characters are allowed.

Footnotes can be defined out of order[^third] (both where they're called and defined).

Block elements such as…

## …headers…[^block]

* …lists…[^block]

> …and quotes…[^block2]

…can contain footnotes, and footnotes can contain block elements.
One footnote can be referenced multiple times.

[^block]: A footnote (number 5) with block elements.
    
    The blocks must be _intented_
    
    * by the same *amount*, and
    * with a tab or four spaces.
    
    They can also contain
    
        code blocks.

[^block2]:
    Block footnotes can start
    
    on or after the first line.

End of test.
