<?php
/**
 * Created by Andrew Solovov.
 * User: Andrey
 * Date: 15.01.2021
 * Time: 18:36
 *
 * Project name: markdown
 *
 * @author     Andrew Solovov <pan.russian@gmail.com>
 * @copyright  1997-2021 Pan Russian solovov.ru
 */

namespace cebe\markdown\block;


trait FooterTrait
{

    protected $footnotes = [];
    protected $footnoteNum = 1;

    /**
     * @param $text
     * @return string
     */
    public function parse($text)
    {
        $absy = $this->parseBlocks(explode("\n", $text));

        foreach ($absy as $block) {
            if ($block[0] == 'footnote') {
                $block['num'] = $this->footnoteNum;
                $this->footnotes[] = $block;
                $this->footnoteNum++;
            }
        }
        $markup = parent::parse($text);
        $markup = $this->applyFooter($markup, $this->footnotes);

        return $markup;
    }

    /**
     * @param $content
     * @param $blocks
     * @return string
     */
    protected function applyFooter($content, $blocks)
    {
        $content .= '<hr>';
        foreach ($blocks as $block) {
            $number = $block['num'] . ". ";
            $link = '<a href="#fnref:' . $block['id'] . '" class="footnote-backref">↩</a>';
            $text = $this->renderAbsy($block['content']);
            $text = substr_replace($text, $number, 3, 0);
            $text = substr_replace($text, $link, -5, 0);

            $content .= '<footnotes id="fn:' . $block['id'] . '">' . $text . "</footnotes>\n";
        }
        return $content;
    }

    /**
     * Parses a footnote link indicated by `[^`.
     * @marker [^
     * @param $text
     * @return array
     */
    protected function parseFootnoteLink($text)
    {
        if (preg_match('/^\[\^(.+?)\]/', $text, $matches)) {
            return [
                ['footnoteLink', $matches[1]],
                strlen($matches[0])
            ];
        }
        return [['text', $text[0]], 1];
    }

    /**
     * @param $block
     * @return string
     */
    protected function renderFootnoteLink($block)
    {
        $footnoteId = $block[1];
        $num = 0;
        $found = false;
        foreach ($this->footnotes as $footnote) {
            $num ++;
            if ($footnote['id']==$footnoteId) {
                $found = true;
                break;
            }
        }
        if (!$found)
            $num = '?';

        $text = htmlspecialchars($block[1], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<sup id="fnref:' . $text . '"><a href="#fn:' . $text . '" class="footnote-ref" rel="footnote">[' . $num . ']</a></sup>';
    }

    /**
     * identify a line as the beginning of a footnote block
     *
     * @param $line
     * @return false|int
     */
    protected function identifyFootnoteList($line)
    {
        return preg_match('/^\[\^(.+?)\]:/', $line);
    }

    /**
     * Consume lines for a footnote
     */
    protected function consumeFootnoteList($lines, $current)
    {
        $id = '';
        $content = [];
        $count = count($lines);
        for ($i = $current; $i < $count; $i++) {
            $line = $lines[$i];

            if ($id == '') {
                if (preg_match('/^\[\^(.+?)\]:[ \t]+/', $line, $matches)) {
                    $id = $matches[1];
                    $str = substr($line, strlen($matches[0]));
                    $content[] = $str;
                }
            } else if (strlen(trim($line)) == 0) {
                break;
            } else {
                $content[] = ltrim($line);
            }
        }

        $block = ['footnote', 'id' => $id, 'content' => $this->parseBlocks($content)];

        return [$block, $i];
    }

    /**
     * @param $block
     * @return string
     */
    protected function renderFootnote($block)
    {
        return '';
    }


}