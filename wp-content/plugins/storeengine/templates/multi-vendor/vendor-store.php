<?php
/**
 * Public vendor store page.
 *
 * Renders the storefront header, a vendor banner with store info, the standard
 * product loop (already scoped by pre_get_posts to author=vendor_id), and the
 * storefront footer. The product cards reuse `content-product.php` so they
 * match the existing shop archive look.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\MultiVendor\Settings as MVSettings;
use StoreEngine\Addons\MultiVendor\StorePage;

$storeengine_vendor = StorePage::current_vendor();
if ( ! $storeengine_vendor ) {
	return;
}

$storeengine_user        = get_user_by( 'id', $storeengine_vendor->get_user_id() );
$storeengine_avatar      = $storeengine_user ? get_avatar( $storeengine_user->ID, 96 ) : '';
$storeengine_store_name  = $storeengine_vendor->get_store_name();
$storeengine_store_slug  = $storeengine_vendor->get_store_slug();
$storeengine_bio         = $storeengine_user ? get_user_meta( $storeengine_user->ID, 'description', true ) : '';
$storeengine_joined      = $storeengine_vendor->get_date_approved() ?? $storeengine_vendor->get_date_registered();
$storeengine_joined_disp = $storeengine_joined ? mysql2date( get_option( 'date_format' ), $storeengine_joined ) : '';

storeengine_get_header();

do_action( 'storeengine/multi_vendor/before_store_page', $storeengine_vendor );
?>
<div class="storeengine-vendor-store">
	<div class="storeengine-container">
		<header class="storeengine-vendor-store__header">
			<?php if ( $storeengine_avatar ) : ?>
				<div class="storeengine-vendor-store__avatar"><?php echo $storeengine_avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>
			<div class="storeengine-vendor-store__meta">
				<h1 class="storeengine-vendor-store__title">
					<?php echo esc_html( $storeengine_store_name ); ?>
					<?php foreach ( $storeengine_vendor->get_badges() as $storeengine_badge_slug ) : ?>
						<span
							class="storeengine-vendor-badge"
							style="background:<?php echo esc_attr( MVSettings::badge_color( $storeengine_badge_slug ) ); ?>;"
						>
							<?php echo esc_html( MVSettings::badge_label( $storeengine_badge_slug ) ); ?>
						</span>
					<?php endforeach; ?>
				</h1>
				<?php if ( $storeengine_store_slug ) : ?>
					<p class="storeengine-vendor-store__slug"><code><?php echo esc_html( $storeengine_store_slug ); ?></code></p>
				<?php endif; ?>
				<?php if ( $storeengine_joined_disp ) : ?>
					<p class="storeengine-vendor-store__joined">
						<?php
						printf(
							/* translators: %s: date */
							esc_html__( 'Member since %s', 'storeengine' ),
							esc_html( $storeengine_joined_disp )
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( $storeengine_bio ) : ?>
					<div class="storeengine-vendor-store__bio">
						<?php echo wp_kses_post( wpautop( $storeengine_bio ) ); ?>
					</div>
				<?php endif; ?>
			</div>
		</header>

		<div class="storeengine-row">
			<div class="storeengine-col-12">
				<?php
				/**
				 * Reuses the same product loop the shop archive uses, so cards match the
				 * theme styling. The main query has been scoped to author=vendor_id by
				 * StorePage::scope_main_query().
				 */
				do_action( 'storeengine/templates/archive_product_content' );
				?>
			</div>
		</div>
	</div>
</div>
<?php
do_action( 'storeengine/multi_vendor/after_store_page', $storeengine_vendor );

storeengine_get_footer();
