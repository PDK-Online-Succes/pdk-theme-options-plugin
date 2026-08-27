<?php
/**
 * Zelftest voor de integriteitscontrole en de wp-config-rechten.
 * Draaien met:
 *   php includes/test-file-integrity.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ );
define( 'PDK_CAP_EDIT_CODE', 'pdk_edit_custom_code' );
define( 'PDK_STORAGE_DIR', sys_get_temp_dir() . '/pdk-test-' . getmypid() . '/' );
define( 'PDK_STORAGE_URL', 'https://example.test/pdk/' );
define( 'PDK_PLUGIN_VERSION', 'test' );

// wp-config bepaalt de code-editors: gebruiker 7 wel, gebruiker 1 (beheerder) niet.
define( 'PDK_CODE_EDITORS', '7' );

$GLOBALS['options'] = [];

function current_user_can( string $cap ): bool {
	return true;
}
function __( string $text, string $domain = '' ): string {
	return $text;
}
function wp_mkdir_p( string $dir ): bool {
	return is_dir( $dir ) || mkdir( $dir, 0777, true );
}
function add_query_arg( $key, $value, $url ) {
	return $url . '?' . $key . '=' . $value;
}
function add_filter( string $hook, $cb, int $prio = 10, int $args = 1 ): bool {
	return true;
}
function add_action( string $hook, $cb, int $prio = 10, int $args = 1 ): bool {
	return true;
}
function add_shortcode( string $tag, $cb ): void {}
function is_email( string $s ) {
	return str_contains( $s, '@' ) ? $s : false;
}
function get_user_by( string $field, string $value ) {
	return false;
}
function get_option( string $key, $default = false ) {
	return $GLOBALS['options'][ $key ] ?? $default;
}
function update_option( string $key, $value, $autoload = null ): bool {
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function is_multisite(): bool {
	return false;
}
function get_site_option( string $key, $default = false ) {
	return $default;
}

class WP_Error {
	public function __construct( private string $code = '', private string $message = '' ) {}
	public function get_error_code(): string {
		return $this->code;
	}
	public function get_error_message(): string {
		return $this->message;
	}
}

require_once __DIR__ . '/helpers.php';

// Niet assert(): die worden met zend.assertions=-1 wegcompileerd, waardoor deze
// test altijd zou slagen zonder iets te controleren.
$fouten = 0;
function check( $ok, string $naam = '' ): void {
	global $fouten;
	if ( ! $ok ) {
		$fouten++;
		echo '  FOUT ' . ( $naam ?: 'controle' ) . "
";
	}
}

$file = 'custom-functions.php';
$path = PDK_STORAGE_DIR . $file;

// --- Rechten uit wp-config gaan boven alles wat in de database staat --------
check( pdk_map_code_cap( [ PDK_CAP_EDIT_CODE ], PDK_CAP_EDIT_CODE, 7 ) === [ 'exist' ] );
check( pdk_map_code_cap( [ PDK_CAP_EDIT_CODE ], PDK_CAP_EDIT_CODE, 1 ) === [ 'do_not_allow' ] );
// Andere capabilities blijven ongemoeid.
check( pdk_map_code_cap( [ 'manage_options' ], 'manage_options', 1 ) === [ 'manage_options' ] );

// --- Integriteit ------------------------------------------------------------
pdk_maybe_create_storage_file( $file, "<?php\n// origineel\n" );
check( false === pdk_file_is_tampered( $file ), 'vers bestand is niet gemanipuleerd' );

// Opslaan via de editor verlegt de baseline.
check( true === pdk_write_storage_file( $file, "<?php\n// versie 2\n" ) );
check( false === pdk_file_is_tampered( $file ) );

// Een hack schrijft rechtstreeks naar het bestand.
file_put_contents( $path, "<?php\n// versie 2\n// hier schrijft de aanvaller zijn backdoor\n" );
check( true === pdk_file_is_tampered( $file ), 'wijziging buiten de editor wordt gedetecteerd' );
check( pdk_tampered_files() === [ $file ] );

// Herstel uit de back-up maakt het weer schoon.
check( true === pdk_write_storage_file( $file, file_get_contents( $path . '.bak' ) ) );
check( false === pdk_file_is_tampered( $file ) );
check( str_contains( file_get_contents( $path ), 'origineel' ), 'back-up bevat de vorige versie' );

// Syntaxfouten worden nog steeds geweigerd (en laten het bestand met rust).
check( pdk_write_storage_file( $file, '<?php if ( {' ) instanceof WP_Error );

// Zonder baseline (bestaande installatie) geldt alles als vertrouwd, tot seeding.
$GLOBALS['options'] = [];
check( false === pdk_file_is_tampered( $file ) );
pdk_seed_file_hashes();
check( false === pdk_file_is_tampered( $file ) );
file_put_contents( $path, '<?php // gehackt' );
check( true === pdk_file_is_tampered( $file ) );

// .htaccess blokkeert zowel .php als de .bak-kopieën.
check( str_contains( file_get_contents( PDK_STORAGE_DIR . '.htaccess' ), '(php|bak)' ) );

// Opruimen.
foreach ( array_diff( scandir( PDK_STORAGE_DIR ), [ '.', '..' ] ) as $f ) {
	unlink( PDK_STORAGE_DIR . $f );
}
rmdir( PDK_STORAGE_DIR );

echo $fouten ? "{$fouten} controle(s) gefaald\n" : "OK — alle controles geslaagd\n";
exit( $fouten ? 1 : 0 );
