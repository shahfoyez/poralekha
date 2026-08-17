<?php
namespace ABlocks\Frontend\DynamicContent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Interpreter {

	protected array $interpreters = [
		'post-type' => Interpreters\PostType::class,
		'current'   => Interpreters\CurrentPost::class,
		'current-date-time' => Interpreters\CurrentDateTime::class,
		'site-title'   => Interpreters\SiteTitle::class,
		'site-tagline' => Interpreters\SiteTagline::class,
		'user-info'    => Interpreters\UserInfo::class,
		'request-parameter' => Interpreters\RequestParams::class,
		'shortcode' => Interpreters\Shortcode::class,
		'link'  => Interpreters\Link::class,
		'image' => Interpreters\Image::class,
	];
	protected string $content;
	protected array $context;
	public function __construct( string $content, $context = [] ) {
		$this->content = $content;
		$this->context = $context;
		$this->parse();// exit;
	}
	public function parse() : void {
		$this->content = preg_replace_callback(
			'~ablocks_dc:(.+?):ablocks_dc|<span\s+(?=[^>]*\bclass=["\']ablocks-richtext-dynamic-content["\'])(?=[^>]*\bdata-source=["\']([^"\']+)["\'])(?=[^>]*\bdata-field=["\']([^"\']+)["\'])[^>]*>(.*?)<\/span>~ims',
			[ $this, 'apply_changes' ],
			$this->content
		);
	}
	public function apply_changes( array $matches ): string {
		$filtered = $this->filter( $matches ); // returns an array
		$args = array_merge( $filtered, [ $this->interpreters, $this->context ] );
		$parser = new ArgumentParser( ...$args );
		$ins = $parser->interpret();
		return is_null( $ins ) ? '' : $ins->content();
	}

	public function filter( array $matches ) : array {
		$is_richtext = false;
		array_shift( $matches );
		if ( count( $matches ) > 2 ) {
			array_shift( $matches );
			$is_richtext = true;
		}
		return [
			implode( '|', $matches ),
			$is_richtext
		];
	}

	public static function init( string $content, $block, $instance ) : string {
		// This filter runs for EVERY block on the page (including core and
		// third-party blocks). The regex in parse() is expensive, so skip it
		// unless the block actually contains a dynamic-content marker.
		if ( false === strpos( $content, 'ablocks_dc' )
			&& false === strpos( $content, 'ablocks-richtext-dynamic-content' ) ) {
			return $content;
		}
		$ins = new self( $content, $instance->context );
		return $ins->content;
	}
}
