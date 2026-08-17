<?php
namespace ABlocks\Blocks\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'search';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-form',
			$this->get_SearchBar_css( $attributes ),
			$this->get_SearchBar_css( $attributes, 'Tablet' ),
			$this->get_SearchBar_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-form:hover',
			$this->get_SearchBar_Hover_CSS( $attributes ),
			$this->get_SearchBar_Hover_CSS( $attributes, 'Tablet' ),
			$this->get_SearchBar_Hover_CSS( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-input',
			$this->get_Input_css( $attributes ),
			$this->get_Input_css( $attributes, 'Tablet' ),
			$this->get_Input_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-input::placeholder',
			$this->get_Input_css( $attributes ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-button > span',
			$this->get_Button_css( $attributes ),
			$this->get_Button_css( $attributes, 'Tablet' ),
			$this->get_Button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-button:hover > span',
			$this->get_Button_hover_css( $attributes ),
			$this->get_Button_hover_css( $attributes, 'Tablet' ),
			$this->get_Button_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-button > span > svg',
			$this->get_Icon_css( $attributes ),
			$this->get_Icon_css( $attributes, 'Tablet' ),
			$this->get_Icon_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-result',
			$this->get_search_result_list( $attributes ),
			$this->get_search_result_list( $attributes, 'Tablet' ),
			$this->get_search_result_list( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-result:hover',
			$this->get_search_result_list_hover( $attributes ),
			$this->get_search_result_list_hover( $attributes, 'Tablet' ),
			$this->get_search_result_list_hover( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-result__list',
			$this->get_search_result_item( $attributes ),
			$this->get_search_result_item( $attributes, 'Tablet' ),
			$this->get_search_result_item( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-result__list:hover',
			$this->get_search_result_item_hover( $attributes ),
			$this->get_search_result_item_hover( $attributes, 'Tablet' ),
			$this->get_search_result_item_hover( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} a.ablocks-block--search-result__list-title',
			$this->get_search_result_title_css( $attributes ),
			$this->get_search_result_title_css( $attributes, 'Tablet' ),
			$this->get_search_result_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-search-block__spin',
			$this->get_loading_spinner_css( $attributes ),
			$this->get_loading_spinner_css( $attributes, 'Tablet' ),
			$this->get_loading_spinner_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-form',
			$this->get_SearchBar_css( $attributes ),
			$this->get_SearchBar_css( $attributes, 'Tablet' ),
			$this->get_SearchBar_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-form:hover',
			$this->get_SearchBar_Hover_CSS( $attributes ),
			$this->get_SearchBar_Hover_CSS( $attributes, 'Tablet' ),
			$this->get_SearchBar_Hover_CSS( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-input',
			$this->get_Input_css( $attributes ),
			$this->get_Input_css( $attributes, 'Tablet' ),
			$this->get_Input_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-input::placeholder',
			$this->get_Input_css( $attributes ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-button > span',
			$this->get_Button_css( $attributes ),
			$this->get_Button_css( $attributes, 'Tablet' ),
			$this->get_Button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-button:hover > span',
			$this->get_Button_hover_css( $attributes ),
			$this->get_Button_hover_css( $attributes, 'Tablet' ),
			$this->get_Button_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-button',
			$this->get_button_bg_css( $attributes ),
			$this->get_button_bg_css( $attributes, 'Tablet' ),
			$this->get_button_bg_css( $attributes, 'Mobile' )
		);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block--search-button',
				$this->get_button_bg_css( $attributes ),
				$this->get_button_bg_css( $attributes, 'Tablet' ),
				$this->get_button_bg_css( $attributes, 'Mobile' )
			);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-button:hover',
			$this->get_button_bg_hover_css( $attributes ),
			$this->get_button_bg_hover_css( $attributes, 'Tablet' ),
			$this->get_button_bg_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-button:hover',
			$this->get_button_bg_hover_css( $attributes ),
			$this->get_button_bg_hover_css( $attributes, 'Tablet' ),
			$this->get_button_bg_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-button > span > svg',
			$this->get_Icon_css( $attributes ),
			$this->get_Icon_css( $attributes, 'Tablet' ),
			$this->get_Icon_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-result',
			$this->get_search_result_list( $attributes ),
			$this->get_search_result_list( $attributes, 'Tablet' ),
			$this->get_search_result_list( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-result:hover',
			$this->get_search_result_list_hover( $attributes ),
			$this->get_search_result_list_hover( $attributes, 'Tablet' ),
			$this->get_search_result_list_hover( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-result__list',
			$this->get_search_result_item( $attributes ),
			$this->get_search_result_item( $attributes, 'Tablet' ),
			$this->get_search_result_item( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--search-result__list:hover',
			$this->get_search_result_item_hover( $attributes ),
			$this->get_search_result_item_hover( $attributes, 'Tablet' ),
			$this->get_search_result_item_hover( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} a.ablocks-block--search-result__list-title',
			$this->get_search_result_title_css( $attributes ),
			$this->get_search_result_title_css( $attributes, 'Tablet' ),
			$this->get_search_result_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-search-block__spin',
			$this->get_loading_spinner_css( $attributes ),
			$this->get_loading_spinner_css( $attributes, 'Tablet' ),
			$this->get_loading_spinner_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	public function get_SearchBar_css( $attributes, $device = '' ) {

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['gap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 0,
				'unitDefaultValue' => 'px',
				'hasUnit' => true,
				'property' => 'gap',
				'device' => $device,
			]),
			isset( $attributes['fullscreenButtonAlignment'] ) ? Alignment::get_css( $attributes['fullscreenButtonAlignment'], 'justify-content', $device ) : [],
			isset( $attributes['searchBoxBorder'] ) ? Border::get_css( $attributes['searchBoxBorder'], '', $device ) : [],
		);
	}

	public function get_SearchBar_Hover_CSS( $attributes, $device = '' ) {
		return array_merge(
			isset( $attributes['searchBoxBorder'] ) ? Border::get_hover_css( $attributes['searchBoxBorder'], '', $device ) : [],
		);
	}

	public function get_Input_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['inputTypographyGlobal'] ) ? $attributes['inputTypographyGlobal'] : array();
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['inputBgColor'] ) ? $attributes['inputBgColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['inputBgColor'] ) ? $attributes['inputBgColor'] : '' ) ],
			isset( $attributes['inputTypography'] ) ? Typography::get_css( $attributes['inputTypography'], '', $device, $typographyValueGlobal ) : [],
			isset( $attributes['inputTextStroke'] ) ? TextStroke::get_css( $attributes['inputTextStroke'], '', $device ) : [],
			isset( $attributes['inputTextShadow'] ) ? TextShadow::get_css( $attributes['inputTextShadow'], '', $device ) : [],
		);
	}
	public function get_loading_spinner_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['loadingSpinnerColor'] ) ? $attributes['loadingSpinnerColor'] : '' ) ];
	}
	public function get_search_result_title_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['searchResTypographyGlobal'] ) ? $attributes['searchResTypographyGlobal'] : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['searchResTColor'] ) ? $attributes['searchResTColor'] : '' ) ],
			isset( $attributes['searchResTypography'] ) ? Typography::get_css( $attributes['searchResTypography'], '', $device, $typographyValueGlobal ) : [],
		);
	}
	public function get_search_result_list( $attributes, $device = '' ) {
		$css = [];
		$css['overflow'] = 'auto';
		$defaultUnit = 'px';
		$offset_top = Range::get_css([
			'attributeValue' => $attributes['verticalOffset'],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'hasUnit' => true,
			'defaultValue' => 230,
			'unitDefaultValue' => 'px',
			'property' => 'top',
			'device' => $device,
		]);
		$offset_bottom = Range::get_css([
			'attributeValue' => $attributes['verticalOffset'],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'hasUnit' => true,
			'defaultValue' => 230,
			'unitDefaultValue' => 'px',
			'property' => 'bottom',
			'device' => $device,
		]);
		$offset_left = Range::get_css([
			'attributeValue' => $attributes['horizontalOffset'],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'hasUnit' => true,
			'defaultValue' => 230,
			'unitDefaultValue' => 'px',
			'property' => 'left',
			'device' => $device,
		]);
		$offset_right = Range::get_css([
			'attributeValue' => $attributes['horizontalOffset'],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'hasUnit' => true,
			'defaultValue' => 230,
			'unitDefaultValue' => 'px',
			'property' => 'right',
			'device' => $device,
		]);
		// Set the position property
		if ( ! empty( $attributes['position'] ) ) {
			$css['position'] = $attributes['position'];
		}

		// Ensure offsets only apply when the position is not "default"
		if ( ! empty( $attributes['position'] ) && $attributes['position'] === 'default' ) {
			unset( $offset_left['left'], $offset_right['right'], $offset_top['top'], $offset_bottom['bottom'] );
		}
		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['listWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => '',
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['searchItemHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 300,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['listGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 0,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			]),
			$offset_top,
			$offset_bottom,
			$offset_left,
			$offset_right,
			isset( $attributes['listPadding'] ) ? Dimensions::get_css( $attributes['listPadding'], 'padding', $device ) : [],
			isset( $attributes['listBorder'] ) ? Border::get_css( $attributes['listBorder'], '', $device ) : [],
		);
	}
	public function get_search_result_list_hover( $attributes, $device = '' ) {
		$css = [];

		return array_merge(
			$css,
			isset( $attributes['listBorder'] ) ? Border::get_hover_css( $attributes['listBorder'], $device ) : [],
		);
	}

	public function get_search_result_item( $attributes, $device = '' ) {
		$css = [];
		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['itemWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => '',
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['itemGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => '',
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			]),
			isset( $attributes['itemPadding'] ) ? Dimensions::get_css( $attributes['itemPadding'], 'padding', $device ) : [],
			isset( $attributes['itemBorder'] ) ? Border::get_css( $attributes['itemBorder'], '', $device ) : [],
		);
	}

	public function get_search_result_item_hover( $attributes, $device = '' ) {
		$css = [];

		return array_merge(
			$css,
			isset( $attributes['itemBorder'] ) ? Border::get_hover_css( $attributes['itemBorder'], '', $device ) : [],
		);
	}

	public function get_Button_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['buttonTypographyGlobal'] ) ? $attributes['buttonTypographyGlobal'] : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonTextColor'] ) ? $attributes['buttonTextColor'] : '' ) ],
			isset( $attributes['buttonTypography'] ) ? Typography::get_css( $attributes['buttonTypography'], '', $device, $typographyValueGlobal ) : [],
			isset( $attributes['buttonTextStroke'] ) ? TextStroke::get_css( $attributes['buttonTextStroke'], '', $device ) : [],
			isset( $attributes['buttonTextShadow'] ) ? TextStroke::get_css( $attributes['buttonTextShadow'], '', $device ) : [],
			Range::get_css([
				'attributeValue' => $attributes['searchBtnWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 70,
				'unitDefaultValue' => 'px',
				'hasUnit' => true,
				'property' => 'width',
				'device' => $device,
			]),
		);
	}
	public function get_Button_hover_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['buttonTypographyHGlobal'] ) ? $attributes['buttonTypographyHGlobal'] : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonTextColorH'] ) ? $attributes['buttonTextColorH'] : '' ) ],
			isset( $attributes['buttonTypographyH'] ) ? Typography::get_css( $attributes['buttonTypographyH'], '', $device, $typographyValueGlobal ) : [],
			isset( $attributes['buttonTextStrokeH'] ) ? TextStroke::get_css( $attributes['buttonTextStrokeH'], '', $device ) : [],
			isset( $attributes['buttonTextShadowH'] ) ? TextStroke::get_css( $attributes['buttonTextShadowH'], '', $device ) : [],
		);
	}

	public function get_button_bg_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['buttonBgColor'] ) ? $attributes['buttonBgColor'] : '' ) ],
		);
	}
	public function get_button_bg_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['buttonBgColorH'] ) ? $attributes['buttonBgColorH'] : '' ) ],
		);
	}
	public function get_Icon_css( $attributes, $device = '' ) {

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['iconWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 18,
				'unitDefaultValue' => 'px',
				'hasUnit' => true,
				'property' => 'width',
				'device' => $device,
			]),
		);
	}



	public function render_block_content( $attributes, $content, $block_instance ) {

		// Sanitize and escape input attributes
		$currentPostID = isset( $attributes['currentPostID'] ) ? (int) sanitize_text_field( $attributes['currentPostID'] ) : '';
		$source = isset( $attributes['source'] ) ? sanitize_text_field( $attributes['source'] ) : '';
		$placeholder = isset( $attributes['placeholder'] ) ? sanitize_text_field( $attributes['placeholder'] ) : esc_html__( 'Write anything...', 'ablocks' );
		$variant = isset( $attributes['variant'] ) ? sanitize_key( $attributes['variant'] ) : 'classic';
		$isIcon = isset( $attributes['isIcon'] ) ? sanitize_key( $attributes['isIcon'] ) : 'icon';
		$buttonAlignment = isset( $attributes['buttonAlignment'] ) ? sanitize_text_field( $attributes['buttonAlignment'] ) : 'left';
		$allowCollapse = isset( $attributes['allowCollapse'] ) ? sanitize_key( $attributes['allowCollapse'] ) : false;
		$buttonText = isset( $attributes['buttonText'] ) ? sanitize_text_field( $attributes['buttonText'] ) : esc_html__( 'Search', 'ablocks' );
		$buttonAlignment = isset( $attributes['buttonAlignment']['value'] ) ? sanitize_key( $attributes['buttonAlignment']['value'] ) : 'left';

		ob_start();
		?>
		<div class="ablocks-block--search-bar <?php echo esc_attr( $variant ); ?>">
			<form method="post" class="ablocks-block--search-form <?php echo esc_attr( ( $isIcon === 'both' || $isIcon === 'text' ) ? $isIcon : '' ); ?>">
				<?php if ( 'left' === $buttonAlignment && 'classic' !== $variant ) : ?>
					<button type="button" class="ablocks-block--search-button <?php echo esc_attr( ( $isIcon === 'both' || $isIcon === 'text' ) ? $isIcon : '' ); ?>  searchbar-submit-left">
						<span class="button-content">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo $this->render_button( $isIcon, $buttonText );
							?>
						</span>
						<span class="loading-spinner">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo $this->loading_spinner();
							?>
						</span>
					</button>
				<?php endif; ?>
				<input class="ablocks-block--search-input <?php echo esc_attr( $allowCollapse ? 'ablocks-block--search-input-collapse' : '' ); ?> <?php echo esc_attr( ( $isIcon === 'both' || $isIcon === 'text' ) ? $isIcon : '' ); ?> searchbar-input-<?php echo esc_attr( $buttonAlignment ); ?>" type="text" placeholder="<?php echo esc_attr( $placeholder ); ?>" value=""/>
				<?php if ( 'right' === $buttonAlignment || 'classic' === $variant ) : ?>
					<button type="button" class="ablocks-block--search-button <?php echo esc_attr( ( $isIcon === 'both' || $isIcon === 'text' ) ? $isIcon : '' ); ?> searchbar-submit-right">
						<span class="button-content">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo $this->render_button( $isIcon, $buttonText );
							?>
						</span>
						<span class="loading-spinner">
							<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
								echo $this->loading_spinner();
							?>
						</span>
					</button>
				<?php endif; ?>
				<input type="hidden" class="ablocks-block--search-source" value="<?php echo esc_attr( $source ); ?>"/>
				<input type="hidden" class="ablocks-block--search__current-post-id" value="<?php echo esc_attr( $currentPostID ); ?>"/>
			</form>
			<ul class="ablocks-block--search-result ablocks-block--search-empty-result">
				<!-- Search results will go here -->
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}


	private function render_button( $isIcon, $buttonText ) {
		$search_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search">
							<circle cx="11" cy="11" r="8"></circle>
							<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
						</svg>';

		if ( 'icon' === $isIcon ) {
			return $search_icon;
		} elseif ( 'text' === $isIcon ) {
			return '<span>' . esc_html( $buttonText ) . '</span>';
		} else {
			return $search_icon . '<span>' . esc_html( $buttonText ) . '</span>';
		}
	}

	private function loading_spinner() {
		$spinner = '<svg viewBox="0 0 800 800" xmlns="http://www.w3.org/2000/svg">
			<circle class="ablocks-search-block__spin" cx="400" cy="400" fill="none"
				r="200" stroke-width="50" stroke="currentColor"
				stroke-dasharray="700 1400"
				stroke-linecap="round" />
			</svg>';
		return $spinner;
	}
}

