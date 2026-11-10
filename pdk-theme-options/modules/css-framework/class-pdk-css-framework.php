<?php
/**
 * Module: CSS Framework
 *
 * Genereert css-framework.css (fluid tokens volgens Utopia, basisstijlen voor
 * koppen en p, flow-regel en layoutklassen) uit gevalideerde getallen.
 * Normatief: docs/css-framework-brief.md.
 *
 * Er komt nooit vrije tekst in de CSS: alleen getallen binnen bereik en namen uit
 * een vaste lijst. Daarom wordt het bestand direct geschreven en niet via
 * pdk_write_storage_file() (geen code-cap, geen .bak/hash).
 */

defined( 'ABSPATH' ) || exit;

class PDK_CSS_Framework {

	const HANDLE = 'pdk-css-framework';
	const FILE   = 'css-framework.css';

	/** Optie met de pluginversie waarmee het bestand voor het laatst is gegenereerd. */
	const BUILT_OPTION = 'pdk_css_framework_built';

	/** T-shirtreeks van de ruimte-stappen => factor op de basis-fontgrootte. */
	const SHIRTS = [ '3xs' => 0.25, '2xs' => 0.5, 'xs' => 0.75, 's' => 1, 'm' => 1.5, 'l' => 2, 'xl' => 3, '2xl' => 4, '3xl' => 6 ];

	/** Tekstelementen waarop de flow-regel werkt (lijst T uit de brief). */
	const FLOW_TEXT = 'h1, h2, h3, h4, h5, h6, p, ul, ol, blockquote, figure, pre, table';

	/** Type, bereik per veld. */
	const INT_FIELDS   = [
		'min_viewport' => [ 200, 2000 ], 'max_viewport' => [ 200, 3840 ], 'steps_up' => [ 0, 8 ],
		'steps_down'   => [ 0, 4 ],
	];
	const FLOAT_FIELDS = [
		'font_min' => [ 8, 64 ], 'font_max' => [ 8, 64 ], 'ratio_min' => [ 1, 2 ], 'ratio_max' => [ 1, 2 ],
		'leading_body' => [ 0, 2 ], 'leading_heading' => [ 0, 2 ], 'grid_min' => [ 10, 80 ],
		'site_width' => [ 20, 240 ], 'measure' => [ 20, 120 ], 'lh_heading_ratio' => [ 1, 2 ], 'lh_body_ratio' => [ 1, 2 ],
	];
	/** De 13 klassen die het framework definieert of verwacht; één bron voor tab en MCP. */
	const CLASSES = [ 'pdk-off', 'pdk-container', 'pdk-content', 'pdk-popout', 'pdk-popout-start', 'pdk-popout-end', 'pdk-wide-start', 'pdk-wide-end', 'pdk-full', 'pdk-full-start', 'pdk-full-end', 'pdk-grid', 'pdk-flow-off' ];

	/** Transient: na een mislukte schrijfpoging (uploads niet schrijfbaar) niet elk request opnieuw proberen. */
	const WRITE_FAILED_TRANSIENT = 'pdk_css_framework_write_failed';

	const ENUM_FIELDS  = [ 'gutter', 'popout_space', 'flow_space', 'flow_heading_space' ];

	public function __construct( PDK_Loader $loader ) {
		$loader->add_action( 'wp_enqueue_scripts', $this, 'register', 9 );
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue', 20 );
	}

	/** Registreert de handle (prio 9), zodat custom-style hem als dependency kan krijgen. */
	public function register(): void {
		$file = PDK_STORAGE_DIR . self::FILE;

		// Regenereren als het bestand ontbreekt of als de plugin is bijgewerkt (D-P2):
		// zo komt gewijzigde generator-uitvoer ook zonder nieuwe save op de site.
		$failed = function_exists( 'get_transient' ) && get_transient( self::WRITE_FAILED_TRANSIENT );
		if ( ! $failed && ( ! file_exists( $file ) || get_option( self::BUILT_OPTION ) !== PDK_PLUGIN_VERSION ) ) {
			$config = self::config();
			if ( self::write_file( $config ) ) {
				update_option( self::BUILT_OPTION, PDK_PLUGIN_VERSION );
				// De Oxygen-variabelen volgen ná een plugin-update; nooit fataal, geen uitvoer op de frontend.
				try {
					self::sync_oxygen( $config );
				} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
					unset( $e );
				}
			} elseif ( function_exists( 'set_transient' ) ) {
				set_transient( self::WRITE_FAILED_TRANSIENT, 1, 10 * MINUTE_IN_SECONDS );
			}
		}
		if ( ! file_exists( $file ) ) {
			return; // Nooit een handle registreren zonder bestand.
		}

