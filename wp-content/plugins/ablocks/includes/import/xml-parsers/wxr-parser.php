<?php

namespace ABlocks\import\XmlParsers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Importer class for managing parsing of WXR files.
 */
class WXR_Parser {
	public function parse( $file ) {
		$parser = new WXR_Parser_SimpleXML();
		return $parser->parse( $file );
	}
}
