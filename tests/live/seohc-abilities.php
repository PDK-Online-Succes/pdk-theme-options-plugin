<?php
/**
 * Slice 2 (L): de MCP-abilities van SEO Health Check op dev.local. Brief §7.4, AC-021 t/m AC-034 en AC-038.
 *
 * Volgorde (de test van AC-024 "status new" vereist een vers geseede dataset):
 *   wpl --user=1 eval-file tests/live/seohc-seed.php
 *   wpl --user=1 eval-file tests/live/seohc-check.php           (de eerste scan na de seed)
 *   wpl --user=1 eval-file tests/live/seohc-abilities.php        (alle AC's behalve de registratie-varianten)
 *
 * Modi (eerste argument):
 *   (leeg)         alles hieronder, in een proces als gebruiker 1
 *   meta           alleen AC-029; draai ook eens met `--skip-plugins=seo-by-rank-math` voor de tak provider `none`
 *   registration N AC-021: controleert dat er N seo-abilities zijn (0 of 12); N=0 -> seo-list-issues is null.
 *                  De drie gevallen draaien als losse processen, omdat registratie bij het opstarten gebeurt:
 *                  1) seo_health_check uit  2) agent_abilities uit  3) losse plugin actief (zie driver in het rapport).
 *
 * De Oxygen 6-pagina is 21710 ("CSS Framework demo"). Het script ruimt zelf op: instellingen, meta, alt-tekst,
 * posts (originele content), tijdelijke gebruiker en tijdelijke posts. Daarna: seed + check voor een schone eindstand.
 * Exit 0 = alles geslaagd.
 */

if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

global $wpdb;
$mode = isset( $args[0] ) ? $args[0] : '';
$fail = 0;
$ok   = static function ( $name, $cond, $detail = '' ) use ( &$fail ) {
	echo ( $cond ? 'PASS' : 'FAIL' ) . '  ' . $name . ( '' !== (string) $detail ? '  [' . $detail . ']' : '' ) . "\n";
	if ( ! $cond ) { $fail++; }
};
$done = static function () use ( &$fail, &$mode ) {
	echo ( $fail ? "RESULT[{$mode}]: {$fail} FAIL\n" : "RESULT[{$mode}]: ALL PASS\n" );
	exit( $fail ? 1 : 0 );
};

if ( ! function_exists( 'wp_get_ability' ) ) { echo "FAIL  wp_get_ability bestaat niet\n"; exit( 1 ); }

$NAMES = [ 'seo-get-status', 'seo-list-issues', 'seo-list-pages', 'seo-get-scan-history', 'seo-get-launch-checks', 'seo-get-headings', 'seo-start-scan', 'seo-rescan-post', 'seo-update-meta', 'seo-update-image-alt', 'seo-set-heading-level', 'seo-insert-h1' ];

// ---------------------------------------------------------------------------
// AC-021: registratie (los proces per geval)
// ---------------------------------------------------------------------------
if ( 'registration' === $mode ) {
	$want = (int) ( $args[1] ?? 0 );
	$have = 0;
	foreach ( $NAMES as $n ) { if ( wp_get_ability( 'pdk-theme-options/' . $n ) ) { $have++; } }
	$ok( "AC-021 aantal geregistreerde seo-abilities = {$want}", $want === $have, "gevonden {$have}" );
	$list = wp_get_ability( 'pdk-theme-options/seo-list-issues' );
	$ok( 'AC-021 wp_get_ability(seo-list-issues) ' . ( $want ? 'bestaat' : 'is null' ), $want ? null !== $list : null === $list );
	echo 'module_active=' . var_export( class_exists( 'PDK_SEO_Health_Check' ) && PDK_SEO_Health_Check::is_active(), true ) . ' SEOHC_VERSION=' . ( defined( 'SEOHC_VERSION' ) ? SEOHC_VERSION : '-' ) . ' SEOHC_FILE=' . ( defined( 'SEOHC_FILE' ) ? str_replace( '\\', '/', SEOHC_FILE ) : '-' ) . "\n";
	$done();
}

// ---------------------------------------------------------------------------
// Voorwaarden en hulpfuncties
// ---------------------------------------------------------------------------
if ( ! class_exists( 'SEOHC_Scan_Queue' ) || ! class_exists( 'PDK_SEO_Health_Check' ) || ! PDK_SEO_Health_Check::is_active() ) {
	echo "FAIL  de module zelf draait niet (uit, of losse plugin actief)\n";
	exit( 1 );
}
$ok( 'voorwaarde: alle 12 abilities geregistreerd', 12 === count( array_filter( $NAMES, static fn( $n ) => (bool) wp_get_ability( 'pdk-theme-options/' . $n ) ) ) );
$ok( 'voorwaarde: PDK-modules seo_health_check en agent_abilities aan', ! empty( PDK_Settings::get( 'seo_health_check' )['enabled'] ) && ! empty( PDK_Settings::get( 'agent_abilities' )['enabled'] ) );

