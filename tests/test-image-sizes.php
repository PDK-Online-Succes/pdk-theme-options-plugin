<?php
/**
 * Zelftest voor de afbeeldingsmaten-module: PDK_Image_Sizes en PDK_Image_Sizes_Batch.
 * Draaien met:
 *   php tests/test-image-sizes.php
 *
 * Elke controle draagt het AC-ID uit docs/afbeeldingsmaten-brief.md in het label.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

// Pad naar de plugin; de tests staan bewust buiten de map die uitgeleverd wordt.
$pdk = dirname( __DIR__ ) . '/pdk-theme-options';

define( 'ABSPATH', sys_get_temp_dir() . '/pdk-image-sizes-test-' . getmypid() . '/' );

@mkdir( ABSPATH . 'wp-admin/includes', 0777, true );
file_put_contents( ABSPATH . 'wp-admin/includes/image.php', "<?php // stub\n" );

// -----------------------------------------------------------------------
// WordPress-stubs
// -----------------------------------------------------------------------

$GLOBALS['settings']           = [];
$GLOBALS['registered']         = [];
$GLOBALS['added_sizes']        = [];
$GLOBALS['options']            = [];
$GLOBALS['files']              = [];
$GLOBALS['gen_meta']           = [];
$GLOBALS['updated_meta']       = [];
$GLOBALS['attachment_meta']    = [];
$GLOBALS['attachment_counts']  = 0;
$GLOBALS['attachment_ids_fixture'] = [];
$_POST                          = [];

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function __( $text, $domain = null ) {
	return $text;
}
function wp_unslash( $value ) {
	return $value;
}
function current_user_can( $cap ) {
	return true;
}
function check_ajax_referer( $action, $arg = false ) {
	return true;
}
function add_image_size( $key, $width, $height, $crop ) {
	$GLOBALS['added_sizes'][ $key ] = [ 'width' => $width, 'height' => $height, 'crop' => $crop ];
}
function wp_get_registered_image_subsizes() {
	return $GLOBALS['registered'];
}
function get_option( $key, $default = false ) {
	return $GLOBALS['options'][ $key ] ?? $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['options'][ $key ] );
	return true;
}
function get_attached_file( $id ) {
	return $GLOBALS['files'][ $id ] ?? false;
}
function wp_generate_attachment_metadata( $id, $file ) {
	return $GLOBALS['gen_meta'][ $id ] ?? [];
}
function wp_update_attachment_metadata( $id, $meta ) {
	$GLOBALS['updated_meta'][ $id ] = $meta;
	return true;
}
function wp_get_attachment_metadata( $id ) {
	return $GLOBALS['attachment_meta'][ $id ] ?? [];
}
function wp_count_attachments() {
	$counts = new stdClass();
	foreach ( PDK_Image_Sizes_Batch::IMAGE_MIME_TYPES as $mime ) {
		$counts->$mime = 0;
	}
	$counts->{'image/jpeg'} = $GLOBALS['attachment_counts'];
	return $counts;
}

/** Draagt het json_error/json_success-antwoord over zonder het proces te beëindigen. */
class PDK_Test_Json_Halt extends \Exception {
	public $success;
	public $data;
	public $code;
	public function __construct( bool $success, $data, $code = null ) {
		$this->success = $success;
		$this->data    = $data;
		$this->code    = $code;
		parent::__construct( 'json-halt' );
	}
}
function wp_send_json_error( $data, $code = null ) {
	throw new PDK_Test_Json_Halt( false, $data, $code );
}
function wp_send_json_success( $data ) {
	throw new PDK_Test_Json_Halt( true, $data );
}

class WP_Query {
	public $posts = [];
	public function __construct( array $args = [] ) {
		$all          = $GLOBALS['attachment_ids_fixture'];
		$offset       = (int) ( $args['offset'] ?? 0 );
		$size         = (int) ( $args['posts_per_page'] ?? count( $all ) );
		$this->posts  = array_slice( $all, $offset, $size );
	}
}

class PDK_Loader {
	public function add_action( string $hook, $obj, string $method, int $prio = 10, int $args = 1 ): void {}
	public function add_filter( string $hook, $obj, string $method, int $prio = 10, int $args = 1 ): void {}
}

