<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Contact-field context, supplied by the verification addon (if active) via
// filter. Core stays decoupled — defaults keep the plain email behaviour.
$storeengine_contact_context = (array) apply_filters( 'storeengine/checkout/contact_field_context', [
	'mode'                  => 'email_only',
	'verification_required' => false,
] );
$storeengine_verification_mode     = $storeengine_contact_context['mode'] ?? 'email_only';
$storeengine_verification_required = ! empty( $storeengine_contact_context['verification_required'] );
$storeengine_contact_is_phone_only = 'phone_only' === $storeengine_verification_mode;
$storeengine_contact_type          = $storeengine_contact_is_phone_only ? 'tel' : ( 'phone_or_email' === $storeengine_verification_mode ? 'text' : 'email' );
$storeengine_contact_label         = $storeengine_contact_is_phone_only
	? __( 'Phone number', 'storeengine' )
	: ( 'phone_or_email' === $storeengine_verification_mode
		? __( 'Email or phone number', 'storeengine' )
		: __( 'Email address', 'storeengine' ) );

// Prefill value. In phone-only mode the identifier IS the phone, so never show
// the account email under a "Phone number" label — use the stored phone if any,
// otherwise leave it blank for the customer to enter.
$storeengine_guest_prefill = (string) ( $storeengine_contact_context['guest_contact_prefill'] ?? '' );
$storeengine_contact_value = $current_user_email;
if ( $storeengine_contact_is_phone_only ) {
	$storeengine_contact_value = is_user_logged_in()
		? (string) get_user_meta( get_current_user_id(), 'billing_phone', true )
		: $storeengine_guest_prefill;
} elseif ( ! is_user_logged_in() && '' !== $storeengine_guest_prefill ) {
	// email-only / phone_or_email: cookie-backed verified identifier takes priority
	// over WC session, which may hold a stale or placeholder email.
	$storeengine_contact_value = $storeengine_guest_prefill;
}

// Logged-in customers normally get a read-only contact (their account email).
// In phone-only mode it must stay editable so they can enter/verify a phone —
// especially when their account has no phone on file yet.
$storeengine_contact_readonly    = is_user_logged_in() && ! $storeengine_contact_is_phone_only;
$storeengine_is_already_verified = ! empty( $storeengine_contact_context['is_already_verified'] );

?>

