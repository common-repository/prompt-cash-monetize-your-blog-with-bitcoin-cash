
		<h4><?php esc_html_e( 'API Tokens', 'ekliptor' ); ?></h4>
		<p>
			<label for="<?php $this->fieldId( 'publicToken' ); ?>" class="ct-toblock">
				<strong><?php esc_html_e( 'Public Token:', 'ekliptor' ); ?></strong>
			</label>
		</p>
		<p class="ct-input-wrap">
			<input type="text" name="<?php $this->fieldName( 'publicToken' ); ?>" class="large-text" id="<?php $this->fieldId( 'publicToken' ); ?>" placeholder="<?php echo esc_attr( $accountTokenPlaceholder ); ?>" value="<?php echo esc_attr( $this->getFieldValue( 'publicToken' ) ); ?>" autocomplete=off />
		</p>
		
		<p>
			<label for="<?php $this->fieldId( 'secretToken' ); ?>" class="ct-toblock">
				<strong><?php esc_html_e( 'Secret Token:', 'ekliptor' ); ?></strong>
			</label>
		</p>
		<p class="ct-input-wrap">
			<input type="text" name="<?php $this->fieldName( 'secretToken' ); ?>" class="large-text" id="<?php $this->fieldId( 'secretToken' ); ?>" placeholder="<?php echo esc_attr( $accountTokenPlaceholder ); ?>" value="<?php echo esc_attr( $this->getFieldValue( 'secretToken' ) ); ?>" autocomplete=off />
		</p>
		
		<?php 
		
		$this->descriptionNoesc( sprintf(__( 'You can find your API tokens <a target="_blank" href="%s">here</a>', 'ekliptor' ), 'https://prompt.cash/account') );
		
		