<?php
/**
 * Shortcode → Block bridge: the registry.
 *
 * Single owner of every shortcode-block descriptor. Plugins register their
 * shortcodes here (imperatively via {@see storeengine_register_shortcode_block()} or
 * declaratively via the `storeengine_shortcode_block_registry` filter); the generic
 * `ablocks/shortcode` block and its editor render purely from this data.
 *
 * Descriptors are validated on registration — malformed ones are skipped and
 * logged rather than fataling, so one bad plugin can't take down the editor.
 *
 * @see docs/shortcode-block-bridge.md for the full v1 contract.
 * @package StoreEngine\Blocks
 */

namespace StoreEngine\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registry {

	/**
	 * Descriptor format version. Bump only on breaking changes.
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Fixed v1 attribute types catalog. Adding is additive; renaming is breaking.
	 */
	const TYPES = [
		'text', 'textarea', 'number', 'range', 'toggle', 'select', 'radio',
		'color', 'post-select', 'taxonomy-select', 'csv',
	];

	const CHOICE_TYPES = [ 'select', 'radio' ];

	/**
	 * @var array<string, array> Descriptors keyed by "owner/tag".
	 */
	private array $descriptors = [];

	/**
	 * Whether the declarative filter has been collected yet.
	 */
	private bool $collected = false;

	private static ?Registry $instance = null;

	public static function instance(): Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register one shortcode-block descriptor. Returns false (and logs) when the
	 * descriptor is malformed.
	 */
	public function register( array $descriptor ): bool {
		$error = $this->validate( $descriptor );
		if ( is_wp_error( $error ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf(
					'[shortcode-block] skipped descriptor for "%s/%s": %s',
					$descriptor['owner'] ?? '?',
					$descriptor['tag'] ?? '?',
					$error->get_error_message()
				) );
			}

			return false;
		}

		$normalized = $this->normalize( $descriptor );
		$key        = $normalized['owner'] . '/' . $normalized['tag'];

		// Keyed by owner/tag: two plugins registering the same bare tag coexist.
		$this->descriptors[ $key ] = $normalized;

