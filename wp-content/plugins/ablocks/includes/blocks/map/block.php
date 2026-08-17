<?php
namespace ABlocks\Blocks\Map;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\CssFilter;
use ABlocks\Controls\Range;

class Block extends BlockBaseAbstract {
	protected $block_name = 'map';
	protected $style_depends = [ 'ablocks-leaflet-style', 'ablocks-leaflet-full-screen-style' ];
	protected $script_depends = [ 'ablocks-leaflet-script', 'ablocks-leaflet-full-screen-script' ];

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-map-block',
			$this->get_map_size_css( $attributes ),
			$this->get_map_size_css( $attributes, 'Tablet' ),
			$this->get_map_size_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-map-block',
			$this->get_map_size_css( $attributes ),
			$this->get_map_size_css( $attributes, 'Tablet' ),
			$this->get_map_size_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	private function get_map_size_css( $attributes, $device = '' ) {
		$size_css = [];

		if ( ! empty( $attributes['mapWidth'][ 'value' . $device ] ) ) {
			$width_value = $attributes['mapWidth'][ 'value' . $device ];
			$width_unit = $attributes['mapWidth'][ 'valueUnit' . $device ] ?? '%';
			$size_css['width'] = $width_value . $width_unit;
		}

		if ( ! empty( $attributes['mapHeight'][ 'value' . $device ] ) ) {
			$height_value = $attributes['mapHeight'][ 'value' . $device ];
			$height_unit = ! empty( $attributes['mapHeight'][ 'valueUnit' . $device ] )
				? $attributes['mapHeight'][ 'valueUnit' . $device ]
				: 'px';

			$size_css['height'] = $height_value . $height_unit;
		}

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['mapWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['mapHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 500,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
			$size_css,
			isset( $attributes['cssFilter'] ) ? CssFilter::get_css( $attributes['cssFilter'], '', $device ) : []
		);
	}

	public static function escaping_array_data( $array ) {
		foreach ( $array as $key => &$value ) {
			if ( is_array( $value ) ) {
				$value = self::escaping_array_data( $value );
			} else {
				$value = esc_attr( sanitize_text_field( $value ) );
			}
		}
		return $array;
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$custom_icon_url = '';
		if ( isset( $attributes['iconImageUrl'] ) && ! empty( $attributes['iconImageUrl'] ) ) {
			$custom_icon_url = esc_url( $attributes['iconImageUrl'] );
		} else {
			$custom_icon_url = esc_url( ABLOCKS_ASSETS_URL . 'images/marker-icon.png' );
		}

		$settings = array(
			'mapMarkerList'      => $this->escaping_array_data( is_array( $attributes['mapMarkerList'] ?? null ) ? $attributes['mapMarkerList'] : [] ),
			'mapZoom'            => isset( $attributes['mapZoom'] ) ? intval( $attributes['mapZoom'] ) : 10,
			'scrollWheelZoom'    => isset( $attributes['scrollWheelZoom'] ) ? filter_var( $attributes['scrollWheelZoom'], FILTER_VALIDATE_BOOLEAN ) : false,
			'mapType'            => isset( $attributes['mapType'] ) ? sanitize_text_field( $attributes['mapType'] ) : 'GM',
			'centerIndex'        => isset( $attributes['centerIndex'] ) ? intval( $attributes['centerIndex'] ) : 0,
			'defaultMarkerIcon'  => $custom_icon_url,
			'iconHeight'         => isset( $attributes['iconHeight'] ) ? sanitize_text_field( $attributes['iconHeight'] ) : 40,
			'iconWidth'          => isset( $attributes['iconWidth'] ) ? sanitize_text_field( $attributes['iconWidth'] ) : 25,
		);

		ob_start();
		?>
		<div 
			data-settings='<?php echo esc_attr( wp_json_encode( $settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) ); ?>' 
			class="ablocks-map-block"
		>
		</div>
		<?php
		$output = ob_get_clean();
		return $output;
	}

}
