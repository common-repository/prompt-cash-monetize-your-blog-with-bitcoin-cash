<?php
if (!defined('ABSPATH'))
	exit("denied");
?>
<div class="ct-btn-wrap-<?php echo esc_attr($btnConf['postID']);?> ct-btn-wrap-top">
	<div class="ct-button-wrap ct-btn-wrap-<?php echo esc_attr($btnConf['postID']);?>">
      <button 
      	class="ct-button-tip"
      	data-id="<?php echo esc_attr($btnConf['postID']);?>"
      	data-amount="<?php echo esc_attr($btnConf['amount']);?>"
      	data-currency="<?php echo esc_attr($btnConf['currency']);?>"
      	data-restricted="<?php echo ($btnConf['isRestricted'] === true ? '1' : '0');?>"
      	><?php echo esc_html($btnConf['btnText']);?> <span class="ct-btn-display-amount"><?php echo $btnConf['amount'];?></span> <?php echo $btnConf['currency'];?></button>
    </div>  
</div>
<div class="ct-button-frame ct-button-frame-<?php echo esc_attr($btnConf['postID']);?>" style="display: none;"></div>