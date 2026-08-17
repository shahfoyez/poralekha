<?php
namespace ABlocks\Blocks\Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Border;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'table';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table table',
			$this->get_table_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table table, {{WRAPPER}}.ablocks-block--table-header .ablocks-block--table-cell , {{WRAPPER}}.ablocks-block--table .ablocks-block--table-cell',
			$this->get_table_border_css( $attributes ),
			$this->get_table_border_css( $attributes, 'Tablet' ),
			$this->get_table_border_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table table:hover, {{WRAPPER}}.ablocks-block--table-header .ablocks-block--table-cell:hover, {{WRAPPER}}.ablocks-block--table .ablocks-block--table-cell:hover',
			$this->get_table_border_hover_css( $attributes ),
			$this->get_table_border_hover_css( $attributes, 'Tablet' ),
			$this->get_table_border_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body .ablocks-table-row--odd',
			$this->get_row_odd_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body .ablocks-table-row--odd:hover',
			$this->get_row_odd_hover_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body .ablocks-table-row--even',
			$this->get_row_even_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body .ablocks-table-row--even:hover',
			$this->get_row_even_hover_css( $attributes )
		);
		// ---header----
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table .ablocks-block--table-header',
			$this->get_header_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table .ablocks-block--table-header:hover',
			$this->get_header_hover_css( $attributes )
		);
		// --table body----
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body',
			$this->get_body_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body:hover',
			$this->get_body_hover_css( $attributes )
		);

		// --table footer--
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table .ablocks-block--table-footer',
			$this->get_footer_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table .ablocks-block--table-footer:hover',
			$this->get_footer_hover_css( $attributes )
		);
		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table table',
			$this->get_table_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table table, {{WRAPPER}}.ablocks-block--table-header .ablocks-block--table-cell , {{WRAPPER}}.ablocks-block--table .ablocks-block--table-cell',
			$this->get_table_border_css( $attributes ),
			$this->get_table_border_css( $attributes, 'Tablet' ),
			$this->get_table_border_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table table:hover, {{WRAPPER}}.ablocks-block--table-header .ablocks-block--table-cell:hover, {{WRAPPER}}.ablocks-block--table .ablocks-block--table-cell:hover',
			$this->get_table_border_hover_css( $attributes ),
			$this->get_table_border_hover_css( $attributes, 'Tablet' ),
			$this->get_table_border_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body .ablocks-table-row--odd',
			$this->get_row_odd_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body .ablocks-table-row--odd:hover',
			$this->get_row_odd_hover_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body .ablocks-table-row--even',
			$this->get_row_even_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body .ablocks-table-row--even:hover',
			$this->get_row_even_hover_css( $attributes )
		);
		// ---header----
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table .ablocks-block--table-header',
			$this->get_header_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table .ablocks-block--table-header:hover',
			$this->get_header_hover_css( $attributes )
		);
		// --table body----
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body',
			$this->get_body_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--table-body:hover',
			$this->get_body_hover_css( $attributes )
		);

		// --table footer--
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table .ablocks-block--table-footer',
			$this->get_footer_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--table .ablocks-block--table-footer:hover',
			$this->get_footer_hover_css( $attributes )
		);
		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	public function get_table_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['borderCollapse'] ) && $attributes['borderCollapse'] === 'collapse' ) {
			$css['border-collapse'] = 'collapse';
		} elseif ( ! empty( $attributes['borderCollapse'] ) && $attributes['borderCollapse'] === 'separate' ) {
			$css['border-collapse'] = 'separate';
		}

		return $css;
	}

	public function get_table_border_css( $attributes, $device = '' ) {

		return Border::get_css( $attributes['border'], '', $device );

	}
	public function get_table_border_hover_css( $attributes, $device = '' ) {

		return Border::get_hover_css( $attributes['border'], '', $device );
	}

	public function get_row_odd_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['rowOddColor'] ) ? $attributes['rowOddColor'] : '' ) ];
	}

	public function get_row_odd_hover_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['rowOddColorH'] ) ? $attributes['rowOddColorH'] : '' ) ];
	}

	public function get_row_even_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['rowEvenColor'] ) ? $attributes['rowEvenColor'] : '' ) ];
	}

	public function get_row_even_hover_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['rowEvenColorH'] ) ? $attributes['rowEvenColorH'] : '' ) ];
	}

	public function get_header_css( $attributes, $device = '' ) {
		$css = [];
		$css['background'] = Color::get_css(
		isset( $attributes['headerColor'] ) ? $attributes['headerColor'] : '') . ' !important';
		return $css;
	}

	public function get_header_hover_css( $attributes, $device = '' ) {
		$css = [];
		$css['background'] = Color::get_css(
		isset( $attributes['headerColorH'] ) ? $attributes['headerColorH'] : '') . ' !important';

		return $css;
	}

	public function get_body_css( $attributes, $device = '' ) {
		$css = [];
		$css['background'] = Color::get_css(
		isset( $attributes['bodyBg'] ) ? $attributes['bodyBg'] : '') . ' !important';
		return $css;
	}

	public function get_body_hover_css( $attributes, $device = '' ) {
		$css = [];
		$css['background'] = Color::get_css(
		isset( $attributes['bodyBgH'] ) ? $attributes['bodyBgH'] : '') . ' !important';

		return $css;
	}

	public function get_footer_css( $attributes, $device = '' ) {
		$css = [];
		$css['background'] = Color::get_css(
		isset( $attributes['footerColor'] ) ? $attributes['footerColor'] : '') . ' !important';

		return $css;
	}

	public function get_footer_hover_css( $attributes, $device = '' ) {
		$css = [];

		$css['background'] = Color::get_css(
		isset( $attributes['footerColorH'] ) ? $attributes['footerColorH'] : '') . ' !important';

		return $css;
	}
}
