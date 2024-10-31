<?php
namespace Ekliptor\PromptCash;

class AbstractImage {
	/** @var string The encoding of this WP installation. */
	protected $encoding;
	/** var string */
	protected $urlBase;
	
	public function __construct() {
		$this->encoding = get_bloginfo('charset');
		$this->urlBase = site_url('/');
	}
	
	protected function toLocalPath(string $imageUrl): string {
		$path = str_replace($this->urlBase, '', $imageUrl); // regex shouldn't be needed
		if (empty($path))
			return ''; // sth went wrong, shouldn't happen
		if (mb_strpos($path, WP_CONTENT_DIR, 0, $this->encoding) === false) { // ensure /wp-content/ is present exactly once
			if ($path[0] === '/')
				$path = mb_substr($path, 1, null, $this->encoding);
			$contentFolder =  basename(WP_CONTENT_DIR) . '/';
			if (mb_stripos($path, $contentFolder, 0, $this->encoding) !== false)
				$path = mb_substr($path, mb_strlen($contentFolder, $this->encoding), null, $this->encoding);
			$path = WP_CONTENT_DIR . '/' . $path; // content dir is without trailling slash
		}
		return $path;
	}
	
	protected function absolutePathToUrl(string $path = ''): string {
	    $url = str_replace(
	        wp_normalize_path( untrailingslashit( ABSPATH ) ),
	        site_url(),
	        wp_normalize_path( $path )
	    );
	    //return esc_url_raw( $url );
	    return $url;
	}
	
	/**
	 * Resolves a filename without an extension and returns the full file path including the extension.
	 * @param string $name The filename to check
	 * @return boolean|string The full path to the file or false if the file doesn't exist
	 */
	protected function resolveFilenameWithoutExtension(string $name) {
		// reads informations over the path
		$info = pathinfo($name);
		if (!empty($info['extension'])) {
			if (file_exists($name) === false)
				return false;
        	return $name;
		}
		
        $filename = $info['filename'];
        $len = mb_strlen($filename, $this->encoding);
        // open the folder
        $dh = @opendir($info['dirname']);
        if (!$dh)
        	return false;
        // scan each file in the folder
        while (($file = readdir($dh)) !== false)
        {
        	if (strncmp($file, $filename, $len) === 0) {
        		if (mb_strlen($name, $this->encoding) > $len) {
        			// if name contains a directory part
        			$name = mb_substr($name, 0, strlen($name) - $len, $this->encoding) . $file;
        		}
        		else {
                	// if the name is at the path root
                	$name = $file;
        		}
        		closedir($dh);
        		return $name;
        	}
        }
        // file not found
        closedir($dh);
        return false;
	}
}
?>