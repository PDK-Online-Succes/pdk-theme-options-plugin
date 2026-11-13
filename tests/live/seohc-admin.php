<?php
/**
 * Admin-checks (dev.local, WP-CLI) voor de SEO Health Check-module: AC-002 (admin-deel), AC-008 t/m AC-011,
 * AC-013 (HTML-deel) en AC-037 (L). Bootstrap volgens brief §6. Draaien (na seed en een geslaagde scan):
 *
 *   wpl --user=1 --exec='define("WP_ADMIN", true); $_SERVER["HTTP_HOST"]="dev.local";' eval-file tests/live/seohc-admin.php [off|mail]
 *
 *   (geen arg)  AC-008, AC-009, AC-010, AC-011, AC-013 (HTML), AC-037 (L)
 *   off         AC-002 admin-deel (module uit)
 *   mail        AC-010 "mail zonder admin-context": draaien ZONDER WP_ADMIN in --exec
 *
 * Een set_error_handler() vangt notices/warnings op; elke opgevangen melding laat de check falen.
 * Exit 0 = geslaagd.
 */

if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

$mode = isset( $args[0] ) ? $args[0] : 'on';
$fail = 0;
$out  = []; // Pas aan het eind afdrukken: eerdere uitvoer geeft "headers already sent"-meldingen van WooCommerce.
$ok   = static function ( $name, $cond, $detail = '' ) use ( &$fail, &$out ) {
	$out[] = ( $cond ? 'PASS' : 'FAIL' ) . '  ' . $name . ( '' !== $detail ? '  [' . $detail . ']' : '' );
	if ( ! $cond ) { $fail++; }
};
$finish = static function () use ( &$fail, &$mode, &$out ) {
	echo implode( "\n", $out ) . "\n";
	echo ( $fail ? "RESULT[{$mode}]: {$fail} FAIL\n" : "RESULT[{$mode}]: ALL PASS\n" );
	exit( $fail ? 1 : 0 );
};

$caught = [];
set_error_handler( static function ( $no, $str, $file, $line ) use ( &$caught ) {
	$caught[] = "[{$no}] {$str} @ " . basename( $file ) . ":{$line}";
	return true;
} );

// ---------------------------------------------------------------------------
// mail zonder admin-context (AC-010, minor 9)
// ---------------------------------------------------------------------------
if ( 'mail' === $mode ) {
	$ok( 'proces draait zonder WP_ADMIN', ! is_admin() );
	$ok( 'SEOHC_Admin is niet geladen (geen admin-context)', ! class_exists( 'SEOHC_Admin', false ) );
	$body = null;
	add_filter( 'pre_wp_mail', static function ( $pre, $atts ) use ( &$body ) { $body = $atts['message']; return true; }, 10, 2 );
	update_option( 'seohc_schedule', array_merge( (array) get_option( 'seohc_schedule', [] ), [ 'email' => 'seohc-test@example.test' ] ) );
	$res = null;
	try {
		$res = SEOHC_Mailer::send_report( true );
	} catch ( \Throwable $e ) {
		$ok( 'send_report(true) zonder fatal', false, get_class( $e ) . ': ' . $e->getMessage() );
		$finish();
	}
	$ok( 'send_report(true) zonder fatal en niet WP_Error', ! is_wp_error( $res ), is_wp_error( $res ) ? $res->get_error_message() : '' );
	$plain = html_entity_decode( (string) $body, ENT_QUOTES );
	$ok( 'mailbody bevat tab=seo_health_check&seohc_view=issues', false !== strpos( $plain, 'tab=seo_health_check&seohc_view=issues' ) );
	$ok( 'mailbody bevat geen oude slug', ! preg_match( '/page=seo-health-check|name="page" value="seo-health-check/', (string) $body ) );
	update_option( 'seohc_schedule', array_merge( (array) get_option( 'seohc_schedule', [] ), [ 'email' => '' ] ) );
	$ok( 'geen opgevangen notices/warnings', [] === $caught, implode( ' | ', $caught ) );
	$finish();
}

// ---------------------------------------------------------------------------
// bootstrap (brief §6, stap 1-4)
// ---------------------------------------------------------------------------
$ok( 'WP_ADMIN actief', is_admin() );
require_once ABSPATH . 'wp-admin/includes/admin.php';
$SLUG = 'pdk-theme-options';
$HOOK = 'toplevel_page_pdk-theme-options';

