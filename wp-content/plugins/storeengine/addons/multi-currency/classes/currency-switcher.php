<?php
/**
 * Currency Switcher Widget
 *
 * Renders the frontend currency selector.
 * Can be placed via:
 *   - Shortcode:  [se_currency_switcher]
 *   - PHP call:   CurrencySwitcher::render()
 *   - Menu item:  registered as a nav menu item type
 *
 * The switcher posts to the multi_currency/switch AJAX endpoint on change,
 * then reloads the page so prices update.
 *
 * @package StoreEngine\Addons\MultiCurrency
 */

namespace StoreEngine\Addons\MultiCurrency\Classes;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CurrencySwitcher {

	public function __construct() {
		add_shortcode( 'storeengine_currency_switcher', [ $this, 'render_shortcode' ] );
	}

	// ── Shortcode ─────────────────────────────────────────────────────────

	public function render_shortcode( array $atts = [] ): string {
		if ( ! Settings::get( 'show_switcher', true ) ) {
			return '';
		}

		ob_start();
		self::render();
		return ob_get_clean();
	}

	// ── Render ────────────────────────────────────────────────────────────

	/**
	 * Output the switcher HTML.
	 */
	public static function render(): void {
		$active     = ActiveCurrency::get();
		$enabled    = Settings::get( 'enabled_currencies', [] );
		$base       = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );

		// Build the full list: base + enabled currencies.
		$all = [];

		// Always include the base currency first.
		$all[] = [
			'code'   => $base,
			'label'  => Helper::get_currency_symbol( $base ) . ' ' . $base,
			'symbol' => Helper::get_currency_symbol( $base ),
		];

		foreach ( $enabled as $c ) {
			if ( ( $c['code'] ?? '' ) && strtoupper( $c['code'] ) !== $base ) {
				$code    = strtoupper( $c['code'] );
				$symbol  = Helper::get_currency_symbol( $code );
				$all[] = [
					'code'   => $code,
					'label'  => $symbol . ' ' . $code, // ( ! empty( $c['label'] ) ? ' — ' . $c['label'] : '' )
					'symbol' => $symbol,
				];
			}
		}

		if ( count( $all ) <= 1 ) {
			return; // Nothing to switch between.
		}

		// Find the active currency label for the trigger button.
		$active_label = '';
		foreach ( $all as $currency ) {
			if ( $currency['code'] === $active ) {
				$active_label = $currency['label'];
				break;
			}
		}
		?>
		<div class="storeengine-currency-switcher" data-active="<?php echo esc_attr( $active ); ?>">
			<label class="storeengine-currency-switcher__label"><?php esc_html_e( 'Currency', 'storeengine' ); ?></label>
			<div class="storeengine-currency-switcher__dropdown">
				<button
					type="button"
					class="storeengine-currency-switcher__trigger"
					aria-expanded="false"
					aria-haspopup="listbox"
					aria-label="<?php esc_attr_e( 'Select currency', 'storeengine' ); ?>"
				>
					<span class="storeengine-currency-switcher__current"><?php echo esc_html( $active_label ); ?></span>
					<svg class="storeengine-currency-switcher__chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
						<polyline points="6 9 12 15 18 9"></polyline>
					</svg>
				</button>
				<ul
					class="storeengine-currency-switcher__options"
					role="listbox"
					aria-label="<?php esc_attr_e( 'Currency options', 'storeengine' ); ?>"
				>
					<?php foreach ( $all as $currency ) : ?>
						<li
							class="storeengine-currency-switcher__option<?php echo $active === $currency['code'] ? ' is-selected' : ''; ?>"
							role="option"
							data-value="<?php echo esc_attr( $currency['code'] ); ?>"
							aria-selected="<?php echo $active === $currency['code'] ? 'true' : 'false'; ?>"
						>
							<?php echo esc_html( $currency['label'] ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

}
