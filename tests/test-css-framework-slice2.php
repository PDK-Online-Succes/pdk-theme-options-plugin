<?php
/**
 * Zelftest Slice 2 van de module CSS Framework (AC-CSS-011 t/m -015, release-check; H-delen).
 * Normatief: docs/css-framework-brief.md §5-§7. Draaien met:  php tests/test-css-framework-slice2.php
 *
 * VASTGELEGDE API (waar de brief het openlaat; backend-dev implementeert exact dit):
 *
 *  PDK_CSS_Framework
 *   static merge_oxygen_variables( array $variables, array $collections, array $tokens ): array{variables:array,collections:array,changed:bool}
 *       pure merge; $tokens = uitvoer van tokens() (alleen name en value tellen). Overschrijft op cssVariableName met behoud
 *       van id (op dezelfde plek), voegt nieuwe aan het eind toe (id = wp_generate_uuid4()), laat de rest ongemoeid.
 *       Variabele: [id, cssVariableName (zonder --), label ('PDK ' . naam zonder pdk- met spaties: 'PDK step 0'), type 'unit',
 *       value [number=>V, unit=>'custom', style=>V], collection 'PDK Framework']; geen dynamicData. Collecties zijn strings.
 *   static sync_oxygen( array $config ): array{status:'synced'|'unchanged'|'skipped'|'error', message?:string}
 *       leest RUW via \Breakdance\Data\global_option_exists()/get_global_option( 'variables_json_string' | 'variables_collections_json_string' )
 *       (nooit getVariables()), doet guards + de vier stappen uit §5 + try/catch \Throwable, roept dan \Breakdance\Variables\saveVariables( $json ).
 *       'skipped' heeft message "Oxygen 6 niet actief — alleen het CSS-bestand bijgewerkt".
 *   static save( array $input ): zoals slice 1, plus bij saved === true de sleutel 'oxygen' => sync_oxygen()-resultaat
 *       (save() roept sync_oxygen() NA opslaan en schrijven; bij saved === false geen sync en geen 'oxygen').
 *   register(): regenereert ook als get_option( 'pdk_css_framework_built' ) !== PDK_PLUGIN_VERSION en slaat die dan op (update_option).
 *   config(): per veld gevalideerd; een ongeldig opgeslagen veld valt terug op zijn default, geldige blijven.
 *
 *  PDK_Admin: handle_save() bij sync-status 'error': redirect met saved=1 EN warning=rawurlencode("Instellingen opgeslagen, Oxygen-sync mislukt: <message>");
 *   geen warning bij synced/unchanged/skipped. render_notices() toont $_GET['warning'] (rawurldecode, esc_html) als notice-warning.
 *
 *  PDK_Agent_Abilities: register_abilities() registreert 'pdk-theme-options/get-css-framework' en '.../update-css-framework' alleen als
 *   class_exists( 'PDK_CSS_Framework' ). Callbacks (via execute_callback, namen vrij): get -> [config, tokens[name,value], classes(13), css_url,
 *   oxygen[active, modern_normalize|null]]; update( ['config'=>[...]] ) -> [saved, config, tokens, oxygen[status,message?]] of
 *   WP_Error 'pdk_invalid_config' met get_error_data() === [ 'errors' => [ veld => melding ] ] (zelfde validate() als de admin).
 *
 * De Oxygen-aanwezigheid (functie ontbreekt / klasse ontbreekt) wordt in een kindproces getest (env PDK_T_OMIT=save|get|class).
 */

namespace Breakdance\Data {
	if ( 'get' !== getenv( 'PDK_T_OMIT' ) ) {
		function global_option_exists( $k ) { $GLOBALS['bd_calls'][] = [ 'global_option_exists', $k ]; return array_key_exists( $k, $GLOBALS['bd_opts'] ); }
		function get_global_option( $k, $a = false ) { $GLOBALS['bd_calls'][] = [ 'get_global_option', $k ]; return $GLOBALS['bd_opts'][ $k ] ?? false; }
	}
}

namespace Breakdance\Settings {
	function get_bool_setting( string $key ): bool {
		if ( 'throw' === ( $GLOBALS['bd_modern'] ?? null ) ) { throw new \InvalidArgumentException( 'geen bool' ); }
		return (bool) ( $GLOBALS['bd_modern'] ?? false );
	}
}

namespace Breakdance\Variables {
	/** Gefilterde variant: mag nooit gebruikt worden voor de merge. */
	function getVariables() { $GLOBALS['bd_calls'][] = [ 'getVariables' ]; return [ [ 'id' => 'flt', 'cssVariableName' => 'filtered-only', 'label' => 'F', 'type' => 'color', 'value' => '#000', 'collection' => 'X' ] ]; }
	if ( 'save' !== getenv( 'PDK_T_OMIT' ) ) {
		/** Gedraagt zich als Oxygen: geen invoercontrole, str_replace van {}, schrijft de ruwe opties. */
		function saveVariables( $data ) {
			$GLOBALS['bd_calls'][] = [ 'saveVariables', $data ];
			if ( 'exception' === $GLOBALS['bd_save'] ) { throw new \Exception( 'boom-exception' ); }
			if ( 'error' === $GLOBALS['bd_save'] ) { throw new \TypeError( 'boom-type' ); }
			$d = json_decode( str_replace( '{}', 'null', $data ), true );
			$GLOBALS['bd_opts']['variables_collections_json_string'] = $d['collections'];
			$GLOBALS['bd_opts']['variables_json_string']             = $d['variables'];
		}
	}
}

namespace {
	if ( PHP_SAPI !== 'cli' ) {
		exit;
	}

