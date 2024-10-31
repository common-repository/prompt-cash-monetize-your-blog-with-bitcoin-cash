<?php
namespace Ekliptor\PromptCash;

class ImageCache extends AbstractImage {
	const IMG_CACHE_DIR = 'prompt-blurry/';
	const TIMEOUT_IMAGE_DL_SEC = 6;
	const DELETE_IMAGE_CACHE_H = 48; // deleter after the image hasn't be accesed for this time
	
	/** @var string */
	protected $uploadBaseDir;
	/** @var string */
	protected $cacheDir;
	
	public function __construct() {
		parent::__construct();
		//$this->cacheDir = trailingslashit( $wp_filesystem->wp_content_dir() );
		$dirs = wp_upload_dir();
		$this->uploadBaseDir = trailingslashit( $dirs['basedir'] );
		$this->cacheDir = static::getCacheDir();
	}
	
	/**
	 * Returns an ImageTag with images in the same order as in the original one, replacing each image URL with
	 * the URL of a blurred image.
	 * @param array ImageTag $originalImages
	 * @param bool $createBlurry Create the blurry images if they don't exist in the cache already. If this is false and no blurry image
	 * 		already exists, then the original unblurred image will be returned!
	 * @return array ImageTag
	 */
	public function getBlurryImages(array $originalImages, bool $createBlurry = false): array {
		// TODO move $createBlurry to a WP background task by doing non-blocking GET request
		$blurryImages = array();
		foreach ($originalImages as $image) {
			$blurry = new ImageTag();
			if ($image->src !== '') {
				$blurry->src = $this->getBlurryImageLink($image->src, $createBlurry);
			}
			foreach($image->srcSet as $src) {
				$blurry->srcSet[] = $this->getBlurryImageLink($src, $createBlurry);
			}
			$blurryImages[] = $blurry;
		}
		return $blurryImages;
	}
	
	public static function getCacheDir(): string {
		$dirs = wp_upload_dir();
		$uploadBaseDir = trailingslashit( $dirs['basedir'] );
		return $uploadBaseDir . static::IMG_CACHE_DIR;
	}
	
	public function cleanupImageCache(int $limitMb) {
		$cacheDir = ImageCache::getCacheDir();
		$files = scandir($cacheDir);
		if ($files === false) {
			PromptCash::notifyErrorExt('Error scanning image dir to cleanup', "cache dir: $cacheDir");
			return;
		}
		// cleanup by age, oldest access time first
		$deletionTime = time() - static::DELETE_IMAGE_CACHE_H*HOUR_IN_SECONDS;
		$usedBytes = 0;
		$fileAccessTimes = array();
		foreach ($files as $file) {
			if (empty($file) || $file[0] === '.')
				continue;
			$filePath = $cacheDir . '/' . $file;
			$lastAccess = fileatime($filePath);
			if ($lastAccess < $deletionTime)
				@unlink($filePath);
			else {
				$usedBytes += filesize($filePath);
				$fileAccessTimes[$filePath] = $lastAccess;
			}
		}
		
		// check max cache size and purge sooner
		$usedMb = $usedBytes / MB_IN_BYTES;
		//$limitMb = $this->settings->get('blurry_cache_mb');
		if ($usedMb <= $limitMb)
			return;
		asort($fileAccessTimes, SORT_NUMERIC);
		foreach ($fileAccessTimes as $filePath => $lastAccess) {
			$sizeMb = filesize($filePath) / MB_IN_BYTES; // could be improved by storing this in array too & use custom array sort function
			@unlink($filePath);
			$usedMb -= $sizeMb;
			if ($usedMb <= $limitMb)
				break;
		}
	}
	
	protected function getBlurryImageLink(string $imgUrl, bool $createBlurry): string {
		$localImg = $this->ensureImageLocal($imgUrl, $createBlurry);
		if ($localImg === false) // failed to download, nothing more we can do
			return $imgUrl; // show the unblurred image
		else if ($localImg === true) {
			// it's local and needs to be blurred
			$imgUrl = $this->toLocalPath($imgUrl);
			$ext = $this->tryGetImageExtension($imgUrl);
			if ($ext === false) // without ext + mime type we can't convert it
				return $imgUrl; // show the unblurred image
			
			$blurrySrc = $this->loadBlurryImage($imgUrl, $ext);
			if ($blurrySrc === false) {
				if ($createBlurry === true)
					return $this->absolutePathToUrl($this->createBlurryImage($imgUrl, $imgUrl, $ext));
				else
					return $imgUrl; // show the unblurred image
			}
			return $this->absolutePathToUrl($blurrySrc);
		}
		// else it's already blurred
		return $this->absolutePathToUrl($localImg);
	}
	
