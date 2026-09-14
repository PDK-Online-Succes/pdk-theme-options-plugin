<?php
/**
 * Frontend markup transformation.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps eligible img elements in a picture element.
 *
 * The original img is never reconstructed: it is concatenated verbatim, so every
 * attribute a theme, plugin or page builder added to it survives untouched.
 */
class Picture_Renderer {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Capability detection.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Cached list of globally enabled formats.
	 *
	 * @var string[]|null
	 */
	private $formats = null;

	/**
	 * Cached host of the uploads base URL.
	 *
	 * @var string|null
	 */
	private $uploads_host = null;

	/**
	 * Cached path prefix of the uploads base URL.
	 *
	 * @var string|null
	 */
	private $uploads_prefix = null;

	/**
	 * Per-request cache of uploads-relative path to attachment ID. Zero means "looked up,
	 * not an attachment", so a miss is never queried twice.
	 *
	 * @var array<string, int>
	 */
	private static $path_cache = array();

	/**
	 * Constructor.
	 *
	 * @param Settings     $settings     Settings.
	 * @param Capabilities $capabilities Capability detection.
	 */
	public function __construct( Settings $settings, Capabilities $capabilities ) {
		$this->settings     = $settings;
		$this->capabilities = $capabilities;
	}

	/**
	 * Drops the cached format list and URL lookups. Used by the test suite.
	 *
	 * @return void
	 */
	public function reset() {
		$this->formats        = null;
		$this->uploads_host   = null;
		$this->uploads_prefix = null;
		self::$path_cache     = array();
	}

	/**
	 * Registers the rendering hooks.
	 *
	 * the_content runs at priority 20, after core's wp_filter_content_tags() at 12, so
	 * srcset, sizes, loading, decoding and fetchpriority are already final.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'wp_get_attachment_image', array( $this, 'filter_attachment_image' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_filter( 'widget_text_content', array( $this, 'filter_content' ), 20 );
		add_filter( 'widget_block_content', array( $this, 'filter_content' ), 20 );

		// Terugval voor paginabuilders zonder eigen koppeling. Oxygen, Breakdance
		// en Oxygen Classic hebben die wel, zie de Oxygen-klasse; die hoeven dit
		// niet aan te hebben staan. Prioriteit 1 zodat de buffer om alle output
		// heen valt.
		if ( $this->settings->get( 'whole_page' ) ) {
			add_action( 'template_redirect', array( $this, 'start_buffer' ), 1 );
		}
	}

	/**
	 * Vangt de volledige pagina-output op, zodat ook markup buiten the_content
	 * langs de transformatie komt.
	 *
	 * @return void
	 */
	public function start_buffer() {
		if ( ! $this->is_active() ) {
			return;
		}

		if ( is_feed() || is_embed() || is_robots() || wp_is_json_request() ) {
			return;
		}

		ob_start( array( $this, 'filter_page' ) );
	}

	/**
	 * Transformeert de opgevangen pagina. Wordt door PHP aangeroepen bij het
	 * leegmaken van de buffer; geeft bij twijfel de invoer onveranderd terug.
	 *
	 * @param string $buffer Volledige pagina-output.
	 * @return string
	 */
	public function filter_page( $buffer ) {
		if ( ! is_string( $buffer ) || '' === $buffer || ! $this->is_active() ) {
			return $buffer;
		}

		if ( false === stripos( $buffer, '<img' ) ) {
			return $buffer;
		}

		// Alleen complete HTML-documenten: een JSON- of XML-antwoord dat toevallig
		// het woord img bevat blijft zo ongemoeid.
		if ( false === stripos( $buffer, '</html>' ) ) {
			return $buffer;
		}

		return $this->transform( $buffer, 'page' );
	}

	/**
	 * Wraps the output of wp_get_attachment_image().
	 *
	 * @param string $html          Image HTML.
	 * @param int    $attachment_id Attachment ID.
	 * @return string
	 */
	public function filter_attachment_image( $html, $attachment_id ) {
		if ( ! is_string( $html ) || '' === $html || ! $this->is_active() ) {
			return $html;
		}

		return $this->wrap_img( $html, (int) $attachment_id, 'attachment_image' );
	}

