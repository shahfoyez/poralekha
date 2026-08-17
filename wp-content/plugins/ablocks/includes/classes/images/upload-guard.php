<?php
namespace ABlocks\Classes\Images;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

/**
 * Hold uploads to a standard: meaningful filenames, and alt text before use.
 *
 * ## What can and cannot be blocked
 *
 * A **filename** can be rejected outright. `wp_handle_upload_prefilter` runs
 * before the file is written, so `IMG_4821.jpg` never reaches the library and
 * the uploader sees why.
 *
 * **Alt text cannot** be required at upload time, however the request is
 * phrased. Alt text is post meta written after the attachment row exists, and
 * the field someone types it into does not render until the upload has already
 * succeeded — there is no moment during the upload at which it could be
 * present. Anything claiming otherwise is either deleting the attachment
 * afterwards (which loses the file someone just waited to upload) or only
 * pretending.
 *
 * What is genuinely enforceable is stopping the image being *used*: the media
 * modal's insert button stays disabled until alt text is filled in, and posts
 * can be blocked from publishing while they contain images that have none. That
 * reaches the same end — nothing ships without alt text — without throwing away
 * the upload.
 */
class UploadGuard {

	/**
	 * Filenames that carry no information about the image.
	 *
	 * These are what cameras, phones and screenshot tools produce. Matched
	 * against the name with its extension and separators removed.
	 */
	const MEANINGLESS = [
		'img',
		'image',
		'images',
		'dsc',
		'dscn',
		'dscf',
		'pxl',
		'pict',
		'photo',
		'foto',
		'picture',
		'pic',
		'untitled',
		'unnamed',
		'noname',
		'screenshot',
		'screen shot',
		'screen capture',
		'capture',
		'download',
		'downloads',
		'file',
		'copy',
		'final',
		'new',
		'temp',
		'tmp',
		'asset',
		'gopr',
		'dji',
		'mvimg',
		'signal',
		'whatsapp image',
		'photo collage',
	];

	/**
	 * Fewest letters a filename must contain to say anything.
	 */
	const MIN_LETTERS = 4;

	public static function init() {
		// Registered in every context, not just admin. These are rules about
		// what may enter the library and what may go live, and content arrives
		// through REST (which is what the block editor uses), WP-CLI, cron and
		// importers as readily as through wp-admin. Gating them on is_admin()
		// would leave the rule enforced only where it happened to be convenient.
		if ( self::require_filename() ) {
			add_filter( 'wp_handle_upload_prefilter', [ __CLASS__, 'check_filename' ] );
		}

		if ( self::require_alt() ) {
			add_filter( 'wp_insert_post_data', [ __CLASS__, 'block_publish_without_alt' ], 10, 2 );

			// The modal gate and its notice are the only admin-only parts.
			if ( is_admin() ) {
				add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_media_guard' ] );
				add_action( 'admin_notices', [ __CLASS__, 'publish_blocked_notice' ] );
			}
		}
	}

	/**
	 * Is the filename rule switched on?
	 *
	 * @return bool
	 */
	public static function require_filename() {
		return (bool) apply_filters(
			'ablocks/images/require_filename',
			(bool) Helper::get_settings( 'perf_image_require_filename', false )
		);
	}

	/**
	 * Is the alt text rule switched on?
	 *
	 * @return bool
	 */
	public static function require_alt() {
		return (bool) apply_filters(
			'ablocks/images/require_alt',
			(bool) Helper::get_settings( 'perf_image_require_alt', false )
		);
	}

	/**
	 * Reject an upload whose filename says nothing about the image.
	 *
	 * @param array $file Upload array from PHP.
	 * @return array
	 */
	public static function check_filename( $file ) {
		if ( empty( $file['name'] ) || ! empty( $file['error'] ) ) {
			return $file;
		}

		// Only images. Blocking a PDF called "invoice-2024.pdf" for being
		// unhelpful would be officious and is not what this is for.
		$type = wp_check_filetype( $file['name'] );
		if ( empty( $type['type'] ) || 0 !== strpos( $type['type'], 'image/' ) ) {
			return $file;
		}

		$reason = self::filename_problem( $file['name'] );
		if ( null === $reason ) {
			return $file;
		}

		$file['error'] = $reason;

		return $file;
	}

