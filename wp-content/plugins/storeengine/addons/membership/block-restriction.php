<?php
/**
 * Block-level access control.
 *
 * Every block gains a "StoreEngine visibility" panel in the editor inspector
 * (see assets/js/block-restriction.js). The choice is stored as block
 * attributes and enforced on the frontend via the render_block filter, so a
 * restricted block is simply omitted (or replaced with a notice) for users who
 * don't qualify — the rest of the page renders normally.
 *
 * Attributes (added to every block type by the editor script):
 *   storeengineVisibility : everyone | logged_in | logged_out | members
 *   storeengineGroups     : int[]  (access-group ids, used when mode = members)
 *
 * @package StoreEngine\Addons\Membership
 */

namespace StoreEngine\Addons\Membership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlockRestriction {

	public static function init() {
		$self = new self();

		if ( ! is_admin() ) {
			add_filter( 'render_block', [ $self, 'maybe_restrict_block' ], 10, 2 );
		}

		// The editor panel is enqueued by core (StoreEngine\Blocks\BlockVisibility)
		// so it can also render an activation promo when this addon is inactive.
	}

	/**
	 * Hide a block whose visibility rule the current viewer fails.
	 *
	 * @param string $block_content Rendered HTML.
	 * @param array  $block         Parsed block (name + attrs).
	 *
	 * @return string
	 */
	public function maybe_restrict_block( $block_content, $block ) {
		$attrs      = $block['attrs'] ?? [];
		$visibility = $attrs['storeengineVisibility'] ?? 'everyone';

		if ( 'everyone' === $visibility || '' === $visibility ) {
			return $block_content;
		}

		$user_id = get_current_user_id();

		if ( $user_id && user_can( $user_id, 'administrator' ) ) {
			return $block_content;
		}

		if ( $this->viewer_qualifies( $visibility, $attrs, $user_id ) ) {
			return $block_content;
		}

		/**
		 * Optional replacement HTML for a hidden block (empty string by default).
		 *
		 * @param string $replacement
		 * @param array  $block
		 */
		return apply_filters( 'storeengine/membership/restricted_block_content', '', $block );
	}

	protected function viewer_qualifies( string $visibility, array $attrs, int $user_id ): bool {
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
				$groups = array_map( 'intval', (array) ( $attrs['storeengineGroups'] ?? [] ) );
				if ( empty( $groups ) ) {
					return ! empty( Access::get_user_groups( $user_id ) );
				}

				return Access::user_has_any_access( $user_id, $groups );
		}

		return true;
	}
}
