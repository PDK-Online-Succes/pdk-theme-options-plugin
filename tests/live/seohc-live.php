<?php
/**
 * Live-checks (dev.local) voor de SEO Health Check-module die geen admin-bootstrap nodig hebben.
 * Eén modus per aanroep (elke modus = een eigen proces, zoals de brief per AC voorschrijft):
 *
 *   wpl --user=1 eval-file tests/live/seohc-live.php <modus>
 *
 *   off          AC-002 (data-deel; module uit, nog nooit data)
 *   schema       AC-003 (en AC-004 na `dropdb`)
 *   dropdb       AC-004 voorbereiding: DROP seohc_links + delete_option seohc_db_version
 *   rescan-1     AC-006 deel 1: H1 in S11 via wp_update_post(wp_slash())
 *   rescan-2     AC-006 deel 2: na `wpl cron event run seohc_scan_single_post`
 *   schedule     AC-007
 *   i18n         AC-013 (zonder HTML; de HTML-check zit in seohc-admin.php)
 *   deact        AC-016 (L)
 *   uninst-prep  AC-014 voorbereiding
 *   uninst-verify AC-014 controle (na het uitvoeren van modules/seo-health-check/uninstall.php)
 *   standalone   AC-012 stap 2/3/4: toestand van de module naast/zonder de losse plugin
 *   summary      print SEOHC_FILE, db_version en summary()
 *
 * Exit 0 = alle checks van de modus geslaagd.
 */

if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

