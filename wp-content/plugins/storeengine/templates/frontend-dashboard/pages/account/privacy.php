<?php
/**
 * Account → Privacy sub-tab.
 *
 * Defers to WP core's existing privacy-export and erasure-request flows
 * (`wp_create_user_request()`). Both buttons trigger an email confirmation
 * the user must click; WP cron handles ZIP generation / erasure after
 * confirmation. Zero new infra here — we just trigger native WP.
 *
 * @var \StoreEngine\Classes\Customer $customer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storeengine_user_id = $customer ? $customer->get_id() : get_current_user_id();
$storeengine_user    = $storeengine_user_id ? get_user_by( 'id', $storeengine_user_id ) : null;
if ( ! $storeengine_user ) {
	return;
}

// Detect any pending request so we don't double-fire while WP is still
// awaiting confirmation or processing.
$storeengine_has_pending_export  = false;
$storeengine_has_pending_erasure = false;
if ( function_exists( 'wp_get_user_request' ) || class_exists( 'WP_User_Query' ) ) {
	$storeengine_pending = get_posts( [
		'post_type'      => 'user_request',
		'posts_per_page' => 5,
		'post_status'    => [ 'request-pending', 'request-confirmed' ],
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small, capped (5 rows) lookup of the current user's own privacy requests; core stores the user id only in post meta.
		'meta_query'     => [
			[
				'key'   => '_wp_user_request_user_id',
				'value' => $storeengine_user_id,
			],
		],
	] );
	foreach ( $storeengine_pending as $storeengine_p ) {
		if ( 'export_personal_data' === $storeengine_p->post_name ) {
			$storeengine_has_pending_export = true;
		}
		if ( 'remove_personal_data' === $storeengine_p->post_name ) {
			$storeengine_has_pending_erasure = true;
		}
	}
}

$storeengine_msg = isset( $_GET['privacy_msg'] ) ? sanitize_key( wp_unslash( $_GET['privacy_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="storeengine-frontend-dashboard-page storeengine-frontend-dashboard-page--privacy">
	<div class="storeengine-container">
		<div class="storeengine-row">
			<div class="storeengine-frontend-account">
				<div class="storeengine-edit-account">
					<h4 class="storeengine-frontend-heading"><?php esc_html_e( 'Privacy & data', 'storeengine' ); ?></h4>

					<?php if ( 'export_requested' === $storeengine_msg ) : ?>
						<p class="storeengine-notice storeengine-notice--success">
							<?php esc_html_e( "We've emailed you to confirm the data export. Click the link to start the export.", 'storeengine' ); ?>
						</p>
					<?php elseif ( 'erasure_requested' === $storeengine_msg ) : ?>
						<p class="storeengine-notice storeengine-notice--success">
							<?php esc_html_e( "We've emailed you to confirm the account deletion request. Click the link to confirm.", 'storeengine' ); ?>
						</p>
					<?php elseif ( 'error' === $storeengine_msg ) : ?>
						<p class="storeengine-notice storeengine-notice--error">
							<?php esc_html_e( 'Something went wrong. Please try again.', 'storeengine' ); ?>
						</p>
					<?php endif; ?>

					<div class="storeengine-privacy-card">
						<strong class="storeengine-privacy-card__title"><?php esc_html_e( 'Download my data', 'storeengine' ); ?></strong>
						<p class="storeengine-privacy-card__desc"><small><?php esc_html_e( 'Get a copy of the personal data we have stored for you. We will email you a download link after you confirm the request.', 'storeengine' ); ?></small></p>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
							<input type="hidden" name="action" value="storeengine/frontend_dashboard_request_data_export">
							<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
							<button type="submit" class="storeengine-btn storeengine-btn--md storeengine-btn--preset-blue" <?php disabled( $storeengine_has_pending_export ); ?>>
								<?php
								echo esc_html(
									$storeengine_has_pending_export
										? __( 'Export already pending', 'storeengine' )
										: __( 'Download my data', 'storeengine' )
								);
								?>
							</button>
						</form>
					</div>

					<div class="storeengine-privacy-card storeengine-privacy-card--danger">
						<strong class="storeengine-privacy-card__title storeengine-privacy-card__title--danger"><?php esc_html_e( 'Delete my account', 'storeengine' ); ?></strong>
						<p class="storeengine-privacy-card__desc"><small><?php esc_html_e( 'Permanently delete your account and personal data. This cannot be undone. We will email you to confirm before erasure.', 'storeengine' ); ?></small></p>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" onsubmit="return confirm( <?php echo esc_js( wp_json_encode( __( 'Are you sure? This permanently deletes your account.', 'storeengine' ) ) ); ?> );">
							<input type="hidden" name="action" value="storeengine/frontend_dashboard_request_account_erasure">
							<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
							<button type="submit" class="storeengine-btn storeengine-btn--md storeengine-btn--preset-red" <?php disabled( $storeengine_has_pending_erasure ); ?>>
								<?php
								echo esc_html(
									$storeengine_has_pending_erasure
										? __( 'Deletion already pending', 'storeengine' )
										: __( 'Delete my account', 'storeengine' )
								);
								?>
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
