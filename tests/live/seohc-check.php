<?php
/**
 * AC-005 (L): scan-end-to-end op de dummy-data. Eerst seohc-seed.php, dan:
 *
 *   wpl --user=1 eval-file tests/live/seohc-check.php           (exit 0 = geslaagd)
 *
 * Draait met de provider die er staat (Yoast, Rank Math of none) en moet bij elke slagen.
 */

if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

$fail = 0;
$ok   = static function ( $name, $cond, $detail = '' ) use ( &$fail ) {
	echo ( $cond ? 'PASS' : 'FAIL' ) . '  ' . $name . ( $detail !== '' ? '  [' . $detail . ']' : '' ) . "\n";
	if ( ! $cond ) { $fail++; }
};

if ( ! class_exists( 'SEOHC_Scan_Queue' ) ) {
	echo "FAIL  SEOHC niet geladen (module uit of losse plugin?)\n";
	exit( 1 );
}

// S-ID => post-ID
$map = [];
foreach ( get_posts( [ 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_seohc_dummy' ] ) as $p ) {
	$map[ get_post_meta( $p->ID, '_seohc_dummy', true ) ] = $p->ID;
}

// 1. Markering (B1)
$bs     = chr( 92 );
$marker = '<!-- wp:paragraph {"placeholder":"a' . $bs . 'u0026b"} --><p>C:' . $bs . 'pad</p><!-- /wp:paragraph -->';
foreach ( [ 'S09', 'S18', 'S18b' ] as $sid ) {
	$has = isset( $map[ $sid ] ) && false !== strpos( (string) get_post_field( 'post_content', $map[ $sid ] ), $marker );
	if ( ! $has ) {
		echo "FAIL  markering ontbreekt in {$sid}: seed opnieuw draaien\n";
		exit( 1 );
	}
}
echo "PASS  slash-markering aanwezig in S09, S18, S18b\n";

// 2. Start
$started = SEOHC_Scan_Queue::start();
$ok( 'start() geeft true', true === $started );
$as = SEOHC_Scan_Queue::use_action_scheduler();
$queued = $as ? as_has_scheduled_action( 'seohc_process_batch' ) : (bool) wp_next_scheduled( 'seohc_process_batch' );
$ok( 'batch staat klaar', (bool) $queued, $as ? 'Action Scheduler' : 'WP-Cron' );

// 3. Batches
$t0 = time();
do {
	wp_cache_delete( 'seohc_scan_state', 'options' );
	SEOHC_Scan_Queue::process_batch();
	wp_cache_delete( 'seohc_scan_state', 'options' );
	$state = SEOHC_Scan_Queue::get_state();
	if ( 'done' === $state['status'] ) { break; }
	sleep( 1 );
} while ( time() - $t0 < 300 );

echo 'provider=' . SEOHC_SEO_Meta::provider_label() . ' mechanisme=' . ( $as ? 'Action Scheduler' : 'WP-Cron' ) . "\n";
$ok( 'status done', 'done' === $state['status'], $state['status'] );
$ok( 'processed === total', (int) $state['processed'] === (int) $state['total'], $state['processed'] . '/' . $state['total'] );

// 4. Issues per S-post
$expect = [
	'S01' => 'title_missing', 'S02' => 'title_too_long', 'S03' => 'title_duplicate', 'S04' => 'title_duplicate',
	'S05' => 'description_missing', 'S06' => 'description_too_long', 'S07' => 'description_duplicate', 'S08' => 'description_duplicate',
	'S09' => 'image_missing_alt', 'S10' => 'image_too_large', 'S11' => 'h1_missing', 'S12' => 'h1_multiple', 'S13' => 'heading_skip',
	'S14' => 'thin_content', 'S15' => 'broken_internal_link', 'S16' => 'no_incoming_links', 'S17' => 'rendered_fetch_failed',
];
$open = static function ( $post_id ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT issue_type, object_id FROM ' . SEOHC_Repository::issues_table() . ' WHERE post_id = %d AND resolved_at IS NULL', $post_id ) );
};
$seen = [];
foreach ( $expect as $sid => $type ) {
	$rows  = isset( $map[ $sid ] ) ? $open( $map[ $sid ] ) : [];
	$types = wp_list_pluck( $rows, 'issue_type' );
	$seen  = array_merge( $seen, $types );
	$ok( "{$sid}: verwacht {$type}", in_array( $type, $types, true ), 'gevonden: ' . implode( ',', $types ) );
}
$rows = $open( $map['S09'] );
$a01  = 0;
foreach ( get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_seohc_dummy', 'meta_value' => 'A01' ] ) as $a ) { $a01 = $a->ID; }
$obj = 0;
foreach ( $rows as $r ) { if ( 'image_missing_alt' === $r->issue_type ) { $obj = (int) $r->object_id; } }
$ok( 'S09 image_missing_alt object_id = A01', $a01 > 0 && $obj === $a01, "object_id={$obj} A01={$a01}" );

$s00 = $open( $map['S00'] );
$ok( 'S00 heeft 0 open issues', 0 === count( $s00 ), implode( ',', wp_list_pluck( $s00, 'issue_type' ) ) );

$all = array_keys( SEOHC_Issue_Types::all() );
$missing = array_diff( $all, array_unique( $seen ) );
$ok( 'alle ' . count( $all ) . ' typen gedekt', 15 === count( $all ) && ! $missing, 'ontbreekt: ' . implode( ',', $missing ) );

echo 'summary=' . wp_json_encode( SEOHC_Repository::summary() ) . "\n";
echo ( $fail ? "RESULT: {$fail} FAIL\n" : "RESULT: ALL PASS\n" );
exit( $fail ? 1 : 0 );
