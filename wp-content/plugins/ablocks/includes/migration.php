<?php
namespace ABlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Admin\Settings;

class Migration {

	public static function init() {
		$self = new self();
		$self->run_migration();
		add_filter( 'render_block_data', array( $self, 'update_attributes_backward_compatibility' ) );
	}

	public function run_migration() {
		$this->migrate_font_stack();

		$ablocks_version = get_option( 'ablocks_version' );
		// Save Version Number, flash role management and save permalink
		if ( ABLOCKS_VERSION !== $ablocks_version ) {
			Settings::save_settings();
			$this->migrate_1_6_3( $ablocks_version );
			$this->migrate_1_8_0( $ablocks_version );
			update_option( 'ablocks_version', ABLOCKS_VERSION );
		}
	}

	/**
	 * font-family declarations are now emitted as full stacks (quoted family +
	 * metric fallback + generic) instead of a bare family name.
	 *
	 * Block CSS is cached in generated files and the global CSS variables live in
	 * a transient, so both hold the old single-name output until something forces
	 * a rebuild. Clear them once so existing pages pick up the new stacks without
	 * anyone having to re-save every page.
	 */
	public function migrate_font_stack() {
		if ( get_option( 'ablocks_font_stack_migrated' ) ) {
			return;
		}

		// Deleting generated files needs WP_Filesystem; keep that out of visitor
		// requests. The first admin page load after the update does the work.
		if ( ! is_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		( new \ABlocks\Classes\FileUpload() )->delete_files();
		delete_transient( 'ablocks_global_css_vars' );
		// Force a rebuild of the template/part/theme-builder font set, which did
		// not exist before this release.
		\ABlocks\Classes\FontCollector::flush_site_fonts();

		update_option( 'ablocks_font_stack_migrated', '1' );
	}

	public function migrate_1_6_3( $version ) {
		if ( version_compare( $version, '1.6.3', '<=' ) ) {
			add_option( 'ablocks_has_required_block_attribute_migration', '1.6.3' );
			// Delete Old Table
			global $wpdb;
			$prefix          = $wpdb->prefix;
			Database\CreateFormTable::down( $prefix );
			// Crate New database table
			Database::create_initial_custom_table();
		}
	}
	public function migrate_1_8_0( $version ) {
		if ( version_compare( $version, '1.8.0', '<=' ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';
			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange 
			$wpdb->query( "ALTER TABLE `{$table_name}` MODIFY `post_id` VARCHAR(100) NULL" );
		}
	}

	public function update_attributes_backward_compatibility( $block ) {
		if ( get_option( 'ablocks_has_required_block_attribute_migration' ) === '1.6.3' ) {

			$attributes = ! empty( $block['attrs'] ) ? $block['attrs'] : [];
			switch ( $block['blockName'] ) {
				case 'ablocks/container':
					$has_old_direction_value = ! empty( $block['attrs']['direction'] );
					if ( empty( $block['attrs']['dir']['value'] ) || $has_old_direction_value ) {
						$block['attrs']['dir']['value'] = $has_old_direction_value ? $attributes['direction'] : 'column';
						$block['attrs']['direction'] = '';
					}

					if ( ! empty( $attributes['directionTablet'] ) ) {
						$block['attrs']['dir']['valueTablet'] = $attributes['directionTablet'];
						$block['attrs']['directionTablet'] = '';
					}

					if ( ! empty( $attributes['directionMobile'] ) ) {
						$block['attrs']['dir']['valueMobile'] = $attributes['directionMobile'];
						$block['attrs']['directionMobile'] = '';
					}

					if ( ! empty( $attributes['justify'] ) ) {
						$block['attrs']['justification']['value'] = $attributes['justify'];
						$block['attrs']['justify'] = '';
					}

					if ( ! empty( $attributes['justifyTablet'] ) ) {
						$block['attrs']['justification']['valueTablet'] = $attributes['justifyTablet'];
						$block['attrs']['justifyTablet'] = '';
					}

					if ( ! empty( $attributes['justifyMobile'] ) ) {
						$block['attrs']['justification']['valueMobile'] = $attributes['justifyMobile'];
						$block['attrs']['justifyMobile'] = '';
					}

					if ( ! empty( $attributes['align'] ) ) {
						$block['attrs']['alignment']['value'] = $attributes['align'];
						$block['attrs']['align'] = '';
					}

					if ( ! empty( $attributes['alignTablet'] ) ) {
						$block['attrs']['alignment']['valueTablet'] = $attributes['alignTablet'];
						$block['attrs']['alignTablet'] = '';
					}

					if ( ! empty( $attributes['alignMobile'] ) ) {
						$block['attrs']['alignment']['valueMobile'] = $attributes['alignMobile'];
						$block['attrs']['alignMobile'] = '';
					}

					if ( ! empty( $attributes['wrap'] ) ) {
						$block['attrs']['wrapping']['value'] = $attributes['wrap'];
						$block['attrs']['wrap'] = '';
					}

					if ( ! empty( $attributes['wrapTablet'] ) ) {
						$block['attrs']['wrapping']['valueTablet'] = $attributes['wrapTablet'];
						$block['attrs']['wrapTablet'] = '';
					}

					if ( ! empty( $attributes['wrapMobile'] ) ) {
						$block['attrs']['wrapping']['valueMobile'] = $attributes['wrapMobile'];
						$block['attrs']['wrapMobile'] = '';
					}
					break;
				case 'ablocks/counter':
				case 'ablocks/coupon':
				case 'ablocks/icon':
				case 'ablocks/notice':
				case 'ablocks/paypal-button':
				case 'ablocks/price-menu-item':
				case 'ablocks/star-ratings':
				case 'ablocks/stripe-button':
				case 'ablocks/svg-draw':
				case 'ablocks/tabs':
					if ( ! empty( $attributes['iconSize'] ) ) {
						$block['attrs']['iconSizing']['value'] = $attributes['iconSize'];
					}
					if ( ! empty( $attributes['iconRotate'] ) ) {
						$block['attrs']['iconRotated']['value'] = $attributes['iconRotate'];
					}
					// button-group control migration start
					$has_old_tabsMenuPosition_value = ! empty( $block['attrs']['tabsMenuPosition'] );
					if ( empty( $block['attrs']['tabsMenuPositioning']['value'] ) || $has_old_tabsMenuPosition_value ) {
						$block['attrs']['tabsMenuPositioning']['value'] = $has_old_tabsMenuPosition_value ? $attributes['tabsMenuPosition'] : 'left';
						$block['attrs']['tabsMenuPosition'] = '';
					}
					if ( ! empty( $attributes['tabsMenuPositionTablet'] ) ) {
						$block['attrs']['tabsMenuPositioning']['valueTablet'] = $attributes['tabsMenuPositionTablet'];
						$block['attrs']['tabsMenuPositionTablet'] = '';
					}
					if ( ! empty( $attributes['tabsMenuPositionMobile'] ) ) {
						$block['attrs']['tabsMenuPositioning']['valueMobile'] = $attributes['tabsMenuPositionMobile'];
						$block['attrs']['tabsMenuPositionMobile'] = '';
					}
					if ( ! empty( $attributes['tabMenuAlign'] ) ) {
						$block['attrs']['tabMenuAlignment']['value'] = $attributes['tabMenuAlign'];
						$block['attrs']['tabMenuAlign'] = '';
					}
					if ( ! empty( $attributes['tabMenuAlignTablet'] ) ) {
						$block['attrs']['tabMenuAlignment']['valueTablet'] = $attributes['tabMenuAlignTablet'];
						$block['attrs']['tabMenuAlignTablet'] = '';
					}
					if ( ! empty( $attributes['tabMenuAlignMobile'] ) ) {
						$block['attrs']['tabMenuAlignment']['valueMobile'] = $attributes['tabMenuAlignMobile'];
						$block['attrs']['tabMenuAlignMobile'] = '';
					}
										$has_old_menuContentAlign_value = ! empty( $block['attrs']['menuContentAlign'] );
					if ( empty( $block['attrs']['menuContentAlignment']['value'] ) || $has_old_menuContentAlign_value ) {
						$block['attrs']['menuContentAlignment']['value'] = $has_old_menuContentAlign_value ? $attributes['menuContentAlign'] : 'center';
						$block['attrs']['menuContentAlign'] = '';
					}
					if ( ! empty( $attributes['menuContentAlignTablet'] ) ) {
						$block['attrs']['menuContentAlignment']['valueTablet'] = $attributes['menuContentAlignTablet'];
						$block['attrs']['menuContentAlignTablet'] = '';
					}
					if ( ! empty( $attributes['menuContentAlignMobile'] ) ) {
						$block['attrs']['menuContentAlignment']['valueMobile'] = $attributes['menuContentAlignMobile'];
						$block['attrs']['menuContentAlignMobile'] = '';
					}

					// button-group control migration end

					break;

				case 'ablocks/button':
					if ( ! empty( $attributes['iconSize'] ) ) {
						$iconSize = intval( $attributes['iconSize'] );
						if ( $iconSize !== 16 ) {
							$block['attrs']['iconSizing']['value'] = $iconSize;
						}
					}
					if ( ! empty( $attributes['iconRotate'] ) ) {
						$block['attrs']['iconRotated']['value'] = $attributes['iconRotate'];
					}
					break;

				case 'ablocks/form-input':
					if ( ! empty( $attributes['iconSize'] ) ) {
						$block['attrs']['iconSizing']['value'] = $attributes['iconSize'];
					}
					if ( ! empty( $attributes['iconRotate'] ) ) {
						$block['attrs']['iconRotated']['value'] = $attributes['iconRotate'];
					}
					break;

				case 'ablocks/form-password':
					if ( ! empty( $attributes['iconSize'] ) ) {
						$block['attrs']['iconSizing']['value'] = $attributes['iconSize'];
					}
					if ( ! empty( $attributes['iconRotate'] ) ) {
						$block['attrs']['iconRotated']['value'] = $attributes['iconRotate'];
					}

					if ( ! empty( $attributes['passwordShowSize'] ) ) {
						$block['attrs']['passwordShowSizing']['value'] = $attributes['passwordShowSize'];
					}
					if ( ! empty( $attributes['passwordShowRotate'] ) ) {
						$block['attrs']['passwordShowRotated']['value'] = $attributes['passwordShowRotate'];
					}
					if ( ! empty( $attributes['passwordHideSize'] ) ) {
						$block['attrs']['passwordHideSizing']['value'] = $attributes['passwordHideSize'];
					}
					if ( ! empty( $attributes['passwordHideRotate'] ) ) {
						$block['attrs']['passwordHideRotated']['value'] = $attributes['passwordHideRotate'];
					}

					break;

				case 'ablocks/carousel':
					if ( ! empty( $attributes['leftIconSize'] ) ) {
						$block['attrs']['leftIconSizing']['value'] = $attributes['leftIconSize'];
					}
					if ( ! empty( $attributes['leftIconRotate'] ) ) {
						$block['attrs']['leftIconRotated']['value'] = $attributes['leftIconRotate'];
					}
					if ( ! empty( $attributes['rightIconSize'] ) ) {
						$block['attrs']['rightIconSizing']['value'] = $attributes['rightIconSize'];
					}
					if ( ! empty( $attributes['rightIconRotate'] ) ) {
						$block['attrs']['rightIconRotated']['value'] = $attributes['rightIconRotate'];
					}
					break;
				case 'ablocks/content-timeline':
					break;
				case 'ablocks/content-timeline-child':
					if ( ! empty( $attributes['iconSize'] ) ) {
						$block['attrs']['iconSizing']['value'] = $attributes['iconSize'];
					}
					if ( ! empty( $attributes['iconRotate'] ) ) {
						$block['attrs']['iconRotated']['value'] = $attributes['iconRotate'];
					}

					if ( ! empty( $attributes['contentTimeLineIconSize'] ) ) {
						$block['attrs']['contentTimeLineIconSizing']['value'] = $attributes['contentTimeLineIconSize'];
					}
					if ( ! empty( $attributes['contentTimeLineIconRotate'] ) ) {
						$block['attrs']['contentTimeLineIconRotated']['value'] = $attributes['contentTimeLineIconRotate'];
					}
					break;

				case 'ablocks/divider':
					if ( ! empty( $attributes['iconSize'] ) ) {
						$block['attrs']['iconSizing']['value'] = $attributes['iconSize'];
					}
					if ( ! empty( $attributes['iconRotate'] ) ) {
						$block['attrs']['iconRotated']['value'] = $attributes['iconRotate'];
					}
					break;

				case 'ablocks/single-accordion':
					if ( ! empty( $attributes['leftActiveIconSize'] ) ) {
						$block['attrs']['leftActiveIconSizing']['value'] = $attributes['leftActiveIconSize'];
					}
					if ( ! empty( $attributes['leftActiveIconRotate'] ) ) {
						$block['attrs']['leftActiveIconRotated']['value'] = $attributes['leftActiveIconRotate'];
					}
					if ( ! empty( $attributes['leftCloseIconSize'] ) ) {
						$block['attrs']['leftCloseIconSizing']['value'] = $attributes['leftCloseIconSize'];
					}
					if ( ! empty( $attributes['leftCloseIconRotate'] ) ) {
						$block['attrs']['leftCloseIconRotated']['value'] = $attributes['leftCloseIconRotate'];
					}
					if ( ! empty( $attributes['rightActiveIconSize'] ) ) {
						$block['attrs']['rightActiveIconSizing']['value'] = $attributes['rightActiveIconSize'];
					}
					if ( ! empty( $attributes['rightActiveIconRotate'] ) ) {
						$block['attrs']['rightActiveIconRotated']['value'] = $attributes['rightActiveIconRotate'];
					}
					if ( ! empty( $attributes['rightCloseIconSize'] ) ) {
						$block['attrs']['rightCloseIconSizing']['value'] = $attributes['rightCloseIconSize'];
					}
					if ( ! empty( $attributes['rightCloseIconRotate'] ) ) {
						$block['attrs']['rightCloseIconRotated']['value'] = $attributes['rightCloseIconRotate'];
					}
					break;

				case 'ablocks/table-of-content':
					if ( ! empty( $attributes['iconSize'] ) ) {
						$block['attrs']['iconSizing']['value'] = $attributes['iconSize'];
					}
					if ( ! empty( $attributes['iconRotate'] ) ) {
						$block['attrs']['iconRotated']['value'] = $attributes['iconRotate'];
					}

					if ( ! empty( $attributes['closeIconSize'] ) ) {
						$block['attrs']['closeIconSizing']['value'] = $attributes['closeIconSize'];
					}
					if ( ! empty( $attributes['closeIconRotate'] ) ) {
						$block['attrs']['closeIconRotated']['value'] = $attributes['closeIconRotate'];
					}
					if ( ! empty( $attributes['openIconSize'] ) ) {
						$block['attrs']['openIconSizing']['value'] = $attributes['openIconSize'];
					}
					if ( ! empty( $attributes['openIconRotate'] ) ) {
						$block['attrs']['openIconRotated']['value'] = $attributes['openIconRotate'];
					}
					break;

				case 'ablocks/info-box':
					if ( ! empty( $attributes['iconSize'] ) ) {
						$block['attrs']['iconSizing']['value'] = $attributes['iconSize'];
					}
					if ( ! empty( $attributes['iconRotate'] ) ) {
						$block['attrs']['iconRotated']['value'] = $attributes['iconRotate'];
					}
					if ( ! empty( $attributes['btnIconSize'] ) ) {
						$block['attrs']['btnIconSizing']['value'] = $attributes['btnIconSize'];
					}
					if ( ! empty( $attributes['btnIconRotate'] ) ) {
						$block['attrs']['btnIconRotated']['value'] = $attributes['btnIconRotate'];
					}
					if ( ! empty( $attributes['starIconSize'] ) ) {
						$block['attrs']['starIconSizing']['value'] = $attributes['starIconSize'];
					}
					if ( ! empty( $attributes['starIconRotate'] ) ) {
						$block['attrs']['starIconRotated']['value'] = $attributes['starIconRotate'];
					}
					break;

				case 'ablocks/lists':
					break;

				case 'ablocks/modal':
					break;
				case 'ablocks/progress-tracker':
					break;
				case 'ablocks/social-shares':
					break;
				case 'ablocks/toggle':
					break;
				case 'ablocks/flip-box':
					break;

				case 'ablocks/countdown':
					// button-group control migration start
					$has_old_direction_value = ! empty( $block['attrs']['direction'] );
					if ( empty( $attributes['orient'] ) || $has_old_direction_value ) {
						$block['attrs']['orient']['value'] = $has_old_direction_value ? $attributes['direction'] : 'row';
						$block['attrs']['direction'] = '';
					}
					if ( ! empty( $attributes['directionTablet'] ) ) {
						$block['attrs']['orient']['valueTablet'] = $attributes['directionTablet'];
						$block['attrs']['directionTablet'] = '';
					}
					if ( ! empty( $attributes['directionMobile'] ) ) {
						$block['attrs']['orient']['valueMobile'] = $attributes['directionMobile'];
						$block['attrs']['directionMobile'] = '';
					}
					$has_old_justifyAlign_value = ! empty( $block['attrs']['justifyAlign'] );
					if ( empty( $attributes['justificationAlign'] ) || $has_old_justifyAlign_value ) {
						$block['attrs']['justificationAlign']['value'] = $has_old_justifyAlign_value ? $attributes['justifyAlign'] : 'center';
						$block['attrs']['justifyAlign'] = '';
					}
					if ( ! empty( $attributes['justifyAlignTablet'] ) ) {
						$block['attrs']['justificationAlign']['valueTablet'] = $attributes['justifyAlignTablet'];
						$block['attrs']['justifyAlignTablet'] = '';
					}
					if ( ! empty( $attributes['justifyAlignMobile'] ) ) {
						$block['attrs']['justificationAlign']['valueMobile'] = $attributes['justifyAlignMobile'];
						$block['attrs']['justifyAlignMobile'] = '';
					}
					$has_old_align_value = ! empty( $block['attrs']['align'] );
					if ( empty( $attributes['alignment'] ) || $has_old_align_value ) {
						$block['attrs']['alignment']['value'] = $has_old_align_value ? $attributes['align'] : 'stretch';
						$block['attrs']['align'] = '';
					}
					if ( ! empty( $attributes['alignTablet'] ) ) {
						$block['attrs']['alignment']['valueTablet'] = $attributes['alignTablet'];
						$block['attrs']['alignTablet'] = '';
					}
					if ( ! empty( $attributes['alignMobile'] ) ) {
						$block['attrs']['alignment']['valueMobile'] = $attributes['alignMobile'];
						$block['attrs']['alignMobile'] = '';
					}
					$has_old_wrap_value = ! empty( $block['attrs']['wrap'] );
					if ( empty( $attributes['wrapping'] ) || $has_old_wrap_value ) {
						$block['attrs']['wrapping']['value'] = $has_old_wrap_value ? $attributes['wrap'] : 'wrap';
						$block['attrs']['wrap'] = '';
					}
					if ( ! empty( $attributes['wrapTablet'] ) ) {
						$block['attrs']['wrapping']['valueTablet'] = $attributes['wrapTablet'];
						$block['attrs']['wrapTablet'] = '';
					}
					if ( ! empty( $attributes['wrapMobile'] ) ) {
						$block['attrs']['wrapping']['valueMobile'] = $attributes['wrapMobile'];
						$block['attrs']['wrapMobile'] = '';
					}
					// button-group control migration end
					break;
			}//end switch
		}//end if
		return $block;
	}


}
