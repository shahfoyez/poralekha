<?php
/**
 * Dropdown price selector for the SINGLE product page.
 *
 * A larger sibling of templates/loop/prices.php, used when the "Single Product
 * Price Display" setting is "dropdown". Unlike the shared loop template, this one
 * resolves the selected price SERVER-SIDE (cart → ?price_id → ?price_type → first)
 * and pre-renders the toggle, so the right price shows on first paint — no empty
 * flash, no JS needed to set the initial state.
 *
 * @var \StoreEngine\Classes\Price[] $prices
 * @var \StoreEngine\Classes\AbstractProduct $product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! isset( $product ) ) {
	global $product;
}

// Group key for ?price_type matching (mirrors single-product/prices.php).
$se_price_group_for = static function ( $price ) {
	$type = $price->get_price_type();
	if ( 'subscription' === $type ) {
		return 'subscription-' . $price->get_payment_duration_type();
	}
	if ( 'installment-plan' === $type ) {
		return 'installment';
	}
	return 'onetime';
};

// Pre-pass: cart items + group keys + which price is initially selected.
$se_meta          = [];
$se_checked_index = 0;
foreach ( $prices as $i => $price ) {
	$cart_item     = storeengine_cart()->get_cart_item_by_product( $product->get_id(), $price->get_id() );
	$se_meta[ $i ] = [ 'cart_item' => $cart_item, 'group' => $se_price_group_for( $price ) ];
	if ( $cart_item && $cart_item->price_id === $price->get_id() ) {
		$se_checked_index = $i;
	}
}

// Deep-link selection, resolved server-side (no JS flash).
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display preference.
$se_req_price_id = isset( $_GET['price_id'] ) ? absint( wp_unslash( $_GET['price_id'] ) ) : 0;
if ( $se_req_price_id ) {
	foreach ( $prices as $i => $price ) {
		if ( (int) $price->get_id() === $se_req_price_id ) {
			$se_checked_index = $i;
			break;
		}
	}
} elseif ( isset( $_GET['price_type'] ) ) {
	$se_req_type = sanitize_key( wp_unslash( $_GET['price_type'] ) );
	$se_aliases  = [
		'lifetime' => 'onetime', 'one-time' => 'onetime', 'onetime' => 'onetime',
		'yearly' => 'subscription-year', 'year' => 'subscription-year',
		'monthly' => 'subscription-month', 'month' => 'subscription-month',
		'weekly' => 'subscription-week', 'week' => 'subscription-week',
		'daily' => 'subscription-day', 'day' => 'subscription-day',
		'installment' => 'installment',
	];
	$se_target = $se_aliases[ $se_req_type ] ?? $se_req_type;
	foreach ( $prices as $i => $price ) {
		if ( $se_meta[ $i ]['group'] === $se_target ) {
			$se_checked_index = $i;
			break;
		}
	}
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Renders the inner content of a price label (shared by the toggle + each option).
$se_render_price_label = static function ( $price ) {
	?>
	<span class="storeengine-loop-product-price-summery">
		<span class="storeengine-loop-product-price-label"><?php echo esc_html( $price->get_name() ); ?></span>
		<span class="storeengine-loop-product-price-value">
			<?php $price->print_price_summery_html(); ?>
			<span class="storeengine-icon--arrow-square-down" aria-hidden="true"></span>
		</span>
	</span>
	<span class="storeengine-loop-product-price-details"><?php $price->print_formatted_price_meta_html(); ?></span>
	<?php
};

$se_selected_price = $prices[ $se_checked_index ] ?? current( $prices );
?>
<div class="storeengine-product__multi-prices storeengine-dropdown storeengine-single-product-dropdown" data-dropdown>
	<div class="storeengine-dropdown__toggle" data-dropdown-toggle>
		<?php // Pre-rendered so the selected price shows immediately (no empty flash).
		$se_render_price_label( $se_selected_price ); ?>
	</div>
	<div class="storeengine-dropdown-content" data-dropdown-list>
		<?php foreach ( $prices as $i => $price ) :
			$id_attr   = 'product-price-' . $product->get_id() . '-' . $price->get_id();
			$cart_item = $se_meta[ $i ]['cart_item'];
			$checked   = $i === $se_checked_index;
			?>
			<div class="storeengine-product__multi-price<?php echo $checked ? ' selected' : ''; ?>">
				<input
					class="storeengine-hide storeengine-radio"
					type="radio"
					name="price_id"
					id="<?php echo esc_attr( $id_attr ); ?>"
					value="<?php echo esc_attr( $price->get_id() ); ?>"
					data-price_type="<?php echo esc_attr( $price->get_price_type() ); ?>"
					<?php echo $cart_item && $cart_item->price_id === $price->get_id() ? 'data-cart_count="' . esc_attr( $cart_item->quantity ) . '"' : ''; ?>
					<?php checked( $checked ); ?>
				/>
				<label for="<?php echo esc_attr( $id_attr ); ?>">
					<?php $se_render_price_label( $price ); ?>
				</label>
			</div>
		<?php endforeach; ?>
	</div>
	<input type="hidden" name="price_id" value="<?php echo esc_attr( $se_selected_price->get_id() ); ?>">
</div>
