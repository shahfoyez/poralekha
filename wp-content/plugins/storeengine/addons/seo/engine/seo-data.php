<?php
/**
 * SeoData — the computed SEO "truth" for a single request/object.
 *
 * Channel-agnostic value object. The standalone head renderer, the bridge
 * adapters, and the REST/headless channel all read from the same instance.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeoData {

	public string $title = '';
	public string $description = '';
	public string $canonical = '';

	/**
	 * @var array{index:bool,follow:bool}
	 */
	public array $robots = [ 'index' => true, 'follow' => true ];

	public string $image = '';

	/**
	 * Open Graph tags (property => content).
	 *
	 * @var array<string,string>
	 */
	public array $og = [];

	/**
	 * Twitter card tags (name => content).
	 *
	 * @var array<string,string>
	 */
	public array $twitter = [];

	/**
	 * JSON-LD graph nodes (each an associative array).
	 *
	 * @var array<int,array>
	 */
	public array $schema = [];

	public function robots_string(): string {
		$parts   = [];
		$parts[] = $this->robots['index'] ? 'index' : 'noindex';
		$parts[] = $this->robots['follow'] ? 'follow' : 'nofollow';

		return implode( ', ', $parts );
	}

	/**
	 * Flat array for the REST/headless channel.
	 */
	public function to_array(): array {
		return [
			'title'       => $this->title,
			'description' => $this->description,
			'canonical'   => $this->canonical,
			'robots'      => $this->robots,
			'image'       => $this->image,
			'og'          => (object) $this->og,
			'twitter'     => (object) $this->twitter,
			'schema'      => $this->schema,
		];
	}
}

// End of file seo-data.php.
