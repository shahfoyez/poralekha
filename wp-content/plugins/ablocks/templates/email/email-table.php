<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<table>
<tr>
	<th style="text-align:center;padding:5px;background:gray;color:white"><?php esc_html_e( 'Name', 'ablocks' ); ?></th>
	<th style="text-align:center;padding:5px;background:gray;color:white"><?php esc_html_e( 'Value', 'ablocks' ); ?></th>
</tr>
<?php
foreach ( $data as $input_id => $attr ) :
	$attr = apply_filters( 'ablocks/form_email_field', $attr, $input_id );
	?>
	<tr id="<?php echo esc_attr( $input_id ); ?>">
		<td style="text-align:center;padding:5px;border:solid 1px gray;"><?php echo \esc_html( $input_id ); ?>: </td>
		<td style="text-align:center;padding:5px;border:solid 1px gray;"><?php echo ( is_iterable( $attr['value'] ) ? wp_kses_post( implode( ', ', $attr['value'] ) ) : \wp_kses_post( $attr['value'] ) ); ?></td>
	</tr>
	<?php
endforeach;
?>
</table>
