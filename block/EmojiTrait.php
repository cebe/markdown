<?php
/**
	9/4/2014 3:00:41 AM
	PHP Github Emoji - @cecekpawon THRSH
 */

namespace cebe\markdown\block;


trait EmojiTrait
{
	private $is_init = false;
	private $emoji_arr = array();
	private $emoji_db = "emoji.json"; ## https://github.com/github/gemoji/
	private $emoji_url = "https://assets.github.com/images/icons/emoji/unicode/";
	private $emoji_ext = ".png";

	protected function init() {
		$emoji_txt = file_get_contents($this->emoji_db);
		$this->emoji_arr = json_decode($emoji_txt, true);
		$this->is_init = true;
	}

	## http://php.net/manual/en/function.array-search.php
	protected function recursive_array_search($needle, $haystack) {
		foreach ($haystack as $key=>$value) {
			$current_key = $key;
			if(($needle === $value) OR (is_array($value) && ($this->recursive_array_search($needle, $value) !== false))) {
				return $current_key;
			}
		}

		return false;
	}

	public function parseEmojiExtd($str) {
		if (!$this->is_init) {
			$this->init();
		}

		$str = strtolower(trim($str));

		$emoji = "";

		if ($key = $this->recursive_array_search($str, $this->emoji_arr)) {
			$emoji = $this->emoji_arr[$key]["emoji"];
			$emoji = preg_replace("#[^0-9]#", "", mb_convert_encoding($emoji, "HTML-ENTITIES", "utf-8"));
			$emoji = dechex($emoji);

			$emoji = "{$this->emoji_url}{$emoji}{$this->emoji_ext}";
    }

		return $emoji;
	}

}