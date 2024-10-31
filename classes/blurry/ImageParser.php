<?php
namespace Ekliptor\PromptCash;

class ImageTag {
	/** @var string */
	public $src = '';
	/** @var array */
	public $srcSet = array();
	
	public function getAllLinks(): array {
		$links = $this->srcSet;
		if ($this->src !== '')
			array_unshift($links, $this->src);
		return $links;
	}
}

class ImageParser extends AbstractImage {
	
	public function __construct() {
		parent::__construct();
	}
	
	public function parseImageTags(string $text): array {
		$tags = array();
		$textLower = strtolower($text);
		$start = 0;
		while (($start = mb_strpos($textLower, '<img ', $start, $this->encoding)) !== false)
		{
			$end = mb_strpos($textLower, '>', $start, $this->encoding);
			if ($end === false) { // invalid tag
				$start += 3;
				continue;
			}
			$tagText = mb_substr($textLower, $start, $end-$start, $this->encoding);
			$start += 3;
			$tag = new ImageTag();
			$tag->src = $this->getAttribute($tagText, 'src');
			if ($tag->src === false)
				continue;
			$tag->src = $this->ensureAbsoluteLink($tag->src);
			$srcSet = $this->getAttribute($tagText, 'srcset');
			if ($srcSet !== false)
				$tag->srcSet = $this->parseSourceSet($srcSet);
			$tags[] = $tag;
		}
		return $tags;
	}
	
	protected function parseSourceSet(string $srcSet): array {
		$setArr = explode(',', $srcSet);
		foreach ($setArr as &$link) {
			$link = preg_replace("/[0-9]+w$/i", "", $link);
			$link = $this->ensureAbsoluteLink(trim($link));
		}
		return $setArr;
	}
	
	protected function ensureAbsoluteLink(string $link): string {
		if (preg_match("/^http?s:\/\//i", $link) !== 1) {
			// we don't know if the relative link is in /uploads/ dir, so just add the base URL
			// if more than the domain has been removed from the relative path, the link likely won't work either way
			if (mb_strpos($link, $this->urlBase, 0, $this->encoding) === 0)
				return $link;
			if ($link[0] === '/')
				$link = mb_substr($link, 1, null, $this->encoding);
			$link = $this->urlBase . $link;
		}
		return $link;
	}
	
	protected function getAttribute(string $tagText, string $attr) {
		$value = $this->getBetween($tagText, $attr . '="', '"'); // TODO foo ="123" with spaces is valid html too, but WP shouldn't do this
		if ($value === false) {
			$value = $this->getBetween($tagText, $attr . "='", "'"); // WP uses double-quotes, but try a fallback
			if ($value === false)
				return false;
		}
		return trim($value);
	}
	
	protected function getBetween($string, $start_txt, $end_txt, &$start_pos = 0, $prestart_txt = '') {
		if ($prestart_txt != '') {
			$start_pos = mb_strpos($string, $prestart_txt, $start_pos, $this->encoding);
			if ($start_pos === false)
				return false;
			$start_pos += mb_strlen($prestart_txt, $this->encoding);
		}
		$start_pos = mb_strpos($string, $start_txt, $start_pos, $this->encoding);
		if ($start_pos === false)
			return false;
		$start_pos += mb_strlen($start_txt, $this->encoding);
		$end_pos = mb_strpos($string, $end_txt, $start_pos, $this->encoding);
		if ($end_pos === false)
			return false;
		return mb_substr($string, $start_pos, $end_pos-$start_pos, $this->encoding);
	}
}
?>