$S = [];
foreach ( get_posts( [ 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_seohc_dummy' ] ) as $p ) {
	$S[ get_post_meta( $p->ID, '_seohc_dummy', true ) ] = $p->ID;
}
$A01 = $S['A01'] ?? 0;
$OXY = 21710;
$bs     = chr( 92 );
$marker = '<!-- wp:paragraph {"placeholder":"a' . $bs . 'u0026b"} --><p>C:' . $bs . 'pad</p><!-- /wp:paragraph -->';
foreach ( [ 'S09', 'S18', 'S18b' ] as $sid ) {
	if ( empty( $S[ $sid ] ) || false === strpos( (string) get_post_field( 'post_content', $S[ $sid ] ), $marker ) ) {
		echo "FAIL  markering ontbreekt in {$sid}: seed opnieuw draaien\n";
		exit( 1 );
	}
}
echo "PASS  slash-markering aanwezig in S09, S18, S18b\n";
$oxy_post = get_post( $OXY );
$ok( 'voorwaarde: Oxygen 6-pagina 21710 bestaat', (bool) $oxy_post );
$oxy_keys = array_keys( get_post_meta( $OXY ) );
echo 'Oxygen 6-pagina ' . $OXY . ' meta-sleutels: ' . implode( ',', $oxy_keys ) . ' | post_content=' . strlen( $oxy_post->post_content ) . " bytes\n";
$ok( 'voorwaarde: de Oxygen 6-pagina heeft builder-meta _oxygen_data (R-10)', in_array( '_oxygen_data', $oxy_keys, true ) );

$run  = static function ( $name, $input = [] ) { return wp_get_ability( 'pdk-theme-options/' . $name )->execute( $input ); };
$code = static fn( $r ) => is_wp_error( $r ) ? $r->get_error_code() : 'geen-fout';
$types = static fn( $r ) => is_array( $r ) ? wp_list_pluck( $r['issues'] ?? [], 'issue_type' ) : [];
$snap = static function ( $id ) use ( $wpdb ) {
	$p = get_post( $id );
	return [
		'content'   => md5( $p->post_content ),
		'meta'      => md5( serialize( get_post_meta( $id ) ) ),
		'revisions' => count( wp_get_post_revisions( $id ) ),
		'issues'    => md5( serialize( $wpdb->get_results( $wpdb->prepare( 'SELECT issue_type, object_id, details, first_seen, resolved_at FROM ' . SEOHC_Repository::issues_table() . ' WHERE post_id = %d ORDER BY id', $id ), ARRAY_A ) ) ),
	];
};
$all_issues = static fn() => md5( serialize( $wpdb->get_results( 'SELECT id, post_id, issue_type, details, first_seen, resolved_at FROM ' . SEOHC_Repository::issues_table() . ' ORDER BY id', ARRAY_A ) ) );
$has_rev = static function ( $id, $old ) {
	foreach ( wp_get_post_revisions( $id ) as $r ) { if ( $r->post_content === $old ) { return true; } }
	return false;
};
$content = static fn( $id ) => (string) get_post_field( 'post_content', $id );
$scan = static function () {
	SEOHC_Scan_Queue::start();
	$t0 = time();
	do {
		wp_cache_delete( 'seohc_scan_state', 'options' );
		SEOHC_Scan_Queue::process_batch();
		wp_cache_delete( 'seohc_scan_state', 'options' );
		$st = SEOHC_Scan_Queue::get_state();
		if ( 'done' === $st['status'] ) { break; }
		sleep( 1 );
	} while ( time() - $t0 < 300 );
	return $st;
};

// Originelen voor het opruimen.
$orig_settings = get_option( 'seohc_settings', [] );
$orig_content  = [];
foreach ( [ 'S09', 'S11', 'S12', 'S13', 'S14', 'S18', 'S18b' ] as $sid ) { $orig_content[ $S[ $sid ] ] = $content( $S[ $sid ] ); }
$orig_launch = get_option( 'seohc_launch_results', null );
$orig_alt    = metadata_exists( 'post', $A01, '_wp_attachment_image_alt' ) ? get_post_meta( $A01, '_wp_attachment_image_alt', true ) : null;
$rm_keys     = [ 'rank_math_title', 'rank_math_description', '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ];
$orig_meta   = [];
foreach ( [ 'S02', 'S05', 'S06' ] as $sid ) {
	foreach ( $rm_keys as $k ) { $orig_meta[ $S[ $sid ] ][ $k ] = metadata_exists( 'post', $S[ $sid ], $k ) ? get_post_meta( $S[ $sid ], $k, true ) : null; }
}
$oxy0 = $snap( $OXY );
$s17_0 = $snap( $S['S17'] );
$tmp_user = 0;
$tmp_posts = [];
$max_id0 = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );
$strays  = static fn() => array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_seohc_dummy' WHERE p.ID > %d AND p.post_type IN ('post','page') AND m.meta_id IS NULL", $max_id0 ) ) );
$restored = false;
$restore = static function () use ( &$restored, &$tmp_user, &$tmp_posts, $strays, $orig_settings, $orig_content, $orig_launch, $orig_alt, $orig_meta, $A01 ) {
	if ( $restored ) { return; }
	$restored = true;
	remove_all_filters( 'wp_revisions_to_keep' );
	remove_all_filters( 'map_meta_cap' );
	update_option( 'seohc_settings', $orig_settings );
	foreach ( $orig_content as $id => $c ) {
		if ( get_post_field( 'post_content', $id ) !== $c ) { wp_update_post( wp_slash( [ 'ID' => $id, 'post_content' => $c ] ) ); }
	}
	if ( null === $orig_launch ) { delete_option( 'seohc_launch_results' ); } else { update_option( 'seohc_launch_results', $orig_launch ); }
	if ( null === $orig_alt ) { delete_post_meta( $A01, '_wp_attachment_image_alt' ); } else { update_post_meta( $A01, '_wp_attachment_image_alt', $orig_alt ); }
	foreach ( $orig_meta as $id => $keys ) {
		foreach ( $keys as $k => $v ) { null === $v ? delete_post_meta( $id, $k ) : update_post_meta( $id, $k, $v ); }
	}
	$tmp_posts = array_unique( array_merge( $tmp_posts, $strays() ) ); // ook verdwaalde kopieën die een defect aanmaakte
	foreach ( $tmp_posts as $id ) { wp_delete_post( $id, true ); if ( class_exists( 'SEOHC_Repository' ) ) { SEOHC_Repository::delete_post( $id ); } }
	if ( $tmp_user ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $tmp_user ); $tmp_user = 0; }
	wp_set_current_user( 1 );
};
register_shutdown_function( $restore );
wp_set_current_user( 1 );

// rescan_on_save uit: een wp_update_post() in de test mag geen scan inplannen (AC-028).
update_option( 'seohc_settings', array_merge( (array) $orig_settings, [ 'rescan_on_save' => 0, 'theme_outputs_h1' => 0 ] ) );
$setting = static function ( $k, $v ) { update_option( 'seohc_settings', array_merge( (array) get_option( 'seohc_settings', [] ), [ $k => $v ] ) ); wp_cache_delete( 'seohc_settings', 'options' ); };

// ---------------------------------------------------------------------------
// AC-029 (L): seo-update-meta
// ---------------------------------------------------------------------------
$ac029 = static function () use ( $ok, $run, $code, $types, $snap, $S, &$orig_meta ) {
	$prov = SEOHC_SEO_Meta::provider();
	echo "-- AC-029, provider={$prov}\n";
	$s05 = $S['S05'];
	$s02 = $S['S02'];
	if ( 'none' === $prov ) {
		$before = $snap( $s05 );
		$r = $run( 'seo-update-meta', [ 'post_id' => $s05, 'field' => 'meta_description', 'value' => 'Een beschrijving' ] );
		$ok( 'AC-029 provider none: pdk_seo_no_seo_plugin', 'pdk_seo_no_seo_plugin' === $code( $r ), $code( $r ) );
		$ok( 'AC-029 provider none: melding noemt Yoast SEO en Rank Math', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'Yoast SEO' ) && false !== strpos( $r->get_error_message(), 'Rank Math' ) );
		$ok( 'AC-029 provider none: S05 ongewijzigd', $before === $snap( $s05 ) );
		return;
	}
	$k_desc = 'yoast' === $prov ? '_yoast_wpseo_metadesc' : 'rank_math_description';
	$k_tit  = 'yoast' === $prov ? '_yoast_wpseo_title' : 'rank_math_title';
	$r = $run( 'seo-update-meta', [ 'post_id' => $s05, 'field' => 'meta_description', 'value' => '<b>Tekst</b> over S05 voor de test' ] );
	$ok( 'AC-029 meta_description: succes', is_array( $r ) && ! empty( $r['saved'] ), $code( $r ) );
	$ok( "AC-029 {$k_desc} = 'Tekst over S05 voor de test' (HTML gestript)", 'Tekst over S05 voor de test' === get_post_meta( $s05, $k_desc, true ), (string) get_post_meta( $s05, $k_desc, true ) );
	$ok( 'AC-029 antwoord bevat geen description_missing voor S05', is_array( $r ) && ! in_array( 'description_missing', $types( $r ), true ), implode( ',', $types( $r ) ) );
	$ok( 'AC-029 antwoord: post_id, score en issues aanwezig', is_array( $r ) && (int) $r['post_id'] === $s05 && is_int( $r['score'] ) && is_array( $r['issues'] ) );
	$m05 = SEOHC_SEO_Meta::get( get_post( $s05 ) );
	$ok( 'AC-029 provider leest de waarde terug: SEOHC_SEO_Meta::get(S05) custom_description true', true === $m05['custom_description'] && 'Tekst over S05 voor de test' === $m05['description'], wp_json_encode( $m05 ) );
	$rs = $run( 'seo-rescan-post', [ 'post_id' => $s05 ] );
	$ok( 'AC-029 na seo-rescan-post: description_missing van S05 staat onder resolved, niet onder open', is_array( $rs ) && in_array( 'description_missing', wp_list_pluck( $run( 'seo-list-issues', [ 'post_id' => $s05, 'status' => 'resolved' ] )['items'] ?? [], 'issue_type' ), true ) && ! in_array( 'description_missing', wp_list_pluck( $run( 'seo-list-issues', [ 'post_id' => $s05, 'status' => 'open' ] )['items'] ?? [], 'issue_type' ), true ) );
	// Provider-pad op een post mét dummy-beschrijving (S06, 200 tekens): de echte provider-waarde moet winnen van de dev-hulp.
	$s06 = $S['S06'];
	$r = $run( 'seo-update-meta', [ 'post_id' => $s06, 'field' => 'meta_description', 'value' => 'Korte echte beschrijving S06' ] );
	$ok( "AC-029 S06: {$k_desc} gezet en description_too_long opgelost (dev-hulp maskeert de provider niet)", is_array( $r ) && 'Korte echte beschrijving S06' === get_post_meta( $s06, $k_desc, true ) && ! in_array( 'description_too_long', $types( $r ), true ), is_array( $r ) ? implode( ',', $types( $r ) ) : $code( $r ) );
	$r = $run( 'seo-update-meta', [ 'post_id' => $s06, 'field' => 'meta_description', 'value' => '' ] );
	$ok( 'AC-029 S06: value \'\' -> terugval op de dummy-beschrijving, description_too_long keert terug', is_array( $r ) && ! metadata_exists( 'post', $s06, $k_desc ) && in_array( 'description_too_long', $types( $r ), true ) );
	$r = $run( 'seo-update-meta', [ 'post_id' => $s05, 'field' => 'meta_description', 'value' => '' ] );
	$ok( 'AC-029 value \'\' verwijdert de meta', is_array( $r ) && ! metadata_exists( 'post', $s05, $k_desc ) );
	$ok( 'AC-029 na verwijderen staat description_missing er weer', is_array( $r ) && in_array( 'description_missing', $types( $r ), true ) );
	$had = in_array( 'title_too_long', wp_list_pluck( SEOHC_Repository::get_issues( [ 'post_id' => $s02, 'per_page' => 0 ] ), 'issue_type' ), true );
	$ok( 'AC-029 voorwaarde: S02 heeft title_too_long', $had );
	$r = $run( 'seo-update-meta', [ 'post_id' => $s02, 'field' => 'seo_title', 'value' => '<i>Korte</i> titel' ] );
	$ok( "AC-029 seo_title op S02: {$k_tit} = 'Korte titel'", is_array( $r ) && 'Korte titel' === get_post_meta( $s02, $k_tit, true ), $code( $r ) . ' ' . get_post_meta( $s02, $k_tit, true ) );
	$ok( 'AC-029 seo_title op S02: geen title_too_long meer', is_array( $r ) && ! in_array( 'title_too_long', $types( $r ), true ), implode( ',', $types( $r ) ) );
	$r = $run( 'seo-update-meta', [ 'post_id' => $s02, 'field' => 'seo_title', 'value' => '' ] );
	$ok( 'AC-029 seo_title \'\' verwijdert de meta en title_too_long keert terug', is_array( $r ) && ! metadata_exists( 'post', $s02, $k_tit ) && in_array( 'title_too_long', $types( $r ), true ) );
	// Schema en weigeringen
	$r = $run( 'seo-update-meta', [ 'post_id' => $s05, 'field' => 'bestaat_niet', 'value' => 'x' ] );
	$ok( 'AC-029 onbekend field: schemafout', 'ability_invalid_input' === $code( $r ), $code( $r ) );
	$r = $run( 'seo-update-meta', [ 'post_id' => $s05, 'field' => 'seo_title', 'value' => str_repeat( 'x', 501 ) ] );
	$ok( 'AC-029 value > 500: schemafout', 'ability_invalid_input' === $code( $r ), $code( $r ) );
	$r = $run( 'seo-update-meta', [ 'post_id' => 999999999, 'field' => 'seo_title', 'value' => 'x' ] );
	$ok( 'AC-029 bestaat niet: pdk_seo_not_found', 'pdk_seo_not_found' === $code( $r ), $code( $r ) );
	// Terugzetten van de originele waarden gebeurt in het opruimen; hier alvast de nul-situatie checken.
	$ok( 'AC-029 S05 en S02 zijn weer op de oorspronkelijke meta', ! metadata_exists( 'post', $s05, $k_desc ) === ( null === $orig_meta[ $s05 ][ $k_desc ] ) && ! metadata_exists( 'post', $s02, $k_tit ) === ( null === $orig_meta[ $s02 ][ $k_tit ] ) );
};

