<?php

namespace StoreEngine\Classes;

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Side-by-side product comparison table.
 *
 * Storage and the toggle buttons follow the wishlist pattern (see the compare
 * section in includes/frontend/functions.php); this class owns only the table
 * itself — the rows to show, and how each cell renders.
 *
 * Rows are built from what the compared products actually have: the fixed rows
 * (image, title, price, availability) always render, taxonomies and attributes
 * only when at least one product in the set carries them. That keeps a
 * two-simple-product comparison from being mostly empty cells.
 */
class ProductCompare {

	const BRAND_TAXONOMY = 'storeengine_product_brand';

	/** Default number of products a shopper can line up at once. */
	const MAX_DEFAULT = 4;

	/**
	 * How many products may be compared at once.
	 *
	 * Kept small on purpose: the table scrolls horizontally, and past four
	 * columns the cells are too narrow to read on a laptop.
	 */
	public static function max(): int {
		/**
		 * Filter the maximum number of products in a comparison.
		 *
		 * @param int $max Maximum count.
		 */
		$max = (int) apply_filters( 'storeengine/compare/max_products', self::MAX_DEFAULT );

		return max( 2, $max );
	}

	/**
	 * Render the comparison table for a list of product ids.
	 *
	 * @param int[] $ids Product ids, in the shopper's chosen order.
	 *
	 * @return string Empty when nothing comparable was found.
	 */
	public static function get_table_html( array $ids ): string {
		$products = self::collect( $ids );

		if ( count( $products ) < 1 ) {
			return '';
		}

		$rows = array_merge(
			[
				[
					'label'  => __( 'Product', 'storeengine' ),
					'render' => [ __CLASS__, 'cell_media' ],
					'class'  => 'is-media',
				],
				[
					'label'  => __( 'Price', 'storeengine' ),
					'render' => [ __CLASS__, 'cell_price' ],
				],
				[
					'label'  => __( 'Availability', 'storeengine' ),
					'render' => [ __CLASS__, 'cell_stock' ],
				],
			],
			self::taxonomy_rows( $products ),
			self::attribute_rows( $products ),
			[
				[
					'label'  => __( 'Add to cart', 'storeengine' ),
					'render' => [ __CLASS__, 'cell_add_to_cart' ],
					'class'  => 'is-action',
				],
			]
		);

		ob_start();
		?>
		<div class="storeengine-compare-table__scroll">
			<table class="storeengine-compare-table">
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr class="<?php echo esc_attr( $row['class'] ?? '' ); ?>">
						<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
						<?php foreach ( $products as $product ) : ?>
							<td>
								<?php
								echo call_user_func( $row['render'], $product, $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each cell_* escapes its own output.
								?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Load the comparable products, preserving the requested order and dropping
	 * anything unpublished or missing.
	 *
	 * @param int[] $ids Product ids.
	 *
	 * @return AbstractProduct[]
	 */
	protected static function collect( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		// Keep the NEWEST picks, matching storeengine_set_user_compare() and the
		// frontend cap — all three trim from the front so the table and the tray
		// can never disagree about which products are in the comparison.
		if ( count( $ids ) > self::max() ) {
			$ids = array_slice( $ids, -self::max() );
		}

		$products = [];

		foreach ( $ids as $id ) {
			if ( 'publish' !== get_post_status( $id ) ) {
				continue;
			}

			$product = Helper::get_product( $id );

			if ( $product ) {
				$products[] = $product;
			}
		}

		return $products;
	}

	/**
	 * Category / tag / brand rows, included only when something in the set has
	 * terms in that taxonomy.
	 *
	 * @param AbstractProduct[] $products Compared products.
	 *
	 * @return array<int,array>
	 */
	protected static function taxonomy_rows( array $products ): array {
		$candidates = [
			Helper::PRODUCT_CATEGORY_TAXONOMY => __( 'Category', 'storeengine' ),
			self::BRAND_TAXONOMY              => __( 'Brand', 'storeengine' ),
			Helper::PRODUCT_TAG_TAXONOMY      => __( 'Tags', 'storeengine' ),
		];

		$rows = [];

		foreach ( $candidates as $taxonomy => $label ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$used = false;
			foreach ( $products as $product ) {
				$terms = get_the_terms( $product->get_id(), $taxonomy );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$used = true;
					break;
				}
			}

			if ( $used ) {
				$rows[] = [
					'label'    => $label,
					'render'   => [ __CLASS__, 'cell_terms' ],
					'taxonomy' => $taxonomy,
				];
			}
		}

		return $rows;
	}

	/**
	 * One row per product attribute taxonomy present across the set, in the
	 * order the first product that uses it declares.
	 *
	 * @param AbstractProduct[] $products Compared products.
	 *
	 * @return array<int,array>
	 */
	protected static function attribute_rows( array $products ): array {
		$taxonomies = [];

		foreach ( $products as $product ) {
			foreach ( array_keys( $product->get_attributes() ) as $taxonomy ) {
				if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
					$taxonomies[] = $taxonomy;
				}
			}
		}

		$rows = [];

		foreach ( $taxonomies as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );
			$rows[] = [
				'label'    => $object ? $object->labels->singular_name : $taxonomy,
				'render'   => [ __CLASS__, 'cell_attribute' ],
				'taxonomy' => $taxonomy,
			];
		}

