		<h4><?php esc_html_e( 'Blurry Images', 'ekliptor' ); ?></h4>
		
		<?php 
		$this->description( __( 'You can blur any image on posts/pages by just writing: [prompt_blur]your image[/prompt_blur]', 'ekliptor' ) );
		?>
		
		<p>
			<label for="<?php $this->fieldId( 'hide_image_txt' ); ?>" class="ct-toblock">
				<?php esc_html_e( 'Text before the blurry image tip button:', 'ekliptor' ); ?>
			</label>
		</p>
		<p class="ct-input-wrap">
			<input type="text" name="<?php $this->fieldName( 'hide_image_txt' ); ?>" class="large-text" id="<?php $this->fieldId( 'hide_image_txt' ); ?>" placeholder="<?php echo esc_attr( $tipImagePlaceholder ); ?>" value="<?php echo esc_attr( $this->getFieldValue( 'hide_image_txt' ) ); ?>" />
		</p>
		<?php 
		
		$cacheMbInfo = $this->makeInfo(
			__( 'The maximum cache size in MB on your local disk. If this size is reached, blurry images will be deleted from the cache even if they have been accessed recently. Note that this is a soft limit, meaning the cache can temporarily exceed this value.', 'ekliptor' ),
			'',
			false
		);
		?>
		<p>
			<label for="<?php $this->fieldId( 'blurry_cache_mb' ); ?>" class="ct-toblock">
				<?php esc_html_e( 'Maximum blurry image cache size (in MB):', 'ekliptor' ); echo $cacheMbInfo; ?>
			</label>
		</p>
		<p class="ct-input-wrap">
			<input type="number" min="1" step="1" name="<?php $this->fieldName( 'blurry_cache_mb' ); ?>" class="large-text" id="<?php $this->fieldId( 'blurry_cache_mb' ); ?>" placeholder="<?php echo esc_attr( $cacheMbPlaceholder ); ?>" value="<?php echo esc_attr( $this->getFieldValue( 'blurry_cache_mb' ) ); ?>" />
		</p>
		<?php 
		