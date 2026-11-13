<?php
/**
 * SEO Health Check — on-page SEO-scan.
 *
 * De module is de losse plugin SEO Health Check 0.10.1, overgenomen in
 * modules/seo-health-check/ met dezelfde klassen, tabellen (`seohc_*`), opties
 * en text domain. Deze klasse is de lijm: guard tegen de losse plugin,
 * constanten, boot, URL-helper en de brug naar de PDK-instellingentab
 * (zes sub-tabs via ?seohc_view=). Normatief: docs/seo-health-check-brief.md.
 */

defined( 'ABSPATH' ) || exit;

class PDK_SEO_Health_Check {

	/** Tab-slug binnen de PDK-instellingenpagina. */
	const TAB = 'seo_health_check';

	/** Sub-tabs: view => msgid (text domain seo-health-check; de eerste is de standaard). */
	const VIEWS = [ 'issues', 'pages', 'scans', 'schedule', 'launch', 'settings' ];

	/** True zodra de module zelf SEOHC heeft opgestart. */
	private static bool $booted = false;

	/** True als de losse plugin draait en de module dus niets doet. */
	private static bool $standalone = false;

	public function __construct( PDK_Loader $loader ) {
		$loader->add_action( 'plugins_loaded', $this, 'boot' );
	}

	/**
	 * Draait op plugins_loaded: dan zijn alle (MU-)plugins ingeladen en is
	 * SEOHC_VERSION gezet als de losse plugin actief is. Vóór deze check
	 * definieert de module niets, anders botst hij met de losse plugin.
	 */
	public function boot(): void {
		if ( defined( 'SEOHC_VERSION' ) ) {
			self::$standalone = true;
			return;
		}

		define( 'SEOHC_VERSION',    PDK_PLUGIN_VERSION );
		define( 'SEOHC_DB_VERSION', '4' );
		define( 'SEOHC_FILE',       __FILE__ );
		define( 'SEOHC_DIR',        __DIR__ . '/' );
		define( 'SEOHC_URL',        PDK_PLUGIN_URL . 'modules/seo-health-check/' );

		foreach ( [ 'issue-types', 'installer', 'settings', 'repository', 'seo-meta', 'content-resolver', 'link-checker', 'scanner', 'scan-queue', 'csv-exporter', 'schedule', 'mailer', 'forms', 'launch-checks', 'plugin' ] as $file ) {
			require_once SEOHC_DIR . 'includes/class-seohc-' . $file . '.php';
		}

		self::$booted = true;
		SEOHC_Plugin::instance();
	}

	/** True als de module SEOHC zelf draait (niet als de losse plugin het doet). */
	public static function is_active(): bool {
		return self::$booted;
	}

	/**
	 * Is de losse plugin actief? Elk pad dat eindigt op seo-health-check.php telt,
	 * ongeacht de mapnaam; netwerkbreed en per site.
	 */
	public static function standalone_plugin_active(): bool {
		$plugins = (array) get_option( 'active_plugins', [] );
		if ( is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}

		foreach ( $plugins as $plugin ) {
			if ( 'seo-health-check.php' === $plugin || str_ends_with( (string) $plugin, '/seo-health-check.php' ) ) {
				return true;
			}
		}

		return false;
	}

	/** Een onbekende view valt terug op de eerste (issues). */
	private static function normalize_view( string $view ): string {
		return in_array( $view, self::VIEWS, true ) ? $view : self::VIEWS[0];
	}

	/** De sub-tab die nu open staat (uit de querystring). */
	public static function current_view(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return self::normalize_view( isset( $_GET['seohc_view'] ) ? sanitize_key( wp_unslash( $_GET['seohc_view'] ) ) : '' );
	}

