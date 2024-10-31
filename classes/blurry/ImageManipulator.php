<?php
namespace Ekliptor\PromptCash;

class ImageManipulator {
	/** @var string */
	protected $extension;
	
	public function __construct(string $imageExtension) {
		$this->extension = strtolower($imageExtension);
		switch ($this->extension)
		{
			case 'jpg':
			case 'jpeg': // shouldn't be present herex
			case 'png':
			case 'gif':
			case 'bmp':
			case 'webp':
				break;
			default:
				throw new \Error('Unsupported image extension to create blurry images: ' . $imageExtension);
		}
	}
	
	public function create(string $filename) {
		switch ($this->extension)
		{
			case 'jpg':
			case 'jpeg':
				return imagecreatefromjpeg($filename);
			case 'png':
				return imagecreatefrompng($filename);
			case 'gif':
				return imagecreatefromgif($filename); // bad idea applying filters on gif?
			case 'bmp':
				if (function_exists('imagecreatefrombmp') === true)
					return imagecreatefrombmp($filename); // PHP >= 7.2
				break;
			case 'webp':
				if (function_exists('imagecreatefromwebp') === true) // officially PHP >= 5.4, but still missing on some PHP 7.0 installs
					return imagecreatefromwebp($filename);
				break;
		}
		return false;
	}
	
	public function get($image, $outputPath = null, $quality = null) {
		switch ($this->extension)
		{
			case 'jpg':
			case 'jpeg':
				return imagejpeg($image, $outputPath, $quality);
			case 'png':
				return imagepng($image, $outputPath, $quality, null);
			case 'gif':
				return imagegif($image, $outputPath);
			case 'bmp':
				return imagebmp($image, $outputPath, true);
			case 'webp':
				return imagewebp($image, $outputPath);
		}
		return null;
	}
}
?>