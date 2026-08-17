<?php
/**
 * Vendor withdrawals page — balance card + new request form + history.
 *
 * @var \StoreEngine\Addons\MultiVendor\Classes\Vendor $vendor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\MultiVendor\Classes\Balance;
use StoreEngine\Addons\MultiVendor\Settings;
use StoreEngine\Utils\Formatting;

global $wpdb;

$storeengine_user_id  = $vendor->get_user_id();
$storeengine_balance  = Balance::for_vendor( $storeengine_user_id );
$storeengine_lifetime = Balance::lifetime_earned( $storeengine_user_id );
$storeengine_paid     = Balance::paid_total( $storeengine_user_id );
$storeengine_min      = (float) Settings::get( 'min_withdraw_amount', 0 );

$storeengine_has_payment_method = $vendor->get_payment_method() && ! empty( $vendor->get_payment_data() );
$storeengine_pm_url             = \StoreEngine\Utils\Helper::get_current_dashboard_endpoint_url( 'vendor-payment-method' );

$storeengine_notice = '';

if ( isset( $_POST['storeengine_vendor_withdraw_nonce'] )
	&& wp_verify_nonce( sanitize_key( $_POST['storeengine_vendor_withdraw_nonce'] ), 'storeengine_vendor_withdraw' )
) {
	$storeengine_amount = isset( $_POST['amount'] ) ? round( (float) $_POST['amount'], 2 ) : 0;
	$storeengine_note   = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

	if ( ! $storeengine_has_payment_method ) {
		$storeengine_notice = [ 'type' => 'error', 'msg' => __( 'Add a payment method first.', 'storeengine' ) ];
	} elseif ( $storeengine_amount <= 0 ) {
		$storeengine_notice = [ 'type' => 'error', 'msg' => __( 'Enter a positive amount.', 'storeengine' ) ];
	} elseif ( $storeengine_amount < $storeengine_min ) {
		/* translators: %s: formatted minimum withdrawal amount */
		$storeengine_notice = [ 'type' => 'error', 'msg' => sprintf( __( 'Minimum withdrawal is %s.', 'storeengine' ), Formatting::price( $storeengine_min ) ) ];
	} elseif ( $storeengine_amount > $storeengine_balance ) {
		$storeengine_notice = [ 'type' => 'error', 'msg' => __( 'Amount exceeds your available balance.', 'storeengine' ) ];
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert of a withdrawal request into a custom StoreEngine table; no cache layer applies.
		$wpdb->insert( $wpdb->prefix . 'storeengine_vendor_withdrawals', [
			'user_id'        => $storeengine_user_id,
			'amount'         => $storeengine_amount,
			'status'         => 'pending',
			'payment_method' => $vendor->get_payment_method(),
			'payment_data'   => wp_json_encode( $vendor->get_payment_data() ),
			'vendor_note'    => $storeengine_note,
			'requested_at'   => current_time( 'mysql', 1 ),
		] );
		do_action( 'storeengine/multi_vendor/withdrawal_requested', (int) $wpdb->insert_id, $storeengine_user_id, $storeengine_amount );
		$storeengine_notice  = [ 'type' => 'success', 'msg' => __( 'Withdrawal request submitted.', 'storeengine' ) ];
		// Refresh balance after the new pending row reduces it.
		$storeengine_balance = Balance::for_vendor( $storeengine_user_id );
	}
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%d) read of the current vendor's withdrawals from a custom StoreEngine table; per-request, not cacheable.
$storeengine_rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT id, amount, status, requested_at, processed_at, admin_note FROM `{$wpdb->prefix}storeengine_vendor_withdrawals` WHERE user_id = %d ORDER BY requested_at DESC LIMIT 50",
	$storeengine_user_id
) );
?>
<div class="storeengine-vendor-withdraw">

	<?php if ( $storeengine_notice ) : ?>
		<div class="storeengine-notice storeengine-notice--<?php echo esc_attr( $storeengine_notice['type'] ); ?>">
			<?php echo esc_html( $storeengine_notice['msg'] ); ?>
		</div>
	<?php endif; ?>

	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px;">
		<div class="storeengine-stat-card">
			<div class="storeengine-stat-card__label"><?php esc_html_e( 'Available balance', 'storeengine' ); ?></div>
			<div class="storeengine-stat-card__value"><?php echo wp_kses_post( Formatting::price( $storeengine_balance ) ); ?></div>
		</div>
		<div class="storeengine-stat-card">
			<div class="storeengine-stat-card__label"><?php esc_html_e( 'Lifetime earned', 'storeengine' ); ?></div>
			<div class="storeengine-stat-card__value"><?php echo wp_kses_post( Formatting::price( $storeengine_lifetime ) ); ?></div>
		</div>
		<div class="storeengine-stat-card">
			<div class="storeengine-stat-card__label"><?php esc_html_e( 'Paid out', 'storeengine' ); ?></div>
			<div class="storeengine-stat-card__value"><?php echo wp_kses_post( Formatting::price( $storeengine_paid ) ); ?></div>
		</div>
		<div class="storeengine-stat-card">
			<div class="storeengine-stat-card__label"><?php esc_html_e( 'Minimum withdrawal', 'storeengine' ); ?></div>
			<div class="storeengine-stat-card__value"><?php echo wp_kses_post( Formatting::price( $storeengine_min ) ); ?></div>
		</div>
	</div>

	<?php if ( ! $storeengine_has_payment_method ) : ?>
		<div class="storeengine-notice storeengine-notice--error">
			<?php
			printf(
				/* translators: %s: payment method link */
				esc_html__( 'Add a %s before requesting a withdrawal.', 'storeengine' ),
				'<a href="' . esc_url( $storeengine_pm_url ) . '">' . esc_html__( 'payment method', 'storeengine' ) . '</a>'
			);
			?>
		</div>
	<?php else : ?>
		<form method="post" class="storeengine-form" style="margin-bottom:24px;">
			<?php wp_nonce_field( 'storeengine_vendor_withdraw', 'storeengine_vendor_withdraw_nonce' ); ?>
			<h3 style="margin:0 0 10px;"><?php esc_html_e( 'Request a withdrawal', 'storeengine' ); ?></h3>
			<div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;align-items:end;">
				<p class="storeengine-form-row">
					<label class="storeengine-form-row__label"><?php esc_html_e( 'Amount', 'storeengine' ); ?></label>
					<input type="number" name="amount" step="0.01" min="<?php echo esc_attr( (string) $storeengine_min ); ?>" max="<?php echo esc_attr( (string) $storeengine_balance ); ?>" required>
				</p>
				<p class="storeengine-form-row">
					<label class="storeengine-form-row__label"><?php esc_html_e( 'Note (optional)', 'storeengine' ); ?></label>
					<input type="text" name="note">
				</p>
			</div>
			<p class="storeengine-form-row">
				<button type="submit" class="storeengine-btn storeengine-btn--md storeengine-btn--preset-blue">
					<?php esc_html_e( 'Request withdrawal', 'storeengine' ); ?>
				</button>
			</p>
		</form>
	<?php endif; ?>

	<h3 class="storeengine-vendor-withdraw__subheading"><?php esc_html_e( 'History', 'storeengine' ); ?></h3>

	<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper">
		<table class="storeengine-dashboard__table storeengine-dashboard__table--vendor-withdrawals">
			<thead>
				<tr>
					<th scope="col" class="col-date"><?php esc_html_e( 'Date', 'storeengine' ); ?></th>
					<th scope="col" class="col-amount"><?php esc_html_e( 'Amount', 'storeengine' ); ?></th>
					<th scope="col" class="col-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
					<th scope="col" class="col-processed"><?php esc_html_e( 'Processed', 'storeengine' ); ?></th>
					<th scope="col" class="col-note"><?php esc_html_e( 'Admin note', 'storeengine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $storeengine_rows ) ) : ?>
					<tr class="storeengine-dashboard__table-empty">
						<td colspan="5"><?php esc_html_e( 'No withdrawal requests yet.', 'storeengine' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $storeengine_rows as $storeengine_row ) : ?>
						<tr>
							<td class="col-date" data-title="<?php esc_attr_e( 'Date', 'storeengine' ); ?>"><?php echo esc_html( (string) $storeengine_row->requested_at ); ?></td>
							<td class="col-amount col-num" data-title="<?php esc_attr_e( 'Amount', 'storeengine' ); ?>"><?php echo wp_kses_post( Formatting::price( (float) $storeengine_row->amount ) ); ?></td>
							<td class="col-status" data-title="<?php esc_attr_e( 'Status', 'storeengine' ); ?>"><span class="storeengine-vendors__status storeengine-vendors__status--<?php echo esc_attr( $storeengine_row->status ); ?>"><?php echo esc_html( $storeengine_row->status ); ?></span></td>
							<td class="col-processed" data-title="<?php esc_attr_e( 'Processed', 'storeengine' ); ?>"><?php echo $storeengine_row->processed_at ? esc_html( $storeengine_row->processed_at ) : '—'; ?></td>
							<td class="col-note" data-title="<?php esc_attr_e( 'Admin note', 'storeengine' ); ?>"><?php echo esc_html( (string) ( $storeengine_row->admin_note ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