$set_request = static function ( array $get ) use ( $SLUG ) {
	$_GET = $_REQUEST = $get;
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?' . http_build_query( $_GET );
	$GLOBALS['pagenow']     = 'admin.php';
	$GLOBALS['plugin_page'] = $SLUG;
	$GLOBALS['hook_suffix'] = 'toplevel_page_pdk-theme-options';
};
$set_request( [ 'page' => $SLUG, 'tab' => 'modules' ] );
do_action( 'admin_menu' );
do_action( 'admin_init' );

// ---------------------------------------------------------------------------
// AC-002 admin-deel (module uit)
// ---------------------------------------------------------------------------
$submenu_slugs = array_map( static function ( $i ) { return $i[2]; }, (array) ( $GLOBALS['submenu'][ $SLUG ] ?? [] ) );
$menu_slugs    = array_map( static function ( $i ) { return $i[2]; }, (array) $GLOBALS['menu'] );
if ( 'off' === $mode ) {
	$ok( 'AC-002 module uit', empty( PDK_Settings::get( 'seo_health_check' )['enabled'] ) );
	$ok( 'AC-002 geen seo-health-check in $menu', ! in_array( 'seo-health-check', $menu_slugs, true ) );
	$ok( 'AC-002 geen pdk-theme-options&tab=seo_health_check in submenu', ! in_array( 'pdk-theme-options&tab=seo_health_check', $submenu_slugs, true ), implode( ',', $submenu_slugs ) );
	$ok( 'AC-002 geen opgevangen notices/warnings', [] === $caught, implode( ' | ', $caught ) );
	$finish();
}

// ---------------------------------------------------------------------------
// AC-008
// ---------------------------------------------------------------------------
$ok( 'AC-008 geen seo-health-check in $menu', ! in_array( 'seo-health-check', $menu_slugs, true ) );
$ok( 'AC-008 $submenu[seo-health-check] bestaat niet', ! isset( $GLOBALS['submenu']['seo-health-check'] ) );
$label = '';
foreach ( (array) $GLOBALS['submenu'][ $SLUG ] as $item ) {
	if ( 'pdk-theme-options&tab=seo_health_check' === $item[2] ) { $label = $item[0]; }
}
$ok( 'AC-008 submenu bevat pdk-theme-options&tab=seo_health_check met label "SEO Health"', 'SEO Health' === $label, $label );

// ---------------------------------------------------------------------------
// render per view (stap 5-8)
// ---------------------------------------------------------------------------
$render = static function ( $view, array $extra = [] ) use ( $set_request, $SLUG, $HOOK ) {
	$set_request( array_merge( [ 'page' => $SLUG, 'tab' => 'seo_health_check', 'seohc_view' => $view ], $extra ) );
	set_current_screen( $HOOK );
	do_action( 'load-' . $HOOK );
	do_action( 'admin_enqueue_scripts', $HOOK );
	ob_start();
	do_action( $HOOK );
	return ob_get_clean();
};
$views = [ 'issues', 'pages', 'scans', 'schedule', 'launch', 'settings' ];
$must  = [
	'issues'   => [ 'id="shc-scan-panel"', 'value="seohc_start_scan"', 'SEOHC dummy S12' ],
	'pages'    => [ 'id="seohc-pages-search-search-input"' ],
	'scans'    => [ 'class="wp-list-table widefat striped seohc-runs"' ],
	'schedule' => [ "~name=[\"']option_page[\"']\\s+value=[\"']seohc_schedule_group[\"']~" ],
	'launch'   => [ 'value="seohc_run_launch_checks"' ],
	'settings' => [ "~value=[\"']seohc_settings_group[\"']~",'name="seohc_settings[post_types][]"' ],
];
delete_user_meta( get_current_user_id(), 'seohc_issues_per_page' );
delete_user_meta( get_current_user_id(), 'seohc_pages_per_page' );