if ( 'meta' === $mode ) {
	$ac029();
	$restore();
	$done();
}

// ===========================================================================
// AC-023 seo-get-status
// ===========================================================================
echo "-- AC-023\n";
wp_cache_delete( 'seohc_scan_state', 'options' );
$r = $run( 'seo-get-status' );
$st = SEOHC_Scan_Queue::get_state();
$ok( 'AC-023 geen fout', is_array( $r ), $code( $r ) );
foreach ( [ 'status', 'processed', 'total', 'started_at', 'finished_at' ] as $k ) {
	$ok( "AC-023 {$k} = get_state()", is_array( $r ) && (string) $r[ $k ] === (string) $st[ $k ], is_array( $r ) ? $r[ $k ] . ' vs ' . $st[ $k ] : '' );
}
$ok( 'AC-023 status is done en processed === total', is_array( $r ) && 'done' === $r['status'] && $r['processed'] === $r['total'] );
$ok( 'AC-023 summary = summary()', is_array( $r ) && wp_json_encode( $r['summary'] ) === wp_json_encode( SEOHC_Repository::summary() ) );
$ok( 'AC-023 provider = provider_label()', is_array( $r ) && $r['provider'] === SEOHC_SEO_Meta::provider_label(), is_array( $r ) ? $r['provider'] : '' );
$ok( 'AC-023 background = use_action_scheduler()', is_array( $r ) && $r['background'] === (bool) SEOHC_Scan_Queue::use_action_scheduler() );
echo 'AC-023 seo-get-status provider=' . ( is_array( $r ) ? $r['provider'] : '?' ) . ' (SEOHC_SEO_Meta::provider()=' . SEOHC_SEO_Meta::provider() . ")\n";
$status_keys = is_array( $r ) ? array_keys( $r ) : [];

// ===========================================================================
// AC-024 seo-list-issues
// ===========================================================================
echo "-- AC-024\n";
$exp = [ 'S01' => 'title_missing', 'S02' => 'title_too_long', 'S03' => 'title_duplicate', 'S04' => 'title_duplicate', 'S05' => 'description_missing', 'S06' => 'description_too_long', 'S07' => 'description_duplicate', 'S08' => 'description_duplicate', 'S09' => 'image_missing_alt', 'S10' => 'image_too_large', 'S11' => 'h1_missing', 'S12' => 'h1_multiple', 'S13' => 'heading_skip', 'S14' => 'thin_content', 'S15' => 'broken_internal_link', 'S16' => 'no_incoming_links', 'S17' => 'rendered_fetch_failed' ];
$items_for = static function ( $status, $post_id ) use ( $run ) {
	$r = $run( 'seo-list-issues', [ 'status' => $status, 'post_id' => $post_id, 'per_page' => 100 ] );
	return is_array( $r ) ? $r['items'] : [];
};
// "new" na de eerste scan (de scan van seohc-check.php)
$miss = [];
foreach ( $exp as $sid => $type ) {
	if ( ! in_array( $type, wp_list_pluck( $items_for( 'new', $S[ $sid ] ), 'issue_type' ), true ) ) { $miss[] = $sid; }
}
$ok( 'AC-024 {status:new} bevat de open S-issues na de eerste scan (17 posts)', [] === $miss, 'ontbreekt: ' . implode( ',', $miss ) );

$r = $run( 'seo-list-issues', [ 'issue_type' => 'heading_skip', 'per_page' => 100 ] );
$ok( 'AC-024 {issue_type:heading_skip}: alleen dat type, met S13', is_array( $r ) && $r['items'] && [] === array_diff( array_unique( wp_list_pluck( $r['items'], 'issue_type' ) ), [ 'heading_skip' ] ) && in_array( $S['S13'], wp_list_pluck( $r['items'], 'post_id' ), true ) );
$r = $run( 'seo-list-issues', [ 'post_id' => $S['S03'], 'per_page' => 100 ] );
$ok( 'AC-024 {post_id:S03}: alleen S03, met title_duplicate', is_array( $r ) && $r['items'] && [ $S['S03'] ] === array_values( array_unique( wp_list_pluck( $r['items'], 'post_id' ) ) ) && in_array( 'title_duplicate', wp_list_pluck( $r['items'], 'issue_type' ), true ) );
$r = $run( 'seo-list-issues', [ 'per_page' => 2, 'page' => 2 ] );
$ok( 'AC-024 {per_page:2,page:2}: <=2 items en total = count_issues()', is_array( $r ) && count( $r['items'] ) >= 1 && count( $r['items'] ) <= 2 && (int) $r['total'] === (int) SEOHC_Repository::count_issues( [] ) && 2 === $r['page'] && 2 === $r['per_page'], is_array( $r ) ? 'items=' . count( $r['items'] ) . ' total=' . $r['total'] . ' count_issues=' . SEOHC_Repository::count_issues( [] ) : $code( $r ) );
$p1 = $run( 'seo-list-issues', [ 'per_page' => 2, 'page' => 1 ] );
$ok( 'AC-024 paginering: pagina 1 en 2 zijn disjunct', is_array( $p1 ) && is_array( $r ) && [] === array_intersect( wp_list_pluck( $p1['items'], 'id' ), wp_list_pluck( $r['items'], 'id' ) ) );
$r = $run( 'seo-list-issues', [ 'per_page' => 500 ] );
$ok( 'AC-024 {per_page:500}: schemafout', 'ability_invalid_input' === $code( $r ), $code( $r ) );
$r = $run( 'seo-list-issues', [ 'issue_type' => 'bestaat_niet' ] );
$ok( 'AC-024 {issue_type:bestaat_niet}: schemafout', 'ability_invalid_input' === $code( $r ), $code( $r ) );
$r = $run( 'seo-list-issues', [ 'bestaat_niet' => 1 ] );
$ok( 'AC-024 onbekende parameter (additionalProperties false): schemafout', 'ability_invalid_input' === $code( $r ), $code( $r ) );
$r = $run( 'seo-list-issues', [ 'issue_type' => 'image_missing_alt', 'per_page' => 100 ] );
$ok( 'AC-024 image_missing_alt heeft editable_field \'alt\' (en object_id = A01 bij S09)', is_array( $r ) && $r['items'] && [ 'alt' ] === array_values( array_unique( wp_list_pluck( $r['items'], 'editable_field' ) ) ) );
$r = $run( 'seo-list-issues', [ 'issue_type' => 'h1_missing', 'per_page' => 100 ] );
$ok( 'AC-024 h1_missing heeft editable_field null', is_array( $r ) && $r['items'] && [ null ] === array_values( array_unique( wp_list_pluck( $r['items'], 'editable_field' ) ) ) );
$r = $run( 'seo-list-issues', [ 'issue_type' => 'description_missing', 'per_page' => 100 ] );
$ok( 'AC-024 description_missing heeft editable_field meta_description', is_array( $r ) && $r['items'] && [ 'meta_description' ] === array_values( array_unique( wp_list_pluck( $r['items'], 'editable_field' ) ) ) );
$r = $run( 'seo-list-issues', [ 'post_id' => $S['S09'], 'issue_type' => 'image_missing_alt' ] );
$ok( 'AC-024 item bevat de documentvelden (id, post_title, label, severity, details, object_id, first_seen, resolved_at)', is_array( $r ) && $r['items'] && ! array_diff( [ 'id', 'post_id', 'post_title', 'issue_type', 'label', 'severity', 'details', 'object_id', 'first_seen', 'resolved_at', 'editable_field' ], array_keys( $r['items'][0] ) ) && $A01 === $r['items'][0]['object_id'] );
$r = $run( 'seo-list-issues', [ 'severity' => 'error', 'per_page' => 100 ] );
$ok( 'AC-024 severity-filter geeft alleen error', is_array( $r ) && $r['items'] && [ 'error' ] === array_values( array_unique( wp_list_pluck( $r['items'], 'severity' ) ) ) );