	$pdk  = dirname( __DIR__ ) . '/pdk-theme-options';
	$omit = (string) getenv( 'PDK_T_OMIT' );

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'PDK_PLUGIN_VERSION', '0.0.0-test' );
	define( 'PDK_PLUGIN_DIR', $pdk . '/' );
	define( 'PDK_CAP_EDIT_CODE', 'pdk_edit_custom_code' );
	$tmp = str_replace( '\\', '/', sys_get_temp_dir() ) . '/pdk-cssfw2-test-' . getmypid();
	define( 'PDK_STORAGE_DIR', $tmp . '/uploads/pdk-theme-options/' );
	define( 'PDK_STORAGE_URL', 'https://example.test/uploads/pdk-theme-options/' );
	mkdir( dirname( PDK_STORAGE_DIR ), 0777, true );
	register_shutdown_function( static function () use ( $tmp ) {
		if ( ! is_dir( $tmp ) ) { return; }
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		rmdir( $tmp );
	} );

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
		public function get_error_message() { return $this->message; } public function get_error_data() { return $this->data; }
	}
	class PDK_Loader {
		public array $hooks = [];
		public function add_action( string $hook, $obj, string $method, int $prio = 10, int $args = 1 ): void { $this->hooks[] = [ $hook, $method, $prio ]; }
		public function add_filter( string $hook, $obj, string $method, int $prio = 10, int $args = 1 ): void { $this->hooks[] = [ $hook, $method, $prio ]; }
	}

	$GLOBALS['abilities'] = [];
	$GLOBALS['bd_opts']   = [];
	$GLOBALS['bd_save']   = null;
	$GLOBALS['uuid_n']    = 0;
	function is_wp_error( $t ) { return $t instanceof WP_Error; }
	function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
	function wp_generate_uuid4() { $GLOBALS['bd_calls'][] = [ 'uuid' ]; return sprintf( '00000000-0000-4000-8000-%012d', ++$GLOBALS['uuid_n'] ); }
	function wp_register_ability( string $name, array $args ): void { $GLOBALS['abilities'][ $name ] = $args; }
	function add_shortcode( ...$a ) {}

	require_once $pdk . '/includes/helpers.php';
	require_once $pdk . '/includes/class-pdk-settings.php';
	require_once $pdk . '/includes/class-pdk-plugin.php';
	require_once $pdk . '/includes/class-pdk-admin.php';
	require_once $pdk . '/modules/custom-css/class-pdk-custom-css.php';
	require_once $pdk . '/modules/agent-abilities/class-pdk-agent-abilities.php';
	if ( 'class' !== $omit ) {
		require_once $pdk . '/modules/css-framework/class-pdk-css-framework.php';
	}

	defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
	defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
	$fouten = 0;
	function check( string $naam, bool $ok, string $extra = '' ): void {
		global $fouten;
		if ( ! $ok ) { $fouten++; }
		echo ( $ok ? '  ok   ' : '  FOUT ' ) . $naam . ( ! $ok && $extra ? "\n       " . $extra : '' ) . "\n";
	}
	/** Voert $fn uit; een Throwable telt als falen (nooit een crash van de runner). $fn geeft true of een foutmelding. */
	function safe( string $naam, callable $fn ): void {
		try { $r = $fn(); check( $naam, true === $r, is_string( $r ) ? $r : '' ); }
		catch ( \Throwable $e ) { check( $naam, false, 'gooit ' . get_class( $e ) . ': ' . $e->getMessage() ); }
	}

	function reset_world(): void {
		$GLOBALS['options'] = []; $GLOBALS['opt_writes'] = 0; $GLOBALS['caps'] = [ 'manage_options' ];
		$GLOBALS['registered'] = []; $GLOBALS['enqueued'] = []; $GLOBALS['bd_calls'] = [];
		$GLOBALS['bd_modern'] = false; $GLOBALS['bd_opts'] = []; $GLOBALS['bd_save'] = null; $GLOBALS['uuid_n'] = 0;
		$GLOBALS['abilities'] = []; $_POST = []; $_GET = [];
		foreach ( glob( PDK_STORAGE_DIR . '*' ) ?: [] as $f ) { is_file( $f ) && unlink( $f ); }
		@mkdir( PDK_STORAGE_DIR, 0777, true );
	}
	function module_aan( array $extra = [] ): void { $GLOBALS['options']['pdk_theme_options'] = [ 'css_framework' => array_merge( [ 'enabled' => true ], $extra ), 'agent_abilities' => [ 'enabled' => true ] ]; }
	function set_oxygen( bool $actief ): void { $GLOBALS['options']['active_plugins'] = $actief ? [ 'oxygen/plugin.php' ] : []; }
	function opgeslagen(): array { return (array) ( ( $GLOBALS['options']['pdk_theme_options'] ?? [] )['css_framework'] ?? [] ); }
	function css_pad(): string { return PDK_STORAGE_DIR . 'css-framework.css'; }
	function saves(): array { return array_values( array_filter( $GLOBALS['bd_calls'], static fn( $c ) => 'saveVariables' === $c[0] ) ); }
	function fv( string $name, array $extra = [] ): array { return array_merge( [ 'id' => 'f-' . $name, 'cssVariableName' => $name, 'label' => 'Foreign', 'type' => 'color', 'value' => '#fff', 'collection' => 'Brand' ], $extra ); }
	function find_var( array $vars, string $name ): ?array { foreach ( $vars as $v ) { if ( ( $v['cssVariableName'] ?? null ) === $name ) { return $v; } } return null; }

	$DEF = [
		'enabled' => false, 'min_viewport' => 400, 'max_viewport' => 1440, 'font_min' => 18, 'font_max' => 20,
		'ratio_min' => 1.2, 'ratio_max' => 1.25, 'steps_up' => 5, 'steps_down' => 2, 'leading_body' => 0.25, 'lh_heading_ratio' => 1.1, 'lh_body_ratio' => 1.3,
		'leading_heading' => 0.25, 'site_width' => 90, 'measure' => 65, 'gutter' => 's-m', 'popout_space' => '2xl', 'grid_min' => 30,
		'space_pairs' => [ 's-l' ], 'flow_enabled' => true, 'flow_space' => 's', 'flow_heading_space' => 'l',
	];
	const STEP0 = 'clamp(1.125rem, 1.0769rem + 0.1923cqi, 1.25rem)';
	const CLASSES13 = [ 'pdk-off', 'pdk-container', 'pdk-content', 'pdk-popout', 'pdk-popout-start', 'pdk-popout-end', 'pdk-wide-start', 'pdk-wide-end', 'pdk-full', 'pdk-full-start', 'pdk-full-end', 'pdk-grid', 'pdk-flow-off' ];

	// ---- Kindproces: afwezige Oxygen-functie of afwezige klasse ----------------------
	if ( '' !== $omit ) {
		reset_world(); module_aan(); set_oxygen( true );
		try {
			if ( 'class' === $omit ) {
				( new PDK_Agent_Abilities( new PDK_Loader() ) )->register_abilities();
				echo json_encode( [ 'abilities' => array_keys( $GLOBALS['abilities'] ) ] ), "\n";
			} else {
				$r = PDK_CSS_Framework::save( [ 'steps_up' => 4 ] );
				echo json_encode( [ 'saved' => $r['saved'], 'status' => $r['oxygen']['status'] ?? null, 'saves' => count( saves() ), 'file' => file_exists( css_pad() ), 'steps' => opgeslagen()['steps_up'] ?? null ] ), "\n";
			}
		} catch ( \Throwable $e ) {
			echo json_encode( [ 'threw' => get_class( $e ) . ': ' . $e->getMessage() ] ), "\n";
		}
		exit( 0 );
	}
	function child( string $mode ): array {
		putenv( 'PDK_T_OMIT=' . $mode );
		$out = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' 2>&1' );
		putenv( 'PDK_T_OMIT' );
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $out ) ), static fn( $l ) => '' !== $l ) );
		return (array) json_decode( (string) end( $lines ), true ) ?: [ 'raw' => $out ];
	}

	$TOK = PDK_CSS_Framework::tokens( array_merge( $DEF, [ 'enabled' => true ] ) );

	// ============================================================================
	echo "AC-CSS-011 MCP-abilities: registratie, schema, rechten, annotaties\n";
	// ============================================================================
	reset_world(); module_aan();
	$ab = new PDK_Agent_Abilities( new PDK_Loader() );
	$ab->register_abilities();
	$GET = $GLOBALS['abilities']['pdk-theme-options/get-css-framework'] ?? null;
	$UPD = $GLOBALS['abilities']['pdk-theme-options/update-css-framework'] ?? null;
	check( 'met PDK_CSS_Framework geladen: get-css-framework geregistreerd', null !== $GET );
	check( 'met PDK_CSS_Framework geladen: update-css-framework geregistreerd', null !== $UPD );
	$GET = $GET ?? []; $UPD = $UPD ?? [];
	check( 'get: readonly, niet destructief, idempotent', ( $GET['meta']['annotations']['readonly'] ?? null ) === true && ( $GET['meta']['annotations']['destructive'] ?? null ) === false && ( $GET['meta']['annotations']['idempotent'] ?? null ) === true );
	check( 'update: niet readonly, destructief, idempotent', ( $UPD['meta']['annotations']['readonly'] ?? null ) === false && ( $UPD['meta']['annotations']['destructive'] ?? null ) === true && ( $UPD['meta']['annotations']['idempotent'] ?? null ) === true );
	check( 'beide: show_in_rest en mcp.public', ( $GET['meta']['show_in_rest'] ?? null ) === true && ( $GET['meta']['mcp']['public'] ?? null ) === true && ( $UPD['meta']['show_in_rest'] ?? null ) === true && ( $UPD['meta']['mcp']['public'] ?? null ) === true );
	foreach ( [ 'get' => $GET, 'update' => $UPD ] as $naam => $a ) {
		$perm = $a['permission_callback'] ?? null;
		$GLOBALS['caps'] = [ 'manage_options' ];
		check( "{$naam}: permission true met manage_options (zonder code-cap)", is_callable( $perm ) && true === $perm() && ! current_user_can( PDK_CAP_EDIT_CODE ) );
		$GLOBALS['caps'] = [];
		check( "{$naam}: permission false zonder manage_options", is_callable( $perm ) && false === $perm() );
		$GLOBALS['caps'] = [ PDK_CAP_EDIT_CODE ];
		check( "{$naam}: permission false met alleen de code-cap (geen manage_options)", is_callable( $perm ) && false === $perm() );
	}
	$GLOBALS['caps'] = [ 'manage_options' ];
	$gs = $GET['input_schema'] ?? [];
	check( 'get: input_schema object, geen properties-sleutel, additionalProperties false', ( $gs['type'] ?? null ) === 'object' && ! isset( $gs['properties'] ) && ( $gs['additionalProperties'] ?? null ) === false, json_encode( $gs ) );
	$us = $UPD['input_schema'] ?? [];
	$cs = $us['properties']['config'] ?? [];
	check( 'update: top-schema object, additionalProperties false, required [config]', ( $us['type'] ?? null ) === 'object' && ( $us['additionalProperties'] ?? null ) === false && ( $us['required'] ?? null ) === [ 'config' ] );
	check( 'update: config-schema object met additionalProperties false', ( $cs['type'] ?? null ) === 'object' && ( $cs['additionalProperties'] ?? null ) === false );
	$pr = $cs['properties'] ?? [];
	$verw = [ 'integer' => [ 'min_viewport', 'max_viewport', 'steps_up', 'steps_down' ], 'number' => [ 'font_min', 'font_max', 'ratio_min', 'ratio_max', 'leading_body', 'leading_heading', 'lh_heading_ratio', 'lh_body_ratio', 'grid_min', 'site_width', 'measure' ], 'string' => [ 'gutter', 'popout_space', 'flow_space', 'flow_heading_space' ], 'boolean' => [ 'flow_enabled' ] ];
	foreach ( $verw as $type => $keys ) { foreach ( $keys as $k ) { check( "schema: {$k} is {$type}", ( $pr[ $k ]['type'] ?? null ) === $type ); } }
	check( 'schema: space_pairs array van strings, maxItems 10', ( $pr['space_pairs']['type'] ?? null ) === 'array' && ( $pr['space_pairs']['items']['type'] ?? null ) === 'string' && ( $pr['space_pairs']['maxItems'] ?? null ) === 10 );
	$verwacht_keys = array_diff( array_keys( $DEF ), [ 'enabled' ] ); $keys = array_keys( $pr ); sort( $verwacht_keys ); sort( $keys );
	check( 'schema: precies de 21 configvelden, geen feature_space en geen enabled', $keys === $verwacht_keys && ! isset( $pr['feature_space'] ), implode( ',', array_diff( $keys, $verwacht_keys ) ) );

	$kind = child( 'class' );
	check( 'zonder klasse PDK_CSS_Framework: geen van beide css-abilities geregistreerd (kindproces)', isset( $kind['abilities'] ) && ! preg_grep( '#css-framework#', $kind['abilities'] ) && in_array( 'pdk-theme-options/get-site-info', $kind['abilities'], true ), json_encode( $kind ) );

	// ============================================================================
	echo "\nAC-CSS-012 get- en update-css-framework\n";
	// ============================================================================
	reset_world(); module_aan(); set_oxygen( false );
	$ab = new PDK_Agent_Abilities( new PDK_Loader() ); $ab->register_abilities();
	$get = $GLOBALS['abilities']['pdk-theme-options/get-css-framework']['execute_callback'] ?? null;
	$upd = $GLOBALS['abilities']['pdk-theme-options/update-css-framework']['execute_callback'] ?? null;
	safe( 'get: config, tokens (alleen name+value), classes (13, geen left/right), css_url, oxygen', static function () use ( $get ) {
		$o = $get( [] );
		$tk = array_column( $o['tokens'], 'value', 'name' );
		if ( $o['config'] !== PDK_CSS_Framework::config() ) { return 'config wijkt af'; }
		foreach ( $o['tokens'] as $t ) { if ( array_keys( $t ) !== [ 'name', 'value' ] ) { return 'token met extra sleutels: ' . json_encode( $t ); } }
		if ( ( $tk['--pdk-step-0'] ?? '' ) !== STEP0 || ( $tk['--pdk-site-width'] ?? '' ) !== '90rem' || ( $tk['--pdk-measure'] ?? '' ) !== '65ch' ) { return 'tokenwaarden'; }
		if ( ( $tk['--pdk-grid-min'] ?? '' ) !== '30ch' ) { return 'V14 grid-min: ' . ( $tk['--pdk-grid-min'] ?? 'null' ); }
		if ( ( $tk['--pdk-lh-heading'] ?? '' ) !== '1.1' || ( $tk['--pdk-lh-body'] ?? '' ) !== '1.3' ) { return 'V13 lh-tokens: ' . json_encode( [ $tk['--pdk-lh-heading'] ?? null, $tk['--pdk-lh-body'] ?? null ] ); }
		$cl = $o['classes']; $cx = CLASSES13; sort( $cl ); sort( $cx );
		if ( $cl !== $cx || preg_grep( '/left|right/', $cl ) ) { return 'classes: ' . json_encode( $o['classes'] ); }
		if ( PDK_STORAGE_URL . 'css-framework.css' !== $o['css_url'] ) { return 'css_url ' . ( $o['css_url'] ?? '' ); }
		return false === ( $o['oxygen']['active'] ?? null ) && array_key_exists( 'modern_normalize', $o['oxygen'] ?? [] ) && null === $o['oxygen']['modern_normalize'] ? true : 'oxygen: ' . json_encode( $o['oxygen'] ?? null );
	} );
	safe( 'get: Oxygen actief + Modern Normalize aan -> active true, modern_normalize true', static function () use ( $get ) {
		set_oxygen( true ); $GLOBALS['bd_modern'] = true; $o = $get( [] );
		return true === $o['oxygen']['active'] && true === $o['oxygen']['modern_normalize'] ? true : json_encode( $o['oxygen'] );
	} );
	safe( 'get: Modern Normalize-check gooit -> modern_normalize null, geen fout', static function () use ( $get ) {
		$GLOBALS['bd_modern'] = 'throw'; $o = $get( [] ); set_oxygen( false ); $GLOBALS['bd_modern'] = false;
		return null === $o['oxygen']['modern_normalize'] ? true : json_encode( $o['oxygen'] );
	} );
	safe( 'update met niet-default waarden (voorbereiding partiële update)', static function () use ( $upd ) {
		$o = $upd( [ 'config' => [ 'max_viewport' => 1600, 'flow_heading_space' => 'xl', 'lh_heading_ratio' => 1.2 ] ] );
		return ! is_wp_error( $o ) && 1600 === opgeslagen()['max_viewport'] && 'xl' === opgeslagen()['flow_heading_space'] && 1.2 === opgeslagen()['lh_heading_ratio'] ? true : 'voorbereiding mislukt';
	} );
	safe( 'update {steps_up: 3}: opgeslagen, bestand geregenereerd, tokens zonder step-4/-5, rest behouden', static function () use ( $upd ) {
		$o = $upd( [ 'config' => [ 'steps_up' => 3 ] ] );
		if ( is_wp_error( $o ) ) { return 'WP_Error ' . $o->get_error_message(); }
		$namen = array_column( $o['tokens'], 'name' );
		$f = (string) @file_get_contents( css_pad() );
		return true === $o['saved'] && 3 === $o['config']['steps_up'] && 3 === opgeslagen()['steps_up'] && 1600 === opgeslagen()['max_viewport'] && 'xl' === opgeslagen()['flow_heading_space'] && 1600 === $o['config']['max_viewport'] && 'xl' === $o['config']['flow_heading_space'] && 1.2 === opgeslagen()['lh_heading_ratio'] && 1.2 === $o['config']['lh_heading_ratio'] && 1.3 === opgeslagen()['lh_body_ratio'] && in_array( '--pdk-lh-heading', $namen, true ) && in_array( '--pdk-lh-body', $namen, true )
			&& ! in_array( '--pdk-step-4', $namen, true ) && ! in_array( '--pdk-step-5', $namen, true ) && in_array( '--pdk-step-3', $namen, true )
			&& str_contains( $f, '--pdk-step-3:' ) && ! str_contains( $f, '--pdk-step-4:' ) && 'skipped' === ( $o['oxygen']['status'] ?? null ) ? true : json_encode( $o['oxygen'] ?? null );
	} );
	$voor = serialize( $GLOBALS['options'] ); $md5 = md5_file( css_pad() ); $inhoud = (string) file_get_contents( css_pad() );
	$ongeldig = [
		'min_viewport >= max_viewport' => [ 'min_viewport' => 1700 ], 'steps_up 9' => [ 'steps_up' => 9 ], 'font_min CSS-injectie' => [ 'font_min' => '1;}body{display:none' ],
		'site_width 1440' => [ 'site_width' => 1440 ], 'lh_heading_ratio 0.99' => [ 'lh_heading_ratio' => 0.99 ], 'V14 grid_min 9.99' => [ 'grid_min' => 9.99 ], 'V14 grid_min 30.125' => [ 'grid_min' => 30.125 ], 'lh_body_ratio 2.01' => [ 'lh_body_ratio' => 2.01 ], 'lh_body_ratio 1.125' => [ 'lh_body_ratio' => 1.125 ], 'measure 3 decimalen' => [ 'measure' => 65.125 ], 'gutter huge' => [ 'gutter' => 'huge' ],
		'flow_enabled ja' => [ 'flow_enabled' => 'ja' ], 'space_pairs l-s' => [ 'space_pairs' => [ 'l-s' ] ], 'space_pairs s-l + newline' => [ 'space_pairs' => [ "s-l\n" ] ],
	];
	foreach ( $ongeldig as $label => $cfg ) {
		safe( "update {$label}: WP_Error pdk_invalid_config, zelfde fouten als validate(), niets gewijzigd", static function () use ( $upd, $cfg, $voor, $md5, $inhoud ) {
			$o = $upd( [ 'config' => $cfg ] );
			$na = serialize( $GLOBALS['options'] ) === $voor && md5_file( css_pad() ) === $md5;
			$GLOBALS['options'] = unserialize( $voor ); file_put_contents( css_pad(), $inhoud ); // geen vervuiling van de volgende cases
			if ( ! is_wp_error( $o ) || 'pdk_invalid_config' !== $o->get_error_code() ) { return 'geen pdk_invalid_config'; }
			$verw = PDK_CSS_Framework::validate( $cfg )['errors'];
			$got  = (array) ( $o->get_error_data()['errors'] ?? [] );
			return $got === $verw && $verw && $na ? true : 'errors ' . json_encode( $got ) . ' vs ' . json_encode( $verw );
		} );
	}
	safe( 'update: tweede identieke update slaagt opnieuw (idempotent)', static function () use ( $upd ) {
		$a = $upd( [ 'config' => [ 'steps_up' => 3 ] ] ); return ! is_wp_error( $a ) && true === $a['saved'];
	} );

	// ============================================================================
	echo "\nAC-CSS-025 Veldtoelichting uit een bron: fields(), tab, MCP-schema, get\n";
	// ============================================================================
	$GROEPEN = [ 'Viewport', 'Typografie', 'Regelhoogte', 'Ruimte', 'Layout', 'Flow', 'Grid' ];
	$fields  = [];
	safe( 'fields(): precies de configsleutels behalve enabled; label/unit/help/group; label en help niet leeg; group uit de vaste lijst', static function () use ( $DEF, $GROEPEN, &$fields ) {
		$fields = PDK_CSS_Framework::fields();
		$verw = array_diff( array_keys( $DEF ), [ 'enabled' ] ); $got = array_keys( $fields ); sort( $verw ); sort( $got );
		if ( $got !== $verw ) { return 'sleutels: mist ' . implode( ',', array_diff( $verw, $got ) ) . ' | te veel ' . implode( ',', array_diff( $got, $verw ) ); }
		foreach ( $fields as $k => $f ) {
			$sk = array_keys( $f ); sort( $sk );
			if ( $sk !== [ 'group', 'help', 'label', 'unit' ] ) { return "{$k}: sleutels " . json_encode( $sk ); }
			if ( ! is_string( $f['label'] ) || '' === trim( $f['label'] ) || ! is_string( $f['help'] ) || '' === trim( $f['help'] ) || ! is_string( $f['unit'] ) ) { return "{$k}: label/help leeg of unit geen string"; }
			if ( ! in_array( $f['group'], $GROEPEN, true ) ) { return "{$k}: groep " . json_encode( $f['group'] ); }
		}
		return true;
	} );
	safe( 'fields(): elke groep uit de lijst komt minstens één keer voor', static function () use ( &$fields, $GROEPEN ) {
		return ! array_diff( $GROEPEN, array_column( $fields, 'group' ) ) ? true : 'ongebruikt: ' . implode( ',', array_diff( $GROEPEN, array_column( $fields, 'group' ) ) );
	} );
	safe( 'tab: elke groep als kop, en per veld label, eenheid en help', static function () use ( &$fields, $GROEPEN ) {
		if ( ! $fields ) { return 'fields() leeg of ontbreekt'; }
		reset_world(); module_aan(); set_oxygen( false );
		$a = new PDK_Admin( new PDK_Loader() ); $m = new ReflectionMethod( 'PDK_Admin', 'render_tab_content' );
		ob_start(); try { $m->invoke( $a, 'css_framework' ); } finally { $html = (string) ob_get_clean(); }
		foreach ( array_unique( array_column( $fields, 'group' ) ) as $g ) {
			if ( ! preg_match( '#<h[2-5][^>]*>\s*' . preg_quote( esc_html( $g ), '#' ) . '\s*</h[2-5]>#', $html ) ) { return "groepkop ontbreekt: {$g}"; }
		}
		foreach ( $fields as $k => $f ) {
			foreach ( [ 'label', 'help' ] as $w ) { if ( ! str_contains( $html, esc_html( $f[ $w ] ) ) ) { return "{$k}: {$w} niet op de tab"; } }
			if ( '' !== $f['unit'] && ! str_contains( $html, esc_html( $f['unit'] ) ) ) { return "{$k}: eenheid '{$f['unit']}' niet op de tab"; }
		}
		return true;
	} );
	safe( 'MCP update-schema: description per property bevat fields()[key][help]', static function () use ( &$fields, $pr ) {
		if ( ! $fields ) { return 'fields() leeg of ontbreekt'; }
		foreach ( $fields as $k => $f ) {
			$d = $pr[ $k ]['description'] ?? null;
			if ( ! is_string( $d ) || ! str_contains( $d, $f['help'] ) ) { return "{$k}: description " . json_encode( $d ); }
		}
		return true;
	} );
	safe( 'get-css-framework geeft fields, gelijk aan fields()', static function () use ( $get, &$fields ) {
		$o = $get( [] ); return isset( $o['fields'] ) && $o['fields'] === $fields ? true : 'fields ontbreekt of wijkt af';
	} );

	// ============================================================================
	echo "\nAC-CSS-014 Oxygen-variabelen: de pure merge\n";
	// ============================================================================
	reset_world();
	safe( 'lege lijst: alle :root-tokens met UUID4-id, naam zonder --, unit/custom, collectie, zonder dynamicData; geen pdk-bo-*', static function () use ( $TOK ) {
		$r = PDK_CSS_Framework::merge_oxygen_variables( [], [], $TOK );
		$namen = array_map( static fn( $t ) => substr( $t['name'], 2 ), $TOK );
		if ( array_column( $r['variables'], 'cssVariableName' ) !== $namen ) { return 'namen/volgorde: ' . implode( ',', array_diff( $namen, array_column( $r['variables'], 'cssVariableName' ) ) ); }
		foreach ( [ 'pdk-site-width', 'pdk-measure', 'pdk-popout-space', 'pdk-lh-heading', 'pdk-lh-body' ] as $n ) { if ( ! find_var( $r['variables'], $n ) ) { return "mist {$n}"; } }
		foreach ( $r['variables'] as $i => $v ) {
			if ( str_starts_with( $v['cssVariableName'], 'pdk-bo-' ) || str_starts_with( $v['cssVariableName'], '--' ) ) { return 'naam ' . $v['cssVariableName']; }
			$val = $TOK[ $i ]['value'];
			$k = array_keys( $v ); sort( $k );
			if ( $k !== [ 'collection', 'cssVariableName', 'id', 'label', 'type', 'value' ] ) { return 'sleutels ' . json_encode( $k ); }
			if ( 'unit' !== $v['type'] || 'PDK Framework' !== $v['collection'] || $v['value'] !== [ 'number' => $val, 'unit' => 'custom', 'style' => $val ] ) { return 'vorm ' . json_encode( $v ); }
			if ( ! preg_match( '/^00000000-0000-4000-8000-\d{12}$/', (string) $v['id'] ) ) { return 'id niet van wp_generate_uuid4(): ' . $v['id']; }
		}
		return ( find_var( $r['variables'], 'pdk-step-0' )['label'] ?? '' ) === 'PDK step 0' && ( find_var( $r['variables'], 'pdk-site-width' )['label'] ?? '' ) === 'PDK site width' && [ 'PDK Framework' ] === $r['collections'] && true === $r['changed'] ? true : 'label/collecties/changed';
	} );
	safe( 'merge op eigen uitvoer: identiek, changed false, geen nieuwe uuid', static function () use ( $TOK ) {
		$a = PDK_CSS_Framework::merge_oxygen_variables( [], [], $TOK ); $n = $GLOBALS['uuid_n'];
		$b = PDK_CSS_Framework::merge_oxygen_variables( $a['variables'], $a['collections'], $TOK );
		return $b['variables'] === $a['variables'] && $b['collections'] === $a['collections'] && false === $b['changed'] && $n === $GLOBALS['uuid_n'] ? true : 'changed=' . var_export( $b['changed'], true );
	} );
	safe( 'bestaande pdk-step-0 met id abc: id blijft, waarde bijgewerkt, zelfde plek', static function () use ( $TOK ) {
		$oud = [ fv( 'brand' ), [ 'id' => 'abc', 'cssVariableName' => 'pdk-step-0', 'label' => 'PDK step 0', 'type' => 'unit', 'value' => [ 'number' => 'old', 'unit' => 'custom', 'style' => 'old' ], 'collection' => 'PDK Framework' ] ];
		$r = PDK_CSS_Framework::merge_oxygen_variables( $oud, [ 'PDK Framework' ], $TOK );
		$v = $r['variables'][1] ?? [];
		return 'abc' === ( $v['id'] ?? '' ) && 'pdk-step-0' === ( $v['cssVariableName'] ?? '' ) && STEP0 === ( $v['value']['style'] ?? '' ) && STEP0 === ( $v['value']['number'] ?? '' ) && 1 === count( array_filter( $r['variables'], static fn( $x ) => 'pdk-step-0' === $x['cssVariableName'] ) ) && true === $r['changed'] ? true : json_encode( $v );
	} );
	safe( 'vreemde variabelen en collecties blijven staan, zelfde volgorde, ongewijzigd; collectie niet dubbel', static function () use ( $TOK ) {
		$f1 = fv( 'brand-red', [ 'dynamicData' => [ 'x' => 1 ] ] ); $f2 = fv( 'brand-blue' );
		$r  = PDK_CSS_Framework::merge_oxygen_variables( [ $f1, $f2 ], [ 'Brand', 'PDK Framework' ], $TOK );
		return $r['variables'][0] === $f1 && $r['variables'][1] === $f2 && [ 'Brand', 'PDK Framework' ] === $r['collections'] && count( $r['variables'] ) === 2 + count( $TOK ) ? true : 'volgorde of aantal';
	} );
	safe( 'verouderde pdk-step-5 (niet meer gegenereerd) blijft onveranderd staan', static function () use ( $DEF ) {
		$tok = PDK_CSS_Framework::tokens( array_merge( $DEF, [ 'enabled' => true, 'steps_up' => 3 ] ) );
		$stale = [ 'id' => 's5', 'cssVariableName' => 'pdk-step-5', 'label' => 'PDK step 5', 'type' => 'unit', 'value' => [ 'number' => 'x', 'unit' => 'custom', 'style' => 'x' ], 'collection' => 'PDK Framework' ];
		$r = PDK_CSS_Framework::merge_oxygen_variables( [ $stale ], [ 'PDK Framework' ], $tok );
		return $r['variables'][0] === $stale && null === find_var( array_slice( $r['variables'], 1 ), 'pdk-step-5' ) ? true : 'stale gewijzigd of dubbel';
	} );

	// ============================================================================
	echo "\nAC-CSS-015 Oxygen-sync bij save(): guards\n";
	// ============================================================================
	$na_save = static function ( array $r, int $steps ): bool {
		return true === $r['saved'] && $steps === ( opgeslagen()['steps_up'] ?? null ) && file_exists( css_pad() ) && file_get_contents( css_pad() ) === PDK_CSS_Framework::generate( PDK_CSS_Framework::config() );
	};
	$sync = static function ( array $setup, array $input, ?string $mode = null ) {
		reset_world(); module_aan(); set_oxygen( true ); $GLOBALS['bd_save'] = $mode; foreach ( $setup as $k => $v ) { $GLOBALS['bd_opts'][ $k ] = $v; }
		return PDK_CSS_Framework::save( $input );
	};
	safe( 'Oxygen inactief: skipped met melding, saveVariables niet aangeroepen, instellingen en bestand opgeslagen', static function () use ( $na_save ) {
		reset_world(); module_aan(); set_oxygen( false );
		$r = PDK_CSS_Framework::save( [ 'steps_up' => 4 ] );
		return 'skipped' === ( $r['oxygen']['status'] ?? null ) && 'Oxygen 6 niet actief — alleen het CSS-bestand bijgewerkt' === ( $r['oxygen']['message'] ?? null ) && ! saves() && $na_save( $r, 4 ) ? true : json_encode( $r['oxygen'] ?? null );
	} );
	foreach ( [ 'save' => 'saveVariables ontbreekt', 'get' => 'get_global_option ontbreekt' ] as $mode => $label ) {
		$k = child( $mode );
		check( "{$label} (kindproces): skipped, niets aangeroepen, save geslaagd", 'skipped' === ( $k['status'] ?? null ) && true === ( $k['saved'] ?? null ) && 0 === ( $k['saves'] ?? -1 ) && true === ( $k['file'] ?? null ) && 4 === ( $k['steps'] ?? null ), json_encode( $k ) );
	}
	safe( 'Oxygen actief, lege optie: synced; payload met alle tokens, collectie PDK Framework, geen {}', static function () use ( $sync, $na_save ) {
		$r = $sync( [], [ 'steps_up' => 4 ] );
		if ( 'synced' !== ( $r['oxygen']['status'] ?? null ) || 1 !== count( saves() ) || ! $na_save( $r, 4 ) ) { return json_encode( $r['oxygen'] ?? null ); }
		$d = json_decode( saves()[0][1], true );
		return count( $d['variables'] ) === count( PDK_CSS_Framework::tokens( PDK_CSS_Framework::config() ) ) && [ 'PDK Framework' ] === $d['collections'] && ! str_contains( saves()[0][1], '{}' ) ? true : 'payload';
	} );
	safe( 'tweede save zonder wijziging: unchanged en saveVariables niet opnieuw aangeroepen', static function () use ( $sync ) {
		$sync( [], [ 'steps_up' => 4 ] ); $r2 = PDK_CSS_Framework::save( [ 'steps_up' => 4 ] );
		return 'unchanged' === ( $r2['oxygen']['status'] ?? null ) && 1 === count( saves() ) ? true : json_encode( $r2['oxygen'] ?? null ) . ' saves=' . count( saves() );
	} );
	safe( 'leest de ruwe optie, nooit getVariables(); gefilterde variabele komt niet in de payload', static function () use ( $sync ) {
		$sync( [ 'variables_json_string' => [ fv( 'brand' ) ] ], [ 'steps_up' => 4 ] );
		return in_array( [ 'get_global_option', 'variables_json_string' ], $GLOBALS['bd_calls'], true ) && ! in_array( [ 'getVariables' ], $GLOBALS['bd_calls'], true ) && saves() && ! str_contains( saves()[0][1], 'filtered-only' ) && str_contains( saves()[0][1], '"brand"' ) ? true : 'ruwe optie niet gebruikt';
	} );
	foreach ( [ 'geen collections-optie' => [], 'collections-optie is null' => [ 'variables_collections_json_string' => null ], 'collections-optie is false' => [ 'variables_collections_json_string' => false ] ] as $label => $extra ) {
		safe( "ruwe variabelen met collection Brand, {$label}: payload collections ['Brand','PDK Framework'], geen error", static function () use ( $sync, $extra ) {
			$r = $sync( [ 'variables_json_string' => [ fv( 'brand' ) ] ] + $extra, [ 'steps_up' => 4 ] );
			$d = json_decode( saves()[0][1] ?? 'null', true );
			return 'synced' === ( $r['oxygen']['status'] ?? null ) && is_array( $d ) && [ 'Brand', 'PDK Framework' ] === $d['collections'] ? true : json_encode( $r['oxygen'] ?? null ) . ' ' . json_encode( $d['collections'] ?? null );
		} );
	}
	safe( 'bestaand id blijft bij overschrijven, nieuwe ids uit wp_generate_uuid4(), verouderde pdk-step-5 blijft, vreemde variabele ongemoeid', static function () use ( $sync, $na_save ) {
		$stale = [ 'id' => 's5', 'cssVariableName' => 'pdk-step-5', 'label' => 'PDK step 5', 'type' => 'unit', 'value' => [ 'number' => 'keep', 'unit' => 'custom', 'style' => 'keep' ], 'collection' => 'PDK Framework' ];
		$oud   = [ 'id' => 'abc', 'cssVariableName' => 'pdk-step-0', 'label' => 'PDK step 0', 'type' => 'unit', 'value' => [ 'number' => 'old', 'unit' => 'custom', 'style' => 'old' ], 'collection' => 'PDK Framework' ];
		$f     = fv( 'brand' );
		$r = $sync( [ 'variables_json_string' => [ $f, $oud, $stale ], 'variables_collections_json_string' => [ 'Brand', 'PDK Framework' ] ], [ 'steps_up' => 3 ] );
		$d = json_decode( saves()[0][1] ?? 'null', true );
		if ( 'synced' !== ( $r['oxygen']['status'] ?? null ) || ! $d ) { return 'status ' . json_encode( $r['oxygen'] ?? null ); }
		$s0 = find_var( $d['variables'], 'pdk-step-0' ); $s1 = find_var( $d['variables'], 'pdk-step-1' );
		return $d['variables'][0] === $f && 'abc' === $s0['id'] && STEP0 === $s0['value']['style'] && find_var( $d['variables'], 'pdk-step-5' ) === $stale && preg_match( '/^00000000-0000-4000-8000-\d{12}$/', (string) ( $s1['id'] ?? '' ) ) && [ 'Brand', 'PDK Framework' ] === $d['collections'] && $na_save( $r, 3 ) ? true : 'inhoud payload';
	} );
	safe( 'oud formaat (JSON-string) wordt gedecodeerd en samengevoegd', static function () use ( $sync ) {
		$r = $sync( [ 'variables_json_string' => json_encode( [ fv( 'oud-formaat' ) ] ) ], [ 'steps_up' => 4 ] );
		return 'synced' === ( $r['oxygen']['status'] ?? null ) && saves() && str_contains( saves()[0][1], 'oud-formaat' ) ? true : json_encode( $r['oxygen'] ?? null );
	} );
	foreach ( [ '\Exception' => 'exception', '\TypeError (\Error)' => 'error' ] as $label => $mode ) {
		safe( "saveVariables gooit {$label}: status error met tekst, save slaagt, bestand geregenereerd, geen uncaught", static function () use ( $sync, $na_save, $mode ) {
			$r = $sync( [], [ 'steps_up' => 2 ], $mode );
			return 'error' === ( $r['oxygen']['status'] ?? null ) && str_contains( (string) ( $r['oxygen']['message'] ?? '' ), 'exception' === $mode ? 'boom-exception' : 'boom-type' ) && $na_save( $r, 2 ) ? true : json_encode( $r['oxygen'] ?? null );
		} );
	}
	safe( 'ongeldige UTF-8 (\xB1) in een vreemd label: error, saveVariables niet aangeroepen', static function () use ( $sync, $na_save ) {
		$r = $sync( [ 'variables_json_string' => [ fv( 'kapot', [ 'label' => "\xB1" ] ) ] ], [ 'steps_up' => 2 ] );
		return 'error' === ( $r['oxygen']['status'] ?? null ) && ! saves() && $na_save( $r, 2 ) ? true : json_encode( $r['oxygen'] ?? null ) . ' saves=' . count( saves() );
	} );
	safe( 'payload met {} (leeg object in een vreemde variabele): error, niet aangeroepen', static function () use ( $sync, $na_save ) {
		$r = $sync( [ 'variables_json_string' => [ fv( 'leeg', [ 'extra' => new stdClass() ] ) ] ], [ 'steps_up' => 2 ] );
		return 'error' === ( $r['oxygen']['status'] ?? null ) && ! saves() && $na_save( $r, 2 ) ? true : json_encode( $r['oxygen'] ?? null ) . ' saves=' . count( saves() );
	} );
	safe( 'letterlijke "{}" in een label (Oxygens str_replace zou het stuk maken): error, niet aangeroepen', static function () use ( $sync ) {
		$r = $sync( [ 'variables_json_string' => [ fv( 'accolade', [ 'label' => 'a {} b' ] ) ] ], [ 'steps_up' => 2 ] );
		return 'error' === ( $r['oxygen']['status'] ?? null ) && ! saves() ? true : json_encode( $r['oxygen'] ?? null );
	} );
	foreach ( [ 'getal 12345' => 12345, 'tekst die geen JSON is' => 'garbage', 'JSON-getal "5"' => '5' ] as $label => $raw ) {
		safe( "ruwe optie bestaat maar geeft geen array ({$label}): error, niet aangeroepen, save slaagt", static function () use ( $sync, $na_save, $raw ) {
			$r = $sync( [ 'variables_json_string' => $raw ], [ 'steps_up' => 2 ] );
			return 'error' === ( $r['oxygen']['status'] ?? null ) && ! saves() && $na_save( $r, 2 ) ? true : json_encode( $r['oxygen'] ?? null ) . ' saves=' . count( saves() );
		} );
	}
	safe( 'ongeldige save: geen sync, saveVariables niet aangeroepen', static function () use ( $sync ) {
		$r = $sync( [], [ 'steps_up' => 99 ] );
		return false === $r['saved'] && ! saves() ? true : 'saved=' . var_export( $r['saved'], true );
	} );

	// ---- Admin: redirect met warning, notice -------------------------------------------
	function form( array $over = [] ): array {
		global $DEF; $f = [];
		foreach ( $DEF as $k => $v ) { if ( 'enabled' === $k ) { continue; } $f[ $k ] = is_array( $v ) ? $v : ( is_bool( $v ) ? ( $v ? '1' : '' ) : (string) $v ); }
		return array_merge( $f, $over );
	}
	function post_en_redirect( array $velden ): string {
		$_POST = [ 'pdk_tab' => 'css_framework', 'css_framework' => $velden ];
		try { ( new PDK_Admin( new PDK_Loader() ) )->handle_save(); } catch ( PDK_Test_Redirect $e ) { return rawurldecode( rawurldecode( $e->url ) ); }
		return '';
	}
	echo "\nAC-CSS-015 / D16 Admin: warning-queryarg en notice\n";
	safe( 'sync-fout: redirect met saved=1 en warning=… (Oxygen-sync mislukt + exceptietekst); instellingen opgeslagen', static function () {
		reset_world(); module_aan(); set_oxygen( true ); $GLOBALS['bd_save'] = 'exception';
		$url = post_en_redirect( form( [ 'steps_up' => '4' ] ) );
		return str_contains( $url, 'saved=1' ) && str_contains( $url, 'warning=' ) && str_contains( $url, 'Oxygen-sync mislukt' ) && str_contains( $url, 'boom-exception' ) && ! str_contains( $url, 'error=' ) && 4 === ( opgeslagen()['steps_up'] ?? null ) ? true : $url;
	} );
	safe( 'sync gelukt: saved=1 zonder warning', static function () {
		reset_world(); module_aan(); set_oxygen( true );
		$url = post_en_redirect( form( [ 'steps_up' => '4' ] ) );
		return str_contains( $url, 'saved=1' ) && ! str_contains( $url, 'warning=' ) ? true : $url;
	} );
	safe( 'Oxygen inactief (skipped): saved=1 zonder warning', static function () {
		reset_world(); module_aan(); set_oxygen( false );
		$url = post_en_redirect( form( [ 'steps_up' => '4' ] ) );
		return str_contains( $url, 'saved=1' ) && ! str_contains( $url, 'warning=' ) ? true : $url;
	} );
	$notices = static function (): string {
		$m = new ReflectionMethod( 'PDK_Admin', 'render_notices' );
		ob_start();
		try { $m->invoke( new PDK_Admin( new PDK_Loader() ) ); } finally { $h = (string) ob_get_clean(); }
		return $h;
	};
	safe( 'render_notices(): warning wordt notice-warning, geëscaped', static function () use ( $notices ) {
		$_GET = [ 'saved' => '1', 'warning' => rawurlencode( 'Instellingen opgeslagen, Oxygen-sync mislukt: <b>x</b>' ) ];
		$h = $notices();
		return str_contains( $h, 'notice-warning' ) && str_contains( $h, 'Oxygen-sync mislukt' ) && str_contains( $h, '&lt;b&gt;x&lt;/b&gt;' ) && ! str_contains( $h, '<b>x' ) ? true : $h;
	} );
	safe( 'render_notices(): zonder warning geen notice-warning', static function () use ( $notices ) {
		$_GET = [ 'saved' => '1' ]; return ! str_contains( $notices(), 'notice-warning' ) ? true : 'wel notice-warning';
	} );

	// ============================================================================
	echo "\nPR #10 review fixes\n";
	// ============================================================================
	function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
	function set_transient( $k, $v, $e = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
	function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
	/** Voert $fn uit met PHP-waarschuwingen onderdrukt maar geteld (bijv. file_put_contents op een map). */
	function quiet( callable $fn, int &$warnings = 0 ) {
		$h = set_error_handler( static function () use ( &$warnings ) { $warnings++; return true; } );
		try { return $fn(); } finally { restore_error_handler(); }
	}
	$UUID = '/^00000000-0000-4000-8000-\d{12}$/';
	$tok1 = array_values( array_filter( $TOK, static fn( $t ) => '--pdk-space-s' === $t['name'] ) );
	$val1 = $tok1[0]['value'];
	$ex   = [ 'id' => 'x1', 'cssVariableName' => 'pdk-space-s', 'label' => 'Mijn label', 'type' => 'unit', 'value' => [ 'number' => 'old', 'unit' => 'custom', 'style' => 'old' ], 'collection' => 'Brand', 'foo' => 1 ];

	// 1. merge behoudt label, collectie en extra sleutels; alleen value verandert
	safe( 'merge: bestaande pdk-space-s behoudt id, label, collectie en extra sleutel foo; alleen value verandert', static function () use ( $tok1, $val1, $ex ) {
		$r = PDK_CSS_Framework::merge_oxygen_variables( [ $ex ], [ 'Brand', 'PDK Framework' ], $tok1 );
		$v = $r['variables'][0] ?? [];
		return 1 === count( $r['variables'] ) && 'x1' === ( $v['id'] ?? null ) && 'Mijn label' === ( $v['label'] ?? null ) && 'Brand' === ( $v['collection'] ?? null ) && 1 === ( $v['foo'] ?? null )
			&& ( $v['value'] ?? null ) === [ 'number' => $val1, 'unit' => 'custom', 'style' => $val1 ] && true === $r['changed'] ? true : json_encode( $v );
	} );
	safe( 'merge: gelijke waarde met eigen label/collectie/foo: variabele ongewijzigd en changed false', static function () use ( $tok1, $val1, $ex ) {
		$gelijk = array_merge( $ex, [ 'value' => [ 'number' => $val1, 'unit' => 'custom', 'style' => $val1 ] ] );
		$r = PDK_CSS_Framework::merge_oxygen_variables( [ $gelijk ], [ 'Brand', 'PDK Framework' ], $tok1 );
		return $r['variables'] === [ $gelijk ] && false === $r['changed'] ? true : 'changed=' . var_export( $r['changed'], true ) . ' ' . json_encode( $r['variables'] );
	} );

	// 2. bestaande variabele zonder id-sleutel
	safe( 'merge: bestaande pdk-gutter zonder id krijgt een uuid4-id en geeft geen PHP-waarschuwing', static function () use ( $TOK, $UUID ) {
		$zonder = [ 'cssVariableName' => 'pdk-gutter', 'label' => 'G', 'type' => 'unit', 'value' => [ 'number' => 'old', 'unit' => 'custom', 'style' => 'old' ], 'collection' => 'PDK Framework' ];
		$w = 0;
		$r = quiet( static fn() => PDK_CSS_Framework::merge_oxygen_variables( [ $zonder ], [ 'PDK Framework' ], $TOK ), $w );
		$v = find_var( $r['variables'], 'pdk-gutter' );
		return 0 === $w && preg_match( $UUID, (string) ( $v['id'] ?? '' ) ) ? true : "waarschuwingen={$w} id=" . json_encode( $v['id'] ?? null );
	} );

	// 3. spaties en tabs rond een paar: getrimd en geaccepteerd; afsluitende newline blijft geweigerd
	safe( 'validate(): space_pairs [" s-l ", "\txs-xl\t"] geldig en getrimd naar [s-l, xs-xl]', static function () {
		$v = PDK_CSS_Framework::validate( [ 'space_pairs' => [ ' s-l ', "\txs-xl\t" ] ] );
		return ! isset( $v['errors']['space_pairs'] ) && [ 's-l', 'xs-xl' ] === $v['config']['space_pairs'] ? true : json_encode( $v['errors'] ) . ' ' . json_encode( $v['config']['space_pairs'] );
	} );
	safe( 'validate(): "s-l\n" blijft geweigerd', static function () {
		return isset( PDK_CSS_Framework::validate( [ 'space_pairs' => [ "s-l\n" ] ] )['errors']['space_pairs'] ) ? true : 'geaccepteerd';
	} );
	safe( 'admin POST: [" s-l ", "\txs-xl\t"] wordt opgeslagen als [s-l, xs-xl]', static function () {
		reset_world(); module_aan(); set_oxygen( false );
		$url = post_en_redirect( form( [ 'space_pairs' => [ ' s-l ', "\txs-xl\t" ] ] ) );
		return str_contains( $url, 'saved=1' ) && [ 's-l', 'xs-xl' ] === ( opgeslagen()['space_pairs'] ?? null ) ? true : $url . ' ' . json_encode( opgeslagen()['space_pairs'] ?? null );
	} );
	safe( 'admin POST: "s-l\n" geeft een error-redirect en slaat niets op', static function () {
		reset_world(); module_aan(); set_oxygen( false );
		$url = post_en_redirect( form( [ 'space_pairs' => [ "s-l\n" ] ] ) );
		return str_contains( $url, 'error=' ) && ! str_contains( $url, 'saved=1' ) ? true : $url;
	} );
	safe( 'MCP update: [" s-l ", "\txs-xl\t"] slaagt met [s-l, xs-xl]; "s-l\n" geeft pdk_invalid_config', static function () use ( $upd ) {
		reset_world(); module_aan(); set_oxygen( false );
		$o = $upd( [ 'config' => [ 'space_pairs' => [ ' s-l ', "\txs-xl\t" ] ] ] );
		if ( is_wp_error( $o ) || [ 's-l', 'xs-xl' ] !== $o['config']['space_pairs'] ) { return 'trim: ' . ( is_wp_error( $o ) ? $o->get_error_code() : json_encode( $o['config']['space_pairs'] ) ); }
		$f = $upd( [ 'config' => [ 'space_pairs' => [ "s-l\n" ] ] ] );
		return is_wp_error( $f ) && 'pdk_invalid_config' === $f->get_error_code() ? true : 'newline geaccepteerd';
	} );

	// 4. register(): sync na regenereren, en een transient bij een mislukte schrijfactie
	safe( 'register() met versieverschil en geslaagde schrijfactie roept de Oxygen-sync aan', static function () {
		reset_world(); module_aan(); set_oxygen( true );
		update_option( 'pdk_css_framework_built', '0.0.1' );
		( new PDK_CSS_Framework( new PDK_Loader() ) )->register();
		return file_exists( css_pad() ) && 1 === count( saves() ) && PDK_PLUGIN_VERSION === get_option( 'pdk_css_framework_built' ) ? true : 'saves=' . count( saves() );
	} );
	safe( 'register() met mislukte schrijfactie: transient pdk_css_framework_write_failed gezet, tweede register() probeert niet opnieuw', static function () {
		reset_world(); module_aan(); set_oxygen( false ); $GLOBALS['transients'] = [];
		$mod = new PDK_CSS_Framework( new PDK_Loader() );
		mkdir( css_pad() );   // een map op de bestandsnaam: file_put_contents faalt
		try {
			quiet( static fn() => $mod->register() );
			if ( false === get_transient( 'pdk_css_framework_write_failed' ) ) { return 'transient niet gezet'; }
			rmdir( css_pad() );   // nu zou een schrijfpoging slagen
			$GLOBALS['registered'] = [];
			$mod->register();
			return ! file_exists( css_pad() ) && ! isset( $GLOBALS['registered']['pdk-css-framework'] ) ? true : 'tweede register() schreef toch';
		} finally { is_dir( css_pad() ) && rmdir( css_pad() ); }
	} );
	safe( 'een geslaagde save() verwijdert de transient pdk_css_framework_write_failed', static function () {
		reset_world(); module_aan(); set_oxygen( false ); $GLOBALS['transients'] = [];
		set_transient( 'pdk_css_framework_write_failed', 1, 60 );
		$r = PDK_CSS_Framework::save( [] );
		return true === $r['saved'] && false === get_transient( 'pdk_css_framework_write_failed' ) ? true : 'transient bleef staan';
	} );

	// 5. mislukte schrijfactie in de MCP-update: eigen foutcode
	safe( 'update-css-framework met mislukte bestandsschrijfactie: WP_Error pdk_file_write_failed, niet pdk_invalid_config', static function () use ( $upd ) {
		reset_world(); module_aan(); set_oxygen( false );
		mkdir( css_pad() );
		try { $o = quiet( static fn() => $upd( [ 'config' => [ 'steps_up' => 4 ] ] ) ); } finally { is_dir( css_pad() ) && rmdir( css_pad() ); }
		return is_wp_error( $o ) && 'pdk_file_write_failed' === $o->get_error_code() ? true : ( is_wp_error( $o ) ? $o->get_error_code() : 'geen WP_Error' );
	} );

	// 6. PDK_CSS_Framework::CLASSES
	safe( 'PDK_CSS_Framework::CLASSES bestaat, bevat precies de 13 klassen en is gelijk aan get-css-framework classes', static function () use ( $get ) {
		$c = ( new ReflectionClass( 'PDK_CSS_Framework' ) )->getConstants()['CLASSES'] ?? null;
		if ( ! is_array( $c ) ) { return 'constante ontbreekt'; }
		$a = $c; $b = CLASSES13; sort( $a ); sort( $b );
		if ( $a !== $b ) { return 'andere klassen: ' . json_encode( $c ); }
		reset_world(); module_aan();
		return $get( [] )['classes'] === $c ? true : 'get-css-framework classes wijkt af van CLASSES';
	} );

	// 7. stap-attributen op de tab
	safe( 'tab: int-velden hebben lege data-pdk-step-ctrl/-alt; factorvelden 0.01/0.01; overige decimale velden 0.1/0.01', static function () use ( $DEF ) {
		reset_world(); module_aan(); set_oxygen( false );
		$a = new PDK_Admin( new PDK_Loader() ); $m = new ReflectionMethod( 'PDK_Admin', 'render_tab_content' );
		ob_start(); try { $m->invoke( $a, 'css_framework' ); } finally { $html = (string) ob_get_clean(); }
		$int = [ 'min_viewport', 'max_viewport', 'steps_up', 'steps_down' ]; $factor = [ 'lh_heading_ratio', 'lh_body_ratio', 'ratio_min', 'ratio_max' ];
		foreach ( array_diff( array_keys( $DEF ), [ 'enabled', 'gutter', 'popout_space', 'flow_space', 'flow_heading_space', 'flow_enabled', 'space_pairs' ] ) as $k ) {
			if ( ! preg_match( '#<input type="number"[^>]*name="css_framework\[' . $k . '\]"[^>]*>#', $html, $im ) ) { return "{$k}: geen number-input"; }
			$tag = $im[0];
			$ctrl = preg_match( '/data-pdk-step-ctrl="([^"]*)"/', $tag, $c ) ? $c[1] : null;
			$alt  = preg_match( '/data-pdk-step-alt="([^"]*)"/', $tag, $d ) ? $d[1] : null;
			$verw = in_array( $k, $int, true ) ? [ '', '' ] : ( in_array( $k, $factor, true ) ? [ '0.01', '0.01' ] : [ '0.1', '0.01' ] );
			if ( [ $ctrl, $alt ] !== $verw ) { return "{$k}: ctrl/alt " . json_encode( [ $ctrl, $alt ] ) . ' verwacht ' . json_encode( $verw ); }
		}
		return true;
	} );

	// ============================================================================
	echo "\nRelease-check\n";
	// ============================================================================
	$root = dirname( __DIR__ );
	$main = (string) @file_get_contents( $pdk . '/pdk-theme-options-plugin.php' );
	check( 'header Version: 2.13.0', (bool) preg_match( '/^\s*\*\s*Version:\s*2\.13\.0\s*$/m', $main ) );
	check( "define( 'PDK_PLUGIN_VERSION', '2.13.0' )", (bool) preg_match( "/define\(\s*'PDK_PLUGIN_VERSION'\s*,\s*'2\.13\.0'\s*\)/", $main ) );
	$cl = (string) @file_get_contents( $root . '/CHANGELOG.md' );
	$ok = preg_match( '/^## \[2\.13\.0\] — \d{4}-\d{2}-\d{2}\s*$(.*?)(?=^## \[|\z)/ms', $cl, $cm );
	check( 'CHANGELOG-kop "## [2.13.0] — jjjj-mm-dd"', (bool) $ok );
	check( 'CHANGELOG 2.13.0 noemt de module CSS Framework', $ok && (bool) preg_match( '/css framework/i', $cm[1] ) );
	check( 'CHANGELOG 2.13.0 noemt de nieuwe dependency van pdk-custom-style', $ok && str_contains( $cm[1], 'custom-style' ) && (bool) preg_match( '/dependency|afhankelijk/i', $cm[1] ) );
	$doc = (string) @file_get_contents( $root . '/docs/modules/css-framework.md' );
	check( 'docs/modules/css-framework.md bestaat', '' !== $doc );
	foreach ( [ '.pdk-off', 'Modern Normalize', 'Utopia', 'font-size: inherit', 'cqi', 'update-css-framework' ] as $t ) { check( "moduledoc noemt {$t}", str_contains( $doc, $t ) ); }
	safe( 'get-site-info heeft een other_output-regel voor css_framework', static function () {
		$src = (string) file_get_contents( PDK_PLUGIN_DIR . 'modules/agent-abilities/class-pdk-agent-abilities.php' );
		return (bool) preg_match( "/'other_output'\s*=>\s*\[.*?'css_framework'\s*=>/s", $src ) ? true : 'ontbreekt';
	} );

	echo "\n" . ( $fouten ? "{$fouten} test(s) gefaald\n" : "Alle tests geslaagd\n" );
	exit( $fouten ? 1 : 0 );
}
