<?php
/**
 * Account → Notifications sub-tab.
 *
 * Customer-facing email opt-in toggles. Stored as user_meta with the
 * `_storeengine_notif_*` prefix; read at send-time via
 * `Helper::should_send_notification()`. Order-status emails are
 * transactional and locked on.
 *
 * @var \StoreEngine\Classes\Customer $customer
 */

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storeengine_user_id   = $customer ? $customer->get_id() : get_current_user_id();
$storeengine_user      = $storeengine_user_id ? get_user_by( 'id', $storeengine_user_id ) : null;
$storeengine_is_vendor = $storeengine_user && in_array( 'storeengine_vendor', (array) $storeengine_user->roles, true );

$storeengine_marketing_on        = Helper::should_send_notification( $storeengine_user_id, 'marketing' );
$storeengine_vendor_new_order_on = $storeengine_is_vendor && Helper::should_send_notification( $storeengine_user_id, 'vendor_new_order' );

$storeengine_saved = isset( $_GET['notif_saved'] ) && '1' === $_GET['notif_saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="storeengine-frontend-dashboard-page storeengine-frontend-dashboard-page--notifications">
	<div class="storeengine-container">
		<div class="storeengine-row">
			<div class="storeengine-frontend-account">
				<div class="storeengine-edit-account">
					<h4 class="storeengine-frontend-heading"><?php esc_html_e( 'Email notifications', 'storeengine' ); ?></h4>

					<?php if ( $storeengine_saved ) : ?>
						<p class="storeengine-notice storeengine-notice--success">
							<?php esc_html_e( 'Notification preferences saved.', 'storeengine' ); ?>
						</p>
					<?php endif; ?>

					<form class="storeengine-notifications-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
						<input type="hidden" name="action" value="storeengine/frontend_dashboard_save_notifications">
						<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>

						<div class="storeengine-form-group">
							<label class="storeengine-form__inner storeengine-form__inner--check">
								<input type="checkbox" name="marketing" value="1" class="storeengine-input-check" <?php checked( $storeengine_marketing_on ); ?>>
								<span>
									<strong><?php esc_html_e( 'Marketing emails', 'storeengine' ); ?></strong><br>
									<small><?php esc_html_e( 'Promotions, sales, and product news. Uncheck to opt out.', 'storeengine' ); ?></small>
								</span>
							</label>
						</div>

						<div class="storeengine-form-group">
							<label class="storeengine-form__inner storeengine-form__inner--check storeengine-form__inner--check-locked">
								<input type="checkbox" class="storeengine-input-check" checked disabled>
								<span>
									<strong><?php esc_html_e( 'Order status updates', 'storeengine' ); ?></strong><br>
									<small><?php esc_html_e( 'Receipts, shipping updates, refund confirmations. Required for legitimate purposes — cannot be disabled.', 'storeengine' ); ?></small>
								</span>
							</label>
						</div>

						<?php if ( $storeengine_is_vendor ) : ?>
						<div class="storeengine-form-group">
							<label class="storeengine-form__inner storeengine-form__inner--check">
								<input type="checkbox" name="vendor_new_order" value="1" class="storeengine-input-check" <?php checked( $storeengine_vendor_new_order_on ); ?>>
								<span>
									<strong><?php esc_html_e( 'New-order emails for my store', 'storeengine' ); ?></strong><br>
									<small><?php esc_html_e( 'Email me when a customer places an order from my products.', 'storeengine' ); ?></small>
								</span>
							</label>
						</div>
						<?php endif; ?>

						<button type="submit" class="storeengine-btn storeengine-btn--md storeengine-btn--preset-blue"><?php esc_html_e( 'Save', 'storeengine' ); ?></button>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
