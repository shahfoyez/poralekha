<?php
namespace ABlocks\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;

class Base {
	public static function get_saved_data() {
		$settings = get_option( ABLOCKS_SETTINGS_NAME );
		if ( $settings ) {
			return json_decode( $settings, true );
		}
		return [];
	}
	public static function get_default_data() {
		return apply_filters('ablocks/admin/settings/base_default_data', [
			// global style
			'default_container_width' => 1140,
			'container_padding' => 10,
			'container_element_gap' => 20,
			'enabled_assets_file_generation' => false,
			'enabled_block_copy_paste_style' => true,
			// Design system lock: authors may only use the global typography /
			// colour presets, so nobody can hand-pick a one-off value by mistake.
			'lock_global_typography' => false,
			// Escalates the typography lock from 'no picking a typeface' to 'no
			// custom typography at all'. Off by default: adjusting a size is normal
			// design work, picking a stray typeface is the mistake.
			'lock_global_typography_strict' => false,
			'lock_global_colors' => false,
			'enabled_load_google_font_locally' => false,
			'enabled_only_selected_fonts' => false,
			'selected_fonts' => [],
			// Emit a metric-adjusted local fallback face per font, so text does not
			// reflow when the web font finishes loading (CLS).
			'font_metric_fallback' => true,
			// Performance Suite — platform optimizations (opt-in; default off so
			// existing sites are unchanged until the user enables them).
			'perf_disable_emojis' => false,
			'perf_disable_embeds' => false,
			'perf_disable_dashicons' => false,
			'perf_disable_jquery_migrate' => false,
			'perf_control_heartbeat' => false,
			'perf_heartbeat_frequency' => 60,
			// Performance Suite — delay JS until interaction.
			'perf_delay_js' => false,
			'perf_delay_js_timeout' => 5000,
			// Performance Suite — image loading (safe: on by default).
			'perf_lazy_images' => true,
			'perf_lcp_eager_count' => 1,
			'perf_image_dimensions' => true,
			'perf_responsive_images' => true,
			'perf_preload_lcp' => true,
			'perf_touch_targets' => false,
			// Performance Suite — inline small page CSS (safe: on by default).
			'perf_inline_css' => true,
			'perf_async_css' => false,
			'perf_critical_css' => false,
			'perf_defer_js' => false,
			// Performance Suite — full-page HTML cache. Off by default: unlike
			// the toggles above it changes what every visitor receives, so it
			// needs a deliberate decision rather than inheriting a default.
			// See docs/PAGE-CACHE-PLAN.md.
			'perf_page_cache' => false,
			// 'all' | 'ablocks_only'
			'perf_page_cache_scope' => 'all',
			// Hours; 0 = keep until something invalidates it.
			'perf_page_cache_ttl' => 0,
			'perf_page_cache_gzip' => true,
			'perf_page_cache_prewarm' => true,
			'perf_page_cache_mobile_variant' => false,
			// Empty allowlist means any query string bypasses the cache, so
			// ?utm_source=x can never overwrite the canonical entry for a URL.
			// Background crawler: tops the cache up with pages no visitor has
			// requested yet, a small batch at a time.
			'perf_page_cache_crawler' => false,
			'perf_page_cache_crawl_batch' => 20,
			'perf_page_cache_query_args' => [],
			'perf_page_cache_exclusions' => [],
			// Performance Suite — move render-blocking inline CSS into cached
			// files so the browser can reuse it across navigations. Off by
			// default: it changes how every page's CSS is delivered.
			'perf_consolidate_css' => false,
			// Runs smaller than this stay inline — below a couple of KB the
			// extra request costs more than the bytes saved.
			'perf_consolidate_css_min' => 2048,
			// Performance Suite — cache rendered FSE template parts (header,
			// footer). Off by default: replaying a cached render's side effects
			// is the delicate part, so it wants per-site verification.
			'perf_fragment_cache' => false,
			'perf_fragment_cache_ttl' => 43200,
			// Performance Suite — memoise block template resolution. Measured at
			// 11-14ms and 7-10 queries per request on a block theme.
			'perf_template_cache' => false,
			'perf_template_cache_ttl' => 43200,
			// Image tools — recompress files on disk. Every part opt-in: unlike
			// the delivery options above, these rewrite the user's media.
			'perf_image_optimize_on_upload' => false,
			// '1x' gentle | '2x' balanced | '5x' aggressive
			'perf_image_level' => '2x',
			'perf_image_webp' => true,
			'perf_image_quarantine_autodelete' => false,
			'perf_image_quarantine_days' => 30,
			// Upload standards. Filenames can be blocked outright; alt text cannot
			// be required at upload time, so it gates use instead. See UploadGuard.
			'perf_image_require_filename' => false,
			'perf_image_require_alt' => false,
			// Marketing — carry incoming affiliate/UTM query params onto tagged
			// links so a visitor who lands on ?ref=xyz keeps that param as they
			// click through. Opt-in; inert unless a tagged link is present AND a
			// tracked param exists in the current URL.
			'param_passthrough_enabled' => false,
			'param_passthrough_keys' => 'ref,utm_source,utm_medium,utm_campaign,utm_term,utm_content',
			// Which links receive the params: 'class' (only links tagged with the
			// CSS class), 'all' (every same-site link), or 'keyword' (links whose
			// URL contains one of the configured words).
			'param_passthrough_match' => 'class',
			'param_passthrough_class' => 'aff-link',
			'param_passthrough_keyword' => '',
			// Remember the incoming params in a cookie so they keep being applied
			// even after the visitor lands on a page whose URL no longer carries
			// them (e.g. after clicking an untagged menu link).
			'param_passthrough_persist' => true,
			'param_passthrough_cookie_days' => 30,
			'enabled_coming_soon_page' => false,
			'coming_soon_page' => '',
			'enabled_maintenance_page' => false,
			'maintenance_page' => '',
			'frontend_dashboard_page' => '',
			'login_page' => '',
			'registration_page' => '',
			'forget_password_page' => '',
			'global_color' => [
				[
					'id' => 'primary',
					'label' => 'Primary Color',
					'value' => '#6EC1E4',
					'is_system' => true,
				],
				[
					'id' => 'secondary',
					'label' => 'Secondary',
					'value' => '#54595F',
					'is_system' => true,
				],
				[
					'id' => 'text',
					'label' => 'Text',
					'value' => '#7A7A7A',
					'is_system' => true,
				],
				[
					'id' => 'accent',
					'label' => 'Accent',
					'value' => '#61CE70',
					'is_system' => true,
				],
			],
			'global_typography' => [
				[
					'id' => 'primary',
					'label' => 'Primary',
					'value' => Typography::get_attribute_default_value( true, [] ),
					'is_system' => true,
				],
				[
					'id' => 'secondary',
					'label' => 'Secondary',
					'value' => Typography::get_attribute_default_value( true, [] ),
					'is_system' => true,
				],
				[
					'id' => 'text',
					'label' => 'Text',
					'value' => Typography::get_attribute_default_value( true, [] ),
					'is_system' => true,
				],
				[
					'id' => 'accent',
					'label' => 'Accent',
					'value' => Typography::get_attribute_default_value( true, [] ),
					'is_system' => true,
				],
			],
			'global_font_family_fallback' => 'Sans-serif',
			// Global Typography and color
			'global_body_text_color' => '',
			'global_body_typography' => Typography::get_attribute_default_value( true, [] ),
			'global_body_paragraph_space' => Range::get_attribute_default_value( [
				'isResponsive' => true,
				'defaultValue' => '',
				'defaultValueTablet' => '',
				'defaultValueMobile' => '',
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'attributeObjectKey' => 'value',
			] ),
			'global_link_color' => '',
			'global_link_hover_color' => '',
			'global_link_typography' => Typography::get_attribute_default_value( true, [] ),
			'global_link_hover_typography' => Typography::get_attribute_default_value( true, [] ),
			'global_h1_color' => '',
			'global_h1_typography' => Typography::get_attribute_default_value( true, [] ),
			'global_h2_color' => '',
			'global_h2_typography' => Typography::get_attribute_default_value( true, [] ),
			'global_h3_color' => '',
			'global_h3_typography' => Typography::get_attribute_default_value( true, [] ),
			'global_h4_color' => '',
			'global_h4_typography' => Typography::get_attribute_default_value( true, [] ),
			'global_h5_color' => '',
			'global_h5_typography' => Typography::get_attribute_default_value( true, [] ),
			'global_h6_color' => '',
			'global_h6_typography' => Typography::get_attribute_default_value( true, [] ),
		]);
	}

	public static function save_settings( $form_data = false ) {
		$default_data = self::get_default_data();
		$saved_data = self::get_saved_data();
		$settings_data = wp_parse_args( $saved_data, $default_data );
		if ( $form_data ) {
			$settings_data = wp_parse_args( $form_data, $settings_data );
		}
		// if settings already saved, then update it
		if ( count( $saved_data ) ) {
			return update_option( ABLOCKS_SETTINGS_NAME, wp_json_encode( $settings_data ) );
		}
		return add_option( ABLOCKS_SETTINGS_NAME, wp_json_encode( $settings_data ) );
	}
}