		wp_register_style( self::HANDLE, PDK_STORAGE_URL . self::FILE, [], filemtime( $file ) );
	}

	public function enqueue(): void {
		wp_enqueue_style( self::HANDLE );
	}

	// -------------------------------------------------------------------------
	// Config en validatie
	// -------------------------------------------------------------------------

	/**
	 * Defaults + opgeslagen waarden. Een ongeldig opgeslagen veld (oude of corrupte
	 * optie, bijv. na een wijziging van een bereik) valt terug op zijn default;
	 * geldige velden blijven. Zo kan de CSS nooit rommel of een TypeError opleveren.
	 */
	public static function config(): array {
		$defaults = PDK_Settings::get_defaults()['css_framework'];
		$saved    = array_intersect_key( (array) PDK_Settings::get( 'css_framework' ), $defaults );
		$result   = self::validate( $saved, $defaults );
		$config   = $result['config'];

		foreach ( array_keys( $result['errors'] ) as $key ) {
			$config[ $key ] = $defaults[ $key ];
		}
		// De kruiscontrole van de viewports hoort bij beide velden.
		if ( isset( $result['errors']['min_viewport'] ) || isset( $result['errors']['max_viewport'] ) ) {
			$config['min_viewport'] = $defaults['min_viewport'];
			$config['max_viewport'] = $defaults['max_viewport'];
		}
		$config['enabled'] = (bool) ( $saved['enabled'] ?? $defaults['enabled'] );

		return $config;
	}

	/**
	 * Valideert $input over $base. Eén functie voor admin en (later) MCP.
	 *
	 * @return array{config: array, errors: array<string,string>}
	 */
	public static function validate( array $input, ?array $base = null ): array {
		$defaults = PDK_Settings::get_defaults()['css_framework'];
		$base     = array_merge( $defaults, array_intersect_key( $base ?? self::config(), $defaults ) );
		$merged   = array_merge( $base, array_intersect_key( $input, $defaults ) );
		$merged['enabled'] = $base['enabled']; // Aan/uit gaat via de Modules-tab.

		$config = $merged;
		$errors = [];

		foreach ( self::INT_FIELDS as $key => [ $lo, $hi ] ) {
			$v = $merged[ $key ];
			if ( is_string( $v ) && preg_match( '/^-?\d+$/D', $v ) ) {
				$v = (int) $v;
			}
			if ( ! is_int( $v ) || $v < $lo || $v > $hi ) {
				$errors[ $key ] = sprintf( __( 'moet een geheel getal zijn tussen %1$d en %2$d.', 'pdk-theme-options' ), $lo, $hi );
				continue;
			}
			$config[ $key ] = $v;
		}

		foreach ( self::FLOAT_FIELDS as $key => [ $lo, $hi ] ) {
			$v = $merged[ $key ];
			if ( is_int( $v ) || is_float( $v ) || ( is_string( $v ) && is_numeric( $v ) ) ) {
				$v = (float) $v;
			} else {
				$v = null;
			}
			if ( null === $v || ! is_finite( $v ) || $v < $lo || $v > $hi ) {
				$errors[ $key ] = sprintf( __( 'moet een getal zijn tussen %1$s en %2$s.', 'pdk-theme-options' ), $lo, $hi );
				continue;
			}
			if ( in_array( $key, [ 'measure', 'lh_heading_ratio', 'lh_body_ratio', 'grid_min' ], true ) && abs( $v * 100 - round( $v * 100 ) ) > 1e-6 ) {
				$errors[ $key ] = __( 'mag hoogstens 2 decimalen hebben.', 'pdk-theme-options' );
				continue;
			}
			$config[ $key ] = $v;
		}

		if ( ! isset( $errors['min_viewport'] ) && ! isset( $errors['max_viewport'] ) && $config['min_viewport'] >= $config['max_viewport'] ) {
			$errors['min_viewport'] = __( 'moet kleiner zijn dan max_viewport.', 'pdk-theme-options' );
		}

		// flow_enabled: bool, 1/0 of '1'/'0'.
		$b = $merged['flow_enabled'];
		if ( true === $b || 1 === $b || '1' === $b ) {
			$config['flow_enabled'] = true;
		} elseif ( false === $b || 0 === $b || '0' === $b ) {
			$config['flow_enabled'] = false;
		} else {
			$errors['flow_enabled'] = __( 'moet aan of uit zijn.', 'pdk-theme-options' );
		}

		// space_pairs: one-up en dubbele paren vallen stil weg, de rest ongeldig = weigeren.
		$pairs = self::normalize_pairs( $merged['space_pairs'], $error );
		if ( null === $pairs ) {
			$errors['space_pairs'] = $error;
			$pairs                 = is_array( $base['space_pairs'] ) ? array_values( array_filter( $base['space_pairs'], 'is_string' ) ) : [];
		}
		$config['space_pairs'] = $pairs;

		// Enum-velden: alleen een token dat de NIEUWE config oplevert.
		$allowed = array_merge( array_keys( self::SHIRTS ), self::one_up_pairs(), $pairs );
		foreach ( self::ENUM_FIELDS as $key ) {
			if ( ! is_string( $merged[ $key ] ) || ! in_array( $merged[ $key ], $allowed, true ) ) {
				$errors[ $key ] = __( 'is geen bestaande ruimte-stap.', 'pdk-theme-options' );
			}
		}

		return [ 'config' => $config, 'errors' => $errors ];
	}

	/** @return string[] 8 one-up-paren, bijv. '3xs-2xs'. */
	private static function one_up_pairs(): array {
		$keys = array_keys( self::SHIRTS );
		$out  = [];
		foreach ( $keys as $i => $k ) {
			if ( isset( $keys[ $i + 1 ] ) ) {
				$out[] = $k . '-' . $keys[ $i + 1 ];
			}
		}
		return $out;
	}

	/** @return string[]|null Genormaliseerde lijst, of null (met $error) als de lijst ongeldig is. */
	private static function normalize_pairs( $raw, &$error ): ?array {
		$error = __( 'moet een lijst zijn van maximaal 10 paren a-b uit de t-shirtreeks, a vóór b.', 'pdk-theme-options' );
		if ( ! is_array( $raw ) || count( $raw ) > 10 ) {
			return null;
		}
		$keys   = array_keys( self::SHIRTS );
		$one_up = self::one_up_pairs();
		$out    = [];
		foreach ( $raw as $p ) {
			// Spaties en tabs rond een paar zijn onschuldig; een newline blijft ongeldig (de /D hieronder).
			$p = is_string( $p ) ? trim( $p, " 	" ) : $p;
			if ( ! is_string( $p ) || ! preg_match( '/^(3xs|2xs|xs|s|m|l|xl|2xl|3xl)-(3xs|2xs|xs|s|m|l|xl|2xl|3xl)$/D', $p, $m ) ) {
				return null;
			}
			if ( array_search( $m[1], $keys, true ) >= array_search( $m[2], $keys, true ) ) {
				return null;
			}
			if ( ! in_array( $p, $one_up, true ) && ! in_array( $p, $out, true ) ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	// -------------------------------------------------------------------------
	// Tokens en CSS
	// -------------------------------------------------------------------------

	/** Getal op 4 decimalen, locale-onafhankelijk, nooit '-0', zonder nullen achteraan. */
	private static function n( float $x ): string {
		$r = round( $x, 4 );
		if ( 0.0 === $r ) {
			return '0';
		}
		return rtrim( rtrim( number_format( $r, 4, '.', '' ), '0' ), '.' );
	}

	/** clamp() voor een stap van $min px naar $max px over de viewportrange. */
	private static function clamp( float $min, float $max, float $min_vw, float $max_vw ): string {
		$slope = $max_vw > $min_vw ? ( $max - $min ) / ( $max_vw - $min_vw ) : 0.0;
		$coef  = $slope * 100;
		$icept = $min - $min_vw * $slope;
		$op    = ( $coef < 0 && '0' !== self::n( $coef ) ) ? '-' : '+';

		return 'clamp(' . self::n( min( $min, $max ) / 16 ) . 'rem, ' . self::n( $icept / 16 ) . 'rem ' . $op . ' '
			. self::n( abs( $coef ) ) . 'cqi, ' . self::n( max( $min, $max ) / 16 ) . 'rem)';
	}

	/**
	 * De tokenlijst (één bron voor bestand, tab en later Oxygen/MCP).
	 * Fluid tokens hebben extra 'min' en 'max' (px) voor de tabel op de tab.
	 *
	 * @return array<int,array{name:string,value:string,min?:float,max?:float}>
	 */
	public static function tokens( array $c ): array {
		$out   = [];
		$min_v = (float) $c['min_viewport'];
		$max_v = (float) $c['max_viewport'];

		for ( $i = -(int) $c['steps_down']; $i <= (int) $c['steps_up']; $i++ ) {
			$min = $c['font_min'] * ( $c['ratio_min'] ** $i );
			$max = $c['font_max'] * ( $c['ratio_max'] ** $i );
			$out[] = [ 'name' => '--pdk-step-' . $i, 'value' => self::clamp( $min, $max, $min_v, $max_v ), 'min' => $min, 'max' => $max ];
		}

		$px = [];
		foreach ( self::SHIRTS as $k => $m ) {
			$px[ $k ] = [ round( $c['font_min'] * $m ), round( $c['font_max'] * $m ) ];
			$out[]    = [ 'name' => '--pdk-space-' . $k, 'value' => self::clamp( $px[ $k ][0], $px[ $k ][1], $min_v, $max_v ), 'min' => $px[ $k ][0], 'max' => $px[ $k ][1] ];
		}

		foreach ( array_merge( self::one_up_pairs(), $c['space_pairs'] ) as $pair ) {
			[ $a, $b ] = explode( '-', $pair, 2 );
			$out[]     = [ 'name' => '--pdk-space-' . $pair, 'value' => self::clamp( $px[ $a ][0], $px[ $b ][1], $min_v, $max_v ), 'min' => $px[ $a ][0], 'max' => $px[ $b ][1] ];
		}

		return array_merge( $out, [
			[ 'name' => '--pdk-site-width',          'value' => self::n( (float) $c['site_width'] ) . 'rem' ],
			[ 'name' => '--pdk-measure',             'value' => self::n( (float) $c['measure'] ) . 'ch' ],
			[ 'name' => '--pdk-gutter',              'value' => 'var(--pdk-space-' . $c['gutter'] . ')' ],
			[ 'name' => '--pdk-popout-space',        'value' => 'var(--pdk-space-' . $c['popout_space'] . ')' ],
			[ 'name' => '--pdk-grid-min',            'value' => self::n( (float) $c['grid_min'] ) . 'ch' ],
			[ 'name' => '--pdk-leading-body',        'value' => self::n( (float) $c['leading_body'] ) . 'rem' ],
			[ 'name' => '--pdk-leading-heading',     'value' => self::n( (float) $c['leading_heading'] ) . 'rem' ],
			[ 'name' => '--pdk-lh-heading',          'value' => self::n( (float) $c['lh_heading_ratio'] ) ],
			[ 'name' => '--pdk-lh-body',             'value' => self::n( (float) $c['lh_body_ratio'] ) ],
			[ 'name' => '--pdk-flow-space',          'value' => 'var(--pdk-space-' . $c['flow_space'] . ')' ],
			[ 'name' => '--pdk-flow-heading-space',  'value' => 'var(--pdk-space-' . $c['flow_heading_space'] . ')' ],
		] );
	}

	public static function generate( array $c ): string {
		$css = "/* (1) Tokens */\n:root {\n";
		foreach ( self::tokens( $c ) as $t ) {
			$css .= "\t" . $t['name'] . ': ' . $t['value'] . ";\n";
		}
		$css .= "}\n\n";

		// (2) Basisstijlen: kale elementselectors (wint van normalize en theme.json).
		for ( $n = 1; $n <= 6; $n++ ) {
			$css .= 'h' . $n . ' { font-size: var(--pdk-step-' . max( (int) $c['steps_up'] - ( $n - 1 ), 0 ) . "); }\n";
		}
		$css .= "h1, h2, h3, h4, h5, h6 { line-height: calc(var(--pdk-lh-heading) * 1em + var(--pdk-leading-heading)); }\n";
		$css .= "p { font-size: var(--pdk-step-0); line-height: calc(var(--pdk-lh-body) * 1em + var(--pdk-leading-body)); }\n\n";

		// (3) Flow: specificiteit 0, wint door volgorde (print ná de normalize).
		if ( $c['flow_enabled'] ) {
			$t    = ':where(' . self::FLOW_TEXT . ')';
			$css .= $t . " { margin-block: 0; }\n";
			$css .= $t . ' + ' . $t . " { margin-block-start: var(--pdk-flow-space); }\n";
			$css .= $t . " + :where(h2, h3, h4) { margin-block-start: var(--pdk-flow-heading-space); }\n";
			$css .= ":where(.pdk-grid, .pdk-flow-off) > * { margin-block: 0; }\n\n";
		}

		// (4) Layout: de section is het grid. Alles specificiteit 0, logische eigenschappen.
		$css .= <<<'CSS'
:where(.pdk-container) { container-type: inline-size; }
:where(section:not(.pdk-off):not([hidden])) {
	--pdk-bo-content: min(var(--pdk-measure), var(--pdk-site-width), 100% - var(--pdk-gutter) * 2);
	--pdk-bo-popout: minmax(0, min(var(--pdk-popout-space), max(0px, (var(--pdk-site-width) - var(--pdk-measure)) / 2)));
	--pdk-bo-wide: minmax(0, max(0px, (var(--pdk-site-width) - var(--pdk-measure)) / 2 - var(--pdk-popout-space)));
	--pdk-bo-full: minmax(var(--pdk-gutter), 1fr);
	container-type: inline-size;
	display: grid;
	font-size: var(--pdk-step-0);
	grid-template-columns:
		[full-start] var(--pdk-bo-full)
		[wide-start] var(--pdk-bo-wide)
		[popout-start] var(--pdk-bo-popout)
		[content-start] var(--pdk-bo-content) [content-end]
		var(--pdk-bo-popout) [popout-end]
		var(--pdk-bo-wide) [wide-end]
		var(--pdk-bo-full) [full-end];
}
:where(section:not(.pdk-off):not([hidden]):not(.pdk-grid)) > * { grid-column: wide; }
:where(section:not(.pdk-off):not([hidden]):not(.pdk-grid)) > :where(.pdk-content) { grid-column: content; }
:where(section:not(.pdk-off):not([hidden]):not(.pdk-grid)) > :where(.pdk-popout) { grid-column: popout; }
:where(section:not(.pdk-off):not([hidden]):not(.pdk-grid)) > :where(.pdk-popout-start) { grid-column: popout-start / content-end; }
:where(section:not(.pdk-off):not([hidden]):not(.pdk-grid)) > :where(.pdk-popout-end) { grid-column: content-start / popout-end; }
:where(section:not(.pdk-off):not([hidden]):not(.pdk-grid)) > :where(.pdk-wide-start) { grid-column: wide-start / content-end; }
:where(section:not(.pdk-off):not([hidden]):not(.pdk-grid)) > :where(.pdk-wide-end) { grid-column: content-start / wide-end; }
:where(section:not(.pdk-off):not([hidden]):not(.pdk-grid)) > :where(.pdk-full) { grid-column: full; }
:where(section:not(.pdk-off):not([hidden]):not(.pdk-grid)) > :where(.pdk-full-start) { grid-column: full-start / wide-end; }
:where(section:not(.pdk-off):not([hidden]):not(.pdk-grid)) > :where(.pdk-full-end) { grid-column: wide-start / full-end; }

:where(.pdk-grid) {
	display: grid;
	gap: var(--pdk-gutter);
	grid-template-columns: repeat(auto-fit, minmax(min(var(--pdk-grid-min), 100%), 1fr));
}

CSS;

		return $css;
	}

	// -------------------------------------------------------------------------
	// Opslaan
	// -------------------------------------------------------------------------

	public static function write_file( array $config ): bool {
		if ( ! pdk_ensure_storage_dir() ) {
			return false;
		}
		return false !== file_put_contents( PDK_STORAGE_DIR . self::FILE, self::generate( $config ), LOCK_EX );
	}

	/**
	 * Valideren -> bestand schrijven -> opslaan. Bij een fout verandert er niets.
	 *
	 * @return array{saved: bool, config: array, errors: array<string,string>}
	 */
	public static function save( array $input ): array {
		$result = self::validate( $input );
		$config = $result['config'];

		if ( $result['errors'] ) {
			return [ 'saved' => false, 'config' => self::config(), 'errors' => $result['errors'] ];
		}
		if ( ! self::write_file( $config ) ) {
			return [ 'saved' => false, 'config' => self::config(), 'errors' => [ 'file' => __( 'het CSS-bestand kon niet worden geschreven.', 'pdk-theme-options' ) ] ];
		}

		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::WRITE_FAILED_TRANSIENT );
		}

		// Direct in de optie: PDK_Settings::update() merget recursief per index, en zou een
		// kortere space_pairs-lijst dus niet vervangen. 'enabled' blijft zoals het was.
		unset( $config['enabled'] );
		$options                    = (array) get_option( PDK_Settings::OPTION_KEY, [] );
		$options['css_framework']   = array_merge( (array) ( $options['css_framework'] ?? [] ), $config );
		update_option( PDK_Settings::OPTION_KEY, $options );

		return [ 'saved' => true, 'config' => self::config(), 'errors' => [], 'oxygen' => self::sync_oxygen( $config + [ 'enabled' => true ] ) ];
	}

	// -------------------------------------------------------------------------
	// Oxygen-variabelen (brief §5)
	// -------------------------------------------------------------------------

	const OXYGEN_COLLECTION = 'PDK Framework';

	/**
	 * Pure merge van de tokens in Oxygens variabelenlijst: overschrijft op naam met
	 * behoud van het id en de plek, voegt nieuwe toe aan het eind, laat de rest staan
	 * (ook verouderde pdk-variabelen: verwijderen zou {var-id}-verwijzingen breken).
	 *
	 * @return array{variables: array, collections: array, changed: bool}
	 */
	public static function merge_oxygen_variables( array $variables, array $collections, array $tokens ): array {
		$changed = false;
		$index   = [];
		foreach ( $variables as $i => $v ) {
			if ( is_array( $v ) && isset( $v['cssVariableName'] ) && ! isset( $index[ $v['cssVariableName'] ] ) ) {
				$index[ $v['cssVariableName'] ] = $i;
			}
		}

		foreach ( $tokens as $t ) {
			$name  = substr( $t['name'], 2 ); // zonder voorloop-'--', zoals Oxygens eigen insert.
			$label = 'PDK ' . str_replace( '--', ' -', preg_replace( '/(?<=\w)-(?=\w)/', ' ', substr( $name, 4 ) ) );
			$value = [ 'number' => $t['value'], 'unit' => 'custom', 'style' => $t['value'] ];

			if ( isset( $index[ $name ] ) ) {
				// Bestaande variabele: alleen type en value bijwerken; id, label, collectie en extra sleutels blijven.
				$old = $variables[ $index[ $name ] ];
				$new = $old;
				if ( ! isset( $old['id'] ) || ! is_string( $old['id'] ) || '' === $old['id'] ) {
					$new['id'] = wp_generate_uuid4();
				}
				$new['type']  = 'unit';
				$new['value'] = $value;
				if ( $new != $old ) { // phpcs:ignore Universal.Operators.StrictComparisons -- sleutelvolgorde mag verschillen.
					$variables[ $index[ $name ] ] = $new;
					$changed                      = true;
				}
			} else {
				$variables[]    = [
					'id'              => wp_generate_uuid4(),
					'cssVariableName' => $name,
					'label'           => $label,
					'type'            => 'unit',
					'value'           => $value,
					'collection'      => self::OXYGEN_COLLECTION,
				];
				$index[ $name ] = array_key_last( $variables );
				$changed        = true;
			}
		}

		if ( ! in_array( self::OXYGEN_COLLECTION, $collections, true ) ) {
			$collections[] = self::OXYGEN_COLLECTION;
			$changed       = true;
		}

		return [ 'variables' => $variables, 'collections' => $collections, 'changed' => $changed ];
	}

	/** Ruwe optie lezen (nooit de gefilterde getVariables()). Oud formaat: JSON-string. */
	private static function read_oxygen_option( string $key ): array {
		if ( ! \Breakdance\Data\global_option_exists( $key ) ) {
			return [];
		}
		$raw = \Breakdance\Data\get_global_option( $key );
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		if ( ! is_array( $raw ) ) {
			throw new \UnexpectedValueException( sprintf( 'Oxygen-optie %s is geen lijst.', $key ) );
		}
		return $raw;
	}

	/**
	 * Synchroniseert de tokens naar Oxygen 6 via de interne saveVariables(), met de
	 * bescherming tegen stil wissen uit §5. Blokkeert nooit het opslaan.
	 *
	 * @return array{status: string, message?: string}
	 */
	public static function sync_oxygen( array $config ): array {
		if ( ! pdk_oxygen6_active() ) {
			return [ 'status' => 'skipped', 'message' => __( 'Oxygen 6 niet actief — alleen het CSS-bestand bijgewerkt', 'pdk-theme-options' ) ];
		}
		if ( ! function_exists( 'Breakdance\Variables\saveVariables' ) || ! function_exists( 'Breakdance\Data\get_global_option' ) || ! function_exists( 'Breakdance\Data\global_option_exists' ) ) {
			return [ 'status' => 'skipped', 'message' => __( 'Oxygen-variabelen niet beschikbaar in deze Oxygen-versie — alleen het CSS-bestand bijgewerkt', 'pdk-theme-options' ) ];
		}

		try {
			$vars   = self::read_oxygen_option( 'variables_json_string' );
			// Collecties leniënt: ontbreekt de lijst of is ze leeg, dan zaaien uit de variabelen, zoals Oxygen
			// zelf doet (variables.php:94-107). Alleen de variabelenlijst zelf is streng.
			try {
				$colls = self::read_oxygen_option( 'variables_collections_json_string' );
			} catch ( \UnexpectedValueException $e ) {
				$colls = [];
			}
			if ( ! $colls ) {
				$colls = array_values( array_unique( array_filter( array_column( array_filter( $vars, 'is_array' ), 'collection' ), 'is_string' ) ) );
			}
			$merged = self::merge_oxygen_variables( $vars, $colls, self::tokens( $config ) );
			if ( ! $merged['changed'] ) {
				return [ 'status' => 'unchanged' ];
			}

			// saveVariables() controleert niets en wist bij een kapotte payload alle variabelen. Het vervangt bovendien elke {} in de payload
			// door null, ook in de teksten van andere variabelen: bevat de payload {}, dan schrijven we niet door.
			// ponytail: wp_json_encode saneert ongeldige UTF-8 zelf (geeft dan geen false); de guard blijft als vangnet.
			$payload = wp_json_encode( [ 'variables' => $merged['variables'], 'collections' => $merged['collections'] ] );
			if ( false === $payload || '' === $payload ) {
				throw new \RuntimeException( 'payload niet te coderen' );
			}
			if ( str_contains( $payload, '{}' ) ) {
				throw new \RuntimeException( 'payload bevat {} (Oxygen zou die stukmaken)' );
			}
			$check = json_decode( $payload, true ); // geen {} aanwezig (zie hierboven), dus Oxygens str_replace verandert niets.
			if ( ! is_array( $check ) || ! is_array( $check['variables'] ?? null ) || ! is_array( $check['collections'] ?? null ) ) {
				throw new \RuntimeException( 'payload zou na Oxygens decode ongeldig zijn' );
			}

			\Breakdance\Variables\saveVariables( $payload );
			return [ 'status' => 'synced' ];
		} catch ( \Throwable $e ) {
			return [ 'status' => 'error', 'message' => $e->getMessage() ];
		}
	}

	// -------------------------------------------------------------------------
	// Admin-tab
	// -------------------------------------------------------------------------

	/** Modern Normalize-stand: null als Oxygen inactief is of de check faalt. Alleen-lezen. */
	public static function modern_normalize_state(): ?bool {
		if ( ! pdk_oxygen6_active() || ! function_exists( 'Breakdance\Settings\get_bool_setting' ) ) {
			return null;
		}
		try {
			return (bool) \Breakdance\Settings\get_bool_setting( 'modern_normalize_enabled' );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Eén bron voor de uitleg van elk instelveld (brief §3a): label, eenheid, help en groep,
	 * in de volgorde van de tab. De admin-tab, het MCP-schema en get-css-framework gebruiken deze lijst.
	 *
	 * @return array<string,array{label:string,unit:string,help:string,group:string}>
	 */
	public static function fields(): array {
		$f = static fn( string $label, string $unit, string $help, string $group ): array => [ 'label' => $label, 'unit' => $unit, 'help' => $help, 'group' => $group ];
		$g = [
			'viewport' => __( 'Viewport', 'pdk-theme-options' ),
			'typo'     => __( 'Typografie', 'pdk-theme-options' ),
			'lh'       => __( 'Regelhoogte', 'pdk-theme-options' ),
			'space'    => __( 'Ruimte', 'pdk-theme-options' ),
			'layout'   => __( 'Layout', 'pdk-theme-options' ),
			'flow'     => __( 'Flow', 'pdk-theme-options' ),
			'grid'     => __( 'Grid', 'pdk-theme-options' ),
		];

		return [
			'min_viewport'       => $f( __( 'Kleinste schermbreedte', 'pdk-theme-options' ), 'px', __( 'Bij deze schermbreedte staan tekst en ruimte op hun kleinste maat; nog smaller verandert er niets meer. Bijvoorbeeld 400 (Oxygen Phone Portrait).', 'pdk-theme-options' ), $g['viewport'] ),
			'max_viewport'       => $f( __( 'Grootste schermbreedte', 'pdk-theme-options' ), 'px', __( 'Bij deze schermbreedte staan tekst en ruimte op hun grootste maat; nog breder verandert er niets meer. Bijvoorbeeld 1440, gelijk aan de sitebreedte.', 'pdk-theme-options' ), $g['viewport'] ),
			'font_min'           => $f( __( 'Basisgrootte klein scherm', 'pdk-theme-options' ), 'px', __( 'Lettergrootte van gewone tekst op het kleinste scherm; alle kop- en ruimtestappen rekenen hiervanuit. Bijvoorbeeld 18.', 'pdk-theme-options' ), $g['typo'] ),
			'font_max'           => $f( __( 'Basisgrootte groot scherm', 'pdk-theme-options' ), 'px', __( 'Lettergrootte van gewone tekst op het grootste scherm. Bijvoorbeeld 20.', 'pdk-theme-options' ), $g['typo'] ),
			'ratio_min'          => $f( __( 'Schaalfactor klein scherm', 'pdk-theme-options' ), 'factor', __( 'Hoeveel keer groter elke kopstap is dan de vorige, op het kleinste scherm. Bijvoorbeeld 1.2.', 'pdk-theme-options' ), $g['typo'] ),
			'ratio_max'          => $f( __( 'Schaalfactor groot scherm', 'pdk-theme-options' ), 'factor', __( 'Hoeveel keer groter elke kopstap is dan de vorige, op het grootste scherm; hoger dan klein geeft meer contrast op groot scherm. Bijvoorbeeld 1.25.', 'pdk-theme-options' ), $g['typo'] ),
			'steps_up'           => $f( __( 'Stappen omhoog', 'pdk-theme-options' ), 'aantal', __( 'Aantal grotere stappen boven de basisgrootte; h1 gebruikt de hoogste stap. Bijvoorbeeld 5.', 'pdk-theme-options' ), $g['typo'] ),
			'steps_down'         => $f( __( 'Stappen omlaag', 'pdk-theme-options' ), 'aantal', __( 'Aantal kleinere stappen onder de basisgrootte, voor kleine tekst zoals bijschriften. Bijvoorbeeld 2.', 'pdk-theme-options' ), $g['typo'] ),
			'lh_heading_ratio'   => $f( __( 'Regelhoogte koppen', 'pdk-theme-options' ), 'factor', __( 'Regelhoogte van h1–h6 is deze factor × de lettergrootte, plus de extra regelhoogte; verhoog hem bij fonts met lange uitlopers. Bijvoorbeeld 1.1.', 'pdk-theme-options' ), $g['lh'] ),
			'lh_body_ratio'      => $f( __( 'Regelhoogte tekst', 'pdk-theme-options' ), 'factor', __( 'Regelhoogte van alinea\'s is deze factor × de lettergrootte, plus de extra regelhoogte. Bijvoorbeeld 1.3.', 'pdk-theme-options' ), $g['lh'] ),
			'leading_heading'    => $f( __( 'Extra regelhoogte koppen', 'pdk-theme-options' ), 'rem', __( 'Vaste toeslag op de regelhoogte van koppen; geeft kleine koppen relatief meer lucht dan grote. Bijvoorbeeld 0.25.', 'pdk-theme-options' ), $g['lh'] ),
			'leading_body'       => $f( __( 'Extra regelhoogte tekst', 'pdk-theme-options' ), 'rem', __( 'Vaste toeslag op de regelhoogte van alinea\'s. Bijvoorbeeld 0.25.', 'pdk-theme-options' ), $g['lh'] ),
			'space_pairs'        => $f( __( 'Extra ruimteparen', 'pdk-theme-options' ), 'lijst', __( 'Eigen ruimtetokens die van de ene stap naar een verdere stap groeien, naast de vaste buurparen; schrijf als klein-groot. Bijvoorbeeld s-l.', 'pdk-theme-options' ), $g['space'] ),
			'gutter'             => $f( __( 'Zijmarge', 'pdk-theme-options' ), 'ruimtetoken', __( 'Minimale ruimte tussen de inhoud en de schermrand, en de tussenruimte in .pdk-grid. Bijvoorbeeld s-m.', 'pdk-theme-options' ), $g['space'] ),
			'popout_space'       => $f( __( 'Uitsteek popout', 'pdk-theme-options' ), 'ruimtetoken', __( 'Hoe ver .pdk-popout aan elke kant buiten de tekstkolom steekt. Bijvoorbeeld 2xl.', 'pdk-theme-options' ), $g['space'] ),
			'site_width'         => $f( __( 'Sitebreedte', 'pdk-theme-options' ), 'rem', __( 'Maximale breedte van de inhoud; elk direct kind van een section staat standaard op deze breedte. Bijvoorbeeld 90 (= 1440px).', 'pdk-theme-options' ), $g['layout'] ),
			'measure'            => $f( __( 'Leesbreedte', 'pdk-theme-options' ), 'ch', __( 'Breedte van de tekstkolom .pdk-content; 60–75 is prettig leesbaar. Bijvoorbeeld 65. 1ch is de breedte van een 0 in de basisgrootte van de section (step-0).', 'pdk-theme-options' ), $g['layout'] ),
			'flow_enabled'       => $f( __( 'Verticale ritmiek', 'pdk-theme-options' ), 'aan/uit', __( 'Zet automatische ruimte tussen opeenvolgende koppen, alinea\'s en lijsten aan. Bijvoorbeeld aan.', 'pdk-theme-options' ), $g['flow'] ),
			'flow_space'         => $f( __( 'Ruimte tussen tekstblokken', 'pdk-theme-options' ), 'ruimtetoken', __( 'Ruimte boven een tekstelement dat direct op een ander volgt. Bijvoorbeeld s.', 'pdk-theme-options' ), $g['flow'] ),
			'flow_heading_space' => $f( __( 'Ruimte boven tussenkoppen', 'pdk-theme-options' ), 'ruimtetoken', __( 'Ruimte boven een h2–h4 die direct op tekst volgt. Bijvoorbeeld l.', 'pdk-theme-options' ), $g['flow'] ),
			'grid_min'           => $f( __( 'Minimale kaartbreedte', 'pdk-theme-options' ), 'ch', __( 'Smalste breedte van een kaart in .pdk-grid; past er geen kaart meer naast, dan schuift hij naar een nieuwe rij. Bijvoorbeeld 30.', 'pdk-theme-options' ), $g['grid'] ),
		];
	}

	public static function render_inline(): void {
		$c = self::config();

		if ( false === self::modern_normalize_state() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Het CSS Framework gaat uit van Oxygens Modern Normalize (een reset die alle standaardmarges en -padding van de browser weghaalt; de flow-regel bouwt daarop). Zet hem aan via Oxygen → Settings → Advanced → Modern Normalize → Enable. Let op: op een bestaande site verschuift daardoor content die op browsermarges leunt.', 'pdk-theme-options' ) . '</p></div>';
		}

		$tokens = array_merge( array_keys( self::SHIRTS ), self::one_up_pairs(), $c['space_pairs'] );
		$input  = static function ( string $key ) use ( $c, $tokens ): void {
			$id = 'cssfw-' . esc_attr( $key );
			if ( in_array( $key, self::ENUM_FIELDS, true ) ) {
				echo '<select id="' . $id . '" name="css_framework[' . esc_attr( $key ) . ']">';
				foreach ( $tokens as $t ) {
					echo '<option value="' . esc_attr( $t ) . '"' . selected( $c[ $key ], $t, false ) . '>' . esc_html( $t ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'flow_enabled' === $key ) {
				echo '<input type="checkbox" id="' . $id . '" name="css_framework[flow_enabled]" value="1"' . checked( $c['flow_enabled'], true, false ) . '>';
			} elseif ( 'space_pairs' === $key ) {
				foreach ( array_merge( $c['space_pairs'], [ '', '' ] ) as $pair ) {
					echo '<input type="text" name="css_framework[space_pairs][]" value="' . esc_attr( $pair ) . '" class="small-text" placeholder="s-l"> ';
				}
			} else {
				// Stappen per veldtype (één bron: INT_FIELDS/FLOAT_FIELDS en de eenheid uit fields()): hele getallen 1;
				// factoren 0,1 / Ctrl 0,01 / Alt 0,01; overige decimale velden 1 / Ctrl 0,1 / Alt 0,01.
				// step="any" blijft de basis, zodat handmatig ingevulde waarden (1.15, 65.25) geldig blijven.
				$steps = isset( self::INT_FIELDS[ $key ] ) ? [ 1, '', '' ] : ( 'factor' === ( self::fields()[ $key ]['unit'] ?? '' ) ? [ 0.1, 0.01, 0.01 ] : [ 1, 0.1, 0.01 ] );
				echo '<input type="number" step="any" id="' . $id . '" name="css_framework[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $c[ $key ] ) . '" class="small-text" data-pdk-step="' . esc_attr( (string) $steps[0] ) . '" data-pdk-step-ctrl="' . esc_attr( (string) $steps[1] ) . '" data-pdk-step-alt="' . esc_attr( (string) $steps[2] ) . '">';
			}
		};

		echo '<p>' . esc_html__( 'Pas tokens hier aan, niet in Oxygen. Het bestand wordt bij elke geldige save opnieuw gegenereerd; bij een ongeldige waarde wordt er niets opgeslagen.', 'pdk-theme-options' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Tip: houd Ctrl ingedrukt voor stappen van 0,1 en Alt voor 0,01 (alleen decimale velden).', 'pdk-theme-options' ) . '</p>';
		$groups = [];
		foreach ( self::fields() as $key => $f ) {
			$groups[ $f['group'] ][ $key ] = $f;
		}
		foreach ( $groups as $group => $group_fields ) {
			echo '<h3>' . esc_html( $group ) . '</h3><table class="form-table" role="presentation"><tbody>';
			foreach ( $group_fields as $key => $f ) {
				echo '<tr><th scope="row"><label for="cssfw-' . esc_attr( $key ) . '">' . esc_html( $f['label'] ) . '</label></th><td>';
				$input( $key );
				echo ' <span class="pdk-unit">' . esc_html( $f['unit'] ) . '</span> <span class="description">— ' . esc_html( $f['help'] ) . '</span></td></tr>';
			}
			echo '</tbody></table>';
		}

		// Modifier-stappen: tijdens het indrukken (pijltje, pijltoets, wiel) tijdelijk de stap van het veld
		// zetten, daarna terug naar "any" zodat handmatige waarden geldig blijven. Vanilla JS, alleen op deze tab.
		echo "<script>( function () {
	var inputs = document.querySelectorAll( 'input[data-pdk-step]' );
	function apply( e ) {
		var i = e.currentTarget, s = i.dataset.pdkStep;
		if ( e.ctrlKey && i.dataset.pdkStepCtrl ) { s = i.dataset.pdkStepCtrl; }
		else if ( e.altKey && i.dataset.pdkStepAlt ) { s = i.dataset.pdkStepAlt; }
		i.step = s;
	}
	function reset( e ) { e.currentTarget.step = 'any'; }
	inputs.forEach( function ( i ) {
		[ 'pointerdown', 'mousedown', 'keydown', 'wheel' ].forEach( function ( t ) { i.addEventListener( t, apply ); } );
		[ 'pointerup', 'mouseup', 'keyup', 'blur', 'mouseleave' ].forEach( function ( t ) { i.addEventListener( t, reset ); } );
		i.addEventListener( 'change', function () { if ( i.value !== '' && ! isNaN( i.value ) ) { i.value = String( Math.round( parseFloat( i.value ) * 100 ) / 100 ); } } );
	} );
}() );</script>";

		echo '<h3>' . esc_html__( 'Banen van de section', 'pdk-theme-options' ) . '</h3><p>' . esc_html__( 'Elke section is een grid; een direct kind staat standaard op de wide-baan. -start en -end wijzen naar de start- en eindkant en draaien dus om in RTL.', 'pdk-theme-options' ) . '</p><ul style="list-style:disc;margin-left:1.5em">';
		foreach ( array_diff( self::CLASSES, [ 'pdk-off', 'pdk-container', 'pdk-grid', 'pdk-flow-off' ] ) as $cls ) {
			echo '<li><code>.' . esc_html( $cls ) . '</code></li>';
		}
		echo '</ul><h3>' . esc_html__( 'Overige klassen', 'pdk-theme-options' ) . '</h3><ul style="list-style:disc;margin-left:1.5em">';
		echo '<li><code>.pdk-off</code> ' . esc_html__( 'op een section: geen grid en geen container-type.', 'pdk-theme-options' ) . '</li>';
		echo '<li><code>.pdk-container</code> ' . esc_html__( 'losse container voor cqi buiten sections.', 'pdk-theme-options' ) . '</li>';
		echo '<li><code>.pdk-grid</code> ' . esc_html__( 'auto-grid zonder media queries.', 'pdk-theme-options' ) . '</li>';
		echo '<li><code>.pdk-flow-off</code> ' . esc_html__( 'op een container: geen flow-marges tussen de kinderen.', 'pdk-theme-options' ) . '</li></ul>';
		echo '<p>' . esc_html__( 'Uitweg voor sections van derden (zet in Custom CSS, wint altijd van het framework):', 'pdk-theme-options' ) . '</p>';
		echo '<pre>' . esc_html( '.woocommerce section { display: block; container-type: normal; font-size: inherit; }' ) . '</pre>';

		echo '<h3>' . esc_html__( 'Tokens', 'pdk-theme-options' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Token', 'pdk-theme-options' ) . '</th><th>min px</th><th>max px</th><th>' . esc_html__( 'Waarde', 'pdk-theme-options' ) . '</th></tr></thead><tbody>';
		foreach ( self::tokens( $c ) as $t ) {
			echo '<tr><td><code>' . esc_html( $t['name'] ) . '</code></td><td>' . esc_html( isset( $t['min'] ) ? self::n( (float) $t['min'] ) : '' ) . '</td><td>' . esc_html( isset( $t['max'] ) ? self::n( (float) $t['max'] ) : '' ) . '</td><td><code>' . esc_html( $t['value'] ) . '</code></td></tr>';
		}
		echo '</tbody></table>';
	}
}
