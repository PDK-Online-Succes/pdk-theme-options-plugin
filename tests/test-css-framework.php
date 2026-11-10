<?php
/**
 * Zelftest voor de module CSS Framework (Slice 1: AC-CSS-001 t/m -010, -019 t/m -023, H-delen; plus D-P2 en config()-fallback).
 * Normatief: docs/css-framework-brief.md. Draaien met:
 *
 *   php tests/test-css-framework.php
 *
 * Verwachte API van PDK_CSS_Framework (modules/css-framework/class-pdk-css-framework.php):
 *
 *   __construct( PDK_Loader )                  hooks: wp_enqueue_scripts -> register (prio 9), enqueue (prio 20)
 *   register(): void                           regenereert het bestand als het ontbreekt; wp_register_style( 'pdk-css-framework', URL, [], filemtime )
 *   enqueue(): void                            wp_enqueue_style( 'pdk-css-framework' )
 *   static config(): array                     defaults + opgeslagen waarden (volledige config)
 *   static validate( array $input, ?array $base = null ): array{config:array,errors:array<string,string>}
 *                                              $input over $base (default: config()); errors = veldnaam => melding; leeg = geldig
 *   static tokens( array $config ): array      lijst van [ 'name' => '--pdk-step-0', 'value' => 'clamp(...)', ... ] (extra sleutels mogen)
 *   static generate( array $config ): string   de CSS
 *   static write_file( array $config ): bool   schrijft PDK_STORAGE_DIR . 'css-framework.css' (direct, geen .bak/hash)
 *   static save( array $input ): array{saved:bool,config:array,errors:array<string,string>}
 *                                              validate -> opslaan in pdk_theme_options[css_framework] -> write_file; bij fout niets wijzigen
 *
 * Admin: PDK_Admin::handle_save() met $_POST['pdk_tab'] = 'css_framework' en de velden in $_POST['css_framework'][<sleutel>]
 * (strings zoals een formulier ze stuurt; space_pairs als array van strings; een ontbrekende flow_enabled = false).
 * Een tab wordt gerenderd door PDK_Admin::render_tab_content( 'css_framework' ).
 */

namespace Breakdance\Settings {
	/** Stub van Oxygen: leest de bool-setting; gooit zoals het echte als de definitie geen bool is. */
	function get_bool_setting( string $key ): bool {
		$GLOBALS['bd_calls'][] = [ 'get_bool_setting', $key ];
		if ( 'throw' === ( $GLOBALS['bd_modern'] ?? null ) ) {
			throw new \InvalidArgumentException( 'geen bool' );
		}
		return (bool) ( $GLOBALS['bd_modern'] ?? false );
	}
}

namespace Breakdance\Variables {
	/** Stub van Oxygens schrijf-functie: de tab-render mag die nooit aanroepen. */
	function saveVariables( string $json ) {
		$GLOBALS['bd_calls'][] = [ 'saveVariables', $json ];
	}
}

namespace {
	if ( PHP_SAPI !== 'cli' ) {
		exit;
	}

