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
	private $emoji_url = "https://github.global.ssl.fastly.net/images/icons/emoji/";
	private $emoji_ext = ".png?v5";

	protected function init() {
		include_once("emoji_db.inc");
		$this->emoji_arr = $emoji_db;
	}

	protected function parseEmojiExtd($str) {
		if (!$this->is_init) {
			$this->init();
		}

		if (count($this->emoji_arr)) {
			$this->is_init = true;

			$str = strtolower(trim($str));
			$key = array_search($str, $this->emoji_arr);

			if ($key !== FALSE) {
				return "{$this->emoji_url}{$this->emoji_arr[$key]}{$this->emoji_ext}";
			}
		}
	}
}