$html = [];
foreach ( $views as $v ) {
	$before = count( $caught );
	$html[ $v ] = $render( $v, 'issues' === $v ? [ 's' => 'SEOHC dummy' ] : [] );
	$h = $html[ $v ];
	$ok( "AC-009 [{$v}] geen notice/warning opgevangen", count( $caught ) === $before, implode( ' | ', array_slice( $caught, $before ) ) );
	$ok( "AC-009 [{$v}] actieve tab is SEO Health", (bool) preg_match( '/nav-tab nav-tab-active"[^>]*>\s*SEO Health\s*</', $h ) );
	$links = 0;
	foreach ( $views as $w ) {
		if ( preg_match( '/href="[^"]*tab=seo_health_check(?:&|&amp;|&#038;)seohc_view=' . $w . '"/', $h ) ) { $links++; }
	}
	$ok( "AC-009 [{$v}] zes links tab=seo_health_check&seohc_view=<v>", 6 === $links, (string) $links );
	$ok( "AC-009 [{$v}] geen class=\"pdk-settings-form\"", false === strpos( $h, 'class="pdk-settings-form"' ) );
	foreach ( $must[ $v ] as $needle ) {
		$is_re = '~' === $needle[0]; // settings_fields() schrijft enkele aanhalingstekens; de brief noemt ze dubbel.
		$ok( "AC-009 [{$v}] bevat {$needle}", $is_re ? (bool) preg_match( $needle, $h ) : false !== strpos( $h, $needle ) );
	}
}
$ok( 'AC-009 [settings] _wp_http_referer met seohc_view=settings', (bool) preg_match( '/name="_wp_http_referer"\s+value="[^"]*seohc_view=settings/', $html['settings'] ) );
$ok( 'AC-013 Issues-HTML bevat "Volledige scan starten"', false !== strpos( $html['issues'], 'Volledige scan starten' ) );

// ---------------------------------------------------------------------------
// AC-010
// ---------------------------------------------------------------------------
$old = '/page=seo-health-check|name="page" value="seo-health-check/';
foreach ( $views as $v ) {
	$ok( "AC-010 [{$v}] geen oude slug", 0 === preg_match_all( $old, $html[ $v ] ) );
}
// Dashboard-widget met en zonder data (zonder data: in een transactie, daarna rollback).
ob_start(); SEOHC_Dashboard_Widget::render(); $w_with = ob_get_clean();
$ok( 'AC-010 widget (met data) heeft links en geen oude slug', false !== strpos( $w_with, 'seohc_view=issues' ) && 0 === preg_match_all( $old, $w_with ) );
global $wpdb;
$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', SEOHC_Repository::pages_table() ) );
$ok( 'AC-010 pagina-tabel is InnoDB (rollback veilig)', 'InnoDB' === $engine, (string) $engine );
$wpdb->query( 'START TRANSACTION' );
$wpdb->query( 'DELETE FROM ' . SEOHC_Repository::pages_table() );
ob_start(); SEOHC_Dashboard_Widget::render(); $w_empty = ob_get_clean();
$wpdb->query( 'ROLLBACK' );
$ok( 'AC-010 widget (zonder data) is de lege variant en heeft de link', false !== strpos( $w_empty, 'seohc_view=issues' ) && false === strpos( $w_empty, 'seohc-widget__score' ) && false !== strpos( $w_with, 'seohc-widget__score' ) );
$ok( 'AC-010 widget (zonder data) geen oude slug', 0 === preg_match_all( $old, $w_empty ) );
$ok( 'AC-010 data na rollback nog aanwezig', (int) SEOHC_Repository::summary()['pages'] > 0 );

// Mailbody (admin-context; de context zonder admin zit in modus "mail").
$body = null;
add_filter( 'pre_wp_mail', $cap = static function ( $pre, $atts ) use ( &$body ) { $body = $atts['message']; return true; }, 10, 2 );
$sched_before = get_option( 'seohc_schedule' );
update_option( 'seohc_schedule', array_merge( (array) $sched_before, [ 'email' => 'seohc-test@example.test' ] ) );
SEOHC_Mailer::send_report( true );
remove_filter( 'pre_wp_mail', $cap, 10 );
$ok( 'AC-010 mailbody opgevangen', is_string( $body ) && '' !== $body );
$ok( 'AC-010 mailbody geen oude slug', 0 === preg_match_all( $old, (string) $body ) );

