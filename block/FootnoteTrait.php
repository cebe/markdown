<?php

namespace cebe\markdown\block;

trait FootnoteTrait
{

    /** @var string[][] Unordered array of footnotes. */
    protected $footnotes = [];

    /** @var int Incrementing counter of the footnote links. */
    protected $footnoteLinkNum = 0;

    /** @var string[] Ordered array of footnote links. */
    protected $footnoteLinks = [];

    /**
     * @inheritDoc
     */
    abstract protected function parseBlocks($lines);

    /**
     * @inheritDoc
     */
    abstract protected function renderAbsy($blocks);

    /**
     * @param $text
     * @return string
     */
    public function parse($text)
    {
        $html = parent::parse($text);

        // If no footnotes found, do nothing more.
        if (count($this->footnotes) === 0) {
            return $html;
        }

        // Sort all found footnotes by the order in which they are linked in the text.
        $footnotesSorted = [];
        $footnoteNum = 0;
        foreach ($this->footnoteLinks as $footnotePos => $footnoteLinkName) {
            foreach ($this->footnotes as $footnoteName => $footnoteHtml) {
                if ($footnoteLinkName === (string)$footnoteName) {
                    // First time sorting this footnote.
                    if (!isset($footnotesSorted[$footnoteName])) {
                        $footnoteNum++;
                        $footnotesSorted[$footnoteName] = [
                            'html' => $footnoteHtml,
                            'num' => $footnoteNum,
                            'refs' => [1 => $footnotePos],
                        ];
                    } else {
                        // Subsequent times sorting this footnote (i.e. every time it's referenced).
                        $footnotesSorted[$footnoteName]['refs'][] = $footnotePos;
                    }
                }
            }
        }

        // Replace the footnote substitution markers with their actual numbers.
        $referencedHtml = preg_replace_callback('/\x1Afootnote-(refnum|num)(.*?)\x1A/', function ($match) use ($footnotesSorted) {
            $footnoteName = $this->footnoteLinks[$match[2]];
            // Replace only the footnote number.
            if ($match[1] === 'num') {
                return $footnotesSorted[$footnoteName]['num'];
            }
            // For backlinks, some have a footnote number and an additional link number.
            if (count($footnotesSorted[$footnoteName]['refs']) > 1) {
                // If this footnote is referenced more than once, use the `-x` suffix.
                $linkNum = array_search($match[2], $footnotesSorted[$footnoteName]['refs']);
                return $footnotesSorted[$footnoteName]['num'] . '-' . $linkNum;
            } else {
                // Otherwise, just the number.
                return $footnotesSorted[$footnoteName]['num'];
            }
        }, $html);

        // Get the footnote HTML and add it to the end of the document.
        return $referencedHtml . $this->getFootnotesHtml($footnotesSorted);
    }

    /**
     * @param mixed[] $footnotesSorted Array with 'html', 'num', and 'refs' keys.
     * @return string
     */
    protected function getFootnotesHtml(array $footnotesSorted)
    {
        $hr = $this->html5 ? "<hr>\n" : "<hr />\n";
        $footnotesHtml = "\n<div class=\"footnotes\" role=\"doc-endnotes\">\n$hr<ol>\n\n";
        foreach ($footnotesSorted as $footnoteInfo) {
            $backLinks = [];
            foreach ($footnoteInfo['refs'] as $refIndex => $refNum) {
                $fnref = count($footnoteInfo['refs']) > 1
                    ? $footnoteInfo['num'] . '-' . $refIndex
                    : $footnoteInfo['num'];
                $backLinks[] = '<a href="#fnref'.'-'.$fnref.'" role="doc-backlink">&#8617;&#xFE0E;</a>';
            }
            $linksPara = '<p class="footnote-backrefs">'.join("\n", $backLinks)."</p>";
            $footnotesHtml .= "<li id=\"fn-{$footnoteInfo['num']}\" role=\"doc-endnote\">\n{$footnoteInfo['html']}$linksPara\n</li>\n\n";
        }
        $footnotesHtml .= "</ol>\n</div>\n";
        return $footnotesHtml;
    }