class PDK_Settings {
	public static function get( string $module = '', string $key = '' ) {
		return $GLOBALS['settings'][ $module ][ $key ] ?? null;
	}
}

require_once $pdk . '/modules/image-sizes/class-pdk-image-sizes.php';

$pass = 0;
$fail = 0;
function check( $label, $condition, $extra = '' ) {
	global $pass, $fail;
	if ( $condition ) {
		++$pass;
		return;
	}
	++$fail;
	echo "FAIL: {$label}" . ( $extra ? "\n      {$extra}" : '' ) . "\n";
}

// -------------------------------------------------------------------------
// AC-005 / AC-028 — blocklist semantiek
// -------------------------------------------------------------------------

$GLOBALS['settings'] = [];
check( 'AC-005: nog nooit vermelde sleutel is actief', PDK_Image_Sizes::is_enabled( 'nog_nooit_gezien' ) === true );

$GLOBALS['settings'] = [ 'image_sizes' => [ 'disabled' => [] ] ];
check( 'AC-005: lege blocklist laat pas geregistreerde maat actief', PDK_Image_Sizes::is_enabled( 'gloednieuwe_maat' ) === true );

$GLOBALS['settings'] = [ 'image_sizes' => [ 'disabled' => [ 'pdk_banner' ] ] ];
check( 'AC-028: uitgezette maat blijft uit, ook niet (nog) geregistreerd', ! PDK_Image_Sizes::is_enabled( 'pdk_banner' ) );

$sizes_bij_upload = [
	'medium'     => [ 'width' => 300, 'height' => 300, 'crop' => false ],
	'pdk_banner' => [ 'width' => 1200, 'height' => 400, 'crop' => true ],
];
$module = new PDK_Image_Sizes( new PDK_Loader() );
$result = $module->filter_generation( $sizes_bij_upload );
check(
	'AC-028: opnieuw geregistreerde uitgezette maat blijft uit de generatie',
	! array_key_exists( 'pdk_banner', $result ) && array_key_exists( 'medium', $result )
);

// -------------------------------------------------------------------------
// AC-007 — sanitize_disabled_list() haalt thumbnail er altijd uit
// -------------------------------------------------------------------------

$schoon = PDK_Image_Sizes::sanitize_disabled_list( [ 'thumbnail', 'medium', 'medium' ] );
check( 'AC-007: thumbnail verdwijnt uit een geposte blocklist', ! in_array( 'thumbnail', $schoon, true ) );
check( 'AC-007: rest van de lijst blijft, zonder duplicaten', $schoon === [ 'medium' ] );

$geprepareerd = PDK_Image_Sizes::sanitize_disabled_list( [ ' Thumbnail ', 'THUMBNAIL', 'large' ] );
check(
	'AC-007: ook een geprepareerde/rare schrijfwijze van thumbnail wordt eruit gehaald',
	! in_array( 'thumbnail', $geprepareerd, true )
);
check( 'AC-007: large blijft staan naast de verwijderde thumbnail-variant', in_array( 'large', $geprepareerd, true ) );

// -------------------------------------------------------------------------
// AC-009 — colliding_key()
// -------------------------------------------------------------------------

$GLOBALS['registered'] = [
	'thumbnail' => [ 'width' => 150, 'height' => 150, 'crop' => true ],
	'large'     => [ 'width' => 1024, 'height' => 1024, 'crop' => false ],
];
$GLOBALS['settings'] = [ 'image_sizes' => [ 'custom' => [ 'pdk_banner' => [ 'width' => 1200, 'height' => 400, 'crop' => true ] ] ] ];

check(
	'AC-009: botsing met een core-maatnaam wordt geweigerd',
	PDK_Image_Sizes::colliding_key( 'large', 'pdk_large' ) === 'large'
);
check(
	'AC-009: botsing met een bestaande eigen maat wordt geweigerd',
	PDK_Image_Sizes::colliding_key( 'banner', 'pdk_banner' ) === 'pdk_banner'
);
check(
	'AC-009: een vrije naam geeft geen vals alarm',
	PDK_Image_Sizes::colliding_key( 'vrije-naam', 'pdk_vrije-naam' ) === ''
);