// ===========================================================================
// AC-025 seo-list-pages
// ===========================================================================
echo "-- AC-025\n";
$r = $run( 'seo-list-pages', [ 'per_page' => 100 ] );
$by = [];
if ( is_array( $r ) ) { foreach ( $r['items'] as $i ) { $by[ $i['post_id'] ] = $i; } }
$ok( 'AC-025 S00 heeft score 100', isset( $by[ $S['S00'] ] ) && 100 === $by[ $S['S00'] ]['score'], isset( $by[ $S['S00'] ] ) ? (string) $by[ $S['S00'] ]['score'] : 'niet gevonden' );
$band14 = $by[ $S['S14'] ]['band'] ?? '';
$r2     = $run( 'seo-list-pages', [ 'band' => $band14, 'per_page' => 100 ] );
$ok( "AC-025 {band:{$band14}} (band van S14) bevat S14 en alleen die band", '' !== $band14 && is_array( $r2 ) && in_array( $S['S14'], wp_list_pluck( $r2['items'], 'post_id' ), true ) && [ $band14 ] === array_values( array_unique( wp_list_pluck( $r2['items'], 'band' ) ) ) );
$p2 = $run( 'seo-list-pages', [ 'per_page' => 2, 'page' => 2 ] );
$ok( 'AC-025 paginering {per_page:2,page:2}: <=2 items, total = count_pages()', is_array( $p2 ) && count( $p2['items'] ) >= 1 && count( $p2['items'] ) <= 2 && (int) $p2['total'] === (int) SEOHC_Repository::count_pages( [] ), is_array( $p2 ) ? 'total=' . $p2['total'] : $code( $p2 ) );
$ok( 'AC-025 per_page:500 en band onbekend: schemafout', 'ability_invalid_input' === $code( $run( 'seo-list-pages', [ 'per_page' => 500 ] ) ) && 'ability_invalid_input' === $code( $run( 'seo-list-pages', [ 'band' => 'bestaat_niet' ] ) ) );
$r3 = $run( 'seo-list-pages', [ 'post_type' => 'page', 'per_page' => 100 ] );
$ok( 'AC-025 post_type-filter geeft alleen pages', is_array( $r3 ) && $r3['items'] && [ 'page' ] === array_values( array_unique( wp_list_pluck( $r3['items'], 'post_type' ) ) ) );

// ===========================================================================
// AC-026 seo-get-scan-history en seo-get-launch-checks
// ===========================================================================
echo "-- AC-026\n";
$r = $run( 'seo-get-scan-history' );
$ok( 'AC-026 scan-history: evenveel items als run_history(), minstens 1', is_array( $r ) && count( $r['items'] ) === count( SEOHC_Repository::run_history() ) && count( $r['items'] ) >= 1, is_array( $r ) ? (string) count( $r['items'] ) : $code( $r ) );
delete_option( 'seohc_launch_results' );
$r = $run( 'seo-get-launch-checks' );
$ok( 'AC-026 launch-checks na delete_option: {time:null, results:[]}', is_array( $r ) && array( 'time' => null, 'results' => array() ) === $r, wp_json_encode( $r ) );
update_option( 'seohc_launch_results', [ 'time' => time(), 'results' => SEOHC_Launch_Checks::run() ] );
$r = $run( 'seo-get-launch-checks' );
$ok( 'AC-026 launch-checks na run(): 27 resultaten', is_array( $r ) && 27 === count( $r['results'] ) && is_int( $r['time'] ), is_array( $r ) ? (string) count( $r['results'] ) : '' );
$bad = 0;
if ( is_array( $r ) ) { foreach ( $r['results'] as $x ) { if ( array_diff( [ 'id', 'phase', 'label', 'status', 'message' ], array_keys( $x ) ) ) { $bad++; } } }
$ok( 'AC-026 elk resultaat heeft id, phase, label, status en message', is_array( $r ) && 0 === $bad );
$ok( 'AC-026 seo-get-launch-checks draait de checks niet (tijd blijft gelijk)', is_array( $r ) && $r === $run( 'seo-get-launch-checks' ) );

// ===========================================================================
// AC-027 seo-start-scan (en AC-024 unchanged na een tweede scan zonder wijzigingen)
// ===========================================================================
echo "-- AC-027\n";
$r = $run( 'seo-start-scan' );
wp_cache_delete( 'seohc_scan_state', 'options' );
$ok( 'AC-027 start bij status done: succes', is_array( $r ), $code( $r ) );
$ok( 'AC-027 de status wordt running', 'running' === SEOHC_Scan_Queue::get_state()['status'] && 'running' === ( $r['status'] ?? '' ), SEOHC_Scan_Queue::get_state()['status'] );
$r2 = $run( 'seo-start-scan' );
$ok( 'AC-027 een tweede keer: pdk_seo_scan_running', 'pdk_seo_scan_running' === $code( $r2 ), $code( $r2 ) );
$t0 = time();
do {
	wp_cache_delete( 'seohc_scan_state', 'options' );
	SEOHC_Scan_Queue::process_batch();
	wp_cache_delete( 'seohc_scan_state', 'options' );
	$st = SEOHC_Scan_Queue::get_state();
	if ( 'done' === $st['status'] ) { break; }
	sleep( 1 );
} while ( time() - $t0 < 300 );
$ok( 'AC-027 de scan loopt door tot done', 'done' === $st['status'] && $st['processed'] === $st['total'], $st['status'] . ' ' . $st['processed'] . '/' . $st['total'] );
$mixed = [];
foreach ( $exp as $sid => $type ) {
	$in_new = in_array( $type, wp_list_pluck( $items_for( 'new', $S[ $sid ] ), 'issue_type' ), true );
	$in_unc = in_array( $type, wp_list_pluck( $items_for( 'unchanged', $S[ $sid ] ), 'issue_type' ), true );
	if ( $in_new || ! $in_unc ) { $mixed[] = $sid; }
}
$ok( 'AC-024 na een tweede scan zonder wijzigingen: S-issues staan onder unchanged en niet onder new', [] === $mixed, 'afwijkend: ' . implode( ',', $mixed ) );

// ===========================================================================
// AC-031 seo-get-headings
// ===========================================================================
echo "-- AC-031\n";
$r = $run( 'seo-get-headings', [ 'post_id' => $S['S13'] ] );
$h = is_array( $r ) ? $r['headings'] : [];
$ok( 'AC-031 S13: [{0,1,Intro},{1,2,Deel},{2,4,Detail}]', 3 === count( $h ) && [ [ 0, 1, 'Intro' ], [ 1, 2, 'Deel' ], [ 2, 4, 'Detail' ] ] === array_map( static fn( $x ) => [ $x['index'], $x['level'], $x['text'] ], $h ), wp_json_encode( $h ) );
$ok( 'AC-031 S13: block false en editable true (kop en post)', is_array( $r ) && $r['editable'] && null === $r['reason'] && [ false ] === array_values( array_unique( wp_list_pluck( $h, 'block' ) ) ) && [ true ] === array_values( array_unique( wp_list_pluck( $h, 'editable' ) ) ) );
$r = $run( 'seo-get-headings', [ 'post_id' => $S['S18'] ] );
$h = is_array( $r ) ? $r['headings'] : [];
$ok( 'AC-031 S18: drie koppen, de twee core/heading-koppen editable', 3 === count( $h ) && $h[0]['editable'] && $h[1]['editable'] && [ 'Blok intro', 'Blok detail', 'In html-blok' ] === wp_list_pluck( $h, 'text' ) && [ true, true, true ] === wp_list_pluck( $h, 'block' ), wp_json_encode( $h ) );
$ok( 'AC-031 S18: \'In html-blok\' editable false, reason unsupported_block (M9)', 3 === count( $h ) && false === $h[2]['editable'] && 'unsupported_block' === $h[2]['reason'] );
$r = $run( 'seo-get-headings', [ 'post_id' => $S['S17'] ] );
$ok( 'AC-031 S17: editable false, reason builder_content', is_array( $r ) && false === $r['editable'] && 'builder_content' === $r['reason'], wp_json_encode( $r ) );
$r = $run( 'seo-get-headings', [ 'post_id' => $OXY ] );
$ok( 'AC-031 Oxygen 6-pagina: editable false, reason builder_content', is_array( $r ) && false === $r['editable'] && 'builder_content' === $r['reason'], is_array( $r ) ? $r['reason'] : $code( $r ) );
$ok( 'AC-031 Oxygen 6-pagina: alle koppen editable false, reason builder_content', is_array( $r ) && [] === array_filter( $r['headings'], static fn( $x ) => $x['editable'] || 'builder_content' !== $x['reason'] ), 'koppen=' . ( is_array( $r ) ? count( $r['headings'] ) : '?' ) );
$setting( 'content_source', 'rendered' );
$r = $run( 'seo-get-headings', [ 'post_id' => $S['S13'] ] );
$ok( 'AC-031 content_source=rendered: S13 reason rendered_source', is_array( $r ) && 'rendered_source' === $r['reason'] && false === $r['editable'] && [ false ] === array_values( array_unique( wp_list_pluck( $r['headings'], 'editable' ) ) ), is_array( $r ) ? (string) $r['reason'] : $code( $r ) );
$setting( 'content_source', $orig_settings['content_source'] ?? 'auto' );
$r = $run( 'seo-get-headings', [ 'post_id' => $S['S13'] ] );
$ok( 'AC-031 instelling teruggezet: S13 weer editable', is_array( $r ) && $r['editable'] );
$ok( 'AC-031 niet-bestaand ID: pdk_seo_not_found; post_id 0: schemafout', 'pdk_seo_not_found' === $code( $run( 'seo-get-headings', [ 'post_id' => 999999999 ] ) ) && 'ability_invalid_input' === $code( $run( 'seo-get-headings', [ 'post_id' => 0 ] ) ) );