    /**
     * Parses a footnote link indicated by `[^`.
     * @marker [^
     * @param $text
     * @return array
     */
    protected function parseFootnoteLink($text)
    {
        if (preg_match('/^\[\^(.+?)]/', $text, $matches)) {
            $footnoteName = $matches[1];

            // We will later order the footnotes according to the order that the footnote links appear in.
            $this->footnoteLinkNum++;
            $this->footnoteLinks[$this->footnoteLinkNum] = $footnoteName;

            // To render a footnote link, we only need to know its link-number,
            // which will later be turned into its footnote-number (after sorting).
            return [
                ['footnoteLink', 'num' => $this->footnoteLinkNum],
                strlen($matches[0])
            ];
        }
        return [['text', $text[0]], 1];
    }

    /**
     * @param string[] $block Array with 'num' key.
     * @return string
     */
    protected function renderFootnoteLink($block)
    {
        $substituteRefnum = "\x1Afootnote-refnum".$block['num']."\x1A";
        $substituteNum = "\x1Afootnote-num".$block['num']."\x1A";
        return '<sup id="fnref-' . $substituteRefnum . '" class="footnote-ref">'
            .'<a href="#fn-' . $substituteNum . '" role="doc-noteref">' . $substituteNum . '</a>'
            .'</sup>';
    }

    /**
     * identify a line as the beginning of a footnote block
     *
     * @param $line
     * @return false|int
     */
    protected function identifyFootnoteList($line)
    {
        return preg_match('/^\[\^(.+?)]:/', $line);
    }

    /**
     * Consume lines for a footnote
     * @return array Array of two elements, the first element contains the block,
     * the second contains the next line index to be parsed.
     */
    protected function consumeFootnoteList($lines, $current)
    {
        $name = '';
        $footnotes = [];
        $count = count($lines);
        $nextLineIndent = null;
        for ($i = $current; $i < $count; $i++) {
            $line = $lines[$i];
            $startsFootnote = preg_match('/^\[\^(.+?)]:[ \t]*/', $line, $matches);
            if ($startsFootnote) {
                // Current line starts a footnote.
                $name = $matches[1];
                $str = substr($line, strlen($matches[0]));
                $footnotes[$name] = [ trim($str) ];
            } else if (strlen(trim($line)) === 0) {
                // Current line is empty and ends this list of footnotes unless the next line is indented.
                if (isset($lines[$i+1])) {
                    $nextLineIndented = preg_match('/^(\t| {4})/', $lines[$i + 1], $matches);
                    if ($nextLineIndented) {
                        // If the next line is indented, keep this empty line.
                        $nextLineIndent = $matches[1];
                        $footnotes[$name][] = $line;
                    } else {
                        // Otherwise, end the current footnote.
                        break;
                    }
                }
            } elseif (!$startsFootnote && isset($footnotes[$name])) {
                // Current line continues the current footnote.
                $footnotes[$name][] = $nextLineIndent
                    ? substr($line, strlen($nextLineIndent))
                    : trim($line);
            }
        }

        // Parse all collected footnotes.
        $parsedFootnotes = [];
        foreach ($footnotes as $footnoteName => $footnoteLines) {
            $parsedFootnotes[$footnoteName] = $this->parseBlocks($footnoteLines);
        }

        return [['footnoteList', 'content' => $parsedFootnotes], $i];
    }

    /**
     * @param array $block
     * @return string
     */
    protected function renderFootnoteList($block)
    {
        foreach ($block['content'] as $footnoteName => $footnote) {
            $this->footnotes[$footnoteName] = $this->renderAbsy($footnote);
        }
        // Render nothing, because all footnote lists will be concatenated at the end of the text.
        return '';
    }
}