// -------------------------------------------------------------------------
// AC-010 — has_valid_dimensions()
// -------------------------------------------------------------------------

check( 'AC-010: 0x0 is ongeldig', PDK_Image_Sizes::has_valid_dimensions( 0, 0 ) === false );
check(
	'AC-010: 0x600 is geldig (proportioneel, net als medium_large)',
	PDK_Image_Sizes::has_valid_dimensions( 0, 600 ) === true
);
check( 'AC-010: 800x0 is ook geldig (proportioneel op breedte)', PDK_Image_Sizes::has_valid_dimensions( 800, 0 ) === true );

// -------------------------------------------------------------------------
// AC-012 — is_custom()
// -------------------------------------------------------------------------

check( 'AC-012: pdk_-sleutel telt als eigen maat', PDK_Image_Sizes::is_custom( 'pdk_banner' ) === true );
check( 'AC-012: core-sleutel telt nooit als eigen maat', PDK_Image_Sizes::is_custom( 'large' ) === false );
check( 'AC-012: thumbnail telt nooit als eigen maat', PDK_Image_Sizes::is_custom( 'thumbnail' ) === false );

// -------------------------------------------------------------------------
// AC-017 — filter_generation() laat uitgezette maten weg, rest blijft staan
// -------------------------------------------------------------------------

$GLOBALS['settings'] = [ 'image_sizes' => [ 'disabled' => [ 'medium_large' ] ] ];
$sizes_upload = [
	'thumbnail'    => [ 'width' => 150, 'height' => 150, 'crop' => true ],
	'medium'       => [ 'width' => 300, 'height' => 300, 'crop' => false ],
	'medium_large' => [ 'width' => 768, 'height' => 0, 'crop' => false ],
	'large'        => [ 'width' => 1024, 'height' => 1024, 'crop' => false ],
];
$gefilterd = ( new PDK_Image_Sizes( new PDK_Loader() ) )->filter_generation( $sizes_upload );
check( 'AC-017: uitgezette medium_large verdwijnt uit de generatielijst', ! isset( $gefilterd['medium_large'] ) );
check(
	'AC-017: de rest van de maten blijft gewoon staan',
	isset( $gefilterd['thumbnail'] ) && isset( $gefilterd['medium'] ) && isset( $gefilterd['large'] )
);
check( 'AC-017: geen bestand voor de uitgezette maat aangemaakt door deze filter', count( $gefilterd ) === 3 );

// -------------------------------------------------------------------------
// AC-027 — filter_size_choices() is additief, regressietest
// -------------------------------------------------------------------------

$GLOBALS['settings'] = [ 'image_sizes' => [ 'custom' => [ 'pdk_banner' => [ 'width' => 1200, 'height' => 400, 'crop' => true ] ] ] ];
$binnenkomend = [
	'medium'              => 'Medium',
	'large'               => 'Large',
	'vreemde_filter_key'  => 'Van een ander filter op lagere prioriteit',
];
$keuzes = ( new PDK_Image_Sizes( new PDK_Loader() ) )->filter_size_choices( $binnenkomend );

check( 'AC-027: eigen maat pdk_banner staat in de keuzelijst', isset( $keuzes['pdk_banner'] ) );
check( 'AC-027: medium blijft ongewijzigd staan', ( $keuzes['medium'] ?? null ) === 'Medium' );
check( 'AC-027: large blijft ongewijzigd staan', ( $keuzes['large'] ?? null ) === 'Large' );
check(
	'AC-027 (regressie): een vreemde sleutel die een ander filter toevoegde, overleeft filter_size_choices()',
	( $keuzes['vreemde_filter_key'] ?? null ) === 'Van een ander filter op lagere prioriteit',
	'Een eerdere versie bouwde de lijst opnieuw op vanaf een vaste set en gooide dit soort keuzes weg.'
);
check( 'AC-027: aantal keuzes is 3 origineel + 1 eigen maat', count( $keuzes ) === 4 );

// -------------------------------------------------------------------------
// AC-026 / AC-031 — batch: modus en gelijktijdige start
// -------------------------------------------------------------------------