	/**
	 * What is wrong with a filename, if anything.
	 *
	 * @param string $filename Original filename.
	 * @return string|null Message for the uploader, or null when acceptable.
	 */
	public static function filename_problem( $filename ) {
		$name = pathinfo( $filename, PATHINFO_FILENAME );
		$name = strtolower( trim( (string) $name ) );

		// Separators become spaces so "IMG_4821" and "img-4821" read alike.
		$readable = trim( preg_replace( '/[\-_.]+/', ' ', $name ) );
		$readable = trim( preg_replace( '/\s+/', ' ', $readable ) );

		if ( '' === $readable ) {
			return self::message( __( 'the file has no name', 'ablocks' ) );
		}

		// Strip trailing counters and dates — "photo 2", "image (3)",
		// "screenshot 2024 05 01" — so the stem is judged rather than whatever
		// the phone or screenshot tool appended. Repeated deliberately: a single
		// pass leaves "screenshot 2024 05", which still reads as a real name.
		$stem = trim( preg_replace( '/(?:[\s(\[]*\d+[\s)\]]*)+$/', '', $readable ) );

		$meaningless = (array) apply_filters( 'ablocks/images/meaningless_filenames', self::MEANINGLESS );
		if ( in_array( $stem, $meaningless, true ) ) {
			/* translators: %s: the filename that was rejected. */
			return self::message( sprintf( __( '"%s" is the name your camera or phone gave it', 'ablocks' ), $filename ) );
		}

		// Stripping trailing numbers is not enough on its own: a macOS
		// screenshot is "Screenshot 2024-05-01 at 10.02.33", where the "at"
		// leaves a word behind and the name looks legitimate. So judge what
		// words actually remain once numbers and connecting words are set
		// aside — if all that is left is "screenshot", the name says nothing.
		$filler    = (array) apply_filters( 'ablocks/images/filler_words', [ 'at', 'on', 'of', 'am', 'pm', 'copy', 'the', 'a', 'v', 'ver' ] );
		$remaining = [];
		foreach ( explode( ' ', $readable ) as $word ) {
			$word = trim( $word );
			if ( '' === $word || is_numeric( $word ) || in_array( $word, $filler, true ) ) {
				continue;
			}
			// Mixed tokens like "20240501" or "img4821" reduce to their letters.
			$letters_only = preg_replace( '/[^a-z]/', '', $word );
			if ( '' === $letters_only ) {
				continue;
			}
			$remaining[] = $letters_only;
		}

		// Checked both ways: every word individually ("img", "photo") and the
		// words rejoined ("screen shot"), because some of these names are only
		// meaningless as a phrase — "screen" alone is fine in
		// "screen-printing-process.jpg".
		$rejoined = implode( ' ', $remaining );
		if (
			! empty( $remaining ) &&
			( 0 === count( array_diff( $remaining, $meaningless ) ) || in_array( $rejoined, $meaningless, true ) )
		) {
			/* translators: %s: the filename that was rejected. */
			return self::message( sprintf( __( '"%s" is the name your camera or phone gave it', 'ablocks' ), $filename ) );
		}

		// Nothing but digits and punctuation.
		if ( ! preg_match( '/[a-z]/', $readable ) ) {
			return self::message( __( 'the name is only numbers', 'ablocks' ) );
		}

		$letters = preg_match_all( '/[a-z]/', $readable );
		$minimum = (int) apply_filters( 'ablocks/images/min_filename_letters', self::MIN_LETTERS );
		if ( $letters < $minimum ) {
			return self::message( __( 'the name is too short to describe anything', 'ablocks' ) );
		}

		return null;
	}

	/**
	 * Build the rejection message.
	 *
	 * Says what is wrong, what to do, and gives an example — a bare "invalid
	 * filename" leaves someone renaming at random until it lets them through.
	 *
	 * @param string $reason Short reason.
	 * @return string
	 */
	private static function message( $reason ) {
		return sprintf(
			/* translators: %s: short explanation of what is wrong with the filename. */
			__( 'This image was not uploaded because %s. Rename the file to describe what it shows — for example "red-running-shoes-side-view.jpg" instead of "IMG_4821.jpg". Descriptive filenames help search engines and anyone using a screen reader.', 'ablocks' ),
			$reason
		);
	}

