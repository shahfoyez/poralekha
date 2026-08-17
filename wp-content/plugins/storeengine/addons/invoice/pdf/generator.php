<?php
/**
 * PDF file generator.
 */

namespace StoreEngine\Addons\Invoice\PDF;

use StoreEngine\Addons\Invoice\HelperAddon;
use StoreEngine\Mpdf\Output\Destination;
use StoreEngine\Mpdf\HTMLParserMode;
use StoreEngine\Mpdf\Config\FontVariables;
use StoreEngine\Mpdf\Mpdf;
use StoreEngine\Mpdf\MpdfException;
use StoreEngine\Utils\Helper;
use Throwable;

class Generator {

	public ?Mpdf $mpdf = null;
	protected string $template;
	protected string $styles;
	protected string $page_size;
	protected string $page_orientation;

	public function __construct( string $template, string $styles, array $args = [] ) {
		$ars = wp_parse_args( $args, [
			'page_size'        => HelperAddon::get_setting( 'invoice_paper_size', 'A4' ),
			'page_orientation' => 'P',
		] );

		$this->template         = $template;
		$this->styles           = $styles;
		$this->page_size        = $ars['page_size'];
		$this->page_orientation = $ars['page_orientation'];
	}

	/**
	 * Get mPDF settings.
	 * @return array
	 */
	public function get_config(): array {
		return apply_filters(
			'storeengine/invoice/mpdf_config',
			[
				'mode'             => 'utf-8',
				'tempDir'          => Helper::get_upload_dir() . '/invoice',
				'fontDir'          => [ HelperAddon::get_fonts_dir() . '/ttfonts' ],
				'format'           => $this->page_size,
				'orientation'      => $this->page_orientation,
				'margin_left'      => 0,
				'margin_right'     => 0,
				'margin_top'       => 0,
				'margin_bottom'    => 0,
				'default_font'     => 'dejavusans',
				'autoScriptToLang' => true,
				'autoLangToFont'   => true,
				'fontdata'         => ( new FontVariables() )->getDefaults()['fontdata'],
			]
		);
	}

	/**
	 * Initialize mPDF.
	 *
	 * @return void
	 * @throws MpdfException
	 */
	public function init_mpdf() {
		if ( isset( $this->mpdf ) ) {
			return;
		}

		$this->mpdf = new Mpdf( $this->get_config() );
		$this->mpdf->setMBencoding( 'UTF-8' );
	}

	/**
	 * Prepare the PDF content.
	 *
	 * @return Mpdf
	 * @throws MpdfException
	 */
	public function prepare_pdf(): Mpdf {
		$this->init_mpdf();
		$this->mpdf->WriteHTML( $this->styles, HTMLParserMode::HEADER_CSS );
		$this->mpdf->WriteHTML( $this->template );

		return $this->mpdf;
	}

	public function preview( $title, bool $download = false ) {
		try {
			$this->prepare_pdf()
			     ->Output(
				     sanitize_file_name( wp_strip_all_tags( $title ) ) . '.pdf',
				     $download ? Destination::DOWNLOAD : Destination::INLINE
			     );
			exit;
		} catch ( Throwable $e ) {
			wp_die(
				esc_html( $e->getMessage() ),
				esc_html__( 'Error: Failed to generate invoice', 'storeengine' ),
				[
					'response'  => 500,
					'back_link' => true,
				]
			);
		}
	}

	/**
	 * Save the PDF file on disk.
	 *
	 * @param string $filepath file path to save the pdf.
	 *
	 * @return string
	 * @throws MpdfException
	 */
	public function save( string $filepath ): string {
		if ( ! str_ends_with( strtolower( $filepath ), '.pdf' ) ) {
			$filepath .= '.pdf';
		}

		$this->prepare_pdf()->Output( $filepath, Destination::FILE );

		return $filepath;
	}
}