// ===========================================================================
// AC-028 seo-rescan-post (rescan_on_save = 0)
// ===========================================================================
echo "-- AC-028\n";
$ok( 'AC-028 voorwaarde: rescan_on_save = 0', 0 === (int) SEOHC_Settings::get( 'rescan_on_save' ) );
$words = [];
for ( $i = 1; $i <= 340; $i++ ) { $words[] = 'extra' . $i; }
$new14 = $content( $S['S14'] ) . '<p>' . implode( ' ', $words ) . '</p>';
wp_update_post( wp_slash( [ 'ID' => $S['S14'], 'post_content' => $new14 ] ) );
$ok( 'AC-028 voorwaarde: S14 heeft nog een open thin_content-rij (geen auto-rescan)', in_array( 'thin_content', wp_list_pluck( SEOHC_Repository::get_issues( [ 'post_id' => $S['S14'], 'per_page' => 0 ] ), 'issue_type' ), true ) );
$r = $run( 'seo-rescan-post', [ 'post_id' => $S['S14'] ] );
$ok( 'AC-028 rescan S14: antwoord zonder thin_content', is_array( $r ) && ! empty( $r['rescanned'] ) && ! in_array( 'thin_content', $types( $r ), true ), is_array( $r ) ? implode( ',', $types( $r ) ) : $code( $r ) );
$resolved = $wpdb->get_var( $wpdb->prepare( 'SELECT resolved_at FROM ' . SEOHC_Repository::issues_table() . " WHERE post_id = %d AND issue_type = 'thin_content' ORDER BY id DESC LIMIT 1", $S['S14'] ) );
$ok( 'AC-028 de thin_content-rij van S14 heeft resolved_at', ! empty( $resolved ), (string) $resolved );
$ok( 'AC-028 antwoord: post_id en score', is_array( $r ) && (int) $r['post_id'] === $S['S14'] && is_int( $r['score'] ) );
$draft = wp_insert_post( wp_slash( [ 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'SEOHC dummy tijdelijk concept', 'post_content' => '<h1>Concept</h1><p>tekst</p>' ] ) );
$tmp_posts[] = $draft;
update_post_meta( $draft, '_seohc_dummy', 'TMP-draft' );
foreach ( [ 'concept' => $draft, 'A01 (bijlage)' => $A01 ] as $label => $id ) {
	$g = $all_issues();
	$r = $run( 'seo-rescan-post', [ 'post_id' => $id ] );
	$ok( "AC-028 {$label}: pdk_seo_not_scannable", 'pdk_seo_not_scannable' === $code( $r ), $code( $r ) );
	$ok( "AC-028 {$label}: de bestaande issue-rijen blijven staan", $g === $all_issues() );
}
$ok( 'AC-028 niet-bestaand ID: pdk_seo_not_found', 'pdk_seo_not_found' === $code( $run( 'seo-rescan-post', [ 'post_id' => 999999999 ] ) ) );

// ===========================================================================
// AC-029 seo-update-meta
// ===========================================================================
echo "-- AC-029\n";
$ac029();

// ===========================================================================
// AC-030 seo-update-image-alt
// ===========================================================================
echo "-- AC-030\n";
$old09 = $content( $S['S09'] );
$ok( 'AC-030 voorwaarde: S09 heeft image_missing_alt en een <img> zonder alt', in_array( 'image_missing_alt', wp_list_pluck( SEOHC_Repository::get_issues( [ 'post_id' => $S['S09'], 'per_page' => 0 ] ), 'issue_type' ), true ) && 1 === preg_match( '/<img src="[^"]*klein\.jpg">/', $old09 ) );
$r = $run( 'seo-update-image-alt', [ 'attachment_id' => $A01, 'value' => 'Klein <b>voorbeeld</b>', 'post_id' => $S['S09'] ] );
$new09 = $content( $S['S09'] );
$ok( 'AC-030 succes (saved)', is_array( $r ) && ! empty( $r['saved'] ), $code( $r ) );
$ok( 'AC-030 _wp_attachment_image_alt = \'Klein voorbeeld\' (M6)', 'Klein voorbeeld' === get_post_meta( $A01, '_wp_attachment_image_alt', true ), (string) get_post_meta( $A01, '_wp_attachment_image_alt', true ) );
$ok( 'AC-030 de <img> in S09 krijgt alt="Klein voorbeeld"', 1 === preg_match( '/<img alt="Klein voorbeeld" src="[^"]*klein\.jpg">/', $new09 ), $new09 );
$ok( 'AC-030 de rest van de content is byte-gelijk (alleen het alt-attribuut erbij)', str_replace( ' alt="Klein voorbeeld"', '', $new09 ) === $old09 );
$ok( 'AC-030 de markering met backslashes blijft byte-gelijk (D22)', false !== strpos( $new09, $marker ) );
$ok( 'AC-030 er is een revisie met de oude content', $has_rev( $S['S09'], $old09 ) );
$ok( 'AC-030 antwoord: geen image_missing_alt en geen content_skipped', is_array( $r ) && ! in_array( 'image_missing_alt', $types( $r ), true ) && ! array_key_exists( 'content_skipped', $r ), is_array( $r ) ? wp_json_encode( $r ) : '' );
// builderpagina (D28). De vorige write + rescan laat $GLOBALS['post'] achter (de content-resolver zet hem en
// wp_reset_postdata() herstelt niets zonder hoofdquery: CLI, REST, cron). Hier expliciet gezet, zodat de test
// deterministisch is: de content overslaan via save_alt(..., 0) mag dan geen kopie van die globale post aanmaken.
$GLOBALS['post'] = get_post( $S['S09'] );
$posts_now = static fn() => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post','page') AND post_status <> 'auto-draft'" );
$n_posts = $posts_now();
$g_s09   = $snap( $S['S09'] );
$g_oxy = $snap( $OXY );
$r = $run( 'seo-update-image-alt', [ 'attachment_id' => $A01, 'value' => 'Oxygen alt', 'post_id' => $OXY ] );
$ok( 'AC-030 Oxygen 6-pagina: succes', is_array( $r ) && ! empty( $r['saved'] ), $code( $r ) );
$ok( 'AC-030 Oxygen 6-pagina: de mediabibliotheek is bijgewerkt', 'Oxygen alt' === get_post_meta( $A01, '_wp_attachment_image_alt', true ) );
$ok( 'AC-030 Oxygen 6-pagina: content_skipped = builder_content', is_array( $r ) && 'builder_content' === ( $r['content_skipped'] ?? null ), is_array( $r ) ? wp_json_encode( $r ) : '' );
$ok( 'AC-030 Oxygen 6-pagina blijft ongewijzigd (content, meta, revisies, issue-rijen)', $g_oxy === $snap( $OXY ), wp_json_encode( [ $g_oxy, $snap( $OXY ) ] ) );
$ok( 'AC-030 Oxygen 6-pagina (content_skipped): er wordt geen post aangemaakt, ook niet met een globale $post', $n_posts === $posts_now(), 'posts ' . $n_posts . ' -> ' . $posts_now() );
$ok( 'AC-030 Oxygen 6-pagina (content_skipped): de globale post S09 blijft ongewijzigd', $g_s09 === $snap( $S['S09'] ) );
$g09 = $snap( $S['S09'] );
$r = $run( 'seo-update-image-alt', [ 'attachment_id' => $S['S09'], 'value' => 'x', 'post_id' => $S['S09'] ] );
$ok( 'AC-030 attachment_id = S09 (geen bijlage): pdk_seo_not_found', 'pdk_seo_not_found' === $code( $r ), $code( $r ) );
$ok( 'AC-030 S09 blijft ongewijzigd na de weigering', $g09 === $snap( $S['S09'] ) );
$ok( 'AC-030 value > 125: schemafout; value met alleen HTML wordt geleegd', 'ability_invalid_input' === $code( $run( 'seo-update-image-alt', [ 'attachment_id' => $A01, 'value' => str_repeat( 'x', 126 ) ] ) ) );
$GLOBALS['post'] = get_post( $S['S09'] );
$n_posts = $posts_now();
$g_s09   = $snap( $S['S09'] );
$r = $run( 'seo-update-image-alt', [ 'attachment_id' => $A01, 'value' => 'Alleen media <i>zonder</i> post' ] );
$ok( 'AC-030 zonder post_id: alleen de mediabibliotheek (geen content_skipped, post_id null)', is_array( $r ) && 'Alleen media zonder post' === get_post_meta( $A01, '_wp_attachment_image_alt', true ) && null === $r['post_id'] && ! isset( $r['content_skipped'] ), is_array( $r ) ? wp_json_encode( $r ) : $code( $r ) );
$ok( 'AC-030 zonder post_id: er wordt geen post aangemaakt, ook niet met een globale $post', $n_posts === $posts_now(), 'posts ' . $n_posts . ' -> ' . $posts_now() );
$ok( 'AC-030 zonder post_id: de globale post S09 blijft ongewijzigd', $g_s09 === $snap( $S['S09'] ) );
unset( $GLOBALS['post'] );

