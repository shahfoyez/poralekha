<?php
/**
 * Per-menu-item access control.
 *
 * Adds a visibility control to every nav-menu item in Appearance → Menus and
 * hides restricted items on the frontend for users who don't qualify. Modes:
 *   everyone     — no restriction (default)
 *   logged_in    — only logged-in users
 *   logged_out   — only logged-out users
 *   members      — only users with access to one of the selected access groups
 *
 * @package StoreEngine\Addons\Membership
 */

namespace StoreEngine\Addons\Membership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NavMenuRestriction {

	const VISIBILITY_META = '_storeengine_menu_visibility';
	const GROUPS_META     = '_storeengine_menu_groups';
	const NONCE           = 'storeengine_menu_item_restriction';

	public static function init() {
		$self = new self();

		if ( is_admin() ) {
			add_action( 'wp_nav_menu_item_custom_fields', [ $self, 'render_fields' ], 10, 2 );
			add_action( 'wp_update_nav_menu_item', [ $self, 'save_fields' ], 10, 2 );
		} else {
			add_filter( 'wp_get_nav_menu_items', [ $self, 'filter_items' ], 20, 2 );
		}
	}

	/**
	 * Render the visibility control inside a menu item (Appearance → Menus).
	 *
	 * @param int      $item_id Menu item (nav_menu_item post) id.
	 * @param \WP_Post $item    Menu item object.
	 */
	public function render_fields( $item_id, $item ) {
		$visibility = get_post_meta( $item_id, self::VISIBILITY_META, true );
		$visibility = $visibility ? $visibility : 'everyone';
		$selected   = (array) get_post_meta( $item_id, self::GROUPS_META, true );
		$selected   = array_map( 'intval', $selected );
		$groups     = Access::get_group_options();

		wp_nonce_field( self::NONCE, self::NONCE . '_' . $item_id );
		?>
		<p class="field-storeengine-visibility description description-wide">
			<label for="storeengine-menu-visibility-<?php echo esc_attr( $item_id ); ?>">
				<?php esc_html_e( 'StoreEngine visibility', 'storeengine' ); ?><br />
				<select
					id="storeengine-menu-visibility-<?php echo esc_attr( $item_id ); ?>"
					class="widefat storeengine-menu-visibility"
					name="storeengine_menu_visibility[<?php echo esc_attr( $item_id ); ?>]"
					data-item="<?php echo esc_attr( $item_id ); ?>"
				>
					<option value="everyone" <?php selected( $visibility, 'everyone' ); ?>><?php esc_html_e( 'Everyone', 'storeengine' ); ?></option>
					<option value="logged_in" <?php selected( $visibility, 'logged_in' ); ?>><?php esc_html_e( 'Logged-in users', 'storeengine' ); ?></option>
					<option value="logged_out" <?php selected( $visibility, 'logged_out' ); ?>><?php esc_html_e( 'Logged-out users', 'storeengine' ); ?></option>
					<option value="members" <?php selected( $visibility, 'members' ); ?>><?php esc_html_e( 'Members of selected groups', 'storeengine' ); ?></option>
				</select>
			</label>
		</p>
		<p class="field-storeengine-groups description description-wide" style="<?php echo 'members' === $visibility ? '' : 'display:none;'; ?>">
			<label><?php esc_html_e( 'Access groups', 'storeengine' ); ?><br />
				<?php if ( empty( $groups ) ) : ?>
					<span class="description"><?php esc_html_e( 'No access groups yet.', 'storeengine' ); ?></span>
				<?php else : ?>
					<span class="storeengine-menu-groups">
						<?php foreach ( $groups as $group ) : ?>
							<label style="display:block;">
								<input
									type="checkbox"
									name="storeengine_menu_groups[<?php echo esc_attr( $item_id ); ?>][]"
									value="<?php echo esc_attr( $group['value'] ); ?>"
									<?php checked( in_array( (int) $group['value'], $selected, true ) ); ?>
								/>
								<?php echo esc_html( $group['label'] ); ?>
							</label>
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
			</label>
		</p>
		<script>
			( function () {
				var sel = document.getElementById( 'storeengine-menu-visibility-<?php echo (int) $item_id; ?>' );
				if ( ! sel ) { return; }
				sel.addEventListener( 'change', function () {
					var wrap = sel.closest( '.menu-item-settings' ) || document;
					var groups = wrap.querySelector( '.field-storeengine-groups' );
					if ( groups ) { groups.style.display = ( 'members' === sel.value ) ? '' : 'none'; }
				} );
			} )();
		</script>
		<?php
	}