// Redirects.
class SEOHC_Test_Redirect extends Exception { public $location = ''; }
add_filter( 'wp_redirect', static function ( $location ) { $e = new SEOHC_Test_Redirect( 'redirect' ); $e->location = $location; throw $e; }, 1 );
$s11 = (int) $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_seohc_dummy' AND meta_value='S11'" );
$handlers = [
	[ 'handle_start_scan',  [ 'SEOHC_Admin', 'handle_start_scan' ],        'seohc_start_scan',             [],                    'issues',   true ],
	[ 'handle_cancel_scan', [ 'SEOHC_Admin', 'handle_cancel_scan' ],       'seohc_cancel_scan',            [],                    'issues',   true ],
	[ 'handle_rescan_post', [ 'SEOHC_Admin', 'handle_rescan_post' ],       'seohc_rescan_post_' . $s11,    [ 'post_id' => $s11 ], 'issues',   true ],
	[ 'Launch_Checks::handle_run', [ 'SEOHC_Launch_Checks', 'handle_run' ], 'seohc_run_launch_checks',    [],                    'launch',   false ],
	[ 'Schedule_Page::handle_test', [ 'SEOHC_Schedule_Page', 'handle_test' ], 'seohc_test_report',         [],                    'schedule', true ],
];
add_filter( 'pre_wp_mail', $cap2 = static function () { return true; } );
foreach ( $handlers as [ $name, $cb, $action, $get, $view, $notice ] ) {
	$set_request( array_merge( [ 'page' => $SLUG ], $get ) );
	$_REQUEST['_wpnonce'] = $_GET['_wpnonce'] = wp_create_nonce( $action );
	$loc = '';
	try {
		call_user_func( $cb );
	} catch ( SEOHC_Test_Redirect $e ) {
		$loc = $e->location;
	}
	$q = [];
	parse_str( (string) wp_parse_url( $loc, PHP_URL_QUERY ), $q );
	$ok(
		"AC-010 {$name} -> tab_url({$view})" . ( $notice ? ' + seohc_notice' : '' ),
		'' !== $loc && ( $q['page'] ?? '' ) === $SLUG && ( $q['tab'] ?? '' ) === 'seo_health_check' && ( $q['seohc_view'] ?? '' ) === $view && ( ! $notice || '' !== ( $q['seohc_notice'] ?? '' ) ),
		$loc
	);
}
remove_all_filters( 'wp_redirect' );
remove_filter( 'pre_wp_mail', $cap2 );
update_option( 'seohc_schedule', $sched_before );

// Paginering en filters (1 per pagina).
update_user_meta( get_current_user_id(), 'seohc_issues_per_page', 1 );
update_user_meta( get_current_user_id(), 'seohc_pages_per_page', 1 );
$base_re = 'page=pdk-theme-options(?:&|&amp;|&#038;)tab=seo_health_check(?:&|&amp;|&#038;)seohc_view=';
$hrefs   = static function ( $h, $needle ) {
	preg_match_all( '/href=["\']([^"\']*)["\']/', $h, $m );
	return array_values( array_filter( $m[1], static function ( $u ) use ( $needle ) { return false !== strpos( $u, $needle ); } ) );
};
$pg = [ 'issues' => $render( 'issues', [ 's' => 'SEOHC dummy' ] ), 'pages' => $render( 'pages', [ 's' => 'SEOHC dummy' ] ) ];
foreach ( [ 'issues' => [ 'paged=2', 'orderby=', 'issue_type=' ], 'pages' => [ 'paged=2', 'band=' ] ] as $v => $needles ) {
	foreach ( $needles as $needle ) {
		$found = $hrefs( $pg[ $v ], $needle );
		$bad   = array_filter( $found, static function ( $u ) use ( $base_re, $v ) { return ! preg_match( '/' . $base_re . $v . '/', $u ); } );
		$ok( "AC-010 [{$v}] links met {$needle} bestaan en wijzen naar de tab", $found && ! $bad, count( $found ) . ' gevonden; fout: ' . implode( ' ; ', array_slice( $bad, 0, 2 ) ) );
	}
	preg_match_all( '/<form[^>]*method=["\']get["\'][^>]*>.*?<\/form>/is', $pg[ $v ], $forms );
	$bad_forms = array_filter( $forms[0], static function ( $f ) use ( $v ) {
		return ! ( preg_match( '/name="page"\s+value="pdk-theme-options"/', $f ) && preg_match( '/name="tab"\s+value="seo_health_check"/', $f ) && preg_match( '/name="seohc_view"\s+value="' . $v . '"/', $f ) );
	} );
	$ok( "AC-010 [{$v}] GET-forms hebben hidden page, tab en seohc_view", $forms[0] && ! $bad_forms, count( $forms[0] ) . ' forms' );
}
delete_user_meta( get_current_user_id(), 'seohc_issues_per_page' );
delete_user_meta( get_current_user_id(), 'seohc_pages_per_page' );

