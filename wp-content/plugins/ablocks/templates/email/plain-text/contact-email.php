<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// esc_html_e( 'New Contact Form Submission', 'ablocks' ) . "\n";
// esc_html_e( 'You have a new contact form submission!', 'ablocks' ) . "\n";
// esc_html_e( 'Email:', 'ablocks' ) . esc_html( $email ) . "\n";
// esc_html_e( 'Message:', 'ablocks' ) . "\n";
echo wp_kses_post( $message ) . "\n";

$url = wp_parse_url( get_bloginfo( 'url' ) );
$host = isset( $url['host'] ) ? sanitize_text_field( $url['host'] ) : '';
$port = isset( $url['port'] ) ? sanitize_text_field( (string) $url['port'] ) : '80';
echo esc_html( $host . ':' . $port );
