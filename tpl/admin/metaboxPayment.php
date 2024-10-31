		<h4><?php esc_html_e( 'Shortcodes for Posts and Pages', 'ekliptor' ); ?></h4>
		<?php
		$this->description( __( 'You can put a tip button on every page or post by just writing: [prompt_button]', 'ekliptor' ) );
		$this->description( __( 'To use a specific amount: [prompt_button amount="5.5" currency="USD"]', 'ekliptor' ) );
		$this->description( __( 'To let the user enter an amount, add the "edit" attribute: [prompt_button amount="5.5" currency="USD" edit]', 'ekliptor' ) );
		$this->description( __( 'Write a custom text before the button: [prompt_button text="Donate to the author"]', 'ekliptor' ) );
		$this->description( __( 'Hide text: [prompt_hide]hidden text or download links[/prompt_hide]', 'ekliptor' ) );
		?>
		<hr>

		<p>
			<label for="<?php $this->fieldId( 'button_currency' ); ?>" class="ct-toblock">
				<?php esc_html_e( 'Button currency:', 'ekliptor' ); ?>
			</label>
		</p>
		<p class="ct-input-wrap">
			<input type="text" name="<?php $this->fieldName( 'button_currency' ); ?>" class="large-text" id="<?php $this->fieldId( 'button_currency' ); ?>" placeholder="<?php echo esc_attr( $btnCurrencyPlaceholder ); ?>" value="<?php echo esc_attr( $this->getFieldValue( 'button_currency' ) ); ?>" />
		</p>
		
		<p>
			<label for="<?php $this->fieldId( 'default_amount' ); ?>" class="ct-toblock">
				<?php esc_html_e( 'Default button amount:', 'ekliptor' ); ?>
			</label>
		</p>
		<p class="ct-input-wrap">
			<input type="number" min="0.0000001" step="0.0000001" name="<?php $this->fieldName( 'default_amount' ); ?>" class="large-text" id="<?php $this->fieldId( 'default_amount' ); ?>" placeholder="<?php echo esc_attr( $defAmountPlaceholder ); ?>" value="<?php echo esc_attr( $this->getFieldValue( 'default_amount' ) ); ?>" />
		</p>
		
		<p>
			<label for="<?php $this->fieldId( 'tip_btn_txt' ); ?>" class="ct-toblock">
				<?php esc_html_e( 'Text on the tip button:', 'ekliptor' ); ?>
			</label>
		</p>
		<p class="ct-input-wrap">
			<input type="text" name="<?php $this->fieldName( 'tip_btn_txt' ); ?>" class="large-text" id="<?php $this->fieldId( 'tip_btn_txt' ); ?>" placeholder="<?php echo esc_attr( $tipTextPlaceholder ); ?>" value="<?php echo esc_attr( $this->getFieldValue( 'tip_btn_txt' ) ); ?>" />
		</p>
		
		<p>
			<label for="<?php $this->fieldId( 'hide_tip_txt' ); ?>" class="ct-toblock">
				<?php esc_html_e( 'Text for hidden content:', 'ekliptor' ); ?>
			</label>
		</p>
		<p class="ct-input-wrap">
			<input type="text" name="<?php $this->fieldName( 'hide_tip_txt' ); ?>" class="large-text" id="<?php $this->fieldId( 'hide_tip_txt' ); ?>" placeholder="<?php echo esc_attr( $hideTipTextPlaceholder ); ?>" value="<?php echo esc_attr( $this->getFieldValue( 'hide_tip_txt' ) ); ?>" />
		</p>
		
		<?php 
		
		
		
		