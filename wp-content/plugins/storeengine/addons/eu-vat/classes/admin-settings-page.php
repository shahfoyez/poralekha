<?php
/**
 * EU VAT Admin Settings Page.
 *
 * Why a separate PHP screen instead of a React tab
 * ────────────────────────────────────────────────
 * StoreEngine's main settings UI is a compiled React app. Injecting a new tab
 * cleanly requires shipping JS into that bundle. For a free addon scoped to
 * "basic features" we register a vanilla PHP submenu page under the StoreEngine
 * menu — visible only while the addon is active. The save handler is a normal
 * admin-post.php endpoint, nonce-protected, manage_options-gated.
 *
 * @package StoreEngine\Addons\EuVat
 */

namespace StoreEngine\Addons\EuVat\Classes;

use StoreEngine\Classes\Countries;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminSettingsPage {

	const MENU_SLUG    = 'storeengine-eu-vat';
	const NONCE_ACTION = 'storeengine_eu_vat_save_settings';
	const POST_ACTION  = 'storeengine_eu_vat_save_settings';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );
		add_action( 'admin_post_' . self::POST_ACTION, [ $this, 'handle_save' ] );
		add_action( 'admin_notices', [ $this, 'maybe_show_saved_notice' ] );
	}

	public function register_menu(): void {
		add_submenu_page(
			'storeengine',
			__( 'EU VAT Settings', 'storeengine' ),
			__( 'EU VAT', 'storeengine' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::all();
		$countries = $this->all_countries();
		?>
		<div class="wrap storeengine-eu-vat-admin">
			<h1><?php esc_html_e( 'EU/UK VAT Settings', 'storeengine' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Collect and validate EU/UK VAT numbers at checkout. Valid B2B numbers in cross-border sales are exempted from VAT automatically.', 'storeengine' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>

				<h2><?php esc_html_e( 'Field Display', 'storeengine' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="eu_vat_field_label"><?php esc_html_e( 'Field label', 'storeengine' ); ?></label></th>
						<td><input type="text" class="regular-text" id="eu_vat_field_label" name="field_label" value="<?php echo esc_attr( $settings['field_label'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="eu_vat_field_placeholder"><?php esc_html_e( 'Placeholder', 'storeengine' ); ?></label></th>
						<td><input type="text" class="regular-text" id="eu_vat_field_placeholder" name="field_placeholder" value="<?php echo esc_attr( $settings['field_placeholder'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="eu_vat_field_description"><?php esc_html_e( 'Description', 'storeengine' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="2" id="eu_vat_field_description" name="field_description"><?php echo esc_textarea( $settings['field_description'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown below the VAT field on checkout. Basic HTML allowed.', 'storeengine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="eu_vat_field_required"><?php esc_html_e( 'Field requirement', 'storeengine' ); ?></label></th>
						<td>
							<select id="eu_vat_field_required" name="field_required">
								<option value="optional" <?php selected( $settings['field_required'], 'optional' ); ?>><?php esc_html_e( 'Optional', 'storeengine' ); ?></option>
								<option value="required" <?php selected( $settings['field_required'], 'required' ); ?>><?php esc_html_e( 'Required (always)', 'storeengine' ); ?></option>
								<option value="required_if_company" <?php selected( $settings['field_required'], 'required_if_company' ); ?>><?php esc_html_e( 'Required only if company name is filled', 'storeengine' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Exemption Rules', 'storeengine' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Preserve VAT in store base country', 'storeengine' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="preserve_in_base_country" value="yes" <?php checked( $settings['preserve_in_base_country'], 'yes' ); ?>>
								<?php esc_html_e( 'Charge VAT for buyers in the same country as your store, even with a valid VAT number.', 'storeengine' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="eu_vat_preserve_countries"><?php esc_html_e( 'Always preserve VAT in these countries', 'storeengine' ); ?></label></th>
						<td>
							<select id="eu_vat_preserve_countries" name="preserve_countries[]" multiple size="8" style="min-width:280px;">
								<?php foreach ( $countries as $code => $name ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( in_array( $code, (array) $settings['preserve_countries'], true ) ); ?>><?php echo esc_html( $name ); ?> (<?php echo esc_html( $code ); ?>)</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Hold Cmd/Ctrl to select multiple. VAT will still be charged for buyers in these countries even with a valid VAT number.', 'storeengine' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Progress Messages', 'storeengine' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$msg = (array) $settings['messages'];
					$rows = [
						'validating'        => __( 'While validating', 'storeengine' ),
						'valid'             => __( 'When valid', 'storeengine' ),
						'invalid'           => __( 'When invalid', 'storeengine' ),
						'validation_failed' => __( 'When validation failed', 'storeengine' ),
					];
					foreach ( $rows as $key => $label ) :
						$value = $msg[ $key ] ?? '';
					?>
					<tr>
						<th scope="row"><label for="eu_vat_msg_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input type="text" class="regular-text" id="eu_vat_msg_<?php echo esc_attr( $key ); ?>" name="messages[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>"></td>
					</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Advanced', 'storeengine' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Debug logging', 'storeengine' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="debug_logging" value="yes" <?php checked( $settings['debug_logging'], 'yes' ); ?>>
								<?php esc_html_e( 'Log VIES failures to PHP error log.', 'storeengine' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'storeengine' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to save these settings.', 'storeengine' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		Settings::update( [
			'field_label'              => isset( $_POST['field_label'] ) ? sanitize_text_field( wp_unslash( $_POST['field_label'] ) ) : '',
			'field_placeholder'        => isset( $_POST['field_placeholder'] ) ? sanitize_text_field( wp_unslash( $_POST['field_placeholder'] ) ) : '',
			'field_description'        => isset( $_POST['field_description'] ) ? wp_kses_post( wp_unslash( $_POST['field_description'] ) ) : '',
			'field_required'           => $this->sanitize_required( isset( $_POST['field_required'] ) ? sanitize_text_field( wp_unslash( $_POST['field_required'] ) ) : 'optional' ),
			'preserve_in_base_country' => ! empty( $_POST['preserve_in_base_country'] ) ? 'yes' : 'no',
			'preserve_countries'       => array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['preserve_countries'] ?? [] ) ) ),
			'messages'                 => array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['messages'] ?? [] ) ) ),
			'debug_logging'            => ! empty( $_POST['debug_logging'] ) ? 'yes' : 'no',
		] );

		wp_safe_redirect( add_query_arg(
			[ 'page' => self::MENU_SLUG, 'updated' => '1' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function maybe_show_saved_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice triggered by a post-save redirect flag; no form data is processed.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice triggered by a post-save redirect flag; no form data is processed.
		$updated = ! empty( $_GET['updated'] );
		if ( $page !== self::MENU_SLUG || ! $updated ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'EU VAT settings saved.', 'storeengine' ) . '</p></div>';
	}

	private function sanitize_required( string $value ): string {
		return in_array( $value, [ 'optional', 'required', 'required_if_company' ], true ) ? $value : 'optional';
	}

	private function all_countries(): array {
		if ( class_exists( Countries::class ) ) {
			$list = Countries::init()->get_countries();
			if ( is_array( $list ) ) {
				return $list;
			}
		}
		// Minimal EU+UK fallback if Countries isn't available.
		return [
			'AT' => 'Austria', 'BE' => 'Belgium', 'BG' => 'Bulgaria', 'CY' => 'Cyprus',
			'CZ' => 'Czechia', 'DE' => 'Germany', 'DK' => 'Denmark', 'EE' => 'Estonia',
			'ES' => 'Spain', 'FI' => 'Finland', 'FR' => 'France', 'GB' => 'United Kingdom',
			'GR' => 'Greece', 'HR' => 'Croatia', 'HU' => 'Hungary', 'IE' => 'Ireland',
			'IT' => 'Italy', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LV' => 'Latvia',
			'MT' => 'Malta', 'NL' => 'Netherlands', 'PL' => 'Poland', 'PT' => 'Portugal',
			'RO' => 'Romania', 'SE' => 'Sweden', 'SI' => 'Slovenia', 'SK' => 'Slovakia',
		];
	}
}
