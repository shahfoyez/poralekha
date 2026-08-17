<?php
/**
 * Frontend helper functions.
 */

use StoreEngine\Addons\Subscription\Hooks;
use StoreEngine\Classes\AbstractCollection;
use StoreEngine\Classes\AbstractOrder;
use StoreEngine\Classes\Attributes;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineNotFoundException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Classes\Price;
use StoreEngine\Classes\Product\SimpleProduct;
use StoreEngine\Classes\Product\VariableProduct;
use StoreEngine\Classes\ProductFactory;
use StoreEngine\Classes\StoreengineDatetime;
use StoreEngine\Utils\ArrayUtil;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\ShippingUtils;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle redirects before content is output - hooked into template_redirect so is_page works.
 *
 * @return void
 *
 * Mirrors the standard template-redirect handler.
 */
function storeengine_template_redirect(): void {
	global $wp_query, $wp;

	if ( ! is_user_logged_in() && Helper::is_dashboard() ) {
		$auth_redirect_type = Helper::get_settings( 'auth_redirect_type', 'storeengine' );
		if ( 'storeengine' !== $auth_redirect_type ) {
			wp_safe_redirect( storeengine_login_url( Helper::get_dashboard_url() ) );
			die();
		}
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	// When default permalinks are enabled, redirect shop page to post type archive url.
	if ( ! empty( $_GET['page_id'] ) && '' === get_option( 'permalink_structure' ) && Helper::get_settings( 'shop_page' ) === absint( $_GET['page_id'] ) && get_post_type_archive_link( Helper::PRODUCT_POST_TYPE ) ) {
		wp_safe_redirect( get_post_type_archive_link( Helper::PRODUCT_POST_TYPE ) );
		exit;
	}

	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// When on the checkout with an empty cart, redirect to cart page.
	if (
		is_page( Helper::get_settings( 'checkout_page' ) ) &&
		Helper::get_settings( 'checkout_page' ) !== Helper::get_settings( 'cart_page' ) &&
		Helper::cart()->is_cart_empty() &&
		empty( $wp->query_vars['order-pay'] ) &&
		! isset( $wp->query_vars['order-received'] ) &&
		! isset( $wp->query_vars['order_pay'] ) &&
		! is_customize_preview() &&
		apply_filters( 'storeengine/checkout_redirect_empty_cart', true )
	) {
		wp_safe_redirect( Helper::get_page_permalink( 'cart_page' ) );
		exit;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	// Logout endpoint under My Account page. Logging out requires a valid nonce.
	if ( Helper::is_endpoint( 'customer-logout' ) ) {
		if ( ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'customer-logout' ) ) {
			wp_logout();
			if ( ! empty( $_REQUEST['redirect_to'] ) ) {
				$redirect_to = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
			} else {
				$redirect_to = storeengine_get_logout_redirect_url();
			}

			wp_safe_redirect( wp_validate_redirect( $redirect_to ) );
			exit;
		}

		/** @noinspection HtmlUnknownTarget */
		wp_die(
		/* translators: %s: logout url */
			sprintf( wp_kses_post( __( 'Are you sure you want to log out? <a href="%s">Confirm and log out</a>', 'storeengine' ) ), esc_url( storeengine_logout_url() ) ),
			esc_html__( 'Are you sure you want to log out?', 'storeengine' ),
			[ 'back_link' => true ]
		);
	}

	// If user landed from another page with redirect-to param.
	if ( is_user_logged_in() && ! empty( $_REQUEST['redirect_to'] ) ) {
		$redirect_to = wp_validate_redirect( esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) );
		if ( $redirect_to ) {
			wp_safe_redirect( $redirect_to );
		} else {
			wp_safe_redirect( remove_query_arg( 'redirect_to' ) );
		}

		exit;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// Trigger 404 if trying to access an endpoint on wrong page.
	if ( Helper::is_endpoint() && ! Helper::is_dashboard() && ! Helper::is_checkout() && apply_filters( 'storeengine/dashboard/endpoint_page_not_found', true ) ) {
		$wp_query->set_404();
		status_header( 404 );
		include get_query_template( '404' );
		exit;
	}

	// Redirect to the product page if we have a single product.
	if ( is_search() && is_post_type_archive( Helper::PRODUCT_POST_TYPE ) && apply_filters( 'storeengine/redirect/single_search_result', true ) && 1 === absint( $wp_query->found_posts ) ) {
		$product = storeengine_get_product( $wp_query->post->ID );
		if ( $product && $product->is_visible() ) {
			wp_safe_redirect( get_permalink( $product->get_id() ) );
			exit;
		}
	}
}

/**
 * SE Login URL
 *
 * Do not hook this function into login_url ever.
 *
 * @param string $redirect Path to redirect to on log in.
 * @param bool $force_reauth Whether to force reauthorization, even if a cookie is present.
 *                           Default false.
 *
 * @return string The login URL. Not HTML-encoded.
 */
function storeengine_login_url( string $redirect = '', bool $force_reauth = false ): string {
	$auth_redirect_type = Helper::get_settings( 'auth_redirect_type', 'storeengine' );

	$login_url = wp_login_url( $redirect, $force_reauth );

	if ( 'default' === $auth_redirect_type || is_admin() ) {
		return wp_login_url( $redirect, $force_reauth );
	}

	if ( 'custom' === $auth_redirect_type && Helper::get_settings( 'auth_redirect_url' ) ) {
		$login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), Helper::get_settings( 'auth_redirect_url' ) );
	} elseif ( 'storeengine' === $auth_redirect_type && Helper::get_settings( 'dashboard_page' ) ) {
		$login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), Helper::get_dashboard_url() );
	}

	if ( $force_reauth ) {
		$login_url = add_query_arg( 'reauth', '1', $login_url );
	}

	return apply_filters( 'storeengine/login_url', $login_url, $redirect, $force_reauth );
}

/**
 * Login URL tailored for the checkout "email already registered" flow.
 *
 * Returns the shopper back to checkout after a successful login and pre-fills
 * the email (via the `se_email` query arg the login template reads) so they
 * don't have to retype it. Shared by BOTH the inline contact pre-check and the
 * Place-Order 409, so the "Log in to continue" call-to-action is identical on
 * every surface that can surface it.
 *
 * @param string $email    Email to pre-fill on the login form.
 * @param string $redirect Where to send the shopper after login. Defaults to
 *                         the current checkout URL.
 *
 * @return string
 */
function storeengine_checkout_login_url( string $email = '', string $redirect = '' ): string {
	if ( '' === $redirect ) {
		$redirect = Helper::get_checkout_url();
	}

	$url = storeengine_login_url( $redirect );

	// add_query_arg() url-encodes the value itself — pass the raw address.
	if ( '' !== $email && is_email( $email ) ) {
		$url = add_query_arg( 'se_email', $email, $url );
	}

	return apply_filters( 'storeengine/checkout/contact_login_url', $url, $email, $redirect );
}

/**
 * Prevent any user who cannot 'edit_posts' (subscribers, customers etc.) from seeing the admin bar.
 *
 * @param bool $show_admin_bar If site should display admin bar.
 *
 * @return bool
 */
function storeengine_disable_admin_bar( bool $show_admin_bar ): bool {
	/**
	 * Controls whether the StoreEngine admin bar should be disabled.
	 *
	 * @param bool $enabled
	 */
	if ( apply_filters( 'storeengine/disable_admin_bar', true ) && ! ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_storeengine' ) ) ) {
		$show_admin_bar = false;
	}

	return $show_admin_bar;
}

/**
 * Initialize global product for current request.
 * This setup the global before the loop for current page only.
 *
 * @return void
 */
