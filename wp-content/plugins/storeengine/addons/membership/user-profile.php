<?php
/**
 * Manual membership assignment on the native WordPress user surfaces:
 *  - a "StoreEngine Memberships" checklist on the user profile / edit-user screen
 *  - a "Memberships" column on the Users list table
 *
 * @package StoreEngine\Addons\Membership
 */

namespace StoreEngine\Addons\Membership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserProfile {

	const NONCE = 'storeengine_membership_user_profile';

	public static function init() {
		$self = new self();

		add_action( 'show_user_profile', [ $self, 'render_fields' ] );
		add_action( 'edit_user_profile', [ $self, 'render_fields' ] );
		add_action( 'personal_options_update', [ $self, 'save_fields' ] );
		add_action( 'edit_user_profile_update', [ $self, 'save_fields' ] );

		add_filter( 'manage_users_columns', [ $self, 'add_column' ] );
		add_filter( 'manage_users_custom_column', [ $self, 'render_column' ], 10, 3 );
	}

	public function render_fields( $user ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$groups = Access::get_group_options();
		if ( empty( $groups ) ) {
			return;
		}

		$held = Access::get_user_groups( (int) $user->ID );
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<h2><?php esc_html_e( 'StoreEngine Memberships', 'storeengine' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label><?php esc_html_e( 'Access Groups', 'storeengine' ); ?></label></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php esc_html_e( 'Access Groups', 'storeengine' ); ?></span>
						</legend>
						<?php foreach ( $groups as $group ) : ?>
							<label style="display:block;margin-bottom:6px;">
								<input
									type="checkbox"
									name="storeengine_membership_groups[]"
									value="<?php echo esc_attr( $group['value'] ); ?>"
									<?php checked( in_array( (int) $group['value'], $held, true ) ); ?>
								/>
								<?php echo esc_html( $group['label'] ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description">
							<?php esc_html_e( 'Check a group to grant this user access, uncheck to revoke. Manual grants behave exactly like purchased ones.', 'storeengine' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_fields( $user_id ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}

		$submitted = isset( $_POST['storeengine_membership_groups'] )
			? array_map( 'absint', (array) wp_unslash( $_POST['storeengine_membership_groups'] ) )
			: [];

		$current = Access::get_user_groups( (int) $user_id );

		// Grant newly checked, revoke newly unchecked — only the diff is touched.
		foreach ( array_diff( $submitted, $current ) as $group_id ) {
			Access::grant( (int) $user_id, (int) $group_id, 'manual' );
		}
		foreach ( array_diff( $current, $submitted ) as $group_id ) {
			Access::revoke( (int) $user_id, (int) $group_id );
		}
	}

	public function add_column( $columns ) {
		$columns['storeengine_memberships'] = __( 'Memberships', 'storeengine' );

		return $columns;
	}

	public function render_column( $output, $column_name, $user_id ) {
		if ( 'storeengine_memberships' !== $column_name ) {
			return $output;
		}

		$held = Access::get_user_groups( (int) $user_id );
		if ( empty( $held ) ) {
			return '—';
		}

		$names = array_filter( array_map( 'get_the_title', $held ) );

		return esc_html( implode( ', ', $names ) );
	}
}
