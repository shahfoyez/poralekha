<?php
/**
 * Vendor returns page.
 *
 * Lists all RMAs whose products belong to the current vendor and lets the
 * vendor record an approve/reject decision. The decision is advisory — the
 * admin still officially advances the RMA via the staff-side controller —
 * but it surfaces the vendor's intent and is exposed via REST so external
 * systems / webhooks can react to it.
 *
 * Hard-gated on the Returns Pro addon being active. When it isn't, vendors
 * see a quiet notice instead of a 500.
 */

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var \StoreEngine\Addons\MultiVendor\Classes\Vendor $vendor */
if ( empty( $vendor ) || ! $vendor->is_approved() ) {
	return;
}

if ( ! class_exists( '\\StoreEnginePro\\Addons\\Returns\\Classes\\ReturnsService' ) ) {
	echo '<div class="storeengine-vendor-returns"><h2>' . esc_html__( 'Returns', 'storeengine' ) . '</h2>';
	echo '<p>' . esc_html__( 'The Returns add-on is not active.', 'storeengine' ) . '</p></div>';
	return;
}

$storeengine_user_id = (int) $vendor->get_user_id();

// Handle the inline POST so vendors can record a decision without a JS
// dependency. CSRF-protected via wp_nonce_field below.
if (
	( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified two lines below before any state change.
	! empty( $_POST['storeengine_vendor_return_decision_nonce'] ) &&
	wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['storeengine_vendor_return_decision_nonce'] ) ), 'storeengine_vendor_return_decision' )
) {
	$storeengine_return_id = isset( $_POST['return_id'] ) ? (int) $_POST['return_id'] : 0;
	$storeengine_decision  = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
	if ( $storeengine_return_id && in_array( $storeengine_decision, [ 'approved', 'rejected' ], true ) ) {
		\StoreEnginePro\Addons\Returns\Classes\ReturnsService::set_vendor_decision( $storeengine_return_id, $storeengine_decision, $storeengine_user_id );
	}
}

$storeengine_rows = \StoreEnginePro\Addons\Returns\Classes\ReturnsService::find_for_vendor( $storeengine_user_id, [ 'per_page' => 100 ] );

$storeengine_status_label = static function ( string $status ): string {
	$labels = [
		'requested' => __( 'Requested', 'storeengine' ),
		'approved'  => __( 'Approved', 'storeengine' ),
		'received'  => __( 'Received', 'storeengine' ),
		'refunded'  => __( 'Refunded', 'storeengine' ),
		'cancelled' => __( 'Cancelled', 'storeengine' ),
	];
	return $labels[ $status ] ?? ucfirst( $status );
};
?>
<div class="storeengine-vendor-returns">

	<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper">
		<table class="storeengine-dashboard__table storeengine-dashboard__table--vendor-returns">
			<thead>
				<tr>
					<th scope="col" class="col-rma"><?php esc_html_e( 'RMA #', 'storeengine' ); ?></th>
					<th scope="col" class="col-order"><?php esc_html_e( 'Order', 'storeengine' ); ?></th>
					<th scope="col" class="col-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
					<th scope="col" class="col-refund"><?php esc_html_e( 'Refund', 'storeengine' ); ?></th>
					<th scope="col" class="col-decision"><?php esc_html_e( 'Your decision', 'storeengine' ); ?></th>
					<th scope="col" class="col-created"><?php esc_html_e( 'Created', 'storeengine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $storeengine_rows ) ) : ?>
					<tr class="storeengine-dashboard__table-empty">
						<td colspan="6"><?php esc_html_e( 'No returns yet for your products.', 'storeengine' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $storeengine_rows as $storeengine_r ) :
						$storeengine_decision = isset( $storeengine_r->vendor_decision ) ? (string) $storeengine_r->vendor_decision : '';
						$storeengine_can_decide = '' === $storeengine_decision && in_array( (string) $storeengine_r->status, [ 'requested', 'approved' ], true );
					?>
						<tr>
							<td class="col-rma col-mono" data-title="<?php esc_attr_e( 'RMA #', 'storeengine' ); ?>"><?php echo esc_html( (string) $storeengine_r->rma_number ); ?></td>
							<td class="col-order" data-title="<?php esc_attr_e( 'Order', 'storeengine' ); ?>">#<?php echo (int) $storeengine_r->order_id; ?></td>
							<td class="col-status" data-title="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
								<?php echo esc_html( $storeengine_status_label( (string) $storeengine_r->status ) ); ?>
							</td>
							<td class="col-refund col-num" data-title="<?php esc_attr_e( 'Refund', 'storeengine' ); ?>">
								<?php echo wp_kses_post( \StoreEngine\Utils\Formatting::price( (float) $storeengine_r->refund_amount ) ); ?>
							</td>
							<td class="col-decision" data-title="<?php esc_attr_e( 'Your decision', 'storeengine' ); ?>">
								<?php if ( $storeengine_decision ) : ?>
									<strong><?php echo esc_html( ucfirst( $storeengine_decision ) ); ?></strong>
									<?php if ( ! empty( $storeengine_r->vendor_decided_at ) ) : ?>
										<div class="storeengine-text-muted storeengine-text-small">
											<?php echo esc_html( (string) $storeengine_r->vendor_decided_at ); ?>
										</div>
									<?php endif; ?>
								<?php elseif ( $storeengine_can_decide ) : ?>
									<div class="storeengine-vendor-returns__decision-buttons">
										<form class="storeengine-vendor-returns__decision-form" method="post">
											<?php wp_nonce_field( 'storeengine_vendor_return_decision', 'storeengine_vendor_return_decision_nonce' ); ?>
											<input type="hidden" name="return_id" value="<?php echo (int) $storeengine_r->id; ?>">
											<input type="hidden" name="decision" value="approved">
											<button type="submit" class="storeengine-btn storeengine-btn--xs storeengine-btn--preset-blue">
												<?php esc_html_e( 'Approve', 'storeengine' ); ?>
											</button>
										</form>
										<form class="storeengine-vendor-returns__decision-form" method="post">
											<?php wp_nonce_field( 'storeengine_vendor_return_decision', 'storeengine_vendor_return_decision_nonce' ); ?>
											<input type="hidden" name="return_id" value="<?php echo (int) $storeengine_r->id; ?>">
											<input type="hidden" name="decision" value="rejected">
											<button type="submit" class="storeengine-btn storeengine-btn--xs storeengine-btn--preset-transparent">
												<?php esc_html_e( 'Reject', 'storeengine' ); ?>
											</button>
										</form>
									</div>
								<?php else : ?>
									<span class="storeengine-text-muted">—</span>
								<?php endif; ?>
							</td>
							<td class="col-created col-mono col-small" data-title="<?php esc_attr_e( 'Created', 'storeengine' ); ?>">
								<?php echo esc_html( (string) $storeengine_r->date_created ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<p class="storeengine-vendor-returns__hint">
		<?php esc_html_e(
			'Your decision is shared with the store admin, who officially approves or rejects the return.',
			'storeengine'
		); ?>
	</p>
</div>