function storeengine_init_global_product_early() {
	if ( empty( $GLOBALS['post'] ) ) {
		return;
	}

	if ( empty( $GLOBALS['post']->post_type ) || $GLOBALS['post']->post_type !== 'storeengine_product' ) {
		return;
	}

	$GLOBALS['product'] = storeengine_get_product( $GLOBALS['post']->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
}

/**
 * Initialize global product inside the loop.
 *
 * @param $post
 *
 * @return false|SimpleProduct|VariableProduct|void
 */
function storeengine_initialize_product_data( $post ) {
	if ( is_int( $post ) ) {
		$post = get_post( $post );
	}

	if ( empty( $post->post_type ) || $post->post_type !== 'storeengine_product' ) {
		return;
	}

	$GLOBALS['the_original_product'] = $GLOBALS['product'] ?? null; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Compat global name mirroring the $product loop global preserved above.

	$GLOBALS['product'] = storeengine_get_product( $post->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

	return $GLOBALS['product'];
}

function storeengine_archive_title_parts( array $title ): array {
	global $wp_query;
	if ( Helper::is_shop() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only archive sort label derived from the public query var; no state change.
		if ( ! empty( $_REQUEST['orderby'] ) && $wp_query->get( 'orderby' ) && $wp_query->get( 'order' ) ) {
			$sort = strtolower( $wp_query->get( 'orderby' ) . ':' . $wp_query->get( 'order' ) );
			switch ( $sort ) {
				case 'post_title:asc':
					$label = __( 'Alphabetical (A–Z)', 'storeengine' );
					break;
				case 'post_title:desc':
					$label = __( 'Alphabetical (Z–A)', 'storeengine' );
					break;

				case 'publish_date:desc':
					$label = __( 'Newest first', 'storeengine' );
					break;
				case 'publish_date:asc':
					$label = __( 'Oldest first', 'storeengine' );
					break;

				case 'modified:desc':
					$label = __( 'Recently updated', 'storeengine' );
					break;
				case 'modified:asc':
					$label = __( 'Least recently updated', 'storeengine' );
					break;

				case 'menu_order:asc':
					$label = __( 'Custom order (ascending)', 'storeengine' );
					break;
				case 'menu_order:desc':
					$label = __( 'Custom order (descending)', 'storeengine' );
					break;

				case 'id:asc':
					$label = __( 'ID (ascending)', 'storeengine' );
					break;
				case 'id:desc':
					$label = __( 'ID (descending)', 'storeengine' );
					break;
				default:
					$label = str_ends_with( $sort, ':desc' ) ? __( 'Sort (descending)', 'storeengine' ) : __( 'Sort (ascending)', 'storeengine' );
					break;
			}

			if ( $label ) {
				$page = $title['page'] ?? null;
				$site = $title['site'] ?? null;
				unset( $title['page'], $title['site'] );

				$title['sort'] = $label;

				if ( $page ) {
					$title['page'] = $page;
				}
				if ( $site ) {
					$title['site'] = $site;
				}
			}
		}
	}

	return $title;
}

add_filter( 'document_title_parts', 'storeengine_archive_title_parts' );

/**
 * @param int $id
 *
 * @return AbstractOrder|Order|WP_Error
 * @throws StoreEngineException
 */
function storeengine_get_order( int $id ) {
	$order = Helper::get_order( $id );
	if ( is_wp_error( $order ) ) {
		throw StoreEngineException::from_wp_error( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	return $order;
}

function storeengine_cart(): Cart {
	return StoreEngine::init()->get_cart();
}

if ( ! function_exists( 'storeengine_get_header' ) ) {
	function storeengine_get_header( $header_name = 'product' ) {
		if ( Helper::is_fse_theme() ) {
			?>
			<!doctype html>
			<html <?php language_attributes(); ?>>
			<head>
				<meta charset="<?php bloginfo( 'charset' ); ?>">
				<?php wp_head(); ?>
			</head>

			<body <?php body_class(); ?>>
			<?php wp_body_open(); ?>
			<div class="wp-site-blocks">
			<?php
			if ( apply_filters( 'storeengine/templates/is_allow_block_theme_header', true ) ) :
				?>
				<header class="wp-block-template-part site-header">
					<?php block_header_area(); ?>
				</header>
			<?php
			endif;
			?>
			<?php
		} else {
			get_header( $header_name );
		}
	}
}

if ( ! function_exists( 'storeengine_get_footer' ) ) {
	function storeengine_get_footer( $footer_name = 'product' ) {
		if ( Helper::is_fse_theme() ) {
			if ( apply_filters( 'storeengine/templates/is_allow_block_theme_footer', true ) ) :
				?>
				<footer class="wp-block-template-part site-footer">
					<?php block_footer_area(); ?>
				</footer>
			<?php endif; ?>
			</div>
			<?php wp_footer(); ?>
			</body>
			</html>
		<?php } else {
			get_footer( $footer_name );
		}
	}
}

if ( ! function_exists( 'storeengine_single_product_header' ) ) {
	function storeengine_single_product_header() {
		Template::get_template(
			'single-product/header.php',
		);
	}
}

if ( ! function_exists( 'storeengine_product_bundle_info' ) ) {
	function storeengine_product_bundle_info( $bundles = null ) {
		global $product;
		if ( ! $bundles && $product && $product->is_type( 'bundled' ) && 'storeengine/templates/single-product/header_right_content' === current_action() ) {
			$bundles = $product->get_bundles();
		}

		if ( empty( $bundles ) || ! is_array( $bundles ) ) {
			return;
		}

		Template::get_template( 'global/bundle-info.php', [ 'bundles' => $bundles ] );
	}
}

if ( ! function_exists( 'storeengine_single_product_add_to_cart' ) ) {
	function storeengine_single_product_add_to_cart() {
		Template::get_template( 'single-product/add-to-cart.php' );
	}
}

if ( ! function_exists( 'storeengine_single_product_footer' ) ) {
	function storeengine_single_product_footer() {
		Template::get_template(
			'single-product/footer.php',
		);
	}
}

if ( ! function_exists( 'storeengine_single_product_description' ) ) {
	function storeengine_single_product_description() {
		Template::get_template(
			'single-product/description.php',
		);
	}
}

if ( ! function_exists( 'storeengine_add_to_cart_form' ) ) {
	function storeengine_add_to_cart_form() {
		$cart_page_permalink = Helper::get_page_permalink( 'cart_page' );
		$has_view_cart       = true;
		Template::get_template(
			'single-product/add-to-cart.php',
			[
				'cart_page_permalink' => $cart_page_permalink,
				'has_view_cart'       => $has_view_cart,
			]
		);
	}
}

function storeengine_placeholder_image_src( $size = 'storeengine_thumbnail' ) {
	// @TODO allow admin to change placeholder image.
	// @TODO implement size args.

	return apply_filters( 'storeengine/placeholder/image_src', STOREENGINE_ASSETS_URI . 'images/thumbnail-placeholder.png', $size );
}

/**
 * Get the placeholder image.
 *
 * @param string $size
 * @param string|array $attr
 *
 * @return string
 *
 * Mirrors the standard option-seeding on install.
 *
 * @see add_image_size()
 */
function storeengine_placeholder_image( string $size = 'storeengine_thumbnail', $attr = '' ): string {
	// @TODO add thumbnail & single product image size
	// @TODO use wp_get_attachment_image

	$dimensions   = [
		'width'  => 600,
		'height' => 600,
	];
	$default_attr = [
		'class' => 'storeengine-placeholder wp-post-image',
		'alt'   => __( 'Placeholder', 'storeengine' ),
	];
	$attr         = wp_parse_args( $attr, $default_attr );
	$image        = storeengine_placeholder_image_src( $size );
	$hwstring     = image_hwstring( $dimensions['width'], $dimensions['height'] );
	$attributes   = [];

	foreach ( $attr as $name => $value ) {
		$attributes[] = esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
	}

	$image_html = '<img src="' . esc_url( $image ) . '" ' . $hwstring . implode( ' ', $attributes ) . '/>'; // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage

	return apply_filters( 'storeengine/placeholder/image', $image_html, $size, $dimensions );
}

function storeengine_has_product_thumbnail( ?int $product_id = null ): bool {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	return (bool) apply_filters( 'storeengine/has_post_thumbnail', has_post_thumbnail( $product_id ), $product_id );
}

function storeengine_has_product_gallery( ?int $product_id = null ): bool {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	// Show the carousel when there's more than one item, or any video (even a
	// single video still needs a player rather than the plain-image layout).
	$items     = storeengine_get_product_gallery_items( $product_id );
	$has_video = false;
	foreach ( $items as $item ) {
		if ( 'video' === $item['type'] ) {
			$has_video = true;
			break;
		}
	}
	$has_gallery = count( $items ) > 1 || $has_video;

	return apply_filters( 'storeengine/has_product_gallery', $has_gallery, $product_id );
}

/**
 * Resolve the single-product gallery layout (global setting): carousel |
 * stacked | grid. Defaults to carousel.
 */
function storeengine_get_product_gallery_layout(): string {
	$layout  = \StoreEngine\Utils\Helper::get_settings( 'single_product_gallery_layout', 'carousel' );
	$allowed = [ 'carousel', 'stacked', 'grid' ];
	if ( ! in_array( $layout, $allowed, true ) ) {
		$layout = 'carousel';
	}

	return apply_filters( 'storeengine/product/gallery_layout', $layout );
}

/**
 * Conditionally enqueue the custom (vanilla-JS, no jQuery/Flickity) product
 * gallery bundle — only when the product actually has gallery media, so the
 * carousel/stacked/grid + lightbox/zoom code loads on demand.
 *
 * @param int|null $product_id
 */
function storeengine_enqueue_product_gallery_assets( ?int $product_id = null ) {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}
	if ( ! $product_id || ! storeengine_has_product_gallery( $product_id ) ) {
		return;
	}

	$asset_file = STOREENGINE_ASSETS_DIR_PATH . 'build/product-gallery.' . STOREENGINE_VERSION . '.asset.php';
	$asset      = file_exists( $asset_file )
		? include $asset_file
		: [
			'dependencies' => [],
			'version'      => STOREENGINE_VERSION,
		];

	wp_enqueue_style(
		'storeengine-product-gallery',
		STOREENGINE_ASSETS_URI . 'build/product-gallery.css',
		[],
		$asset['version']
	);
	wp_enqueue_script(
		'storeengine-product-gallery',
		STOREENGINE_ASSETS_URI . 'build/product-gallery.' . STOREENGINE_VERSION . '.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);
	wp_localize_script( 'storeengine-product-gallery', 'StoreEngineGalleryL10n', [
		'close'     => __( 'Close', 'storeengine' ),
		'next'      => __( 'Next image', 'storeengine' ),
		'prev'      => __( 'Previous image', 'storeengine' ),
		'zoomIn'    => __( 'Zoom in', 'storeengine' ),
		'zoomOut'   => __( 'Zoom out', 'storeengine' ),
		'resetZoom' => __( 'Reset zoom', 'storeengine' ),
		'playVideo' => __( 'Play video', 'storeengine' ),
		/* translators: %1$s current index, %2$s total. */
		'counter'   => __( '%1$s of %2$s', 'storeengine' ),
	] );
}

function storeengine_get_product_image( $size = 'storeengine_thumbnail', $product_id = null, $attr = '', $placeholder = true ) {
	$image        = '';
	$thumbnail_id = 0;

	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	if ( $product_id ) {
		$thumbnail_id = (int) get_post_thumbnail_id( $product_id );
	}

	if ( ! $thumbnail_id ) {
		// Fallback to first item on the gallery.
		$ids = get_post_meta( $product_id, '_storeengine_product_gallery_ids', true );
		if ( $ids && is_array( $ids ) ) {
			$ids          = array_unique( array_filter( $ids ) );
			$thumbnail_id = reset( $ids );
		}
	}

	if ( $thumbnail_id ) {
		$image = wp_get_attachment_image( $thumbnail_id, $size, false, $attr );
	}

	if ( ! $image && $placeholder ) {
		$image = storeengine_placeholder_image( $size, $attr );
	}

	return apply_filters( 'storeengine/product/image_html', $image, $product_id, $size, $attr, $placeholder );
}

function storeengine_product_image( $size = 'storeengine_thumbnail', $product_id = null, $attr = '', $placeholder = true ) {
	// Output from wp image html.
	// wp_kses-post remove srcset, sizes etc attributes
	echo storeengine_get_product_image( $size, $product_id, $attr, $placeholder ); // phpcs:ignore
}

function storeengine_product_gallery( $product_id = null, $size = 'storeengine_thumbnail', $attr = '' ) {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	if ( $product_id ) {
		// @TODO allow admin to choose a featured image too..
		// @XXX backend allows admin to add same image multiple time.
		$ids = get_post_meta( $product_id, '_storeengine_product_gallery_ids', true );
		if ( ! is_array( $ids ) ) {
			$ids = [];
		}

		$ids = array_unique( array_filter( $ids ) );
		if ( ! Helper::is_product() && count( $ids ) > 1 ) {
			$ids = [ reset( $ids ) ];
			$ids = array_filter( $ids );
		}

		foreach ( $ids as $id ) {
			$image = wp_get_attachment_image_src( $id, $size );
			if ( ! $image ) {
				continue;
			}

			printf(
				'<span class="carousel-cell">%1$s</span>',
				wp_get_attachment_image( $id, $size, false, $attr )
			);
		}
	}
}

/**
 * Normalized list of gallery videos for a product.
 *
 * @param int|null $product_id
 *
 * @return array<int, array{url:string, poster_id:int}>
 */
function storeengine_get_product_gallery_videos( ?int $product_id = null ): array {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	$videos = $product_id ? get_post_meta( $product_id, '_storeengine_product_gallery_videos', true ) : [];
	if ( ! is_array( $videos ) ) {
		$videos = [];
	}

	$out = [];
	foreach ( $videos as $video ) {
		$url = is_array( $video ) ? ( $video['url'] ?? '' ) : (string) $video;
		$url = trim( (string) $url );
		if ( '' === $url ) {
			continue;
		}
		$out[] = [
			'url'       => $url,
			'poster_id' => is_array( $video ) ? absint( $video['poster_id'] ?? 0 ) : 0,
		];
	}

	return apply_filters( 'storeengine/product/gallery_videos', $out, $product_id );
}

/**
 * Extract a YouTube video id from common URL shapes (watch, youtu.be, embed,
 * shorts). Returns '' when the url is not a recognizable YouTube link.
 */
function storeengine_extract_youtube_id( string $url ): string {
	if ( preg_match( '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m ) ) {
		return $m[1];
	}

	return '';
}

/**
 * Whether a URL points to a directly-playable video file (uploaded / media
 * library video, or any .mp4/.webm/... link).
 */
function storeengine_is_video_file_url( string $url ): bool {
	$path = wp_parse_url( $url, PHP_URL_PATH );
	$ext  = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';

	return in_array( $ext, [ 'mp4', 'm4v', 'webm', 'ogv', 'ogg', 'mov' ], true );
}

/**
 * Sanitise an oEmbed / iframe embed fragment down to a safe, iframe-allowing
 * allow-list with wp_kses (mirrors WordPress core's oEmbed sanitisation).
 * Strips scripts, event handlers and unsafe protocols while keeping provider
 * iframes intact.
 *
 * Used as defence-in-depth: the JS injects this HTML via innerHTML, so we do
 * not want to rely solely on core's `oembed_result` filter still being
 * attached (another plugin could remove it) when a lower-privileged editor /
 * marketplace vendor — who may lack `unfiltered_html` — supplied the video URL.
 *
 * @param string $html
 * @return string
 */
function storeengine_kses_gallery_embed( string $html ): string {
	$allowed = [
		'a'          => [
			'href'  => true,
			'title' => true,
		],
		'blockquote' => [ 'class' => true ],
		'iframe'     => [
			'src'             => true,
			'width'           => true,
			'height'          => true,
			'frameborder'     => true,
			'marginwidth'     => true,
			'marginheight'    => true,
			'scrolling'       => true,
			'title'           => true,
			'class'           => true,
			'name'            => true,
			'allow'           => true,
			'allowfullscreen' => true,
			'loading'         => true,
			'referrerpolicy'  => true,
			'sandbox'         => true,
			'style'           => true,
		],
	];

	return wp_kses( $html, $allowed );
}

/**
 * Resolve a video URL to sanitised, cached oEmbed HTML.
 *
 * `wp_oembed_get()` performs an outbound HTTP request (plus provider discovery
 * for non-whitelisted hosts) on every call, so the result is cached in a
 * transient keyed by URL + width to avoid a fetch on every product-page view.
 * The HTML is additionally run through {@see storeengine_kses_gallery_embed()}.
 *
 * @param string $url
 * @param int    $width
 * @return string Sanitised embed HTML, or '' if the URL isn't embeddable.
 */
function storeengine_get_gallery_oembed_html( string $url, int $width = 1200 ): string {
	$cache_key = 'se_gallery_oembed_' . md5( $url . '|' . $width );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return (string) $cached;
	}

	$embed = wp_oembed_get( $url, [ 'width' => $width ] );
	$html  = $embed ? storeengine_kses_gallery_embed( (string) $embed ) : '';

	// Cache successes for a day; cache misses only briefly so a transient
	// provider hiccup doesn't suppress a valid embed for long.
	set_transient( $cache_key, $html, $html ? DAY_IN_SECONDS : 5 * MINUTE_IN_SECONDS );

	return $html;
}

/**
 * Markup for a single gallery video's main (player) cell.
 *
 * @param array{url:string, poster_id:int} $video
 */
function storeengine_get_gallery_video_html( array $video ): string {
	$url = $video['url'];

	// Direct video file (uploaded / media library / any .mp4 etc.). Wrap in the
	// fixed-aspect container so the carousel cell has a stable height at init
	// (a bare <video> reports height 0 until metadata loads and collapses the
	// Flickity cell).
	if ( storeengine_is_video_file_url( $url ) ) {
		$poster = ! empty( $video['poster_id'] ) ? wp_get_attachment_image_url( (int) $video['poster_id'], 'large' ) : '';

		return sprintf(
			'<div class="storeengine-gallery__video-embed"><video class="storeengine-gallery__video-player" controls preload="metadata" playsinline%1$s src="%2$s"></video></div>',
			$poster ? ' poster="' . esc_url( $poster ) . '"' : '',
			esc_url( $url )
		);
	}

	// Embeddable providers (YouTube, Vimeo, …). Cached + kses-sanitised.
	$embed = storeengine_get_gallery_oembed_html( $url, 900 );
	if ( $embed ) {
		return '<div class="storeengine-gallery__video-embed">' . $embed . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-sanitised in helper.
	}

	// Last resort: iframe the URL so at least something renders.
	return sprintf(
		'<div class="storeengine-gallery__video-embed"><iframe src="%1$s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy" title="%2$s"></iframe></div>',
		esc_url( $url ),
		esc_attr__( 'Product video', 'storeengine' )
	);
}

/**
 * Markup for a single gallery video's thumbnail-nav cell (poster image with a
 * play badge). Falls back to a derived YouTube thumbnail, then a plain badge.
 *
 * @param array{url:string, poster_id:int} $video
 */
function storeengine_get_gallery_video_poster_html( array $video ): string {
	$badge = '<span class="storeengine-gallery__play-badge" aria-hidden="true"></span>';

	if ( ! empty( $video['poster_id'] ) ) {
		$img = wp_get_attachment_image( (int) $video['poster_id'], 'storeengine_thumbnail' );
		if ( $img ) {
			return $img . $badge;
		}
	}

	$youtube_id = storeengine_extract_youtube_id( $video['url'] );
	if ( $youtube_id ) {
		return sprintf(
			'<img src="%1$s" alt="" loading="lazy" />%2$s',
			esc_url( 'https://img.youtube.com/vi/' . $youtube_id . '/hqdefault.jpg' ),
			$badge
		);
	}

	return '<span class="storeengine-gallery__video-thumb-placeholder">' . $badge . '</span>';
}

/**
 * Unified, ordered gallery items (images + videos) for a product.
 *
 * Prefers the `_storeengine_product_gallery` meta (source of truth for the
 * editor order). Falls back to the legacy featured image + gallery ids +
 * gallery videos so products saved before the unified gallery still render.
 *
 * @param int|null $product_id
 *
 * @return array<int, array{type:string, id?:int, url?:string, poster_id?:int}>
 */
function storeengine_get_product_gallery_items( ?int $product_id = null ): array {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}
	if ( ! $product_id ) {
		return [];
	}

	$items       = [];
	$seen_images = [];

	$unified = get_post_meta( $product_id, '_storeengine_product_gallery', true );

	if ( is_array( $unified ) && ! empty( $unified ) ) {
		foreach ( $unified as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = $item['type'] ?? '';
			if ( 'image' === $type && ! empty( $item['id'] ) ) {
				$id = (int) $item['id'];
				if ( isset( $seen_images[ $id ] ) ) {
					continue;
				}
				$seen_images[ $id ] = true;
				$items[]            = [
					'type' => 'image',
					'id'   => $id,
				];
			} elseif ( 'video' === $type && ! empty( $item['url'] ) ) {
				$items[] = [
					'type'      => 'video',
					'url'       => (string) $item['url'],
					'poster_id' => absint( $item['poster_id'] ?? 0 ),
				];
			}
		}
	} else {
		// Legacy fallback: featured image, then gallery image ids, then videos.
		$featured = (int) get_post_thumbnail_id( $product_id );
		if ( $featured ) {
			$items[]                  = [
				'type' => 'image',
				'id'   => $featured,
			];
			$seen_images[ $featured ] = true;
		}

		$ids = get_post_meta( $product_id, '_storeengine_product_gallery_ids', true );
		if ( is_array( $ids ) ) {
			foreach ( array_unique( array_filter( $ids ) ) as $id ) {
				$id = (int) $id;
				if ( isset( $seen_images[ $id ] ) ) {
					continue;
				}
				$seen_images[ $id ] = true;
				$items[]            = [
					'type' => 'image',
					'id'   => $id,
				];
			}
		}

		foreach ( storeengine_get_product_gallery_videos( $product_id ) as $video ) {
			$items[] = [
				'type'      => 'video',
				'url'       => $video['url'],
				'poster_id' => $video['poster_id'],
			];
		}
	}

	return apply_filters( 'storeengine/product/gallery_items', $items, $product_id );
}

/**
 * Build the markup for a single gallery item (image or video) consumed by the
 * custom vanilla-JS gallery. Everything the JS needs (full-size url, poster,
 * embed html) rides on data-* attributes so no separate JSON blob is needed.
 *
 * @param array $item  { type, id | url, poster_id }
 * @param int   $index Zero-based position.
 *
 * @return string
 */
function storeengine_get_product_gallery_item_html( array $item, int $index ): string {
	if ( 'image' === $item['type'] ) {
		$id     = (int) $item['id'];
		$thumb  = wp_get_attachment_image_url( $id, 'storeengine_thumbnail' );
		$medium = wp_get_attachment_image_url( $id, 'large' );
		$full   = wp_get_attachment_image_url( $id, 'full' );
		$medium = $medium ?: ( $full ?: $thumb );
		$full   = $full ?: $medium;
		$thumb  = $thumb ?: $medium;
		if ( ! $medium ) {
			return '';
		}
		$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );

		return sprintf(
			'<figure class="storeengine-gallery__item" data-index="%1$d" data-type="image" data-full="%2$s" data-thumb="%3$s"><img class="storeengine-gallery__img" src="%4$s" alt="%5$s" loading="%6$s" decoding="async" /></figure>',
			$index,
			esc_url( $full ),
			esc_url( $thumb ),
			esc_url( $medium ),
			esc_attr( $alt ),
			0 === $index ? 'eager' : 'lazy'
		);
	}

	// Video item.
	$url       = (string) $item['url'];
	$poster_id = (int) ( $item['poster_id'] ?? 0 );
	$youtube   = storeengine_extract_youtube_id( $url );
	$is_file   = storeengine_is_video_file_url( $url );
	$provider  = $is_file ? 'file' : ( $youtube ? 'youtube' : ( false !== stripos( $url, 'vimeo.com' ) ? 'vimeo' : 'embed' ) );

	$poster = '';
	$thumb  = '';
	if ( $poster_id ) {
		$poster = wp_get_attachment_image_url( $poster_id, 'large' );
		$thumb  = wp_get_attachment_image_url( $poster_id, 'storeengine_thumbnail' );
	} elseif ( $youtube ) {
		$poster = 'https://img.youtube.com/vi/' . $youtube . '/hqdefault.jpg';
		$thumb  = 'https://img.youtube.com/vi/' . $youtube . '/mqdefault.jpg';
	}

	// Non-file providers ship their embed html (base64 in a data attr) so the JS
	// can inject it on demand without another network round-trip.
	$embed_attr = '';
	if ( ! $is_file ) {
		$embed = storeengine_get_gallery_oembed_html( $url, 1200 );
		if ( ! $embed ) {
			$embed = sprintf(
				'<iframe src="%s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>',
				esc_url( $url )
			);
		}
		$embed_attr = ' data-embed="' . esc_attr( base64_encode( $embed ) ) . '"'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport only.
	}

	if ( $thumb ) {
		$thumb_html = sprintf( '<img class="storeengine-gallery__img" src="%s" alt="" loading="lazy" decoding="async" />', esc_url( $thumb ) );
	} elseif ( $is_file ) {
		$thumb_html = sprintf( '<video class="storeengine-gallery__img" src="%s#t=0.1" muted preload="metadata" playsinline></video>', esc_url( $url ) );
	} else {
		$thumb_html = '<span class="storeengine-gallery__video-empty"></span>';
	}

	return sprintf(
		'<figure class="storeengine-gallery__item storeengine-gallery__item--video" data-index="%1$d" data-type="video" data-provider="%2$s" data-url="%3$s"%4$s data-poster="%5$s">%6$s<span class="storeengine-gallery__play" aria-hidden="true"></span></figure>',
		$index,
		esc_attr( $provider ),
		esc_url( $url ),
		$embed_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above.
		esc_url( $poster ),
		$thumb_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* above.
	);
}

/**
 * Render the full custom gallery root (SSR markup the vanilla JS enhances).
 * Falls back to a single image when there is no gallery.
 *
 * @param int|null $product_id
 */
function storeengine_render_product_gallery( $product_id = null ) {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	$items = storeengine_get_product_gallery_items( $product_id );

	if ( empty( $items ) ) {
		echo '<div class="storeengine-gallery-root storeengine-gallery-root--single">';
		storeengine_product_image( 'large', $product_id );
		echo '</div>';
		return;
	}

	$layout = storeengine_get_product_gallery_layout();
	$html   = '';
	foreach ( $items as $i => $item ) {
		$html .= storeengine_get_product_gallery_item_html( $item, (int) $i );
	}

	printf(
		'<div class="storeengine-gallery-root storeengine-gallery-root--%1$s" data-se-gallery data-layout="%1$s"><div class="storeengine-gallery__items">%2$s</div></div>',
		esc_attr( $layout ),
		$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each item built with esc_* in storeengine_get_product_gallery_item_html().
	);
}

/**
 * Output the unified gallery carousel cells (images + videos, in order) for the
 * given carousel `$context` ('main' player cells or 'nav' thumbnail cells).
 *
 * @param int|null $product_id
 * @param string   $context
 * @param string   $size
 * @param string   $attr
 */
function storeengine_product_gallery_render( $product_id = null, string $context = 'main', string $size = 'storeengine_thumbnail', string $attr = '' ) {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	foreach ( storeengine_get_product_gallery_items( $product_id ) as $item ) {
		if ( 'image' === $item['type'] ) {
			$image = wp_get_attachment_image( $item['id'], $size, false, $attr );
			if ( ! $image ) {
				continue;
			}
			printf( '<span class="carousel-cell">%1$s</span>', $image ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'nav' === $context ) {
			printf(
				'<span class="carousel-cell storeengine-gallery__video-nav">%1$s</span>',
				storeengine_get_gallery_video_poster_html( $item ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		} else {
			printf(
				'<span class="carousel-cell storeengine-gallery__video-cell">%1$s</span>',
				storeengine_get_gallery_video_html( $item ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}
	}
}

/**
 * Output gallery-video carousel cells. `$context` is 'main' (player) or 'nav'
 * (poster thumbnail). Videos render only on the single-product page.
 *
 * @param int|null $product_id
 * @param string   $context
 */
function storeengine_product_gallery_videos( $product_id = null, string $context = 'main' ) {
	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	// Videos are a single-product experience; skip in archive/loop where only
	// the featured image is shown.
	if ( ! Helper::is_product() ) {
		return;
	}

	foreach ( storeengine_get_product_gallery_videos( $product_id ) as $video ) {
		if ( 'nav' === $context ) {
			printf(
				'<span class="carousel-cell storeengine-gallery__video-nav">%1$s</span>',
				storeengine_get_gallery_video_poster_html( $video ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		} else {
			printf(
				'<span class="carousel-cell storeengine-gallery__video-cell">%1$s</span>',
				storeengine_get_gallery_video_html( $video ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}
	}
}

if ( ! function_exists( 'storeengine_price' ) ) {
	function storeengine_price() {
		Template::get_template(
			'single-product/price.php'
		);
	}
}

if ( ! function_exists( 'storeengine_single_view_cart' ) ) {
	function storeengine_single_view_cart() {
		$cart_item = storeengine_cart()->get_cart_items_by_product( get_the_ID() );

		if ( $cart_item ) {
			$total_quantity_in_cart = array_sum( array_column( $cart_item, 'quantity' ) );
			$num_prices_in_cart     = count( $cart_item );
			Template::get_template( 'notice/view-cart.php', [
				'total_quantity_in_cart' => $total_quantity_in_cart,
				'num_prices_in_cart'     => $num_prices_in_cart,
			] );
		}
	}
}

if ( ! function_exists( 'storeengine_product_loop_header' ) ) {
	function storeengine_product_loop_header() {
		storeengine_enqueue_product_archive_assets();
		Template::get_template(
			'loop/header.php',
		);
	}
}

// Cards are not only rendered server-side on the shop archive — Recently Viewed
// (product pages) and the wishlist page inject them after load, and those cards
// are built inside a REST request where wp_enqueue_style() can't reach the page
// that will show them. Enqueueing only from the card template therefore left
// injected cards with no Quick View styles and no `position: relative` on the
// card header. Register during the normal asset phase instead; the function is
// already settings-gated and idempotent, so this is cheap.
add_action( 'wp_enqueue_scripts', 'storeengine_enqueue_product_archive_assets' );

if ( ! function_exists( 'storeengine_product_archive_features' ) ) {
	/**
	 * Which shop-card enhancements are enabled (card carousel, variant swatches,
	 * quick view).
	 *
	 * @return array{carousel:bool, swatches:bool, quick_view:bool}
	 */
	function storeengine_product_archive_features(): array {
		return [
			'carousel'   => (bool) \StoreEngine\Utils\Helper::get_settings( 'product_archive_card_carousel', false ),
			'swatches'   => (bool) \StoreEngine\Utils\Helper::get_settings( 'product_archive_card_swatches', false ),
			'quick_view' => (bool) \StoreEngine\Utils\Helper::get_settings( 'product_archive_quick_view', false ),
		];
	}
}

if ( ! function_exists( 'storeengine_enqueue_product_archive_assets' ) ) {
	/**
	 * Enqueue the archive-card bundle (and, for quick view, the gallery lib) —
	 * once per request, only when at least one card enhancement is on.
	 */
	function storeengine_enqueue_product_archive_assets() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$features = storeengine_product_archive_features();
		if ( ! array_filter( $features ) ) {
			return;
		}
		$done = true;

		$asset_file = STOREENGINE_ASSETS_DIR_PATH . 'build/product-archive.' . STOREENGINE_VERSION . '.asset.php';
		$asset      = file_exists( $asset_file ) ? include $asset_file : [ 'dependencies' => [], 'version' => STOREENGINE_VERSION ];

		wp_enqueue_style( 'storeengine-product-archive', STOREENGINE_ASSETS_URI . 'build/product-archive.css', [], $asset['version'] );
		wp_enqueue_script( 'storeengine-product-archive', STOREENGINE_ASSETS_URI . 'build/product-archive.' . STOREENGINE_VERSION . '.js', $asset['dependencies'], $asset['version'], true );

		// Quick View reuses the single-product gallery lib inside the modal.
		if ( $features['quick_view'] ) {
			$g_asset_file = STOREENGINE_ASSETS_DIR_PATH . 'build/product-gallery.' . STOREENGINE_VERSION . '.asset.php';
			$g_asset      = file_exists( $g_asset_file ) ? include $g_asset_file : [ 'dependencies' => [], 'version' => STOREENGINE_VERSION ];
			wp_enqueue_style( 'storeengine-product-gallery', STOREENGINE_ASSETS_URI . 'build/product-gallery.css', [], $g_asset['version'] );
			wp_enqueue_script( 'storeengine-product-gallery', STOREENGINE_ASSETS_URI . 'build/product-gallery.' . STOREENGINE_VERSION . '.js', $g_asset['dependencies'], $g_asset['version'], true );
			wp_localize_script( 'storeengine-product-gallery', 'StoreEngineGalleryL10n', [
				'close'   => __( 'Close', 'storeengine' ),
				'next'    => __( 'Next image', 'storeengine' ),
				'prev'    => __( 'Previous image', 'storeengine' ),
				'counter' => __( '%1$s of %2$s', 'storeengine' ),
			] );
		}

		$qv_position = (string) \StoreEngine\Utils\Helper::get_settings( 'quick_view_position', 'center' );
		if ( ! in_array( $qv_position, [ 'center', 'left', 'right' ], true ) ) {
			$qv_position = 'center';
		}
		$qv_animation = (string) \StoreEngine\Utils\Helper::get_settings( 'quick_view_animation', 'fade' );
		if ( ! in_array( $qv_animation, [ 'fade', 'zoom', 'slide', 'none' ], true ) ) {
			$qv_animation = 'fade';
		}

		wp_localize_script( 'storeengine-product-archive', 'StoreEngineArchive', [
			'features'  => $features,
			'position'  => $qv_position,
			'animation' => $qv_animation,
			'restUrl'   => esc_url_raw( rest_url( 'storeengine/v1/products/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'i18n'      => [
				'quickView'    => __( 'Quick View', 'storeengine' ),
				'close'        => __( 'Close', 'storeengine' ),
				'loading'      => __( 'Loading…', 'storeengine' ),
				'error'        => __( 'Could not load the product.', 'storeengine' ),
				'prevProduct'  => __( 'Previous product', 'storeengine' ),
				'nextProduct'  => __( 'Next product', 'storeengine' ),
			],
		] );
	}
}

if ( ! function_exists( 'storeengine_render_sticky_add_to_cart' ) ) {
	/**
	 * Sticky mobile Add-to-Cart bar. Rendered in the footer on single product
	 * pages; the frontend script reveals it once the real Add-to-Cart button
	 * scrolls out of view and proxies clicks back to the real form (so the
	 * existing delegated cart handler + variation/price/qty state are reused).
	 */
	function storeengine_render_sticky_add_to_cart() {
		if ( ! \StoreEngine\Utils\Helper::is_product() || ! \StoreEngine\Utils\Helper::get_settings( 'sticky_add_to_cart', true ) ) {
			return;
		}

		global $product;
		if ( ! $product instanceof \StoreEngine\Classes\AbstractProduct ) {
			$product = \StoreEngine\Utils\Helper::get_product( get_the_ID() );
		}
		if ( ! $product ) {
			return;
		}

		// Already in the cart — the bar exists to nudge an add, so there is
		// nothing left for it to do. Matches on product id, so any variation
		// of a variable product counts. The frontend script does the same on a
		// successful add, for the case where the cart changes without a reload.
		if ( ! empty( storeengine_cart()->get_cart_items_by_product( $product->get_id() ) ) ) {
			return;
		}

		// Not get_the_post_thumbnail(): that returns '' for a product with no
		// featured image, which left a gap where the thumbnail should be. This
		// helper falls back to the first gallery image and then to the
		// placeholder, so the bar always has something to show.
		$thumb = storeengine_get_product_image( 'thumbnail', $product->get_id(), [
			'class' => 'storeengine-sticky-atc__img',
			'alt'   => '',
		] );
		?>
		<div class="storeengine-sticky-atc" data-storeengine-sticky-atc data-product-id="<?php echo (int) $product->get_id(); ?>" hidden>
			<?php // Inner wrapper so the bar can span the viewport while its content stays aligned with the page on wide screens. ?>
			<div class="storeengine-sticky-atc__inner">
				<div class="storeengine-sticky-atc__info">
					<?php if ( $thumb ) : ?>
						<span class="storeengine-sticky-atc__thumb"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated <img>. ?></span>
					<?php endif; ?>
					<span class="storeengine-sticky-atc__meta">
						<span class="storeengine-sticky-atc__name"><?php echo esc_html( $product->get_name() ); ?></span>
						<span class="storeengine-sticky-atc__price" data-storeengine-sticky-price></span>
					</span>
				</div>
				<button type="button" class="storeengine-btn storeengine-btn--preset-blue storeengine-sticky-atc__btn" data-storeengine-sticky-atc-btn>
					<?php esc_html_e( 'Add to Cart', 'storeengine' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
add_action( 'wp_footer', 'storeengine_render_sticky_add_to_cart' );

if ( ! function_exists( 'storeengine_render_recently_viewed' ) ) {
	/**
	 * "Recently viewed" strip on single product pages. The list of ids lives in
	 * the shopper's browser (localStorage); this only prints an empty container
	 * carrying the REST endpoint + nonce, which the frontend script fills.
	 */
	function storeengine_render_recently_viewed() {
		if ( ! \StoreEngine\Utils\Helper::is_product() || ! \StoreEngine\Utils\Helper::get_settings( 'enable_recently_viewed', true ) ) {
			return;
		}
		?>
		<section
			class="storeengine-recently-viewed storeengine-container"
			data-storeengine-recently-viewed
			data-rest="<?php echo esc_url( rest_url( 'storeengine/v1/products/recently-viewed' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-exclude="<?php echo (int) get_the_ID(); ?>"
			hidden
		>
			<div class="storeengine-product-single__content-item">
				<h2 class="storeengine-recently-viewed__title"><?php esc_html_e( 'Recently viewed', 'storeengine' ); ?></h2>
				<div class="storeengine-products storeengine-products--grid">
					<div class="storeengine-products__body">
						<div class="storeengine-row" data-storeengine-recently-viewed-grid></div>
					</div>
				</div>
			</div>
		</section>
		<?php
	}
}
// wp_footer (always fires, incl. block themes where after_main_content doesn't);
// the frontend script relocates the strip into the main content area.
add_action( 'wp_footer', 'storeengine_render_recently_viewed', 20 );

if ( ! function_exists( 'storeengine_render_size_guide_trigger' ) ) {
	/**
	 * "Size guide" trigger shown in the product summary (single product page and
	 * Quick View, since both fire header_right_content).
	 *
	 * Resolves the product's size chart from the admin-managed library (see
	 * {@see \StoreEngine\Classes\SizeChart}) and inlines it as JSON, so the modal
	 * opens instantly with no REST round-trip — and works inside Quick View,
	 * which is itself injected over AJAX.
	 *
	 * No chart matched means no trigger: a product with nothing to show should
	 * not advertise a size guide.
	 */
	function storeengine_render_size_guide_trigger() {
		if ( ! \StoreEngine\Utils\Helper::get_settings( 'enable_size_guide', false ) ) {
			return;
		}

		global $product;
		$product_id = ( $product && method_exists( $product, 'get_id' ) ) ? (int) $product->get_id() : (int) get_the_ID();

		$chart = $product_id ? \StoreEngine\Classes\SizeChart::get_product_chart( $product_id ) : [];

		if ( empty( $chart ) ) {
			return;
		}

		echo '<div class="storeengine-size-guide">';
		printf(
			'<button type="button" class="storeengine-size-guide-trigger" data-storeengine-size-guide><span class="storeengine-size-guide-trigger__icon" aria-hidden="true"></span>%s</button>',
			esc_html__( 'Size guide', 'storeengine' )
		);
		printf(
			'<script type="application/json" class="storeengine-size-guide-data">%s</script>',
			wp_json_encode( $chart, JSON_HEX_TAG | JSON_HEX_AMP ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded with tag/amp escaping.
		);
		echo '</div>';
	}
}
add_action( 'storeengine/templates/single-product/header_right_content', 'storeengine_render_size_guide_trigger', 35 );

/* -------------------------------------------------------------------------- *
 * Wishlist (core). Logged-in shoppers → user meta; guests → browser
 * localStorage (client side). Heart buttons on cards / product page / Quick
 * View, a [storeengine_wishlist] page and a [storeengine_wishlist_count] badge.
 * -------------------------------------------------------------------------- */
if ( ! function_exists( 'storeengine_wishlist_enabled' ) ) {
	function storeengine_wishlist_enabled(): bool {
		return (bool) \StoreEngine\Utils\Helper::get_settings( 'enable_wishlist', false );
	}
}

if ( ! function_exists( 'storeengine_get_user_wishlist' ) ) {
	function storeengine_get_user_wishlist( int $user_id = 0 ): array {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) {
			return [];
		}
		$ids = get_user_meta( $user_id, 'storeengine_wishlist', true );

		return is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : [];
	}
}

if ( ! function_exists( 'storeengine_set_user_wishlist' ) ) {
	function storeengine_set_user_wishlist( array $ids, int $user_id = 0 ): array {
		$user_id = $user_id ?: get_current_user_id();
		$ids     = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( $user_id ) {
			update_user_meta( $user_id, 'storeengine_wishlist', $ids );
		}

		return $ids;
	}
}

if ( ! function_exists( 'storeengine_wishlist_button' ) ) {
	/**
	 * Render a wishlist (heart) toggle for the current loop / product. The active
	 * state is applied client-side (per shopper), so the markup is state-neutral.
	 */
	function storeengine_wishlist_button() {
		if ( ! storeengine_wishlist_enabled() ) {
			return;
		}
		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return;
		}
		printf(
			'<button type="button" class="storeengine-wishlist-btn" data-storeengine-wishlist data-product-id="%1$d" aria-pressed="false" aria-label="%2$s" title="%2$s"><span class="storeengine-wishlist-btn__icon" aria-hidden="true"></span></button>',
			(int) $product_id,
			esc_attr__( 'Add to wishlist', 'storeengine' )
		);
	}
}
add_action( 'storeengine/templates/after_product_loop_header_inner', 'storeengine_wishlist_button', 15 );
add_action( 'storeengine/templates/single-product/header_right_content', 'storeengine_wishlist_button', 6 );

if ( ! function_exists( 'storeengine_wishlist_page_shortcode' ) ) {
	/**
	 * `[storeengine_wishlist]` — the wishlist page. Prints an empty grid carrying
	 * the cards REST endpoint; the frontend script fills it from the shopper's
	 * ids (account for logged-in, browser for guests).
	 */
	function storeengine_wishlist_page_shortcode(): string {
		if ( ! storeengine_wishlist_enabled() ) {
			return '';
		}

		return sprintf(
			'<div class="storeengine-wishlist-page" data-storeengine-wishlist-page data-cards="%1$s" data-nonce="%2$s">'
			. '<p class="storeengine-wishlist-page__empty" hidden>%3$s</p>'
			. '<div class="storeengine-products storeengine-products--grid"><div class="storeengine-products__body">'
			. '<div class="storeengine-row" data-storeengine-wishlist-grid></div></div></div></div>',
			esc_url( rest_url( 'storeengine/v1/wishlist/cards' ) ),
			esc_attr( wp_create_nonce( 'wp_rest' ) ),
			esc_html__( 'Your wishlist is empty.', 'storeengine' )
		);
	}
}
add_shortcode( 'storeengine_wishlist', 'storeengine_wishlist_page_shortcode' );

if ( ! function_exists( 'storeengine_wishlist_count_shortcode' ) ) {
	/** `[storeengine_wishlist_count]` — a live count badge (updated client-side). */
	function storeengine_wishlist_count_shortcode(): string {
		if ( ! storeengine_wishlist_enabled() ) {
			return '';
		}

		return '<span class="storeengine-wishlist-count" data-storeengine-wishlist-count>0</span>';
	}
}
add_shortcode( 'storeengine_wishlist_count', 'storeengine_wishlist_count_shortcode' );

/* -------------------------------------------------------------------------- *
 * Product compare (core). Same storage split as the wishlist above:
 * logged-in shoppers → user meta, guests → browser localStorage. Toggle
 * buttons on cards / product page / Quick View, a docked tray that appears once
 * something is selected, and a [storeengine_compare] page holding the
 * side-by-side table (built by \StoreEngine\Classes\ProductCompare).
 * -------------------------------------------------------------------------- */
if ( ! function_exists( 'storeengine_compare_enabled' ) ) {
	function storeengine_compare_enabled(): bool {
		return (bool) \StoreEngine\Utils\Helper::get_settings( 'enable_product_compare', false );
	}
}

if ( ! function_exists( 'storeengine_get_user_compare' ) ) {
	function storeengine_get_user_compare( int $user_id = 0 ): array {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) {
			return [];
		}
		$ids = get_user_meta( $user_id, 'storeengine_compare', true );

		return is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : [];
	}
}

if ( ! function_exists( 'storeengine_set_user_compare' ) ) {
	function storeengine_set_user_compare( array $ids, int $user_id = 0 ): array {
		$user_id = $user_id ?: get_current_user_id();
		$ids     = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		// Trim from the front, so the most recent pick always survives the cap.
		$max = \StoreEngine\Classes\ProductCompare::max();
		if ( count( $ids ) > $max ) {
			$ids = array_slice( $ids, -$max );
		}
		if ( $user_id ) {
			update_user_meta( $user_id, 'storeengine_compare', $ids );
		}

		return $ids;
	}
}

if ( ! function_exists( 'storeengine_compare_button' ) ) {
	/**
	 * Render a compare toggle for the current loop / product. Like the wishlist
	 * heart, the active state is per-shopper and applied client-side, so the
	 * server markup is state-neutral and stays cacheable.
	 */
	function storeengine_compare_button() {
		if ( ! storeengine_compare_enabled() ) {
			return;
		}
		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return;
		}
		printf(
			'<button type="button" class="storeengine-compare-btn" data-storeengine-compare data-product-id="%1$d" aria-pressed="false">'
			. '<span class="storeengine-compare-btn__icon" aria-hidden="true"></span>'
			. '<span class="storeengine-compare-btn__label">%2$s</span></button>',
			(int) $product_id,
			esc_html__( 'Compare', 'storeengine' )
		);
	}
}
// Card: floats over the thumbnail, directly under the wishlist heart. It must
// stay absolutely positioned (see the CSS) — the header is the positioning
// context for the Quick View overlay, so any in-flow content here would inflate
// it and push that button down onto the card body.
add_action( 'storeengine/templates/after_product_loop_header_inner', 'storeengine_compare_button', 16 );
add_action( 'storeengine/templates/single-product/header_right_content', 'storeengine_compare_button', 36 );

if ( ! function_exists( 'storeengine_render_compare_tray' ) ) {
	/**
	 * The docked compare tray. Server-rendered empty and hidden; the frontend
	 * script fills in the thumbnails and reveals it once something is selected.
	 *
	 * On wp_footer (like the recently-viewed strip) so it exists on every page,
	 * including block themes where after_main_content never fires.
	 */
	function storeengine_render_compare_tray() {
		if ( ! storeengine_compare_enabled() ) {
			return;
		}

		printf(
			'<div class="storeengine-compare-tray" data-storeengine-compare-tray hidden data-table="%1$s" data-nonce="%2$s">'
			. '<div class="storeengine-compare-tray__items" data-storeengine-compare-tray-items></div>'
			. '<div class="storeengine-compare-tray__actions">'
			. '<button type="button" class="storeengine-compare-tray__clear" data-storeengine-compare-clear>%3$s</button>'
			. '<button type="button" class="storeengine-btn storeengine-btn--preset-blue storeengine-compare-tray__cta" data-storeengine-compare-open>%4$s</button>'
			. '</div></div>',
			esc_url( rest_url( 'storeengine/v1/compare/table' ) ),
			esc_attr( wp_create_nonce( 'wp_rest' ) ),
			esc_html__( 'Clear', 'storeengine' ),
			esc_html__( 'Compare', 'storeengine' )
		);
	}
}
add_action( 'wp_footer', 'storeengine_render_compare_tray', 21 );

if ( ! function_exists( 'storeengine_compare_page_shortcode' ) ) {
	/**
	 * `[storeengine_compare]` — the comparison page. Prints an empty shell
	 * carrying the table REST endpoint; the frontend script fills it from the
	 * shopper's ids (account for logged-in, browser for guests).
	 */
	function storeengine_compare_page_shortcode(): string {
		if ( ! storeengine_compare_enabled() ) {
			return '';
		}

		return sprintf(
			'<div class="storeengine-compare-page" data-storeengine-compare-page data-table="%1$s" data-nonce="%2$s">'
			. '<p class="storeengine-compare-page__empty" hidden>%3$s</p>'
			. '<div data-storeengine-compare-table></div></div>',
			esc_url( rest_url( 'storeengine/v1/compare/table' ) ),
			esc_attr( wp_create_nonce( 'wp_rest' ) ),
			esc_html__( 'Pick a few products to compare and they will show up here.', 'storeengine' )
		);
	}
}
add_shortcode( 'storeengine_compare', 'storeengine_compare_page_shortcode' );

if ( ! function_exists( 'storeengine_compare_count_shortcode' ) ) {
	/** `[storeengine_compare_count]` — a live count badge (updated client-side). */
	function storeengine_compare_count_shortcode(): string {
		if ( ! storeengine_compare_enabled() ) {
			return '';
		}

		return '<span class="storeengine-compare-count" data-storeengine-compare-count>0</span>';
	}
}
add_shortcode( 'storeengine_compare_count', 'storeengine_compare_count_shortcode' );

/* -------------------------------------------------------------------------- *
 * Shop-card variant switcher (Shopify-style). Gated by the existing
 * `product_archive_card_swatches` setting. Renders color/image swatches +
 * text pills under each variable card; the archive script resolves the picked
 * variant, swaps the image, updates the price, and enables add-to-cart.
 * -------------------------------------------------------------------------- */
if ( ! function_exists( 'storeengine_card_swatches_enabled' ) ) {
	function storeengine_card_swatches_enabled(): bool {
		return (bool) \StoreEngine\Utils\Helper::get_settings( 'product_archive_card_swatches', false );
	}
}

if ( ! function_exists( 'storeengine_get_card_variant_model' ) ) {
	/**
	 * Build the per-card variant model for a variable product: selectable
	 * attributes (each option carrying its swatch color / image, else a text
	 * pill) and the full variation matrix (attributes → variation id, price_id,
	 * thumbnail-sized image, final price + stock). Returns [] when not usable.
	 *
	 * @param mixed $product
	 * @return array
	 */
	function storeengine_get_card_variant_model( $product ): array {
		if ( ! $product instanceof \StoreEngine\Classes\Product\VariableProduct || 'variable' !== $product->get_type() ) {
			return [];
		}

		$base = \StoreEngine\Assets::get_product_variations( $product );
		if ( empty( $base['variations'] ) || empty( $base['taxonomies'] ) ) {
			return [];
		}

		// Variation matrix with thumbnail image + final price html.
		$variations = [];
		foreach ( $product->get_available_variants() as $variant ) {
			$attributes = [];
			foreach ( $variant->get_attributes() as $attribute ) {
				$attributes[ $attribute->taxonomy ] = $attribute->slug;
			}
			$pricing_id = (int) $variant->get_pricing_id();
			$base_price = isset( $base['pricing'][ $pricing_id ] ) ? (float) $base['pricing'][ $pricing_id ] : 0;
			$final      = $base_price + (float) $variant->get_price();
			$thumb_id   = (int) $variant->get_featured_image();

			$variations[] = [
				'id'         => (int) $variant->get_id(),
				'price_id'   => $pricing_id,
				'attributes' => $attributes,
				'price'      => $final,
				'price_html' => \StoreEngine\Utils\Formatting::price( $final ),
				'image'      => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'storeengine_thumbnail' ) : '',
				'in_stock'   => method_exists( $variant, 'is_in_stock' ) ? (bool) $variant->is_in_stock() : true,
			];
		}

		// Selectable attributes (in taxonomy order) with per-term swatch meta.
		$attributes = [];
		foreach ( $base['taxonomies'] as $taxonomy ) {
			$slugs = [];
			foreach ( $variations as $variation ) {
				if ( ! empty( $variation['attributes'][ $taxonomy ] ) ) {
					$slugs[ $variation['attributes'][ $taxonomy ] ] = true;
				}
			}
			$slugs = array_keys( $slugs );
			if ( empty( $slugs ) ) {
				continue;
			}

			$terms = get_terms( [
				'taxonomy'   => $taxonomy,
				'slug'       => $slugs,
				'hide_empty' => false,
			] );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$options = [];
			foreach ( $terms as $term ) {
				$color   = (string) get_term_meta( $term->term_id, '_storeengine_swatch_color', true );
				$image_id = (int) get_term_meta( $term->term_id, '_storeengine_swatch_image', true );
				$options[] = [
					'slug'  => $term->slug,
					'name'  => $term->name,
					'color' => $color,
					'image' => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'storeengine_thumbnail' ) : '',
				];
			}

			$tax_obj = get_taxonomy( $taxonomy );
			$attributes[] = [
				'taxonomy' => $taxonomy,
				'label'    => $tax_obj ? $tax_obj->labels->singular_name : ucwords( str_replace( [ 'se_pa_', '-', '_' ], [ '', ' ', ' ' ], $taxonomy ) ),
				'options'  => $options,
			];
		}

		if ( empty( $attributes ) ) {
			return [];
		}

		// Default price display (range across variations, or a single price).
		$prices     = array_column( $variations, 'price' );
		$price_html = ( $prices && min( $prices ) !== max( $prices ) )
			? \StoreEngine\Utils\Formatting::format_price_range( max( $prices ), min( $prices ) )
			: \StoreEngine\Utils\Formatting::price( $prices ? (float) reset( $prices ) : 0 );

		return [
			'product_id' => (int) $product->get_id(),
			'attributes' => $attributes,
			'variations' => $variations,
			'price_html' => $price_html,
		];
	}
}

if ( ! function_exists( 'storeengine_product_has_card_variants' ) ) {
	/** Whether the card variant switcher should render for this product. */
	function storeengine_product_has_card_variants( $product ): bool {
		return storeengine_card_swatches_enabled() && ! empty( storeengine_get_card_variant_model( $product ) );
	}
}

if ( ! function_exists( 'storeengine_render_card_variant_switcher' ) ) {
	/**
	 * Render the swatch/pill strip + inline variation matrix under a variable
	 * product card (hooked to before_product_loop_footer_inner).
	 *
	 * This belongs in the card footer, not the header: the header is the media
	 * box and is the positioning context for the absolutely-placed Quick View /
	 * wishlist overlays, so any in-flow content there inflates it and pushes the
	 * bottom-anchored Quick View button down onto the swatches.
	 */
	function storeengine_render_card_variant_switcher() {
		if ( ! storeengine_card_swatches_enabled() ) {
			return;
		}
		global $product;
		$model = storeengine_get_card_variant_model( $product );
		if ( empty( $model ) ) {
			return;
		}

		echo '<div class="storeengine-card-variants" data-storeengine-card-variants>';
		foreach ( $model['attributes'] as $attr ) {
			printf(
				'<div class="storeengine-card-variants__group" role="radiogroup" aria-label="%1$s" data-taxonomy="%2$s">',
				esc_attr( $attr['label'] ),
				esc_attr( $attr['taxonomy'] )
			);
			foreach ( $attr['options'] as $opt ) {
				$type   = $opt['color'] ? 'color' : ( $opt['image'] ? 'image' : 'text' );
				$style  = $opt['color'] ? sprintf( ' style="background-color:%s"', esc_attr( $opt['color'] ) ) : '';
				$inner  = $opt['image'] ? sprintf( '<img src="%s" alt="" loading="lazy" />', esc_url( $opt['image'] ) ) : ( 'text' === $type ? esc_html( $opt['name'] ) : '' );
				printf(
					'<button type="button" class="storeengine-card-variants__opt storeengine-card-variants__opt--%1$s" role="radio" aria-checked="false" data-slug="%2$s" title="%3$s" aria-label="%3$s"%4$s>%5$s</button>',
					esc_attr( $type ),
					esc_attr( $opt['slug'] ),
					esc_attr( $opt['name'] ),
					$style, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr'd above.
					$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url/esc_html above.
				);
			}
			echo '</div>';
		}
		printf(
			'<script type="application/json" class="storeengine-card-variants-data">%s</script>',
			wp_json_encode(
				[
					'product_id' => $model['product_id'],
					'variations' => $model['variations'],
				],
				JSON_HEX_TAG | JSON_HEX_AMP
			)
		);
		echo '</div>';
	}
}
add_action( 'storeengine/templates/before_product_loop_footer_inner', 'storeengine_render_card_variant_switcher', 10 );

if ( ! function_exists( 'storeengine_loop_card_enhancements' ) ) {
	/**
	 * Injects the card-carousel image data and the Quick View button into each
	 * shop card (hooked to after_product_loop_header_inner).
	 */
	function storeengine_loop_card_enhancements() {
		$features = storeengine_product_archive_features();
		if ( ! array_filter( $features ) ) {
			return;
		}
		$product_id = get_the_ID();

		if ( $features['carousel'] ) {
			$images = [];
			foreach ( storeengine_get_product_gallery_items( $product_id ) as $item ) {
				if ( 'image' !== $item['type'] ) {
					continue;
				}
				$url = wp_get_attachment_image_url( $item['id'], 'storeengine_thumbnail' );
				if ( $url ) {
					$images[] = $url;
				}
			}
			if ( count( $images ) > 1 ) {
				printf(
					'<script type="application/json" class="storeengine-card-gallery-data">%s</script>',
					wp_json_encode( $images ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
		}

		if ( $features['quick_view'] ) {
			printf(
				'<button type="button" class="storeengine-quick-view-btn" data-product-id="%1$d" aria-label="%2$s"><span class="storeengine-quick-view-btn__icon" aria-hidden="true"></span><span class="storeengine-quick-view-btn__label">%2$s</span></button>',
				(int) $product_id,
				esc_attr__( 'Quick View', 'storeengine' )
			);
		}
	}
}

if ( ! function_exists( 'storeengine_product_loop_content' ) ) {
	function storeengine_product_loop_content() {
		Template::get_template(
			'loop/content.php',
		);
	}
}

if ( ! function_exists( 'storeengine_product_loop_footer' ) ) {
	function storeengine_product_loop_footer() {
		Template::get_template(
			'loop/footer.php',
		);
	}
}

if ( ! function_exists( 'storeengine_product_loop_add_to_cart' ) ) {
	function storeengine_product_loop_add_to_cart() {
		Template::get_template( 'loop/add-to-cart.php' );
	}
}

if ( ! function_exists( 'storeengine_get_the_product_category' ) ) {
	function storeengine_get_the_product_category( $ID ) {
		return get_the_terms( $ID, Helper::PRODUCT_CATEGORY_TAXONOMY );
	}
}

if ( ! function_exists( 'storeengine_get_the_product_tag' ) ) {
	function storeengine_get_the_product_tag( $ID ) {
		return get_the_terms( $ID, Helper::PRODUCT_TAG_TAXONOMY );
	}
}

if ( ! function_exists( 'storeengine_single_categories' ) ) {
	function storeengine_single_categories() {
		Template::get_template(
			'single-product/categories.php',
		);
	}
}

if ( ! function_exists( 'storeengine_single_tag' ) ) {
	function storeengine_single_tag() {
		Template::get_template(
			'single-product/tag.php',
		);
	}
}

if ( ! function_exists( 'storeengine_global_products' ) ) {
	function storeengine_global_products() {
		Helper::get_template( 'global/products.php' );
	}
}

if ( ! function_exists( 'storeengine_no_products' ) ) {
	function storeengine_no_products() {
		Helper::get_template( 'archive/product-none.php' );
	}
}

if ( ! function_exists( 'storeengine_get_product' ) ) {
	function storeengine_get_product( $product_id ) {
		$product = ( new ProductFactory() )->get_product( $product_id );

		return $product->get_id() ? $product : false;
	}
}

if ( ! function_exists( 'storeengine_attributes_generator' ) ) {
	function storeengine_attributes_generator(): Attributes {
		return new Attributes();
	}
}

if ( ! function_exists( 'storeengine_get_checkout_url' ) ) {
	/**
	 * @return mixed|null
	 * @deprecated
	 */
	function storeengine_get_checkout_url() {
		return Helper::get_checkout_url();
	}
}

if ( ! function_exists( 'storeengine_get_account_menu_item' ) ) {
	/**
	 * @return array
	 * @deprecated
	 */
	function storeengine_get_account_menu_items(): array {
		return apply_filters(
			'storeengine/account_menu_items',
			[
				'index'             => __( 'Dashboard', 'storeengine' ),
				'orders'            => __( 'Orders', 'storeengine' ),
				'plans'             => __( 'Plans', 'storeengine' ),
				'edit-address'      => _n( 'Address', 'Addresses', ( 1 + (int) ShippingUtils::is_shipping_enabled() ), 'storeengine' ),
				'affiliate-partner' => __( 'Affiliate', 'storeengine' ),
				'payment-methods'   => __( 'Payment methods', 'storeengine' ),
				'edit-account'      => __( 'Account details', 'storeengine' ),
				'customer-logout'   => __( 'Log out', 'storeengine' ),
			]
		);
	}
}

if ( ! function_exists( 'storeengine_get_endpoint_url' ) ) {
	/**
	 * @param string $endpoint
	 * @param string|int|float $value
	 * @param string|false $permalink
	 *
	 * @return string
	 * @deprecated
	 * @use Helper::get_endpoint_url()
	 */
	function storeengine_get_endpoint_url( string $endpoint, $value = '', $permalink = '' ): string {
		return Helper::get_endpoint_url( $endpoint, $value, $permalink );
	}
}

function storeengine_get_logout_redirect_url(): string {
	return Helper::get_logout_redirect_url();
}

function storeengine_logout_url( string $redirect = '' ): string {
	return Helper::get_logout_url( $redirect );
}

/**
 * @param string $endpoint
 * @param string|int|float $value $value
 *
 * @return string|null
 */
function storeengine_get_dashboard_endpoint_url( string $endpoint, $value = '' ) {
	return Helper::get_account_endpoint_url( $endpoint, $value );
}

/**
 * @param string $message
 * @param 'primary'|'secondary'|'info'|'success'|'error'|'warning'|array{
 *      type?: 'primary'|'secondary'|'info'|'success'|'error'|'warning',
 *      title?: string,
 *      icon?: string,
 *      alt?: bool|string,
 *      dismissible?: bool,
 *      id?: string,
 *      buttons?: array<array{
 *          label: string,
 *          link?: string,
 *          classes?: string,
 *          target?: '_blank'|'_self'|'_parent'|'_top'|string,
 *          icon?:string,
 *          attrs?:array<array<string, string>>,
 *      }>
 *  } $args
 * @param string $name Optional. The name of the notice, provided for context to enable filtering
 *
 * @return void
 */
function storeengine_show_notice( string $message, $args = [], string $name = '' ) {
	if ( is_string( $args ) ) {
		$args = [ 'type' => $args ];
	}

	$args = wp_parse_args( $args, [
		'type'        => 'info',
		'title'       => '',
		'icon'        => 'info',
		'alt'         => false,
		'dismissible' => false,
		'id'          => wp_unique_id( 'storeengine-notice-' ),
		'buttons'     => [],
	] );

	if ( 'danger' === $args['type'] ) {
		$args['type'] = 'error';
	}
	if ( 'alert' === $args['type'] ) {
		$args['type'] = 'warning';
	}

	$valid_notice_types = [ 'primary', 'secondary', 'info', 'success', 'error', 'warning' ];

	if ( ! in_array( $args['type'], $valid_notice_types, true ) ) {
		$args['type'] = 'info';
	}

	$args['message'] = $message;

	if ( $name ) {
		/**
		 * Filters notice arguments.
		 *
		 * If the third parameter of the storeengine_show_notice() function is present then this filter is available.
		 * The third parameter, $name, is the name of the notice.
		 *
		 * @param array  $args Arguments provided to the notice.
		 * @param string $name The notice name.
		 */
		$args = apply_filters( "storeengine/notice/notice_{$name}", $args, $name );
	}

	Template::get_template( 'notice/notice.php', $args );
}

if ( ! function_exists( 'storeengine_frontend_dashboard_content' ) ) {
	function storeengine_frontend_dashboard_content() {
		global $wp;
		if ( $wp->query_vars && ! empty( $wp->query_vars['storeengine_dashboard_page'] ) ) {
			$value = sanitize_key( $wp->query_vars['storeengine_dashboard_page'] );

			if ( has_action( 'storeengine/frontend/dashboard_' . $value . '_endpoint' ) ) {
				/**
				 * Hook for dynamic dashboard page contents.
				 *
				 * @param string $value page slug.
				 */
				do_action( 'storeengine/frontend/dashboard_' . $value . '_endpoint', get_query_var( 'storeengine_dashboard_sub_page' ) );

				return;
			}
		}

		// No endpoint found? Default to dashboard.
		Template::get_template( 'frontend-dashboard/pages/dashboard.php' );
	}
}

if ( ! function_exists( 'storeengine_dashboard_orders' ) ) {
	function storeengine_dashboard_orders() {
		Template::get_template( 'frontend-dashboard/partials/orders.php' );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_orders_endpoint_content' ) ) {
	function storeengine_frontend_dashboard_orders_endpoint_content( $order_id ) {
		if ( $order_id ) {
			$order            = Helper::get_order( absint( $order_id ) );
			$invalid_statuses = [ OrderStatus::DRAFT, OrderStatus::AUTO_DRAFT, OrderStatus::TRASH ];

			if ( ! $order || is_wp_error( $order ) || ! $order->get_id() || $order->has_status( $invalid_statuses ) || get_current_user_id() !== $order->get_customer_id() ) {
				Template::get_template( 'frontend-dashboard/pages/partials/invalid-order.php', [ 'order' => $order_id ] );

				return;
			}

			Template::get_template( 'frontend-dashboard/pages/view-order.php', [ 'order' => $order ] );
		} else {
			Template::get_template( 'frontend-dashboard/pages/orders.php' );
		}
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_downloads_content' ) ) {
	function storeengine_frontend_dashboard_downloads_content() {
		Template::get_template( 'frontend-dashboard/pages/downloads.php' );
	}
}

if ( ! function_exists( 'storeengine_get_customer_reviewable_products' ) ) {
	/**
	 * Products the current customer has bought on completed orders, each with
	 * whether the customer has already reviewed it.
	 *
	 * @return array<int,array{product:object,reviewed:bool,comment:\WP_Comment|null}>
	 */
	function storeengine_get_customer_reviewable_products(): array {
		global $wpdb;

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return [];
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// Most recently purchased products first.
		$product_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT op.product_id
				FROM {$wpdb->prefix}storeengine_orders o
				JOIN {$wpdb->prefix}storeengine_order_product_lookup op ON o.id = op.order_id
				WHERE o.customer_id = %d AND o.status = 'completed'
				GROUP BY op.product_id
				ORDER BY MAX( o.date_created_gmt ) DESC, MAX( o.id ) DESC",
			$user_id
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$items = [];
		foreach ( array_unique( array_map( 'absint', (array) $product_ids ) ) as $product_id ) {
			$product = Helper::get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$existing = get_comments( [
				'user_id' => $user_id,
				'post_id' => $product_id,
				'type'    => 'storeengine_product',
				'number'  => 1,
				'status'  => 'all',
			] );

			$items[] = [
				'product'  => $product,
				'reviewed' => ! empty( $existing ),
				'comment'  => ! empty( $existing ) ? $existing[0] : null,
			];
		}

		return $items;
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_reviews_content' ) ) {
	function storeengine_frontend_dashboard_reviews_content() {
		Template::get_template( 'frontend-dashboard/pages/reviews.php', [
			'items' => storeengine_get_customer_reviewable_products(),
		] );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_payment_methods_content' ) ) {
	function storeengine_frontend_dashboard_payment_methods_content() {
		Template::get_template( 'frontend-dashboard/pages/payment-methods.php' );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_add_payment_method_content' ) ) {
	function storeengine_frontend_dashboard_add_payment_method_content() {
		Template::get_template( 'frontend-dashboard/pages/form-add-payment-method.php' );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_edit_address_content' ) ) {
	function storeengine_frontend_dashboard_edit_address_content( string $load_address = '' ) {
		if ( in_array( $load_address, [ 'billing', 'shipping' ], true ) ) {
			$load_address = sanitize_key( $load_address );
			$customer     = Helper::get_customer();
			$country      = 'billing' === $load_address ? $customer->get_billing_country() : $customer->get_shipping_country();

			if ( ! $country ) {
				$country = Countries::init()->get_base_country();
			}

			if ( 'billing' === $load_address ) {
				$allowed_countries = Countries::init()->get_allowed_countries();

				if ( ! array_key_exists( $country, $allowed_countries ) ) {
					$country = current( array_keys( $allowed_countries ) );
				}
			}

			if ( 'shipping' === $load_address ) {
				$allowed_countries = Countries::init()->get_shipping_countries();

				if ( ! array_key_exists( $country, $allowed_countries ) ) {
					$country = current( array_keys( $allowed_countries ) );
				}
			}

			$address = Countries::init()->get_address_fields( $country, $load_address . '_' );

			foreach ( $address as $key => $field ) {
				$method = 'get_' . $key;
				$value  = '';

				if ( method_exists( $customer, $method ) ) {
					$value = $customer->{'get_' . $key}();
				}

				if ( ! $value && ( 'billing_email' === $key || 'shipping_email' === $key ) ) {
					$value = $customer->get_email();
				}

				$address[ $key ]['value'] = apply_filters( 'storeengine/dashboard/edit_address/field_value', $value, $key, $load_address );
			}

			$address = apply_filters( 'storeengine/dashboard/edit_address/address_to_edit', $address, $load_address );

			Template::get_template( 'frontend-dashboard/pages/form-edit-address.php', [
				'load_address' => $load_address,
				'address'      => $address,
			] );
		} else {
			Template::get_template( 'frontend-dashboard/pages/edit-address.php', [
				'customer'  => Helper::get_customer(),
				'countries' => Countries::init()->get_countries(),
			] );
		}
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_payment_settings_content' ) ) {
	function storeengine_frontend_dashboard_payment_settings_content() {
		$user_id                    = get_current_user_id();
		$affiliate_settings         = \StoreEngine\Addons\Affiliate\Settings\Affiliate::get_settings_saved_data();
		$minimum_withdraw_amount    = isset( $affiliate_settings['minimum_withdraw_amount'] ) ? $affiliate_settings['minimum_withdraw_amount'] : 0;
		$is_enabled_paypal_withdraw = isset( $affiliate_settings['is_enabled_paypal_withdraw'] ) ? $affiliate_settings['is_enabled_paypal_withdraw'] : false;
		$is_enabled_echeck_withdraw = isset( $affiliate_settings['is_enabled_echeck_withdraw'] ) ? $affiliate_settings['is_enabled_echeck_withdraw'] : false;
		$is_enabled_bank_withdraw   = isset( $affiliate_settings['is_enabled_bank_withdraw'] ) ? $affiliate_settings['is_enabled_bank_withdraw'] : false;
		$selected_payment_method    = get_user_meta( $user_id, 'storeengine_affiliate_withdraw_method_type', true ) ?? '';

		Template::get_template(
			'frontend-dashboard/pages/payments.php',
			[
				'minimum_withdraw_amount'    => $minimum_withdraw_amount,
				'is_enabled_paypal_withdraw' => $is_enabled_paypal_withdraw,
				'is_enabled_echeck_withdraw' => $is_enabled_echeck_withdraw,
				'is_enabled_bank_withdraw'   => $is_enabled_bank_withdraw,
				'withdraw_method_type'       => $selected_payment_method,
				'current_user_id'            => $user_id,
			]
		);
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_edit_account_content' ) ) {
	function storeengine_frontend_dashboard_edit_account_content() {
		Template::get_template( 'frontend-dashboard/pages/edit-account.php', [ 'customer' => Helper::get_customer() ] );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_forgot_password_content' ) ) {
	function storeengine_frontend_dashboard_forgot_password_content() {
		// Already-authenticated visitors don't need this flow. We can't
		// silently redirect from inside this template hook (headers are
		// already sent by the dashboard page), so just show a short notice
		// with a link back to the dashboard.
		if ( is_user_logged_in() ) {
			echo '<div class="storeengine-login-form-wrapper">';
			echo '<p>' . esc_html__( 'You are already signed in.', 'storeengine' ) . ' ';
			echo '<a href="' . esc_url( Helper::get_dashboard_url() ) . '">' . esc_html__( 'Go to your dashboard', 'storeengine' ) . '</a>.';
			echo '</p></div>';
			return;
		}

		Template::get_template( 'frontend-dashboard/pages/forgot-password.php' );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_register_content' ) ) {
	function storeengine_frontend_dashboard_register_content() {
		// Already-signed-in visitors hit /dashboard/register/ by accident
		// (e.g. stale bookmark) — show a short notice rather than the form.
		// We can't redirect here because the dashboard page has already
		// started outputting.
		if ( is_user_logged_in() ) {
			echo '<div class="storeengine-login-form-wrapper">';
			echo '<p>' . esc_html__( 'You are already signed in.', 'storeengine' ) . ' ';
			echo '<a href="' . esc_url( Helper::get_dashboard_url() ) . '">' . esc_html__( 'Go to your dashboard', 'storeengine' ) . '</a>.';
			echo '</p></div>';
			return;
		}

		Template::get_template( 'frontend-dashboard/pages/register.php' );
	}
}

// Archive
if ( ! function_exists( 'storeengine_archive_product_header' ) ) {
	function storeengine_archive_product_header() {
		Template::get_template( 'archive/header.php' );
	}
}

// Archive Header Filter
if ( ! function_exists( 'storeengine_archive_header_filter' ) ) {
	function storeengine_archive_header_filter() {
		if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} else {
			$orderby = Helper::get_settings( 'product_archive_products_order', '' );
		}

		$sort_options = [
			'menu_order' => __( 'Sort By Menu Order', 'storeengine' ),
			'title'      => __( 'Sort By Product Name', 'storeengine' ),
			'date'       => __( 'Sort By Publish Date', 'storeengine' ),
			'modified'   => __( 'Sort By Modified Date', 'storeengine' ),
			'ID'         => __( 'Sort By ID', 'storeengine' ),
		];

		?>
		<div class="storeengine-products__filter">
			<form class="storeengine__header-ordering" method="get">
				<select name="orderby" class="storeengine__header-orderby"
						aria-label="<?php esc_attr_e( 'Sort Products', 'storeengine' ); ?>"
						onchange="this.form.submit()">
					<option
						value="" <?php selected( $orderby, '' ); ?>><?php esc_html_e( 'Default Sorting', 'storeengine' ); ?></option>
					<?php foreach ( $sort_options as $value => $label ) { ?>
						<option
							value="<?php echo esc_attr( $value ); ?>" <?php selected( $orderby, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php } ?>
				</select>
				<input type="hidden" name="paged" value="1">
			</form>
		</div>
		<?php
	}
}

// Product archive pagination
if ( ! function_exists( 'storeengine_product_pagination' ) ) {
	function storeengine_product_pagination() {
		Template::get_template( 'archive/pagination.php' );
	}
}

if ( ! function_exists( 'storeengine_archive_product_sidebar' ) ) {
	function storeengine_archive_product_sidebar() {
		Template::get_template( 'archive/sidebar.php' );
	}
}

function storeengine_get_archive_filter_widgets() {
	$config = wp_parse_args( (array) Helper::get_settings( 'product_archive_filters' ), [
		'search'   => (object) [
			'status' => true,
			'order'  => 0,
		],
		'category' => (object) [
			'status' => true,
			'order'  => 1,
		],
		'tags'     => (object) [
			'status' => true,
			'order'  => 2,
		],
	] );

	$config = array_filter( $config, fn( $value ) => $value->status );
	$order  = array_column( $config, 'order' );

	array_multisort( $config, SORT_ASC, $order );

	return apply_filters( 'storeengine/archive/product_filter_widgets', $config );
}

if ( ! function_exists( 'storeengine_archive_header_filter_widget' ) ) {
	function storeengine_archive_header_filter_widget() {
		$filters = storeengine_get_archive_filter_widgets();
		foreach ( $filters as $widget => $value ) {
			$filter_function = 'storeengine_render_archive_product_filter_' . $widget . '_widget';
			if ( $value && function_exists( $filter_function ) ) {
				$classes = 'storeengine-archive-product-widget storeengine-archive-product-widget--' . $widget;
				if ( ! empty( $value->wrapper_class ) ) {
					$classes .= ' ' . $value->wrapper_class;
				}
				?>
				<div class=" <?php echo esc_attr( trim( $classes ) ); ?>">
					<?php do_action( 'storeengine/archive/sidebar/filter_widget_before', $widget ); ?>
					<?php call_user_func( $filter_function ); ?>
					<?php do_action( 'storeengine/archive/sidebar/filter_widget_after', $widget ); ?>
				</div>
				<?php
			}
		}
	}
}

if ( ! function_exists( 'storeengine_render_archive_product_filter_search_widget' ) ) {
	function storeengine_render_archive_product_filter_search_widget() {
		Template::get_template( 'archive/widgets/search.php', apply_filters( 'storeengine/archive/product_filter_by_search_args', [] ) );
	}
}

if ( ! function_exists( 'storeengine_render_archive_product_filter_category_widget' ) ) {
	function storeengine_render_archive_product_filter_category_widget() {
		$args = apply_filters( 'storeengine/archive/product_filter_by_category_args', [ 'categories' => Helper::get_all_product_category_lists() ] );
		Template::get_template( 'archive/widgets/category.php', $args );
	}
}

if ( ! function_exists( 'storeengine_render_archive_product_filter_tags_widget' ) ) {
	function storeengine_render_archive_product_filter_tags_widget() {
		$tags = get_terms( [
			'taxonomy'   => 'storeengine_product_tag',
			'hide_empty' => true,
		] );
		$args = apply_filters( 'storeengine/archive/product_filter_by_tags_args', [ 'tags' => $tags ] );

		Template::get_template( 'archive/widgets/tags.php', $args );
	}
}//end if

if ( ! function_exists( 'storeengine_checkout_order_details' ) ) {
	function storeengine_checkout_order_details() {
		if ( ! Formatting::string_to_bool( get_query_var( 'order_pay' ) ) ) {
			return;
		}

		/** @var Order $order */
		$order = Helper::get_order( absint( get_query_var( 'order_id' ) ) );

		if ( is_wp_error( $order ) ) {
			/** @var WP_Error $order */
			if ( StoreEngineNotFoundException::WP_ERROR_CODE === $order->get_error_code() ) {
				storeengine_show_notice( __( 'Order not found.', 'storeengine' ), 'error' );

				return;
			}
			if ( in_array( $order->get_error_code(), [ 'order-not-found', 'order-class-not-found' ], true ) ) {
				storeengine_show_notice( __( 'Invalid Order', 'storeengine' ), 'error', 'order_pay_error' );

				return;
			}

			storeengine_show_notice( $order->get_error_message(), 'error' );

			return;
		}

		if ( ! $order->needs_payment() ) {
			if ( OrderStatus::CANCELLED === $order->get_status() ) {
				$error_message = __( 'This order has been canceled and payment is no longer possible. If you believe this is a mistake, please contact support.', 'storeengine' );
			} elseif ( OrderStatus::COMPLETED === $order->get_status() ) {
				$error_message = __( 'This order has already been completed and does not require payment. If you need help, please contact support.', 'storeengine' );
			} else {
				$error_message = __( 'This order cannot be paid at the moment. Please contact support if you need assistance.', 'storeengine' );
			}

			storeengine_show_notice(
				$error_message,
				[
					'type'  => 'warning',
					'title' => sprintf(
					// translators: %s. Order ID/Number.
						__( 'Order #%s', 'storeengine' ),
						$order->get_order_number()
					),
				],
				'order_pay_error'
			);

			return;
		}

		// Translators: %s Order number (id).
		echo '<h3>' . esc_html( sprintf( __( 'Complete Payment for Order #%s', 'storeengine' ), $order->get_id() ) ) . '</h3>';

		if ( class_exists( Hooks::class ) ) {
			remove_action( 'storeengine/thankyou/after_order_details', [ Hooks::class, 'order_subscription_details' ] );
		}

		Template::get_template( 'shortcode/order-details.php', [
			'order'              => $order,
			'order_items'        => $order->get_items(),
			'show_purchase_note' => false,
		] );

		echo '</br>';
	}
}

if ( ! function_exists( 'storeengine_checkout_payment_method' ) ) {
	function storeengine_checkout_payment_method() {
		$needs_payment     = Helper::cart()->needs_payment() || Formatting::string_to_bool( get_query_var( 'order_pay' ) );
		$place_order_label = __( 'Place Order', 'storeengine' );
		$selected          = false;

		if ( $needs_payment ) {
			if ( Formatting::string_to_bool( get_query_var( 'order_pay' ) ) ) {
				$order = Helper::get_order( absint( get_query_var( 'order_id' ) ) );

				if ( is_wp_error( $order ) || ! $order->needs_payment() ) {
					return;
				}

				$selected          = $order->get_payment_method();
				$place_order_label = __( 'Pay for Order', 'storeengine' );
			}

			$available_gateways = Helper::get_payment_gateways()->get_available_payment_gateways();
			// On the order-pay page, keep the order's existing payment method
			// selected (e.g. an early subscription renewal reuses the method the
			// customer paid with before) instead of defaulting to the first gateway.
			Helper::get_payment_gateways()->set_current_gateway( $available_gateways, is_string( $selected ) ? $selected : '' );
		}

		$place_order_label = apply_filters( 'storeengine/checkout/place_order_button_text', $place_order_label, $needs_payment );

		Template::get_template( 'checkout/payment.php', [
			'needs_payment'     => $needs_payment,
			'selected'          => $selected,
			'place_order_label' => $place_order_label,
		] );
	}
}

if ( ! function_exists( 'storeengine_checkout_form_field_user_info' ) ) {
	function storeengine_checkout_form_field_user_info() {
		if ( Formatting::string_to_bool( get_query_var( 'order_pay' ) ) ) {
			return;
		}

		Template::get_template( 'checkout/contact-info.php', [ 'current_user_email' => StoreEngine::init()->customer->get_email() ] );
	}
}

if ( ! function_exists( 'storeengine_checkout_form_field_shipping_address' ) ) {
	function storeengine_checkout_form_field_shipping_address() {
		if ( ! get_query_var( 'order_pay' ) ) {
			$order = Helper::get_recent_draft_order();
			if ( Helper::cart()->needs_shipping() ) {
				Template::get_template( 'checkout/shipping-address.php', [ 'order' => $order ] );
			}
		}
	}
}

if ( ! function_exists( 'storeengine_checkout_form_field_billing_address' ) ) {
	function storeengine_checkout_form_field_billing_address() {
		if ( ! get_query_var( 'order_pay' ) ) {
			$order = Helper::get_recent_draft_order();
			Template::get_template( 'checkout/billing-address.php', [
				'order'           => $order,
				'is_digital_cart' => ! Helper::cart()->needs_shipping(),
			] );
		}
	}
}

// Checkout Form
if ( ! function_exists( 'storeengine_checkout_total' ) ) {
	function storeengine_checkout_total() {
		Template::get_template( 'checkout/checkout-total.php' );
	}
}

if ( ! function_exists( 'storeengine_frontend_dashboard_menu' ) ) {
	function storeengine_frontend_dashboard_menu() {
		$menu_items = Helper::get_frontend_dashboard_menu_items();

		// Group-aware sort: orders → earnings → account, with non-clickable
		// section headers injected before the first member of each group.
		// Ungrouped items (Dashboard, Log out, vendor section) keep their
		// position via their own priority.
		$menu_items = Helper::apply_frontend_dashboard_menu_groups( $menu_items );

		Helper::get_template( 'frontend-dashboard/menu.php', [ 'menu_items' => $menu_items ] );
	}
}

if ( ! function_exists( 'storeengine_get_the_canvas_container_class' ) ) {
	function storeengine_get_the_canvas_container_class() {
		global $post;

		echo esc_attr( apply_filters( 'storeengine/templates/canvas_container_class', 'storeengine-container', $post->ID ) );
	}
}

/**
 * Render frontend top-bar.
 *
 * @return void
 */
function storeengine_frontend_dashboard_content_topbar() {
	$path       = (string) get_query_var( 'storeengine_dashboard_page' );
	$path       = $path ? $path : 'index';
	$sub_path   = (string) get_query_var( 'storeengine_dashboard_sub_page' );
	$page_title = StoreEngine\Utils\Helper::get_frontend_dashboard_page_title( $path, $sub_path );

	Template::get_template( 'frontend-dashboard/topbar.php', [
		'page_title' => $page_title,
		'path'       => $path,
		'sub_path'   => $sub_path,
	] );
}

/**
 * Per-endpoint description shown under the shared page-header title.
 *
 * @param string $description
 * @param string $path
 * @param string $sub_path
 *
 * @return string
 */
function storeengine_frontend_dashboard_page_description( $description, $path, $sub_path ) {
	$descriptions = [
		'payment-methods' => __( 'Set a default method and edit or remove your saved cards anytime.', 'storeengine' ),
	];

	return $descriptions[ $path ] ?? $description;
}

/**
 * Primary page action rendered on the right of the shared page-header for
 * endpoints that have one (replacing the per-page hand-rolled buttons).
 *
 * @param string $path
 * @param string $sub_path
 */
function storeengine_frontend_dashboard_topbar_actions( $path, $sub_path ) {
	if ( 'payment-methods' === $path && ! $sub_path && StoreEngine\Utils\Helper::get_payment_gateways()->get_available_payment_gateways() ) {
		printf(
			'<a class="storeengine-btn storeengine-btn--preset-primary storeengine-dashboard-header-action" href="%1$s"><span aria-hidden="true">+</span> %2$s</a>',
			esc_url( StoreEngine\Utils\Helper::get_account_endpoint_url( 'add-payment-method' ) ),
			esc_html__( 'Add Payment Method', 'storeengine' )
		);
	}
}

function storeengine_frontend_dashboard_breadcrumbs_order_title( string $path, string $sub_path ) {
	if ( 'orders' === $path && $sub_path ) {
		echo ' <i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i> ';
		/* translators: %s. Order ID/Number. */
		printf( esc_html__( 'Order #%s', 'storeengine' ), esc_html( $sub_path ) );
	}
}

function storeengine_frontend_dashboard_breadcrumbs_plan_title( string $path, string $sub_path ) {
	if ( 'plans' === $path && $sub_path ) {
		echo ' <i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i> ';
		/* translators: %s. Plan ID. */
		printf( esc_html__( 'Plan #%s', 'storeengine' ), esc_html( $sub_path ) );
	}
}

/**
 * Resolves page template file for plugin.
 *
 * Shop page (product-archive) will not go through `page_template` filter. As the plugin marks it as a archive page
 * and instead WordPress will load it through `archive_template` filter.
 *
 * @param string $template
 *
 * @return string
 */
function storeengine_redirect_canvas_page_template( $template ) {
	if ( Helper::is_fse_theme() ) {
		return $template;
	}

	$post          = get_post();
	$page_template = get_post_meta( $post->ID, '_wp_page_template', true );
	if ( 'storeengine-canvas.php' === basename( $page_template ) ) {
		$template = STOREENGINE_TEMPLATE_PATH . 'storeengine-canvas.php';
	}

	return $template;
}

// Dashboard Order Pagination
if ( ! function_exists( 'storeengine_dashboard_order_pagination' ) ) {
	/**
	 * @param $query
	 *
	 * @return void
	 * @deprecated 1.6.9
	 * @see storeengine_dashboard_collection_query_pagination()
	 */
	function storeengine_dashboard_order_pagination( $query ) {
		_deprecated_function( __FUNCTION__, '1.6.9', 'storeengine_dashboard_collection_query_pagination' );
		$current_page = max( 1, get_query_var( 'paged' ) );
		Template::get_template( 'frontend-dashboard/partials/query-pagination.php', [
			'query'         => $query,
			'page_url'      => Helper::get_current_dashboard_endpoint_url(),
			'current_page'  => $current_page,
			'previous_page' => $current_page - 1,
			'max_pages'     => $query->get_max_num_pages(),
		] );
	}
}

// Dashboard Order Pagination
if ( ! function_exists( 'storeengine_dashboard_subscription_pagination' ) ) {
	/**
	 * @param $query
	 *
	 * @return void
	 * @see storeengine_dashboard_collection_query_pagination()
	 * @deprecated 1.6.9
	 */
	function storeengine_dashboard_subscription_pagination( $query ) {
		_deprecated_function( __FUNCTION__, '1.6.9', 'storeengine_dashboard_collection_query_pagination' );
		$current_page = max( 1, get_query_var( 'paged' ) );
		Template::get_template( 'frontend-dashboard/partials/query-pagination.php', [
			'query'         => $query,
			'page_url'      => Helper::get_current_dashboard_endpoint_url(),
			'current_page'  => $current_page,
			'previous_page' => $current_page - 1,
			'max_pages'     => $query->get_max_num_pages(),
		] );
	}
}

if ( ! function_exists( 'storeengine_dashboard_collection_query_pagination' ) ) {
	/**
	 * Renders simple pagination from `Collection Query`.
	 *
	 * @param AbstractCollection $query Collection Query instance.
	 * @param string|null $endpoint Current endpoint (dashboard page).
	 *
	 * @return void
	 * @since 1.6.9
	 */
	function storeengine_dashboard_collection_query_pagination( AbstractCollection $query, string $endpoint = null ) {
		if ( $query->get_max_num_pages() <= 1 ) {
			return;
		}

		$current_page = max( 1, get_query_var( 'paged' ) );
		Template::get_template( 'frontend-dashboard/partials/query-pagination.php', [
			'query'         => $query,
			'page_url'      => Helper::get_current_dashboard_endpoint_url( $endpoint ),
			'current_page'  => $current_page,
			'previous_page' => $current_page - 1,
			'max_pages'     => $query->get_max_num_pages(),
		] );
	}
}

if ( ! function_exists( 'storeengine_single_product_feedback' ) ) {
	function storeengine_single_product_feedback() {
		if ( ! (bool) Helper::get_settings( 'enable_product_reviews', true ) ) {
			return;
		}
		$rating = \StoreEngine\Models\Product::get_product_rating( get_the_ID() );
		Template::get_template( 'single-product/feedback.php', array( 'rating' => $rating ) );
	}
}

if ( ! function_exists( 'storeengine_single_product_review_and_comments' ) ) {
	function storeengine_single_product_review_and_comments() {
		$enable_product_reviews  = (bool) \StoreEngine\Utils\Helper::get_settings( 'enable_product_reviews', true );
		$enable_product_comments = (bool) \StoreEngine\Utils\Helper::get_settings( 'enable_product_comments', false );

		if ( ! $enable_product_comments && ! $enable_product_reviews ) {
			return;
		}

		// Only render the comments block when product comments are enabled in
		// settings. The product post type always `supports` comments, so
		// comments_open() alone stays true even after the store disables them —
		// gating here is what actually hides the comments section.
		if ( $enable_product_comments && ( comments_open() || get_comments_number() ) ) {
			comments_template();
		}

		if ( $enable_product_reviews ) {
			$rating = StoreEngine\Models\Product::get_product_rating( get_the_ID() );
			Helper::get_template( 'single-product/feedback.php', [ 'rating' => $rating ] );
			Helper::get_template( 'single-product/reviews.php', [ 'product_id' => get_the_ID() ] );
		}
	}
}

if ( ! function_exists( 'storeengine_has_ordered_product' ) ) {
	/**
	 * Whether a customer has an order (any non-draft status) for a product.
	 *
	 * @param int $product_id Product id.
	 * @param int $user_id    Customer id (0 = current user).
	 *
	 * @return bool
	 */
	function storeengine_has_ordered_product( $product_id, $user_id = 0 ): bool {
		global $wpdb;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT o.id
				FROM {$wpdb->prefix}storeengine_orders o
				JOIN {$wpdb->prefix}storeengine_order_product_lookup op ON o.id = op.order_id
				WHERE o.customer_id = %d
				AND op.product_id = %d
				AND o.status NOT IN ( 'draft', 'auto-draft', 'trash' )
				LIMIT 1;",
			$user_id,
			$product_id
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}

if ( ! function_exists( 'storeengine_can_review_product' ) ) {
	/**
	 * Whether the current user may leave a review, per the `review_permission`
	 * setting: completed_order (default) | purchased | everyone.
	 *
	 * @param int $product_id Product id.
	 * @param int $user_id    User id (0 = current user).
	 *
	 * @return bool
	 */
	function storeengine_can_review_product( $product_id, $user_id = 0 ): bool {
		$permission = \StoreEngine\Utils\Helper::get_settings( 'review_permission', 'completed_order' );

		// Anyone (still requires login — reviews are stored as comments).
		if ( 'everyone' === $permission ) {
			$allowed = is_user_logged_in();
		} elseif ( ! is_user_logged_in() ) {
			$allowed = false;
		} elseif ( 'purchased' === $permission ) {
			$allowed = storeengine_has_ordered_product( $product_id, $user_id );
		} else {
			// completed_order — bought AND the order is completed.
			$allowed = (bool) \StoreEngine\Utils\Helper::is_purchase_the_product( $product_id, $user_id );
		}

		return (bool) apply_filters( 'storeengine/can_review_product', $allowed, $product_id, $user_id, $permission );
	}
}

if ( ! function_exists( 'storeengine_get_user_review' ) ) {
	/**
	 * The current (or given) user's own review for a product, if any.
	 *
	 * @param int $product_id Product id.
	 * @param int $user_id    User id (0 = current user).
	 *
	 * @return \WP_Comment|null
	 */
	function storeengine_get_user_review( $product_id, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return null;
		}

		$comments = get_comments( [
			'user_id' => $user_id,
			'post_id' => $product_id,
			'type'    => 'storeengine_product',
			'number'  => 1,
			'status'  => 'all',
		] );

		return $comments ? $comments[0] : null;
	}
}

if ( ! function_exists( 'storeengine_review_auto_approve' ) ) {
	/**
	 * Whether new reviews are auto-approved (vs held for manual approval),
	 * per the `review_approval` setting.
	 *
	 * @return bool
	 */
	function storeengine_review_auto_approve(): bool {
		$approval = \StoreEngine\Utils\Helper::get_settings( 'review_approval', 'auto' );

		return (bool) apply_filters( 'storeengine/review_auto_approve', 'pending' !== $approval );
	}
}

if ( ! function_exists( 'storeengine_review_media_max_size' ) ) {
	/**
	 * Max bytes a single review upload may be — the smaller of the plugin's own
	 * cap and the server's real PHP limit (upload_max_filesize / post_max_size).
	 *
	 * @param int $product_id Product id (for the filter).
	 *
	 * @return int Bytes.
	 */
	function storeengine_review_media_max_size( $product_id = 0 ): int {
		$plugin_max = (int) apply_filters( 'storeengine/review_media_max_size', 25 * MB_IN_BYTES, $product_id );
		$server_max = (int) wp_max_upload_size();

		if ( $server_max > 0 ) {
			return min( $plugin_max, $server_max );
		}

		return $plugin_max;
	}
}

if ( ! function_exists( 'storeengine_review_media_max' ) ) {
	/**
	 * Max media attachments allowed per review (0 = unlimited).
	 *
	 * @return int
	 */
	function storeengine_review_media_max(): int {
		return max( 0, (int) \StoreEngine\Utils\Helper::get_settings( 'review_media_max', 5 ) );
	}
}

if ( ! function_exists( 'storeengine_get_product_faqs' ) ) {
	/**
	 * Resolve the grouped FAQ list for a product (inline + rule-matched groups).
	 *
	 * @param int $product_id Product id.
	 *
	 * @return array<int,array{id:int,title:string,items:array<int,array{question:string,answer:string}>}>
	 */
	function storeengine_get_product_faqs( $product_id ): array {
		return \StoreEngine\Classes\Faq::get_product_faqs( absint( $product_id ) );
	}
}

if ( ! function_exists( 'storeengine_single_product_faq' ) ) {
	function storeengine_single_product_faq() {
		// The whole FAQ feature can be turned off in settings.
		if ( ! (bool) Helper::get_settings( 'enable_faqs', true ) ) {
			return;
		}

		$product_id = get_the_ID();
		$groups     = storeengine_get_product_faqs( $product_id );

		if ( empty( $groups ) ) {
			return;
		}

		Helper::get_template( 'single-product/faq.php', [
			'product_id' => $product_id,
			'groups'     => $groups,
		] );

		storeengine_product_faq_schema( $product_id, $groups );
	}
}

if ( ! function_exists( 'storeengine_product_faq_schema' ) ) {
	/**
	 * Emit schema.org FAQPage JSON-LD so search engines can surface FAQ rich
	 * results. Answers are reduced to plain text as the spec requires.
	 *
	 * @param int   $product_id Product id.
	 * @param array $groups     Resolved FAQ groups.
	 */
	function storeengine_product_faq_schema( $product_id, array $groups ) {
		if ( ! apply_filters( 'storeengine/product_faq_schema_enabled', true, $product_id ) ) {
			return;
		}

		$entities = [];
		foreach ( $groups as $group ) {
			foreach ( $group['items'] as $item ) {
				$question = trim( wp_strip_all_tags( $item['question'] ) );
				$answer   = trim( wp_strip_all_tags( $item['answer'] ) );

				if ( '' === $question || '' === $answer ) {
					continue;
				}

				$entities[] = [
					'@type'          => 'Question',
					'name'           => $question,
					'acceptedAnswer' => [
						'@type' => 'Answer',
						'text'  => $answer,
					],
				];
			}
		}

		if ( empty( $entities ) ) {
			return;
		}

		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		];

		echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'storeengine_review_display_gravatar' ) ) {
	/**
	 * Display the review authors gravatar
	 *
	 * @param stdClass|WP_Comment $comment WP_Comment.
	 *
	 * @return void
	 */
	function storeengine_review_display_gravatar( $comment ) {
		echo get_avatar( $comment->comment_author_email, apply_filters( 'storeengine/review_gravatar_size', '80' ), '' );
	}
}

if ( ! function_exists( 'storeengine_review_display_rating' ) ) {
	/**
	 * Display the reviewers star rating
	 *
	 * @return void
	 */
	function storeengine_review_display_rating( $comment ) {
		if ( post_type_supports( 'storeengine_product', 'comments' ) ) {
			Template::get_template( 'single-product/review-rating.php', [ 'comment' => $comment ] );
		}
	}
}

if ( ! function_exists( 'storeengine_review_display_meta' ) ) {
	/**
	 * Display the review authors meta (name, verified owner, review date)
	 *
	 * @return void
	 */
	function storeengine_review_display_meta( $comment ) {
		Template::get_template( 'single-product/review-meta.php', [ 'comment' => $comment ] );
	}
}

if ( ! function_exists( 'storeengine_review_display_comment_text' ) ) {

	/**
	 * Display the review content.
	 */
	function storeengine_review_display_comment_text( $comment ) {
		echo '<div class="storeengine-review-description">';
		comment_text( $comment->comment_ID );
		// @TODO make dynamic single-product/review-thumbnail.php
		echo '</div>';
	}
}

if ( ! function_exists( 'storeengine_get_review_media' ) ) {
	/**
	 * Get the media (images/videos) attached to a review.
	 *
	 * @param int $comment_id Comment id.
	 *
	 * @return array<int,array{id:int,url:string,thumb:string,type:string}>
	 */
	function storeengine_get_review_media( $comment_id ): array {
		$ids = get_comment_meta( $comment_id, 'storeengine_review_media', true );

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return [];
		}

		$media = [];
		foreach ( $ids as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			$url           = wp_get_attachment_url( $attachment_id );

			if ( ! $url ) {
				continue;
			}

			$mime    = (string) get_post_mime_type( $attachment_id );
			$media[] = [
				'id'    => $attachment_id,
				'url'   => $url,
				'thumb' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: $url,
				'type'  => ( 0 === strpos( $mime, 'video/' ) ) ? 'video' : 'image',
			];
		}

		return $media;
	}
}

if ( ! function_exists( 'storeengine_review_display_media' ) ) {
	/**
	 * Render the media gallery attached to a review.
	 *
	 * @param \WP_Comment $comment Comment.
	 */
	function storeengine_review_display_media( $comment ) {
		$media = storeengine_get_review_media( $comment->comment_ID );

		if ( empty( $media ) ) {
			return;
		}

		echo '<div class="storeengine-review-media">';
		foreach ( $media as $item ) {
			if ( 'video' === $item['type'] ) {
				printf(
					'<a class="storeengine-review-media__item storeengine-review-media__item--video" href="%1$s" target="_blank" rel="noopener"><video src="%1$s" preload="metadata" muted playsinline></video><span class="storeengine-review-media__play" aria-hidden="true"></span></a>',
					esc_url( $item['url'] )
				);
			} else {
				printf(
					'<a class="storeengine-review-media__item storeengine-review-media__item--image" href="%1$s" target="_blank" rel="noopener"><img src="%2$s" alt="%3$s" loading="lazy" /></a>',
					esc_url( $item['url'] ),
					esc_url( $item['thumb'] ),
					esc_attr__( 'Review media', 'storeengine' )
				);
			}
		}
		echo '</div>';
	}
}

if ( ! function_exists( 'storeengine_get_rating_html' ) ) {
	/**
	 * Get HTML for ratings.
	 *
	 * @param float $rating Rating being shown.
	 * @param int $count Total number of ratings.
	 *
	 * @return string
	 */
	function storeengine_get_rating_html( $rating, $count = 0 ) {
		$html = '';
		if ( 0 < $rating ) {
			$html = Helper::single_star_rating_generator( $rating );
		}

		return apply_filters( 'storeengine/course_get_rating_html', $html, $rating, $count );
	}
}

if ( ! function_exists( 'storeengine_get_rating_html' ) ) {
	function storeengine_single_product_count_review() {
		?>
		<div class="storeengine-single__rating storeengine-d-flex">
			<div>
				<i class="storeengine-icon storeengine-icon--star-fill"></i>
				<i class="storeengine-icon storeengine-icon--star-fill"></i>
				<i class="storeengine-icon storeengine-icon--star-fill"></i>
				<i class="storeengine-icon storeengine-icon--star-fill"></i>
				<i class="storeengine-icon storeengine-icon--star-fill"></i>
			</div>
			<div><a href="#">(<span>1</span> customer review)</a></div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'storeengine_review_lists' ) ) {
	function storeengine_review_lists( $comment ) {
		Template::get_template( 'single-product/review.php', [ 'comment' => $comment ] );
	}
}

if ( ! function_exists( 'storeengine_comment_reform' ) ) {
	/**
	 * Comment Message Box
	 */
	function storeengine_comment_reform( $arg ) {
		$arg['title_reply'] = esc_html__( 'Post your Comment About This Product', 'storeengine' );

		return $arg;
	}

	add_filter( 'comment_form_defaults', 'storeengine_comment_reform' );
}

if ( ! function_exists( 'storeengine_time_ago_comment' ) ) {
	function storeengine_time_ago_comment( $comment_id = null ): string {
		if ( is_null( $comment_id ) ) {
			$comment_id = get_comment_ID(); // Get the current comment's ID if not provided
		}

		$comment_time = get_comment_date( 'U', $comment_id ); // Get the comment's timestamp
		$time_diff    = human_time_diff( $comment_time, current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		/* translators: %s: Human-readable time difference. */
		return sprintf( esc_html__( '%s ago', 'storeengine' ), $time_diff );
	}
}

if ( ! function_exists( 'storeengine_comments' ) ) {
	function storeengine_comments( $comment, $args, $depth ) {
		$GLOBALS['comment'] = $comment; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$commenter          = wp_get_current_commenter();
		$show_pending_links = ! empty( $commenter['comment_author'] );

		if ( $commenter['comment_author_email'] ) {
			$moderation_note = __( 'Your comment is awaiting moderation.', 'storeengine' );
		} else {
			$moderation_note = __( 'Your comment is awaiting moderation. This is a preview; your comment will be visible after it has been approved.', 'storeengine' );
		}

		$classes = 'storeengine-comment';

		if ( ! empty( $args['has_children'] ) ) {
			$classes .= ' storeengine-comment--parent parent';
		}

		// @XXX DO NOT CLOSE THE `LI` TAGS. THE COMMENT WALKER CLASS CLOSES IT.
		if ( ( 'pingback' === $comment->comment_type || 'trackback' === $comment->comment_type ) && $args['short_ping'] ) : ?>
			<li id="comment-<?php comment_ID(); ?>" <?php comment_class( 'storeengine-' . $comment->comment_type, $comment ); ?>>
			<div class="comment-body">
				<?php esc_html_e( 'Pingback:', 'storeengine' ); ?><?php comment_author_link(); ?><?php edit_comment_link( esc_attr__( 'Edit', 'storeengine' ), '<span class="edit-link">', '</span>' ); ?>
			</div>
		<?php else : ?>
			<li id="comment-<?php comment_ID(); ?>" <?php comment_class( $classes, $comment ); ?>>
			<article id="div-comment-<?php comment_ID(); ?>" class="comment-body storeengine-row storeengine-relative">
				<div class="storeengine-col-2 storeengine-col-md-1 storeengine-comment-contain-img">
					<?php echo get_avatar( $comment, 64, '', get_comment_author( $comment ) ); // 50 is the size of the avatar ?>
				</div>
				<div class="storeengine-col-10 storeengine-col-md-11">
					<div class="comment-header">
						<div class="comment-author vcard">
							<?php
							$comment_author = get_comment_author_link( $comment );

							if ( '0' === $comment->comment_approved && ! $show_pending_links ) {
								$comment_author = get_comment_author( $comment );
							}

							echo wp_kses_post(
								sprintf(
								/* translators: %s: Comment author link. */
									__( '%s <span class="says">says:</span>', 'storeengine' ),
									sprintf( '<b class="comment-author-title fn">%s</b>', $comment_author )
								)
							);
							?>
						</div>
						<div class="comment-metadata">
							<?php
							printf(
								'<a class="storeengine-comment-time" href="%s"><time class="moment-skip" datetime="%s">%s</time></a>',
								esc_url( get_comment_link( $comment, $args ) ),
								esc_attr( get_comment_time( 'c' ) ),
								esc_html( storeengine_time_ago_comment( $comment ) )
							);
							edit_comment_link( __( 'Edit', 'storeengine' ), ' <span class="edit-link">', '</span>' );
							comment_reply_link( array_merge( $args, array(
								'add_below' => 'div-comment',
								'depth'     => $depth,
								'max_depth' => $args['max_depth'],
								'before'    => '<span class="storeengine_replay_text_link">',
								'after'     => '</span>',
							) ) );
							?>
						</div>
					</div>
					<!--
						<div class="storeengine-flex storeengine-flex-gap-4">
							<a class="storeengine-comment-time" href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>">
								<time datetime="<?php comment_time( 'c' ); ?>">
								</time>
							</a>
						</div>
						-->
					<div class="comment-content storeengine-comment--content">
						<?php if ( '0' === $comment->comment_approved ) : ?>
							<p><em class="comment-awaiting-moderation"><?php echo esc_html( $moderation_note ); ?></em></p>
						<?php endif; ?>
						<?php comment_text(); ?>
					</div>
				</div>
			</article>
		<?php
		endif;
	}
}

// Dashboard Downloads Pagination
if ( ! function_exists( 'storeengine_dashboard_downloads_pagination' ) ) {
	function storeengine_dashboard_downloads_pagination( $downloadable_permissions ) {
		Template::get_template(
			'frontend-dashboard/partials/downloads-pagination.php',
			array( 'downloadable_permissions' => $downloadable_permissions )
		);
	}
}

if ( ! function_exists( 'storeengine_get_cart_item_data' ) ) {
	function storeengine_get_cart_item_data( $cart_item ): array {
		$item_data = [];

		foreach ( $cart_item->variation ?? [] as $taxonomy => $value ) {
			$taxonomy = get_taxonomy( $taxonomy );
			if ( $taxonomy instanceof WP_Taxonomy ) {
				$term = get_term_by( 'slug', $value, $taxonomy->name );
				if ( $term instanceof WP_Term ) {
					$value = $term->name;
				}
			}
			$item_data[] = [
				// `$taxonomy->label` is the admin plural — register_taxonomy()
				// sets labels['name'] to "Product %s" and WP then copies that
				// over ->label, so it reads "Product Color" here. The singular
				// is the attribute's own name ("Color"), which is what belongs
				// on a customer-facing "Color: Red" line.
				'label' => $taxonomy instanceof WP_Taxonomy
					? ( $taxonomy->labels->singular_name ?: $taxonomy->label )
					: $taxonomy,
				'value' => $value,
			];
		}

		return apply_filters( 'storeengine/get_cart_item_data', $item_data, $cart_item );
	}
}

if ( ! function_exists( 'storeengine_display_item_meta' ) ) {
	/**
	 * Display item meta data.
	 *
	 * @param OrderItemProduct $item Order Item.
	 * @param array $args Arguments.
	 *
	 * @return string|void
	 * @since  0.0.6-beta
	 */
	function storeengine_display_item_meta( OrderItemProduct $item, array $args = [] ) {
		$strings = [];
		$html    = '';
		$args    = wp_parse_args(
			$args,
			[
				'before'       => '<ul class="storeengine-order-item-meta"><li>',
				'after'        => '</li></ul>',
				'separator'    => '</li><li>',
				'echo'         => true,
				'autop'        => true,
				'label_before' => '<strong class="storeengine-order-item-meta-label">',
				'label_after'  => ':</strong> ',
			]
		);

		foreach ( $item->get_all_formatted_metadata() as $metadata ) {
			$value     = wp_kses_post( make_clickable( trim( $metadata['display_value'] ) ) );
			$strings[] = $args['label_before'] . wp_kses_post( $metadata['display_key'] ) . $args['label_after'] . $value;
		}

		if ( $strings ) {
			$html = $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
		}

		$html = apply_filters( 'storeengine/display_item_meta', $html, $item, $args );

		if ( $args['echo'] ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			return $html;
		}
	}
}

if ( ! function_exists( 'storeengine_single_product_display_price' ) ) {
	/**
	 * @param Price $price_item
	 *
	 * @return void
	 * @deprecated
	 */
	function storeengine_single_product_display_price( Price $price_item ) {
		if ( empty( $price_item->get_price() ) ) {
			return;
		}
		?>
		<span class="has-storeengine-single__sell-amount storeengine-product__price-amount storeengine-mr-1">
			<?php
			if ( 'subscription' === $price_item->get_type() ) {
				echo wp_kses_post( $price_item->get_formatted_payment_duration() );
				if ( $price_item->is_trial() ) {
					echo '<br>';
					echo wp_kses_post( 'Starting in ' . $price_item->get_trial_days() . ' days' );
				}

				if ( $price_item->is_setup_fee() ) {
					echo '<br>';
					echo wp_kses_post( Formatting::price( $price_item->get_setup_fee_price() ) . ' ' . $price_item->get_setup_fee_name() );
				}
			} else {
				echo wp_kses_post( Formatting::price( $price_item->get_price() ) );
			}
			?>
		</span>
		<?php
	}
}

// Single Like and dislike
if ( ! function_exists( 'storeengine_single_like_dislike' ) ) {
	function storeengine_single_like_dislike() {
		Template::get_template( 'single-product/like.php' );
	}
}

// Single
if ( ! function_exists( 'storeengine_single_filter' ) ) {
	function storeengine_single_filter() {
		Template::get_template( 'single-product/filter.php' );
	}
}

/**
 * Get account formatted address.
 *
 * @param string $address_type Type of address; 'billing' or 'shipping'.
 * @param int $customer_id Customer ID. Defaults to 0.
 *
 * @return string
 */
function storeengine_get_dashboard_formatted_address( string $address_type = 'billing', int $customer_id = 0 ): string {
	$getter  = "get_{$address_type}";
	$address = [];

	if ( 0 === $customer_id ) {
		$customer_id = get_current_user_id();
	}

	$customer = new \StoreEngine\Classes\Customer( $customer_id );

	if ( is_callable( array( $customer, $getter ) ) ) {
		$address = $customer->$getter();
		unset( $address['email'], $address['tel'] );
	}

	return Countries::init()->get_formatted_address( apply_filters( 'storeengine/frontend/dashboard_formatted_address', $address, $customer->get_id(), $address_type ) );
}

if ( ! function_exists( 'storeengine_form_field' ) ) {

	/**
	 * Outputs a checkout/address form field.
	 *
	 * @param string $key Key.
	 * @param array|string $args Arguments.
	 * @param ?string $value (default: null).
	 *
	 * @return string|void
	 * @noinspection HtmlUnknownAttribute
	 */
	function storeengine_form_field( string $key, $args, ?string $value = null ) {
		$defaults = [
			'type'              => 'text',
			'label'             => '',
			'description'       => '',
			'placeholder'       => '',
			'maxlength'         => false,
			'minlength'         => false,
			'required'          => false,
			'autocomplete'      => false,
			'id'                => $key,
			'class'             => [],
			'label_class'       => [],
			'input_class'       => [],
			'return'            => false,
			'options'           => [],
			'custom_attributes' => [],
			'validate'          => [],
			'default'           => '',
			'autofocus'         => '',
			'priority'          => '',
			'unchecked_value'   => null,
			'checked_value'     => '1',
		];

		$args = wp_parse_args( $args, $defaults );
		$args = apply_filters( 'storeengine/frontend/form_field_args', $args, $key, $value );

		if ( is_string( $args['class'] ) ) {
			$args['class'] = array( $args['class'] );
		}

		if ( is_string( $args['label_class'] ) ) {
			$args['label_class'] = array( $args['label_class'] );
		}

		if ( is_null( $value ) ) {
			$value = $args['default'];
		}

		// Custom attribute handling.
		$custom_attributes         = [];
		$args['custom_attributes'] = array_filter( (array) $args['custom_attributes'], 'strlen' );

		if ( $args['required'] ) {
			// hidden inputs are the only kind of inputs that don't need an `aria-required` attribute.
			// checkboxes apply the `custom_attributes` to the label - we need to apply the attribute on the input itself, instead.
			if ( ! in_array( $args['type'], [ 'hidden', 'checkbox' ], true ) ) {
				$args['custom_attributes']['aria-required'] = 'true';
				$args['label_class'][]                      = 'required_field';
			}

			$args['class'][]    = 'validate-required';
			$required_indicator = '&nbsp;<abbr class="storeengine-required" title="' . esc_attr__( 'Required', 'storeengine' ) . '" aria-hidden="true">*</abbr>';
		} else {
			$required_indicator = '&nbsp;<span class="storeengine-optional screen-reader-text">(' . esc_html__( 'optional', 'storeengine' ) . ')</span>';
		}

		if ( $args['maxlength'] ) {
			$args['custom_attributes']['maxlength'] = absint( $args['maxlength'] );
		}

		if ( $args['minlength'] ) {
			$args['custom_attributes']['minlength'] = absint( $args['minlength'] );
		}

		if ( ! empty( $args['autocomplete'] ) ) {
			$args['custom_attributes']['autocomplete'] = $args['autocomplete'];
		}

		if ( true === $args['autofocus'] ) {
			$args['custom_attributes']['autofocus'] = 'autofocus';
		}

		if ( $args['description'] ) {
			$args['custom_attributes']['aria-describedby'] = $args['id'] . '-description';
		}

		if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
			foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
				$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
			}
		}

		if ( ! empty( $args['validate'] ) ) {
			foreach ( $args['validate'] as $validate ) {
				$args['class'][] = 'validate-' . $validate;
			}
		}

		$field           = '';
		$label_id        = $args['id'];
		$sort            = $args['priority'] ? $args['priority'] : '';
		$field_container = '<p class="storeengine-form-field %1$s" id="%2$s" data-priority="' . esc_attr( $sort ) . '">%3$s</p>';

		/** @noinspection HtmlUnknownAttribute */
		switch ( $args['type'] ) {
			case 'country':
				$countries = 'shipping_country' === $key ? Countries::init()->get_shipping_countries() : Countries::init()->get_allowed_countries();

				if ( 1 === count( $countries ) ) {
					$field .= '<strong>' . current( array_values( $countries ) ) . '</strong>';

					$field .= '<input type="hidden" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . current( array_keys( $countries ) ) . '" ' . implode( ' ', $custom_attributes ) . ' class="country_to_state" readonly="readonly" />';
				} else {
					$data_label = ! empty( $args['label'] ) ? 'data-label="' . esc_attr( $args['label'] ) . '"' : '';

					$field = '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="store-form-control country_to_state country_select ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . ' data-placeholder="' . esc_attr( $args['placeholder'] ? $args['placeholder'] : esc_attr__( 'Select a country / region&hellip;', 'storeengine' ) ) . '" ' . $data_label . '><option value="">' . esc_html__( 'Select a country / region&hellip;', 'storeengine' ) . '</option>';

					foreach ( $countries as $ckey => $cvalue ) {
						$field .= '<option value="' . esc_attr( $ckey ) . '" ' . selected( $value, $ckey, false ) . '>' . esc_html( $cvalue ) . '</option>';
					}

					$field .= $required_indicator . '</select>';

					$field .= '<noscript><button type="submit" name="storeengine_checkout_update_totals" value="' . esc_attr__( 'Update country / region', 'storeengine' ) . '">' . esc_html__( 'Update country / region', 'storeengine' ) . '</button></noscript>';
				}

				break;
			case 'state':
				/* Get country this state field is representing */
				$for_country = null;

				if ( isset( $args['country'] ) ) {
					$for_country = $args['country'];
				} else {
					$index = 'billing_state' === $key ? 'billing_country' : 'shipping_country';
					if ( ! empty( $_POST[ $index ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checking if country field is selected.
						$for_country = sanitize_text_field( wp_unslash( $_POST[ $index ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Setting selected country code for retrieving states for the country.
					} else {
						if ( storeengine()->get_customer() && storeengine()->get_customer()->get_id() ) {
							$for_country = storeengine()->get_customer()->{'get_' . $index}();
							if ( '' === $for_country ) {
								$for_country = null;
							}
						}
					}
				}

				$states = Countries::init()->get_states( $for_country );

				if ( is_array( $states ) && empty( $states ) ) {
					$field_container = '<p class="storeengine-form-field %1$s" id="%2$s" style="display: none">%3$s</p>';

					$field .= '<input type="hidden" class="hidden" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="" ' . implode( ' ', $custom_attributes ) . ' placeholder="' . esc_attr( $args['placeholder'] ) . '" readonly="readonly" data-input-classes="' . esc_attr( implode( ' ', $args['input_class'] ) ) . '"/>';
				} elseif ( ! is_null( $for_country ) && is_array( $states ) ) {
					$data_label = ! empty( $args['label'] ) ? 'data-label="' . esc_attr( $args['label'] ) . '"' : '';

					$field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="store-form-control state_select ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . ' data-placeholder="' . esc_attr( $args['placeholder'] ? $args['placeholder'] : esc_html__( 'Select an option&hellip;', 'storeengine' ) ) . '"  data-input-classes="' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . $data_label . '>
						<option value="">' . esc_html__( 'Select an option&hellip;', 'storeengine' ) . '</option>';

					foreach ( $states as $ckey => $cvalue ) {
						$field .= '<option value="' . esc_attr( $ckey ) . '" ' . selected( $value, $ckey, false ) . '>' . esc_html( $cvalue ) . '</option>';
					}

					$field .= '</select>';
				} else {
					$field .= '<input type="text" class="store-form-control ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" value="' . esc_attr( $value ) . '"  placeholder="' . esc_attr( $args['placeholder'] ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" ' . implode( ' ', $custom_attributes ) . ' data-input-classes="' . esc_attr( implode( ' ', $args['input_class'] ) ) . '"/>';
				}

				break;
			case 'textarea':
				$field .= '<textarea name="' . esc_attr( $key ) . '" class="store-form-control ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" ' . ( empty( $args['custom_attributes']['rows'] ) ? ' rows="2"' : '' ) . ( empty( $args['custom_attributes']['cols'] ) ? ' cols="5"' : '' ) . implode( ' ', $custom_attributes ) . '>' . esc_textarea( $value ) . '</textarea>';

				break;
			case 'checkbox':
				$field = '<label class="checkbox ' . esc_attr( implode( ' ', $args['label_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . '>';

				// Output a hidden field so a value is POSTed if the box is not checked.
				if ( ! is_null( $args['unchecked_value'] ) ) {
					$field .= sprintf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $key ), esc_attr( $args['unchecked_value'] ) );
				}

				$field .= sprintf(
					'<input type="checkbox" name="%1$s" id="%2$s" value="%3$s" class="%4$s" %5$s%6$s /> %7$s',
					esc_attr( $key ),
					esc_attr( $args['id'] ),
					esc_attr( $args['checked_value'] ),
					esc_attr( 'input-checkbox ' . implode( ' ', $args['input_class'] ) ),
					checked( $value, $args['checked_value'], false ),
					$args['required'] ? ' aria-required="true"' : '',
					wp_kses_post( $args['label'] )
				);

				$field .= $required_indicator . '</label>';

				break;
			case 'text':
			case 'password':
			case 'datetime':
			case 'datetime-local':
			case 'date':
			case 'month':
			case 'time':
			case 'week':
			case 'number':
			case 'email':
			case 'url':
			case 'tel':
				$field .= '<input type="' . esc_attr( $args['type'] ) . '" class="store-form-control ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"  value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

				break;
			case 'hidden':
				$field .= '<input type="' . esc_attr( $args['type'] ) . '" class="hidden storeengine-hidden ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

				break;
			case 'select':
				$options = '';

				if ( ! empty( $args['options'] ) ) {
					foreach ( $args['options'] as $option_key => $option_text ) {
						if ( '' === $option_key ) {
							// A blank option is the proper way to set a placeholder. If one is supplied we make sure the placeholder key is set for the enhanced select field.
							if ( empty( $args['placeholder'] ) ) {
								$args['placeholder'] = $option_text ? $option_text : __( 'Choose an option', 'storeengine' );
							}
							$custom_attributes[] = 'data-allow_clear="true"';
						}
						$options .= '<option value="' . esc_attr( $option_key ) . '" ' . selected( $value, $option_key, false ) . '>' . esc_html( $option_text ) . '</option>';
					}

					$field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="store-form-control ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . ' data-placeholder="' . esc_attr( $args['placeholder'] ) . '">
							' . $options . '
						</select>';
				}

				break;
			case 'radio':
				$label_id .= '_' . current( array_keys( $args['options'] ) );

				if ( ! empty( $args['options'] ) ) {
					foreach ( $args['options'] as $option_key => $option_text ) {
						$field .= '<input type="radio" class="store-form-control-radio storeengine-radio ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" value="' . esc_attr( $option_key ) . '" name="' . esc_attr( $key ) . '" ' . implode( ' ', $custom_attributes ) . ' id="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '"' . checked( $value, $option_key, false ) . ' />';
						$field .= '<label for="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '" class="radio ' . implode( ' ', $args['label_class'] ) . '">' . esc_html( $option_text ) . $required_indicator . '</label>';
					}
				}

				break;
		}

		if ( ! empty( $field ) ) {
			$field_html = '';

			if ( $args['label'] && 'checkbox' !== $args['type'] ) {
				$field_html .= '<label for="' . esc_attr( $label_id ) . '" class="store-form-control-checkbox ' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . wp_kses_post( $args['label'] ) . $required_indicator . '</label>';
			}

			$field_html .= '<span class="storeengine-input-wrapper">' . $field;

			if ( $args['description'] ) {
				$field_html .= '<span class="storeengine-description" id="' . esc_attr( $args['id'] ) . '-description" aria-hidden="true">' . wp_kses_post( $args['description'] ) . '</span>';
			}

			$field_html .= '</span>';

			$container_class = esc_attr( implode( ' ', $args['class'] ) );
			$container_id    = esc_attr( $args['id'] ) . '_field';
			$field           = sprintf( $field_container, $container_class, $container_id, $field_html );
		}

		/**
		 * Filter by type.
		 */
		$field = apply_filters( 'storeengine/frontend/form_field_' . $args['type'], $field, $key, $args, $value );

		/**
		 * General filter on form fields.
		 */
		$field = apply_filters( 'storeengine/frontend/form_field', $field, $key, $args, $value );

		if ( $args['return'] ) {
			return $field;
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $field;
		}
	}
}

if ( ! function_exists( 'storeengine_render_upsell_items' ) ) {
	function storeengine_render_upsell_items(): void {
		$id  = is_int( $temp_id = get_the_ID() ) ? $temp_id : null;
		$ids = ! is_null( $id ) ? get_post_meta( $id, '_storeengine_upsell_ids', true ) : null;
		$ids = is_array( $ids ) ? $ids : null;

		if ( empty( $ids ) ) {
			return;
		}

		$args = array(
			'post_type'      => Helper::PRODUCT_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
			'fields'         => 'ids',
			'post__in'       => $ids,
		);

		$products_per_row = Helper::get_settings( 'product_archive_products_per_row', (object) [
			'desktop' => 3,
			'tablet'  => 2,
			'mobile'  => 1,
		] );

		$grid_class = Helper::get_responsive_column( array(
			'desktop' => (int) $products_per_row->desktop ?? 3,
			'tablet'  => 2,
			'mobile'  => 1,
		) );

		wp_reset_postdata();

		// phpcs:ignore WordPress.WP.DiscouragedFunctions.query_posts_query_posts
		query_posts( apply_filters( 'storeengine/products/cross_sell/args', $args ) );
		Template::get_template( 'single-product/upsell.php', array( 'grid_class' => $grid_class ) );
		wp_reset_postdata();
	}
}

if ( ! function_exists( 'storeengine_render_crosssell_items' ) ) {
	function storeengine_render_crosssell_items(): void {
		// Get product IDs from cart
		$cart_items  = storeengine_cart()->get_cart_items();
		$product_ids = array_unique(
			array_values(
				array_map(
					fn( $cart_item ) => (int) $cart_item->product_id,
					$cart_items
				)
			)
		);

		// Collect all cross-sell IDs
		$cross_sell_ids = [];
		foreach ( $product_ids as $pid ) {
			$cr_ids = get_post_meta( $pid, '_storeengine_crosssell_ids', true );
			if ( is_array( $cr_ids ) ) {
				$cross_sell_ids = array_merge( $cross_sell_ids, $cr_ids );
			}
		}
		$cross_sell_ids = array_unique( $cross_sell_ids );

		if ( empty( $cross_sell_ids ) ) {
			return;
		}

		$args = array(
			'post_type'      => Helper::PRODUCT_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
			'fields'         => 'ids',
			'post__in'       => $cross_sell_ids,
		);

		$products_per_row = Helper::get_settings( 'product_archive_products_per_row', (object) [
			'desktop' => 3,
			'tablet'  => 2,
			'mobile'  => 2,
		] );

		$grid_class = Helper::get_responsive_column( array(
			'desktop' => (int) $products_per_row->desktop ?? 3,
			'tablet'  => 2,
			'mobile'  => 2,
		) );

		if ( wp_is_rest_endpoint() ) {
			// For mini-cart.
			$grid_class = Helper::get_responsive_column( array(
				'desktop' => 2,
				'tablet'  => 2,
				'mobile'  => 2,
			) );
		}

		wp_reset_postdata();

		// phpcs:ignore WordPress.WP.DiscouragedFunctions.query_posts_query_posts
		query_posts( apply_filters( 'storeengine/products/cross_sell/args', $args ) );
		Template::get_template( 'cart/cross-sell.php', array( 'grid_class' => $grid_class ) );
		wp_reset_postdata();
	}
}

/**
 * Get account orders actions.
 *
 * @param Order $order Order instance or ID.
 *
 * @return array
 */
function storeengine_get_account_orders_actions( Order $order ): array {
	$actions = [
		'view'   => [
			'classes'    => 'storeengine-btn--preset-gray',
			'url'        => $order->get_view_order_url(),
			'icon'       => 'eye',
			'name'       => __( 'Details', 'storeengine' ),
			/* translators: %s: order number */
			'aria-label' => sprintf( __( 'View order %s', 'storeengine' ), $order->get_order_number() ),
		],
		'pay'    => [
			'classes'    => 'storeengine-btn--preset-success',
			'url'        => $order->get_checkout_payment_url(),
			// A failed order is a retry, not a first payment — label it as such.
			'name'       => OrderStatus::PAYMENT_FAILED === $order->get_status() ? __( 'Retry payment', 'storeengine' ) : __( 'Pay now', 'storeengine' ),
			/* translators: %s: order number */
			'aria-label' => sprintf( __( 'Pay for order %s', 'storeengine' ), $order->get_order_number() ),
		],
		'cancel' => [
			'classes'    => 'storeengine-btn--preset-red',
			'url'        => $order->get_cancel_order_url( Helper::get_dashboard_url() ),
			'name'       => __( 'Cancel Order', 'storeengine' ),
			/* translators: %s: order number */
			'aria-label' => sprintf( __( 'Cancel order %s', 'storeengine' ), $order->get_order_number() ),
			'data'       => [
				'confirm-action' => __( 'Are you sure you want to cancel your order?', 'storeengine' ),
			],
		],
	];

	if ( ! $order->needs_payment() ) {
		unset( $actions['pay'] );
	}

	/**
	 * Filters the valid order statuses for cancel action.
	 *
	 * @param array $statuses_for_cancel Array of valid order statuses for cancel action.
	 * @param Order $order Order instance.
	 */
	$statuses_for_cancel = apply_filters( 'storeengine/order/valid_statuses_for_cancel', [
		OrderStatus::PAYMENT_PENDING,
		OrderStatus::PAYMENT_FAILED,
	], $order );

	if ( ! in_array( $order->get_status(), $statuses_for_cancel, true ) ) {
		unset( $actions['cancel'] );
	}

	return apply_filters( 'storeengine/dashboard/order/actions', $actions, $order );
}

/**
 * List of dashboard action button configurations.
 *
 * @param array<string, array{
 *     url: string,
 *     name?: string,
 *     icon?: string,
 *     data?: array<string, string>,
 *     classes?: string,
 *     target?: string,
 *     styles?: string|array<string>|array<string,string>,
 *     priority?: int,
 * }>&array $actions
 * @param string $type  Context type (e.g. 'order', 'subscription').
 * @param ?string $menu_name  Menu name for accessibility (E.g. "Order", "Payment method", etc).
 * @param bool   $split Whether to split primary/secondary actions.
 *
 * @example
 * ```php
 * storeengine_render_dashboard_action_buttons( [
 *     'view' => [
 *         'classes'    => 'storeengine-btn--preset-gray',
 *         'url'        => 'https://example.com/order/123',
 *         'icon'       => 'eye',
 *         'aria-label' => 'View Order 123',
 *     ],
 *     'reactivate' => [
 *         'url'        => 'https://example.com/order/delete/123',
 *         'name'       => 'Delete',
 *         'aria-label' => 'Delete Order 123',
 *         'data'       => [
 *             'confirm-action' => 'Are you sure?', // supported by js.
 *         ],
 *     ],
 * ] );
 * ```
 *
 * @return void
 */
function storeengine_render_dashboard_action_buttons( array $actions, string $type = 'order', string $menu_name = null, bool $split = true ): void {
	if ( ! $actions ) {
		return;
	}

	ArrayUtil::priority_sort( $actions );

	$action = null;

	if ( $split && $actions ) {
		$action  = array_slice( $actions, 0, 1, true ); // Get the first key-value pair;
		$actions = array_slice( $actions, 1, null, true ); // Get the remaining key-value pairs;
	}

	$id = wp_unique_id( $type . '-' );
	?>
	<div class="storeengine-action-dropdown storeengine-actions--<?php echo esc_attr( $type ); ?>">
		<?php
		if ( $action ) {
			foreach ( $action as $key => $item ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				storeengine_print_dropdown_action_button( $key, $item, $type );
			}
		}
		?>
		<?php if ( ! empty( $actions ) ) {
			$menu_name = $menu_name ?? __( 'Dropdown', 'storeengine' );
			?>
			<button id="<?php echo esc_attr( $id . '-menu-button' ); ?>" type="button" class="storeengine-btn storeengine-btn--preset-gray storeengine-action-dropdown--toggle" data-toggle="dropdown" aria-controls="<?php echo esc_attr( $id . '-menu' ); ?>" aria-haspopup="true" aria-expanded="false">
				<span class="screen-reader-text">
					<?php
					printf(
						// translators: %s. Toggle menu name screen-reader (accessibility).
						esc_html__( 'Toggle %s Actions', 'storeengine' ),
						esc_html( $menu_name )
					);
					?>
				</span>
				<?php storeengine_render_icon( 'three-dots-menu' ); ?>
			</button>
			<div id="<?php echo esc_attr( $id . '-menu' ); ?>" class="storeengine-action-dropdown--menu" role="menu" aria-labelledby="<?php echo esc_attr( $id . '-menu-button' ); ?>">
				<?php
				foreach ( $actions as $key => $item ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					storeengine_print_dropdown_action_button( $key, $item, $type );
				}
				?>
			</div>
		<?php } ?>
	</div>
	<?php
}

if ( ! function_exists( 'storeengine_render_icon' ) ) {
	/**
	 * Render Icon.
	 *
	 * @param string $icon
	 * @param string|string[] $args
	 *
	 * @return string|void
	 */
	function storeengine_render_icon( string $icon, $args = '' ) {
		$args = wp_parse_args( $args, [
			'return'      => false,
			'classes'     => '',
			'aria-hidden' => true,
		] );

		$return    = (bool) $args['return'];
		$classname = 'storeengine-icon storeengine-icon--' . $icon;

		if ( $args['classes'] ) {
			$classname .= ' ' . $args['classes'];
		}

		unset( $args['return'], $args['classes'] );

		if (
			! empty( $args['title'] ) ||
			! empty( $args['aria-label'] ) ||
			! empty( $args['aria-labelledby'] ) ||
			! empty( $args['aria-description'] ) ||
			! empty( $args['aria-describedby'] ) ||
			! $args['aria-hidden']
		) {
			unset( $args['aria-hidden'] );
		}

		$attrs = [];

		foreach ( $args as $k => $v ) {
			if ( is_bool( $v ) ) {
				$v = $v ? 'true' : 'false';
			}

			$attrs[] = esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}

		$output = '<span class="' . esc_attr( $classname ) . '" ' . implode( ' ', $attrs ) . '></span>';

		if ( $return ) {
			return $output;
		} else {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

function storeengine_print_dropdown_action_button( string $key, array $action, string $type = null ): void {
	if ( ! empty( $action['is-divider'] ) ) {
		?>
		<div class="storeengine-action-dropdown--divider"></div>
		<?php
		return;
	}

	$aria_label = $action['aria-label'] ?? $action['name'];
	$classes    = [ 'storeengine-action-dropdown--item storeengine-btn storeengine-btn--link' ];

	if ( ! empty( $action['classes'] ) && is_string( $action['classes'] ) ) {
		$classes[] = sanitize_html_class( $action['classes'] );
	}

	$classes[] = 'storeengine-btn--' . sanitize_html_class( $key );
	$classes[] = $type ? $type . '-' . sanitize_html_class( $key ) : '';
	$classes   = implode( ' ', array_filter( $classes ) );

	// Prepare escaped attribute string.
	$attributes  = 'class="' . esc_attr( $classes ) . '"';
	$attributes .= ' href="' . esc_url( $action['url'] ) . '"';
	$attributes .= ' aria-label="' . esc_attr( $aria_label ) . '"';

	if ( ! empty( $action['title'] ) ) {
		if ( true === $action['title'] ) {
			$title = $action['name'] ?? $action['aria-label'] ?? '';
			if ( $title ) {
				$attributes .= ' title="' . esc_attr( $title ) . '"';
			}
		} else {
			$attributes .= ' title="' . esc_attr( $action['title'] ) . '"';
		}
	}

	if ( ! empty( $action['target'] ) && '_blank' === $action['target'] ) {
		$attributes .= ' target="_blank"';
	}

	if ( ! empty( $action['data'] ) && is_array( $action['data'] ) ) {
		foreach ( $action['data'] as $key => $value ) {
			$attributes .= ' data-' . sanitize_title( $key ) . '="' . esc_attr( $value ) . '"';
		}
		unset( $action['data'] );
	}

	if ( ! empty( $action['styles'] ) ) {
		$styles = $action['styles'];
		if ( is_array( $action['styles'] ) ) {
			if ( wp_is_numeric_array( $action['styles'] ) ) {
				$styles = implode( ';', $action['styles'] );
			} else {
				$styles = '';
				foreach ( $action['styles'] as $key => $value ) {
					$styles .= sanitize_key( $key ) . ':' . esc_attr( $value ) . ';';
				}
			}
		}

		unset( $action['styles'] );

		if ( $styles && is_string( $styles ) ) {
			$attributes .= ' style="' . $styles . '"';
		}
	}

	?>
	<a <?php echo $attributes;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped above. ?>>
		<?php
		if ( ! empty( $action['icon'] ) ) {
			storeengine_render_icon( $action['icon'] );
		}

		if ( ! empty( $action['name'] ) ) {
			echo esc_html( $action['name'] );
		}
		?>
	</a>
	<?php
}

if ( ! function_exists( 'storeengine_direct_checkout_url_params' ) ) {
	function storeengine_direct_checkout_url_params() {
		if ( ! Helper::is_checkout() || ! isset( $_GET['product_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- request from email or outside shared link.
			return;
		}

		if ( ! Helper::cart() ) {
			return;
		}

		$product_id   = absint( wp_unslash( $_GET['product_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- request from email or outside shared link.
		$price_id     = isset( $_GET['price_id'] ) ? absint( wp_unslash( $_GET['price_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- request from email or outside shared link.
		$quantity     = isset( $_GET['qty'] ) ? absint( wp_unslash( $_GET['qty'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- request from email or outside shared link.
		$variation_id = isset( $_GET['variation_id'] ) ? absint( wp_unslash( $_GET['variation_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- request from email or outside shared link.

		if ( ! $price_id ) {
			$product = Helper::get_product( $product_id );
			if ( ! $product ) {
				return;
			}

			$prices = $product->get_prices();

			if ( empty( $prices ) ) {
				return;
			}

			$price_id = reset( $prices )->get_id();
		}

		Helper::cart()->clear_cart();
		Helper::cart()->add_product_to_cart( $price_id, $quantity, $variation_id );

		$coupon_code = sanitize_text_field( wp_unslash( $_GET['coupon'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- request from email or outside shared link.

		if ( ! empty( $coupon_code ) ) {
			Helper::cart()->apply_coupon( $coupon_code );
		}

		Helper::cart()->store_on_database();
	}
}

if ( ! function_exists( 'storeengine_print_time' ) ) {
	/**
	 * Print datetime with time tag.
	 *
	 * @TODO add support for string|int datetime input.
	 *
	 * @param StoreengineDatetime|null $datetime
	 * @param string|string[] $args
	 *
	 * @return void
	 */
	function storeengine_print_time( ?StoreengineDatetime $datetime, $args = [] ) {
		if ( is_string( $args ) ) {
			$args = [ 'format' => $args ];
		}

		$args = wp_parse_args( $args, [
			'format'         => 'Y-m-d H:i:s',
			'fallback'       => __( 'N/A', 'storeengine' ),
			'display_format' => _x(
				'MMM DD, YYYY',
				'Moment.js supported date format for user-dashboard order date',
				'storeengine'
			),
		] );

		if ( ! $datetime && is_scalar( $args['fallback'] ) && ! empty( $args['fallback'] ) ) {
			echo esc_html( $args['fallback'] );

			return;
		}

		?>
		<time datetime="<?php echo esc_attr( $datetime ); ?>" data-format="<?php echo esc_attr( $args['display_format'] ); ?>"><?php echo esc_html( $datetime->toLocal( $args['format'] ) ); ?></time>
		<?php
	}
}

/**
 * Get the quantity input args.
 *
 * Note, when autocomplete is enabled in firefox, it will overwrite actual value with what user entered last. So we default to off.
 *
 * @param array $args The arguments.
 * @param Price|null $price
 * @param SimpleProduct|VariableProduct|null $product Product.
 *
 * @return array
 */
function storeengine_get_quantity_args( array $args, ?Price $price = null, $product = null ): array {
	if ( is_null( $product ) && is_null( $price ) ) {
		$product = $GLOBALS['product'] ?? false;
	} elseif ( is_null( $product ) && $price ) {
		$product = $price->get_product();
	}

	$defaults = [
		'id'           => uniqid( 'quantity_' ),
		'name'         => 'quantity',
		'pattern'      => apply_filters( 'storeengine/quantity_input_pattern', '[0-9]*' ),
		'inputmode'    => apply_filters( 'storeengine/quantity_input_inputmode', 'numeric' ), // Or decimal.
		'placeholder'  => apply_filters( 'storeengine/quantity_input_placeholder', '', $price ),
		'autocomplete' => apply_filters( 'storeengine/quantity_input_autocomplete', 'off', $price ),
		'disabled'     => false,
		'readonly'     => false,
	];

	if ( $product ) {
		$defaults['max_qty']      = - 1; // $product->get_max_purchase_quantity();
		$defaults['min_qty']      = 1; // $product->get_min_purchase_quantity();
		$defaults['step']         = 1; // $product->get_purchase_quantity_step();
		$defaults['product_name'] = $product->get_name();
	} else {
		$defaults['max_qty']      = apply_filters( 'storeengine/quantity_input_max', - 1, $product, $price );
		$defaults['min_qty']      = apply_filters( 'storeengine/quantity_input_min', 1, $product, $price );
		$defaults['step']         = apply_filters( 'storeengine/quantity_input_step', 1, $product, $price );
		$defaults['product_name'] = '';
	}

	// translators: %s. Product name.
	$args['label'] = ! empty( $args['product_name'] ) ? sprintf( __( '%s quantity', 'storeengine' ), wp_strip_all_tags( $args['product_name'] ) ) : __( 'Quantity', 'storeengine' );

	/**
	 * Filters all quantity input args.
	 *
	 * @param array $args The arguments.
	 * @param SimpleProduct|VariableProduct|null $product The product.
	 *
	 * @return array
	 * @since 1.6.9
	 */
	$args = apply_filters( 'storeengine/quantity_input_args', wp_parse_args( $args, $defaults ), $product );

	// Apply correction to min/max args - min cannot be lower than 0.
	$args['min_qty'] = max( $args['min_qty'], 0 );
	$args['max_qty'] = 0 < $args['max_qty'] ? $args['max_qty'] : '';

	// Max cannot be lower than min if defined.
	if ( '' !== $args['max_qty'] && $args['max_qty'] < $args['min_qty'] ) {
		$args['max_qty'] = $args['min_qty'];
	}

	// Default value should be the min value unless defined.
	$args['quantity'] = $args['quantity'] ?? $defaults['min_qty'];

	/**
	 * The input type attribute will generally be 'number' unless the quantity cannot be changed, in which case
	 * it will be set to 'hidden'. An exception is made for non-hidden readonly inputs: in this case we set the
	 * type to 'text' (this prevents most browsers from rendering increment/decrement arrows, which are useless
	 * and/or confusing in this context).
	 */
	$type = $args['min_qty'] > 0 && $args['min_qty'] === $args['max_qty'] ? 'hidden' : 'number';
	$type = $args['readonly'] && 'hidden' !== $type ? 'text' : $type;

	// Store-wide "Hide quantity selector" setting — force quantity to 1 and render
	// a hidden input (the quantity-input template keeps submitting the value).
	if ( Helper::get_settings( 'hide_quantity_selector' ) ) {
		$args['min_qty']  = 1;
		$args['max_qty']  = '';
		$args['quantity'] = 1;
		$type             = 'hidden';
	}

	/**
	 * Controls the quantity input's type attribute.
	 *
	 * @param string $type A valid input type attribute value, usually 'number' or 'hidden'.
	 *
	 * @since 1.6.9
	 */
	$args['type'] = apply_filters( 'storeengine/quantity_input_type', $type );

	return $args;
}

if ( ! function_exists( 'storeengine_quantity_input' ) ) {
	/**
	 * Output the quantity input for any form.
	 *
	 * @param array|string $args Args for the input.
	 * @param Price|null $price
	 * @param SimpleProduct|VariableProduct|null $product Product.
	 * @param boolean $echo Whether to return or echo|string.
	 *
	 * @return string|void
	 */
	function storeengine_quantity_input( $args = [], ?Price $price = null, $product = null, bool $echo = true ) {
		$args = storeengine_get_quantity_args( $args, $price, $product );

		if ( ! $echo ) {
			ob_start();
			Template::get_template( 'global/quantity-input.php', $args );

			return ob_get_clean();
		}

		Template::get_template( 'global/quantity-input.php', $args );
	}
}

if ( ! function_exists( 'storeengine_oops_message' ) ) {
	/**
	 * Renders the no-content template.
	 *
	 * @param string|array{image?:string,title?:string,message?:string,classes?:string} $args
	 *
	 * @return void
	 */
	function storeengine_oops_message( $args = [] ) {
		$args = wp_parse_args( $args, [
			'image'   => null,
			'title'   => null,
			'message' => null,
			'classes' => null,
		] );

		Template::get_template( 'global/oops.php', $args );
	}
}

if ( ! function_exists( 'storeengine_table_oops_message' ) ) {
	/**
	 * Renders the no-content template.
	 *
	 * @param string|array{columns:int,image?:string,title?:string,message?:string,classes?:string} $args
	 *
	 * @return void
	 */
	function storeengine_table_oops_message( $args ) {
		$args = wp_parse_args( $args, [
			'columns' => 100,
			'image'   => null,
			'title'   => null,
			'message' => null,
			'classes' => null,
		] );

		$columns = $args['columns'];
		unset( $args['columns'] );

		Template::get_template( 'global/table-oops.php', [
			'columns' => $columns,
			'args'    => $args,
		] );
	}
}