	/**
	 * Persist the visibility control on menu save.
	 *
	 * @param int $menu_id            Menu term id (unused).
	 * @param int $menu_item_db_id    Menu item post id.
	 */
	public function save_fields( $menu_id, $menu_item_db_id ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$nonce_key = self::NONCE . '_' . $menu_item_db_id;
		if ( ! isset( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), self::NONCE ) ) {
			return;
		}

		$visibility = isset( $_POST['storeengine_menu_visibility'][ $menu_item_db_id ] )
			? sanitize_key( wp_unslash( $_POST['storeengine_menu_visibility'][ $menu_item_db_id ] ) )
			: 'everyone';

		if ( ! in_array( $visibility, [ 'everyone', 'logged_in', 'logged_out', 'members' ], true ) ) {
			$visibility = 'everyone';
		}

		$groups = isset( $_POST['storeengine_menu_groups'][ $menu_item_db_id ] )
			? array_map( 'absint', (array) wp_unslash( $_POST['storeengine_menu_groups'][ $menu_item_db_id ] ) )
			: [];

		if ( 'everyone' === $visibility ) {
			delete_post_meta( $menu_item_db_id, self::VISIBILITY_META );
			delete_post_meta( $menu_item_db_id, self::GROUPS_META );

			return;
		}

		update_post_meta( $menu_item_db_id, self::VISIBILITY_META, $visibility );
		update_post_meta( $menu_item_db_id, self::GROUPS_META, $groups );
	}

	/**
	 * Drop restricted items (and their orphaned descendants) on the frontend.
	 *
	 * @param array $items Menu item objects.
	 *
	 * @return array
	 */
	public function filter_items( $items ) {
		if ( empty( $items ) || is_admin() ) {
			return $items;
		}

		$user_id = get_current_user_id();
		$removed = [];

		foreach ( $items as $key => $item ) {
			// Descendant of an already-removed item — drop the whole branch.
			if ( $item->menu_item_parent && isset( $removed[ $item->menu_item_parent ] ) ) {
				$removed[ $item->ID ] = true;
				unset( $items[ $key ] );
				continue;
			}

			if ( ! $this->can_view( (int) $item->ID, $user_id ) ) {
				$removed[ $item->ID ] = true;
				unset( $items[ $key ] );
			}
		}

		return array_values( $items );
	}

	protected function can_view( int $item_id, int $user_id ): bool {
		$visibility = get_post_meta( $item_id, self::VISIBILITY_META, true );
		if ( ! $visibility || 'everyone' === $visibility ) {
			return true;
		}

		if ( $user_id && user_can( $user_id, 'administrator' ) ) {
			return true;
		}

		$logged_in = (bool) $user_id;

		switch ( $visibility ) {
			case 'logged_in':
				return $logged_in;
			case 'logged_out':
				return ! $logged_in;
			case 'members':
				if ( ! $logged_in ) {
					return false;
				}
				$groups = array_map( 'intval', (array) get_post_meta( $item_id, self::GROUPS_META, true ) );
				if ( empty( $groups ) ) {
					// "members" with no group selected == any logged-in member of any group.
					return ! empty( Access::get_user_groups( $user_id ) );
				}

				return Access::user_has_any_access( $user_id, $groups );
		}

		return true;
	}
}