	/**
	 * Return the path of the blurry image from cache if it exists or false otherwise.
	 * @param string $imgUrl
	 * @return boolean|string
	 */
	protected function loadBlurryImage(string $imgUrl, string $ext) {
		// we want to avoid additional mysql queries for blurry image mappings
		// so we use HASH(imageUrl) as file names for blurry images
		$path = $this->getBlurryImagePath($imgUrl, $ext);
		if (file_exists($path) === false)
			return false;
		return $path;
	}
	
	protected function createBlurryImage(string $imgUrl, string $localImageFile, string $ext): string {
		$blurryPath = $this->getBlurryImagePath($imgUrl, $ext);
		//if (file_exists($blurryPath) === true) // not needed here
			//return $blurryPath;
		if ($this->applyImageFilters($localImageFile, $blurryPath, $ext) === false)
			return $imgUrl; // conversion failed, display the unblurred image
		return $blurryPath;
	}
	
	protected function getBlurryImagePath(string $fullOriginalPathOrUrl, string $ext): string {
		$hash = hash('sha512', $fullOriginalPathOrUrl);
		// TODO create subfolders to avoid performance issues with too many files in dir (on windows)
		$path = $this->cacheDir . $hash;
		if ($ext !== '')
			$path .= '.' . $ext;
		return $path;
	}
	
	protected function getFileExtension($name, $with_dot = true, $default = '') {
		$pos = strrpos($name, '.');
		if ($pos === false)
			return $default;
		if (!$with_dot)
			$pos++;
		$extension = substr($name, $pos);
		if (strlen($extension) > 5)
			return $default;
		return strtolower($extension);
	}
	
	protected function getMimeType(string $fileName) {
		if (class_exists('\finfo') === false)
			return false;
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		return $finfo->file($fileName); // returns image/jpeg etc... or false
	}
	
	protected function getExtensionFromMimeType(string $fileName) {
		$mime = $this->getMimeType($fileName);
		if ($mime === false || mb_strpos($mime, 'image/') !== 0)
			return false;
		switch ($mime)
		{
			case 'image/jpeg':
			case 'image/jpg': // shouldn't be possible
				return 'jpg';
			case 'image/png':
				return 'png';
			case 'image/bmp': // what about image/x-windows-bmp ?
				return 'bmp';
			case 'image/webp':
				return 'webp';
		}
		return false;
	}
	
	/**
	 * Try to get an image extension.
	 * @param string $fullOriginalPathOrUrl
	 * @return boolean|string the extension or false
	 */
	protected function tryGetImageExtension(string $fullOriginalPathOrUrl, bool $lookMimeType = true) {
		$urlPath = $fullOriginalPathOrUrl;
		$end = false;
		if (($end = mb_strpos($urlPath, '?', 0, $this->encoding)) !== false)
			$urlPath = mb_substr($urlPath, 0, $end, $this->encoding);
		$ext = $this->getFileExtension($urlPath, false, false);
		if ($ext === false && $lookMimeType === true) // check mime type as a fallback (although should be more accurate?)
			$ext = $this->getExtensionFromMimeType($urlPath);
		else if ($ext === 'jpeg')
			$ext = 'jpg';
		return $ext;
	}
	