	/**
	 * Wraps eligible images inside a block of HTML.
	 *
	 * @param string $content HTML.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( ! is_string( $content ) || '' === $content || ! $this->is_active() ) {
			return $content;
		}

		if ( false === stripos( $content, '<img' ) ) {
			return $content;
		}

		return $this->transform( $content, 'content' );
	}

	/**
	 * Finds img tags belonging to a Media Library attachment and wraps them.
	 *
	 * An attachment ID comes from the wp-image-<id> class when WordPress put one there.
	 * Page builders such as Oxygen, Breakdance and Bricks render their own markup without
	 * that class, so as a fallback the src is resolved back to an attachment. Both routes
	 * end in the same validation: a variant is only ever emitted for a filename that the
	 * resolved attachment's own registry lists, so a wrong guess produces no markup change.
	 *
	 * Images already inside a picture element are skipped so nesting can never occur.
	 * Replacements are applied back to front so earlier offsets stay valid.
	 *
	 * @param string $content HTML.
	 * @param string $context Context label passed to the filters.
	 * @return string
	 */
	public function transform( $content, $context = 'content' ) {
		$protected = $this->picture_ranges( $content );

		$found = preg_match_all(
			'#<img\s[^>]*?>#is',
			$content,
			$matches,
			PREG_OFFSET_CAPTURE | PREG_SET_ORDER
		);

		if ( ! $found ) {
			return $content;
		}

		// First pass: pick up the IDs we already know and collect the URLs we do not.
		$ids       = array();
		$unresolved = array();

		foreach ( $matches as $index => $match ) {
			if ( $this->is_protected( $match[0][1], $protected ) ) {
				continue;
			}

			$tag = $match[0][0];

			if ( preg_match( '#wp-image-(\d+)#i', $tag, $class_match ) ) {
				$ids[ $index ] = (int) $class_match[1];
				continue;
			}

			$src = $this->attribute( $tag, 'src' );

			if ( '' !== $src ) {
				$unresolved[ $index ] = $src;
			}
		}

		if ( $unresolved ) {
			$ids += $this->resolve_attachment_ids( $unresolved );
		}

		if ( ! $ids ) {
			return $content;
		}

		// Second pass: splice back to front so the offsets stay valid.
		for ( $i = count( $matches ) - 1; $i >= 0; $i-- ) {
			if ( empty( $ids[ $i ] ) ) {
				continue;
			}

			$tag         = $matches[ $i ][0][0];
			$replacement = $this->wrap_img( $tag, $ids[ $i ], $context );

			if ( $replacement !== $tag ) {
				$content = substr_replace( $content, $replacement, $matches[ $i ][0][1], strlen( $tag ) );
			}
		}

		return $content;
	}

	/**
	 * Resolves img src URLs back to attachment IDs in one query.
	 *
	 * Used only for images that carry no wp-image-<id> class, which in practice means
	 * page builder output. Batched deliberately: an Oxygen page with thirty images must
	 * not cost thirty queries.
	 *
	 * @param array<int, string> $urls Match index to src attribute value.
	 * @return array<int, int> Match index to attachment ID.
	 */
	private function resolve_attachment_ids( array $urls ) {
		$by_path = array();

		foreach ( $urls as $index => $url ) {
			foreach ( $this->candidate_paths( $url ) as $path ) {
				$by_path[ $path ][] = $index;
			}
		}

		if ( ! $by_path ) {
			return array();
		}

		$wanted  = array_keys( $by_path );
		$unknown = array_values( array_diff( $wanted, array_keys( self::$path_cache ) ) );

		if ( $unknown ) {
			self::$path_cache += array_fill_keys( $unknown, 0 );
			self::$path_cache  = $this->lookup_attached_files( $unknown ) + self::$path_cache;
		}

		$resolved = array();

		foreach ( $wanted as $path ) {
			if ( empty( self::$path_cache[ $path ] ) ) {
				continue;
			}

			foreach ( $by_path[ $path ] as $index ) {
				if ( ! isset( $resolved[ $index ] ) ) {
					$resolved[ $index ] = self::$path_cache[ $path ];
				}
			}
		}

		return $resolved;
	}

