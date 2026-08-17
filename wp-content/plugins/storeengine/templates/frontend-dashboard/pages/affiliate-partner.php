<?php
/**
 * @var string $referral_url
 * @var string $referral_code
 * @var string $referral_param
 */

use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/**
 * @vars
 *
 * @var string $total_amount
 * @var string $available_amount
 * @var array $withdraw_history
 * @var string $withdraw_method_type
 * @var int $current_user_id
 * @var string $payment_settings_url
 * @var bool $show_withdraw_button
 * @var string $referral_url
 */

$methods         = [
	'paypal' => [
		'label' => __( 'PayPal', 'storeengine' ),
		'icon'  => 'paypal',
		'value' => 'paypal',
	],
	'echeck' => [
		'label' => __( 'E-check', 'storeengine' ),
		'icon'  => 'e-check',
		'value' => 'echeck',
	],
	'bank'   => [
		'label' => __( 'Bank Transfer', 'storeengine' ),
		'icon'  => 'bank-transfer',
		'value' => 'bank_transfer',
	],
];
$payout_statuses = [
	'completed' => __( 'completed', 'storeengine' ),
	'pending'   => __( 'pending', 'storeengine' ),
];

?>

<div class="storeengine-dashboard-withdrawal-info-wrapper storeengine-affiliate-overview">
	<?php if ( ! empty( $referral_url ) ) : ?>
		<div class="storeengine-affiliate-overview__section">
			<span class="storeengine-cta-sub-title"><?php esc_html_e( 'Your Referral Link', 'storeengine' ); ?></span>
			<p class="storeengine-cta-desc" style="margin:2px 0 12px; color:#6b7280; font-size:13px;"><?php esc_html_e( 'Share this link to earn commission on every sale it brings.', 'storeengine' ); ?></p>
			<div class="storeengine-affiliate-referral-notice" style="display:flex; align-items:center; gap:10px; background:#f6f7f9; border:1px solid #dddedf; border-radius:8px; padding:10px 14px;">
				<span class="storeengine-icon storeengine-icon--info-primary" aria-hidden="true"></span>
				<input id="storeengine-input--referral-url" type="text" value="<?php echo esc_url( $referral_url ); ?>" readonly style="flex:1; min-width:0; border:none; background:transparent; box-shadow:none; outline:none; padding:0; font-size:14px; color:inherit;"/>
				<button class="storeengine-btn storeengine-btn--sm storeengine-btn--preset-blue storeengine-btn--referral-url" type="button" style="flex-shrink:0;">
					<span class="storeengine-icon storeengine-icon--duplicate" aria-hidden="true"></span>
					<?php esc_html_e( 'Copy', 'storeengine' ); ?>
				</button>
			</div>
		</div>

		<hr style="border:0; border-top:1px solid #dddedf; margin:20px 0;">
	<?php endif; ?>

	<div class="storeengine-affiliate-overview__section">
		<div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
			<div>
				<span class="storeengine-cta-sub-title"><?php esc_html_e( 'Available Balance', 'storeengine' ); ?></span>
				<h4 class="storeengine-cta-title" style="margin:4px 0 0;"><?php echo wp_kses_post( Formatting::price( $available_amount ) ); ?></h4>
			</div>
			<a href="<?php echo esc_url( $payment_settings_url ); ?>" class="storeengine-btn storeengine-btn--sm storeengine-btn--preset-gray" style="flex-shrink:0;"><?php esc_html_e( 'Withdrawal settings', 'storeengine' ); ?></a>
		</div>

	<?php if ( $show_withdraw_button ) : ?>
	<form id="storeengine_withdrawal" class="storeengine-dashboard-instructor-earning-withdrawal" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'storeengine_nonce', 'security' ); ?>
		<input type="hidden" name="action" value="storeengine_affiliate/affiliate_earning_withdrawal">
		<div class="storeengine-dashboard-instructor-earning-withdrawal_type storeengine-my-2">
			<?php

			$withdrawal_method = $methods[ $withdraw_method_type ] ?? null;
			if ( $withdrawal_method ) {
				$icon   = 'storeengine-icon storeengine-icon--' . $withdrawal_method['icon'];
				$method = $withdrawal_method['value'] ?? $withdraw_method_type;
				$label  = $withdrawal_method['label'] ?? $withdraw_method_type;
				?>
				<input type="hidden" name="withdrawal_type" value="<?php echo esc_attr( $method ); ?>">

				<label for="withdrawal_amount" class="storeengine-withdraw-title">
					<?php printf(
						// translators: %1$s: Withdrawal Method Icon. %2$s: Withdrawal Method Name.
						esc_html__( 'Withdrawal Amount (%1$s %2$s)', 'storeengine' ),
						'<span class="' . esc_attr( $icon ) . '"></span>',
						esc_html( $label )
					); ?>
				</label>
				<?php
			}
			?>
		</div>
		<div class="storeengine-withdrawal-amount-action">
			<input type="number" id="withdrawal_amount" name="withdrawal_amount" class="storeengine-input storeengine-my-2" value="" min="0" max="<?php echo esc_attr( $available_amount ); ?>" placeholder="<?php esc_attr_e( 'Enter withdrawal amount', 'storeengine' ); ?>" aria-label="<?php esc_attr_e( 'Enter withdrawal amount', 'storeengine' ); ?>" required>
			<button class="storeengine-btn storeengine-btn--sm storeengine-btn--preset-blue"><?php echo esc_html__( 'Withdraw', 'storeengine' ); ?></button>
		</div>
	</form>
	<?php else : ?>
		<p class="storeengine-info" style="margin:14px 0 0;"><?php esc_html_e( 'You don’t have enough balance to withdraw yet.', 'storeengine' ); ?></p>
	<?php endif; ?>
	</div>
</div>

<div class="storeengine-dashboard__table-wrapper storeengine-frontend-dashboard--affiliate-withdraw">
	<table class="storeengine-dashboard__table">
		<thead>
		<tr>
			<th scope="row" class="col-payment_method"><?php esc_html_e( 'Method', 'storeengine' ); ?></th>
			<th scope="row" class="col-created_at"><?php esc_html_e( 'Requested On', 'storeengine' ); ?></th>
			<th scope="row" class="col-payout_amount"><?php esc_html_e( 'Amount', 'storeengine' ); ?></th>
			<th scope="row" class="col-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php if ( is_array( $withdraw_history ) && count( $withdraw_history ) ) : ?>
			<?php foreach ( $withdraw_history as $withdraw_item ) : ?>
				<tr>
					<td class="col-payment_method"><?php echo esc_html( $withdraw_item['payment_method'] ); ?></td>
					<td class="col-created_at"><?php echo esc_html( $withdraw_item['created_at'] ); ?></td>
					<td class="col-payout_amount"><?php echo esc_html( $withdraw_item['payout_amount'] ); ?></td>
					<td class="col-status">
						<span class="storeengine-status storeengine-status--<?php echo esc_attr( $withdraw_item['status'] ); ?>">
							<?php echo esc_html( $payout_statuses[ $withdraw_item['status'] ] ?? $withdraw_item['status'] ); ?>
						</span>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
		<?php storeengine_table_oops_message( 'columns=4' ); ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