	/**
	 * Load the media-library gate.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue_media_guard( $hook ) {
		// Loaded wherever the media modal can be opened. did_action() catches
		// anything that has already called wp_enqueue_media(); the list covers
		// the screens that call it later than this hook runs — including the
		// site editor, which is where images get inserted on a block theme.
		$screens = (array) apply_filters(
			'ablocks/images/alt_guard_screens',
			[ 'post.php', 'post-new.php', 'upload.php', 'site-editor.php', 'widgets.php', 'customize.php' ]
		);

		if ( ! did_action( 'wp_enqueue_media' ) && ! in_array( $hook, $screens, true ) ) {
			return;
		}

		wp_enqueue_script(
			'ablocks-media-alt-guard',
			ABLOCKS_ASSETS_URL . 'js/media-alt-guard.js',
			[ 'media-views' ],
			ABLOCKS_VERSION,
			true
		);

		wp_localize_script(
			'ablocks-media-alt-guard',
			'aBlocksAltGuard',
			[
				'message' => __( 'Add alt text before using this image.', 'ablocks' ),
				'hint'    => __( 'Describe what the image shows. Leave it empty only if the image is purely decorative.', 'ablocks' ),
			]
		);
	}

	/**
	 * Refuse to publish a post that uses images with no alt text.
	 *
	 * This is the part that actually enforces the rule. The media modal gate
	 * covers the normal path, but it is client side and only guards the modal —
	 * a block pasted in, an imported page or a REST call would sail past it.
	 * Checking at save time is the backstop that cannot be walked around.
	 *
	 * The post is demoted to draft rather than rejected, so the writing is never
	 * lost; a notice explains why.
	 *
	 * @param array $data    Sanitised post data.
	 * @param array $postarr Raw post data.
	 * @return array
	 */
	public static function block_publish_without_alt( $data, $postarr ) {
		if ( 'publish' !== $data['post_status'] ) {
			return $data;
		}
		if ( ! empty( $data['post_type'] ) && ! is_post_type_viewable( $data['post_type'] ) ) {
			return $data;
		}
		if ( wp_is_post_revision( $postarr['ID'] ?? 0 ) || wp_is_post_autosave( $postarr['ID'] ?? 0 ) ) {
			return $data;
		}

		$missing = self::images_without_alt( (string) $data['post_content'] );
		if ( empty( $missing ) ) {
			return $data;
		}

		$data['post_status'] = 'draft';

		set_transient(
			'ablocks_alt_block_' . get_current_user_id(),
			$missing,
			60
		);

		return $data;
	}

	/**
	 * Attachment ids referenced in content that have no alt text.
	 *
	 * @param string $content Post content.
	 * @return int[]
	 */
	public static function images_without_alt( $content ) {
		if ( false === strpos( $content, 'wp-image-' ) ) {
			return [];
		}

		if ( ! preg_match_all( '/wp-image-(\d+)/', $content, $matches ) ) {
			return [];
		}

		$missing = [];
		foreach ( array_unique( $matches[1] ) as $id ) {
			$id = (int) $id;
			if ( ! $id ) {
				continue;
			}
			$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( '' === trim( (string) $alt ) ) {
				$missing[] = $id;
			}
		}

		return $missing;
	}

	/**
	 * Explain a blocked publish.
	 */
	public static function publish_blocked_notice() {
		$key     = 'ablocks_alt_block_' . get_current_user_id();
		$missing = get_transient( $key );

		if ( empty( $missing ) || ! is_array( $missing ) ) {
			return;
		}
		delete_transient( $key );

		$links = [];
		foreach ( array_slice( $missing, 0, 10 ) as $id ) {
			$title  = get_the_title( $id );
			$edit   = get_edit_post_link( $id );
			$label  = $title ? $title : '#' . $id;
			$links[] = $edit
				? '<a href="' . esc_url( $edit ) . '">' . esc_html( $label ) . '</a>'
				: esc_html( $label );
		}

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( 'Saved as a draft: some images have no alt text.', 'ablocks' );
		echo '</strong></p><p>';
		esc_html_e( 'Your site requires every image to describe itself before a post goes live. Add alt text to the images below, then publish again.', 'ablocks' );
		echo '</p><p>' . wp_kses_post( implode( ', ', $links ) ) . '</p></div>';
	}
}
