<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$label = str_pad( esc_html__( 'Label', 'ablocks' ), 20, ' ' ); // Prepare raw string first.
$value = esc_html__( 'Value', 'ablocks' );

echo "\n\n\n" . esc_html( $label . $value ) . "\n";
echo "-------------------------------------\n";

// Ensure $data is an array to avoid errors
if ( ! empty( $data ) && is_array( $data ) ) {
	foreach ( $data as $input_id => $attr ) {
		$attr = apply_filters( 'ablocks/form_email_field', $attr, $input_id );
		$label_text = ucfirst( preg_replace( '/[_-]/m', ' ', $input_id ) );
		$label      = str_pad( esc_html( $label_text ), 18, ' ' );
		$value      = isset( $attr['value'] ) ? (
			 ( is_iterable( $attr['value'] ) ? wp_kses_post( implode( ', S', $attr['value'] ) ) : \wp_kses_post( $attr['value'] ) )
		) : '';

		// Output the formatted label and value
		echo esc_html( $label . ': ' . $value ) . "\n";
	}
}