	/**
	 * Ensures the image is stored on our WP server for applying image filters.
	 * @param string $imgUrl
	 * @param bool $createBlurry
	 * @return boolean|string The string of the blurry image if it was created. True if the image
	 */
	protected function ensureImageLocal(string $imgUrl, bool $createBlurry) {
		if (mb_stripos($imgUrl, $this->urlBase, 0, $this->encoding) === 0) // full url hosted on our site
			return true;
		else if (preg_match("/^https?:\/\//i", $imgUrl) !== 1) { // it's a relative url, so it must be local
			$localImageFile = $imgUrl;
			if (mb_strpos($localImageFile, $this->uploadBaseDir, 0, $this->encoding) === 0)
				return $localImageFile;
			if ($localImageFile[0] === '/')
				$localImageFile = mb_substr($localImageFile, 1, null, $this->encoding);
			$localImageFile = $this->uploadBaseDir . $localImageFile;
			return $localImageFile;
		}
		
		// the image hosted on another site
		// see if we have it cached already
		$ext = $this->tryGetImageExtension($imgUrl, false);
		if ($ext !== false) {
			$blurryPath = $this->getBlurryImagePath($imgUrl, $ext);
			if (file_exists($blurryPath) === true)
				return $blurryPath;
		}
		else {
			// we hacve to check if the file exists in our cache by its hash without extension. otherwise we download the image all the time
			// glob() with pattern is slow, better just read dir (and take care of subdirs in the name arg once we implement them)
			$blurryPathStart = $this->getBlurryImagePath($imgUrl, '');
			$blurryPath = $this->resolveFilenameWithoutExtension($blurryPathStart);
			if ($blurryPath !== false)
				return $blurryPath;
		}
		
		$tempFilename = download_url($imgUrl, static::TIMEOUT_IMAGE_DL_SEC); // TODO set user-agent for some sites that only allow browsers?
		if ($tempFilename instanceof \WP_Error)
			return false;
		
		if ($ext === false) {
			$ext = $this->getExtensionFromMimeType($tempFilename); // has a .tmp extension from WP
			if ($ext === false) {
				@unlink($tempFilename);
				return false; // we don't know how to blur an unknown file type
			}
		}
		$blurryPath = $this->getBlurryImagePath($imgUrl, $ext);
		if (rename($tempFilename, $blurryPath) === false) {
			@unlink($tempFilename);
			return false;
		}
		if ($createBlurry === false)
			return $blurryPath; // return the unblurred image
		return $this->createBlurryImage($imgUrl, $blurryPath, $ext);
	}
	
	protected function applyImageFilters(string $file, string $outputPath, string $ext): bool {
		$manipulator = null;
		try {
			$manipulator = new ImageManipulator($ext);
		}
		catch (\Error $e) {
			//PromptCash::notifyErrorExt("Error loading image manipulator, $e);
			return false;
		}
		$image = $manipulator->create($file);
		if ($image === false)
			return false;
		list($w, $h) = getimagesize($file);
		
		// downsized image
		$size = array('sm'=>array('w'=>intval($w/4), 'h'=>intval($h/4)),
                   'md'=>array('w'=>intval($w/2), 'h'=>intval($h/2))
                  );
		
		// Scale by 25% and apply Gaussian blur
		$sm = imagecreatetruecolor($size['sm']['w'],$size['sm']['h']);
		imagecopyresampled($sm, $image, 0, 0, 0, 0, $size['sm']['w'], $size['sm']['h'], $w, $h);

		for ($x=1; $x <=10; $x++){
			imagefilter($sm, IMG_FILTER_GAUSSIAN_BLUR, 999);
		} 

		imagefilter($sm, IMG_FILTER_SMOOTH, 99);
		imagefilter($sm, IMG_FILTER_BRIGHTNESS, 10);        

		// Scale result by 200% and blur again
		$md = imagecreatetruecolor($size['md']['w'], $size['md']['h']);
		imagecopyresampled($md, $sm, 0, 0, 0, 0, $size['md']['w'], $size['md']['h'], $size['sm']['w'], $size['sm']['h']);
		imagedestroy($sm);
		
		for ($x=1; $x <=5; $x++){
			imagefilter($md, IMG_FILTER_GAUSSIAN_BLUR, 999);
		} 
		
		imagefilter($md, IMG_FILTER_SMOOTH, 99);
		imagefilter($md, IMG_FILTER_BRIGHTNESS, 10);        
		
		// Scale result back to original size
		imagecopyresampled($image, $md, 0, 0, 0, 0, $w, $h, $size['md']['w'], $size['md']['h']);
		imagedestroy($md);    

		$saved = $manipulator->get($image, $outputPath);
		imagedestroy($image);
		return $saved === true;
	}
}
?>