	/** Staat de SEO Health-tab nu open? */
	public static function is_current_tab(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['tab'] ) && self::TAB === sanitize_key( wp_unslash( $_GET['tab'] ) );
	}

	/** URL van een sub-tab; dé plek voor elke link, redirect en form-action van SEOHC. */
	public static function tab_url( string $view = 'issues', array $args = [] ): string {
		return add_query_arg(
			array_merge(
				[ 'page' => PDK_Admin::PAGE_SLUG, 'tab' => self::TAB, 'seohc_view' => self::normalize_view( $view ) ],
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/** Hidden velden voor een GET-formulier dat op dezelfde sub-tab moet blijven. Geëscaped. */
	public static function hidden_fields( string $view ): string {
		$fields = [ 'page' => PDK_Admin::PAGE_SLUG, 'tab' => self::TAB, 'seohc_view' => self::normalize_view( $view ) ];
		$html   = '';
		foreach ( $fields as $name => $value ) {
			$html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}

		return $html;
	}

	// -------------------------------------------------------------------------
	// Kop-helpers voor de MCP-abilities (brief D20, D21). Gericht en minimaal:
	// alleen de tags van één kop (en bij core/heading het level-attribuut)
	// veranderen; de rest van de content blijft byte-gelijk.
	// -------------------------------------------------------------------------

	/**
	 * Builder-meta die aangeeft dat de content niet in post_content staat.
	 * Oxygen 6 schrijft _oxygen_data (vastgesteld op dev.local, pagina 21710).
	 */
	const BUILDER_META = [
		'ct_builder_shortcodes', 'ct_builder_json',    // Oxygen Classic
		'_oxygen_data', '_breakdance_data',            // Oxygen 6 / Breakdance
		'_elementor_edit_mode',                        // Elementor
		'_bricks_page_content_2',                      // Bricks
		'_fl_builder_enabled',                         // Beaver Builder
	];

	/** Tekst van een kop of invoer: tags weg, entities decoderen, witruimte samenvoegen. */
	public static function normalize_text( string $html ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	/**
	 * Alle <h1>-<h6> in raw post_content, in documentvolgorde, met positie.
	 * Koppen in HTML-commentaar tellen niet mee. Een kop is alleen bewerkbaar buiten
	 * blokken of direct in core/heading; elk ander omringend blok (ook core/html) niet.
	 *
	 * @return array[] index, level, text, block, editable, reason, offset, length, comment (voor core/heading).
	 */
	private static function find_headings( string $content ): array {
		$re = '/<!--\s+(\/)?wp:([a-z][a-z0-9_\/-]*)(?:\s+(\{.*?\}))?\s*(\/)?-->|<!--.*?-->|<h([1-6])\b[^>]*>.*?<\/h\5>/is';
		if ( ! preg_match_all( $re, $content, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return [];
		}

		$stack = [];
		$out   = [];
		foreach ( $m as $hit ) {
			$text = $hit[0][0];
			$off  = $hit[0][1];

			if ( isset( $hit[5] ) && '' !== $hit[5][0] ) {
				$top   = $stack ? end( $stack ) : null;
				$block = null !== $top;
				$ok    = ! $block || 'core/heading' === $top['name'];
				$out[] = [
					'index'    => count( $out ),
					'level'    => (int) $hit[5][0],
					'text'     => self::normalize_text( $text ),
					'block'    => $block,
					'editable' => $ok,
					'reason'   => $ok ? null : 'unsupported_block',
					'offset'   => $off,
					'length'   => strlen( $text ),
					'comment'  => $block && $ok ? $top : null,
				];
			} elseif ( isset( $hit[2] ) && '' !== $hit[2][0] ) {
				$name = false === strpos( $hit[2][0], '/' ) ? 'core/' . $hit[2][0] : $hit[2][0];
				if ( '' !== ( $hit[1][0] ?? '' ) ) {
					if ( $stack && end( $stack )['name'] === $name ) {
						array_pop( $stack );
					}
				} elseif ( '' === ( $hit[4][0] ?? '' ) ) {
					$stack[] = [
						'name'   => $name,
						'offset' => $off,
						'length' => strlen( $text ),
						'attrs'  => isset( $hit[3] ) && -1 !== $hit[3][1] ? [ $hit[3][1] - $off, strlen( $hit[3][0] ) ] : null,
					];
				}
			}
		}

		return $out;
	}

	/** Koppen voor de ability seo-get-headings (zonder interne posities). */
	public static function headings( string $content ): array {
		return array_map(
			static fn( array $h ): array => array_diff_key( $h, [ 'offset' => 0, 'length' => 0, 'comment' => 0 ] ),
			self::find_headings( $content )
		);
	}

	/**
	 * Waarom een post geen content-writes toestaat (D20 0, a-e); '' als het mag.
	 * not_scannable | builder_content | rendered_source | unfiltered_html | revisions_disabled
	 */
	public static function editable_reason( WP_Post $post ): string {
		if ( ! SEOHC_Scanner::is_scannable( $post ) ) {
			return 'not_scannable';
		}
		if ( '' === trim( wp_strip_all_tags( $post->post_content ) ) ) {
			return 'builder_content';
		}
		if ( 'rendered' === SEOHC_Settings::get( 'content_source' ) ) {
			return 'rendered_source';
		}
		foreach ( self::BUILDER_META as $key ) {
			if ( metadata_exists( 'post', $post->ID, $key ) ) {
				return 'builder_content';
			}
		}
		if ( 'on' === get_post_meta( $post->ID, '_et_pb_use_builder', true ) ) {
			return 'builder_content';
		}
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return 'unfiltered_html';
		}
		if ( ! wp_revisions_enabled( $post ) ) {
			return 'revisions_disabled';
		}

		return '';
	}

	/**
	 * Zet het level van één kop. Alleen de open- en sluittag veranderen en, bij
	 * core/heading, het attribuut level (level 2 = standaard = weglaten).
	 *
	 * @return string|WP_Error Nieuwe content.
	 */
	public static function set_heading_level( string $content, int $index, string $expected_text, int $level ) {
		$found = self::find_headings( $content );
		if ( ! isset( $found[ $index ] ) ) {
			return new WP_Error( 'pdk_seo_not_found', __( 'Er is geen kop met deze index.', 'pdk-theme-options' ) );
		}

		$h = $found[ $index ];
		if ( ! $h['editable'] ) {
			return new WP_Error( 'pdk_seo_unsupported_block', __( 'Deze kop staat in een blok dat niet via de API te bewerken is (alleen klassieke HTML en core/heading).', 'pdk-theme-options' ) );
		}
		if ( self::normalize_text( $expected_text ) !== $h['text'] ) {
			return new WP_Error( 'pdk_seo_heading_mismatch', __( 'De kop op deze index heeft een andere tekst dan verwacht. Lees de koppen opnieuw.', 'pdk-theme-options' ) );
		}

		$tag = substr( $content, $h['offset'], $h['length'] );
		$tag = (string) preg_replace( '/^<h[1-6]/i', '<h' . $level, $tag, 1 );
		$tag = (string) preg_replace( '/<\/h[1-6]>$/i', '</h' . $level . '>', $tag, 1 );
		$out = substr_replace( $content, $tag, $h['offset'], $h['length'] );

		if ( $h['comment'] ) { // Staat vóór de kop, dus de offset blijft geldig.
			$c       = $h['comment'];
			$comment = substr( $content, $c['offset'], $c['length'] );
			$attrs   = $c['attrs'] ? substr( $comment, $c['attrs'][0], $c['attrs'][1] ) : '';
			$new     = self::level_attrs( $attrs, $level );
			if ( null === $new ) {
				return new WP_Error( 'pdk_seo_unsupported_block', __( 'De blokattributen van deze kop zijn niet te lezen.', 'pdk-theme-options' ) );
			}

			if ( $new !== $attrs ) {
				if ( $c['attrs'] ) {
					$pre  = substr( $comment, 0, $c['attrs'][0] );
					$post = substr( $comment, $c['attrs'][0] + $c['attrs'][1] );
					$comment = '' === $new ? rtrim( $pre ) . ' ' . ltrim( $post ) : $pre . $new . $post;
				} else {
					$comment = (string) preg_replace( '/\s*-->$/', ' ' . $new . ' -->', $comment, 1 );
				}
				$out = substr_replace( $out, $comment, $c['offset'], $c['length'] );
			}
		}

		return $out;
	}

	/**
	 * Nieuwe attributen-JSON van een heading-blok voor een level; '' = geen attributen meer,
	 * null = JSON onleesbaar. Tekstueel, zodat de overige attributen byte-gelijk blijven.
	 * ponytail: gaat uit van het eerste "level" in de JSON als top-level sleutel.
	 */
	private static function level_attrs( string $attrs, int $level ): ?string {
		if ( '' === $attrs ) {
			return 2 === $level ? '' : '{"level":' . $level . '}';
		}
		$data = json_decode( $attrs, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$has = array_key_exists( 'level', $data );

		if ( 2 === $level ) {
			if ( ! $has ) {
				return $attrs;
			}
			$out = (string) preg_replace( '/"level"\s*:\s*\d+\s*,?\s*/', '', $attrs, 1 );
			$out = (string) preg_replace( '/,\s*\}$/', '}', $out );
		} elseif ( $has ) {
			$out = (string) preg_replace( '/"level"\s*:\s*\d+/', '"level":' . $level, $attrs, 1 );
		} else {
			$out = [] === $data ? '{"level":' . $level . '}' : '{"level":' . $level . ',' . ltrim( substr( $attrs, 1 ) );
		}

		$decoded = json_decode( $out, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		return [] === $decoded ? '' : $out;
	}

	/** H1 vooraan in de content (D21: exacte markup; blokken of klassiek). */
	public static function insert_h1( string $content, string $text ): string {
		$text = esc_html( sanitize_text_field( $text ) );

		if ( has_blocks( $content ) ) {
			return '<!-- wp:heading {"level":1} -->' . "\n" . '<h1 class="wp-block-heading">' . $text . '</h1>' . "\n" . '<!-- /wp:heading -->' . "\n\n" . $content;
		}

		return '<h1>' . $text . '</h1>' . "\n" . $content;
	}

	/** Rendert de tab: sub-navigatie plus de bestaande SEOHC-pagina. */
	public static function render_inline(): void {
		if ( self::$standalone || ! self::$booted ) {
			echo '<div class="notice notice-warning inline"><p>'
				. esc_html__( 'De losse plugin SEO Health Check is actief, dus de module doet niets. Deactiveer de losse plugin; de data blijft behouden. Verwijder hem niet via het Plugins-scherm (Verwijderen wist de data): haal de map weg via FTP of de shell (rm -r wp-content/plugins/seo-health-check).', 'pdk-theme-options' )
				. '</p></div>';
			return;
		}

		$view   = self::current_view();
		$labels = [
			'issues'   => __( 'Issues', 'seo-health-check' ),
			'pages'    => __( 'Page scores', 'seo-health-check' ),
			'scans'    => __( 'Scans', 'seo-health-check' ),
			'schedule' => __( 'Automatic scan', 'seo-health-check' ),
			'launch'   => __( 'Launch checks', 'seo-health-check' ),
			'settings' => __( 'Settings', 'seo-health-check' ),
		];

		echo '<p class="shc-subnav" style="display:flex;gap:6px;flex-wrap:wrap;margin:0 0 1em;">';
		foreach ( $labels as $slug => $label ) {
			printf(
				'<a href="%s" class="button%s"%s>%s</a>',
				esc_url( self::tab_url( $slug ) ),
				$slug === $view ? ' button-primary' : '',
				$slug === $view ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</p>';

		$pages = [
			'issues'   => [ 'SEOHC_Admin', 'render_overview' ],
			'pages'    => [ 'SEOHC_Admin', 'render_pages' ],
			'scans'    => [ 'SEOHC_Scans_Page', 'render_page' ],
			'schedule' => [ 'SEOHC_Schedule_Page', 'render_page' ],
			'launch'   => [ 'SEOHC_Launch_Checks', 'render_page' ],
			'settings' => [ 'SEOHC_Settings', 'render_page' ],
		];
		call_user_func( $pages[ $view ] );
	}
}
