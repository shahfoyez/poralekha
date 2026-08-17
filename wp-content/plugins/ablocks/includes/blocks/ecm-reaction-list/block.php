<?php

namespace ABlocks\Blocks\EcmReactionList;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Color;
use ABlocks\Controls\Alignment;

class Block extends BlockBaseAbstract {
	protected $block_name = 'ecm-reaction-list';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} p.ecm-shortcode--reaction__list-title',
			$this->getTitleTextCSS( $attributes ),
			$this->getTitleTextCSS( $attributes, 'Tablet' ),
			$this->getTitleTextCSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} ul.ecm-shortcode--reaction__list',
			$this->get_alignment_css( $attributes ),
			$this->get_alignment_css( $attributes, 'Tablet' ),
			$this->get_alignment_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} p.ecm-shortcode--reaction__list-meta',
			$this->getListTextCSS( $attributes ),
			$this->getListTextCSS( $attributes, 'Tablet' ),
			$this->getListTextCSS( $attributes, 'Mobile' )
		);	
		$css_generator->add_class_styles(
			'{{WRAPPER}} div.ecm-shortcode--reaction',
			$this->nobookmarksTextCSS( $attributes ),
			$this->nobookmarksTextCSS( $attributes, 'Tablet' ),
			$this->nobookmarksTextCSS( $attributes, 'Mobile' )
		);				
		return $css_generator->generate_css();
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'user_id'     				  => Helper::get_attribute_value( $attributes, 'user_id' ),
			'not_found_text'     		  => Helper::get_attribute_value( $attributes, 'not_found_text' ),
		];

		// if ( isset( $block_instance->context['postId'] ) && ! empty( $block_instance->context['postId'] ) && empty( $attr_array['product_id'] ) ) {
		// 	$attr_array['product_id'] = $block_instance->context['postId'];
		// }

		// // phpcs:ignore  WordPress.Security.NonceVerification.Recommended,  WordPress.Security.ValidatedSanitizedInput.MissingUnslash 
		// if ( isset( $_GET['context'] ) && 'edit' === sanitize_text_field( $_GET['context'] ) ) {
		// 	$attr_array['dummy'] = true;
		// }

		$shortcode = '[ecm_reaction_list ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
		
	}

	public function getTitleTextCSS( $attributes, $device = '' ) {
		// $typographyGlobal = ( isset( $attributes['couponTypographyGlobal'] ) ? $attributes['couponTypographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['reacTitleColor'] ) ? $attributes['reacTitleColor'] : '' ) ],

			Typography::get_css( $attributes['reacTitleTypography'], '', $device ),

		);
	}
	public function get_alignment_css( $attributes, $device = '' ) {
		$css = [
			'flex-direction' => 'column',
			'display' => 'flex'
		];

		if ( isset( $attributes['reactionAlignment'] ) ) {
			$css = array_merge( $css, Alignment::get_css( $attributes['reactionAlignment'], 'align-items', $device ) );
			$css = array_merge( $css, Alignment::get_css( $attributes['reactionAlignment'], 'text-align', $device ) );
			$css['list-style-type'] = $attributes['reactionUlStyle'];
		}
			return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['reacTitleColor'] ) ? $attributes['reacTitleColor'] : '' ) ],
			Typography::get_css( $attributes['reacTitleTypography'], '', $device ),
			$css,
		);
	}
	public function getListTextCSS( $attributes, $device = '' ) {
		// $typographyGlobal = ( isset( $attributes['couponTypographyGlobal'] ) ? $attributes['couponTypographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['reacListColor'] ) ? $attributes['reacListColor'] : '' ) ],

			Typography::get_css( $attributes['reacListTypography'], '', $device ),

		);
	}
	public function nobookmarksTextCSS( $attributes, $device = '' ) {
		$css = ['flex-direction' => 'column'];

		if ( isset( $attributes['reactionAlignment'] ) ) {
			$css = array_merge( $css, Alignment::get_css( $attributes['reactionAlignment'], 'align-items', $device ) );
		}		
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['nobookmarksColor'] ) ? $attributes['nobookmarksColor'] : '' ) ],

			Typography::get_css( $attributes['nobookmarksTypography'], '', $device ),
            $css,
		);
	}	
}
