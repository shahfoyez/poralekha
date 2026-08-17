<?php
namespace ABlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

use ABlocks\Classes\AssetsGenerator;
use ABlocks\Helper;
use ABlocks\Classes\FileUpload;
use ABlocks\Classes\FontLoadLocally;
use ABlocks\Blocks\FormBuilder\BlockAttrSanitizer\Sanitizer as BlockSanitizer;
use ABlocks\Blocks\FormBuilder\Helper as FormBuilderHelper;

class Blocks {
	public static function init() {
		$self = new self();
		// block initialization
		add_action( 'init', [ $self, 'blocks_init' ] );
		add_action( 'enqueue_block_assets', [ $self, 'load_academy_core_scripts' ] );
		add_filter( 'block_categories_all', [ $self, 'register_block_category' ], 10, 2 );
		// Assets Generator
		add_action( 'save_post', [ $self, 'generate_block_assets' ], 10, 3 );
		add_action( 'switch_theme', [ $self, 'clear_generated_block_assets' ] );
		add_action( 'save_post', [ $self, 'download_google_fonts_locally' ], 20, 3 );
		add_action( 'deleted_post', [ $self, 'flush_site_fonts_on_delete' ], 10, 2 );
		// Self-hosting template fonts discovered on a front-end request happens
		// here, off the visitor's request.
		add_action( 'ablocks/download_site_fonts', [ '\ABlocks\Classes\FontCollector', 'update_site_fonts' ] );
		// A theme swap changes which templates and template parts exist.
		add_action( 'switch_theme', [ '\ABlocks\Classes\FontCollector', 'flush_site_fonts' ] );
		// PERF-2: pre-warm the page's generated assets in the background after save,
		// so the first visitor doesn't pay the parse/generate/write cost.
		add_action( 'save_post', [ $self, 'prewarm_page_assets' ], 30, 3 );
		// Recompute global typography fonts whenever the plugin's global settings change.
		add_action( 'ablocks/after_save_settings', [ $self, 'refresh_global_fonts' ] );
		// Bust the cached global CSS variables when global settings change.
		add_action( 'ablocks/after_save_settings', [ $self, 'clear_global_css_cache' ] );

		$self->register_block_sanitizer();
		$self->form_builder_default_data();
	}

