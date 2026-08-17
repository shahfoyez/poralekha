<?php
/**
 * @var \StoreEngine\Classes\Order $order
 * @var string|null $invoice_date
 */

use StoreEngine\Addons\Invoice\HelperAddon;
use StoreEngine\Addons\Invoice\InvoiceNumber;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// §14 UStG data.
$kleinunternehmer = (bool) HelperAddon::get_setting( 'invoice_kleinunternehmer', false );
$reverse          = HelperAddon::get_reverse_charge( $order );
$show_tax         = ! $kleinunternehmer && ! empty( $order->get_taxes() );
$breakdown        = HelperAddon::get_tax_breakdown( $order );
$show_delivery    = (bool) HelperAddon::get_setting( 'invoice_show_delivery_note', true );

$seller_name  = HelperAddon::get_setting( 'invoice_seller_legal_name' ) ?: Helper::get_settings( 'store_name' );
$tax_number   = (string) HelperAddon::get_setting( 'invoice_tax_number' );
$vat_id       = (string) HelperAddon::get_setting( 'invoice_vat_id' );
$seller_extra = (string) HelperAddon::get_setting( 'invoice_seller_extra' );

?>
<table class="invoice-header">
	<tr>
		<?php
		if ( ! empty( HelperAddon::get_setting( 'logo' ) ) ) : ?>
			<td class="header">
				<img style="background: none;" height="30"
					 src="<?php echo esc_attr( wp_get_attachment_image_url( HelperAddon::get_setting( 'logo' ), 'full' ) ); ?>"
					 alt="Logo"/>
			</td>
		<?php endif; ?>
		<td class="invoice-data">
			<h3 class="invoice"><?php esc_html_e( 'INVOICE', 'storeengine' ); ?></h3>
			<br>
			<table>
				<tr class="invoice-date">
					<th><?php esc_html_e( 'Invoice Number:', 'storeengine' ); ?></th>
					<td><?php echo esc_html( InvoiceNumber::get( $order ) ); ?></td>
				</tr>
				<?php if ( $invoice_date ) : ?>
					<tr>
						<th><?php esc_html_e( 'Invoice Date:', 'storeengine' ); ?></th>
						<td><?php echo esc_html( $invoice_date ); ?></td>
					</tr>
				<?php endif; ?>
			</table>
		</td>
	</tr>
</table>

