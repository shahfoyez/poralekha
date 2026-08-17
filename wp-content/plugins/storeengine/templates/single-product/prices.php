<?php
/**
 * @var \StoreEngine\Classes\Price[] $prices
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Map each price to a filter group (Lifetime / Yearly / Installment / …) so the
// product page can show a type filter. Auto-derived from the price types present;
// the filter only renders when a product offers more than one type.
$se_price_group_for = static function ( $price ) {
	$type = $price->get_price_type();

	if ( 'subscription' === $type ) {
		$period = $price->get_payment_duration_type(); // day|week|month|year
		$labels = [
			'day'   => __( 'Daily', 'storeengine' ),
			'week'  => __( 'Weekly', 'storeengine' ),
			'month' => __( 'Monthly', 'storeengine' ),
			'year'  => __( 'Yearly', 'storeengine' ),
		];

		return [ 'key' => 'subscription-' . $period, 'label' => $labels[ $period ] ?? ucfirst( $period ) ];
	}

	if ( 'installment-plan' === $type ) {
		return [ 'key' => 'installment', 'label' => __( 'Installment', 'storeengine' ) ];
	}

	return [ 'key' => 'onetime', 'label' => __( 'One time', 'storeengine' ) ];
};

// Pre-pass: cache cart items + groups, find which price is initially checked.
$se_meta          = [];
$se_groups        = [];
$se_checked_index = 0;
foreach ( $prices as $i => $price ) {
	$cart_item = storeengine_cart()->get_cart_item_by_product( $price->get_product_id(), $price->get_id() );
	$group     = $se_price_group_for( $price );

	$se_meta[ $i ] = [ 'cart_item' => $cart_item, 'group' => $group ];

	if ( ! isset( $se_groups[ $group['key'] ] ) ) {
		$se_groups[ $group['key'] ] = $group['label'];
	}

	if ( $cart_item && $cart_item->price_id === $price->get_id() ) {
		$se_checked_index = $i;
	}
}

// Let deep links pre-activate a price group server-side (so the right prices
// render active immediately, with no JS flash):
//   ?price_id=123        → the group that price belongs to (also pre-checks it)
//   ?price_type=yearly   → matching group, by key or a friendly alias
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display preference, no state change.
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
		'lifetime'    => 'onetime',
		'one-time'    => 'onetime',
		'onetime'     => 'onetime',
		'yearly'      => 'subscription-year',
		'year'        => 'subscription-year',
		'monthly'     => 'subscription-month',
		'month'       => 'subscription-month',
		'weekly'      => 'subscription-week',
		'week'        => 'subscription-week',
		'daily'       => 'subscription-day',
		'day'         => 'subscription-day',
		'installment' => 'installment',
	];
	$se_target_group = $se_aliases[ $se_req_type ] ?? $se_req_type;
	if ( isset( $se_groups[ $se_target_group ] ) ) {
		foreach ( $prices as $i => $price ) {
			if ( $se_meta[ $i ]['group']['key'] === $se_target_group ) {
				$se_checked_index = $i;
				break;
			}
		}
	}
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$se_show_filter   = count( $se_groups ) > 1;
$se_default_group = $se_meta[ $se_checked_index ]['group']['key'] ?? '';
?>
<div class="storeengine-single-product-prices">
	<?php if ( $se_show_filter ) { ?>
		<div class="storeengine-price-filter" role="tablist" aria-label="<?php esc_attr_e( 'Filter pricing by type', 'storeengine' ); ?>">
			<?php foreach ( $se_groups as $group_key => $group_label ) { ?>
				<button
					type="button"
					class="storeengine-price-filter__btn<?php echo $group_key === $se_default_group ? ' is-active' : ''; ?>"
					data-price-group="<?php echo esc_attr( $group_key ); ?>"
				><?php echo esc_html( $group_label ); ?></button>
			<?php } ?>
		</div>
	<?php } ?>

	<?php foreach ( $prices as $i => $price ) {
		$id_attr   = 'product-price-' . $price->get_product_id() . '-' . $price->get_id();
		$cart_item = $se_meta[ $i ]['cart_item'];
		$group_key = $se_meta[ $i ]['group']['key'];
		$checked   = $i === $se_checked_index;
		// When the filter is shown, only the active group's prices are visible initially.
		$hidden    = $se_show_filter && $group_key !== $se_default_group;
		?>
		<label
			class="storeengine-single-product-price<?php echo $hidden ? ' storeengine-price--hidden' : ''; ?>"
			data-price-group="<?php echo esc_attr( $group_key ); ?>"
			<?php echo $hidden ? 'style="display:none;"' : ''; ?>
			for="<?php echo esc_attr( $id_attr ); ?>">
			<span class="storeengine-single-product-price-summery">
				<span class="storeengine-single-product-price-label">
					<input
						type="radio"
						class="storeengine-radio"
						id="<?php echo esc_attr( $id_attr ); ?>"
						name="price_id"
						value="<?php echo esc_attr( $price->get_id() ); ?>"
						data-price_type="<?php echo esc_attr( $price->get_price_type() ); ?>"
						<?php echo $cart_item && $cart_item->price_id === $price->get_id() ? 'data-cart_count="' . esc_attr( $cart_item->quantity ) . '"' : ''; ?>
						<?php checked( $checked ); ?>
					/>
					<span class="storeengine-single-product-price-name"><?php echo esc_html( $price->get_name() ); ?></span>
				</span>
				<span class="storeengine-single-product-price-value"><?php $price->print_price_summery_html(); ?></span>
			</span>
			<span class="storeengine-single-product-price-details"><?php $price->print_formatted_price_meta_html(); ?></span>
		</label>
	<?php } ?>
</div>