<div class="storeengine-ajax-checkout-form__contact-information">
	<h4 class="storeengine-ajax-checkout-form__heading"><?php esc_html_e( 'Contact', 'storeengine' ); ?></h4>

	<?php if ( $storeengine_verification_required ) : ?>

		<?php /* --- Verification mode: unified contact + OTP block --- */ ?>

		<div class="storeengine-ajax-checkout-form__user-info-top">
			<label for="user_email" class="storeengine-ajax-checkout-form__title"><?php echo esc_html( $storeengine_contact_label ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label>
			<?php if ( ! is_user_logged_in() ) : ?>
				<p class="storeengine-form-field__login-link">
					<a href="<?php echo esc_url( storeengine_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Already have an account? Log in', 'storeengine' ); ?></a>
				</p>
			<?php endif; ?>
			<?php if ( is_user_logged_in() ) : ?>
				<p class="storeengine-form-field__login-link">
					<a class="storeengine-ajax-checkout-form__logout" href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Logout', 'storeengine' ); ?></a>
				</p>
			<?php endif; ?>
		</div>

		<?php /* Tabs above the block for phone_or_email mode */ ?>
		<?php if ( 'phone_or_email' === $storeengine_verification_mode ) : ?>
			<div class="se-contact-tabs" id="se-contact-tabs">
				<button type="button" class="se-contact-tab se-contact-tab--active" data-tab="email"><?php esc_html_e( 'Email', 'storeengine' ); ?></button>
				<button type="button" class="se-contact-tab" data-tab="phone"><?php esc_html_e( 'Phone', 'storeengine' ); ?></button>
			</div>
		<?php endif; ?>

		<?php /* Unified block: contact input row + OTP flow */ ?>
		<div class="se-contact-block"
		     id="storeengine-otp-verify"
		     data-verified="<?php echo $storeengine_is_already_verified ? '1' : '0'; ?>"
		     role="group"
		     aria-label="<?php esc_attr_e( 'Contact and verification', 'storeengine' ); ?>">

			<?php /* ── Input row: contact field(s) flush with Send / Verified ── */ ?>
			<div class="se-contact-block__input-row">

				<?php if ( $storeengine_contact_is_phone_only ) : ?>
					<div class="se-phone-input-row" id="se-phone-input-row">
						<select id="se-dial-code" class="se-dial-code-select"><!-- JS fills --></select>
						<input type="tel" id="se-phone-local"
						       autocomplete="tel-national"
						       placeholder="<?php esc_attr_e( 'Phone number', 'storeengine' ); ?>"
						       required>
					</div>
					<input type="hidden" name="user_email" id="user_email" value="<?php echo esc_attr( $storeengine_contact_value ); ?>">
					<input type="hidden" name="billing_phone" id="se_contact_phone_mirror" value="<?php echo esc_attr( $storeengine_contact_value ); ?>">

				<?php elseif ( 'phone_or_email' === $storeengine_verification_mode ) : ?>
					<div id="se-email-wrap">
						<input type="email" name="user_email" id="user_email"
						       value="<?php echo esc_attr( $storeengine_contact_value ); ?>"
						       placeholder="<?php esc_attr_e( 'Email address', 'storeengine' ); ?>"
						       required>
					</div>
					<div class="se-phone-input-row" id="se-phone-input-row" style="display:none">
						<select id="se-dial-code" class="se-dial-code-select"><!-- JS fills --></select>
						<input type="tel" id="se-phone-local"
						       autocomplete="tel-national"
						       placeholder="<?php esc_attr_e( 'Phone number', 'storeengine' ); ?>">
					</div>

				<?php else : ?>
					<input type="<?php echo esc_attr( $storeengine_contact_type ); ?>"
					       name="user_email" id="user_email"
					       value="<?php echo esc_attr( $storeengine_contact_value ); ?>"
					       placeholder="<?php echo esc_attr( $storeengine_contact_label ); ?>"
					       required<?php wp_readonly( $storeengine_contact_readonly ); ?>>
				<?php endif; ?>

				<?php /* Action: Send code button | Verified pill */ ?>
				<div class="se-contact-block__action">
					<button type="button" class="se-otp__btn--send" id="se-otp-send">
						<span class="se-otp__btn-label"><?php esc_html_e( 'Send code', 'storeengine' ); ?></span>
						<span class="se-otp__spinner" aria-hidden="true"></span>
					</button>
					<div class="se-otp__verified-pill">
						<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
						<?php esc_html_e( 'Verified', 'storeengine' ); ?>
					</div>
				</div>

			</div><!-- .se-contact-block__input-row -->

			<?php /* Status line (below input row, empty = invisible) */ ?>
			<span class="se-otp__status" id="se-otp-status" role="status" aria-live="polite"></span>

			<?php /* Code-entry step (shown after Send is clicked) */ ?>
			<div class="se-otp__confirm" id="se-otp-confirm" aria-hidden="true">
				<div class="se-otp__code-row">
					<input type="text" inputmode="numeric" autocomplete="one-time-code"
					       class="se-otp__code-input" id="se-otp-code" maxlength="8"
					       placeholder="<?php esc_attr_e( '6-digit code', 'storeengine' ); ?>"
					       aria-label="<?php esc_attr_e( 'Verification code', 'storeengine' ); ?>">
					<button type="button" class="se-otp__btn se-otp__btn--primary" id="se-otp-check">
						<span class="se-otp__btn-label"><?php esc_html_e( 'Verify', 'storeengine' ); ?></span>
						<span class="se-otp__spinner" aria-hidden="true"></span>
					</button>
				</div>
				<div class="se-otp__resend">
					<span class="se-otp__timer" id="se-otp-timer"></span>
					<button type="button" class="se-otp__resend-btn" id="se-otp-resend-btn" disabled>
						<?php esc_html_e( 'Resend code', 'storeengine' ); ?>
					</button>
				</div>
			</div>

			<?php /* Lock hint (shown by CSS while form.is-verify-locked) */ ?>
			<p class="se-otp__lock-hint" role="alert">
				<?php esc_html_e( 'Verify your contact to unlock the rest of checkout.', 'storeengine' ); ?>
			</p>

		</div><!-- .se-contact-block -->

	<?php else : ?>

		<?php /* --- No verification: original plain input structure --- */ ?>
		<div class="storeengine-ajax-checkout-form__user-info">
			<div class="storeengine-ajax-checkout-form__user-info-top">
				<label for="user_email" class="storeengine-ajax-checkout-form__title"><?php echo esc_html( $storeengine_contact_label ); ?>&nbsp;<abbr class="storeengine-required" title="<?php esc_attr_e( 'required', 'storeengine' ); ?>">*</abbr></label>
				<?php if ( ! is_user_logged_in() ) : ?>
					<p class="storeengine-form-field__login-link">
						<a href="<?php echo esc_url( storeengine_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Already have an account? Log in', 'storeengine' ); ?></a>
					</p>
				<?php endif; ?>
				<?php if ( is_user_logged_in() ) : ?>
					<p class="storeengine-form-field__login-link">
						<a class="storeengine-ajax-checkout-form__logout" href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Logout', 'storeengine' ); ?></a>
					</p>
				<?php endif; ?>
			</div>
			<input class="storeengine-ajax-checkout-form__input"
			       type="<?php echo esc_attr( $storeengine_contact_type ); ?>"
			       name="user_email" id="user_email"
			       value="<?php echo esc_attr( $storeengine_contact_value ); ?>"
			       placeholder="<?php echo esc_attr( $storeengine_contact_label ); ?>"
			       required<?php wp_readonly( $storeengine_contact_readonly ); ?>>
		</div>

	<?php endif; ?>

	<?php // "Email me with news and offers" only makes sense when the contact is an email — hide in phone-only mode. ?>
	<?php if ( is_user_logged_in() && ! $storeengine_contact_is_phone_only ) : ?>
		<div class="storeengine-mb-3">
			<label class="storeengine-flex storeengine-flex-align-center storeengine-mt-2" style="display:flex;gap:5px">
				<input type="checkbox" name="subscribe_to_email" class="storeengine-input-check">
				<span class="storeengine-ajax-checkout-form__user-info-checkbox"><?php esc_html_e( 'Email me with news and offers', 'storeengine' ); ?></span>
			</label>
		</div>
	<?php endif; ?>
</div>