$GLOBALS['options']             = [];
$GLOBALS['attachment_counts']   = 5;
$batch = new PDK_Image_Sizes_Batch( new PDK_Loader() );

$_POST = [ 'mode' => 'bogus-modus' ];
try {
	$batch->ajax_start();
	check( 'AC-026: onbekende modus wordt geweigerd', false, 'ajax_start() gaf geen foutrespons' );
} catch ( PDK_Test_Json_Halt $e ) {
	check( 'AC-026: onbekende modus wordt geweigerd (400)', $e->success === false && 400 === $e->code );
}
check( 'AC-026: bij weigering wordt geen run gestart', [] === PDK_Image_Sizes_Batch::current_state() );

$_POST = [ 'mode' => 'missing' ];
$eerste_state = null;
try {
	$batch->ajax_start();
	check( 'AC-031: eerste start slaagt', false, 'verwachtte een json_success-halt' );
} catch ( PDK_Test_Json_Halt $e ) {
	check( 'AC-031: eerste start slaagt (success)', $e->success === true );
	$eerste_state = PDK_Image_Sizes_Batch::current_state();
}

try {
	$batch->ajax_start();
	check( 'AC-031: tweede gelijktijdige start wordt geweigerd', false, 'verwachtte een json_error-halt' );
} catch ( PDK_Test_Json_Halt $e ) {
	check( 'AC-031: tweede gelijktijdige start wordt geweigerd (409)', $e->success === false && 409 === $e->code );
}
check(
	'AC-031: de bestaande status blijft onaangeroerd na de geweigerde tweede start',
	PDK_Image_Sizes_Batch::current_state() === $eerste_state
);

delete_option( PDK_Image_Sizes_Batch::STATE_OPTION );

// -------------------------------------------------------------------------
// AC-029 — een falende bijlage stopt de run niet
// -------------------------------------------------------------------------

$tmpdir = sys_get_temp_dir() . '/pdk-image-sizes-files-' . getmypid();
@mkdir( $tmpdir, 0777, true );
$goed_bestand = $tmpdir . '/goed.jpg';
file_put_contents( $goed_bestand, 'x' );

$GLOBALS['attachment_ids_fixture'] = [ 101, 102, 103 ];
$GLOBALS['files'] = [
	101 => $goed_bestand,
	102 => false, // bronbestand ontbreekt
	103 => $tmpdir . '/niet-bestaand.jpg', // pad gezet, bestand ontbreekt echt niet op schijf
];
$GLOBALS['gen_meta'] = [
	101 => [ 'width' => 800, 'height' => 600, 'sizes' => [ 'medium' => [ 'file' => 'goed-300x300.jpg' ] ] ],
];

$state = [
	'mode'      => 'all',
	'offset'    => 0,
	'total'     => 3,
	'processed' => 0,
	'generated' => 0,
	'skipped'   => 0,
	'errors'    => [],
	'started'   => time(),
];

$state = $batch->run_step( $state );

check( 'AC-029: alle drie de bijlagen zijn verwerkt, de fouten stoppen de run niet', 3 === $state['processed'] );
check( 'AC-029: de geldige bijlage is gegenereerd', 1 === $state['generated'] );
check( 'AC-029: de twee bijlagen zonder leesbaar bronbestand leverden elk een fout op', 2 === count( $state['errors'] ) );
check(
	'AC-029: de fout noemt de bijlage-ID van de ontbrekende bron',
	false !== strpos( $state['errors'][0], '#102' ) || false !== strpos( $state['errors'][1], '#102' )
);
check(
	'AC-029: de fout noemt ook de bijlage-ID van het niet-bestaande pad',
	false !== strpos( implode( '|', $state['errors'] ), '#103' )
);
check( 'AC-029: de run zelf loopt door tot offset === total', 3 === $state['offset'] );

array_map( 'unlink', glob( $tmpdir . '/*' ) ?: [] );
rmdir( $tmpdir );

// -------------------------------------------------------------------------
// Opruimen
// -------------------------------------------------------------------------

unlink( ABSPATH . 'wp-admin/includes/image.php' );
rmdir( ABSPATH . 'wp-admin/includes' );
rmdir( ABSPATH . 'wp-admin' );
rmdir( ABSPATH );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