	/**
	 * Turns an image URL into the uploads-relative paths that could identify it.
	 *
	 * Returns the path as written plus, for a sub-size URL, the full-size path that
	 * _wp_attached_file actually stores. Foreign hosts are rejected outright: without
	 * that check an image on someone else's domain could resolve to a local attachment
	 * and produce source URLs that 404 on that domain.
	 *
	 * @param string $url Raw src attribute value.
	 * @return string[]
	 */
	private function candidate_paths( $url ) {
		$url = trim( wp_specialchars_decode( (string) $url, ENT_QUOTES ) );

		if ( '' === $url || preg_match( '#^(data|blob):#i', $url ) ) {
			return array();
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( $host && strtolower( $host ) !== $this->uploads_host() ) {
			return array();
		}

		$prefix = $this->uploads_path_prefix();

		if ( '' === $prefix ) {
			return array();
		}

		$path = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$at   = strpos( $path, $prefix );

		if ( false === $at ) {
			return array();
		}

		$relative = ltrim( substr( $path, $at + strlen( $prefix ) ), '/' );

		if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
			return array();
		}

		$paths     = array( $relative );
		$full_size = preg_replace( '#-\d+x\d+(?=\.[A-Za-z0-9]+$)#', '', $relative );

		if ( is_string( $full_size ) && $full_size !== $relative ) {
			$paths[] = $full_size;
		}

		return $paths;
	}

	/**
	 * Looks up _wp_attached_file values in a single query.
	 *
	 * @param string[] $paths Uploads-relative paths.
	 * @return array<string, int> Path to attachment ID.
	 */
	private function lookup_attached_files( array $paths ) {
		global $wpdb;

		$paths = array_slice( array_values( array_unique( $paths ) ), 0, 200 );

		if ( ! $paths ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $paths ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated, values are prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value IN ( {$placeholders} )",
				$paths
			)
		);
		// phpcs:enable

		$map = array();

		foreach ( (array) $rows as $row ) {
			$map[ (string) $row->meta_value ] = (int) $row->post_id;
		}

