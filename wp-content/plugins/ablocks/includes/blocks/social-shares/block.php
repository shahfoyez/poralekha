<?php
namespace ABlocks\Blocks\SocialShares;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Range;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Color;
class Block extends BlockBaseAbstract {
	protected $block_name = 'social-shares';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
			$css_generator->add_class_styles(
				'{{WRAPPER}}.ablocks-block--social-shares .ablocks-block-container',
				$this->get_share_css( $attributes, '' ),
				$this->get_share_css( $attributes, 'Tablet' ),
				$this->get_share_css( $attributes, 'Mobile' )
			);
			// Social Button Styles
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-container .ablocks-social-share',
				$this->get_social_share_bar_css( $attributes ),
				$this->get_social_share_bar_css( $attributes, 'Table' ),
				$this->get_social_share_bar_css( $attributes, 'Mobile' )
			);
			// share Hover Styles
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-container .ablocks-social-share:hover',
				$this->get_share_border_hover_css( $attributes, '' ),
				$this->get_share_border_hover_css( $attributes, 'Tablet' ),
				$this->get_share_border_hover_css( $attributes, 'Mobile' )
			);
			// share Icon Size
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share > .ablocks-svg-icon',
				$this->get_share_icon_css( $attributes ),
				$this->get_share_icon_css( $attributes, 'Table' ),
				$this->get_share_icon_css( $attributes, 'Mobile' ),
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share > .ablocks-svg-icon:hover',
				$this->get_share_icon_hover_css(
					$attributes
				),
			);
			// item style
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share-item',
				$this->get_item_border_css( $attributes ),
				$this->get_item_border_css( $attributes, 'Tablet' ),
				$this->get_item_border_css( $attributes, 'Mobile' )
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-container .ablocks-social-share-item:hover',
				$this->get_Item_border_hover_css( $attributes ),
				$this->get_Item_border_hover_css( $attributes, 'Tablet' ),
				$this->get_Item_border_hover_css( $attributes, 'Mobile' )
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share-item--icon',
				$this->get_share_item_css( $attributes ),
				$this->get_share_item_css( $attributes, 'Tablet' ),
				$this->get_share_item_css( $attributes, 'Mobile' ),
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share-item--icon>.ablocks-svg-icon',
				$this->shareItemIconSVG( $attributes ),
				$this->shareItemIconSVG( $attributes, 'Tablet' ),
				$this->shareItemIconSVG( $attributes, 'Mobile' ),
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share-item--text',
				$this->get_share_item_text_css( $attributes, '' ),
				$this->get_share_item_text_css( $attributes, 'Tablet' ),
				$this->get_share_item_text_css( $attributes, 'Mobile' )
			);
		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
			$css_generator->add_class_styles(
				'{{WRAPPER}}.ablocks-block--social-shares:not(.ablocks-has-container),
                   {{WRAPPER}}.ablocks-block--social-shares .ablocks-block-container',
				$this->get_share_css( $attributes, '' ),
				$this->get_share_css( $attributes, 'Tablet' ),
				$this->get_share_css( $attributes, 'Mobile' )
			);
			// Social Button Styles
			$css_generator->add_class_styles(
				'{{WRAPPER}}  .ablocks-social-share',
				$this->get_social_share_bar_css( $attributes ),
				$this->get_social_share_bar_css( $attributes, 'Table' ),
				$this->get_social_share_bar_css( $attributes, 'Mobile' )
			);
				// share Hover Styles
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share:hover',
				$this->get_share_border_hover_css( $attributes, '' ),
				$this->get_share_border_hover_css( $attributes, 'Tablet' ),
				$this->get_share_border_hover_css( $attributes, 'Mobile' )
			);

			// share Icon Size
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share > .ablocks-svg-icon',
				$this->get_share_icon_css( $attributes ),
				$this->get_share_icon_css( $attributes, 'Table' ),
				$this->get_share_icon_css( $attributes, 'Mobile' ),
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share > .ablocks-svg-icon:hover',
				$this->get_share_icon_hover_css(
					$attributes
				),
			);
			// item style
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share-item',
				$this->get_item_border_css( $attributes ),
				$this->get_item_border_css( $attributes, 'Tablet' ),
				$this->get_item_border_css( $attributes, 'Mobile' )
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share-item:hover',
				$this->get_Item_border_hover_css( $attributes ),
				$this->get_Item_border_hover_css( $attributes, 'Tablet' ),
				$this->get_Item_border_hover_css( $attributes, 'Mobile' )
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share-item--icon',
				$this->get_share_item_css( $attributes ),
				$this->get_share_item_css( $attributes, 'Tablet' ),
				$this->get_share_item_css( $attributes, 'Mobile' ),
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share-item--icon>.ablocks-svg-icon',
				$this->shareItemIconSVG( $attributes ),
				$this->shareItemIconSVG( $attributes, 'Tablet' ),
				$this->shareItemIconSVG( $attributes, 'Mobile' ),
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-social-share-item--text',
				$this->get_share_item_text_css( $attributes, '' ),
				$this->get_share_item_text_css( $attributes, 'Tablet' ),
				$this->get_share_item_text_css( $attributes, 'Mobile' )
			);
		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	public function get_share_css( $attributes, $device = '' ) {
		$css = [];
		$stack = $attributes[ 'stack' . $device ] ?? $attributes['stack'] ?? '';
		if ( $stack === 'vertical' ) {
			$css['flex-direction'] = 'column';
			if ( ! empty( $attributes[ 'verticalAlignment' . $device ] ) ) {
				$css['align-items'] = $attributes[ 'verticalAlignment' . $device ];
			}
		} elseif ( $stack === 'horizontal' ) {
			$css['flex-direction'] = 'row';
			$horizontal_alignment = $attributes[ 'horizontalAlignment' . $device ] ?? $attributes['horizontalAlignment'] ?? '';
			if ( ! empty( $horizontal_alignment ) ) {
				$css['justify-content'] = $horizontal_alignment;
			}
		}
		$alignment_css = isset( $attributes['horizontalAlignment'] )
			? Alignment::get_css( $attributes['horizontalAlignment'], 'justify-content', $device )
			: [];
		$css = array_merge( $css, $alignment_css );

		foreach ( $css as $property => $value ) {
			if ( ! is_string( $value ) || empty( $value ) ) {
				unset( $css[ $property ] );
			}
		}
		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['spaceBetween'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 20,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			])
		);
	}


	public function get_social_share_bar_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['buttonBackground'] ) ? $attributes['buttonBackground'] : '' ) ],
			Border::get_css( $attributes['border'], $device ),
			Range::get_css([
				'attributeValue' => $attributes['shareSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => 48,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['shareSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => 48,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]),
		);
	}

	public function get_share_border_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['buttonHover'] ) ? $attributes['buttonHover'] : '' ) ],
			( isset( $attributes['border'] ) ? Border::get_hover_css( $attributes['border'], '', $device ) : [] )
		);
	}
	public function get_share_icon_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'fill' => Color::get_css( isset( $attributes['shareButtonIconColor'] ) ? $attributes['shareButtonIconColor'] : '' ) . '!important' ],
			Range::get_css( [
				'attributeValue' => $attributes['shareIconSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => 16,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			] ),
			Range::get_css( [
				'attributeValue' => $attributes['shareIconSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => 16,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			] )
		);
	}
	public function get_share_icon_hover_css( $attributes, $device = '' ) {
		return [ 'fill' => Color::get_css( isset( $attributes['shareButtonIconColorH'] ) ? $attributes['shareButtonIconColorH'] : '' ) . '!important' ];
	}
	public function shareItemIconSVG( $attributes, $device = '' ) {
		return Range::get_css( [
			'attributeValue' => $attributes['shareItemIconSize'],
			'attribute_object_key' => 'value',
			'isResponsive' => false,
			'defaultValue' => 42,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'height',
			'device' => $device,
		] );
	}

	public function get_share_item_css( $attributes, $device = '' ) {
		$css = [];
		$range_width = Range::get_css([
			'attributeValue' => $attributes['itemIconWidth'],
			'attribute_object_key' => 'value',
			'isResponsive' => false,
			'defaultValue' => 43,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'width',
			'device' => $device,
		]);
		$range_height = Range::get_css([
			'attributeValue' => $attributes['itemIconHeight'],
			'attribute_object_key' => 'value',
			'isResponsive' => false,
			'defaultValue' => 42,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'height',
			'device' => $device,
		] );
		return array_merge( $css, $range_width, $range_height );
	}
	public function get_item_border_css( $attributes, $device = '' ) {
		$css = ( isset( $attributes['itemBorder'] )
		? Border::get_css( $attributes['itemBorder'], '', $device )
		: [] );
		return array_merge(
			$css,
			BoxShadow::get_css( $attributes['shareItemShadow'], '', $device ),
		);
	}
	public function get_Item_border_hover_css( $attributes, $device = '' ) {
		return array_merge(
			( isset( $attributes['itemBorder'] ) ? Border::get_hover_css( $attributes['itemBorder'], '', $device ) : [] ),
			BoxShadow::get_hover_css( $attributes['shareItemShadow'], '', $device )
		);
	}
	public function get_share_item_text_css( $attributes, $device = '' ) {
		$css = [];
		$range_width = Range::get_css([
			'attributeValue' => $attributes['itemTextWidth'],
			'attribute_object_key' => 'value',
			'isResponsive' => false,
			'defaultValue' => 80,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'width',
			'device' => $device,
		]);
		$range_height = Range::get_css( [
			'attributeValue' => $attributes['itemTextHeight'],
			'attribute_object_key' => 'value',
			'isResponsive' => false,
			'defaultValue' => 42,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'height',
			'device' => $device,
		] );
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : array();
		return array_merge(
			$css,
			$range_width,
			$range_height,
			isset( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'text-align', $device ) : [],
			isset( $attributes['typography'] ) ? Typography::get_css( $attributes['typography'], '', $device, $typographyValueGlobal ) : [],
			isset( $attributes['textShadow'] ) ? TextShadow::get_css( $attributes['textShadow'], '', $device ) : [],
			isset( $attributes['textStroke'] ) ? TextStroke::get_css( $attributes['textStroke'], '', $device ) : []
		);
	}

}