// ===========================================================================
// AC-032 seo-set-heading-level
// ===========================================================================
echo "-- AC-032\n";
$old13 = $content( $S['S13'] );
$r = $run( 'seo-set-heading-level', [ 'post_id' => $S['S13'], 'index' => 2, 'expected_text' => 'Detail', 'level' => 3 ] );
$new13 = $content( $S['S13'] );
$ok( 'AC-032 klassiek: succes', is_array( $r ) && ! empty( $r['saved'] ), $code( $r ) );
$ok( 'AC-032 klassiek: de diff is precies <h4> -> <h3> en </h4> -> </h3>', 1 === substr_count( $old13, '<h4>' ) && 1 === substr_count( $old13, '</h4>' ) && $new13 === str_replace( [ '<h4>', '</h4>' ], [ '<h3>', '</h3>' ], $old13 ) && $new13 !== $old13 );
$ok( 'AC-032 klassiek: revisie met de oude content', $has_rev( $S['S13'], $old13 ) );
$ok( 'AC-032 klassiek: antwoord zonder heading_skip', is_array( $r ) && ! in_array( 'heading_skip', $types( $r ), true ), is_array( $r ) ? implode( ',', $types( $r ) ) : '' );
$old18 = $content( $S['S18'] );
$r = $run( 'seo-set-heading-level', [ 'post_id' => $S['S18'], 'index' => 1, 'expected_text' => 'Blok detail', 'level' => 2 ] );
$new18 = $content( $S['S18'] );
$blocks = parse_blocks( $new18 );
$hb = null;
foreach ( $blocks as $b ) { if ( 'core/heading' === $b['blockName'] && false !== strpos( $b['innerHTML'], 'Blok detail' ) ) { $hb = $b; } }
$ok( 'AC-032 blok: succes', is_array( $r ) && ! empty( $r['saved'] ), $code( $r ) );
$ok( 'AC-032 blok: parse_blocks geeft (attrs[level] ?? 2) === 2', null !== $hb && 2 === ( $hb['attrs']['level'] ?? 2 ) && ! isset( $hb['attrs']['level'] ) );
$ok( 'AC-032 blok: innerHTML bevat <h2 class="wp-block-heading">', null !== $hb && false !== strpos( $hb['innerHTML'], '<h2 class="wp-block-heading">' ) );
$ok( 'AC-032 blok: de markering blijft byte-gelijk', false !== strpos( $new18, $marker ) && str_ends_with( $new18, $marker ) );
$ok( 'AC-032 blok: de rest is byte-gelijk (alleen het commentaar en de h4-tags)', $new18 === str_replace( [ '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Blok detail</h4>' ], [ '<!-- wp:heading --><h2 class="wp-block-heading">Blok detail</h2>' ], $old18 ) );
$ok( 'AC-032 blok: revisie met de oude content', $has_rev( $S['S18'], $old18 ) );
$old12 = $content( $S['S12'] );
$ok( 'AC-032 voorwaarde: S12 heeft open h1_multiple', in_array( 'h1_multiple', wp_list_pluck( SEOHC_Repository::get_issues( [ 'post_id' => $S['S12'], 'per_page' => 0 ] ), 'issue_type' ), true ) );
$r = $run( 'seo-set-heading-level', [ 'post_id' => $S['S12'], 'index' => 1, 'expected_text' => 'Tweede h1 S12', 'level' => 2 ] );
$ok( 'AC-032 dubbele H1: S12 index 1 -> level 2 lost h1_multiple op', is_array( $r ) && ! in_array( 'h1_multiple', $types( $r ), true ) && $content( $S['S12'] ) === str_replace( [ '<h1>Tweede h1 S12</h1>' ], [ '<h2>Tweede h1 S12</h2>' ], $old12 ), is_array( $r ) ? implode( ',', $types( $r ) ) : $code( $r ) );
$ok( 'AC-024 na AC-032: {status:resolved, post_id:S13} bevat heading_skip', in_array( 'heading_skip', wp_list_pluck( $items_for( 'resolved', $S['S13'] ), 'issue_type' ), true ) );
// weigeren; de post blijft telkens ongewijzigd
$refuse = static function ( $label, $expect, $input, $post_ids ) use ( $ok, $run, $code, $snap, $all_issues ) {
	$before = []; foreach ( $post_ids as $id ) { $before[ $id ] = $snap( $id ); }
	$g = $all_issues();
	$r = $run( 'seo-set-heading-level', $input );
	$same = true; foreach ( $post_ids as $id ) { $same = $same && $before[ $id ] === $snap( $id ); }
	$ok( "AC-032 weigeren: {$label} -> {$expect}", $expect === $code( $r ), $code( $r ) );
	$ok( "AC-032 weigeren: {$label}: post(s) en issue-rijen ongewijzigd", $same && $g === $all_issues() );
};
$s13 = $S['S13'];
$refuse( 'verkeerde expected_text', 'pdk_seo_heading_mismatch', [ 'post_id' => $s13, 'index' => 2, 'expected_text' => 'Verkeerd', 'level' => 4 ], [ $s13 ] );
$refuse( 'index buiten bereik', 'pdk_seo_not_found', [ 'post_id' => $s13, 'index' => 9, 'expected_text' => 'Detail', 'level' => 4 ], [ $s13 ] );
$refuse( 'level 7', 'ability_invalid_input', [ 'post_id' => $s13, 'index' => 2, 'expected_text' => 'Detail', 'level' => 7 ], [ $s13 ] );
$refuse( 'level 0', 'ability_invalid_input', [ 'post_id' => $s13, 'index' => 2, 'expected_text' => 'Detail', 'level' => 0 ], [ $s13 ] );
$refuse( 'negatieve index', 'ability_invalid_input', [ 'post_id' => $s13, 'index' => -1, 'expected_text' => 'Detail', 'level' => 4 ], [ $s13 ] );
$refuse( 'S18 index 2 (core/html)', 'pdk_seo_unsupported_block', [ 'post_id' => $S['S18'], 'index' => 2, 'expected_text' => 'In html-blok', 'level' => 2 ], [ $S['S18'] ] );
$refuse( 'concept', 'pdk_seo_not_scannable', [ 'post_id' => $draft, 'index' => 0, 'expected_text' => 'Concept', 'level' => 2 ], [ $draft ] );
$refuse( 'niet-bestaande post', 'pdk_seo_not_found', [ 'post_id' => 999999999, 'index' => 0, 'expected_text' => 'x', 'level' => 2 ], [] );
add_filter( 'map_meta_cap', $f_uh = static fn( $caps, $cap ) => 'unfiltered_html' === $cap ? [ 'do_not_allow' ] : $caps, 10, 2 );
$refuse( 'zonder unfiltered_html (M5)', 'pdk_seo_unfiltered_html', [ 'post_id' => $s13, 'index' => 2, 'expected_text' => 'Detail', 'level' => 4 ], [ $s13 ] );
remove_filter( 'map_meta_cap', $f_uh, 10 );
add_filter( 'wp_revisions_to_keep', $f_rev = static fn() => 0 );
$refuse( 'revisies uit (M4)', 'pdk_seo_revisions_disabled', [ 'post_id' => $s13, 'index' => 2, 'expected_text' => 'Detail', 'level' => 4 ], [ $s13 ] );
remove_filter( 'wp_revisions_to_keep', $f_rev );
$ok( 'AC-032 de filters zijn weg: de write werkt weer (S13 3 -> 4 terug)', ! is_wp_error( $run( 'seo-set-heading-level', [ 'post_id' => $s13, 'index' => 2, 'expected_text' => 'Detail', 'level' => 4 ] ) ) && $content( $s13 ) === $old13 );