		return true;
	}

	/**
	 * The full registry (both imperative + declarative registrations).
	 *
	 * @return array<int, array>
	 */
	public function all(): array {
		$this->collect();

		return array_values( $this->descriptors );
	}

	/**
	 * A single descriptor by its fully-qualified "owner/tag" id, or null.
	 */
	public function get( string $owner_tag ): ?array {
		$this->collect();

		return $this->descriptors[ $owner_tag ] ?? null;
	}

	/**
	 * Pull in declarative registrations once (lazy — after all plugins loaded).
	 */
	private function collect(): void {
		if ( $this->collected ) {
			return;
		}
		$this->collected = true;

		/**
		 * Declarative registration: return an array of descriptors.
		 *
		 * @param array[] $descriptors
		 */
		$declarative = apply_filters( 'storeengine_shortcode_block_registry', [] );
		foreach ( (array) $declarative as $descriptor ) {
			if ( is_array( $descriptor ) ) {
				$this->register( $descriptor );
			}
		}
	}

	/* -------------------------------------------------------------- */
	/* Validation                                                      */
	/* -------------------------------------------------------------- */

	/**
	 * @return true|\WP_Error
	 */
	private function validate( array $d ) {
		foreach ( [ 'tag', 'owner', 'title' ] as $required ) {
			if ( empty( $d[ $required ] ) || ! is_string( $d[ $required ] ) ) {
				return new \WP_Error( 'missing_field', "missing required field \"$required\"" );
			}
		}

		if ( isset( $d['content'] ) ) {
			$mode = $d['content']['mode'] ?? 'plain';
			if ( ! in_array( $mode, [ 'plain', 'innerblocks' ], true ) ) {
				return new \WP_Error( 'bad_content_mode', "content.mode must be plain|innerblocks" );
			}
		}

		if ( isset( $d['preview']['mode'] )
			&& ! in_array( $d['preview']['mode'], [ 'server', 'static', 'none' ], true ) ) {
			return new \WP_Error( 'bad_preview_mode', 'preview.mode must be server|static|none' );
		}

		foreach ( (array) ( $d['attributes'] ?? [] ) as $i => $attr ) {
			$err = $this->validate_attribute( $attr, $i );
			if ( is_wp_error( $err ) ) {
				return $err;
			}
		}

		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	private function validate_attribute( $attr, $i ) {
		if ( ! is_array( $attr ) || empty( $attr['name'] ) || empty( $attr['label'] ) ) {
			return new \WP_Error( 'bad_attribute', "attribute #$i needs name + label" );
		}
		$type = $attr['type'] ?? 'text';
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return new \WP_Error( 'bad_type', "attribute \"{$attr['name']}\" has unknown type \"$type\"" );
		}
		if ( in_array( $type, self::CHOICE_TYPES, true ) && empty( $attr['options'] ) ) {
			return new \WP_Error( 'missing_options', "attribute \"{$attr['name']}\" ($type) requires options" );
		}
		if ( 'range' === $type && ( ! isset( $attr['min'] ) || ! isset( $attr['max'] ) ) ) {
			return new \WP_Error( 'missing_range', "attribute \"{$attr['name']}\" (range) requires min + max" );
		}
		if ( 'post-select' === $type && empty( $attr['post_type'] ) ) {
			return new \WP_Error( 'missing_post_type', "attribute \"{$attr['name']}\" (post-select) requires post_type" );
		}
		if ( 'taxonomy-select' === $type && empty( $attr['taxonomy'] ) ) {
			return new \WP_Error( 'missing_taxonomy', "attribute \"{$attr['name']}\" (taxonomy-select) requires taxonomy" );
		}

		return true;
	}

	/* -------------------------------------------------------------- */
	/* Normalization (fill defaults so consumers can trust the shape)  */
	/* -------------------------------------------------------------- */

	private function normalize( array $d ): array {
		$out = [
			'tag'            => $d['tag'],
			'owner'          => $d['owner'],
			'id'             => $d['owner'] . '/' . $d['tag'],
			'title'          => $d['title'],
			'category'       => $d['category'] ?? ucfirst( $d['owner'] ),
			'description'    => $d['description'] ?? '',
			'icon'           => $d['icon'] ?? 'shortcode',
			'keywords'       => array_values( (array) ( $d['keywords'] ?? [] ) ),
			'preview'        => [
				'mode' => $d['preview']['mode'] ?? 'server',
				'note' => $d['preview']['note'] ?? '',
			],
			'attributes'     => array_map( [ $this, 'normalize_attribute' ], (array) ( $d['attributes'] ?? [] ) ),
			// Optional native-block mapping: the aBlocks (or other) block this
			// shortcode converts to, and how its atts map onto that block's attrs.
			'ablocks_block'  => isset( $d['ablocks_block'] ) ? (string) $d['ablocks_block'] : '',
			'ablocks_map'    => ( isset( $d['ablocks_map'] ) && is_array( $d['ablocks_map'] ) ) ? $d['ablocks_map'] : [],
			'schema_version' => (int) ( $d['schema_version'] ?? self::SCHEMA_VERSION ),
		];

		if ( isset( $d['content'] ) ) {
			$out['content'] = [
				'supported' => (bool) ( $d['content']['supported'] ?? true ),
				'mode'      => $d['content']['mode'] ?? 'plain',
				'label'     => $d['content']['label'] ?? __( 'Content', 'storeengine' ),
			];
		}

		return $out;
	}

	private function normalize_attribute( array $a ): array {
		$type = $a['type'] ?? 'text';
		$out  = [
			'name'        => $a['name'],
			'label'       => $a['label'],
			'type'        => $type,
			'default'     => $a['default'] ?? self::default_for_type( $type ),
			'help'        => $a['help'] ?? '',
			'placeholder' => $a['placeholder'] ?? '',
			'required'    => (bool) ( $a['required'] ?? false ),
			'group'       => $a['group'] ?? __( 'Settings', 'storeengine' ),
			'sanitize'    => $a['sanitize'] ?? self::sanitize_for_type( $type ),
		];

		if ( in_array( $type, self::CHOICE_TYPES, true ) ) {
			$out['options'] = array_values( (array) ( $a['options'] ?? [] ) );
		}
		foreach ( [ 'min', 'max', 'step' ] as $k ) {
			if ( isset( $a[ $k ] ) ) {
				$out[ $k ] = $a[ $k ];
			}
		}
		if ( isset( $a['post_type'] ) ) {
			$out['post_type'] = $a['post_type'];
		}
		if ( isset( $a['taxonomy'] ) ) {
			$out['taxonomy'] = $a['taxonomy'];
		}
		if ( isset( $a['depends_on']['name'] ) ) {
			$out['depends_on'] = [
				'name'  => $a['depends_on']['name'],
				'value' => $a['depends_on']['value'] ?? '',
			];
		}

		return $out;
	}

	private static function default_for_type( string $type ) {
		switch ( $type ) {
			case 'number':
			case 'range':
				return 0;
			case 'toggle':
				return 'false';
			default:
				return '';
		}
	}

	private static function sanitize_for_type( string $type ): string {
		switch ( $type ) {
			case 'number':
			case 'range':
				return 'int';
			case 'color':
				return 'color';
			case 'csv':
				return 'csv';
			case 'post-select':
			case 'taxonomy-select':
				return 'key';
			default:
				return 'text';
		}
	}
}