	public function blocks_init() {
		// keep it common for Academy LMS and QuizPress
		if ( ( helper::is_gutenberg_editor() && ( Helper::check_post_type_from_admin( 'academy_certificate' ) || Helper::check_post_type_from_admin( 'qp_certificate' ) ) ) || ( ! helper::is_gutenberg_editor() && ! is_admin() ) ) {
			new \ABlocks\Blocks\AcademyCertificate\Block();
			new \ABlocks\Blocks\AcademyCertificateText\Block();
			new \ABlocks\Blocks\AcademyContainer\Block();
			if ( Helper::is_plugin_active( 'academy-pro/academy-pro.php' ) ) {
				new \ABlocks\Blocks\AcademyCertificateId\Block();
			}
		}
		// Academy LMS Block Register Here
		if ( Helper::is_plugin_active( 'academy/academy.php' ) ) {
			add_filter( 'academy/is_load_common_scripts', '__return_true' );
			if ( Helper::is_gutenberg_editor() ) {
				add_filter( 'academy/is_load_common_js_scripts', '__return_false' );
			}
			new \ABlocks\Blocks\AcademyCourses\Block();
			new \ABlocks\Blocks\AcademyEnrollForm\Block();
			new \ABlocks\Blocks\AcademyStudentRegistrationForm\Block();
			new \ABlocks\Blocks\AcademyCourseSearch\Block();
			new \ABlocks\Blocks\AcademyInstructorRegistrationForm\Block();
			new \ABlocks\Blocks\AcademyPdf\Block();
			new \ABlocks\Blocks\AcademyPasswordResetForm\Block();
			new \ABlocks\Blocks\AcademyLoginForm\Block();
			new \ABlocks\Blocks\AcademyCourseMedia\Block();

			new \ABlocks\Blocks\AcademyCourseCurriculums\Block();
			new \ABlocks\Blocks\AcademyCourseReviews\Block();
			new \ABlocks\Blocks\AcademyReviewForm\Block();
			new \ABlocks\Blocks\AcademyReviewList\Block();
			new \ABlocks\Blocks\AcademyAdditionInfo\Block();
			new \ABlocks\Blocks\AcademyCourseDescription\Block();
			new \ABlocks\Blocks\AcademyCourseInstructor\Block();
			new \ABlocks\Blocks\AcademyEnrollContent\Block();
		}//end if
		// StoreEngine Register Block
		if ( Helper::is_plugin_active( 'storeengine/storeengine.php' ) ) {
			new \ABlocks\Blocks\StoreengineProducts\Block();
			new \ABlocks\Blocks\StoreengineCouponForm\Block();
			new \ABlocks\Blocks\StoreengineCartList\Block();
			new \ABlocks\Blocks\StoreengineLoginForm\Block();
			new \ABlocks\Blocks\StoreengineProductFilter\Block();
			new \ABlocks\Blocks\StoreengineCheckoutForm\Block();
			new \ABlocks\Blocks\StoreengineContinueButton\Block();
			new \ABlocks\Blocks\StoreengineCheckoutButton\Block();
			new \ABlocks\Blocks\StoreengineCartSubTable\Block();
			new \ABlocks\Blocks\StoreengineCartButton\Block();
			new \ABlocks\Blocks\StoreengineOrderInfo\Block();
			new \ABlocks\Blocks\StoreengineBillingInfo\Block();
			new \ABlocks\Blocks\StoreengineShippingInfo\Block();
			new \ABlocks\Blocks\StoreengineCartNotice\Block();
			new \ABlocks\Blocks\StoreengineOrderDetails\Block();
			// Funnel Builder blocks (render the funnel shortcodes).
			new \ABlocks\Blocks\StoreengineFunnelCheckout\Block();
			new \ABlocks\Blocks\StoreengineFunnelOffer\Block();
			new \ABlocks\Blocks\StoreengineFunnelAccept\Block();
			new \ABlocks\Blocks\StoreengineFunnelDecline\Block();
			new \ABlocks\Blocks\StoreengineFunnelContinue\Block();
			new \ABlocks\Blocks\StoreengineMiniCart\Block();
			new \ABlocks\Blocks\StoreengineProductGallery\Block();
			new \ABlocks\Blocks\StoreengineProductSummary\Block();
			new \ABlocks\Blocks\StoreengineProductDescription\Block();
			new \ABlocks\Blocks\StoreengineProductReview\Block();
		}//end if
		
		if ( class_exists('EasyContentManager') ) {
			// Easy Content Manager Register Block
			new \ABlocks\Blocks\EcmBookmarkButton\Block();
			new \ABlocks\Blocks\EcmBookmarkBadge\Block();
			new \ABlocks\Blocks\EcmBookmarkList\Block();
			new \ABlocks\Blocks\EcmBookmarkCount\Block();
			new \ABlocks\Blocks\EcmReactionList\Block();
			new \ABlocks\Blocks\EcmReaction\Block();
			new \ABlocks\Blocks\EcmUpvote\Block();
			new \ABlocks\Blocks\EcmUpvoteCount\Block();
			new \ABlocks\Blocks\EcmUpvoteList\Block();
			new \ABlocks\Blocks\EcmClaimButton\Block();
			new \ABlocks\Blocks\EcmClaimBadge\Block();
			new \ABlocks\Blocks\EcmClaimList\Block();
			new \ABlocks\Blocks\EcmClaimCount\Block();
		}
		
		new \ABlocks\Blocks\Container\Block();
		new \ABlocks\Blocks\Heading\Block();
		new \ABlocks\Blocks\Paragraph\Block();
		new \ABlocks\Blocks\Image\Block();
		new \ABlocks\Blocks\Button\Block();
		new \ABlocks\Blocks\DualButton\Block();
		new \ABlocks\Blocks\Icon\Block();
		new \ABlocks\Blocks\InfoBox\Block();
		new \ABlocks\Blocks\Lists\Block();
		new \ABlocks\Blocks\Counter\Block();
		new \ABlocks\Blocks\StarRatings\Block();
		new \ABlocks\Blocks\Divider\Block();
		new \ABlocks\Blocks\Spacer\Block();
		new \ABlocks\Blocks\Video\Block();
		new \ABlocks\Blocks\LoopBuilder\Block();
		new \ABlocks\Blocks\LoopTemplate\Block();
		new \ABlocks\Blocks\LoopFilter\Block();
		new \ABlocks\Blocks\LoopLoadMore\Block();
		new \ABlocks\Blocks\Search\Block();
		new \ABlocks\Blocks\Carousel\Block();
		new \ABlocks\Blocks\CarouselChild\Block();
		new \ABlocks\Blocks\Chart\Block();
		new \ABlocks\Blocks\Toggle\Block();
		new \ABlocks\Blocks\ToggleChild\Block();
		new \ABlocks\Blocks\Accordion\Block();
		new \ABlocks\Blocks\SingleAccordion\Block();
		new \ABlocks\Blocks\Tabs\Block();
		new \ABlocks\Blocks\TabsChild\Block();
		new \ABlocks\Blocks\Countdown\Block();
		new \ABlocks\Blocks\NewsTicker\Block();
		new \ABlocks\Blocks\ImageHotspot\Block();
		new \ABlocks\Blocks\ImageHotspotChild\Block();
		new \ABlocks\Blocks\ProgressTracker\Block();
		new \ABlocks\Blocks\ImageScroll\Block();
		new \ABlocks\Blocks\Menu\Block();
		new \ABlocks\Blocks\MenuItem\Block();
		new \ABlocks\Blocks\MenuChildSub\Block();
		new \ABlocks\Blocks\MenuChildMega\Block();
		new \ABlocks\Blocks\StripeButton\Block();
		new \ABlocks\Blocks\PaypalButton\Block();

		new \ABlocks\Blocks\Table\Block();
		new \ABlocks\Blocks\TableCell\Block();
		new \ABlocks\Blocks\TableRow\Block();
		new \ABlocks\Blocks\TableHeader\Block();
		new \ABlocks\Blocks\TableFooter\Block();
		new \ABlocks\Blocks\TableBody\Block();

		new \ABlocks\Blocks\Coupon\Block();
		new \ABlocks\Blocks\ContentTimeline\Block();
		new \ABlocks\Blocks\ContentTimelineChild\Block();
		new \ABlocks\Blocks\Map\Block();
		new \ABlocks\Blocks\TableOfContent\Block();
		new \ABlocks\Blocks\Modal\Block();
		new \ABlocks\Blocks\ModalTrigger\Block();
		new \ABlocks\Blocks\ModalPanel\Block();
		new \ABlocks\Blocks\ImageComparison\Block();
		new \ABlocks\Blocks\FlipBox\Block();
		new \ABlocks\Blocks\FlipBoxChild\Block();
		new \ABlocks\Blocks\PriceMenu\Block();
		new \ABlocks\Blocks\PriceMenuItem\Block();
		new \ABlocks\Blocks\SocialShares\Block();
		new \ABlocks\Blocks\Notice\Block();
		new \ABlocks\Blocks\SvgDraw\Block();
		new \ABlocks\Blocks\FrontendDashboard\Block();
		new \ABlocks\Blocks\LottieAnimation\Block();
		new \ABlocks\Blocks\Marquee\Block();
		new \ABlocks\Blocks\DynamicText\Block();
		new \ABlocks\Blocks\MarqueeChild\Block();
		new \ABlocks\Blocks\Logout\Block();
		new \ABlocks\Blocks\FilterableCards\Block();
		new \ABlocks\Blocks\FilterableCardsItem\Block();
		new \ABlocks\Blocks\AdvanceLists\Block();
		new \ABlocks\Blocks\AdvanceListItem\Block();
		new \ABlocks\Blocks\QrCode\Block();
		new \ABlocks\Blocks\FeaturedImage\Block();
		new \ABlocks\Blocks\ScrollToTop\Block();
		new \ABlocks\Blocks\Breadcrumb\Block();
		new \ABlocks\Blocks\InteractiveCircleInfographic\Block();
		// Form Builder
		new \ABlocks\Blocks\FormBuilder\Block();
		new \ABlocks\Blocks\FormInput\Block();
		new \ABlocks\Blocks\FormPassword\Block();
		new \ABlocks\Blocks\FormTextarea\Block();
		new \ABlocks\Blocks\FormCheckbox\Block();
		new \ABlocks\Blocks\FormSelect\Block();
		new \ABlocks\Blocks\FormMultiStep\Block();
		new \ABlocks\Blocks\FormMultiStepChild\Block();
		new \ABlocks\Blocks\FormRadio\Block();
		new \ABlocks\Blocks\TextPath\Block();
		new \ABlocks\Blocks\taxonomyListing\Block();
		new \ABlocks\Blocks\Player\Block();
	}

