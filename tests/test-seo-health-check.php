<?php
/**
 * Zelftest slice 1 van de module SEO Health Check (H-delen).
 * Normatief: docs/seo-health-check-brief.md §6. Draaien met:  php tests/test-seo-health-check.php
 *
 * Dekt: AC-001, AC-015, AC-016 (H), AC-017, AC-018, AC-019, AC-037 (H) en (slice 2) AC-035, AC-036.
 * De live-delen (AC-002 t/m AC-014, AC-016 L, AC-037 L) staan in tests/live/ en draaien op dev.local.
 *
 * AC-015 draait pdk-theme-options/uninstall.php echt, per scenario in een kindproces, tegen stubs
 * voor $wpdb, get_option, get_sites en WordPress-functies (harness wordt tijdelijk weggeschreven; scenario via env PDK_T_SCEN).
 * Release-checks (AC-017) zijn bewust gebonden aan 2.14.0: dat is de release van deze module (D5).
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$root = dirname( __DIR__ );
$pdk  = $root . '/pdk-theme-options';
$mod  = $pdk . '/modules/seo-health-check';

// Het kindproces voor AC-015 staat als losse harness in $harness (wordt bij het draaien weggeschreven).
$harness_code = <<<'HARNESS'
<?php
$GLOBALS['pdk_dir'] = getenv( 'PDK_T_ROOT' );
$s = json_decode( (string) getenv( 'PDK_T_SCEN' ), true );

define( 'ABSPATH', $GLOBALS['pdk_dir'] . '/tests/' );
define( 'WP_UNINSTALL_PLUGIN', 'pdk-theme-options/pdk-theme-options-plugin.php' );
$tmp = sys_get_temp_dir() . '/pdk-seohc-un-' . getmypid();
mkdir( $tmp . '/uploads', 0777, true );

$GLOBALS['blog']   = 1;
$GLOBALS['stack']  = [];
$GLOBALS['drops']  = [];
$GLOBALS['sites_args'] = null;

class T_WPDB {
	public $prefix = 'wp_';
	public $options = 'wp_options';
	public $postmeta = 'wp_postmeta';
	public function query( $sql ) {
		if ( preg_match( '/^DROP TABLE IF EXISTS (\S+)/', $sql, $m ) ) { $GLOBALS['drops'][] = [ 'blog' => $GLOBALS['blog'], 'table' => $m[1] ]; }
		return 0;
	}
	public function prepare( $q, ...$a ) { return $q; }
	public function esc_like( $t ) { return $t; }
	public function delete( ...$a ) { return 0; }
}
$wpdb = new T_WPDB();

function is_multisite() { return ! empty( $GLOBALS['s']['multisite'] ); }
function get_option( $k, $d = false ) {
	if ( 'active_plugins' === $k ) {
		$by = $GLOBALS['s']['active_by_site'] ?? null;
		return $by ? ( $by[ (string) $GLOBALS['blog'] ] ?? [] ) : ( $GLOBALS['s']['active'] ?? [] );
	}
	return $d;
}
function get_site_option( $k, $d = false ) { return 'active_sitewide_plugins' === $k ? array_fill_keys( $GLOBALS['s']['network'] ?? [], 1 ) : $d; }
function get_sites( $a ) { $GLOBALS['sites_args'] = $a; return $GLOBALS['s']['sites'] ?? [ 1 ]; }
function switch_to_blog( $id ) { $GLOBALS['stack'][] = $GLOBALS['blog']; $GLOBALS['blog'] = $id; }
function restore_current_blog() { $GLOBALS['blog'] = array_pop( $GLOBALS['stack'] ); }
function delete_option( $k ) { return true; }
function delete_site_option( $k ) { return true; }
function delete_transient( $k ) { return true; }
function delete_site_transient( $k ) { return true; }
function delete_metadata( ...$a ) { return true; }
function wp_unschedule_hook( $h ) { return 0; }
function wp_get_upload_dir() { return [ 'basedir' => $GLOBALS['tmp_up'] ]; }
function trailingslashit( $p ) { return rtrim( $p, '/\\' ) . '/'; }
function wp_delete_file( $f ) { @unlink( $f ); }
function get_users( $a ) { return []; }
function get_role( $n ) { return null; }
function wp_roles() { return (object) [ 'roles' => [] ]; }
$GLOBALS['s']      = $s;
$GLOBALS['tmp_up'] = $tmp . '/uploads';

require $GLOBALS['pdk_dir'] . '/pdk-theme-options/uninstall.php';

echo json_encode( [ 'drops' => $GLOBALS['drops'], 'sites_args' => $GLOBALS['sites_args'] ] );
exit( 0 );
HARNESS;

// ============================================================================
// Hoofdproces
// ============================================================================
define( 'ABSPATH', __DIR__ . '/' );
define( 'PDK_PLUGIN_VERSION', '0.0.0-test' );
define( 'PDK_PLUGIN_DIR', $pdk . '/' );
define( 'PDK_PLUGIN_URL', 'https://example.test/pdk/' );

$GLOBALS['t_checks'] = 0;
$GLOBALS['t_fail']   = 0;

function check( string $naam, bool $ok, string $extra = '' ): void {
	$GLOBALS['t_checks']++;
	if ( ! $ok ) {
		$GLOBALS['t_fail']++;
	}
	echo ( $ok ? '  ok    ' : '  FOUT  ' ) . $naam . ( ! $ok && '' !== $extra ? '  [' . $extra . ']' : '' ) . "\n";
}

// --- stubs voor het hoofdproces --------------------------------------------
$GLOBALS['opts']      = [];
$GLOBALS['calls']     = [];
$GLOBALS['multisite'] = false;

function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return $t; }
function is_multisite() { return $GLOBALS['multisite']; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['calls'][] = [ 'update_option', $k ]; $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { $GLOBALS['calls'][] = [ 'delete_option', $k ]; unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_site_option( $k, $d = false ) { return $d; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }
function wp_unslash( $v ) { return $v; }
function wp_unschedule_hook( $h ) { $GLOBALS['calls'][] = [ 'wp_unschedule_hook', $h ]; return 1; }
function as_unschedule_all_actions( $h ) { $GLOBALS['calls'][] = [ 'as_unschedule_all_actions', $h ]; }
function flush_rewrite_rules() { $GLOBALS['calls'][] = [ 'flush_rewrite_rules' ]; }

class PDK_Admin { const PAGE_SLUG = 'pdk-theme-options'; }
class PDK_Loader { public function add_action( ...$a ) {} }

require_once $pdk . '/includes/class-pdk-settings.php';
require_once $pdk . '/includes/class-pdk-plugin.php';
require_once $pdk . '/modules/seo-health-check/class-pdk-seo-health-check.php';

// ============================================================================
echo "AC-001 Module en standaard\n";
// ============================================================================
$def = PDK_Settings::get_defaults();
check( "AC-001 get_defaults()['seo_health_check']['enabled'] === false", isset( $def['seo_health_check'] ) && false === ( $def['seo_health_check']['enabled'] ?? null ) );
$labels = PDK_Settings::module_labels();
$descs  = PDK_Settings::module_descriptions();
check( 'AC-001 label bestaat', isset( $labels['seo_health_check'] ) && '' !== trim( $labels['seo_health_check'] ) );
check( 'AC-001 omschrijving bestaat', isset( $descs['seo_health_check'] ) && strlen( $descs['seo_health_check'] ) > 20 );
check( 'AC-001 omschrijving is Nederlands en noemt de losse plugin', isset( $descs['seo_health_check'] ) && false !== stripos( $descs['seo_health_check'], 'losse plugin' ) );
$map = ( new ReflectionClass( 'PDK_Plugin' ) )->getDefaultProperties()['module_map'] ?? [];
check( '$module_map heeft seo_health_check', isset( $map['seo_health_check'] ) );
check( '$module_map-bestand bestaat', isset( $map['seo_health_check'] ) && is_file( $pdk . '/' . $map['seo_health_check']['file'] ), $map['seo_health_check']['file'] ?? '' );
check( '$module_map-klasse is PDK_SEO_Health_Check', 'PDK_SEO_Health_Check' === ( $map['seo_health_check']['class'] ?? '' ) );
check( 'AC-001 module staat niet in ALWAYS_ON_MODULES (uitschakelbaar)', ! PDK_Settings::module_always_on( 'seo_health_check' ) );

// ============================================================================
echo "\nAC-019 tab_url() en statische scan\n";
// ============================================================================
$base = 'https://example.test/wp-admin/admin.php?page=pdk-theme-options&tab=seo_health_check&seohc_view=';
foreach ( [ 'issues', 'pages', 'scans', 'schedule', 'launch', 'settings' ] as $v ) {
	check( "AC-019 tab_url('{$v}')", $base . $v === PDK_SEO_Health_Check::tab_url( $v ), PDK_SEO_Health_Check::tab_url( $v ) );
}
check( 'AC-019 tab_url() zonder argument = issues', $base . 'issues' === PDK_SEO_Health_Check::tab_url() );
check( 'AC-019 onbekende view valt terug op issues', $base . 'issues' === PDK_SEO_Health_Check::tab_url( 'bestaat-niet' ) );
check( 'AC-019 lege view valt terug op issues', $base . 'issues' === PDK_SEO_Health_Check::tab_url( '' ) );
check( 'AC-019 extra argumenten worden toegevoegd', $base . 'launch&seohc_notice=x' === PDK_SEO_Health_Check::tab_url( 'launch', [ 'seohc_notice' => 'x' ] ) );
$_GET['seohc_view'] = 'bestaat-niet';
check( 'AC-019 current_view() met onbekende waarde = issues', 'issues' === PDK_SEO_Health_Check::current_view() );
$_GET['seohc_view'] = 'scans';
check( 'AC-019 current_view() = scans', 'scans' === PDK_SEO_Health_Check::current_view() );
unset( $_GET['seohc_view'] );

$files = [];
$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $mod, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	if ( $f->isFile() && 'mo' !== $f->getExtension() ) { $files[] = $f->getPathname(); }
}
check( 'AC-019 modulemap bevat php-bestanden om te scannen', count( $files ) > 20, (string) count( $files ) );
$literal = [ 'page=seo-health-check', 'seo-health-check-launch', "'page' => SEOHC_Admin::MENU_SLUG", 'esc_attr( self::MENU_SLUG )', 'esc_attr( self::PAGES_SLUG )' ];
$regex   = [ "~'admin\\.php\\?page=' \\. (self|SEOHC_Admin|SEOHC_Settings|SEOHC_Schedule)::~" ];
$hits    = [];
foreach ( $files as $f ) {
	$c = (string) file_get_contents( $f );
	foreach ( $literal as $p ) { if ( false !== strpos( $c, $p ) ) { $hits[] = basename( $f ) . ': ' . $p; } }
	foreach ( $regex as $p )   { if ( preg_match( $p, $c ) ) { $hits[] = basename( $f ) . ': ' . $p; } }
	if ( 'class-seohc-mailer.php' === basename( $f ) && false !== strpos( $c, 'SEOHC_Admin::' ) ) { $hits[] = 'class-seohc-mailer.php: SEOHC_Admin::'; }
}
check( 'AC-019 statische scan: geen verboden patronen (7 patronen)', [] === $hits, implode( ' | ', $hits ) );
check( 'AC-019 mailer gebruikt tab_url()', false !== strpos( (string) file_get_contents( $mod . '/includes/class-seohc-mailer.php' ), 'PDK_SEO_Health_Check::tab_url' ) );

// ============================================================================
echo "\nAC-037 (H) Ctrl+S-guard in admin-save.js\n";
// ============================================================================
$js    = (string) file_get_contents( $pdk . '/assets/js/admin-save.js' );
$guard = strpos( $js, "closest('.shc-panel')" );
$click = strpos( $js, 'button.click()' );
check( "AC-037 admin-save.js bevat de guard closest('.shc-panel')", false !== $guard );
check( 'AC-037 de guard staat vóór button.click()', false !== $guard && false !== $click && $guard < $click );
check( 'AC-037 de guard keert terug (return) zonder preventDefault ervoor', false !== $guard && (bool) preg_match( "/closest\('\.shc-panel'\)\)\s*return;/", $js ) && $guard < strpos( $js, 'e.preventDefault()' ) );

// ============================================================================
echo "\nAC-017 Release\n";
// ============================================================================
$main = file( $pdk . '/pdk-theme-options-plugin.php' );
check( 'AC-017 Version: 2.14.0 op regel 6', 1 === preg_match( '/^\s*\*\s*Version:\s*2\.14\.0\s*$/', $main[5] ?? '' ), trim( $main[5] ?? '' ) );
check( "AC-017 define PDK_PLUGIN_VERSION '2.14.0' op regel 24", 1 === preg_match( "/define\(\s*'PDK_PLUGIN_VERSION'\s*,\s*'2\.14\.0'\s*\)/", $main[23] ?? '' ), trim( $main[23] ?? '' ) );
$cl = (string) file_get_contents( $root . '/CHANGELOG.md' );
preg_match( '/^## \[.*$/m', $cl, $first );
check( 'AC-017 eerste versiekop is "## [2.14.0] — 2026-11-13"', '## [2.14.0] — 2026-11-13' === trim( $first[0] ?? '' ), $first[0] ?? '' );
preg_match( '/^## \[2\.14\.0\].*?\R(.*?)(?=^## \[|\z)/ms', $cl, $m );
$sec = $m[1] ?? '';
check( 'AC-017 CHANGELOG 2.14.0 heeft inhoud', strlen( $sec ) > 500 );
$need = [
	'de module, standaard uit'                         => '/SEO Health Check.*standaard uit/is',
	'tab met 6 sub-tabs'                               => '/zes sub-tabs|6 sub-tabs/i',
	'overstap van de losse plugin'                     => '/Overstappen van de losse plugin/i',
	'waarschuwing: niet via Plugins → Verwijderen'     => '/Verwijder (hem|de losse plugin) niet via Plugins/i',
	'rm -r / FTP of shell'                             => '/rm -r wp-content\/plugins\/seo-health-check/',
	'updater en Plugins-links vervallen'               => '/Vervallen\.?\*{0,2}.*updater.*links op het Plugins-scherm/is',
	'alleen beheerders (manage_options)'               => '/Alleen voor beheerders.*manage_options/is',
	'ook als de filter lager staat (seo_health_check_capability)' => '/seo_health_check_capability/',
	'mailer-fix'                                       => '/rapportmail vanuit cron/i',
	'installer ongewijzigd'                            => '/pdk-mu-installer\/.*ongewijzigd/i',
];
foreach ( $need as $naam => $re ) { check( "AC-017 CHANGELOG noemt: {$naam}", 1 === preg_match( $re, $sec ) ); }

// ============================================================================
echo "\nAC-018 README en moduledoc\n";
// ============================================================================
$readme = (string) file_get_contents( $root . '/README.md' );
check( 'AC-018 README heeft de rij [**SEO Health Check**](docs/modules/seo-health-check.md)', false !== strpos( $readme, '[**SEO Health Check**](docs/modules/seo-health-check.md)' ) );
$doc = (string) @file_get_contents( $root . '/docs/modules/seo-health-check.md' );
check( 'AC-018 docs/modules/seo-health-check.md bestaat', '' !== $doc );
$need = [
	'de tab (seohc_view, zes sub-tabs)' => '/seohc_view.*issues.*pages.*scans.*schedule.*launch.*settings/s',
	'de overstap'                       => '/Overstappen van de losse plugin/',
	'waarschuwing D26 (niet via Verwijderen)' => '/niet via Plugins → Verwijderen/',
	'rm -r'                             => '/rm -r wp-content\/plugins\/seo-health-check/',
	'wat de uninstall wist'             => '/PDK verwijderen.*wist de drie tabellen/s',
	'de dev.local-scripts'              => '/dev\.local.*tests\/live\//s',
	'de bron 0.10.1'                    => '/0\.10\.1/',
];
foreach ( $need as $naam => $re ) { check( "AC-018 moduledoc beschrijft: {$naam}", 1 === preg_match( $re, $doc ) ); }

// ============================================================================
echo "\nAC-015 Uninstall (kindprocessen met stubs)\n";
// ============================================================================
$harness_file = sys_get_temp_dir() . '/pdk-seohc-harness-' . getmypid() . '.php';
file_put_contents( $harness_file, $harness_code );
register_shutdown_function( static function () use ( $harness_file ) { @unlink( $harness_file ); } );
$un = (string) file_get_contents( $pdk . '/uninstall.php' );
check( 'AC-015 pdk uninstall.php laadt modules/seo-health-check/uninstall.php met require', 1 === preg_match( "~^\s*require(_once)?\s+__DIR__\s*\.\s*'/modules/seo-health-check/uninstall\.php'\s*;~m", $un ) );
check( 'AC-015 modulebestand begint met de WP_UNINSTALL_PLUGIN-guard', false !== strpos( (string) file_get_contents( $mod . '/uninstall.php' ), "defined( 'WP_UNINSTALL_PLUGIN' ) || exit;" ) );

$run = static function ( array $scen ) {
	$out = [];
	global $harness_file, $root;
	$cmd = escapeshellarg( PHP_BINARY ) . ' -d zend.assertions=1 -d assert.exception=1 ' . escapeshellarg( $harness_file ) . ' 2>&1';
	putenv( 'PDK_T_SCEN=' . json_encode( $scen ) );
	putenv( 'PDK_T_ROOT=' . $root );
	exec( $cmd, $out, $code );
	putenv( 'PDK_T_SCEN' );
	putenv( 'PDK_T_ROOT' );
	$j = json_decode( implode( "\n", $out ), true );
	return [ $code, $j, implode( "\n", $out ) ];
};
$drops = static function ( $r ) { return is_array( $r[1] ) ? count( $r[1]['drops'] ) : -1; };

$r = $run( [ 'active' => [ 'akismet/akismet.php' ] ] );
check( 'AC-015 niet actief: precies 3x DROP', 0 === $r[0] && 3 === $drops( $r ), $r[2] );
check( 'AC-015 niet actief: de 3 tabellen zijn issues, pages, links', is_array( $r[1] ) && [ 'wp_seohc_issues', 'wp_seohc_pages', 'wp_seohc_links' ] === array_column( $r[1]['drops'], 'table' ) );
$r = $run( [ 'active' => [ 'seo-health-check/seo-health-check.php' ] ] );
check( 'AC-015 seo-health-check/seo-health-check.php actief: 0x DROP', 0 === $r[0] && 0 === $drops( $r ), $r[2] );
$r = $run( [ 'active' => [ 'seohc-oud/seo-health-check.php' ] ] );
check( 'AC-015 seohc-oud/seo-health-check.php (andere mapnaam): 0x DROP', 0 === $r[0] && 0 === $drops( $r ), $r[2] );
$r = $run( [ 'active' => [ 'seo-health-check.php' ] ] );
check( 'AC-015 kaal seo-health-check.php actief: 0x DROP', 0 === $r[0] && 0 === $drops( $r ), $r[2] );
$r = $run( [ 'active' => [ 'andere/not-seo-health-check.phpx', 'x/seo-health-check.php.bak' ] ] );
check( 'AC-015 lijkt-op-maar-is-niet (namen die niet op /seo-health-check.php eindigen): 3x DROP', 0 === $r[0] && 3 === $drops( $r ), $r[2] );
$r = $run( [ 'multisite' => true, 'sites' => [ 1, 2 ], 'active_by_site' => [ '1' => [], '2' => [ 'seo-health-check/seo-health-check.php' ] ] ] );
check( 'AC-015 multisite, losse plugin actief op site 2: 3x DROP', 0 === $r[0] && 3 === $drops( $r ), $r[2] );
check( 'AC-015 multisite: de DROPs zijn alleen voor site 1', is_array( $r[1] ) && [ 1 ] === array_values( array_unique( array_column( $r[1]['drops'], 'blog' ) ) ) );
$r = $run( [ 'multisite' => true, 'sites' => [ 1, 2 ], 'active_by_site' => [ '1' => [], '2' => [] ] ] );
check( 'AC-015 multisite, nergens actief: 6x DROP (beide sites)', 0 === $r[0] && 6 === $drops( $r ), $r[2] );
check( 'AC-015 get_sites wordt aangeroepen met number => 0 (en fields => ids)', is_array( $r[1] ) && 0 === ( $r[1]['sites_args']['number'] ?? null ) && 'ids' === ( $r[1]['sites_args']['fields'] ?? null ), json_encode( $r[1]['sites_args'] ?? null ) );
$r = $run( [ 'multisite' => true, 'sites' => [ 1, 2 ], 'network' => [ 'seohc-oud/seo-health-check.php' ], 'active_by_site' => [ '1' => [], '2' => [] ] ] );
check( 'AC-015 netwerkbreed actief: 0x DROP', 0 === $r[0] && 0 === $drops( $r ), $r[2] );

// ============================================================================
echo "\nAC-016 (H) PDK_Plugin::deactivate()\n";
// ============================================================================
require_once $pdk . '/modules/imgx/includes/class-generator.php';
$seohc_hooks = [ 'seohc_process_batch', 'seohc_scan_single_post', 'seohc_scheduled_scan' ];
$reset = static function ( array $opts ) {
	$GLOBALS['opts']  = $opts;
	$GLOBALS['calls'] = [];
};
$unscheduled = static function () {
	$n = [];
	foreach ( $GLOBALS['calls'] as $c ) { if ( 'wp_unschedule_hook' === $c[0] && 0 === strpos( $c[1], 'seohc_' ) ) { $n[] = $c[1]; } }
	return $n;
};
$state0 = [ 'status' => 'running', 'processed' => 3 ];

// a) module niet geboot (en de losse plugin niet actief): hooks wel afmelden, scanstatus ongemoeid.
$reset( [ 'seohc_scan_state' => $state0 ] );
PDK_Plugin::deactivate();
check( 'AC-016 niet geboot: de 3 hooks worden toch afgemeld', $seohc_hooks === $unscheduled(), implode( ',', $unscheduled() ) );
check( 'AC-016 niet geboot: as_unschedule_all_actions(seohc_process_batch) aangeroepen', in_array( [ 'as_unschedule_all_actions', 'seohc_process_batch' ], $GLOBALS['calls'], true ) );
check( 'AC-016 niet geboot: seohc_scan_state ongewijzigd', $state0 === $GLOBALS['opts']['seohc_scan_state'] && ! in_array( [ 'update_option', 'seohc_scan_state' ], $GLOBALS['calls'], true ) );
check( 'AC-016 niet geboot: SEOHC_Installer::deactivate() niet aangeroepen', 0 === SEOHC_Installer::$called );

// b) losse plugin actief: niets afmelden.
foreach ( [ 'seo-health-check/seo-health-check.php', 'seohc-oud/seo-health-check.php' ] as $plugin ) {
	$reset( [ 'active_plugins' => [ $plugin ], 'seohc_scan_state' => $state0 ] );
	PDK_Plugin::deactivate();
	check( "AC-016 losse plugin actief ({$plugin}): niets afgemeld", [] === $unscheduled() && ! in_array( [ 'as_unschedule_all_actions', 'seohc_process_batch' ], $GLOBALS['calls'], true ), implode( ',', $unscheduled() ) );
	check( "AC-016 losse plugin actief ({$plugin}): scanstatus ongewijzigd", $state0 === $GLOBALS['opts']['seohc_scan_state'] );
}

// c) module geboot: SEOHC_Installer::deactivate() draait ook (status cancelled + lock weg zit daarin).
class SEOHC_Installer { public static $called = 0; public static function deactivate() { self::$called++; } }
( new ReflectionClass( 'PDK_SEO_Health_Check' ) )->setStaticPropertyValue( 'booted', true );
$reset( [] );
PDK_Plugin::deactivate();
check( 'AC-016 geboot: hooks afgemeld en SEOHC_Installer::deactivate() aangeroepen', $seohc_hooks === $unscheduled() && 1 === SEOHC_Installer::$called );
$reset( [ 'active_plugins' => [ 'seo-health-check/seo-health-check.php' ] ] );
SEOHC_Installer::$called = 0;
PDK_Plugin::deactivate();
check( 'AC-016 geboot maar losse plugin actief: niets, ook geen SEOHC_Installer::deactivate()', [] === $unscheduled() && 0 === SEOHC_Installer::$called );

// ============================================================================
echo "\nAC-035 Kop-helpers (pure functies op strings)\n";
// ============================================================================
// Minimale stubs van WordPress-functies die de helpers aanroepen; de echte worden live getest (seohc-abilities.php).
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $c;
		public function __construct( $c = '', $m = '' ) { $this->c = $c; }
		public function get_error_code() { return $this->c; }
	}
}
class WP_Post {
	public $ID = 5; public $post_content = '<h1>Kop</h1><p>tekst</p>'; public $post_status = 'publish';
	public function __construct( array $a = [] ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}
class SEOHC_Scanner { public static function is_scannable( $p ) { return 'publish' === $p->post_status; } }
class SEOHC_Settings { public static function get( $k ) { return $GLOBALS['seohc_setting'][ $k ] ?? ''; } }
$GLOBALS['seohc_setting'] = [];
$GLOBALS['t_meta']        = [];
$GLOBALS['t_caps']        = [ 'unfiltered_html' => true ];
$GLOBALS['t_revs']        = true;
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s ) ) ); }
function sanitize_text_field( $s ) { return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function has_blocks( $c ) { return false !== strpos( (string) $c, '<!-- wp:' ); }
function metadata_exists( $t, $id, $k ) { return isset( $GLOBALS['t_meta'][ $k ] ); }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['t_meta'][ $k ] ?? ''; }
function current_user_can( $c ) { return ! empty( $GLOBALS['t_caps'][ $c ] ); }
function wp_revisions_enabled( $p ) { return $GLOBALS['t_revs']; }

$bs     = chr( 92 );
$marker = '<!-- wp:paragraph {"placeholder":"a' . $bs . 'u0026b"} --><p>C:' . $bs . 'pad</p><!-- /wp:paragraph -->';
$H      = 'PDK_SEO_Health_Check';
$err    = static fn( $r ) => $r instanceof WP_Error ? $r->get_error_code() : 'geen-fout';

// headings(): commentaar overslaan, volgorde, tekst.
$classic = '<h1>Intro</h1>' . "\n" . '<h2>Deel</h2><!-- <h3>in commentaar</h3> -->' . "\n" . '<h4>Detail</h4>' . "\n" . $marker;
$hs      = $H::headings( $classic );
check( 'AC-035 headings() slaat HTML-commentaar over (3 koppen)', 3 === count( $hs ), (string) count( $hs ) );
check( 'AC-035 headings(): levels 1,2,4 en teksten in documentvolgorde', [ 1, 2, 4 ] === array_column( $hs, 'level' ) && [ 'Intro', 'Deel', 'Detail' ] === array_column( $hs, 'text' ) && [ 0, 1, 2 ] === array_column( $hs, 'index' ) );
check( 'AC-035 klassieke koppen: block=false, editable=true, reason=null', ! $hs[2]['block'] && $hs[2]['editable'] && null === $hs[2]['reason'] );
check( 'AC-035 headings() geeft geen interne posities door', ! isset( $hs[0]['offset'] ) && ! isset( $hs[0]['length'] ) && ! isset( $hs[0]['comment'] ) );
check( 'AC-035 headings(): lege content geeft []', [] === $H::headings( '' ) && [] === $H::headings( '<p>geen kop</p>' ) );

// Normalisatie.
check( 'AC-035 normalisatie: <h2>A &amp; <em>b</em> </h2> -> "A & b"', 'A & b' === $H::headings( '<h2>A &amp; <em>b</em> </h2>' )[0]['text'] );
check( 'AC-035 normalize_text(): witruimte samengevoegd, entities gedecodeerd', 'A & b c' === $H::normalize_text( "  A &amp;\n <em>b</em>\t c " ) );

// set_heading_level(): klassiek.
$out = $H::set_heading_level( $classic, 2, 'Detail', 3 );
check( 'AC-035 set_heading_level klassiek: alleen <h4>/</h4> -> <h3>/</h3>', $out === str_replace( '<h4>Detail</h4>', '<h3>Detail</h3>', $classic ), is_string( $out ) ? $out : $err( $out ) );
check( 'AC-035 set_heading_level klassiek: markering met backslashes byte-gelijk', is_string( $out ) && false !== strpos( $out, $marker ) );
check( 'AC-035 set_heading_level: het commentaar met een h3 blijft onaangeroerd', is_string( $out ) && false !== strpos( $out, '<!-- <h3>in commentaar</h3> -->' ) );
check( 'AC-035 expected_text wordt genormaliseerd vergeleken', is_string( $H::set_heading_level( '<h2>A &amp; <em>b</em></h2>', 0, ' A &  b ', 3 ) ) );

// core/heading.
$blk = '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Blok detail</h4><!-- /wp:heading -->' . $marker;
$o2  = $H::set_heading_level( $blk, 0, 'Blok detail', 2 );
check( 'AC-035 core/heading level 2: attribuut level verwijderd + <h2 class="wp-block-heading">', $o2 === '<!-- wp:heading --><h2 class="wp-block-heading">Blok detail</h2><!-- /wp:heading -->' . $marker, is_string( $o2 ) ? $o2 : $err( $o2 ) );
$o3  = $H::set_heading_level( $blk, 0, 'Blok detail', 3 );
check( 'AC-035 core/heading level 3: attribuut level:3 gezet', $o3 === '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Blok detail</h3><!-- /wp:heading -->' . $marker, is_string( $o3 ) ? $o3 : $err( $o3 ) );
$b2 = '<!-- wp:heading --><h2 class="wp-block-heading">Zonder attr</h2><!-- /wp:heading -->';
check( 'AC-035 core/heading zonder attributen, level 3: {"level":3} toegevoegd', '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Zonder attr</h3><!-- /wp:heading -->' === $H::set_heading_level( $b2, 0, 'Zonder attr', 3 ) );
check( 'AC-035 core/heading zonder attributen, level 2 blijft identiek', $b2 === $H::set_heading_level( $b2, 0, 'Zonder attr', 2 ) );
$bx = '<!-- wp:heading {"level":4,"className":"x"} --><h4 class="wp-block-heading x">T</h4><!-- /wp:heading -->';
check( 'AC-035 core/heading met andere attributen, level 2: alleen level verwijderd', '<!-- wp:heading {"className":"x"} --><h2 class="wp-block-heading x">T</h2><!-- /wp:heading -->' === $H::set_heading_level( $bx, 0, 'T', 2 ) );
$by = '<!-- wp:heading {"className":"x","level":4} --><h4 class="wp-block-heading x">T</h4><!-- /wp:heading -->';
check( 'AC-035 core/heading, level achteraan, level 2: geldige JSON blijft over', '<!-- wp:heading {"className":"x"} --><h2 class="wp-block-heading x">T</h2><!-- /wp:heading -->' === $H::set_heading_level( $by, 0, 'T', 2 ) );
check( 'AC-035 core/heading met andere attributen, level 5: level vervangen', '<!-- wp:heading {"level":5,"className":"x"} --><h5 class="wp-block-heading x">T</h5><!-- /wp:heading -->' === $H::set_heading_level( $bx, 0, 'T', 5 ) );
$bz = '<!-- wp:heading {"className":"x"} --><h2 class="wp-block-heading x">T</h2><!-- /wp:heading -->';
check( 'AC-035 core/heading level 2->3 met alleen className: level vooraan toegevoegd', '<!-- wp:heading {"level":3,"className":"x"} --><h3 class="wp-block-heading x">T</h3><!-- /wp:heading -->' === $H::set_heading_level( $bz, 0, 'T', 3 ) );

// Kop in een ander blok.
$html  = '<!-- wp:html --><h3>In html-blok</h3><!-- /wp:html -->';
$group = '<!-- wp:group --><div class="wp-block-group"><!-- wp:html --><h3>Diep</h3><!-- /wp:html --></div><!-- /wp:group -->';
foreach ( [ 'core/html' => $html, 'core/group > core/html' => $group, 'kale h3 in core/group' => '<!-- wp:group --><div><h3>Kaal</h3></div><!-- /wp:group -->' ] as $naam => $c ) {
	$h = $H::headings( $c )[0];
	check( "AC-035 kop in {$naam}: editable=false, reason=unsupported_block, block=true", ! $h['editable'] && 'unsupported_block' === $h['reason'] && $h['block'] );
	check( "AC-035 set_heading_level op {$naam}: pdk_seo_unsupported_block", 'pdk_seo_unsupported_block' === $err( $H::set_heading_level( $c, 0, $h['text'], 2 ) ) );
}
$mix = $blk . $html;
check( 'AC-035 gemengd: kop 0 (core/heading) editable, kop 1 (core/html) niet', $H::headings( $mix )[0]['editable'] && ! $H::headings( $mix )[1]['editable'] );
$x = $H::set_heading_level( $mix, 0, 'Blok detail', 2 );
check( 'AC-035 gemengd: wijzigen van kop 0 laat het html-blok en de markering byte-gelijk', is_string( $x ) && str_ends_with( $x, $html ) && false !== strpos( $x, $marker ) );

// Fouten.
check( 'AC-035 verkeerde expected_text: pdk_seo_heading_mismatch', 'pdk_seo_heading_mismatch' === $err( $H::set_heading_level( $classic, 2, 'Fout', 3 ) ) );
check( 'AC-035 index buiten bereik: pdk_seo_not_found', 'pdk_seo_not_found' === $err( $H::set_heading_level( $classic, 3, 'Detail', 3 ) ) );
check( 'AC-035 negatieve index: pdk_seo_not_found', 'pdk_seo_not_found' === $err( $H::set_heading_level( $classic, -1, 'Intro', 3 ) ) );
check( 'AC-035 index op lege content: pdk_seo_not_found', 'pdk_seo_not_found' === $err( $H::set_heading_level( '', 0, 'x', 3 ) ) );

// insert_h1(): exacte markup uit D21.
check( 'AC-035 insert_h1 met blokken: exacte D21-markup', "<!-- wp:heading {\"level\":1} -->\n<h1 class=\"wp-block-heading\">Blok kop</h1>\n<!-- /wp:heading -->\n\n" . $blk === $H::insert_h1( $blk, 'Blok kop' ) );
check( 'AC-035 insert_h1 klassiek: <h1>TEKST</h1> + "\n"', "<h1>Kop</h1>\n<p>x</p>" === $H::insert_h1( '<p>x</p>', 'Kop' ) );
check( 'AC-035 insert_h1 op lege content (klassiek)', "<h1>Kop</h1>\n" === $H::insert_h1( '', 'Kop' ) );
check( 'AC-035 insert_h1: \'Nieuwe <b>kop</b>\' -> eerst gestript -> <h1>Nieuwe kop</h1>', "<h1>Nieuwe kop</h1>\n" === $H::insert_h1( '', 'Nieuwe <b>kop</b>' ) );
check( 'AC-035 insert_h1: daarna geescaped met esc_html (& wordt &amp;)', "<h1>A &amp; b</h1>\n" === $H::insert_h1( '', 'A & <b>b</b>' ) );
check( 'AC-035 insert_h1: de rest van de content blijft byte-gelijk', str_ends_with( $H::insert_h1( $classic, 'X' ), $classic ) );
check( 'AC-035 insert_h1 met blokken: markering met backslashes byte-gelijk', false !== strpos( $H::insert_h1( $blk, 'X' ), $marker ) );

// editable_reason().
$reason = static function ( array $post = [], array $meta = [], array $caps = [ 'unfiltered_html' => true ], bool $revs = true, array $set = [] ) use ( $H ) {
	$GLOBALS['t_meta'] = $meta; $GLOBALS['t_caps'] = $caps; $GLOBALS['t_revs'] = $revs; $GLOBALS['seohc_setting'] = $set;
	return $H::editable_reason( new WP_Post( $post ) );
};
check( 'AC-035 editable_reason: gewone post met alles in orde = ""', '' === $reason() );
check( 'AC-035 editable_reason: lege content = builder_content', 'builder_content' === $reason( [ 'post_content' => '' ] ) );
check( 'AC-035 editable_reason: alleen tags/witruimte = builder_content', 'builder_content' === $reason( [ 'post_content' => "<div> <!-- x --> </div>\n" ] ) );
foreach ( [ 'ct_builder_shortcodes', 'ct_builder_json', '_oxygen_data', '_breakdance_data', '_elementor_edit_mode', '_bricks_page_content_2', '_fl_builder_enabled' ] as $key ) {
	check( "AC-035 editable_reason: buildersleutel {$key} = builder_content", 'builder_content' === $reason( [], [ $key => 'x' ] ) );
}
check( 'AC-035 BUILDER_META bevat precies de 7 sleutels uit D20(c)', 7 === count( $H::BUILDER_META ) && [] === array_diff( [ 'ct_builder_shortcodes', 'ct_builder_json', '_oxygen_data', '_breakdance_data', '_elementor_edit_mode', '_bricks_page_content_2', '_fl_builder_enabled' ], $H::BUILDER_META ) );
check( 'AC-035 editable_reason: Divi _et_pb_use_builder=on = builder_content', 'builder_content' === $reason( [], [ '_et_pb_use_builder' => 'on' ] ) );
check( 'AC-035 editable_reason: Divi _et_pb_use_builder=off = ""', '' === $reason( [], [ '_et_pb_use_builder' => 'off' ] ) );
check( 'AC-035 editable_reason: concept = not_scannable', 'not_scannable' === $reason( [ 'post_status' => 'draft' ] ) );
check( 'AC-035 editable_reason: content_source=rendered = rendered_source', 'rendered_source' === $reason( [], [], [ 'unfiltered_html' => true ], true, [ 'content_source' => 'rendered' ] ) );
check( 'AC-035 editable_reason: zonder unfiltered_html = unfiltered_html', 'unfiltered_html' === $reason( [], [], [] ) );
check( 'AC-035 editable_reason: revisies uit = revisions_disabled', 'revisions_disabled' === $reason( [], [], [ 'unfiltered_html' => true ], false ) );
check( 'AC-035 editable_reason: volgorde (not_scannable gaat voor builder; builder voor unfiltered_html)', 'not_scannable' === $reason( [ 'post_status' => 'draft', 'post_content' => '' ], [], [], false ) && 'builder_content' === $reason( [], [ '_oxygen_data' => 'x' ], [], false ) );

// ============================================================================
echo "\nAC-036 Documentatie slice 2\n";
// ============================================================================
$names  = [ 'seo-get-status', 'seo-list-issues', 'seo-list-pages', 'seo-get-scan-history', 'seo-get-launch-checks', 'seo-get-headings', 'seo-start-scan', 'seo-rescan-post', 'seo-update-meta', 'seo-update-image-alt', 'seo-set-heading-level', 'seo-insert-h1' ];
$aa     = (string) file_get_contents( $root . '/docs/modules/agent-abilities.md' );
$inputs = [
	'seo-list-issues' => [ 'issue_type', 'status', 'post_id', 'severity', 'per_page' ], 'seo-list-pages' => [ 'band', 'post_type', 'per_page' ],
	'seo-get-headings' => [ 'post_id' ], 'seo-rescan-post' => [ 'post_id' ], 'seo-update-meta' => [ 'post_id', 'field', 'value' ],
	'seo-update-image-alt' => [ 'attachment_id', 'value', 'post_id' ], 'seo-set-heading-level' => [ 'post_id', 'index', 'expected_text', 'level' ], 'seo-insert-h1' => [ 'post_id', 'text' ],
];
foreach ( $names as $n ) {
	preg_match( '/^\|\s*`' . preg_quote( $n, '/' ) . '`\s*\|(.*)$/m', $aa, $row );
	check( "AC-036 agent-abilities.md heeft een rij voor {$n}", ! empty( $row ) );
	$miss = [];
	foreach ( $inputs[ $n ] ?? [] as $in ) {
		if ( false === strpos( $row[1] ?? '', '`' . $in . '`' ) ) { $miss[] = $in; }
	}
	check( "AC-036 agent-abilities.md: rij {$n} noemt de inputs", [] === $miss, implode( ',', $miss ) );
}
$sd = (string) file_get_contents( $root . '/docs/modules/seo-health-check.md' );
foreach ( [ 'pdk_seo_not_found', 'pdk_seo_not_scannable', 'pdk_seo_builder_content', 'pdk_seo_rendered_source', 'pdk_seo_unfiltered_html', 'pdk_seo_revisions_disabled', 'pdk_seo_unsupported_block', 'pdk_seo_heading_mismatch', 'pdk_seo_theme_h1', 'pdk_seo_h1_exists', 'pdk_seo_no_seo_plugin', 'content_skipped', '_oxygen_data' ] as $term ) {
	check( "AC-036 seo-health-check.md noemt {$term}", false !== strpos( $sd, $term ) );
}
check( 'AC-036 agent-abilities.md noemt pdk_seo_scan_running en ability_invalid_permissions', false !== strpos( $aa, 'pdk_seo_scan_running' ) && false !== strpos( $aa, 'ability_invalid_permissions' ) );
check( 'AC-036 seo-health-check.md noemt D28 en de revisie vóór het schrijven', 1 === preg_match( '/D28/', $sd ) && 1 === preg_match( '/revisie/i', $sd ) );
$cl = (string) file_get_contents( $root . '/CHANGELOG.md' );
preg_match( '/^## \[[^\]]+\].*?(?=^## \[)/ms', $cl, $first );
$sec = $first[0] ?? '';
check( 'AC-036 CHANGELOG (eerste kop) noemt de SEO-abilities (alle 12 namen)', '' !== $sec && [] === array_filter( $names, static fn( $n ) => false === strpos( $sec, '`' . $n . '`' ) ) );
check( 'AC-036 CHANGELOG noemt de slash-fix (update_alt_in_content, wp_slash, backslashes)', false !== strpos( $sec, 'update_alt_in_content' ) && false !== strpos( $sec, 'wp_slash' ) && false !== stripos( $sec, 'backslash' ) );
$st = (string) file_get_contents( $pdk . '/includes/class-pdk-settings.php' );
$d = [ 1 => null ];
foreach ( explode( "
", $st ) as $line ) { // de omschrijving staat op de regel met 'agent_abilities' die "SEO" noemt (de eerste is het label)
	if ( false !== strpos( $line, "'agent_abilities'" ) && false !== strpos( $line, 'Abilities API' ) ) { $d[1] = $line; }
}
check( 'AC-036 class-pdk-settings.php: omschrijving agent_abilities noemt de SEO-abilities en manage_options', isset( $d[1] ) && false !== strpos( $d[1], 'SEO-abilities' ) && false !== strpos( $d[1], 'manage_options' ), $d[1] ?? 'geen match' );

// ============================================================================
echo "\n";
if ( $GLOBALS['t_fail'] > 0 ) {
	echo 'MISLUKT: ' . $GLOBALS['t_fail'] . ' van ' . $GLOBALS['t_checks'] . " checks\n";
	exit( 1 );
}
echo 'OK: ' . $GLOBALS['t_checks'] . " checks geslaagd\n";
