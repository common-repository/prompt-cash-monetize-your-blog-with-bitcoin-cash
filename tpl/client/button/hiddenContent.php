<?php
if (!defined('ABSPATH'))
	exit("denied");
?>
<div class="ct-button">
	<div id="ct-button-text-<?php echo $btnConf['postID'];?>" class="ct-button-text">
	  <?php if(isset($btnConf['text']) && $btnConf['text'] !== ''):?>
	    <?php esc_html_e($btnConf['text']); ?>
	  <?php else:?>  
	    <span class="ct-restricted"><?php esc_html_e($btnConf['restrictedTxt']); ?></span>
	  <?php endif;?>  
	  <?php include PRCA__PLUGIN_DIR . 'tpl/client/button/amountEditInput.php';?>
	</div>
      <?php include PRCA__PLUGIN_DIR . 'tpl/client/button/buttonCode.php';?>
</div>
<?php if($this->settings->get('show_search_engines') === true):?>
  <div id="ct-hidden-<?php echo $btnConf['postID'];?>" class="ct-hidden-text">
    <?php echo $btnConf['content'];?>
  </div>
<?php endif;?>  