	public function register_block_category( $categories, $post ) {
		return array_merge(
			[
				[
					'slug' => 'ablocks',
					'title' => __( 'ABlocks', 'ablocks' ),
				],
				[
					'slug' => 'academy',
					'title' => __( 'Academy LMS', 'ablocks' ),
				],
				[
					'slug' => 'storeengine',
					'title' => __( 'StoreEngine', 'ablocks' ),
				],
				[
					'slug' => 'easy-content-manager',
					'title' => __( 'Easy Content Manager', 'ablocks' ),
				],
			],
			$categories
		);
	}

	public function load_academy_core_scripts() {
		if ( ! Helper::is_plugin_active( 'academy/academy.php' ) || ! is_admin() ) {
			return;
		}
		$ScriptsBase = new \Academy\Assets();
		$ScriptsBase->frontend_common_assets();
	}

	public function generate_block_assets( $post_id, $post, $update ) {
		if ( ! Helper::is_enabled_assets_generation() ) {
			return;
		}

		if ( isset( $post->post_status ) && 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( false !== wp_is_post_revision( $post_id ) ) {
			return;
		}

		$uploader = new \ABlocks\Classes\FileUpload();

		// Template-like content is shared across many pages, so a change there must
		// invalidate every page's combined assets. Regular content only affects its
		// own page — scope the invalidation to that one post so a single edit can't
		// wipe the whole site's asset cache and cause a regeneration stampede.
		$global_impact_post_types = apply_filters(
			'ablocks/global_asset_invalidation_post_types',
			[ 'ablocks_tb', 'wp_template', 'wp_template_part', 'wp_block' ]
		);

		if ( in_array( get_post_type( $post_id ), $global_impact_post_types, true ) ) {
			$this->clear_generated_block_assets();
			return;
		}

		// Drop just this post's cached files; they regenerate on next visit.
		$uploader->delete_file( $post_id . '.min.css' );
		$uploader->delete_file( $post_id . '.min.js' );
	}

	public function download_google_fonts_locally( $post_id, $post, $update ) {
		if ( isset( $post->post_status ) && 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( false !== wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only process content that can contain aBlocks fonts (aBlocks blocks or
		// reusable blocks that might wrap them).
		if ( empty( $post->post_content )
			|| ( false === strpos( $post->post_content, 'wp:ablocks' ) && false === strpos( $post->post_content, 'wp:block' ) ) ) {
			return;
		}

		// Collect the fonts this post actually uses from its saved attributes,
		// cache them in post meta, and self-host them locally.
		// See docs/FONT-MANAGEMENT-PLAN.md.
		\ABlocks\Classes\FontCollector::save_post_fonts( $post_id );

		// Templates, template parts and theme builder layouts render on pages that
		// are not the queried post, so their fonts are tracked site-wide. Recompute
		// (and download) here rather than on a visitor's request.
		if ( in_array( get_post_type( $post_id ), \ABlocks\Classes\FontCollector::get_site_font_post_types(), true ) ) {
			\ABlocks\Classes\FontCollector::update_site_fonts();
		}
	}

	/**
	 * Deleting a template/part/layout can remove the last user of a font.
	 *
	 * @param int      $post_id Post being deleted.
	 * @param \WP_Post $post    The deleted post — by this point it is gone from the
	 *                          database, so the type has to come from here.
	 */
	public function flush_site_fonts_on_delete( $post_id, $post = null ) {
		$post_type = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );

		if ( in_array( $post_type, \ABlocks\Classes\FontCollector::get_site_font_post_types(), true ) ) {
			\ABlocks\Classes\FontCollector::flush_site_fonts();
		}
	}

	/**
	 * Pre-warm a published page's combined CSS/JS in the background after save,
	 * via a non-blocking loopback request. This moves the parse/generate/write
	 * cost off the first visitor's request (PERF-2). If loopback is unavailable,
	 * the existing on-request generation still runs as a fallback.
	 */
	public function prewarm_page_assets( $post_id, $post, $update ) {
		if ( ! Helper::is_enabled_assets_generation() ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( false !== wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! ( $post instanceof \WP_Post ) ) {
			$post = get_post( $post_id );
		}
		if ( ! $post || 'publish' !== $post->post_status || ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}
		// Only for content that actually has aBlocks (or wrapping) blocks.
		if ( empty( $post->post_content )
			|| ( false === strpos( $post->post_content, 'wp:ablocks' ) && false === strpos( $post->post_content, 'wp:block' ) ) ) {
			return;
		}
		if ( ! (bool) apply_filters( 'ablocks/perf/prewarm_assets', true, $post_id ) ) {
			return;
		}

		$url = get_permalink( $post_id );
		if ( ! $url ) {
			return;
		}

		wp_remote_get(
			$url,
			[
				'blocking'  => false,
				'timeout'   => 0.01,
				'sslverify' => false,
				'headers'   => [ 'X-ABlocks-Prewarm' => '1' ],
			]
		);
	}

	/**
	 * Recompute the global typography font set after settings are saved.
	 * Re-primes the in-memory settings so the collector reads fresh values.
	 */
	public function refresh_global_fonts() {
		$GLOBALS['ablocks_settings'] = json_decode( get_option( ABLOCKS_SETTINGS_NAME, '{}' ) );
		\ABlocks\Classes\FontCollector::update_global_fonts();
	}

	/**
	 * Invalidate the cached global CSS variables (see Assets::global_css_variable).
	 */
	public function clear_global_css_cache() {
		delete_transient( 'ablocks_global_css_vars' );
	}

	public function form_builder_default_data(): void {
		add_filter(
			'ablocks/assets/editor_scripts_data',
			[ FormBuilderHelper::class, 'form_builder_default_data' ]
		);
	}
	public function register_block_sanitizer(): void {
		BlockSanitizer::init();
	}

	public function clear_generated_block_assets() {
		$uploader = new \ABlocks\Classes\FileUpload();
		$uploader->delete_files();
		Helper::clear_third_party_plugin_cache();
	}
}
