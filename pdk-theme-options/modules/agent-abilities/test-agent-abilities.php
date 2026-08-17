<?php
/**
 * Zelftest voor de agent-abilities callbacks en de PHP-syntaxcontrole.
 * Draaien met:
 *   php modules/agent-abilities/test-agent-abilities.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ );
define( 'PDK_CAP_EDIT_CODE', 'pdk_edit_custom_code' );
define( 'PDK_STORAGE_DIR', sys_get_temp_dir() . '/pdk-test-' . getmypid() . '/' );
define( 'PDK_STORAGE_URL', 'https://example.test/pdk/' );
define( 'PDK_PLUGIN_VERSION', 'test' );

$GLOBALS['can_edit'] = true;

function current_user_can( string $cap ): bool {
	return (bool) $GLOBALS['can_edit'];
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
function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

class WP_Error {
	private string $code;
	private string $message;

	public function __construct( string $code = '', string $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code(): string {
		return $this->code;
	}

	public function get_error_message(): string {
		return $this->message;
	}
}

class PDK_Loader {
	public function add_action( $hook, $obj, $method, $prio = 10, $args = 1 ): void {}
}

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/class-pdk-agent-abilities.php';

$m = new PDK_Agent_Abilities( new PDK_Loader() );

// Onbekende sleutel -> WP_Error, geen bestandsoperatie.
assert( $m->read( [ 'file' => 'ini' ] )->get_error_code() === 'pdk_unknown_file' );
assert( $m->write( [ 'file' => '../wp-config', 'content' => 'x' ] )->get_error_code() === 'pdk_unknown_file' );

// Nog niet bestaand bestand leest als lege string, niet als fout.
assert( $m->read( [ 'file' => 'css' ] ) === '' );

// Schrijven en teruglezen.
assert( $m->write( [ 'file' => 'css', 'content' => 'body{color:red}' ] ) === [ 'saved' => true, 'bytes' => 15 ] );
assert( $m->read( [ 'file' => 'css' ] ) === 'body{color:red}' );

// Geldige PHP mag; kapotte PHP wordt geweigerd en overschrijft niets.
assert( $m->write( [ 'file' => 'php', 'content' => "<?php\nfunction pdk_ok() { return 1; }\n" ] )['saved'] === true );
$broken = $m->write( [ 'file' => 'php', 'content' => "<?php\nfunction pdk_ok( { }\n" ] );
assert( $broken->get_error_code() === 'php_parse_error' );
assert( $m->read( [ 'file' => 'php' ] ) === "<?php\nfunction pdk_ok() { return 1; }\n" );

// Zonder capability: lezen mag (permission_callback regelt dat), schrijven niet.
$GLOBALS['can_edit'] = false;
assert( $m->write( [ 'file' => 'js', 'content' => 'alert(1)' ] )->get_error_code() === 'forbidden' );

// Opruimen — ook .htaccess, die pdk_ensure_storage_dir() aanmaakt.
foreach ( array_diff( scandir( PDK_STORAGE_DIR ), [ '.', '..' ] ) as $f ) {
	unlink( PDK_STORAGE_DIR . $f );
}
rmdir( PDK_STORAGE_DIR );

echo "OK\n";
