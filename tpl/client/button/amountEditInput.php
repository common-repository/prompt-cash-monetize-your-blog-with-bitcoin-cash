<?php
if (!defined('ABSPATH'))
	exit("denied");
?>
<?php if($btnConf['edit'] === true):?>
	<input type="number" min="<?php echo $btnConf['amount'];?>" step="0.01" name="amount" class="input ct-input-amount ct-input-amount-<?php echo $btnConf['postID'];?>" value="<?php echo $btnConf['amount']?>" placeholder="<?php echo $btnConf['currency']?>">
<?php endif;?>