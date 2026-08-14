<?php
/**
 * Module: Custom Fonts
 *
 * Scant wp-content/uploads/fonts/ recursief op fontbestanden en genereert
 * een @font-face CSS-blok. CSS wordt als bestand gecached (pdk-custom-fonts.css)
 * of inline uitgeschreven, afhankelijk van de instelling.
 *
 * Bestandsnaamconventie: FamilyName-Weight.woff2
 * Voorbeelden: Raleway-Bold.woff2, Montserrat-LightItalic.ttf
 *
 * Ondersteunde formaten (in volgorde van voorkeur): woff2, woff, ttf, otf.
 * Gewicht en stijl worden automatisch uit de bestandsnaam afgeleid.
 * Variable fonts worden herkend via 'VariableFont' of '[wght]' in de bestandsnaam.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Custom_Fonts {

	/** Volgorde bepaalt prioriteit in de src:-url-lijst. */
	public const FORMATS = [ 'woff2', 'woff', 'ttf', 'otf' ];

	/** Bestandsnaam van het gegenereerde CSS-cachebestand in de fontmap. */
	private const CSS_FILE = 'pdk-custom-fonts.css';

	public function __construct( PDK_Loader $loader ) {
		$loader->add_action( 'wp_head',    $this, 'output_font_css', 5 );
		$loader->add_action( 'admin_head', $this, 'output_font_css', 5 );

		// Gutenberg: fonts beschikbaar maken in de blok-editor.
		add_filter( 'wp_theme_json_data_theme', [ $this, 'theme_json_inject_fonts' ] );
	}

	// -------------------------------------------------------------------------
	// Frontend / admin <head> output
	// -------------------------------------------------------------------------

	public function output_font_css(): void {
		$fonts   = self::collect_fonts_grouped();
		$display = PDK_Settings::get_with_default( 'custom_fonts', 'display' ) ?: 'swap';

		if ( empty( $fonts ) ) {
			return;
		}

		$css_output = PDK_Settings::get_with_default( 'custom_fonts', 'css_output' ) ?: 'inline';

		if ( 'file' === $css_output ) {
			$css_url = self::get_or_generate_css_file( $fonts, $display );
			if ( $css_url ) {
				printf( '<link id="pdk-custom-fonts" rel="stylesheet" href="%s">' . "\n", esc_url( $css_url ) );
				return;
			}
		}

		// Fallback / inline CSS.
		echo '<style id="pdk-custom-fonts">' . "\n";
		echo self::build_all_font_faces( $fonts, $display ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</style>' . "\n";
	}

	// -------------------------------------------------------------------------
	// Gutenberg theme.json injectie
	// -------------------------------------------------------------------------

	public function theme_json_inject_fonts( WP_Theme_JSON_Data $theme_json ): WP_Theme_JSON_Data {
		$families = self::get_font_families();
		if ( empty( $families ) ) {
			return $theme_json;
		}

		$font_families = [];
		foreach ( $families as $name ) {
			$slug            = preg_replace( '/[^a-z0-9\-_]/', '-', strtolower( $name ) );
			$font_families[] = [
				'fontFamily' => '"' . $name . '"',
				'slug'       => $slug,
				'name'       => $name,
			];
		}

		return $theme_json->update_with( [
			'version'  => 2,
			'settings' => [
				'typography' => [
					'fontFamilies' => $font_families,
				],
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Statische hulpmethoden — ook bruikbaar vanuit de admin zonder instantie.
	// -------------------------------------------------------------------------

	public static function font_dir(): string {
		$info = wp_get_upload_dir();
		return $info['basedir'] . '/fonts/';
	}

	public static function font_url(): string {
		$info = wp_get_upload_dir();
		return $info['baseurl'] . '/fonts/';
	}

	/** @return string[] Ondersteunde bestandsextensies. */
	public static function allowed_extensions(): array {
		return self::FORMATS;
	}

	/**
	 * Geeft een platte lijst met alle geïnstalleerde fontfamilienamen.
	 *
	 * @return string[]
	 */
	public static function get_font_families(): array {
		return array_keys( self::collect_fonts_grouped() );
	}

	/**
	 * Scant de fontmap recursief en groepeert bestanden per family/weight/style/format.
	 * Interne structuur (voor CSS-generatie):
	 *   $fonts[family][weight/style][format] = url
	 *
	 * @return array<string, array<string, array<string, string>>>
	 */
	public static function collect_fonts_grouped(): array {
		$dir = self::font_dir();

		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$url   = self::font_url();
		$fonts = [];

		$di  = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::UNIX_PATHS );
		$rii = new RecursiveIteratorIterator( $di );

		/** @var SplFileInfo $file */
		foreach ( $rii as $file ) {
			$ext = strtolower( $file->getExtension() );
			if ( ! in_array( $ext, self::FORMATS, true ) ) {
				continue;
			}

			$basename = $file->getBasename( '.' . $file->getExtension() );
			$details  = self::parse_font_name( $basename );
			$family   = $details['family'];
			$weight   = $details['weight'];
			$style    = $details['style'];

			// Relatief pad t.o.v. fontmap + URL-encoding van elk segment.
			$rel_dir = str_replace( $dir, '', $file->getPath() . '/' );
			$encoded = implode( '/', array_map( 'rawurlencode', array_filter( explode( '/', trim( $rel_dir, '/' ) ) ) ) );
			$file_url = $url . ( $encoded ? $encoded . '/' : '' ) . rawurlencode( $file->getFilename() );

			$key = $weight . '/' . $style;
			$fonts[ $family ][ $key ][ $ext ] = $file_url;
		}

		ksort( $fonts, SORT_NATURAL | SORT_FLAG_CASE );
		return $fonts;
	}

	/**
	 * Scant de fontmap en geeft per family een lijst van varianten terug.
	 * Formaat is compatibel met wat de admin-tab verwacht.
	 *
	 * @return array<string, list<array{weight:string,style:string,src:string,format:string,file:string,size:int,basename:string}>>
	 */
	public static function scan_fonts(): array {
		$dir = self::font_dir();
		$url = self::font_url();

		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$fonts = [];

		$di  = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::UNIX_PATHS );
		$rii = new RecursiveIteratorIterator( $di );

		/** @var SplFileInfo $file */
		foreach ( $rii as $file ) {
			$ext = strtolower( $file->getExtension() );
			if ( ! in_array( $ext, self::FORMATS, true ) ) {
				continue;
			}

			$basename = $file->getBasename( '.' . $file->getExtension() );
			$details  = self::parse_font_name( $basename );
			$family   = $details['family'];
			$weight   = $details['weight'];
			$style    = $details['style'];

			$rel_dir  = str_replace( $dir, '', $file->getPath() . '/' );
			$encoded  = implode( '/', array_map( 'rawurlencode', array_filter( explode( '/', trim( $rel_dir, '/' ) ) ) ) );
			$file_url = $url . ( $encoded ? $encoded . '/' : '' ) . rawurlencode( $file->getFilename() );
			$src      = $file_url . '?v=' . $file->getMTime();

			$fonts[ $family ][] = [
				'weight'   => $weight,
				'style'    => $style,
				'src'      => $src,
				'format'   => self::get_format( $ext ),
				'file'     => $file->getFilename(),
				'size'     => (int) $file->getSize(),
				'basename' => $basename,
			];
		}

		ksort( $fonts, SORT_NATURAL | SORT_FLAG_CASE );
		return $fonts;
	}

	/**
	 * Leidt family, weight en style af uit bestandsnaam.
	 * Conventie: FamilyName-Bold.woff2, FamilyName-LightItalic.ttf, Raleway[wght].woff2
	 *
	 * Gewicht wordt herkend via regex met woordgrenzen, waardoor namen als
	 * 'SemiCondensed' niet verward worden met het gewicht 'Semi'.
	 *
	 * @return array{family:string, weight:string, style:string}
	 */
	public static function parse_font_name( string $basename ): array {
		// Gewicht-regex: meer specifiek voor minder specifiek (volgorde telt!).
		$weight_patterns = [
			'200' => '/[ \-]?(200|\b((extra|ultra)\-?light)\b)/i',
			'800' => '/[ \-]?(800|\b((extra|ultra)\-?bold)\b)/i',
			'600' => '/[ \-]?(600|\b([ds]emi(\-?bold)?)\b)/i',
			'100' => '/[ \-]?(100|\bthin\b)/i',
			'300' => '/[ \-]?(300|\blight\b)/i',
			'400' => '/[ \-]?(400|\b(normal|regular|book)\b)/i',
			'500' => '/[ \-]?(500|\bmedium\b)/i',
			'700' => '/[ \-]?(700|\bbold\b)/i',
			'900' => '/[ \-]?(900|\b(black|heavy)\b)/i',
			'var' => '/[ \-]?(VariableFont|\[wght\])/i',
		];

		$name   = $basename;
		$weight = '400';
		$style  = 'normal';

		// Stijl detecteren en verwijderen.
		$new_name = (string) preg_replace( '/[ \-]?(italic|oblique)/i', '', $name, -1, $count );
		if ( $count > 0 && $new_name !== '' ) {
			$name  = $new_name;
			$style = 'italic';
		}

		// Gewicht detecteren en verwijderen.
		foreach ( $weight_patterns as $w => $pattern ) {
			$new_name = (string) preg_replace( $pattern, '', $name, -1, $count );
			if ( $count > 0 && $new_name !== '' ) {
				$name   = $new_name;
				$weight = (string) $w;
				break;
			}
		}

		// '-webfont' achtervoegsel verwijderen.
		$name = (string) preg_replace( '/[ \-]?webfont$/i', '', $name );

		// Bij variable fonts: optica-specificaties verwijderen.
		if ( 'var' === $weight ) {
			$name = (string) preg_replace( '/_(opsz,wght|opsz|wght)$/i', '', $name );
		}

		// Afsluitende koppeltekens/spaties opruimen.
		$family = trim( $name, ' -' );
		if ( '' === $family ) {
			$family = $basename;
		}

		return [ 'family' => $family, 'weight' => $weight, 'style' => $style ];
	}

	/** Geeft de CSS font-weight naam terug (voor weergave in de admin). */
	public static function weight_label( string $weight, string $style ): string {
		$names = [
			'100' => 'Thin',
			'200' => 'Extra Light',
			'300' => 'Light',
			'400' => 'Regular',
			'500' => 'Medium',
			'600' => 'Semi Bold',
			'700' => 'Bold',
			'800' => 'Extra Bold',
			'900' => 'Black',
			'var' => 'Variable',
		];

		$label = $names[ $weight ] ?? $weight;
		if ( 'italic' === $style ) {
			$label .= ' Italic';
		}
		return $label;
	}

	public static function get_format( string $ext ): string {
		return match ( strtolower( $ext ) ) {
			'woff2' => 'woff2',
			'woff'  => 'woff',
			'ttf'   => 'truetype',
			'otf'   => 'opentype',
			default => $ext,
		};
	}

	/**
	 * Bouwt alle @font-face declaraties op basis van de gegroepeerde fontlijst.
	 * Combineert meerdere bestandsformaten (woff2, ttf …) in één src:-regel.
	 * Variable fonts krijgen font-weight: 1 1000.
	 *
	 * @param array<string, array<string, array<string, string>>> $fonts
	 */
	public static function build_all_font_faces( array $fonts, string $display = 'swap' ): string {
		$css = '';
		foreach ( $fonts as $family => $variants ) {
			ksort( $variants );
			foreach ( $variants as $weight_style => $formats ) {
				[ $weight, $style ] = explode( '/', $weight_style, 2 );

				$urls = [];
				foreach ( self::FORMATS as $fmt ) {
					if ( isset( $formats[ $fmt ] ) ) {
						$urls[] = sprintf( "url('%s') format('%s')", esc_url( $formats[ $fmt ] ), self::get_format( $fmt ) );
					}
				}

				if ( empty( $urls ) ) {
					continue;
				}

				$weight_css = ( 'var' === $weight ) ? '1 1000' : esc_attr( $weight );

				$css .= sprintf(
					"@font-face {\n  font-family: '%s';\n  src: %s;\n  font-weight: %s;\n  font-style: %s;\n  font-display: %s;\n}\n",
					esc_attr( $family ),
					implode( ',\n       ', $urls ),
					$weight_css,
					esc_attr( $style ),
					esc_attr( $display )
				);
			}
		}
		return $css;
	}

	// -------------------------------------------------------------------------
	// CSS-bestand (gecachete alternatief voor inline)
	// -------------------------------------------------------------------------

	/**
	 * Genereert of hergebruikt het CSS-cachebestand in de fontmap.
	 * Alleen als de inhoud is veranderd wordt het bestand opnieuw geschreven.
	 * Geeft de URL terug inclusief versie-hash, of null bij schrijffout.
	 *
	 * @param array<string, array<string, array<string, string>>> $fonts
	 */
	private static function get_or_generate_css_file( array $fonts, string $display ): ?string {
		$dir      = self::font_dir();
		$css_path = $dir . self::CSS_FILE;
		$css_url  = self::font_url() . self::CSS_FILE;

		if ( ! wp_is_writable( $dir ) ) {
			return null;
		}

		$css_content = '/* Generated by PDK Theme Options */' . "\n" . self::build_all_font_faces( $fonts, $display );
		$new_hash    = hash( 'crc32', $css_content );
		$old_hash    = file_exists( $css_path ) ? hash_file( 'crc32', $css_path ) : '';

		if ( $new_hash !== $old_hash ) {
			file_put_contents( $css_path, $css_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		return $css_url . '?ver=' . $new_hash;
	}
}
