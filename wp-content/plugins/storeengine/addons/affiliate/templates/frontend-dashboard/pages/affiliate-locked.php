<?php
/**
 * Affiliate dashboard — locked notice.
 *
 * Shown in place of the earnings dashboard when the current affiliate's account
 * is `rejected` or `suspended`. Those accounts cannot earn or track referrals,
 * so the full dashboard (with its dead referral link) is withheld.
 *
 * @var string $status Affiliate status (rejected|suspended).
 */

use StoreEngine\Addons\Affiliate\models\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status = isset( $status ) ? (string) $status : '';

if ( Affiliate::STATUS_SUSPENDED === $status ) {
	$heading = __( 'Your affiliate account is suspended', 'storeengine' );
	$message = __( 'Your affiliate account has been suspended, so referral tracking and commissions are paused. Please contact the store if you think this is a mistake.', 'storeengine' );
} else {
	$heading = __( 'Your affiliate application was not approved', 'storeengine' );
	$message = __( 'Your affiliate application was not approved, so the affiliate dashboard is unavailable. Please contact the store if you have any questions.', 'storeengine' );
}
?>
<div class="storeengine-affiliate-locked" style="max-width:560px;margin:40px auto;padding:32px;text-align:center;border:1px solid var(--storeengine-border-color,#DEDEDE);border-radius:10px;background:#fff;">
	<span class="storeengine-status storeengine-status--<?php echo esc_attr( $status ); ?>">
		<?php echo esc_html( Affiliate::status_label( $status ) ); ?>
	</span>
	<h2 class="storeengine-affiliate-locked__heading" style="margin:16px 0 8px;font-size:20px;"><?php echo esc_html( $heading ); ?></h2>
	<p class="storeengine-affiliate-locked__message" style="margin:0;color:var(--storeengine-subtitle-color,#646C73);line-height:1.6;"><?php echo esc_html( $message ); ?></p>
</div>