		return $rows;
	}

	/* ---------------------------------------------------------------------- *
	 * Cell renderers. Each escapes its own output.
	 * ---------------------------------------------------------------------- */

	/** Thumbnail + linked title + a control to drop the column. */
	protected static function cell_media( $product ): string {
		$id   = $product->get_id();
		$link = get_permalink( $id );

		return sprintf(
			'<button type="button" class="storeengine-compare-table__remove" data-storeengine-compare-remove="%1$d" aria-label="%2$s" title="%2$s">&times;</button>'
			. '<a class="storeengine-compare-table__media" href="%3$s">%4$s</a>'
			. '<a class="storeengine-compare-table__title" href="%3$s">%5$s</a>',
			(int) $id,
			esc_attr__( 'Remove from comparison', 'storeengine' ),
			esc_url( $link ),
			storeengine_get_product_image( 'storeengine_thumbnail', $id ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image output.
			esc_html( $product->get_name() )
		);
	}

	/** Single price, or a range when the product has several. */
	protected static function cell_price( $product ): string {
		$prices = array_map(
			static fn( $price ) => (float) $price->get_price(),
			$product->get_prices()
		);

		if ( ! $prices ) {
			return '<span class="storeengine-compare-table__empty">&mdash;</span>';
		}

		$html = ( min( $prices ) !== max( $prices ) )
			? Formatting::format_price_range( max( $prices ), min( $prices ) )
			: Formatting::price( reset( $prices ) );

		return wp_kses_post( $html );
	}

	protected static function cell_stock( $product ): string {
		$in_stock = ! method_exists( $product, 'is_in_stock' ) || $product->is_in_stock();

		return sprintf(
			'<span class="storeengine-compare-table__stock %1$s">%2$s</span>',
			$in_stock ? 'is-in' : 'is-out',
			$in_stock ? esc_html__( 'In stock', 'storeengine' ) : esc_html__( 'Out of stock', 'storeengine' )
		);
	}

	/**
	 * Linked term list for a taxonomy row.
	 *
	 * @param AbstractProduct $product Product.
	 * @param array           $row     Row definition (carries the taxonomy).
	 */
	protected static function cell_terms( $product, array $row ): string {
		$terms = get_the_terms( $product->get_id(), $row['taxonomy'] );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return '<span class="storeengine-compare-table__empty">&mdash;</span>';
		}

		$links = [];
		foreach ( $terms as $term ) {
			$url     = get_term_link( $term );
			$links[] = is_wp_error( $url )
				? esc_html( $term->name )
				: sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $term->name ) );
		}

		return implode( ', ', $links );
	}

	/**
	 * Attribute values for one attribute taxonomy.
	 *
	 * @param AbstractProduct $product Product.
	 * @param array           $row     Row definition (carries the taxonomy).
	 */
	protected static function cell_attribute( $product, array $row ): string {
		$attributes = $product->get_attributes();
		$values     = $attributes[ $row['taxonomy'] ] ?? [];

		if ( ! $values ) {
			return '<span class="storeengine-compare-table__empty">&mdash;</span>';
		}

		// AttributeData exposes plain public properties, not getters.
		$names = array_map(
			static fn( $attribute ) => esc_html( $attribute->name ?? '' ),
			$values
		);

		return implode( ', ', array_filter( $names ) );
	}

	/**
	 * Real add-to-cart controls, by rendering the same loop template the shop
	 * cards use — so variable products get their swatches and simple products a
	 * working button, rather than a link that drops the shopper somewhere else.
	 */
	protected static function cell_add_to_cart( $product ): string {
		$id   = $product->get_id();
		$post = get_post( $id );

		if ( ! $post ) {
			return '';
		}

		$prev_post    = $GLOBALS['post'] ?? null;
		$prev_product = $GLOBALS['product'] ?? null;

		$GLOBALS['post']    = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		setup_postdata( $post );

		ob_start();
		Template::get_template( 'loop/add-to-cart.php' );
		$html = (string) ob_get_clean();

		$GLOBALS['post']    = $prev_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['product'] = $prev_product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

		if ( $prev_post instanceof \WP_Post ) {
			setup_postdata( $prev_post );
		} else {
			wp_reset_postdata();
		}

		return $html;
	}
}