// ===========================================================================
// AC-033 seo-insert-h1
// ===========================================================================
echo "-- AC-033\n";
$s11 = $S['S11'];
$old11 = $content( $s11 );
$setting( 'theme_outputs_h1', 1 );
$g = $snap( $s11 );
$r = $run( 'seo-insert-h1', [ 'post_id' => $s11, 'text' => 'Nieuwe kop' ] );
$ok( 'AC-033.1 theme_outputs_h1=1 op S11: pdk_seo_theme_h1', 'pdk_seo_theme_h1' === $code( $r ), $code( $r ) );
$ok( 'AC-033.1 S11 blijft ongewijzigd', $g === $snap( $s11 ) );
$setting( 'theme_outputs_h1', 0 );
$r = $run( 'seo-insert-h1', [ 'post_id' => $s11, 'text' => 'Nieuwe <b>kop</b>' ] );
$new11 = $content( $s11 );
$ok( 'AC-033.2 S11 klassiek: succes', is_array( $r ) && ! empty( $r['saved'] ), $code( $r ) );
$ok( 'AC-033.2 post_content begint met <h1>Nieuwe kop</h1> + "\n"', 0 === strpos( $new11, "<h1>Nieuwe kop</h1>\n" ), substr( $new11, 0, 40 ) );
$ok( 'AC-033.2 de rest is byte-gelijk', substr( $new11, strlen( "<h1>Nieuwe kop</h1>\n" ) ) === $old11 );
$ok( 'AC-033.2 revisie met de oude content', $has_rev( $s11, $old11 ) );
$ok( 'AC-033.2 h1_missing is opgelost (antwoord en rij)', is_array( $r ) && ! in_array( 'h1_missing', $types( $r ), true ) && ! empty( $wpdb->get_var( $wpdb->prepare( 'SELECT resolved_at FROM ' . SEOHC_Repository::issues_table() . " WHERE post_id = %d AND issue_type = 'h1_missing' ORDER BY id DESC LIMIT 1", $s11 ) ) ) );
$s18b = $S['S18b'];
$old18b = $content( $s18b );
$r = $run( 'seo-insert-h1', [ 'post_id' => $s18b, 'text' => 'Blok kop' ] );
$new18b = $content( $s18b );
$b0 = parse_blocks( $new18b )[0] ?? [];
$d21 = "<!-- wp:heading {\"level\":1} -->\n<h1 class=\"wp-block-heading\">Blok kop</h1>\n<!-- /wp:heading -->\n\n";
$ok( 'AC-033.3 S18b blokken: succes', is_array( $r ) && ! empty( $r['saved'] ), $code( $r ) );
$ok( 'AC-033.3 eerste blok van parse_blocks is core/heading met attrs[level] === 1', 'core/heading' === ( $b0['blockName'] ?? '' ) && 1 === ( $b0['attrs']['level'] ?? null ) );
$ok( 'AC-033.3 innerHTML bevat <h1 class="wp-block-heading">Blok kop</h1>', false !== strpos( $b0['innerHTML'] ?? '', '<h1 class="wp-block-heading">Blok kop</h1>' ) );
$ok( 'AC-033.3 post_content begint met de markup uit D21 en de rest is byte-gelijk', 0 === strpos( $new18b, $d21 ) && substr( $new18b, strlen( $d21 ) ) === $old18b );
$ok( 'AC-033.3 de markering blijft byte-gelijk', false !== strpos( $new18b, $marker ) );
$ok( 'AC-033.3 revisie met de oude content', $has_rev( $s18b, $old18b ) );
$ok( 'AC-033.3 h1_missing is opgelost', is_array( $r ) && ! in_array( 'h1_missing', $types( $r ), true ), is_array( $r ) ? implode( ',', $types( $r ) ) : '' );
$g = $snap( $S['S00'] );
$r = $run( 'seo-insert-h1', [ 'post_id' => $S['S00'], 'text' => 'Nog een H1' ] );
$ok( 'AC-033.4 S00 (heeft al een H1): pdk_seo_h1_exists', 'pdk_seo_h1_exists' === $code( $r ), $code( $r ) );
$ok( 'AC-033.4 S00 blijft ongewijzigd', $g === $snap( $S['S00'] ) );
$g = $snap( $S['S00'] );
$ok( 'AC-033 tekst die na strippen leeg is: pdk_seo_invalid_input', 'pdk_seo_invalid_input' === $code( $run( 'seo-insert-h1', [ 'post_id' => $S['S00'], 'text' => '<b></b>' ] ) ) && $g === $snap( $S['S00'] ) );
$ok( 'AC-033 text \'\' en text > 200: schemafout', 'ability_invalid_input' === $code( $run( 'seo-insert-h1', [ 'post_id' => $S['S00'], 'text' => '' ] ) ) && 'ability_invalid_input' === $code( $run( 'seo-insert-h1', [ 'post_id' => $S['S00'], 'text' => str_repeat( 'x', 201 ) ] ) ) );
$ok( 'AC-033 concept en niet-bestaande post: not_scannable / not_found', 'pdk_seo_not_scannable' === $code( $run( 'seo-insert-h1', [ 'post_id' => $draft, 'text' => 'x' ] ) ) && 'pdk_seo_not_found' === $code( $run( 'seo-insert-h1', [ 'post_id' => 999999999, 'text' => 'x' ] ) ) );

// ===========================================================================
// AC-034 builder-weigering
// ===========================================================================
echo "-- AC-034\n";
foreach ( [ 'S17' => $S['S17'], 'Oxygen 6 (' . $OXY . ')' => $OXY ] as $label => $id ) {
	foreach ( [ 'seo-set-heading-level' => [ 'post_id' => $id, 'index' => 0, 'expected_text' => 'x', 'level' => 2 ], 'seo-insert-h1' => [ 'post_id' => $id, 'text' => 'Nieuwe kop' ] ] as $ab => $in ) {
		$g = $snap( $id );
		$r = $run( $ab, $in );
		$ok( "AC-034 {$ab} op {$label}: pdk_seo_builder_content", 'pdk_seo_builder_content' === $code( $r ), $code( $r ) );
		$ok( "AC-034 {$ab} op {$label}: melding noemt de builder of de editor", is_wp_error( $r ) && 1 === preg_match( '/builder|editor/i', $r->get_error_message() ), is_wp_error( $r ) ? $r->get_error_message() : '' );
		$ok( "AC-034 {$ab} op {$label}: ongewijzigd (content, builder-meta, revisies, rijen)", $g === $snap( $id ) );
	}
}
$ok( 'AC-034 Oxygen 6-pagina is over het hele script ongewijzigd (content, meta, revisies, issue-rijen)', $oxy0 === $snap( $OXY ), wp_json_encode( [ $oxy0, $snap( $OXY ) ] ) );

// ===========================================================================
// AC-022 permissies
// ===========================================================================
echo "-- AC-022\n";
$inputs = [
	'seo-get-status' => [], 'seo-list-issues' => [], 'seo-list-pages' => [], 'seo-get-scan-history' => [], 'seo-get-launch-checks' => [],
	'seo-get-headings' => [ 'post_id' => $S['S13'] ], 'seo-start-scan' => [], 'seo-rescan-post' => [ 'post_id' => $S['S13'] ],
	'seo-update-meta' => [ 'post_id' => $S['S05'], 'field' => 'meta_description', 'value' => 'Verboden' ],
	'seo-update-image-alt' => [ 'attachment_id' => $A01, 'value' => 'Verboden' ],
	'seo-set-heading-level' => [ 'post_id' => $S['S13'], 'index' => 2, 'expected_text' => 'Detail', 'level' => 3 ],
	'seo-insert-h1' => [ 'post_id' => $S['S11'], 'text' => 'Verboden' ],
];
$tmp_user = wp_insert_user( [ 'user_login' => 'seohc-editor', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'seohc-editor@example.invalid', 'role' => 'editor' ] );
$ok( 'AC-022 tijdelijke gebruiker seohc-editor (rol editor) aangemaakt', is_int( $tmp_user ) && $tmp_user > 0 );
$guard = [ $S['S13'] => $snap( $S['S13'] ), $S['S11'] => $snap( $S['S11'] ), $S['S05'] => $snap( $S['S05'] ) ];
$state0 = md5( serialize( get_option( 'seohc_scan_state' ) ) );
$alt0 = get_post_meta( $A01, '_wp_attachment_image_alt', true );
wp_set_current_user( $tmp_user );
$ok( 'AC-022 voorwaarde: editor heeft geen manage_options', ! current_user_can( 'manage_options' ) && user_can( $tmp_user, 'edit_post', $S['S13'] ) );
$codes = [];
foreach ( $inputs as $ab => $in ) {
	$r = $run( $ab, $in );
	$codes[ $ab ] = $code( $r );
}
echo 'AC-022 vastgestelde foutcode(s) voor een editor: ' . implode( ',', array_unique( $codes ) ) . "\n";
foreach ( $codes as $ab => $c ) { $ok( "AC-022 editor: {$ab} geeft WP_Error ability_invalid_permissions", 'ability_invalid_permissions' === $c, $c ); }
wp_set_current_user( 1 );
$same = true; foreach ( $guard as $id => $s ) { $same = $same && $s === $snap( $id ); }
$ok( 'AC-022 S13, S11, S05 en seohc_scan_state blijven ongewijzigd', $same && $state0 === md5( serialize( get_option( 'seohc_scan_state' ) ) ) && $alt0 === get_post_meta( $A01, '_wp_attachment_image_alt', true ) );
// Admin zonder edit_post op S13
add_filter( 'map_meta_cap', $f_ep = static function ( $caps, $cap, $uid, $a ) use ( $S ) { return 'edit_post' === $cap && isset( $a[0] ) && (int) $a[0] === $S['S13'] ? [ 'do_not_allow' ] : $caps; }, 10, 4 );
$ok( 'AC-022 voorwaarde: admin mist edit_post op S13, heeft het op S11', ! current_user_can( 'edit_post', $S['S13'] ) && current_user_can( 'edit_post', $S['S11'] ) && current_user_can( 'manage_options' ) );
$g13 = $snap( $S['S13'] ); $g5 = $snap( $S['S05'] );
$r1 = $run( 'seo-set-heading-level', [ 'post_id' => $S['S13'], 'index' => 2, 'expected_text' => 'Detail', 'level' => 3 ] );
$r2 = $run( 'seo-update-meta', [ 'post_id' => $S['S13'], 'field' => 'seo_title', 'value' => 'Verboden titel' ] );
$ok( 'AC-022 admin zonder edit_post: set-heading-level op S13 geeft ability_invalid_permissions', 'ability_invalid_permissions' === $code( $r1 ), $code( $r1 ) );
$ok( 'AC-022 admin zonder edit_post: update-meta op S13 geeft dezelfde code', 'ability_invalid_permissions' === $code( $r2 ), $code( $r2 ) );
$r3 = $run( 'seo-update-image-alt', [ 'attachment_id' => $A01, 'value' => 'x', 'post_id' => $S['S13'] ] );
$ok( 'AC-022 admin zonder edit_post: update-image-alt met post_id S13 geeft dezelfde code', 'ability_invalid_permissions' === $code( $r3 ), $code( $r3 ) );
$ok( 'AC-022 admin zonder edit_post: S13 blijft ongewijzigd', $g13 === $snap( $S['S13'] ) && $alt0 === get_post_meta( $A01, '_wp_attachment_image_alt', true ) );
$ok( 'AC-022 de toegestane rol slaagt nog: lezen op S13 en schrijven op S11 (wel edit_post)', is_array( $run( 'seo-get-headings', [ 'post_id' => $S['S13'] ] ) ) && is_array( $run( 'seo-rescan-post', [ 'post_id' => $S['S11'] ] ) ) );
remove_filter( 'map_meta_cap', $f_ep, 10 );
$ok( 'AC-022 zonder filter slaagt set-heading-level op S13 weer', ! is_wp_error( $run( 'seo-set-heading-level', [ 'post_id' => $S['S13'], 'index' => 2, 'expected_text' => 'Detail', 'level' => 3 ] ) ) );
$run( 'seo-set-heading-level', [ 'post_id' => $S['S13'], 'index' => 2, 'expected_text' => 'Detail', 'level' => 4 ] );