global $wpdb;
$mode = isset( $args[0] ) ? $args[0] : '';
$fail = 0;
$ok   = static function ( $name, $cond, $detail = '' ) use ( &$fail ) {
	echo ( $cond ? 'PASS' : 'FAIL' ) . '  ' . $name . ( '' !== $detail ? '  [' . $detail . ']' : '' ) . "\n";
	if ( ! $cond ) { $fail++; }
};
$norm = static function ( $p ) { return str_replace( '\\', '/', (string) $p ); };
$sid  = static function ( $s ) {
	$p = get_posts( [ 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_seohc_dummy', 'meta_value' => $s ] );
	return $p ? (int) $p[0] : 0;
};
$cron_hooks = static function () {
	$out = [];
	foreach ( (array) _get_cron_array() as $ts => $hooks ) {
		foreach ( $hooks as $h => $_ ) { $out[ $h ] = ( $out[ $h ] ?? 0 ) + 1; }
	}
	return $out;
};
$modroot = '/mu-plugins/pdk-theme-options/modules/seo-health-check/';

switch ( $mode ) {

	case 'off':
		$ok( 'AC-002 geen seohc-tabellen', [] === $wpdb->get_col( "SHOW TABLES LIKE '%seohc%'" ) );
		$ok( 'AC-002 seohc_db_version bestaat niet', null === get_option( 'seohc_db_version', null ) );
		$ok( 'AC-002 SEOHC_Plugin niet geladen', ! class_exists( 'SEOHC_Plugin', false ) );
		$ok( 'AC-002 geen seohc_ in cron', [] === array_filter( array_keys( $cron_hooks() ), static function ( $h ) { return 0 === strpos( $h, 'seohc_' ); } ) );
		$ok( 'AC-002 module staat uit', empty( PDK_Settings::get( 'seo_health_check' )['enabled'] ) );
		break;

	case 'schema':
		$prefix = $wpdb->prefix;
		$cols   = [
			'seohc_issues' => [ 'id', 'post_id', 'issue_type', 'severity', 'object_id', 'details', 'created_at', 'first_seen', 'resolved_at' ],
			'seohc_pages'  => [ 'post_id', 'seo_title', 'meta_description', 'title_hash', 'description_hash', 'word_count', 'issue_count', 'score', 'previous_score', 'scanned_at' ],
			'seohc_links'  => [ 'from_post_id', 'to_post_id' ],
		];
		foreach ( $cols as $t => $want ) {
			$exists = $prefix . $t === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . $t ) );
			$have   = $exists ? $wpdb->get_col( "SHOW COLUMNS FROM {$prefix}{$t}" ) : [];
			$ok( "tabel {$t} bestaat met kolommen uit de installer", $exists && $have === $want, implode( ',', $have ) );
		}
		$ok( "seohc_db_version === '4'", '4' === get_option( 'seohc_db_version' ), var_export( get_option( 'seohc_db_version' ), true ) );
		$ok( 'seohc_settings heeft 15 sleutels', 15 === count( (array) get_option( 'seohc_settings' ) ), (string) count( (array) get_option( 'seohc_settings' ) ) );
		$ok( 'SEOHC_FILE onder mu-plugins/.../modules/seo-health-check/', defined( 'SEOHC_FILE' ) && false !== strpos( $norm( SEOHC_FILE ), $modroot ), defined( 'SEOHC_FILE' ) ? $norm( SEOHC_FILE ) : 'niet gedefinieerd' );
		$ok( 'SEOHC_DIR onder dezelfde map', defined( 'SEOHC_DIR' ) && false !== strpos( $norm( SEOHC_DIR ), $modroot ), defined( 'SEOHC_DIR' ) ? $norm( SEOHC_DIR ) : '' );
		$ok( 'class_exists(SEOHC_Updater) is false', ! class_exists( 'SEOHC_Updater' ) );
		$ok( 'PDK_SEO_Health_Check::is_active()', PDK_SEO_Health_Check::is_active() );
		break;

	case 'dropdb':
		$wpdb->query( "DROP TABLE {$wpdb->prefix}seohc_links" );
		delete_option( 'seohc_db_version' );
		$ok( 'AC-004 voorbereiding: links-tabel weg, db_version weg', null === $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}seohc_links'" ) && false === get_option( 'seohc_db_version' ) );
		break;

	case 'rescan-1':
		$s11 = $sid( 'S11' );
		$ok( 'rescan_on_save = 1', 1 === (int) SEOHC_Settings::get( 'rescan_on_save' ) );
		$post = get_post( $s11 );
		wp_clear_scheduled_hook( 'seohc_scan_single_post', [ $s11 ] );
		wp_update_post( wp_slash( [ 'ID' => $s11, 'post_content' => '<h1>Nu met H1</h1>' . $post->post_content ] ) );
		$next = wp_next_scheduled( 'seohc_scan_single_post', [ $s11 ] );
		$ok( 'AC-006 seohc_scan_single_post([S11]) staat gepland', (bool) $next, (string) $next );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT resolved_at FROM ' . SEOHC_Repository::issues_table() . " WHERE post_id=%d AND issue_type='h1_missing' ORDER BY id DESC", $s11 ) );
		$ok( 'AC-006 voor de cron-run: h1_missing van S11 nog open', $row && null === $row->resolved_at );
		break;

	case 'rescan-2':
		$s11 = $sid( 'S11' );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT resolved_at FROM ' . SEOHC_Repository::issues_table() . " WHERE post_id=%d AND issue_type='h1_missing' ORDER BY id DESC", $s11 ) );
		$ok( 'AC-006 na cron-run: h1_missing van S11 heeft resolved_at', $row && null !== $row->resolved_at, $row ? (string) $row->resolved_at : 'geen rij' );
		break;

	case 'schedule':
		// De optie moet al bestaan: update_option() op een ontbrekende optie wordt add_option() en vuurt
		// update_option_seohc_schedule niet af (0.10.1-gedrag; ensure_booked() boekt dan bij de volgende init).
		update_option( 'seohc_schedule', [ 'enabled' => 0 ] );
		wp_clear_scheduled_hook( 'seohc_scheduled_scan' );
		update_option( 'seohc_schedule', [ 'enabled' => 1, 'days' => [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ], 'time' => '03:00', 'email' => '', 'keep' => 1 ] );
		$next = wp_next_scheduled( 'seohc_scheduled_scan' );
		$ok( 'AC-007 seohc_scheduled_scan ligt in de toekomst', $next && $next > time(), $next ? gmdate( 'c', $next ) : 'niet gepland' );
		break;

	case 'i18n':
		$ok( 'locale is nl_NL', 'nl_NL' === determine_locale(), determine_locale() );
		$ok( "'Missing H1' => 'H1 ontbreekt'", 'H1 ontbreekt' === __( 'Missing H1', 'seo-health-check' ), __( 'Missing H1', 'seo-health-check' ) );
		$ok( "'Automatic scan' => 'Automatisch scannen'", 'Automatisch scannen' === __( 'Automatic scan', 'seo-health-check' ), __( 'Automatic scan', 'seo-health-check' ) );
		$src = getenv( 'SEOHC_SRC' ) ?: 'C:/Users/Luuk de Bresser/Desktop/GitHub/seo-health-check';
		foreach ( [ 'po', 'mo' ] as $ext ) {
			$f = 'languages/seo-health-check-nl_NL.' . $ext;
			$a = @sha1_file( SEOHC_DIR . $f );
			$b = @sha1_file( $src . '/' . $f );
			$ok( "sha1 {$ext} gelijk aan de bron", $a && $a === $b, "module={$a} bron={$b}" );
		}
		break;

	case 'deact':
		SEOHC_Scan_Queue::start();
		update_option( 'seohc_schedule', [ 'enabled' => 1, 'days' => [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ], 'time' => '03:00', 'email' => '', 'keep' => 1 ] );
		SEOHC_Schedule::reschedule();
		wp_schedule_single_event( time() + 3600, 'seohc_scan_single_post', [ 999999 ] );
		update_option( 'seohc_scan_lock', time(), false );
		$h = $cron_hooks();
		$as = SEOHC_Scan_Queue::use_action_scheduler();
		$ok( 'voorbereiding: 3 hooks geboekt', isset( $h['seohc_process_batch'], $h['seohc_scan_single_post'], $h['seohc_scheduled_scan'] ) || ( $as && isset( $h['seohc_scan_single_post'], $h['seohc_scheduled_scan'] ) ), wp_json_encode( array_intersect_key( $h, array_flip( [ 'seohc_process_batch', 'seohc_scan_single_post', 'seohc_scheduled_scan' ] ) ) ) );
		if ( $as ) { $ok( 'voorbereiding: AS-actie seohc_process_batch geboekt', (bool) as_has_scheduled_action( 'seohc_process_batch' ) ); }
		$ok( 'voorbereiding: scan loopt', 'running' === SEOHC_Scan_Queue::get_state()['status'] );
		PDK_Plugin::deactivate();
		wp_cache_delete( 'seohc_scan_state', 'options' );
		wp_cache_delete( 'seohc_scan_lock', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$h = $cron_hooks();
		foreach ( [ 'seohc_process_batch', 'seohc_scan_single_post', 'seohc_scheduled_scan' ] as $hook ) {
			$ok( "AC-016 wp_next_scheduled false voor {$hook}", ! isset( $h[ $hook ] ) );
		}
		if ( $as ) {
			$ok( 'AC-016 as_has_scheduled_action(seohc_process_batch) false', ! as_has_scheduled_action( 'seohc_process_batch' ) );
		}
		$ok( 'AC-016 seohc_scan_lock weg', false === get_option( 'seohc_scan_lock', false ) );
		$ok( 'AC-016 status cancelled', 'cancelled' === SEOHC_Scan_Queue::get_state()['status'], SEOHC_Scan_Queue::get_state()['status'] );
		update_option( 'seohc_schedule', [ 'enabled' => 0 ] );
		break;

	case 'uninst-prep':
		$up     = wp_get_upload_dir();
		$folder = trailingslashit( $up['basedir'] ) . 'seo-health-check';
		wp_mkdir_p( $folder );
		file_put_contents( $folder . '/seohc-test-rapport.csv', "x\n" );
		set_transient( 'seohc_link_test', 'x', 3600 );
		update_user_meta( 1, 'seohc_issues_per_page', 7 );
		foreach ( [ 'seohc_settings', 'seohc_db_version', 'seohc_scan_state', 'seohc_scan_lock', 'seohc_launch_results', 'seohc_link_cache_generation', 'seohc_scan_history', 'seohc_schedule', 'seohc_report_state' ] as $o ) {
			if ( false === get_option( $o, false ) ) { update_option( $o, 'x', false ); }
		}
		update_option( 'seohc_schedule', [ 'enabled' => 1, 'days' => [ 'mon' ], 'time' => '03:00', 'email' => '', 'keep' => 1 ] );
		SEOHC_Schedule::reschedule();
		wp_schedule_single_event( time() + 3600, 'seohc_scan_single_post', [ 999999 ] );
		if ( false === get_option( 'imgx_settings', false ) ) { add_option( 'imgx_settings', [ 'seohc_test_marker' => 1 ], '', false ); }
		$rows = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SEOHC_Repository::issues_table() ) + (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SEOHC_Repository::pages_table() );
		$ok( 'voorbereiding: tabellen bevatten data', $rows > 0, (string) $rows );
		$ok( 'voorbereiding: transient, bestand, usermeta, geboekte scan', false !== get_transient( 'seohc_link_test' ) && is_file( $folder . '/seohc-test-rapport.csv' ) && '7' === (string) get_user_meta( 1, 'seohc_issues_per_page', true ) && wp_next_scheduled( 'seohc_scheduled_scan' ) );
		update_option( 'seohc_prep_hash', md5( serialize( get_option( 'pdk_theme_options' ) ) ), false );
		break;

	case 'uninst-verify':
		$prefix = $wpdb->prefix;
		$up     = wp_get_upload_dir();
		$ok( 'AC-014 3 tabellen weg', [] === $wpdb->get_col( "SHOW TABLES LIKE '%seohc_%'" ) );
		foreach ( [ 'seohc_settings', 'seohc_db_version', 'seohc_scan_state', 'seohc_scan_lock', 'seohc_launch_results', 'seohc_link_cache_generation', 'seohc_scan_history', 'seohc_schedule', 'seohc_report_state' ] as $o ) {
			$ok( "AC-014 optie {$o} weg", false === get_option( $o, false ) );
		}
		$ok( 'AC-014 transients seohc_link_* weg', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_seohc\\_link\\_%' OR option_name LIKE '\\_transient\\_timeout\\_seohc\\_link\\_%'" ) );
		$ok( 'AC-014 usermeta seohc_issues_per_page weg', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='seohc_issues_per_page'" ) );
		$h = $cron_hooks();
		foreach ( [ 'seohc_process_batch', 'seohc_scan_single_post', 'seohc_scheduled_scan' ] as $hook ) {
			$ok( "AC-014 hook {$hook} weg", ! isset( $h[ $hook ] ) );
		}
		$ok( 'AC-014 uploads/seo-health-check weg', ! is_dir( trailingslashit( $up['basedir'] ) . 'seo-health-check' ) );
		$ok( 'AC-014 pdk_theme_options blijft', is_array( get_option( 'pdk_theme_options' ) ) && md5( serialize( get_option( 'pdk_theme_options' ) ) ) === get_option( 'seohc_prep_hash' ) );
		$ok( 'AC-014 imgx_settings blijft', false !== get_option( 'imgx_settings', false ) );
		$imgx = get_option( 'imgx_settings' );
		if ( is_array( $imgx ) && isset( $imgx['seohc_test_marker'] ) ) { delete_option( 'imgx_settings' ); }
		delete_option( 'seohc_prep_hash' );
		break;

	case 'standalone':
		$file = defined( 'SEOHC_FILE' ) ? $norm( SEOHC_FILE ) : '';
		echo "SEOHC_FILE={$file}\n";
		$in_plugin = false !== strpos( $file, '/plugins/seo-health-check/' );
		$cb = isset( $GLOBALS['wp_filter']['seohc_process_batch'] ) ? $GLOBALS['wp_filter']['seohc_process_batch']->callbacks : [];
		$n10 = isset( $cb[10] ) ? count( $cb[10] ) : 0;
		if ( $in_plugin ) {
			$ok( 'AC-012 SEOHC_FILE onder /plugins/seo-health-check/', true );
			$ok( 'AC-012 precies 1 callback op seohc_process_batch (prio 10)', 1 === $n10 && 1 === count( $cb ), "n10={$n10} prioriteiten=" . implode( ',', array_keys( $cb ) ) );
			$ok( 'AC-012 PDK-module is niet geboot', ! PDK_SEO_Health_Check::is_active() );
			ob_start();
			PDK_SEO_Health_Check::render_inline();
			$html = ob_get_clean();
			$ok( 'AC-012 tab toont de melding uit D2 + waarschuwing D26 (rm -r)', false !== strpos( $html, 'losse plugin' ) && false !== strpos( $html, 'rm -r' ) && false !== strpos( $html, 'Verwijderen' ) );
			$ok( 'AC-012 geen shc-scan-panel', false === strpos( $html, 'shc-scan-panel' ) );
		} else {
			$ok( 'AC-012 SEOHC_FILE in de modulemap', false !== strpos( $file, $modroot ), $file );
			$ok( 'AC-012 precies 1 callback op seohc_process_batch (prio 10)', 1 === $n10 && 1 === count( $cb ), "n10={$n10}" );
			$ok( "AC-012 seohc_db_version '4'", '4' === get_option( 'seohc_db_version' ) );
			$ok( 'AC-012 PDK-module is geboot', PDK_SEO_Health_Check::is_active() );
		}
		echo 'summary=' . wp_json_encode( SEOHC_Repository::summary() ) . "\n";
		break;

	case 'summary':
		echo 'SEOHC_FILE=' . ( defined( 'SEOHC_FILE' ) ? $norm( SEOHC_FILE ) : '-' ) . ' db=' . var_export( get_option( 'seohc_db_version' ), true ) . "\n";
		echo 'summary=' . wp_json_encode( SEOHC_Repository::summary() ) . "\n";
		break;

	default:
		fwrite( STDERR, "onbekende modus\n" );
		exit( 2 );
}

echo ( $fail ? "RESULT[{$mode}]: {$fail} FAIL\n" : "RESULT[{$mode}]: ALL PASS\n" );
exit( $fail ? 1 : 0 );
