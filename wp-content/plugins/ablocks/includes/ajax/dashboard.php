<?php

namespace ABlocks\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\AbstractAjaxHandler;
use ABlocks\Classes\FileUpload;
use ABlocks\Classes\FontLoadLocally;
use ABlocks\Classes\FontCollector;
use ABlocks\Classes\FontStack;
use ABlocks\Classes\DesignAudit;
use ABlocks\Helper;

class Dashboard extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = array(
			'get_admin_menu_items'      => array(
				'callback' => array( $this, 'get_admin_menu_items' ),
				'capability'    => 'manage_options',
			),
			'regenerate_assets'      => array(
				'callback' => array( $this, 'regenerate_assets' ),
				'capability'    => 'manage_options',
			),
			'page_cache_status'      => array(
				'callback' => array( $this, 'page_cache_status' ),
				'capability'    => 'manage_options',
			),
			'purge_page_cache'      => array(
				'callback' => array( $this, 'purge_page_cache' ),
				'capability'    => 'manage_options',
			),
			'run_scanner'      => array(
				'callback' => array( $this, 'run_scanner' ),
				'capability'    => 'manage_options',
			),
			'scanner_dismiss'      => array(
				'callback' => array( $this, 'scanner_dismiss' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'id'      => 'string',
					'dismiss' => 'boolean',
				),
			),
			'scanner_apply_fix'      => array(
				'callback' => array( $this, 'scanner_apply_fix' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'field' => 'string',
				),
			),
			'image_stats'      => array(
				'callback' => array( $this, 'image_stats' ),
				'capability'    => 'manage_options',
			),
			'image_optimize_batch'      => array(
				'callback' => array( $this, 'image_optimize_batch' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'level' => 'string',
					'webp'  => 'boolean',
					'all'   => 'boolean',
				),
			),
			'image_discard_originals'      => array(
				'callback' => array( $this, 'image_discard_originals' ),
				'capability'    => 'manage_options',
			),
			'image_restore_all'      => array(
				'callback' => array( $this, 'image_restore_all' ),
				'capability'    => 'manage_options',
			),
			'image_scan_unused'      => array(
				'callback' => array( $this, 'image_scan_unused' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'offset' => 'integer',
				),
			),
			'image_scan_duplicates'      => array(
				'callback' => array( $this, 'image_scan_duplicates' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'offset' => 'integer',
				),
			),
			'image_quarantine'      => array(
				'callback' => array( $this, 'image_quarantine' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'ids' => 'json',
				),
			),
			'image_quarantine_action'      => array(
				'callback' => array( $this, 'image_quarantine_action' ),
				'capability'    => 'manage_options',
				'fields' => array(
					'do' => 'string',
					'id' => 'integer',
				),
			),
			'clear_demo_transients'      => array(
				'callback' => array( $this, 'clear_demo_transients' ),
				'capability'    => 'manage_options',
			),
			'install_academy_lms'      => array(
				'callback' => array( $this, 'install_academy_lms' ),
				'capability'    => 'manage_options',
			),
			'install_storeengine'      => array(
				'callback' => array( $this, 'install_storeengine' ),
				'capability'    => 'manage_options',
			),
			'install_ecm'      => array(
				'callback' => array( $this, 'install_ecm' ),
			),
			'download_google_fonts'      => array(
				'callback' => array( $this, 'download_google_fonts' ),
				'capability'    => 'manage_options',
			),
			'font_audit'      => array(
				'callback' => array( $this, 'font_audit' ),
				'capability'    => 'manage_options',
			),
			'design_audit'      => array(
				'callback' => array( $this, 'design_audit' ),
				'capability'    => 'manage_options',
			),
		);
	}

	/**
	 * Which content still carries hand-picked typography or colours instead of a
	 * global preset.
	 *
	 * The editor lock stops new ones being made; it deliberately leaves existing
	 * content alone so turning the setting on can't restyle a live site. This is
	 * how you find what is already there and fix it on purpose.
	 */
	public function design_audit() {
		$post_ids = get_posts(
			[
				'post_type'              => array_merge(
					[ 'post', 'page' ],
					FontCollector::get_site_font_post_types()
				),
				'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
				'posts_per_page'         => 200,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		$pages      = [];
		$families   = 0;
		$typography = 0;
		$colors     = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post || false === strpos( (string) $post->post_content, 'wp:ablocks' ) ) {
				continue;
			}

			$found = DesignAudit::scan_blocks( parse_blocks( $post->post_content ) );
			if ( empty( $found['families'] ) && empty( $found['typography'] ) && empty( $found['colors'] ) ) {
				continue;
			}

			$families   += count( $found['families'] );
			$typography += count( $found['typography'] );
			$colors     += count( $found['colors'] );

			$pages[] = [
				'id'         => $post_id,
				'title'      => get_the_title( $post_id ),
				'type'       => get_post_type( $post_id ),
				'edit_link'  => get_edit_post_link( $post_id, 'raw' ),
				'families'   => array_values( array_unique( $found['families'] ) ),
				'typography' => array_values( array_unique( $found['typography'] ) ),
				'colors'     => array_values( array_unique( $found['colors'] ) ),
				// Sorted by the things the lock actually governs.
				'total'      => count( $found['families'] ) + count( $found['colors'] ),
			];
		}//end foreach

		// Worst offenders first — that is where cleanup pays.
		usort(
			$pages,
			function ( $a, $b ) {
				return $b['total'] <=> $a['total'];
			}
		);

		wp_send_json_success(
			[
				'pages'             => array_slice( $pages, 0, 50 ),
				'total_pages'       => count( $pages ),
				'total_families'    => $families,
				'total_typography'  => $typography,
				'total_colors'      => $colors,
				'scanned'           => count( $post_ids ),
				'typography_locked' => (bool) Helper::get_settings( 'lock_global_typography', false ),
				'strict_locked'     => (bool) Helper::get_settings( 'lock_global_typography_strict', false ),
				'colors_locked'     => (bool) Helper::get_settings( 'lock_global_colors', false ),
			]
		);
	}

	/**
	 * Which fonts this site loads, where they come from, and where they are used.
	 *
	 * Answers the questions that make font problems hard to debug: is this family
	 * self-hosted or still coming from Google, does it have a zero-shift fallback,
	 * and which page is pulling in the eleventh weight.
	 */
	public function font_audit() {
		$loader = new FontLoadLocally();

		$global_fonts = FontCollector::get_global_fonts();
		$site_fonts   = FontCollector::get_site_fonts();

		$post_ids = get_posts(
			[
				'post_type'              => 'any',
				'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => FontCollector::POST_META_KEY,
						'compare' => 'EXISTS',
					],
				],
			]
		);

		$all_fonts = FontCollector::merge( $global_fonts, $site_fonts );
		$pages     = [];

		foreach ( $post_ids as $post_id ) {
			$fonts = get_post_meta( $post_id, FontCollector::POST_META_KEY, true );
			if ( empty( $fonts ) || ! is_array( $fonts ) ) {
				continue;
			}

			$all_fonts = FontCollector::merge( $all_fonts, $fonts );

			$weight_count = 0;
			foreach ( $fonts as $weights ) {
				$weight_count += count( (array) $weights );
			}

			$pages[] = [
				'id'        => $post_id,
				'title'     => get_the_title( $post_id ),
				'type'      => get_post_type( $post_id ),
				'edit_link' => get_edit_post_link( $post_id, 'raw' ),
				'families'  => array_keys( $fonts ),
				'weights'   => $weight_count,
			];
		}//end foreach

		// Heaviest pages first — that is where a fix is worth making.
		usort(
			$pages,
			function ( $a, $b ) {
				return $b['weights'] <=> $a['weights'];
			}
		);

		$families = [];
		foreach ( $all_fonts as $family => $weights ) {
			$weights = array_values( array_unique( array_map( 'strval', (array) $weights ) ) );
			sort( $weights );

			$missing = $loader->get_missing( [ $family => $weights ] );

			$families[] = [
				'family'      => $family,
				'weights'     => $weights,
				'category'    => FontStack::get_category( $family ),
				'stack'       => FontStack::build( $family ),
				'self_hosted' => empty( $missing ),
				'has_metrics' => FontStack::has_metrics( $family ),
				'in_global'   => isset( $global_fonts[ $family ] ),
				'in_site'     => isset( $site_fonts[ $family ] ),
			];
		}

		usort(
			$families,
			function ( $a, $b ) {
				return strcasecmp( $a['family'], $b['family'] );
			}
		);

		$total_weights = 0;
		foreach ( $all_fonts as $weights ) {
			$total_weights += count( (array) $weights );
		}

		wp_send_json_success(
			[
				'families'         => $families,
				'pages'            => array_slice( $pages, 0, 25 ),
				'total_families'   => count( $families ),
				'total_weights'    => $total_weights,
				'remote_families'  => count(
					array_filter(
						$families,
						function ( $item ) {
							return ! $item['self_hosted'];
						}
					)
				),
				'metric_fallbacks' => FontStack::metric_fallback_enabled(),
			]
		);
	}

	public function get_admin_menu_items() {
		$menu_items = wp_json_encode( Helper::get_admin_menu_list() );
		wp_send_json_success( $menu_items );
	}
	public function regenerate_assets() {
		$FileUpload = new FileUpload();
		$has_delete = $FileUpload->delete_files();
		Helper::clear_third_party_plugin_cache();
		update_option( ABLOCKS_FONTS_SETTINGS_NAME, '{}' );
		wp_send_json_success( $has_delete );
	}

	/**
	 * Cache occupancy and environment, for the Performance settings tab.
	 *
	 * `object_cache` is reported because the template cache is inert without a
	 * persistent one — its toggle would otherwise appear to do nothing, and the
	 * UI needs to say why rather than leave the user guessing.
	 */
	public function page_cache_status() {
		$stats = \ABlocks\Classes\PageCache\Store::stats();

		$css_dir   = \ABlocks\Performance\StyleConsolidator::base_dir();
		$css_files = is_dir( $css_dir ) ? glob( $css_dir . '/*.css' ) : [];

		wp_send_json_success(
			array(
				'pages'        => (int) $stats['pages'],
				'files'        => (int) $stats['files'],
				'bytes'        => (int) $stats['bytes'],
				'size'         => size_format( $stats['bytes'], 2 ),
				'css_files'    => is_array( $css_files ) ? count( $css_files ) : 0,
				'object_cache' => \ABlocks\Classes\CacheBackend::is_persistent(),
			)
		);
	}

	/**
	 * Empty every cache this plugin owns, and report what is left.
	 *
	 * Consolidated stylesheets are deliberately NOT removed here. They are
	 * content-addressed and referenced by cached HTML, so deleting them while a
	 * cached page still points at one would strip that page's styling. They are
	 * reclaimed by age instead, via StyleConsolidator::prune().
	 */
	public function purge_page_cache() {
		$removed = \ABlocks\Classes\PageCache\Store::flush();

		// Fragments and resolved templates live behind generation counters, so
		// bumping those invalidates them without a scan.
		\ABlocks\Performance\FragmentCache::bump_version();
		\ABlocks\Performance\TemplateCache::maybe_bump_version();

		Helper::clear_third_party_plugin_cache();

		$stats = \ABlocks\Classes\PageCache\Store::stats();

		wp_send_json_success(
			array(
				'removed' => (int) $removed,
				'pages'   => (int) $stats['pages'],
				'size'    => size_format( $stats['bytes'], 2 ),
			)
		);
	}

	/**
	 * Audit the site and report what to fix.
	 */
	public function run_scanner() {
		// is_plugin_active() lives in an admin include that is not loaded for
		// AJAX requests, and the conflicting-cache check depends on it.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		wp_send_json_success( \ABlocks\Classes\Scanner\Scanner::run() );
	}

	/**
	 * Ignore a check, or bring an ignored one back.
	 *
	 * Kept rather than deleted: an ignored check still appears in its own list
	 * with an undo, so a decision made months ago stays visible instead of
	 * quietly disappearing from the report.
	 *
	 * @param array $payload Request fields.
	 */
	public function scanner_dismiss( $payload ) {
		$id = ! empty( $payload['id'] ) ? sanitize_key( $payload['id'] ) : '';
		if ( '' === $id ) {
			wp_send_json_error( __( 'No check given.', 'ablocks' ) );
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		\ABlocks\Classes\Scanner\Scanner::set_dismissed( $id, ! empty( $payload['dismiss'] ) );

		wp_send_json_success( array( 'report' => \ABlocks\Classes\Scanner\Scanner::run() ) );
	}

	/**
	 * Switch on one setting the scanner recommended.
	 *
	 * The allowed fields are taken from the scanner's own current output rather
	 * than from a hard-coded list. That means this endpoint can only ever change
	 * something the scanner is, right now, asking the user to change — a request
	 * naming any other option is rejected, and the two can never drift apart.
	 *
	 * @param array $payload Request fields.
	 */
	public function scanner_apply_fix( $payload ) {
		$field = ! empty( $payload['field'] ) ? sanitize_key( $payload['field'] ) : '';
		if ( '' === $field ) {
			wp_send_json_error( __( 'No setting given.', 'ablocks' ) );
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$report  = \ABlocks\Classes\Scanner\Scanner::run();
		$allowed = array();
		foreach ( $report['checks'] as $check ) {
			if ( ! empty( $check['field'] ) ) {
				$allowed[] = $check['field'];
			}
		}

		if ( ! in_array( $field, $allowed, true ) ) {
			wp_send_json_error( __( 'That setting is not one the scanner offers to change.', 'ablocks' ) );
		}

		$settings           = json_decode( get_option( ABLOCKS_SETTINGS_NAME, '{}' ), true );
		$settings           = is_array( $settings ) ? $settings : array();
		$settings[ $field ] = true;

		update_option( ABLOCKS_SETTINGS_NAME, wp_json_encode( $settings ) );

		// The scanner reads settings through the global that is populated at
		// bootstrap, so refresh it before re-running or the fix appears not to
		// have taken effect.
		$GLOBALS['ablocks_settings'] = json_decode( wp_json_encode( $settings ) );

		wp_send_json_success(
			array(
				'field'  => $field,
				'report' => \ABlocks\Classes\Scanner\Scanner::run(),
			)
		);
	}

	/**
	 * Totals for the Images panel.
	 */
	public function image_stats() {
		wp_send_json_success( \ABlocks\Performance\ImageTools::stats() );
	}

	/**
	 * Optimize one batch of images.
	 *
	 * Deliberately a small batch per request rather than one long job: PHP
	 * timeouts and memory limits are the normal failure mode for bulk media
	 * work, and a browser that walks batches can report progress and resume.
	 *
	 * @param array $payload Request fields.
	 */
	public function image_optimize_batch( $payload ) {
		$level = ! empty( $payload['level'] ) ? (string) $payload['level'] : '2x';
		$webp  = ! empty( $payload['webp'] );
		$all   = ! empty( $payload['all'] );

		$ids    = \ABlocks\Classes\Images\Compressor::pending_ids( 5, $all );
		$before = 0;
		$after  = 0;
		$done   = array();

		foreach ( $ids as $id ) {
			$result  = \ABlocks\Classes\Images\Compressor::optimize_attachment( $id, $level, $webp );
			$before += (int) $result['before'];
			$after   += (int) $result['after'];

			// Named so the UI can show what it is working through rather than an
			// anonymous counter.
			$done[] = array(
				'id'     => (int) $id,
				'title'  => get_the_title( $id ),
				'thumb'  => wp_get_attachment_image_url( $id, 'thumbnail' ),
				'before' => size_format( (int) $result['before'], 1 ),
				'after'  => size_format( (int) $result['after'], 1 ),
				'saved'  => $result['before'] > 0
					? round( 100 * ( $result['before'] - $result['after'] ) / $result['before'] )
					: 0,
			);
		}

		$stats = \ABlocks\Performance\ImageTools::stats();

		wp_send_json_success(
			array(
				'processed' => count( $ids ),
				'done'      => count( $ids ) < 5,
				'items'     => $done,
				'saved'     => size_format( max( 0, $before - $after ), 1 ),
				'stats'     => $stats,
			)
		);
	}

	/**
	 * Delete every stored original, reclaiming the disk they occupy.
	 *
	 * Irreversible by definition: it removes the only copy that "restore" could
	 * have restored, and the only clean source a different strength could have
	 * been applied from. Each affected attachment is flagged so a later run
	 * refuses rather than silently adopting the compressed file as its baseline.
	 */
	public function image_discard_originals() {
		$ids   = \ABlocks\Classes\Images\Compressor::ids_with_originals();
		$freed = 0;

		foreach ( $ids as $id ) {
			$freed += \ABlocks\Classes\Images\Compressor::discard_originals( $id );
		}

		wp_send_json_success(
			array(
				'cleared' => count( $ids ),
				'freed'   => size_format( $freed, 1 ),
				'stats'   => \ABlocks\Performance\ImageTools::stats(),
			)
		);
	}

	/**
	 * Put every optimized image back to its original.
	 */
	public function image_restore_all() {
		$ids   = \ABlocks\Classes\Images\Compressor::pending_ids( 10000, true );
		$count = 0;
		foreach ( $ids as $id ) {
			$count += \ABlocks\Classes\Images\Compressor::restore_attachment( $id );
		}

		wp_send_json_success(
			array(
				'restored' => $count,
				'stats'    => \ABlocks\Performance\ImageTools::stats(),
			)
		);
	}

	/**
	 * Scan a slice of the media library for unreferenced images.
	 *
	 * @param array $payload Request fields.
	 */
	public function image_scan_unused( $payload ) {
		$offset = isset( $payload['offset'] ) ? (int) $payload['offset'] : 0;
		$batch  = \ABlocks\Classes\Images\UnusedScanner::scan( 25, $offset );

		foreach ( $batch['items'] as &$item ) {
			$item['size'] = size_format( $item['bytes'], 1 );
		}
		unset( $item );

		wp_send_json_success( $batch );
	}

	/**
	 * Hash a slice of the library, and return the duplicate groups once done.
	 *
	 * Hashing reads whole files, so it is batched like the other bulk tools —
	 * the browser walks it rather than one request trying to read the entire
	 * media library before PHP gives up.
	 *
	 * @param array $payload Request fields.
	 */
	public function image_scan_duplicates( $payload ) {
		$offset = isset( $payload['offset'] ) ? (int) $payload['offset'] : 0;
		$batch  = \ABlocks\Classes\Images\DuplicateScanner::scan( 40, $offset );

		// Groups are only assembled at the end: reporting them mid-scan would
		// show duplicates that turn out to have a third copy further down.
		$batch['groups']  = $batch['done'] ? \ABlocks\Classes\Images\DuplicateScanner::groups() : array();
		$batch['summary'] = $batch['done'] ? \ABlocks\Classes\Images\DuplicateScanner::summary() : null;

		wp_send_json_success( $batch );
	}

	/**
	 * Move reviewed images into quarantine.
	 *
	 * @param array $payload Request fields.
	 */
	public function image_quarantine( $payload ) {
		$ids = isset( $payload['ids'] ) ? (array) $payload['ids'] : array();
		$ids = array_filter( array_map( 'intval', $ids ) );

		$held     = 0;
		$bytes    = 0;
		$skipped  = array();

		foreach ( $ids as $id ) {
			$result = \ABlocks\Classes\Images\Quarantine::hold( $id );
			if ( $result['ok'] ) {
				$held++;
				$bytes += (int) $result['bytes'];
			} else {
				$skipped[] = array(
					'id'     => $id,
					'reason' => $result['message'],
				);
			}
		}

		wp_send_json_success(
			array(
				'held'    => $held,
				'freed'   => size_format( $bytes, 1 ),
				'skipped' => $skipped,
				'stats'   => \ABlocks\Performance\ImageTools::stats(),
			)
		);
	}

	/**
	 * List, restore from, or empty the quarantine.
	 *
	 * @param array $payload Request fields.
	 */
	public function image_quarantine_action( $payload ) {
		$do = ! empty( $payload['do'] ) ? sanitize_key( $payload['do'] ) : 'list';

		if ( 'restore' === $do ) {
			$ok = \ABlocks\Classes\Images\Quarantine::restore( (int) $payload['id'] );
			wp_send_json_success(
				array(
					'ok'    => (bool) $ok,
					'items' => array_values( \ABlocks\Classes\Images\Quarantine::records() ),
					'stats' => \ABlocks\Performance\ImageTools::stats(),
				)
			);
		}

		if ( 'sweep' === $do ) {
			// Zero days: the user asked to empty it now, so retention does not
			// apply. Retention protects against forgetting, not against intent.
			$result = \ABlocks\Classes\Images\Quarantine::sweep( 0 );
			wp_send_json_success(
				array(
					'deleted' => (int) $result['deleted'],
					'freed'   => size_format( (int) $result['bytes'], 1 ),
					'items'   => array_values( \ABlocks\Classes\Images\Quarantine::records() ),
					'stats'   => \ABlocks\Performance\ImageTools::stats(),
				)
			);
		}

		$items = array_values( \ABlocks\Classes\Images\Quarantine::records() );
		foreach ( $items as &$item ) {
			$item['size'] = size_format( isset( $item['bytes'] ) ? (int) $item['bytes'] : 0, 1 );
			$item['held'] = isset( $item['time'] ) ? gmdate( 'Y-m-d', (int) $item['time'] ) : '';
			unset( $item['files'] );
		}
		unset( $item );

		wp_send_json_success( array( 'items' => $items ) );
	}

	public function clear_demo_transients() {
		global $wpdb;

		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_ablocks_demo_%' OR option_name LIKE '_transient_timeout_ablocks_demo_%'" );

		if ( ! empty( $wpdb->last_error ) ) {
			wp_send_json_error( $wpdb->last_error );
		}

		wp_send_json_success();
	}
	public function install_academy_lms() {
		// Check user permissions
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( __( 'You do not have sufficient permissions to install plugins.', 'ablocks' ) );
		}

		// Check if the plugin is already installed
		if ( ! Helper::is_plugin_installed( 'academy/academy.php' ) ) {

			$plugin_status = $this->install_plugin( 'academy', true );
			if ( $plugin_status ) {
				wp_send_json_success( __( 'Plugin installed and activated successfully!', 'ablocks' ) );
			}
			wp_send_json_error( __( 'Sorry, failed to download.', 'ablocks' ) );
		}

		// Activate the plugin
		$activate_status = activate_plugin( 'academy/academy.php' );
		if ( is_wp_error( $activate_status ) ) {
			wp_send_json_error( 'Plugin activation failed: ' . $activate_status->get_error_message() );
		}
		wp_send_json_success( __( 'Plugin installed and activated successfully!', 'ablocks' ) );
	}
	public function install_storeengine() {
		// Check user permissions
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( __( 'You do not have sufficient permissions to install plugins.', 'ablocks' ) );
		}

		// Check if the plugin is already installed
		if ( ! Helper::is_plugin_installed( 'storeengine/storeengine.php' ) ) {

			$plugin_status = $this->install_plugin( 'storeengine', true );
			if ( $plugin_status ) {
				wp_send_json_success( __( 'Plugin installed and activated successfully!', 'ablocks' ) );
			}
			wp_send_json_error( __( 'Sorry, failed to download.', 'ablocks' ) );
		}

		// Activate the plugin
		$activate_status = activate_plugin( 'storeengine/storeengine.php' );
		if ( is_wp_error( $activate_status ) ) {
			wp_send_json_error( 'Plugin activation failed: ' . $activate_status->get_error_message() );
		}
		wp_send_json_success( __( 'Plugin installed and activated successfully!', 'ablocks' ) );
	}
	public function install_ecm() {
		// Check user permissions
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( __( 'You do not have sufficient permissions to install plugins.', 'ablocks' ) );
		}

		// Check if the plugin is already installed
		if ( ! Helper::is_plugin_installed( 'easy-content-manager/easy-content-manager.php' ) ) {

			$plugin_status = $this->install_plugin( 'easy-content-manager', true );
			if ( $plugin_status ) {
				wp_send_json_success( __( 'Plugin installed and activated successfully!', 'ablocks' ) );
			}
			wp_send_json_error( __( 'Sorry, failed to download.', 'ablocks' ) );
		}

		// Activate the plugin
		$activate_status = activate_plugin( 'easy-content-manager/easy-content-manager.php' );
		if ( is_wp_error( $activate_status ) ) {
			wp_send_json_error( 'Plugin activation failed: ' . $activate_status->get_error_message() );
		}
		wp_send_json_success( __( 'Plugin installed and activated successfully!', 'ablocks' ) );
	}
	public function install_plugin( $slug = '', $active = true ) {
		if ( empty( $slug ) ) {
			return new \WP_Error( 'empty_arg', __( 'Argument should not be empty.', 'ablocks' ) );
		}

		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

		$plugin_data = $this->get_remote_plugin_data( $slug );

		if ( is_wp_error( $plugin_data ) ) {
			return $plugin_data;
		}

		$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );

		// install plugin
		$install = $upgrader->install( $plugin_data->download_link );

		if ( is_wp_error( $install ) ) {
			return $install;
		}

		// activate plugin
		if ( $install === true && $active ) {
			$active = activate_plugin( $upgrader->plugin_info(), '', false, true );

			if ( is_wp_error( $active ) ) {
				return $active;
			}

			return $active === null;
		}

		return $install;
	}

	public function get_remote_plugin_data( $slug = '' ) {
		if ( empty( $slug ) ) {
			return new \WP_Error( 'empty_arg', __( 'Argument should not be empty.', 'ablocks' ) );
		}

		$response = wp_remote_post(
			'http://api.wordpress.org/plugins/info/1.0/',
			array(
				'body' => array(
					'action' => 'plugin_information',
					'request' => maybe_serialize( (object) array(
						'slug' => $slug,
						'fields' => array(
							'version' => false,
						),
					)),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return maybe_unserialize( wp_remote_retrieve_body( $response ) );
	}

	public function download_google_fonts() {
		global $ablocks_fonts;
		$fontDownloader = new FontLoadLocally();
		$fontDownloader->process_font_queue( $ablocks_fonts );
	}
}
