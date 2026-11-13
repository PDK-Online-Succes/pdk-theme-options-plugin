<?php
/**
 * Seed (brief §5): dummy-inhoud voor SEO Health Check op dev.local.
 *
 *   wpl --user=1 eval-file tests/live/seohc-seed.php            seed (idempotent)
 *   wpl --user=1 eval-file tests/live/seohc-seed.php cleanup    ruimt alles op (posts, bijlage A01, bestanden)
 *
 * Alles heeft de meta `_seohc_dummy = <ID>` en de titelprefix "SEOHC dummy". De testinstellingen
 * (theme_outputs_h1=0, max_image_kb=300, max_title_length=100) worden bij seed gezet; de vorige waarden
 * staan in de optie `seohc_dummy_prev_settings` en cleanup zet ze terug.
 */

if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

$mode = isset( $args[0] ) ? $args[0] : '';
$mode = 'cleanup' === $mode ? 'cleanup' : 'seed';

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$up      = wp_get_upload_dir();
$dir     = trailingslashit( $up['basedir'] ) . 'seohc-dummy';
$url_dir = trailingslashit( $up['baseurl'] ) . 'seohc-dummy';

// --- opruimen ---------------------------------------------------------------
$old = get_posts( [ 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_seohc_dummy' ] );
// Wezen: kopieën met de dummy-titelprefix maar zonder `_seohc_dummy` (achtergelaten door losse proefscripts). Ze geven
// anders extra title_duplicate-issues. Alleen post/page, en alleen als de titel met "SEOHC dummy " begint.
global $wpdb;
$old = array_merge( $old, array_map( 'intval', $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_seohc_dummy' WHERE p.post_type IN ('post','page') AND p.post_title LIKE 'SEOHC dummy %' AND m.meta_id IS NULL" ) ) );
foreach ( $old as $id ) {
	if ( 'attachment' === get_post_type( $id ) ) { wp_delete_attachment( $id, true ); } else { wp_delete_post( $id, true ); }
}
foreach ( (array) glob( $dir . '/*' ) as $f ) { wp_delete_file( $f ); }
if ( is_dir( $dir ) ) { rmdir( $dir ); }
if ( class_exists( 'SEOHC_Repository' ) ) {
	foreach ( $old as $id ) { SEOHC_Repository::delete_post( $id ); }
}

if ( 'cleanup' === $mode ) {
	$prev = get_option( 'seohc_dummy_prev_settings', null );
	if ( is_array( $prev ) ) {
		$s = (array) get_option( 'seohc_settings', [] );
		update_option( 'seohc_settings', array_merge( $s, $prev ) );
		delete_option( 'seohc_dummy_prev_settings' );
	}
	echo 'cleanup klaar: ' . count( $old ) . " dummy-items verwijderd\n";
	return;
}

// --- instellingen -----------------------------------------------------------
$settings = (array) get_option( 'seohc_settings', [] );
if ( false === get_option( 'seohc_dummy_prev_settings', false ) ) {
	$cur = wp_parse_args( $settings, class_exists( 'SEOHC_Settings' ) ? SEOHC_Settings::defaults() : [] );
	add_option( 'seohc_dummy_prev_settings', array_intersect_key( $cur, array_flip( [ 'theme_outputs_h1', 'max_image_kb', 'max_title_length' ] ) ), '', false );
}
update_option( 'seohc_settings', array_merge( $settings, [ 'theme_outputs_h1' => 0, 'max_image_kb' => 300, 'max_title_length' => 100 ] ) );

// --- bestanden --------------------------------------------------------------
wp_mkdir_p( $dir );
file_put_contents( $dir . '/groot.jpg', random_bytes( 400 * 1024 ) );
// Echte JPEG van 1x1 px (GD).
$gd = imagecreatetruecolor( 1, 1 );
imagejpeg( $gd, $dir . '/klein.jpg' );
$att = wp_insert_attachment( [ 'post_mime_type' => 'image/jpeg', 'post_title' => 'SEOHC dummy klein', 'post_status' => 'inherit' ], $dir . '/klein.jpg' );
update_post_meta( $att, '_seohc_dummy', 'A01' );
update_post_meta( $att, '_wp_attached_file', 'seohc-dummy/klein.jpg' );
delete_post_meta( $att, '_wp_attachment_image_alt' );

// --- posts ------------------------------------------------------------------
$bs     = chr( 92 );
$marker = '<!-- wp:paragraph {"placeholder":"a' . $bs . 'u0026b"} --><p>C:' . $bs . 'pad</p><!-- /wp:paragraph -->';

$words = static function ( $n, $seed = 'woord' ) {
	$out = [];
	for ( $i = 1; $i <= $n; $i++ ) { $out[] = $seed . $i; }
	return implode( ' ', $out );
};
$desc = static function ( $len, $base ) { return substr( str_pad( $base . ' ', $len, 'abcdefghij ' ), 0, $len - 1 ) . 'x'; };

$ids  = [];
$make = static function ( $sid, array $a, $description = true ) use ( &$ids ) {
	$postarr = array_merge( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'SEOHC dummy ' . $sid, 'post_content' => '' ], $a );
	$id      = wp_insert_post( wp_slash( $postarr ), true );
	if ( is_wp_error( $id ) ) { fwrite( STDERR, "seed {$sid}: " . $id->get_error_message() . "\n" ); exit( 1 ); }
	update_post_meta( $id, '_seohc_dummy', $sid );
	if ( true === $description ) { $description = 'Unieke beschrijving van dummy ' . $sid . ' voor de SEO Health Check test.'; }
	if ( $description ) { update_post_meta( $id, '_seohc_dummy_description', $description ); }
	$ids[ $sid ] = $id;
	return $id;
};

$s00 = $make( 'S00', [ 'post_title' => 'SEOHC dummy S00 in orde', 'post_content' => '<h1>Hoofdkop in orde</h1><p>' . $words( 120, 'alfa' ) . '</p><h2>Eerste deel</h2><p>' . $words( 120, 'beta' ) . '</p><h3>Detail</h3><p>' . $words( 120, 'gamma' ) . '</p>' ] );
$make( 'S01', [ 'post_title' => '', 'post_content' => '<h1>S01</h1><p>' . $words( 320, 'delta' ) . '</p>' ] );
$make( 'S02', [ 'post_title' => substr( 'SEOHC dummy S02 ' . str_repeat( 'lang ', 40 ), 0, 150 ), 'post_content' => '<h1>S02</h1><p>' . $words( 320, 'eps' ) . '</p>' ] );
$make( 'S03', [ 'post_title' => 'SEOHC dummy dubbele titel', 'post_content' => '<h1>S03</h1><p>' . $words( 320, 'zeta' ) . '</p>' ] );
$make( 'S04', [ 'post_title' => 'SEOHC dummy dubbele titel', 'post_content' => '<h1>S04</h1><p>' . $words( 320, 'eta' ) . '</p>' ] );
$make( 'S05', [ 'post_content' => '<h1>S05</h1><p>' . $words( 320, 'theta' ) . '</p>' ], false );
$make( 'S06', [ 'post_content' => '<h1>S06</h1><p>' . $words( 320, 'iota' ) . '</p>' ], $desc( 200, 'Te lange beschrijving S06' ) );
$dup = $desc( 120, 'Gelijke beschrijving voor S07 en S08' );
$make( 'S07', [ 'post_content' => '<h1>S07</h1><p>' . $words( 320, 'kappa' ) . '</p>' ], $dup );
$make( 'S08', [ 'post_content' => '<h1>S08</h1><p>' . $words( 320, 'lambda' ) . '</p>' ], $dup );
$make( 'S09', [ 'post_content' => '<h1>S09</h1><p>' . $words( 320, 'mu' ) . '</p><img src="' . $url_dir . '/klein.jpg">' . $marker ] );
$make( 'S10', [ 'post_content' => '<h1>S10</h1><p>' . $words( 320, 'nu' ) . '</p><img src="' . $url_dir . '/groot.jpg" alt="Groot">' ] );
$make( 'S11', [ 'post_content' => '<h2>Alleen een h2</h2><p>' . $words( 320, 'xi' ) . '</p>' ] );
$make( 'S12', [ 'post_content' => '<h1>Eerste h1 S12</h1><p>' . $words( 320, 'omi' ) . '</p><h1>Tweede h1 S12</h1><p><a href="' . get_permalink( $s00 ) . '">naar S00</a></p>' ] );
$make( 'S13', [ 'post_content' => '<h1>Intro</h1><h2>Deel</h2><h4>Detail</h4><p>' . $words( 320, 'pi' ) . '</p>' ] );
$make( 'S14', [ 'post_content' => '<h1>S14</h1><p>' . $words( 20, 'rho' ) . '</p>' ] );
$make( 'S15', [ 'post_content' => '<h1>S15</h1><p>' . $words( 320, 'sigma' ) . '</p><p><a href="' . $url_dir . '/bestaat-niet.pdf">kapotte bijlage</a></p>' ] );
$make( 'S16', [ 'post_type' => 'page', 'post_content' => '<h1>S16</h1><p>' . $words( 320, 'tau' ) . '</p>' ] );
$make( 'S17', [ 'post_type' => 'page', 'post_name' => 'seohc-dummy-s17', 'post_content' => '' ] );
$make( 'S18', [ 'post_content' => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Blok intro</h1><!-- /wp:heading -->' . '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Blok detail</h4><!-- /wp:heading -->' . '<!-- wp:html --><h3>In html-blok</h3><!-- /wp:html -->' . $marker ] );
$make( 'S18b', [ 'post_content' => '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Blok detail</h4><!-- /wp:heading -->' . $marker ] );

echo 'seed klaar: A01=' . $att . ' ' . wp_json_encode( $ids ) . "\n";
