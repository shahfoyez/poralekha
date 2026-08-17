<?php
/**
 * "Restrict This Block" editor panel (StoreEngine core).
 *
 * Enqueues the block-visibility inspector control on EVERY block, always — even
 * when the Membership addon is inactive — so the panel can double as a
 * promotion: when Membership is off it shows an "activate" prompt instead of the
 * controls. When Membership is on, it exposes the real access-group picker and
 * the enforcement runs server-side (see
 * StoreEngine\Addons\Membership\BlockRestriction::maybe_restrict_block()).
 *
 * The editor script lives with the membership addon on disk (always present,
 * only booted when active), so core can reference it regardless of activation.
 *
 * @package StoreEngine\Blocks
 */

namespace StoreEngine\Blocks;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlockVisibility {

	const HANDLE = 'storeengine-membership-block-restriction';

	public static function init() {
		$self = new self();
		add_action( 'enqueue_block_editor_assets', [ $self, 'enqueue' ] );
	}

	public function enqueue() {
		$active = Helper::get_addon_active_status( 'membership' );

		wp_enqueue_script(
			self::HANDLE,
			STOREENGINE_PLUGIN_ROOT_URI . 'addons/membership/assets/js/block-restriction.js',
			[ 'wp-blocks', 'wp-hooks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-compose', 'wp-i18n', 'wp-data' ],
			STOREENGINE_VERSION,
			true
		);

		wp_set_script_translations( self::HANDLE, 'storeengine' );

		$groups = [];
		if ( $active && class_exists( '\StoreEngine\Addons\Membership\Access' ) ) {
			$groups = \StoreEngine\Addons\Membership\Access::get_group_options();
		}

		wp_add_inline_script(
			self::HANDLE,
			'window.StoreEngineMembershipBlocks = ' . wp_json_encode( [
				'membershipActive' => (bool) $active,
				'groups'           => $groups,
				'activateUrl'      => admin_url( 'admin.php?page=' . STOREENGINE_PLUGIN_SLUG . '-addons' ),
			] ) . ';',
			'before'
		);
	}
}
