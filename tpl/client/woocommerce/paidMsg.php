<?php
if (!defined('ABSPATH'))
	exit("denied");
?>
<p><?php esc_html_e('Your order has been fully paid.', 'ekliptor');?></p>
<p class="overs-cc-notice">
  <?php echo esc_html($msgConf['paid']);?>
</p>