// ---------------------------------------------------------------------------
// AC-011
// ---------------------------------------------------------------------------
$deq = static function () { wp_dequeue_style( 'shc-admin' ); wp_dequeue_script( 'shc-admin' ); };
$deq();
$render( 'issues' );
global $wp_styles, $wp_scripts;
$ok( 'AC-011 shc-admin css in de wachtrij', wp_style_is( 'shc-admin', 'enqueued' ) );
$ok( 'AC-011 shc-admin js in de wachtrij', wp_script_is( 'shc-admin', 'enqueued' ) );
$css_src = $wp_styles->registered['shc-admin']->src ?? '';
$js_src  = $wp_scripts->registered['shc-admin']->src ?? '';
$ok( 'AC-011 css src onder modules/seo-health-check/assets/', false !== strpos( $css_src, '/modules/seo-health-check/assets/' ), $css_src );
$ok( 'AC-011 js src onder modules/seo-health-check/assets/', false !== strpos( $js_src, '/modules/seo-health-check/assets/' ), $js_src );
$per = get_current_screen()->get_option( 'per_page', 'option' );
$ok( 'AC-011 screen option issues = seohc_issues_per_page', 'seohc_issues_per_page' === $per, (string) $per );
$deq();
$render( 'pages' );
$per = get_current_screen()->get_option( 'per_page', 'option' );
$ok( 'AC-011 screen option pages = seohc_pages_per_page', 'seohc_pages_per_page' === $per, (string) $per );
$deq();
$set_request( [ 'page' => $SLUG, 'tab' => 'modules' ] );
set_current_screen( $HOOK );
do_action( 'admin_enqueue_scripts', $HOOK );
$ok( 'AC-011 tab=modules: shc-admin niet in de wachtrij (css en js)', ! wp_style_is( 'shc-admin', 'enqueued' ) && ! wp_script_is( 'shc-admin', 'enqueued' ) );
$deq();
$set_request( [ 'page' => $SLUG, 'tab' => 'modules' ] );
do_action( 'admin_enqueue_scripts', 'index.php' );
$ok( 'AC-011 index.php: alleen de css', wp_style_is( 'shc-admin', 'enqueued' ) && ! wp_script_is( 'shc-admin', 'enqueued' ) );
foreach ( [ 'wp_ajax_seohc_scan_status', 'wp_ajax_seohc_inline_save', 'admin_post_seohc_export' ] as $hk ) {
	$ok( "AC-011 has_action({$hk})", false !== has_action( $hk ) );
}
$ok( 'AC-011 has_action(wp_dashboard_setup, [SEOHC_Dashboard_Widget, register])', false !== has_action( 'wp_dashboard_setup', [ 'SEOHC_Dashboard_Widget', 'register' ] ) );

// ---------------------------------------------------------------------------
// AC-037 (L): simuleert findSaveButton() van admin-save.js op de HTML van AC-009
// ---------------------------------------------------------------------------
$find = static function ( $h ) {
	libxml_use_internal_errors( true );
	$d = new DOMDocument();
	$d->loadHTML( '<?xml encoding="utf-8"?>' . $h );
	$x = new DOMXPath( $d );
	$cls = static function ( $c ) { return "contains(concat(' ',normalize-space(@class),' '),' {$c} ')"; };
	foreach ( [ "//*[@id='save-post']", "//*[@id='publish']", "//*[@id='submit']", "//*[" . $cls( 'wrap' ) . "]//form//input[@type='submit'][" . $cls( 'button-primary' ) . "]", "//*[" . $cls( 'wrap' ) . "]//form//button[@type='submit'][" . $cls( 'button-primary' ) . "]" ] as $q ) {
		$n = $x->query( $q );
		if ( $n->length ) {
			$btn = $n->item( 0 );
			return [ true, $x->query( "ancestor::*[" . $cls( 'shc-panel' ) . "]", $btn )->length > 0, $btn->getAttribute( 'value' ) . '#' . $btn->getAttribute( 'id' ) ];
		}
	}
	return [ false, false, '' ];
};
foreach ( [ 'issues' => true, 'launch' => true, 'settings' => false, 'schedule' => false ] as $v => $in_panel ) {
	[ $found, $inside, $desc ] = $find( $html[ $v ] );
	$ok( "AC-037 [{$v}] eerste opslag-/primaire knop gevonden", $found, $desc );
	$ok( "AC-037 [{$v}] knop " . ( $in_panel ? 'staat' : 'staat niet' ) . ' binnen .shc-panel', $found && $inside === $in_panel, $desc );
}
// knoppen met de actie-waarde zelf
foreach ( [ 'issues' => 'seohc_start_scan', 'launch' => 'seohc_run_launch_checks' ] as $v => $act ) {
	libxml_use_internal_errors( true );
	$d = new DOMDocument();
	$d->loadHTML( '<?xml encoding="utf-8"?>' . $html[ $v ] );
	$x = new DOMXPath( $d );
	$n = $x->query( "//*[@value='{$act}']/ancestor::*[contains(concat(' ',normalize-space(@class),' '),' shc-panel ')]" );
	$ok( "AC-037 [{$v}] {$act} binnen .shc-panel", $n->length > 0 );
}

$ok( 'geen opgevangen notices/warnings in het hele script', [] === $caught, implode( ' | ', array_unique( $caught ) ) );
$finish();
