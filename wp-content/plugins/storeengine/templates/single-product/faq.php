<?php
/**
 * Single product FAQ section (modern accordion).
 *
 * Items resolved from the product's inline Q&A and any matching FAQ groups are
 * rendered as one flat accordion — group names are intentionally NOT shown on
 * the storefront (grouping is an admin-side organisation concept only).
 *
 * This template can be overridden by copying it to yourtheme/storeengine/single-product/faq.php.
 *
 * @package StoreEngine\Templates
 * @version 3.1.0
 *
 * @var int   $product_id
 * @var array $groups List of { id, title, items:[{question,answer}] }.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( empty( $groups ) ) {
	return;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Flatten every group's items into a single list — no group headings.
$storeengine_faq_items = [];
foreach ( $groups as $storeengine_faq_group ) {
	foreach ( $storeengine_faq_group['items'] as $storeengine_faq_item ) {
		$storeengine_faq_items[] = $storeengine_faq_item;
	}
}

if ( empty( $storeengine_faq_items ) ) {
	return;
}
?>
<section id="storeengine-product-faq" class="storeengine-container storeengine-single-product__content-item storeengine-single-product__content-item--faq storeengine-faq">
	<h3 class="storeengine-faq__heading"><?php esc_html_e( 'Frequently Asked Questions', 'storeengine' ); ?></h3>

	<div class="storeengine-faq__items">
		<?php foreach ( $storeengine_faq_items as $faq ) : ?>
			<details class="storeengine-faq__item">
				<summary class="storeengine-faq__question">
					<span class="storeengine-faq__question-text"><?php echo esc_html( $faq['question'] ); ?></span>
					<span class="storeengine-faq__toggle" aria-hidden="true"></span>
				</summary>
				<div class="storeengine-faq__answer">
					<div class="storeengine-faq__answer-inner">
						<?php echo wp_kses_post( wpautop( $faq['answer'] ) ); ?>
					</div>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
</section>
