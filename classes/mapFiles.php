<?php
$srcDir = dirname(__FILE__) . '/src/';

// $fileIdentifier (array key) has no purpose for caching & static builds. in our app. It's just present to have the same
// syntax as composer.
// It must still be unique!
return array(
	//'phpqrcode/src/helpers/constants.php' => $srcDir . 'phpqrcode/src/helpers/constants.php',
);
