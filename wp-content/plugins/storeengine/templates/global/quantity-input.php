<?php
/**
 * Add to cart quantity input.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Params.
 *
 * @var bool $disabled
 * @var bool $readonly
 *
 * @var string $id
 * @var string $label
 * @var string $name
 * @var string $type
 * @var int $max_qty
 * @var int $min_qty
 * @var int $step
 * @var int $quantity
 * @var string $autocomplete
 * @var string $placeholder
 * @var string $inputmode
 */

?>
<?php if ( 'hidden' === $type ) : ?>
	<?php
	// Quantity cannot be changed (sold individually, or the store-wide "Hide
	// quantity selector" setting). Render a bare hidden input that still submits
	// the value — no stepper buttons and no styled container box to show.
	?>
	<input
		type="hidden"
		id="<?php echo esc_attr( $id ); ?>"
		class="storeengine-quantity-input__input"
		name="<?php echo esc_attr( $name ?? 'quantity' ); ?>"
		value="<?php echo esc_attr( absint( $quantity ?? 1 ) ); ?>"
	/>
<?php else : ?>
<div class="storeengine-quantity-input">
	<button type="button" class="storeengine-quantity-input__btn decrement" aria-label="<?php esc_attr_e( 'Decrease quantity by 1', 'storeengine' ); ?>"<?php disabled( isset( $disabled ) && $disabled || $min_qty === $quantity ); ?>>
		<i class="storeengine-icon storeengine-icon--minus" aria-hidden="true"></i>
	</button>
	<?php if ( $label ) { ?>
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_attr( $label ); ?></label>
	<?php } ?>
	<input
		id="<?php echo esc_attr( $id ); ?>"
		class="storeengine-quantity-input__input"
		type="<?php echo esc_attr( $type ); ?>"
		name="<?php echo esc_attr( $name ?? 'quantity' ); ?>"
		<?php if ( $max_qty ) { ?>
			max="<?php echo esc_attr( $max_qty ); ?>"
		<?php } ?>
		min="<?php echo esc_attr( $min_qty ); ?>"
		value="<?php echo esc_attr( absint( $quantity ?? 1 ) ); ?>"
		aria-label="<?php esc_attr_e( 'Product quantity', 'storeengine' ); ?>"
		<?php if ( ! $readonly && ! $disabled ) { ?>
			step="<?php echo esc_attr( $step ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			inputmode="<?php echo esc_attr( $inputmode ); ?>"
			autocomplete="<?php echo esc_attr( $autocomplete ?? 'on' ); ?>"
		<?php } ?>
		<?php disabled( $disabled ); ?>
		<?php wp_readonly( $readonly ); ?>
	/>
	<button type="button" class="storeengine-quantity-input__btn increment" aria-label="<?php esc_attr_e( 'Increase quantity by 1', 'storeengine' ); ?>"<?php disabled( isset( $disabled ) && $disabled || $max_qty === $quantity ); ?>>
		<i class="storeengine-icon storeengine-icon--add" aria-hidden="true"></i>
	</button>
</div>
<?php endif; ?>