		return $map;
	}

	/**
	 * Host of the uploads base URL, lowercased.
	 *
	 * @return string
	 */
	private function uploads_host() {
		if ( null === $this->uploads_host ) {
			$uploads            = wp_get_upload_dir();
			$host               = wp_parse_url( (string) $uploads['baseurl'], PHP_URL_HOST );
			$this->uploads_host = $host ? strtolower( $host ) : '';
		}

		return $this->uploads_host;
	}

	/**
	 * Path component of the uploads base URL, with a trailing slash.
	 *
	 * @return string
	 */
	private function uploads_path_prefix() {
		if ( null === $this->uploads_prefix ) {
			$uploads = wp_get_upload_dir();
			$path    = (string) wp_parse_url( (string) $uploads['baseurl'], PHP_URL_PATH );

			$this->uploads_prefix = ( '' === $path ) ? '' : trailingslashit( $path );
		}

		return $this->uploads_prefix;
	}

	/**
	 * Wraps a single img element.
	 *
	 * Public so themes and page builders that render their own markup can reuse it:
	 * imgx()->renderer()->wrap_img( $html, $attachment_id, 'my-builder' ).
	 *
	 * @param string $img_html      The complete img element.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $context       Context label.
	 * @return string The picture element, or the untouched input.
	 */
	public function wrap_img( $img_html, $attachment_id, $context = 'custom' ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id < 1 || false === stripos( $img_html, '<img' ) ) {
			return $img_html;
		}

		/**
		 * Filters whether one specific image may be wrapped.
		 *
		 * @param bool   $should        Whether to wrap. Default true.
		 * @param int    $attachment_id Attachment ID.
		 * @param string $context       Context label.
		 */
		if ( ! apply_filters( 'imgx_should_render_picture', true, $attachment_id, $context ) ) {
			return $img_html;
		}

		$map = Variants::lookup_map( $attachment_id );

		if ( ! $map ) {
			return $img_html;
		}

		$formats = $this->formats_for( $attachment_id, $context );

		if ( ! $formats ) {
			return $img_html;
		}

		$srcset = $this->attribute( $img_html, 'srcset' );
		$src    = $this->attribute( $img_html, 'src' );
		$sizes  = $this->attribute( $img_html, 'sizes' );

		$candidates = $this->parse_srcset( '' !== $srcset ? $srcset : $src );

		if ( ! $candidates ) {
			return $img_html;
		}

		$verify  = (bool) apply_filters( 'imgx_verify_variant_files', false );
		$dir     = $verify ? $this->attachment_dir( $attachment_id ) : '';
		$sources = '';

		foreach ( $formats as $format ) {
			$variant_srcset = $this->build_srcset( $candidates, $map, $format, $dir );

			if ( '' === $variant_srcset ) {
				continue;
			}

			$sources .= '<source type="' . esc_attr( Files::MIME_TYPES[ $format ] ) . '"'
				. ' srcset="' . $variant_srcset . '"';

			if ( '' !== $sizes ) {
				$sources .= ' sizes="' . esc_attr( wp_specialchars_decode( $sizes, ENT_QUOTES ) ) . '"';
			}

			$sources .= ' />';
		}

		if ( '' === $sources ) {
			return $img_html;
		}

		$classes = $this->picture_classes( $img_html, $attachment_id, $context );
		$html    = '<picture'
			. ( '' !== $classes ? ' class="' . esc_attr( $classes ) . '"' : '' )
			. '>' . $sources . $img_html . '</picture>';

		/**
		 * Filters the final picture markup.
		 *
		 * @param string $html          Generated markup.
		 * @param string $img_html      The original, unmodified img element.
		 * @param int    $attachment_id Attachment ID.
		 * @param string $context       Context label.
		 */
		return (string) apply_filters( 'imgx_picture_html', $html, $img_html, $attachment_id, $context );
	}

	/**
	 * Builds the class attribute for the picture element.
	 *
	 * The image's own classes are carried over, because wrapping moves the `<img>` one
	 * level down: a page builder or theme that styles or lays out the image through a
	 * class — Oxygen's `.ct-image` and its per-element `.oxy-image-2-100`, a grid or flex
	 * child, an alignment class — would otherwise be styling an element that no longer
	 * sits where the CSS expects it.
	 *
	 * The `id` attribute is deliberately not copied: two elements sharing an id is invalid
	 * HTML and would break `getElementById` and `#id` selectors.
	 *
	 * Note that a class carrying box properties (margin, padding, border) now applies to
	 * both the wrapper and the image, and those add up. Use `imgx_picture_class` to drop
	 * such a class from the wrapper.
	 *
	 * @param string $img_html      The original img element.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $context       Context label.
	 * @return string Space separated class list, unescaped.
	 */
	private function picture_classes( $img_html, $attachment_id, $context ) {
		$classes = array( 'imgx-picture' );

		$attribute = trim( wp_specialchars_decode( $this->attribute( $img_html, 'class' ), ENT_QUOTES ) );

		if ( '' !== $attribute ) {
			foreach ( preg_split( '/\s+/', $attribute ) as $class ) {
				if ( '' !== $class ) {
					$classes[] = $class;
				}
			}
		}

		$classes = array_values( array_unique( $classes ) );

		/**
		 * Filters the classes placed on the generated picture element.
		 *
		 * Defaults to `imgx-picture` plus every class the image itself carries. Return a
		 * smaller list to keep a class off the wrapper, for example one that sets margins:
		 *
		 *     add_filter( 'imgx_picture_class', function ( $classes ) {
		 *         return array_values( array_diff( $classes, array( 'my-spaced-image' ) ) );
		 *     } );
		 *
		 * @param string[] $classes       Class names.
		 * @param string   $img_html      The original img element.
		 * @param int      $attachment_id Attachment ID.
		 * @param string   $context       Context label.
		 */
		$classes = apply_filters( 'imgx_picture_class', $classes, $img_html, $attachment_id, $context );

		$classes = array_filter( array_map( 'trim', array_map( 'strval', (array) $classes ) ) );

		return implode( ' ', $classes );
	}

	/**
	 * Builds a srcset attribute value for one format.
	 *
	 * Candidates without a recorded variant are dropped rather than guessed, so a source
	 * can never point at a file that does not exist.
	 *
	 * @param array  $candidates Parsed candidates from the original srcset.
	 * @param array  $map        Source basename to variant basenames.
	 * @param string $format     Format key.
	 * @param string $dir        Attachment directory when file verification is enabled.
	 * @return string Escaped attribute value, or an empty string.
	 */
	private function build_srcset( array $candidates, array $map, $format, $dir = '' ) {
		$parts = array();

		foreach ( $candidates as $candidate ) {
			$basename = Files::basename_from_url( $candidate['url'] );

			if ( '' === $basename || empty( $map[ $basename ][ $format ] ) ) {
				continue;
			}

			$variant = $map[ $basename ][ $format ];

			if ( '' !== $dir && ! file_exists( $dir . '/' . $variant ) ) {
				continue;
			}

			$url = Files::swap_basename_in_url( $candidate['url'], $variant );

			$parts[] = esc_url( $url ) . ( '' !== $candidate['descriptor'] ? ' ' . $candidate['descriptor'] : '' );
		}

		if ( ! $parts ) {
			return '';
		}

		return implode( ', ', $parts );
	}

	/**
	 * Splits a srcset attribute into url/descriptor pairs.
	 *
	 * @param string $srcset Raw attribute value.
	 * @return array<int, array{url:string,descriptor:string}>
	 */
	private function parse_srcset( $srcset ) {
		$srcset = trim( wp_specialchars_decode( (string) $srcset, ENT_QUOTES ) );

		if ( '' === $srcset ) {
			return array();
		}

		$candidates = array();

		foreach ( explode( ',', $srcset ) as $part ) {
			$part = trim( $part );

			if ( '' === $part ) {
				continue;
			}

			$pieces = preg_split( '/\s+/', $part, 2 );
			$url    = isset( $pieces[0] ) ? trim( $pieces[0] ) : '';

			if ( '' === $url ) {
				continue;
			}

			$descriptor = isset( $pieces[1] ) ? trim( $pieces[1] ) : '';

			// A descriptor must be a width or a pixel density; anything else is malformed.
			if ( '' !== $descriptor && ! preg_match( '/^\d+(\.\d+)?[wx]$/', $descriptor ) ) {
				return array();
			}

			$candidates[] = array(
				'url'        => $url,
				'descriptor' => $descriptor,
			);
		}

		return $candidates;
	}

	/**
	 * Reads a single attribute value from an img tag.
	 *
	 * @param string $html      The img element.
	 * @param string $attribute Attribute name.
	 * @return string Raw (still HTML-escaped) value.
	 */
	private function attribute( $html, $attribute ) {
		$pattern = '#\s' . preg_quote( $attribute, '#' ) . '\s*=\s*(["\'])(.*?)\1#is';

		if ( preg_match( $pattern, $html, $match ) ) {
			return $match[2];
		}

		return '';
	}

	/**
	 * Locates every picture element in a string.
	 *
	 * @param string $content HTML.
	 * @return array<int, array{0:int,1:int}> Start and end offsets.
	 */
	private function picture_ranges( $content ) {
		if ( false === stripos( $content, '<picture' ) ) {
			return array();
		}

		$ranges = array();

		if ( preg_match_all( '#<picture\b[^>]*>.*?</picture\s*>#is', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$ranges[] = array( $match[1], $match[1] + strlen( $match[0] ) );
			}
		}

		return $ranges;
	}

	/**
	 * Whether an offset falls inside one of the protected ranges.
	 *
	 * @param int   $offset Offset.
	 * @param array $ranges Ranges.
	 * @return bool
	 */
	private function is_protected( $offset, array $ranges ) {
		foreach ( $ranges as $range ) {
			if ( $offset >= $range[0] && $offset < $range[1] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Absolute directory holding an attachment's files.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private function attachment_dir( $attachment_id ) {
		$file = get_attached_file( (int) $attachment_id );

		return $file ? Files::normalize( dirname( $file ) ) : '';
	}

	/**
	 * Formats to offer for one attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $context       Context label.
	 * @return string[]
	 */
	private function formats_for( $attachment_id, $context ) {
		if ( null === $this->formats ) {
			$this->formats = $this->settings->enabled_formats( $this->capabilities );
		}

		/** This filter is documented in includes/class-generator.php */
		$formats = apply_filters( 'imgx_formats', $this->formats, (int) $attachment_id, 'render' );

		unset( $context );

		return array_values(
			array_intersect( array( 'avif', 'webp' ), array_map( 'strval', (array) $formats ) )
		);
	}

	/**
	 * Whether markup transformation may run in this request at all.
	 *
	 * Deliberately not memoised: conditional tags such as is_feed() only become reliable
	 * after the main query has run, and a decision cached before that would be wrong for
	 * the rest of the request. Every check below is a handful of function calls.
	 *
	 * @return bool
	 */
	public function is_active() {
		if ( ! $this->settings->get( 'picture_enabled' ) ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( did_action( 'wp' ) && ( is_feed() || is_embed() ) ) {
			return false;
		}

		if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
			return false;
		}

		if ( ! $this->settings->enabled_formats( $this->capabilities ) ) {
			return false;
		}

		/**
		 * Filters whether IMGX may replace img elements in this request.
		 *
		 * @param bool   $enabled       Whether replacement is enabled.
		 * @param int    $attachment_id Always 0 at request level.
		 * @param string $context       Always request at this level.
		 */
		return (bool) apply_filters( 'imgx_enable_picture', true, 0, 'request' );
	}
}