	$pdk = dirname( __DIR__ ) . '/pdk-theme-options';

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'PDK_PLUGIN_VERSION', '0.0.0-test' );
	define( 'PDK_PLUGIN_DIR', $pdk . '/' );
	define( 'PDK_CAP_EDIT_CODE', 'pdk_edit_custom_code' );
	$tmp = str_replace( '\\', '/', sys_get_temp_dir() ) . '/pdk-cssfw-test-' . getmypid();
	define( 'PDK_STORAGE_DIR', $tmp . '/uploads/pdk-theme-options/' );
	define( 'PDK_STORAGE_URL', 'https://example.test/uploads/pdk-theme-options/' );
	mkdir( dirname( PDK_STORAGE_DIR ), 0777, true );

	// ---- WordPress-stubs ------------------------------------------------------
	$GLOBALS['options']    = [];
	$GLOBALS['opt_writes'] = 0;
	$GLOBALS['caps']       = [ 'manage_options' ];
	$GLOBALS['registered'] = [];
	$GLOBALS['enqueued']   = [];
	$GLOBALS['bd_calls']   = [];
	$GLOBALS['styles']     = [];

	function add_filter( ...$a ) { return true; }
	function add_action( ...$a ) { return true; }
	function __( $s, $d = null ) { return $s; }
	function _e( $s, $d = null ) { echo $s; }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $s ) { return (string) $s; }
	function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_html__( $s, $d = null ) { return esc_html( $s ); }
	function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
	function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
	function esc_attr_e( $s, $d = null ) { echo esc_attr( $s ); }
	function wp_kses_post( $s ) { return (string) $s; }
	function wp_kses( $s, $a = [] ) { return (string) $s; }
	function checked( $a, $b = true, $echo = true ) { $r = ( (string) $a === (string) $b ) ? " checked='checked'" : ''; if ( $echo ) { echo $r; } return $r; }
	function selected( $a, $b = true, $echo = true ) { $r = ( (string) $a === (string) $b ) ? " selected='selected'" : ''; if ( $echo ) { echo $r; } return $r; }
	function disabled( $a, $b = true, $echo = true ) { $r = ( (string) $a === (string) $b ) ? " disabled='disabled'" : ''; if ( $echo ) { echo $r; } return $r; }
	function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d ); }
	function get_admin_page_title() { return 'PDK'; }
	function wp_nonce_field( ...$a ) {}
	function submit_button( ...$a ) {}
	function is_multisite() { return false; }
	function get_site_option( $k, $d = false ) { return $d; }
	function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
	function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; $GLOBALS['opt_writes']++; return true; }
	function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['caps'], true ); }
	function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
	function wp_die( $m = '', $c = 0 ) { throw new RuntimeException( 'wp_die: ' . $m ); }
	function check_admin_referer( ...$a ) { return true; }
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
	function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
	function wp_unslash( $v ) { return $v; }
	function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
	function add_query_arg( ...$a ) {
		if ( is_array( $a[0] ) ) { $args = $a[0]; $url = $a[1] ?? ''; } else { $args = [ $a[0] => $a[1] ]; $url = $a[2] ?? ''; }
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}
	class PDK_Test_Redirect extends Exception {
		public string $url;
		public function __construct( string $url ) { parent::__construct( $url ); $this->url = $url; }
	}
	function wp_safe_redirect( $url, $status = 302 ) { throw new PDK_Test_Redirect( (string) $url ); }
	function wp_register_style( $h, $src = '', $deps = [], $ver = false, $media = 'all' ) {
		$GLOBALS['registered'][ $h ] = compact( 'src', 'deps', 'ver' );
		return true;
	}
	function wp_enqueue_style( $h, $src = '', $deps = [], $ver = false, $media = 'all' ) {
		$GLOBALS['enqueued'][ $h ] = compact( 'src', 'deps', 'ver' );
		if ( '' === $src && isset( $GLOBALS['registered'][ $h ] ) ) {
			$GLOBALS['enqueued'][ $h ] = $GLOBALS['registered'][ $h ];
		}
	}
	function wp_style_is( $h, $status = 'enqueued' ) {
		return 'registered' === $status ? isset( $GLOBALS['registered'][ $h ] ) : isset( $GLOBALS['enqueued'][ $h ] );
	}
	class WP_Error {
		public function __construct( public $code = '', public $message = '', public $data = null ) {}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
	class PDK_Loader {
		public array $hooks = [];
		public function add_action( string $hook, $obj, string $method, int $prio = 10, int $args = 1 ): void { $this->hooks[] = [ $hook, $method, $prio ]; }
		public function add_filter( string $hook, $obj, string $method, int $prio = 10, int $args = 1 ): void { $this->hooks[] = [ $hook, $method, $prio ]; }
	}

	require_once $pdk . '/includes/helpers.php';
	require_once $pdk . '/includes/class-pdk-settings.php';
	require_once $pdk . '/includes/class-pdk-plugin.php';
	require_once $pdk . '/includes/class-pdk-admin.php';
	require_once $pdk . '/modules/custom-css/class-pdk-custom-css.php';

	$fouten = 0;
	function check( string $naam, bool $ok, string $extra = '' ): void {
		global $fouten;
		if ( ! $ok ) { $fouten++; }
		echo ( $ok ? '  ok   ' : '  FOUT ' ) . $naam . ( ! $ok && $extra ? "\n       " . $extra : '' ) . "\n";
	}

	// ---- Onafhankelijke Utopia-rekenmachine (NIET uit de module; brief §1d) -----
	function ref_n( float $x ): string {
		$r = round( $x, 4 );
		if ( 0.0 === $r ) { return '0'; }
		return rtrim( rtrim( number_format( $r, 4, '.', '' ), '0' ), '.' );
	}
	function ref_clamp( float $min, float $max, float $minVw, float $maxVw ): string {
		$slope = ( $max - $min ) / ( $maxVw - $minVw );
		$icept = $min - $minVw * $slope;
		$coef  = $slope * 100;
		$op    = ( $coef < 0 && '0' !== ref_n( $coef ) ) ? '-' : '+';
		return 'clamp(' . ref_n( min( $min, $max ) / 16 ) . 'rem, ' . ref_n( $icept / 16 ) . 'rem ' . $op . ' ' . ref_n( abs( $coef ) ) . 'cqi, ' . ref_n( max( $min, $max ) / 16 ) . 'rem)';
	}
	const REF_SHIRTS = [ '3xs' => 0.25, '2xs' => 0.5, 'xs' => 0.75, 's' => 1, 'm' => 1.5, 'l' => 2, 'xl' => 3, '2xl' => 4, '3xl' => 6 ];
	/** @return array<string,string> tokennaam => waarde, alleen de fluid tokens. */
	function ref_tokens( array $c ): array {
		$out = [];
		for ( $n = -$c['steps_down']; $n <= $c['steps_up']; $n++ ) {
			$out[ '--pdk-step-' . $n ] = ref_clamp( $c['font_min'] * ( $c['ratio_min'] ** $n ), $c['font_max'] * ( $c['ratio_max'] ** $n ), $c['min_viewport'], $c['max_viewport'] );
		}
		$px = [];
		foreach ( REF_SHIRTS as $k => $m ) {
			$px[ $k ] = [ round( $c['font_min'] * $m ), round( $c['font_max'] * $m ) ];
			$out[ '--pdk-space-' . $k ] = ref_clamp( $px[ $k ][0], $px[ $k ][1], $c['min_viewport'], $c['max_viewport'] );
		}
		$keys = array_keys( REF_SHIRTS );
		foreach ( $keys as $i => $k ) {
			if ( isset( $keys[ $i + 1 ] ) ) {
				$out[ '--pdk-space-' . $k . '-' . $keys[ $i + 1 ] ] = ref_clamp( $px[ $k ][0], $px[ $keys[ $i + 1 ] ][1], $c['min_viewport'], $c['max_viewport'] );
			}
		}
		foreach ( $c['space_pairs'] as $p ) {
			[ $a, $b ] = explode( '-', $p, 2 );
			$out[ '--pdk-space-' . $p ] = ref_clamp( $px[ $a ][0], $px[ $b ][1], $c['min_viewport'], $c['max_viewport'] );
		}
		return $out;
	}

	// ---- CSS-hulpen ---------------------------------------------------------------
	function compact_css( string $s ): string { return preg_replace( '/\s+/', '', $s ); }
	/** Parseert CSS naar [ [ 'sel' => compact, 'body' => raw, 'children' => [...] ] ]; statements ('@layer a, b;') hebben body null. */
	function css_parse( string $css ): array {
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		$out = [];
		$len = strlen( $css );
		$i   = 0;
		$pre = '';
		while ( $i < $len ) {
			$ch = $css[ $i ];
			if ( ';' === $ch ) {
				$out[] = [ 'sel' => compact_css( $pre ), 'body' => null, 'children' => [] ];
				$pre = '';
				$i++;
			} elseif ( '{' === $ch ) {
				$depth = 1;
				$j     = $i + 1;
				while ( $j < $len && $depth > 0 ) {
					if ( '{' === $css[ $j ] ) { $depth++; }
					if ( '}' === $css[ $j ] ) { $depth--; }
					$j++;
				}
				$body  = substr( $css, $i + 1, $j - $i - 2 );
				$out[] = [ 'sel' => compact_css( $pre ), 'body' => $body, 'children' => str_contains( $body, '{' ) ? css_parse( $body ) : [] ];
				$pre   = '';
				$i     = $j;
			} else {
				$pre .= $ch;
				$i++;
			}
		}
		return $out;
	}
	function css_unlayered( string $css ): array {
		return array_values( array_filter( css_parse( $css ), static fn( $r ) => null !== $r['body'] && ! str_starts_with( $r['sel'], '@layer' ) ) );
	}
	function css_layer( string $css, string $name ): array {
		foreach ( css_parse( $css ) as $r ) {
			if ( '@layer' . $name === $r['sel'] && null !== $r['body'] ) { return $r['children']; }
		}
		return [];
	}
	function css_find( array $rules, string $selector ): ?array {
		$s = compact_css( $selector );
		foreach ( $rules as $r ) { if ( $r['sel'] === $s ) { return $r; } }
		return null;
	}
	/** Staat de declaratie (spatie-onafhankelijk) in de regel? */
	function css_has( ?array $rule, string $decl ): bool {
		return null !== $rule && str_contains( compact_css( (string) $rule['body'] ), compact_css( $decl ) );
	}

	/** Haalt :where( ... ) weg maar houdt de inhoud: ':where(a > b)' -> 'a>b' (compact). */
	function sel_plain( string $sel ): string {
		$out = ''; $stack = []; $n = strlen( $sel );
		for ( $i = 0; $i < $n; $i++ ) {
			if ( substr( $sel, $i, 7 ) === ':where(' ) { $stack[] = true; $i += 6; continue; }
			if ( '(' === $sel[ $i ] ) { $stack[] = false; }
			if ( ')' === $sel[ $i ] ) { if ( array_pop( $stack ) ) { continue; } }
			$out .= $sel[ $i ];
		}
		// :not([hidden]) en :not(.pdk-grid) zijn uitsluitingen op de section; de vormvergelijkingen hieronder gaan over de baan zelf (eigen checks verderop).
		return str_replace( [ ':not([hidden])', ':not(.pdk-grid)' ], '', $out );
	}
	/** Selector zonder de inhoud van :where(...) en zonder combinatoren: leeg = specificiteit 0. */
	function sel_buiten_where( string $sel ): string {
		$out = ''; $depth = 0; $n = strlen( $sel );
		for ( $i = 0; $i < $n; $i++ ) {
			if ( 0 === $depth && substr( $sel, $i, 7 ) === ':where(' ) { $depth = 1; $i += 6; continue; }
			if ( $depth > 0 ) { if ( '(' === $sel[ $i ] ) { $depth++; } elseif ( ')' === $sel[ $i ] ) { $depth--; } continue; }
			$out .= $sel[ $i ];
		}
		return str_replace( [ ',', '>', '+', '~', '*' ], '', $out );
	}
	function css_plain_find( array $rules, string $plain ): ?array {
		$s = compact_css( $plain );
		foreach ( $rules as $r ) { if ( sel_plain( $r['sel'] ) === $s ) { return $r; } }
		return null;
	}
	function is_flow_rule( array $r ): bool {
		return str_contains( $r['sel'], 'h1,h2,h3,h4,h5,h6,p,ul,ol' ) || ':where(.pdk-grid,.pdk-flow-off)>*' === $r['sel'];
	}
	function is_base_rule( array $r ): bool {
		return (bool) preg_match( '/^(h[1-6](,h[1-6])*|p|:root)$/', $r['sel'] );
	}

	const T_LIJST = 'h1, h2, h3, h4, h5, h6, p, ul, ol, blockquote, figure, pre, table';

	function reset_world(): void {
		$GLOBALS['options'] = []; $GLOBALS['opt_writes'] = 0; $GLOBALS['caps'] = [ 'manage_options' ];
		$GLOBALS['registered'] = []; $GLOBALS['enqueued'] = []; $GLOBALS['bd_calls'] = [];
		$GLOBALS['bd_modern'] = false; $_POST = [];
		foreach ( glob( PDK_STORAGE_DIR . '*' ) ?: [] as $f ) { is_file( $f ) && unlink( $f ); }
		@mkdir( PDK_STORAGE_DIR, 0777, true );
	}
	function module_aan( array $extra = [] ): void {
		$GLOBALS['options']['pdk_theme_options'] = [ 'css_framework' => array_merge( [ 'enabled' => true ], $extra ) ];
	}
	function set_oxygen( bool $actief ): void {
		$GLOBALS['options']['active_plugins'] = $actief ? [ 'oxygen/plugin.php' ] : [];
	}
	function opgeslagen(): array { return (array) ( ( $GLOBALS['options']['pdk_theme_options'] ?? [] )['css_framework'] ?? [] ); }
	function css_pad(): string { return PDK_STORAGE_DIR . 'css-framework.css'; }
	function ongeveer( $a, $b ): bool { return ( is_int( $a ) || is_float( $a ) ) && abs( $a - $b ) < 1e-9; }

	// De standaardwaarden uit brief §3 — zelf uitgeschreven, niet opgehaald uit de module.
	$DEF = [
		'enabled' => false, 'min_viewport' => 400, 'max_viewport' => 1440, 'font_min' => 18, 'font_max' => 20,
		'ratio_min' => 1.2, 'ratio_max' => 1.25, 'steps_up' => 5, 'steps_down' => 2, 'leading_body' => 0.25, 'lh_heading_ratio' => 1.1, 'lh_body_ratio' => 1.3,
		'leading_heading' => 0.25, 'site_width' => 90, 'measure' => 65, 'gutter' => 's-m', 'popout_space' => '2xl', 'grid_min' => 30,
		'space_pairs' => [ 's-l' ], 'flow_enabled' => true, 'flow_space' => 's', 'flow_heading_space' => 'l',
	];

	// ============================================================================
	echo "AC-CSS-001 Module en instellingen\n";
	// ============================================================================
	reset_world();
	$mm = ( new ReflectionClass( 'PDK_Plugin' ) )->getDefaultProperties()['module_map'] ?? [];
	check( '$module_map bevat css_framework met bestand en klasse',
		( $mm['css_framework'] ?? null ) === [ 'file' => 'modules/css-framework/class-pdk-css-framework.php', 'class' => 'PDK_CSS_Framework' ] );
	$tabs = ( new ReflectionClass( 'PDK_Admin' ) )->getConstants()['MODULE_TABS'] ?? [];
	check( 'MODULE_TABS bevat css_framework => "CSS Framework"', ( $tabs['css_framework'] ?? null ) === 'CSS Framework' );
	check( 'modulelabel en -omschrijving aanwezig', ( PDK_Settings::module_labels()['css_framework'] ?? '' ) !== '' && ( PDK_Settings::module_descriptions()['css_framework'] ?? '' ) !== '' );

	$d = PDK_Settings::get_defaults()['css_framework'] ?? [];
	$s = $DEF; $a = $d; ksort( $s ); ksort( $a );
	check( 'defaults hebben precies de sleutels van §3', array_keys( $s ) === array_keys( $a ), 'verschil: ' . implode( ',', array_merge( array_diff( array_keys( $s ), array_keys( $a ) ), array_diff( array_keys( $a ), array_keys( $s ) ) ) ) );
	$types_ok = true; $waar_ok = true;
	foreach ( $DEF as $k => $v ) {
		$got = $d[ $k ] ?? null;
		if ( is_int( $v ) && in_array( $k, [ 'min_viewport', 'max_viewport', 'steps_up', 'steps_down' ], true ) ) { $ok = ( $got === $v ); }
		elseif ( is_int( $v ) || is_float( $v ) ) { $ok = ongeveer( $got, $v ); }
		else { $ok = ( $got === $v ); }
		$waar_ok = $waar_ok && $ok;
		if ( ! $ok ) { echo "       default {$k}: " . var_export( $got, true ) . ' ipv ' . var_export( $v, true ) . "\n"; }
	}
	check( 'defaultwaarden en types kloppen (enabled false, 400, 1440, 1440, flow true, s, l)', $waar_ok );

	set_oxygen( false );
	check( 'module_missing_dependency zonder Oxygen is leeg', '' === PDK_Settings::module_missing_dependency( 'css_framework' ) );
	set_oxygen( true );
	check( 'ook met Oxygen actief is er geen afhankelijkheid', '' === PDK_Settings::module_missing_dependency( 'css_framework' ) );
	set_oxygen( false );
	check( 'module staat standaard uit', false === PDK_Settings::is_module_enabled( 'css_framework' ) );
	module_aan();
	check( 'module is aan te zetten zonder Oxygen', true === PDK_Settings::is_module_enabled( 'css_framework' ) );

	$ref_admin = new ReflectionClass( 'PDK_Admin' );
	$get_tabs  = $ref_admin->getMethod( 'get_tabs' );
	$admin     = new PDK_Admin( new PDK_Loader() );
	check( 'tab "CSS Framework" zichtbaar met module aan', ( $get_tabs->invoke( $admin )['css_framework'] ?? null ) === 'CSS Framework' );
	reset_world();
	check( 'tab niet zichtbaar met module uit', ! isset( $get_tabs->invoke( $admin )['css_framework'] ) );

	// Vanaf hier is de module zelf nodig.
	$module_file = $pdk . '/modules/css-framework/class-pdk-css-framework.php';
	if ( ! file_exists( $module_file ) ) {
		check( 'module-bestand bestaat (modules/css-framework/class-pdk-css-framework.php)', false, 'ontbreekt nog: verwacht rood voor de implementatie' );
		echo "\n" . $fouten . " test(s) gefaald (module nog niet aanwezig)\n";
		exit( 1 );
	}
	require_once $module_file;
	check( 'klasse PDK_CSS_Framework bestaat', class_exists( 'PDK_CSS_Framework' ) );

	// ============================================================================
	echo "\nAC-CSS-003 Fluid tokens volgens Utopia (tabel §1d, onafhankelijk herberekend)\n";
	// ============================================================================
	// De literals uit de brief, 1-op-1 overgenomen.
	$BRIEF = [
		'--pdk-step--2' => 'clamp(0.7813rem, 0.774rem + 0.0288cqi, 0.8rem)',
		'--pdk-step--1' => 'clamp(0.9375rem, 0.9135rem + 0.0962cqi, 1rem)',
		'--pdk-step-0'  => 'clamp(1.125rem, 1.0769rem + 0.1923cqi, 1.25rem)',
		'--pdk-step-1'  => 'clamp(1.35rem, 1.2683rem + 0.3269cqi, 1.5625rem)',
		'--pdk-step-2'  => 'clamp(1.62rem, 1.4919rem + 0.5125cqi, 1.9531rem)',
		'--pdk-step-3'  => 'clamp(1.944rem, 1.7527rem + 0.7652cqi, 2.4414rem)',
		'--pdk-step-4'  => 'clamp(2.3328rem, 2.0563rem + 1.1061cqi, 3.0518rem)',
		'--pdk-step-5'  => 'clamp(2.7994rem, 2.4088rem + 1.5621cqi, 3.8147rem)',
		'--pdk-space-3xs' => 'clamp(0.3125rem, 0.3125rem + 0cqi, 0.3125rem)',
		'--pdk-space-2xs' => 'clamp(0.5625rem, 0.5385rem + 0.0962cqi, 0.625rem)',
		'--pdk-space-xs'  => 'clamp(0.875rem, 0.851rem + 0.0962cqi, 0.9375rem)',
		'--pdk-space-s'   => 'clamp(1.125rem, 1.0769rem + 0.1923cqi, 1.25rem)',
		'--pdk-space-m'   => 'clamp(1.6875rem, 1.6154rem + 0.2885cqi, 1.875rem)',
		'--pdk-space-l'   => 'clamp(2.25rem, 2.1538rem + 0.3846cqi, 2.5rem)',
		'--pdk-space-xl'  => 'clamp(3.375rem, 3.2308rem + 0.5769cqi, 3.75rem)',
		'--pdk-space-2xl' => 'clamp(4.5rem, 4.3077rem + 0.7692cqi, 5rem)',
		'--pdk-space-3xl' => 'clamp(6.75rem, 6.4615rem + 1.1538cqi, 7.5rem)',
		'--pdk-space-3xs-2xs' => 'clamp(0.3125rem, 0.1923rem + 0.4808cqi, 0.625rem)',
		'--pdk-space-2xs-xs'  => 'clamp(0.5625rem, 0.4183rem + 0.5769cqi, 0.9375rem)',
		'--pdk-space-xs-s'    => 'clamp(0.875rem, 0.7308rem + 0.5769cqi, 1.25rem)',
		'--pdk-space-s-m'     => 'clamp(1.125rem, 0.8365rem + 1.1538cqi, 1.875rem)',
		'--pdk-space-m-l'     => 'clamp(1.6875rem, 1.375rem + 1.25cqi, 2.5rem)',
		'--pdk-space-l-xl'    => 'clamp(2.25rem, 1.6731rem + 2.3077cqi, 3.75rem)',
		'--pdk-space-xl-2xl'  => 'clamp(3.375rem, 2.75rem + 2.5cqi, 5rem)',
		'--pdk-space-2xl-3xl' => 'clamp(4.5rem, 3.3462rem + 4.6154cqi, 7.5rem)',
		'--pdk-space-s-l'     => 'clamp(1.125rem, 0.5962rem + 2.1154cqi, 2.5rem)',
	];
	$ref = ref_tokens( $DEF );
	check( 'brief-tabel = onafhankelijke herberekening uit de formule (alle 26 rijen)', $ref === $BRIEF, 'verschil: ' . json_encode( array_diff_assoc( $ref, $BRIEF ) ) );

	reset_world();
	$cfg = PDK_CSS_Framework::config();
	check( 'config() zonder opgeslagen waarden = defaults', ongeveer( $cfg['font_min'], 18 ) && 400 === $cfg['min_viewport'] && [ 's-l' ] === $cfg['space_pairs'] );
	$tok = [];
	foreach ( PDK_CSS_Framework::tokens( array_merge( $DEF, [ 'enabled' => true ] ) ) as $t ) { $tok[ $t['name'] ] = $t['value']; }
	foreach ( $BRIEF as $naam => $waarde ) {
		check( "generator {$naam}", ( $tok[ $naam ] ?? null ) === $waarde, 'kreeg: ' . var_export( $tok[ $naam ] ?? null, true ) );
	}
	$neg = array_merge( $DEF, [ 'ratio_min' => 1.5, 'ratio_max' => 1.1 ] );
	$tn  = array_column( PDK_CSS_Framework::tokens( $neg ), 'value', 'name' );
	check( 'dalende stap: step-5 = clamp(2.0131rem, 11.0544rem - 10.0459cqi, 8.543rem)', ( $tn['--pdk-step-5'] ?? '' ) === 'clamp(2.0131rem, 11.0544rem - 10.0459cqi, 8.543rem)', var_export( $tn['--pdk-step-5'] ?? null, true ) );

	// Extra configs tegen de onafhankelijke rekenmachine (negatieve intercept, andere viewport, andere schaal, extra paren).
	$varianten = [
		'font 8-64 (negatieve intercept)' => array_merge( $DEF, [ 'font_min' => 8, 'font_max' => 64 ] ),
		'viewport 320-1920, 16-22, 1.125/1.333, 3 op / 1 neer' => array_merge( $DEF, [ 'min_viewport' => 320, 'max_viewport' => 1920, 'font_min' => 16, 'font_max' => 22, 'ratio_min' => 1.125, 'ratio_max' => 1.333, 'steps_up' => 3, 'steps_down' => 1 ] ),
		'dalend 1.5/1.1' => $neg,
		'paren m-xl en 3xs-3xl' => array_merge( $DEF, [ 'space_pairs' => [ 'm-xl', '3xs-3xl' ] ] ),
		'steps 0/0' => array_merge( $DEF, [ 'steps_up' => 0, 'steps_down' => 0 ] ),
		'gelijke min/max (helling 0)' => array_merge( $DEF, [ 'font_min' => 20, 'font_max' => 20, 'ratio_min' => 1.25, 'ratio_max' => 1.25 ] ),
	];
	foreach ( $varianten as $label => $c ) {
		$got = [];
		foreach ( PDK_CSS_Framework::tokens( $c ) as $t ) { if ( str_starts_with( $t['value'], 'clamp(' ) ) { $got[ $t['name'] ] = $t['value']; } }
		check( "tokens {$label} = herberekening", $got === ref_tokens( $c ), 'verschil: ' . json_encode( array_diff_assoc( ref_tokens( $c ), $got ) ) );
	}
	$alle = [];
	foreach ( $varianten as $c ) { foreach ( PDK_CSS_Framework::tokens( $c ) as $t ) { $alle[] = $t['value']; } }
	$fout_fmt = array_filter( $alle, static fn( $v ) => str_starts_with( $v, 'clamp(' ) && ! preg_match( '/^clamp\(\d+(\.\d{1,4})?rem, -?\d+(\.\d{1,4})?rem [+-] \d+(\.\d{1,4})?cqi, \d+(\.\d{1,4})?rem\)$/', $v ) );
	check( 'notatie: max 4 decimalen, geen "1.1250", operator + of -', ! $fout_fmt, implode( ' | ', $fout_fmt ) );
	check( 'nooit "+ -", nooit "-0" als getal', ! array_filter( $alle, static fn( $v ) => str_contains( $v, '+ -' ) || preg_match( '/(?<![\d.])-0(?![\d.])/', $v ) ) );
	check( 'helling nul geeft "+ 0cqi"', str_contains( $BRIEF['--pdk-space-3xs'], '+ 0cqi' ) && ( $tok['--pdk-space-3xs'] ?? '' ) === $BRIEF['--pdk-space-3xs'] );

	// ============================================================================
	echo "\nAC-CSS-004 Tokenset en geen vw/vi/@media\n";
	// ============================================================================
	$ts    = PDK_CSS_Framework::tokens( array_merge( $DEF, [ 'enabled' => true ] ) );
	$namen = array_column( $ts, 'name' );
	$verwacht = array_merge( array_keys( $BRIEF ), [ '--pdk-site-width', '--pdk-measure', '--pdk-gutter', '--pdk-popout-space', '--pdk-grid-min', '--pdk-leading-body', '--pdk-leading-heading', '--pdk-lh-heading', '--pdk-lh-body', '--pdk-flow-space', '--pdk-flow-heading-space' ] );
	check( 'V13: precies 11 vaste tokens (incl. --pdk-lh-heading en --pdk-lh-body); geen --pdk-bo-* en geen --pdk-feature-space op :root', 11 === count( array_filter( $namen, static fn( $n ) => ! preg_match( '/^--pdk-(step|space)-/', $n ) ) ) && ! array_filter( $namen, static fn( $n ) => str_starts_with( $n, '--pdk-bo-' ) || '--pdk-feature-space' === $n ) );
	check( '26 fluid tokens: 8 steps + 9 spaces + 8 paren + s-l', 26 === count( array_filter( $namen, static fn( $n ) => preg_match( '/^--pdk-(step|space)-/', $n ) ) ) );
	check( 'precies de verwachte namen (measure, popout-space), elk één keer', count( $namen ) === count( array_unique( $namen ) ) && ! array_diff( $verwacht, $namen ) && ! array_diff( $namen, $verwacht ), 'ontbreekt: ' . implode( ',', array_diff( $verwacht, $namen ) ) . ' | te veel: ' . implode( ',', array_diff( $namen, $verwacht ) ) );
	$vast = array_column( $ts, 'value', 'name' );
	check( 'vaste tokens: site-width 90rem, measure 65ch, gutter/popout/flow als var(), V14 grid-min 30ch, leadings',
		'90rem' === ( $vast['--pdk-site-width'] ?? null ) && '65ch' === ( $vast['--pdk-measure'] ?? null ) && 'var(--pdk-space-s-m)' === ( $vast['--pdk-gutter'] ?? null ) && 'var(--pdk-space-2xl)' === ( $vast['--pdk-popout-space'] ?? null ) && ! isset( $vast['--pdk-feature-space'] )
		&& '30ch' === $vast['--pdk-grid-min'] && '0.25rem' === $vast['--pdk-leading-body'] && '0.25rem' === $vast['--pdk-leading-heading'] && '1.1' === ( $vast['--pdk-lh-heading'] ?? null ) && '1.3' === ( $vast['--pdk-lh-body'] ?? null )
		&& 'var(--pdk-space-s)' === $vast['--pdk-flow-space'] && 'var(--pdk-space-l)' === $vast['--pdk-flow-heading-space'] );
	$tv = array_column( PDK_CSS_Framework::tokens( array_merge( $DEF, [ 'site_width' => 75.5, 'measure' => '60.25' ] ) ), 'value', 'name' );
	check( 'site_width 75.5 -> 75.5rem, measure 60.25 -> 60.25ch', '75.5rem' === ( $tv['--pdk-site-width'] ?? null ) && '60.25ch' === ( $tv['--pdk-measure'] ?? null ) );
	$ts_uit = array_column( PDK_CSS_Framework::tokens( array_merge( $DEF, [ 'flow_enabled' => false ] ) ), 'name' );
	check( '--pdk-flow-* tokens staan er ook met flow_enabled = false', in_array( '--pdk-flow-space', $ts_uit, true ) && in_array( '--pdk-flow-heading-space', $ts_uit, true ) );
	$klein = array_column( PDK_CSS_Framework::tokens( array_merge( $DEF, [ 'steps_up' => 3, 'steps_down' => 1, 'space_pairs' => [] ] ) ), 'name' );
	check( 'steps_up 3 / steps_down 1: step--1..step-3, geen step-4/-5/--2, geen space-s-l',
		in_array( '--pdk-step--1', $klein, true ) && in_array( '--pdk-step-3', $klein, true ) && ! in_array( '--pdk-step-4', $klein, true ) && ! in_array( '--pdk-step--2', $klein, true ) && ! in_array( '--pdk-space-s-l', $klein, true ) );

	$css = PDK_CSS_Framework::generate( array_merge( $DEF, [ 'enabled' => true ] ) );
	$zonder_commentaar = preg_replace( '#/\*.*?\*/#s', '', $css );
	check( 'generate() geeft een niet-lege string', is_string( $css ) && '' !== trim( $css ) );
	check( 'CSS bevat geen vw/vi/vh-eenheid en geen @media', ! preg_match( '/\d(vw|vi|vh)\b/', $zonder_commentaar ) && ! str_contains( $zonder_commentaar, '@media' ) );
	check( 'CSS bevat geen "+ -" en geen "-0rem"', ! str_contains( $zonder_commentaar, '+ -' ) && ! str_contains( $zonder_commentaar, '-0rem' ) );
	$tokenlaag = css_find( css_unlayered( $css ), ':root' );
	$alle_in_laag = null !== $tokenlaag;
	foreach ( $ts as $t ) { $alle_in_laag = $alle_in_laag && css_has( $tokenlaag, $t['name'] . ': ' . $t['value'] ); }
	check( 'alle tokens staan in :root (unlayered)', $alle_in_laag );
	check( 'D-L1: geen enkele @layer in de CSS', ! str_contains( $zonder_commentaar, '@layer' ) );
	check( 'generate() is deterministisch', $css === PDK_CSS_Framework::generate( array_merge( $DEF, [ 'enabled' => true ] ) ) );

	// ============================================================================
	echo "\nAC-CSS-005 Basisstijlen koppen en p (unlayered)\n";
	// ============================================================================
	check( 'D-L1: het bestand begint niet met @layer', ! str_starts_with( ltrim( $css ), '@layer' ) );
	$volgorde = array_column( css_unlayered( $css ), 'sel' );
	$i_root = array_search( ':root', $volgorde, true ); $i_h1 = array_search( 'h1', $volgorde, true ); $i_p0 = array_search( 'p', $volgorde, true );
	$i_fl = array_search( compact_css( ':where(' . T_LIJST . ')' ), $volgorde, true ); $i_lay = array_search( ':where(.pdk-container)', $volgorde, true );
	check( 'volgorde: tokens (:root) -> basisregels -> flow -> layout', 0 === $i_root && false !== $i_h1 && $i_h1 > $i_root && false !== $i_p0 && false !== $i_fl && $i_fl > $i_p0 && false !== $i_lay && $i_lay > $i_fl, json_encode( [ $i_root, $i_h1, $i_p0, $i_fl, $i_lay ] ) );
	$ul = css_unlayered( $css );
	for ( $n = 1; $n <= 6; $n++ ) {
		$r = css_find( $ul, "h{$n}" );
		check( "h{$n} unlayered met font-size: var(--pdk-step-" . ( 6 - $n ) . ')', css_has( $r, 'font-size: var(--pdk-step-' . ( 6 - $n ) . ')' ) );
	}
	check( 'V13: h1..h6 { line-height: calc(var(--pdk-lh-heading) * 1em + var(--pdk-leading-heading)) }', css_has( css_find( $ul, 'h1, h2, h3, h4, h5, h6' ), 'line-height: calc(var(--pdk-lh-heading) * 1em + var(--pdk-leading-heading))' ) );
	$p = css_find( $ul, 'p' );
	check( 'V13: p { font-size: var(--pdk-step-0); line-height: calc(var(--pdk-lh-body) * 1em + var(--pdk-leading-body)) }', css_has( $p, 'font-size: var(--pdk-step-0)' ) && css_has( $p, 'line-height: calc(var(--pdk-lh-body) * 1em + var(--pdk-leading-body))' ) );
	check( 'selectors van de kop-/p-regels zijn kaal (geen :where(), geen klasse)', ! array_filter( [ 'h1', 'h6', 'h1,h2,h3,h4,h5,h6', 'p' ], static fn( $x ) => null === css_find( $ul, $x ) ) );
	$css3 = PDK_CSS_Framework::generate( array_merge( $DEF, [ 'steps_up' => 3 ] ) );
	$ul3  = css_unlayered( $css3 );
	$map3 = [ 1 => 3, 2 => 2, 3 => 1, 4 => 0, 5 => 0, 6 => 0 ];
	$ok3  = true;
	foreach ( $map3 as $n => $stap ) { $ok3 = $ok3 && css_has( css_find( $ul3, "h{$n}" ), "font-size: var(--pdk-step-{$stap})" ); }
	check( 'steps_up 3: h1/h2/h3 = step-3/-2/-1, h4-h6 = step-0', $ok3 );

	// ============================================================================
	echo "\nAC-CSS-006 Layout: de section is het grid (V10), unlayered, specificiteit 0, logisch (V11)\n";
	// ============================================================================
	$ul   = css_unlayered( $css );
	$lay  = array_values( array_filter( $ul, static fn( $r ) => ! is_base_rule( $r ) && ! is_flow_rule( $r ) ) );
	check( 'layoutblok: 13 regels (container, section, > *, 9 baan-klassen, grid)', 13 === count( $lay ), (string) count( $lay ) . ': ' . implode( ' | ', array_column( $lay, 'sel' ) ) );
	// Specificiteit (0,0,0): alles wat een klasse of element noemt staat in :where(); alleen combinatoren en * blijven buiten.
	$niet0 = array_filter( $lay, static fn( $r ) => '' !== sel_buiten_where( $r['sel'] ) );
	check( 'specificiteit 0 voor elke layoutselector (alles in :where(), ook de kind-selectors)', ! $niet0, implode( ' | ', array_column( $niet0, 'sel' ) ) );
	$niet0f = array_filter( array_filter( $ul, 'is_flow_rule' ), static fn( $r ) => '' !== sel_buiten_where( $r['sel'] ) );
	check( 'specificiteit 0 voor de flowregels', ! $niet0f );
	check( ':where(.pdk-container) heeft alleen container-type: inline-size', compact_css( (string) ( css_plain_find( $lay, '.pdk-container' )['body'] ?? 'x' ) ) === 'container-type:inline-size;' || compact_css( (string) ( css_plain_find( $lay, '.pdk-container' )['body'] ?? 'x' ) ) === 'container-type:inline-size' );
	check( ':where(.pdk-container, section...) bestaat niet meer als gecombineerde selector', null === css_plain_find( $lay, 'section:not(.pdk-off), .pdk-container' ) );

	$sec  = css_plain_find( $lay, 'section:not(.pdk-off)' );
	$secb = compact_css( (string) ( $sec['body'] ?? '' ) );
	check( 'section-regel bestaat (:where(section:not(.pdk-off)))', null !== $sec );
	$BO = [
		'--pdk-bo-content' => 'min(var(--pdk-measure), var(--pdk-site-width), 100% - var(--pdk-gutter) * 2)',
		'--pdk-bo-popout'  => 'minmax(0, min(var(--pdk-popout-space), max(0px, (var(--pdk-site-width) - var(--pdk-measure)) / 2)))',
		'--pdk-bo-wide'    => 'minmax(0, max(0px, (var(--pdk-site-width) - var(--pdk-measure)) / 2 - var(--pdk-popout-space)))',
		'--pdk-bo-full'    => 'minmax(var(--pdk-gutter), 1fr)',
	];
	foreach ( $BO as $naam => $waarde ) { check( "section definieert {$naam} letterlijk", css_has( $sec, "{$naam}: {$waarde}" ) ); }
	check( 'section: container-type inline-size en display grid', css_has( $sec, 'container-type: inline-size' ) && css_has( $sec, 'display: grid' ) );
	check( 'section: grid-template-columns met lijnnamen full-, wide-, popout-, content-start/-end in volgorde',
		css_has( $sec, 'grid-template-columns: [full-start] var(--pdk-bo-full) [wide-start] var(--pdk-bo-wide) [popout-start] var(--pdk-bo-popout) [content-start] var(--pdk-bo-content) [content-end] var(--pdk-bo-popout) [popout-end] var(--pdk-bo-wide) [wide-end] var(--pdk-bo-full) [full-end]' ), $secb );
	check( 'V12: section krijgt font-size: var(--pdk-step-0)', css_has( $sec, 'font-size: var(--pdk-step-0)' ), $secb );

	// Kindregels in vaste volgorde: > * (wide) en daarna de negen klassen.
	$kinderen = [
		'section:not(.pdk-off) > *'                 => 'wide',
		'section:not(.pdk-off) > .pdk-content'      => 'content',
		'section:not(.pdk-off) > .pdk-popout'       => 'popout',
		'section:not(.pdk-off) > .pdk-popout-start' => 'popout-start / content-end',
		'section:not(.pdk-off) > .pdk-popout-end'   => 'content-start / popout-end',
		'section:not(.pdk-off) > .pdk-wide-start'   => 'wide-start / content-end',
		'section:not(.pdk-off) > .pdk-wide-end'     => 'content-start / wide-end',
		'section:not(.pdk-off) > .pdk-full'         => 'full',
		'section:not(.pdk-off) > .pdk-full-start'   => 'full-start / wide-end',
		'section:not(.pdk-off) > .pdk-full-end'     => 'wide-start / full-end',
	];
	foreach ( $kinderen as $sel => $col ) { check( "{$sel} { grid-column: {$col} }", css_has( css_plain_find( $lay, $sel ), "grid-column: {$col}" ) ); }
	$plain_volgorde = array_map( static fn( $r ) => sel_plain( $r['sel'] ), $lay );
	$verwacht_volgorde = array_map( 'compact_css', array_keys( $kinderen ) );
	$pos = array_map( static fn( $x ) => array_search( $x, $plain_volgorde, true ), $verwacht_volgorde );
	check( 'kindregels staan in de volgorde van §4 (> * eerst, baan-klassen erna)', ! in_array( false, $pos, true ) && $pos === array_values( array_unique( $pos ) ) && $pos === ( function ( $a ) { sort( $a ); return $a; } )( $pos ), json_encode( $pos ) );
	check( 'kind-selectors hebben de vorm :where(section:not(.pdk-off)...) > :where(.pdk-...) (alles in :where)', css_plain_find( $lay, 'section:not(.pdk-off) > .pdk-content' ) !== null && css_plain_find( $lay, 'section:not(.pdk-off) > .pdk-full-end' ) !== null && css_plain_find( $lay, 'section:not(.pdk-off) > *' ) !== null && ! array_filter( $lay, static fn( $r ) => str_contains( $r['sel'], 'section' ) && ! str_starts_with( $r['sel'], ':where(section' ) ) );
	// <section hidden>: de section-regel en alle kind-/baanregels sluiten [hidden] uit, zodat het hidden-attribuut (display: none) wint.
	$sec_regels = array_values( array_filter( $lay, static fn( $r ) => str_starts_with( $r['sel'], ':where(section:not(.pdk-off)' ) ) );
	check( 'hidden: de section-regel en alle 10 kindregels (11 regels) bevatten :not([hidden])', 11 === count( $sec_regels ) && ! array_filter( $sec_regels, static fn( $r ) => ! str_contains( $r['sel'], ':not([hidden])' ) ), (string) count( $sec_regels ) );
	// section.pdk-grid: de baanplaatsing (kindregels) geldt er niet, anders krijgen de kaarten grid-column: wide (impliciete kolommen).
	$kind_regels = array_values( array_filter( $sec_regels, static fn( $r ) => str_contains( $r['sel'], '>' ) ) );
	$i_last_kind = max( array_keys( array_filter( $lay, static fn( $r ) => str_starts_with( $r['sel'], ':where(section:not(.pdk-off)' ) && str_contains( $r['sel'], '>' ) ) ) );
	$reset = false;
	foreach ( $lay as $i_r => $r_ ) { if ( str_contains( $r_['sel'], 'pdk-grid' ) && str_contains( $r_['sel'], 'section' ) && str_contains( $r_['sel'], '>' ) && css_has( $r_, 'grid-column: auto' ) && $i_r > $i_last_kind ) { $reset = true; } }
	check( 'section.pdk-grid: de 10 kindregels sluiten :not(.pdk-grid) uit, of een latere reset zet grid-column: auto', 10 === count( $kind_regels ) && ( ! array_filter( $kind_regels, static fn( $r ) => ! str_contains( $r['sel'], ':not(.pdk-grid)' ) ) || $reset ) );
	$grid = css_plain_find( $lay, '.pdk-grid' );
	check( ':where(.pdk-grid): display grid, gap gutter, repeat(auto-fit, minmax(min(var(--pdk-grid-min), 100%), 1fr))',
		css_has( $grid, 'display: grid' ) && css_has( $grid, 'gap: var(--pdk-gutter)' ) && css_has( $grid, 'grid-template-columns: repeat(auto-fit, minmax(min(var(--pdk-grid-min), 100%), 1fr))' ) );

	// Verdwenen: oude breakout-/feature-/max-width-/padding-regels en fysieke namen (V11).
	$bz = preg_replace( '#/\*.*?\*/#s', '', $css );
	foreach ( [ 'pdk-breakout', 'pdk-feature', '--pdk-bo-feature', 'max-inline-size', 'box-sizing', 'padding-inline', 'min-inline-size', 'pdk-full-left', 'pdk-full-right' ] as $weg ) {
		check( "CSS bevat niet: {$weg}", ! str_contains( $bz, $weg ) );
	}
	check( 'V11: geen fysieke eigenschap (left/right/width/margin-top...) in de CSS', ! preg_match( '/(^|[\s;{])(left|right|width|height|margin-(top|bottom|left|right)|padding-(top|bottom|left|right))\s*:/', $bz ), (string) ( preg_match( '/(^|[\s;{])(left|right|width|height|margin-(top|bottom|left|right)|padding-(top|bottom|left|right))\s*:/', $bz, $mm_ ) ? $mm_[0] : '' ) );
	check( 'V11: geen -left/-right in een klassenaam', ! preg_match( '/\.pdk-[a-z-]*-(left|right)\b/', $bz ) );
	check( 'precies de klassen uit §6 komen in de CSS voor (geen wide/content-start/-end)',
		( function () use ( $bz ) {
			preg_match_all( '/\.(pdk-[a-z-]+)/', $bz, $m );
			$gevonden = array_values( array_unique( $m[1] ) ); sort( $gevonden );
			$toegestaan = [ 'pdk-container', 'pdk-content', 'pdk-flow-off', 'pdk-full', 'pdk-full-end', 'pdk-full-start', 'pdk-grid', 'pdk-off', 'pdk-popout', 'pdk-popout-end', 'pdk-popout-start', 'pdk-wide-end', 'pdk-wide-start' ];
			return ! array_diff( $gevonden, $toegestaan ) && ! array_diff( $toegestaan, $gevonden );
		} )(), 'gevonden: ' . ( function () use ( $bz ) { preg_match_all( '/\.(pdk-[a-z-]+)/', $bz, $m ); return implode( ',', array_unique( $m[1] ) ); } )() );

	$i_laatste_basis = max( array_merge( [ -1 ], array_keys( array_filter( $ul, static fn( $r ) => 'p' === $r['sel'] || is_flow_rule( $r ) ) ) ) );
	$i_eerste_layout = min( array_merge( [ PHP_INT_MAX ], array_keys( array_filter( $ul, static fn( $r ) => ! is_base_rule( $r ) && ! is_flow_rule( $r ) ) ) ) );
	check( 'layoutregels komen ná de basisregels en ná de flowregels', $i_eerste_layout > $i_laatste_basis, "{$i_eerste_layout} vs {$i_laatste_basis}" );
	$ul_zf = css_unlayered( PDK_CSS_Framework::generate( array_merge( $DEF, [ 'flow_enabled' => false ] ) ) );
	$ip    = array_search( 'p', array_column( $ul_zf, 'sel' ), true );
	$il    = min( array_merge( [ PHP_INT_MAX ], array_keys( array_filter( $ul_zf, static fn( $r ) => ! is_base_rule( $r ) ) ) ) );
	check( 'ook zonder flow staan de layoutregels ná p', false !== $ip && $il > $ip );

	// ============================================================================
	echo "\nAC-CSS-021 Flow-regels (H)\n";
	// ============================================================================
	$T    = T_LIJST;
	$flow = array_values( array_filter( css_unlayered( $css ), 'is_flow_rule' ) );
	$sels = array_column( $flow, 'sel' );
	check( 'precies vier unlayered flowregels', 4 === count( $flow ), (string) count( $flow ) );
	check( 'in volgorde: T | T + T | T + h2,h3,h4 | grid/flow-off > *',
		$sels === [ compact_css( ":where({$T})" ), compact_css( ":where({$T}) + :where({$T})" ), compact_css( ":where({$T}) + :where(h2, h3, h4)" ), compact_css( ':where(.pdk-grid, .pdk-flow-off) > *' ) ], json_encode( $sels ) );
	check( 'marges: 0 / flow-space / flow-heading-space / 0',
		css_has( $flow[0] ?? null, 'margin-block: 0' ) && css_has( $flow[1] ?? null, 'margin-block-start: var(--pdk-flow-space)' )
		&& css_has( $flow[2] ?? null, 'margin-block-start: var(--pdk-flow-heading-space)' ) && css_has( $flow[3] ?? null, 'margin-block: 0' ) );
	$alle_unl = array_column( css_unlayered( $css ), 'sel' );
	$i_p      = array_search( 'p', $alle_unl, true );
	$i_flow   = array_search( $sels[0] ?? '', $alle_unl, true );
	check( 'flow-regels staan ná de basisregels voor koppen en p', false !== $i_p && false !== $i_flow && $i_flow > $i_p );
	$css_uit = PDK_CSS_Framework::generate( array_merge( $DEF, [ 'flow_enabled' => false ] ) );
	check( 'flow_enabled = false: geen flowregels', ! array_filter( css_unlayered( $css_uit ), 'is_flow_rule' ) );
	check( 'flow_enabled = false: --pdk-flow-* staan er nog in :root', css_has( css_find( css_unlayered( $css_uit ), ':root' ), '--pdk-flow-space: var(--pdk-space-s)' ) && css_has( css_find( css_unlayered( $css_uit ), ':root' ), '--pdk-flow-heading-space: var(--pdk-space-l)' ) );
	check( 'flow_enabled = false: kop-/p-regels en layout blijven', css_has( css_find( css_unlayered( $css_uit ), 'p' ), 'font-size: var(--pdk-step-0)' ) && null !== css_plain_find( css_unlayered( $css_uit ), '.pdk-grid' ) );

	// ============================================================================
	echo "\nAC-CSS-002 / -021 Validatie (save weigert, opgeslagen config blijft)\n";
	// ============================================================================
	reset_world();
	module_aan();
	$eerste_save = PDK_CSS_Framework::save( [] );
	check( 'baseline: lege save slaagt en schrijft het bestand', true === $eerste_save['saved'] && file_exists( css_pad() ) );
	$voor_opt  = serialize( $GLOBALS['options'] );
	$voor_file = md5_file( css_pad() );
	$voor_inhoud = (string) file_get_contents( css_pad() );

	$fout_cases = [
		'min_viewport >= max_viewport (gelijk)' => [ [ 'min_viewport' => 1440 ], 'min_viewport' ],
		'min_viewport > max_viewport'           => [ [ 'min_viewport' => 1500 ], 'min_viewport' ],
		'min_viewport te laag'  => [ [ 'min_viewport' => 199 ], 'min_viewport' ],
		'min_viewport te hoog'  => [ [ 'min_viewport' => 2001, 'max_viewport' => 3000 ], 'min_viewport' ],
		'max_viewport te laag'  => [ [ 'min_viewport' => 200, 'max_viewport' => 199 ], 'max_viewport' ],
		'max_viewport te hoog'  => [ [ 'max_viewport' => 3841 ], 'max_viewport' ],
		'font_min te laag'  => [ [ 'font_min' => 7.9 ], 'font_min' ],
		'font_min te hoog'  => [ [ 'font_min' => 64.1 ], 'font_min' ],
		'font_max te laag'  => [ [ 'font_max' => 7.9 ], 'font_max' ],
		'font_max te hoog'  => [ [ 'font_max' => 64.1 ], 'font_max' ],
		'ratio_min = 0.9'   => [ [ 'ratio_min' => 0.9 ], 'ratio_min' ],
		'ratio_min te hoog' => [ [ 'ratio_min' => 2.01 ], 'ratio_min' ],
		'ratio_max te laag' => [ [ 'ratio_max' => 0.99 ], 'ratio_max' ],
		'ratio_max te hoog' => [ [ 'ratio_max' => 2.01 ], 'ratio_max' ],
		'steps_up negatief' => [ [ 'steps_up' => -1 ], 'steps_up' ],
		'steps_up te hoog'  => [ [ 'steps_up' => 9 ], 'steps_up' ],
		'steps_down negatief' => [ [ 'steps_down' => -1 ], 'steps_down' ],
		'steps_down te hoog'  => [ [ 'steps_down' => 5 ], 'steps_down' ],
		'leading_body negatief' => [ [ 'leading_body' => -0.1 ], 'leading_body' ],
		'leading_body te hoog'  => [ [ 'leading_body' => 2.1 ], 'leading_body' ],
		'leading_heading negatief' => [ [ 'leading_heading' => -0.1 ], 'leading_heading' ],
		'leading_heading te hoog'  => [ [ 'leading_heading' => 2.1 ], 'leading_heading' ],
		'lh_heading_ratio 0.99'    => [ [ 'lh_heading_ratio' => 0.99 ], 'lh_heading_ratio' ],
		'lh_heading_ratio 2.01'    => [ [ 'lh_heading_ratio' => 2.01 ], 'lh_heading_ratio' ],
		'lh_heading_ratio 1.125 (3 decimalen)' => [ [ 'lh_heading_ratio' => 1.125 ], 'lh_heading_ratio' ],
		'lh_heading_ratio "abc"'    => [ [ 'lh_heading_ratio' => 'abc' ], 'lh_heading_ratio' ],
		'lh_heading_ratio NAN'     => [ [ 'lh_heading_ratio' => 'NAN' ], 'lh_heading_ratio' ],
		'lh_heading_ratio array'   => [ [ 'lh_heading_ratio' => [ 1.1 ] ], 'lh_heading_ratio' ],
		'lh_body_ratio 0.99'       => [ [ 'lh_body_ratio' => 0.99 ], 'lh_body_ratio' ],
		'lh_body_ratio 2.01'       => [ [ 'lh_body_ratio' => 2.01 ], 'lh_body_ratio' ],
		'lh_body_ratio 1.125 (3 decimalen)' => [ [ 'lh_body_ratio' => '1.125' ], 'lh_body_ratio' ],
		'lh_body_ratio "abc"'       => [ [ 'lh_body_ratio' => 'abc' ], 'lh_body_ratio' ],
		'lh_body_ratio CSS-injectie' => [ [ 'lh_body_ratio' => '1.3;}body{x:y' ], 'lh_body_ratio' ],
		'site_width (rem) te laag' => [ [ 'site_width' => 19.9 ], 'site_width' ],
		'site_width (rem) te hoog' => [ [ 'site_width' => 240.1 ], 'site_width' ],
		'site_width = 1440 (oude px-waarde)' => [ [ 'site_width' => 1440 ], 'site_width' ],
		'site_width NAN' => [ [ 'site_width' => 'NAN' ], 'site_width' ],
		'measure te laag' => [ [ 'measure' => 19.9 ], 'measure' ],
		'measure te hoog' => [ [ 'measure' => 120.1 ], 'measure' ],
		'measure met 3 decimalen' => [ [ 'measure' => '65.123' ], 'measure' ],
		'measure = 65.125' => [ [ 'measure' => 65.125 ], 'measure' ],
		'measure = 121' => [ [ 'measure' => 121 ], 'measure' ],
		'site_width = 240.5' => [ [ 'site_width' => 240.5 ], 'site_width' ],
		'measure CSS-injectie' => [ [ 'measure' => '65ch;}body{x:y' ], 'measure' ],
		'V14 grid_min te laag (9.99)'   => [ [ 'grid_min' => 9.99 ], 'grid_min' ],
		'V14 grid_min te hoog (80.01)'  => [ [ 'grid_min' => 80.01 ], 'grid_min' ],
		'V14 grid_min 30.125 (3 decimalen)' => [ [ 'grid_min' => 30.125 ], 'grid_min' ],
		'V14 grid_min "abc"'            => [ [ 'grid_min' => 'abc' ], 'grid_min' ],
		'V14 grid_min 9 (te laag)' => [ [ 'grid_min' => 9 ], 'grid_min' ],
		'CSS-injectie in font_min' => [ [ 'font_min' => '1;}body{display:none' ], 'font_min' ],
		'font_min = 1e999 (INF)'   => [ [ 'font_min' => '1e999' ], 'font_min' ],
		'font_min = NAN'           => [ [ 'font_min' => 'NAN' ], 'font_min' ],
		'font_min leeg'            => [ [ 'font_min' => '' ], 'font_min' ],
		'font_min array'           => [ [ 'font_min' => [ 18 ] ], 'font_min' ],
		'max_viewport = "1440.5"'  => [ [ 'max_viewport' => '1440.5' ], 'max_viewport' ],
		'max_viewport = 1440.5'    => [ [ 'max_viewport' => 1440.5 ], 'max_viewport' ],
		'steps_up = 2.5'           => [ [ 'steps_up' => 2.5 ], 'steps_up' ],
		'steps_up = "2.5"'         => [ [ 'steps_up' => '2.5' ], 'steps_up' ],
		'steps_up met spatie'      => [ [ 'steps_up' => ' 3' ], 'steps_up' ],
		'steps_up = "abc"'         => [ [ 'steps_up' => 'abc' ], 'steps_up' ],
		'gutter = huge'            => [ [ 'gutter' => 'huge' ], 'gutter' ],
		'gutter = s-l zonder space_pairs' => [ [ 'gutter' => 's-l', 'space_pairs' => [] ], 'gutter' ],
		'gutter = "<script>"'      => [ [ 'gutter' => '<script>' ], 'gutter' ],
		'popout_space = huge'     => [ [ 'popout_space' => 'huge' ], 'popout_space' ],
		'popout_space = s-l zonder space_pairs' => [ [ 'popout_space' => 's-l', 'space_pairs' => [] ], 'popout_space' ],
		'flow_space = huge'        => [ [ 'flow_space' => 'huge' ], 'flow_space' ],
		'flow_heading_space = s-l zonder space_pairs' => [ [ 'flow_heading_space' => 's-l', 'space_pairs' => [] ], 'flow_heading_space' ],
		'flow_heading_space = huge' => [ [ 'flow_heading_space' => 'huge' ], 'flow_heading_space' ],
		'flow_enabled = "ja"'      => [ [ 'flow_enabled' => 'ja' ], 'flow_enabled' ],
		'flow_enabled = 2'         => [ [ 'flow_enabled' => 2 ], 'flow_enabled' ],
		'flow_enabled = "true"'    => [ [ 'flow_enabled' => 'true' ], 'flow_enabled' ],
		"space_pairs ['s-l','l-s'] (verkeerde volgorde)" => [ [ 'space_pairs' => [ 's-l', 'l-s' ] ], 'space_pairs' ],
		"space_pairs ['x-y'] (onbekende naam)"           => [ [ 'space_pairs' => [ 'x-y' ] ], 'space_pairs' ],
		"space_pairs ['<script>']"                       => [ [ 'space_pairs' => [ '<script>' ] ], 'space_pairs' ],
		'space_pairs = gelijke stap ["s-s"]'             => [ [ 'space_pairs' => [ 's-s' ] ], 'space_pairs' ],
		'space_pairs ["s-l\n"] (afsluitende newline, regex zonder /D)' => [ [ 'space_pairs' => [ "s-l\n" ] ], 'space_pairs' ],
		'gutter = "s\n" (afsluitende newline)' => [ [ 'gutter' => "s\n" ], 'gutter' ],
		'space_pairs: 11 entries'                        => [ [ 'space_pairs' => [ '3xs-xs', '3xs-s', '3xs-m', '3xs-l', '3xs-xl', '3xs-2xl', '3xs-3xl', '2xs-s', '2xs-m', '2xs-l', '2xs-xl' ] ], 'space_pairs' ],
	];
	foreach ( $fout_cases as $label => [ $input, $veld ] ) {
		try {
			$r  = PDK_CSS_Framework::save( $input );
			$ok = false === $r['saved'] && isset( $r['errors'][ $veld ] ) && serialize( $GLOBALS['options'] ) === $voor_opt && md5_file( css_pad() ) === $voor_file;
			$extra = 'saved=' . var_export( $r['saved'], true ) . ' errors=' . json_encode( $r['errors'] );
		} catch ( \Throwable $e ) {
			$ok = false; $extra = 'gooit ' . get_class( $e ) . ': ' . $e->getMessage();
		}
		check( "geweigerd met veldnaam {$veld}: {$label}", $ok, $extra );
		$GLOBALS['options'] = unserialize( $voor_opt ); file_put_contents( css_pad(), $voor_inhoud ); // een ten onrechte geslaagde save mag de volgende cases niet vervuilen
	}
	try { $vn = PDK_CSS_Framework::validate( [ 'space_pairs' => [ "s-l\n" ] ], $DEF ); $okn = isset( $vn['errors']['space_pairs'] ); } catch ( \Throwable $e ) { $okn = false; }
	check( 'validate() weigert "s-l\n" zelf (geen doorgifte aan tokens())', $okn );
	$v = PDK_CSS_Framework::validate( [ 'font_min' => 'NAN', 'steps_up' => 2.5 ], $DEF );
	check( 'validate() meldt meerdere velden tegelijk (per veld een fout)', isset( $v['errors']['font_min'], $v['errors']['steps_up'] ) );
	check( 'validate() schrijft niets weg', serialize( $GLOBALS['options'] ) === $voor_opt );
	check( 'oude gutter-token geweigerd na verwijderen paar: stale gutter s-l wordt gevangen',
		( function () use ( $DEF ) {
			$r = PDK_CSS_Framework::save( [ 'gutter' => 's-l' ] );          // s-l bestaat in de default space_pairs
			$r2 = PDK_CSS_Framework::save( [ 'space_pairs' => [] ] );        // nu bestaat s-l niet meer, gutter staat er nog op
			return true === $r['saved'] && false === $r2['saved'] && isset( $r2['errors']['gutter'] );
		} )() );
	// Terug naar een schone staat voor de succesgevallen.
	reset_world();
	module_aan();

	echo "\nAC-CSS-002 Succesgevallen en normalisatie\n";
	$r = PDK_CSS_Framework::save( [ 'space_pairs' => [ 'm-l', 's-l', 's-l' ] ] );
	check( "space_pairs ['m-l','s-l','s-l'] slaagt en wordt ['s-l'] (one-up en dubbel weggelaten)", true === $r['saved'] && [ 's-l' ] === $r['config']['space_pairs'] && [ 's-l' ] === opgeslagen()['space_pairs'], json_encode( $r ) );
	$r = PDK_CSS_Framework::save( [ 'font_min' => '18', 'font_max' => '20', 'ratio_min' => '1.2', 'steps_up' => '5', 'min_viewport' => '400', 'grid_min' => '30' ] );
	check( 'numerieke strings worden als getal geaccepteerd', true === $r['saved'] && ongeveer( $r['config']['font_min'], 18 ) && 5 === $r['config']['steps_up'] && 400 === $r['config']['min_viewport'] && ongeveer( $r['config']['ratio_min'], 1.2 ), json_encode( $r ) );
	check( 'opgeslagen waarden zijn getallen, geen strings', is_int( opgeslagen()['steps_up'] ) && ( is_int( opgeslagen()['font_min'] ) || is_float( opgeslagen()['font_min'] ) ) );
	$r = PDK_CSS_Framework::save( [ 'evil' => 'x', 'unknown' => [ 1 ], 'steps_up' => 4 ] );
	check( 'onbekende sleutels vallen weg (resultaat en opslag)', true === $r['saved'] && ! isset( $r['config']['evil'] ) && ! isset( opgeslagen()['evil'] ) && ! isset( opgeslagen()['unknown'] ) && 4 === opgeslagen()['steps_up'] );
	PDK_CSS_Framework::save( [ 'max_viewport' => 1600, 'flow_heading_space' => 'xl' ] );
	PDK_CSS_Framework::save( [ 'steps_up' => 4 ] );
	check( 'partiële save: ontbrekende sleutels houden hun (niet-default) waarde', 1600 === opgeslagen()['max_viewport'] && 'xl' === opgeslagen()['flow_heading_space'] );
	PDK_CSS_Framework::save( [ 'max_viewport' => 1440, 'flow_heading_space' => 'l' ] );
	check( 'save raakt "enabled" niet (module blijft aan)', true === ( opgeslagen()['enabled'] ?? null ) && PDK_Settings::is_module_enabled( 'css_framework' ) );
	foreach ( [ 'min_viewport' => 200, 'max_viewport' => 3840, 'font_min' => 8, 'font_max' => 64, 'ratio_min' => 1.0, 'ratio_max' => 2.0, 'steps_up' => 8, 'steps_down' => 4, 'leading_body' => 0, 'leading_heading' => 2, 'site_width' => 20, 'grid_min' => 80 ] as $veld => $grens ) {
		$r = PDK_CSS_Framework::save( [ 'min_viewport' => 200, 'max_viewport' => 3840, $veld => $grens ] );
		check( "grenswaarde geldig: {$veld} = {$grens}", true === $r['saved'], json_encode( $r['errors'] ) );
	}
	foreach ( [ [ 'site_width', 240 ], [ 'measure', 20 ], [ 'measure', 120 ] ] as [ $veld, $grens ] ) {
		$r = PDK_CSS_Framework::save( [ $veld => $grens ] );
		check( "grenswaarde geldig: {$veld} = {$grens}", true === $r['saved'], json_encode( $r['errors'] ) );
	}
	$r = PDK_CSS_Framework::save( [ 'measure' => '65.12', 'site_width' => '90' ] );
	check( 'measure met 2 decimalen en site_width als string worden geaccepteerd', true === $r['saved'] && ongeveer( $r['config']['measure'], 65.12 ) && ongeveer( $r['config']['site_width'], 90 ) );
	$r = PDK_CSS_Framework::save( [ 'measure' => 65.25 ] );
	check( 'measure = 65.25 en 65.5 zijn geldig (2 decimalen)', true === $r['saved'] && true === PDK_CSS_Framework::save( [ 'measure' => 65.5 ] )['saved'] );
	foreach ( [ 'lh_heading_ratio', 'lh_body_ratio' ] as $veld ) {
		foreach ( [ 1.0, 1.15, '1.15', 2.0 ] as $w ) {
			$r = PDK_CSS_Framework::save( [ $veld => $w ] );
			check( "V13: {$veld} = " . var_export( $w, true ) . ' geldig en opgeslagen als getal', true === $r['saved'] && ongeveer( opgeslagen()[ $veld ] ?? null, (float) $w ), json_encode( $r['errors'] ) );
		}
	}
	PDK_CSS_Framework::save( [ 'lh_heading_ratio' => 1.1, 'lh_body_ratio' => 1.3 ] );
	foreach ( [ 10, 80, '25.5', 30.25 ] as $w ) {
		$r = PDK_CSS_Framework::save( [ 'grid_min' => $w ] );
		check( 'V14: grid_min = ' . var_export( $w, true ) . ' geldig', true === $r['saved'] && ongeveer( opgeslagen()['grid_min'] ?? null, (float) $w ), json_encode( $r['errors'] ) );
	}
	PDK_CSS_Framework::save( [ 'grid_min' => 25.5 ] );
	$gt = array_column( PDK_CSS_Framework::tokens( PDK_CSS_Framework::config() ), 'value', 'name' );
	check( 'V14: grid_min 25.5 geeft --pdk-grid-min: 25.5ch', '25.5ch' === ( $gt['--pdk-grid-min'] ?? null ), (string) ( $gt['--pdk-grid-min'] ?? '' ) );
	check( 'V14: bestand bevat --pdk-grid-min: 25.5ch', str_contains( (string) @file_get_contents( css_pad() ), '--pdk-grid-min: 25.5ch;' ) );
	PDK_CSS_Framework::save( [ 'grid_min' => 30 ] );
	$r = PDK_CSS_Framework::save( [ 'feature_space' => 's' ] );
	check( 'oude sleutel feature_space valt weg als onbekend', true === $r['saved'] && ! isset( opgeslagen()['feature_space'] ) );
	foreach ( [ true, false, 1, 0, '1', '0' ] as $b ) {
		$r = PDK_CSS_Framework::save( [ 'flow_enabled' => $b ] );
		check( 'flow_enabled = ' . var_export( $b, true ) . ' geldig en genormaliseerd naar bool', true === $r['saved'] && is_bool( $r['config']['flow_enabled'] ) && $r['config']['flow_enabled'] === (bool) (int) $b );
	}
	$r = PDK_CSS_Framework::save( [ 'font_min' => '1e1' ] );
	check( 'font_min = "1e1" (is_numeric) wordt 10', true === $r['saved'] && ongeveer( $r['config']['font_min'], 10 ) );
	$r = PDK_CSS_Framework::save( [ 'font_min' => 18, 'gutter' => 's-l', 'space_pairs' => [ 's-l' ] ] );
	check( 'gutter = s-l mag zodra s-l in space_pairs staat', true === $r['saved'] && 's-l' === $r['config']['gutter'] );
	$r = PDK_CSS_Framework::save( [ 'gutter' => 's-m', 'space_pairs' => [ 's-l', 'xs-xl' ] ] );
	$r = PDK_CSS_Framework::save( [ 'space_pairs' => [ 's-l' ] ] );
	check( 'verkorte lijst space_pairs vervangt de oude (geen recursieve merge per index)', true === $r['saved'] && [ 's-l' ] === opgeslagen()['space_pairs'], json_encode( opgeslagen()['space_pairs'] ?? null ) );
	$r = PDK_CSS_Framework::save( [ 'space_pairs' => [] ] );
	check( 'lege space_pairs wordt opgeslagen als []', true === $r['saved'] && [] === opgeslagen()['space_pairs'] );

	// ============================================================================
	echo "\nAC-CSS-008 Bestand schrijven\n";
	// ============================================================================
	reset_world();
	module_aan();
	$GLOBALS['caps'] = [ 'manage_options' ];   // wel manage_options, geen pdk_edit_custom_code
	check( 'testgebruiker heeft geen code-cap', ! current_user_can( PDK_CAP_EDIT_CODE ) && pdk_current_user_can_edit_code() === false );
	$r = PDK_CSS_Framework::save( [ 'steps_up' => 4 ] );
	$cfg = PDK_CSS_Framework::config();
	check( 'save zonder code-cap slaagt', true === $r['saved'] );
	check( 'bestand = exact de generator-uitvoer van de opgeslagen config', file_exists( css_pad() ) && file_get_contents( css_pad() ) === PDK_CSS_Framework::generate( $cfg ) );
	check( 'steps_up 4 is in het bestand terug te vinden', ! str_contains( (string) file_get_contents( css_pad() ), '--pdk-step-5:' ) && str_contains( (string) file_get_contents( css_pad() ), '--pdk-step-4:' ) );
	PDK_CSS_Framework::save( [ 'steps_up' => 3 ] );
	check( 'geen .bak-bestand (ook niet na tweede save)', ! file_exists( css_pad() . '.bak' ) && ! glob( PDK_STORAGE_DIR . '*.bak' ) );
	check( 'geen hash in pdk_file_hashes', ! isset( ( (array) get_option( 'pdk_file_hashes', [] ) )['css-framework.css'] ) );
	check( 'css-framework.css staat niet in pdk_watched_files()', ! in_array( 'css-framework.css', pdk_watched_files(), true ) );
	check( 'integriteitscontrole ziet het bestand niet als gemanipuleerd', false === pdk_file_is_tampered( 'css-framework.css' ) );
	check( 'write_file() schrijft direct dezelfde inhoud', ( function () { $c = PDK_CSS_Framework::config(); unlink( css_pad() ); return PDK_CSS_Framework::write_file( $c ) && file_get_contents( css_pad() ) === PDK_CSS_Framework::generate( $c ); } )() );
	$md5 = md5_file( css_pad() );
	$bad = PDK_CSS_Framework::save( [ 'steps_up' => 99 ] );
	check( 'ongeldige save laat het bestand byte-gelijk', false === $bad['saved'] && md5_file( css_pad() ) === $md5 );
	check( 'ongeldige save laat de opgeslagen config ongewijzigd', 3 === opgeslagen()['steps_up'] );
	$bad2 = PDK_CSS_Framework::save( [ 'font_min' => '1;}body{display:none' ] );
	check( 'geïnjecteerde tekst komt nooit in het bestand', false === $bad2['saved'] && ! str_contains( (string) file_get_contents( css_pad() ), 'display:none' ) );

	// ============================================================================
	echo "\nAC-CSS-008 / -009 Registreren en enqueuen\n";
	// ============================================================================
	reset_world();
	module_aan();
	$loader = new PDK_Loader();
	$mod    = new PDK_CSS_Framework( $loader );
	check( 'hook wp_enqueue_scripts -> register op prioriteit 9', in_array( [ 'wp_enqueue_scripts', 'register', 9 ], $loader->hooks, true ), json_encode( $loader->hooks ) );
	check( 'hook wp_enqueue_scripts -> enqueue op prioriteit 20', in_array( [ 'wp_enqueue_scripts', 'enqueue', 20 ], $loader->hooks, true ), json_encode( $loader->hooks ) );
	check( 'bestand ontbreekt vóór register()', ! file_exists( css_pad() ) );
	$mod->register();
	check( 'register() regenereert het ontbrekende bestand (config uit opslag/defaults)', file_exists( css_pad() ) && file_get_contents( css_pad() ) === PDK_CSS_Framework::generate( PDK_CSS_Framework::config() ) );
	$reg = $GLOBALS['registered']['pdk-css-framework'] ?? null;
	check( 'handle pdk-css-framework geregistreerd met URL, geen deps, versie = filemtime',
		null !== $reg && PDK_STORAGE_URL . 'css-framework.css' === $reg['src'] && [] === $reg['deps'] && filemtime( css_pad() ) === (int) $reg['ver'], json_encode( $reg ) );
	check( 'register() enqueuet nog niet', ! isset( $GLOBALS['enqueued']['pdk-css-framework'] ) );
	$mod->enqueue();
	check( 'enqueue() zet pdk-css-framework in de wachtrij', isset( $GLOBALS['enqueued']['pdk-css-framework'] ) );

	file_put_contents( css_pad(), '/* handmatig */' );
	$GLOBALS['registered'] = [];
	$mod->register();
	check( 'bestaand bestand wordt niet bij elk request opnieuw gegenereerd', '/* handmatig */' === file_get_contents( css_pad() ) && isset( $GLOBALS['registered']['pdk-css-framework'] ) );

	// Custom CSS-dependency (B1).
	file_put_contents( PDK_STORAGE_DIR . 'custom-style.css', 'h1{font-size:10px}' );
	$cc = new PDK_Custom_CSS( new PDK_Loader() );
	$GLOBALS['enqueued'] = [];
	$GLOBALS['registered'] = [ 'pdk-css-framework' => [ 'src' => 'x', 'deps' => [], 'ver' => 1 ] ];
	$cc->enqueue();
	check( 'pdk-custom-style heeft deps [pdk-css-framework] als die geregistreerd is', [ 'pdk-css-framework' ] === ( $GLOBALS['enqueued']['pdk-custom-style']['deps'] ?? null ), json_encode( $GLOBALS['enqueued'] ) );
	$GLOBALS['enqueued'] = [];
	$GLOBALS['registered'] = [];
	$cc->enqueue();
	check( 'pdk-custom-style heeft deps [] als de handle niet geregistreerd is (module uit)', [] === ( $GLOBALS['enqueued']['pdk-custom-style']['deps'] ?? null ) && isset( $GLOBALS['enqueued']['pdk-custom-style'] ) );

	// ============================================================================
	echo "\nAC-CSS-010 (H-deel) Admin: opslaan via handle_save()\n";
	// ============================================================================
	function form( array $over = [] ): array {
		global $DEF;
		$f = [];
		foreach ( $DEF as $k => $v ) {
			if ( 'enabled' === $k ) { continue; }
			$f[ $k ] = is_array( $v ) ? $v : ( is_bool( $v ) ? ( $v ? '1' : '' ) : (string) $v );
		}
		foreach ( $over as $k => $v ) { if ( null === $v ) { unset( $f[ $k ] ); } else { $f[ $k ] = $v; } }
		return $f;
	}
	function post_en_redirect( array $velden ): string {
		$_POST = [ 'pdk_tab' => 'css_framework', 'css_framework' => $velden ];
		try {
			( new PDK_Admin( new PDK_Loader() ) )->handle_save();
		} catch ( PDK_Test_Redirect $e ) {
			return rawurldecode( rawurldecode( $e->url ) );
		}
		return '';
	}
	reset_world();
	module_aan();
	$url = post_en_redirect( form( [ 'steps_up' => '4', 'flow_enabled' => null ] ) );
	check( 'geldige save: redirect naar tab=css_framework met saved=1', str_contains( $url, 'tab=css_framework' ) && str_contains( $url, 'saved=1' ) && ! str_contains( $url, 'error=' ), $url );
	check( 'geldige save: getal opgeslagen, bestand geschreven', 4 === ( opgeslagen()['steps_up'] ?? null ) && file_exists( css_pad() ) );
	check( 'ontbrekend vinkje flow_enabled = false (opgeslagen en geen flow-regels in bestand)', false === ( opgeslagen()['flow_enabled'] ?? null ) && ! array_filter( css_unlayered( (string) file_get_contents( css_pad() ) ), 'is_flow_rule' ) );
	check( 'module blijft aan na admin-save', true === ( opgeslagen()['enabled'] ?? null ) );
	$md5 = md5_file( css_pad() );
	$voor = serialize( $GLOBALS['options'] );
	$url = post_en_redirect( form( [ 'min_viewport' => '3000', 'max_viewport' => '1440' ] ) );
	check( 'ongeldige save: redirect met error= en de veldnaam, zonder saved=1', str_contains( $url, 'error=' ) && str_contains( $url, 'min_viewport' ) && ! str_contains( $url, 'saved=1' ), $url );
	check( 'ongeldige save: opslag en bestand ongewijzigd', serialize( $GLOBALS['options'] ) === $voor && md5_file( css_pad() ) === $md5 );
	$url = post_en_redirect( form( [ 'font_min' => '1;}body{display:none' ] ) );
	check( 'CSS-injectie via formulier geweigerd met veldnaam font_min', str_contains( $url, 'error=' ) && str_contains( $url, 'font_min' ) && md5_file( css_pad() ) === $md5 );
	$GLOBALS['caps'] = [];
	$_POST = [ 'pdk_tab' => 'css_framework', 'css_framework' => form() ];
	$geweigerd = false;
	try { ( new PDK_Admin( new PDK_Loader() ) )->handle_save(); } catch ( RuntimeException $e ) { $geweigerd = str_starts_with( $e->getMessage(), 'wp_die' ); } catch ( PDK_Test_Redirect $e ) { }
	check( 'zonder manage_options wordt de save geweigerd', $geweigerd && md5_file( css_pad() ) === $md5 );
	$GLOBALS['caps'] = [ 'manage_options' ];

	// ============================================================================
	echo "\nAC-CSS-023 (H-deel) Modern Normalize-melding op de tab\n";
	// ============================================================================
	$render = $ref_admin->getMethod( 'render_tab_content' );
	$tab = static function () use ( $render ): string {
		$a = new PDK_Admin( new PDK_Loader() );
		ob_start();
		try { $render->invoke( $a, 'css_framework' ); } finally { $html = (string) ob_get_clean(); }
		return $html;
	};
	reset_world();
	module_aan();
	PDK_CSS_Framework::save( [] );

	set_oxygen( true ); $GLOBALS['bd_modern'] = false; $GLOBALS['bd_calls'] = []; $writes = $GLOBALS['opt_writes'];
	$html = $tab();
	check( 'tab rendert zonder fatale fout en is niet leeg', '' !== trim( $html ) );
	check( 'Oxygen actief + Modern Normalize uit: notice-warning aanwezig', str_contains( $html, 'notice-warning' ) );
	check( 'melding noemt "Modern Normalize" en het pad "Oxygen → Settings → Advanced"', str_contains( $html, 'Modern Normalize' ) && str_contains( $html, 'Oxygen → Settings → Advanced' ) );
	check( 'de check las de setting modern_normalize_enabled', in_array( [ 'get_bool_setting', 'modern_normalize_enabled' ], $GLOBALS['bd_calls'], true ) );
	check( 'render roept nooit een schrijvende Oxygen-functie aan', ! array_filter( $GLOBALS['bd_calls'], static fn( $c ) => 'saveVariables' === $c[0] ) );
	check( 'render schrijft geen opties', $writes === $GLOBALS['opt_writes'] );

	$GLOBALS['bd_modern'] = true; $GLOBALS['bd_calls'] = [];
	$html = $tab();
	check( 'Oxygen actief + Modern Normalize aan: geen notice-warning', ! str_contains( $html, 'notice-warning' ) && '' !== trim( $html ) );
	$GLOBALS['bd_modern'] = 'throw';
	try { $html = $tab(); $fatal = false; } catch ( \Throwable $e ) { $html = ''; $fatal = true; }
	check( 'InvalidArgumentException uit get_bool_setting: geen fatale fout', ! $fatal );
	check( 'InvalidArgumentException: geen melding', ! str_contains( $html, 'notice-warning' ) && '' !== trim( $html ) );
	set_oxygen( false ); $GLOBALS['bd_modern'] = false;
	$html = $tab();
	check( 'Oxygen inactief (setting zou false geven): geen melding', ! str_contains( $html, 'notice-warning' ) && '' !== trim( $html ) );
	check( 'nooit saveVariables aangeroepen in alle gevallen', ! array_filter( $GLOBALS['bd_calls'], static fn( $c ) => 'saveVariables' === $c[0] ) );

	// ============================================================================
	echo "\nAC-CSS-019 (H-deel) uitweg-snippet op de tab\n";
	// ============================================================================
	reset_world(); module_aan(); PDK_CSS_Framework::save( [] );
	$html = $tab();
	preg_match( '#<pre>(.*?)</pre>#s', $html, $pm );
	$snip = $pm[1] ?? '';
	check( 'snippet noemt .woocommerce section met display: block, container-type: normal en font-size: inherit',
		str_contains( $snip, '.woocommerce section' ) && str_contains( $snip, 'display: block;' ) && str_contains( $snip, 'container-type: normal;' ) && str_contains( $snip, 'font-size: inherit;' ), $snip );

	// ============================================================================
	echo "\nAC-CSS-010 (H-deel) measure-hint\n";
	// ============================================================================
	preg_match( '#id="cssfw-measure".*?<span class="description">(.*?)</span>#s', $html, $mm2 );
	$hint = $mm2[1] ?? '';
	check( 'hint bij measure noemt de basisgrootte van de section (step-0 of basisgrootte)', (bool) preg_match( '/step-0|basisgrootte/i', $hint ), $hint );

	// ============================================================================
	echo "\nD-P2 Regenereren bij een andere pluginversie\n";
	// ============================================================================
	reset_world(); module_aan();
	$mod = new PDK_CSS_Framework( new PDK_Loader() );
	file_put_contents( css_pad(), '/* oud */' );
	update_option( 'pdk_css_framework_built', '0.0.1' );
	$mod->register();
	check( 'bestand aanwezig, versie ongelijk: register() regenereert', file_get_contents( css_pad() ) === PDK_CSS_Framework::generate( PDK_CSS_Framework::config() ) );
	check( 'en slaat pdk_css_framework_built = PDK_PLUGIN_VERSION op', PDK_PLUGIN_VERSION === get_option( 'pdk_css_framework_built' ) );
	file_put_contents( css_pad(), '/* nieuw */' ); $w = $GLOBALS['opt_writes'];
	$mod->register();
	check( 'versie gelijk en bestand aanwezig: niet opnieuw schrijven en geen optie-write', '/* nieuw */' === file_get_contents( css_pad() ) && $w === $GLOBALS['opt_writes'] );
	update_option( 'pdk_css_framework_built', false ); $GLOBALS['options'] = array_diff_key( $GLOBALS['options'], [ 'pdk_css_framework_built' => 1 ] );
	$mod->register();
	check( 'versie nooit opgeslagen (bestaande installatie): regenereert ook', '/* nieuw */' !== file_get_contents( css_pad() ) && PDK_PLUGIN_VERSION === get_option( 'pdk_css_framework_built' ) );

	// ============================================================================
	echo "\nconfig() valt per veld terug op de default bij een oude/corrupte opgeslagen waarde\n";
	// ============================================================================
	// Vastgelegd gedrag: ongeldige velden krijgen de default, geldige opgeslagen velden blijven.
	reset_world();
	module_aan( [ 'site_width' => 1440, 'space_pairs' => 'x', 'gutter' => 'red;}', 'font_min' => 20 ] );
	try {
		$c = PDK_CSS_Framework::config();
		check( 'config(): site_width 1440 -> default 90', ongeveer( $c['site_width'], 90 ) );
		check( 'config(): space_pairs "x" -> default [s-l]', [ 's-l' ] === $c['space_pairs'] );
		check( 'config(): gutter "red;}" -> default s-m', 's-m' === $c['gutter'] );
		check( 'config(): geldige opgeslagen font_min 20 blijft', ongeveer( $c['font_min'], 20 ) );
		$bad = false;
	} catch ( \Throwable $e ) { check( 'config() gooit niet', false, $e->getMessage() ); $bad = true; }
	try {
		( new PDK_CSS_Framework( new PDK_Loader() ) )->register();
		$f = (string) @file_get_contents( css_pad() );
		check( 'register() met corrupte config: geldig bestand zonder rommel', str_contains( $f, '--pdk-site-width: 90rem;' ) && ! str_contains( $f, 'red;}' ) && ! str_contains( $f, '1440rem' ) );
	} catch ( \Throwable $e ) { check( 'register() gooit niet', false, get_class( $e ) . ': ' . $e->getMessage() ); }
	try {
		$GLOBALS['bd_modern'] = false; $h = $tab();
		check( 'render_inline() met corrupte config: geen fout, geen rommel, site_width 90', ! str_contains( $h, 'red;}' ) && str_contains( $h, 'name="css_framework[site_width]"' ) && str_contains( $h, 'value="90"' ) );
	} catch ( \Throwable $e ) { check( 'render_inline() gooit niet', false, get_class( $e ) . ': ' . $e->getMessage() ); }

	// Opruimen.
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $f ) {
		$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
	}
	rmdir( $tmp );

	echo "\n" . ( $fouten ? "{$fouten} test(s) gefaald\n" : "Alle tests geslaagd\n" );
	exit( $fouten ? 1 : 0 );
}
