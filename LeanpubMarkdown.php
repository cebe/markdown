<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown;

/**
 * Markdown parser for leanpub flavored markdown.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class LeanpubMarkdown extends \cebe\markdown\GithubMarkdown {

    // include block element parsing using traits
    use block\FootnoteTrait;
    use block\LeanpubQuoteTrait;

    // include inline element parsing using traits
    use inline\FootnoteLinkTrait;
    use inline\SuperscriptTrait;

}
