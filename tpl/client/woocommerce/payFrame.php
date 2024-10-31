<?php
if (!defined('ABSPATH'))
	exit("denied");
?>
<div class="ct-button-frame-<?php echo esc_attr($frameCfg['orderID']);?>">
	<iframe src="<?php echo esc_attr($frameCfg['url']);?>" scrolling="no" style="overflow: hidden;" width="400" height="800"></iframe>
</div>