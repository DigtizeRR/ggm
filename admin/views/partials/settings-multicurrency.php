<?php
/**
 * Settings Partial — Multi Currency.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$known_currencies = class_exists( 'GGM_Currency' ) ? GGM_Currency::currencies() : array();
$enabled_codes    = class_exists( 'GGM_Currency' ) ? GGM_Currency::enabled_codes() : array( 'INR' );
$base_currency    = class_exists( 'GGM_Currency' ) ? GGM_Currency::base_currency() : 'INR';
$default_api_url   = class_exists( 'GGM_Currency' ) ? GGM_Currency::api_url_template() : 'https://api.currencylayer.com/live?access_key={api_key}&source=USD&currencies={symbols}';
?>
<div class="ggm-settings-section-header">
	<h3><?php esc_html_e( 'Multi Currency Settings', 'ggm-member-dashboard' ); ?></h3>
	<p><?php esc_html_e( 'Show course and workshop prices in the visitor currency using exchange rates, with optional per-item extra amounts.', 'ggm-member-dashboard' ); ?></p>
</div>

<table class="form-table ggm-settings-table">
	<tr>
		<th><?php esc_html_e( 'Enable Multi Currency', 'ggm-member-dashboard' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="settings[ggm_multicurrency_enabled]" value="1" <?php checked( ! empty( $settings['ggm_multicurrency_enabled'] ) ); ?>>
				<?php esc_html_e( 'Show enabled currencies and charge checkout in the selected currency.', 'ggm-member-dashboard' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-multicurrency-default"><?php esc_html_e( 'Default Currency', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<select name="settings[ggm_multicurrency_default_currency]" id="ggm-multicurrency-default" class="regular-text">
				<?php foreach ( $known_currencies as $code => $currency ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $settings['ggm_multicurrency_default_currency'] ?? $base_currency, $code ); ?>>
						<?php echo esc_html( $code . ' — ' . $currency['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php printf( esc_html__( 'Base pricing is still read from %s amounts. Other currencies are converted from this base.', 'ggm-member-dashboard' ), esc_html( $base_currency ) ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-multicurrency-codes"><?php esc_html_e( 'Enabled Currencies', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="text" name="settings[ggm_multicurrency_enabled_codes]" id="ggm-multicurrency-codes" value="<?php echo esc_attr( $settings['ggm_multicurrency_enabled_codes'] ?? implode( ',', $enabled_codes ) ); ?>" class="large-text" placeholder="INR,USD,EUR,GBP,AED,SAR,AUD,CAD">
			<p class="description"><?php esc_html_e( 'Comma-separated ISO currency codes. The base currency is always enabled automatically.', 'ggm-member-dashboard' ); ?></p>
			<p class="description">
				<?php
				$labels = array();
				foreach ( $known_currencies as $code => $currency ) {
					$labels[] = $code . ' (' . $currency['symbol'] . ')';
				}
				echo esc_html( implode( ', ', $labels ) );
				?>
			</p>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Browser Currency Detection', 'ggm-member-dashboard' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="settings[ggm_multicurrency_auto_detect]" value="1" <?php checked( ! empty( $settings['ggm_multicurrency_auto_detect'] ?? '1' ) ); ?>>
				<?php esc_html_e( 'Automatically choose currency from browser locale when the visitor has not selected one yet.', 'ggm-member-dashboard' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-exchange-api-url"><?php esc_html_e( 'Exchange Rate API URL', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="url" name="settings[ggm_exchange_rate_api_url]" id="ggm-exchange-api-url" value="<?php echo esc_attr( $default_api_url ); ?>" class="large-text">
			<p class="description"><?php esc_html_e( 'Currencylayer /live format: use {api_key}. Keep source=USD for keys that do not support custom sources; {symbols} automatically includes the base currency for cross-rate conversion.', 'ggm-member-dashboard' ); ?></p>
			<p class="description"><code>https://api.currencylayer.com/live?access_key={api_key}&amp;source=USD&amp;currencies={symbols}</code></p>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-exchange-api-key"><?php esc_html_e( 'Exchange Rate API Key', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="password" name="settings[ggm_exchange_rate_api_key]" id="ggm-exchange-api-key" value="<?php echo esc_attr( $settings['ggm_exchange_rate_api_key'] ?? '' ); ?>" class="regular-text" autocomplete="off">
			<p class="description"><?php esc_html_e( 'Only needed if your custom API URL uses {api_key}.', 'ggm-member-dashboard' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="ggm-exchange-cache-hours"><?php esc_html_e( 'Rate Cache Hours', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<input type="number" min="1" max="168" step="1" name="settings[ggm_exchange_rate_cache_hours]" id="ggm-exchange-cache-hours" value="<?php echo esc_attr( $settings['ggm_exchange_rate_cache_hours'] ?? 12 ); ?>" class="small-text">
		</td>
	</tr>
	<tr>
		<th><label for="ggm-currency-manual-rates"><?php esc_html_e( 'Manual Fallback Rates', 'ggm-member-dashboard' ); ?></label></th>
		<td>
			<textarea name="settings[ggm_currency_manual_rates]" id="ggm-currency-manual-rates" class="large-text code" rows="6" placeholder="USD=0.012&#10;EUR=0.011&#10;AED=0.044"><?php echo esc_textarea( $settings['ggm_currency_manual_rates'] ?? '' ); ?></textarea>
			<p class="description"><?php printf( esc_html__( 'One per line: CODE=rate, where rate means 1 %s equals that currency. Used when the API has no rate for a currency.', 'ggm-member-dashboard' ), esc_html( $base_currency ) ); ?></p>
		</td>
	</tr>
</table>