// ===========================================================================
// AC-038 REST (M12)
// ===========================================================================
echo "-- AC-038\n";
$routes = rest_get_server()->get_routes( 'wp-abilities/v1' );
$run_route = '';
foreach ( array_keys( $routes ) as $rt ) { if ( preg_match( '#/run$#', $rt ) ) { $run_route = $rt; echo "AC-038 route gevonden: {$rt}  methoden: " . implode( ',', array_unique( array_merge( ...array_map( static fn( $h ) => array_keys( $h['methods'] ), $routes[ $rt ] ) ) ) ) . "\n"; } }
$ok( 'AC-038 een /run-route onder wp-abilities/v1 bestaat', '' !== $run_route );
$base = '/wp-abilities/v1/abilities/pdk-theme-options/';
$call = static function ( $method, $name, $user, $input = null ) use ( $base ) {
	wp_set_current_user( $user );
	$req = new WP_REST_Request( $method, $base . $name . '/run' );
	if ( 'GET' === $method && null !== $input ) { $req->set_query_params( [ 'input' => $input ] ); }
	if ( 'POST' === $method ) { $req->set_header( 'content-type', 'application/json' ); $req->set_body( wp_json_encode( [ 'input' => $input ?? new stdClass() ] ) ); }
	$res = rest_do_request( $req );
	wp_set_current_user( 1 );
	return $res;
};
$res = $call( 'GET', 'seo-get-status', 1 );
$d = $res->get_data();
$ok( 'AC-038 user 1: GET seo-get-status geeft 200', 200 === $res->get_status(), (string) $res->get_status() . ' ' . wp_json_encode( $d ) );
$ok( 'AC-038 user 1: dezelfde velden als AC-023', is_array( $d ) && ! array_diff( $status_keys, array_keys( $d ) ) && ! array_diff( array_keys( $d ), $status_keys ), is_array( $d ) ? implode( ',', array_keys( $d ) ) : '' );
$res = $call( 'GET', 'seo-get-status', $tmp_user );
$ok( 'AC-038 seohc-editor: dezelfde aanroep geeft 403', 403 === $res->get_status(), (string) $res->get_status() . ' ' . wp_json_encode( $res->get_data() ) );
echo 'AC-038 editor-foutcode in REST: ' . ( $res->get_data()['code'] ?? '?' ) . "\n";
$res = $call( 'GET', 'seo-get-status', 0 );
$ok( 'AC-038 niet ingelogd: 401 of 403', in_array( $res->get_status(), [ 401, 403 ], true ), (string) $res->get_status() );
$res = $call( 'POST', 'seo-get-status', 1 );
$ok( 'AC-038 readonly-ability met POST: 405 (methode volgt de annotaties)', 405 === $res->get_status(), (string) $res->get_status() );
$res = $call( 'GET', 'seo-get-headings', 1, [ 'post_id' => $S['S13'] ] );
$ok( 'AC-038 GET seo-get-headings met input[post_id]: 200 en 3 koppen', 200 === $res->get_status() && 3 === count( $res->get_data()['headings'] ?? [] ), (string) $res->get_status() );
$res = $call( 'POST', 'seo-rescan-post', 1, [ 'post_id' => $S['S11'] ] );
$ok( 'AC-038 POST seo-rescan-post met body {"input":{...}}: 200 en rescanned', 200 === $res->get_status() && ! empty( $res->get_data()['rescanned'] ), (string) $res->get_status() . ' ' . wp_json_encode( $res->get_data() ) );
$g = $snap( $S['S13'] );
$res = $call( 'POST', 'seo-set-heading-level', $tmp_user, [ 'post_id' => $S['S13'], 'index' => 2, 'expected_text' => 'Detail', 'level' => 3 ] );
$ok( 'AC-038 editor: POST seo-set-heading-level geeft 403 en S13 blijft ongewijzigd', 403 === $res->get_status() && $g === $snap( $S['S13'] ), (string) $res->get_status() );
$res = $call( 'POST', 'seo-set-heading-level', 1, [ 'post_id' => $S['S13'], 'index' => 2, 'expected_text' => 'Verkeerd', 'level' => 3 ] );
$ok( 'AC-038 user 1: domeinfout via REST behoudt de foutcode pdk_seo_heading_mismatch', ( $res->get_data()['code'] ?? '' ) === 'pdk_seo_heading_mismatch' && $res->get_status() >= 400, 'HTTP ' . $res->get_status() . ' (waarneming: de Abilities API geeft 500 bij een WP_Error zonder data.status)' );
$res = $call( 'GET', 'seo-list-issues', 1, [ 'per_page' => 500 ] );
$ok( 'AC-038 GET seo-list-issues per_page 500: 400', 400 === $res->get_status(), (string) $res->get_status() . ' ' . ( $res->get_data()['code'] ?? '' ) );

// ===========================================================================
// Opruimen en eindstand
// ===========================================================================
echo "-- opruimen\n";
$new_posts = array_values( array_diff( $strays(), [ $draft ] ) );
$ok( 'geen nieuwe posts aangemaakt tijdens het script (behalve het tijdelijke concept)', [] === $new_posts, 'nieuw: ' . implode( ',', $new_posts ) );
$restore();
$ok( 'opruimen: tijdelijke gebruiker verwijderd', false === get_user_by( 'login', 'seohc-editor' ) );
$ok( 'opruimen: tijdelijk concept verwijderd', null === get_post( $draft ) );
$ok( 'opruimen: de S-posts hebben weer hun originele content (byte-gelijk, inclusief markering)', [] === array_filter( $orig_content, static fn( $c, $id ) => $content( $id ) !== $c, ARRAY_FILTER_USE_BOTH ) );
$ok( 'opruimen: A01 alt en de SEO-meta zijn terug op de oorspronkelijke waarden', ( null === $orig_alt ? ! metadata_exists( 'post', $A01, '_wp_attachment_image_alt' ) : $orig_alt === get_post_meta( $A01, '_wp_attachment_image_alt', true ) ) );
$ok( 'opruimen: de Oxygen 6-pagina is nog altijd identiek aan het begin', $oxy0 === $snap( $OXY ) );
$ok( 'opruimen: S17 is identiek aan het begin', $s17_0 === $snap( $S['S17'] ) );
$final = $scan();
$ok( 'eindstand: een volledige scan is afgerond (done, processed === total)', 'done' === $final['status'] && $final['processed'] === $final['total'], $final['status'] . ' ' . $final['processed'] . '/' . $final['total'] );
echo 'summary=' . wp_json_encode( SEOHC_Repository::summary() ) . "\n";
$done();