<table class="order-data-addresses">
	<tr>
		<td>
			<h4 class="address-title"><?php esc_html_e( 'Invoice To:', 'storeengine' ); ?></h4>
		</td>
	</tr>
	<tr>
		<td class="address billing-address">
			<?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?>
			<div class="billing-phone"><?php echo esc_html( $order->get_billing_phone() ); ?></div>
			<div class="billing-email"><?php echo esc_html( $order->get_billing_email() ); ?></div>
			<?php if ( '' !== $reverse['vat_number'] ) : ?>
				<div class="billing-vat-id"><?php echo esc_html__( 'VAT ID:', 'storeengine' ) . ' ' . esc_html( $reverse['vat_number'] ); ?></div>
			<?php endif; ?>
		</td>
		<td class="address shipping-address">
			<?php
			if ( $order->has_shipping_address() && $order->needs_shipping_address() ) {
				echo wp_kses_post( $order->get_formatted_shipping_address() );
			}
			?>
		</td>
		<td class="shop-details">
			<h4><?php echo esc_html( $seller_name ); ?></h4>
			<?php if ( ! empty( Helper::get_shop_address() ) ) : ?>
				<p><?php echo wp_kses_post( Helper::get_shop_address() ); ?></p>
			<?php endif; ?>
			<?php if ( $tax_number ) : ?>
				<p class="seller-tax-number"><?php echo esc_html__( 'Tax number:', 'storeengine' ) . ' ' . esc_html( $tax_number ); ?></p>
			<?php endif; ?>
			<?php if ( $vat_id ) : ?>
				<p class="seller-vat-id"><?php echo esc_html__( 'VAT ID:', 'storeengine' ) . ' ' . esc_html( $vat_id ); ?></p>
			<?php endif; ?>
			<?php if ( $seller_extra ) : ?>
				<p class="seller-extra"><?php echo wp_kses_post( wpautop( $seller_extra ) ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
</table>

<table class="products-table">
	<thead>
	<tr class="product-header">
		<?php if ( HelperAddon::get_setting( 'invoice_show_product_image', false ) ) : ?>
			<th></th>
		<?php endif; ?>
		<th><?php esc_html_e( 'Product', 'storeengine' ); ?></th>
		<th><?php esc_html_e( 'Price', 'storeengine' ); ?></th>
		<th><?php esc_html_e( 'Qty', 'storeengine' ); ?></th>
		<th><?php esc_html_e( 'Subtotal', 'storeengine' ); ?></th>
		<?php if ( $show_tax ) : ?>
			<th><?php esc_html_e( 'Tax', 'storeengine' ); ?></th>
		<?php endif; ?>
	</tr>
	</thead>
	<tbody>
	<?php /** @var \StoreEngine\Classes\Order\OrderItemProduct $item */
	foreach ( $order->get_items() as $item ) : ?>
		<tr class="product-body-row">
			<?php
			if ( HelperAddon::get_setting( 'invoice_show_product_image', false ) ) : ?>
				<td>
					<img width="80" height="80" class="product-image"
						 src="<?php echo esc_attr( ! empty( get_the_post_thumbnail_url( $item->get_product_id() ) ) ? get_the_post_thumbnail_url( $item->get_product_id() ) : storeengine_placeholder_image_src() ); ?>"
						 alt="<?php echo esc_attr( $item->get_name() ); ?>"/>
				</td>
			<?php endif; ?>
			<td class="product-content">
				<span class="item-name"><?php
					echo esc_html( apply_filters( 'storeengine/order/item_name', $item->get_name(), $item ) );
				if ( $item->get_price_name() ) {
					echo '&nbsp;<span class="nobr" style="font-size:14px;font-weight:400;line-height:1.5">(' . esc_html( $item->get_price_name() ) . ')</span>';
				}
				?></span>
			</td>
			<td><?php echo wp_kses_post( Formatting::price( $item->get_price(), [ 'currency' => $order->get_currency() ] ) ); ?></td>
			<td><?php echo esc_html( $item->get_quantity() ); ?></td>
			<td><?php echo $order->get_formatted_line_subtotal( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			<?php
			if ( $show_tax ) : ?>
				<td><?php echo $item->get_total_tax() > 0 ? wp_kses_post( HelperAddon::price( $item->get_total_tax(), $order ) ) : ''; ?></td>
			<?php endif; ?>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<table class="order-totals">
	<?php foreach ( $order->get_order_item_totals() as $item_total ) : ?>
		<tr>
			<td><strong><?php echo esc_html( $item_total['label'] ); ?></strong></td>
			<td></td>
			<td class="value"><?php echo wp_kses_post( $item_total['value'] ); ?></td>
		</tr>
	<?php endforeach; ?>
</table>

<?php if ( $show_tax && ! empty( $breakdown['rates'] ) ) : ?>
	<table class="tax-breakdown">
		<thead>
		<tr>
			<th><?php esc_html_e( 'VAT rate', 'storeengine' ); ?></th>
			<th><?php esc_html_e( 'Net', 'storeengine' ); ?></th>
			<th><?php esc_html_e( 'VAT amount', 'storeengine' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( $breakdown['rates'] as $rate ) : ?>
			<tr>
				<td><?php echo esc_html( $rate['percent'] > 0 ? $rate['label'] . ' (' . Formatting::format_decimal( $rate['percent'], false, true ) . ' %)' : $rate['label'] ); ?></td>
				<td><?php echo wp_kses_post( HelperAddon::price( $rate['net'], $order ) ); ?></td>
				<td><?php echo wp_kses_post( HelperAddon::price( $rate['tax'], $order ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( $reverse['is_reverse_charge'] ) : ?>
	<p class="invoice-tax-note reverse-charge">
		<?php esc_html_e( 'Steuerschuldnerschaft des Leistungsempfängers (Reverse charge).', 'storeengine' ); ?>
	</p>
<?php endif; ?>

<?php if ( $kleinunternehmer ) : ?>
	<p class="invoice-tax-note kleinunternehmer">
		<?php echo esc_html( HelperAddon::get_setting( 'invoice_kleinunternehmer_note', __( 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.', 'storeengine' ) ) ); ?>
	</p>
<?php endif; ?>

<table class="order-info">
	<tr class="order-number">
		<th><?php esc_html_e( 'Order Number:', 'storeengine' ); ?></th>
		<td><?php echo esc_html( $order->get_id() ); ?></td>
	</tr>
	<tr class="order-date">
		<th><?php esc_html_e( 'Order Date:', 'storeengine' ); ?></th>
		<td><?php echo esc_html( $order->get_date_created()->format( HelperAddon::get_setting( 'date_format', 'd F, Y' ) ) ); ?></td>
	</tr>
	<?php if ( $show_delivery ) : ?>
		<tr class="delivery-date">
			<th><?php esc_html_e( 'Delivery / service date:', 'storeengine' ); ?></th>
			<td><?php esc_html_e( 'Same as the invoice date.', 'storeengine' ); ?></td>
		</tr>
	<?php endif; ?>
</table>

<?php if ( ! empty( HelperAddon::get_setting( 'invoice_default_note' ) ) ) : ?>
	<div class="notes">
		<h4><?php esc_html_e( 'Notes', 'storeengine' ); ?></h4>
		<p><?php echo esc_html( HelperAddon::get_setting( 'invoice_default_note' ) ); ?></p>
	</div>
<?php endif; ?>

<div id="footer">
	<?php echo wp_kses_post( HelperAddon::get_setting( 'invoice_footer_text' ) ); ?><br>
</div>
