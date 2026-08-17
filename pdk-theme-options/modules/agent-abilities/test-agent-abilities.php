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
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}
function esc_url( string $url ): string {
	return $url;
}
function wp_specialchars_decode( string $text ): string {
	return htmlspecialchars_decode( $text, ENT_QUOTES );
}
function current_time( string $format ): string {
	return '2026-08-16'; // een zondag
}
function wp_timezone(): DateTimeZone {
	return new DateTimeZone( 'Europe/Amsterdam' );
}
function is_multisite(): bool {
	return false;
}
function add_shortcode( string $tag, callable $cb ): void {}
function add_action( string $hook, $cb, int $prio = 10, int $args = 1 ): void {}
function get_option( string $key, $default = false ) {
	if ( 'active_plugins' === $key ) {
		return [];
	}

	return $GLOBALS['options'] ?? $default;
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
require_once __DIR__ . '/../../includes/class-pdk-settings.php';
require_once __DIR__ . '/../../modules/site-settings/class-pdk-site-settings.php';
require_once __DIR__ . '/class-pdk-agent-abilities.php';

$GLOBALS['options'] = [
	'site_settings'   => [
		'company_name'     => 'Testbedrijf',
		'company_street'   => 'Kerkstraat',
		'company_number'   => '12',
		'company_zipcode'  => '1234 AB',
		'company_city'     => 'Amsterdam',
		'client_logo'      => 'https://example.test/logo.svg',
		'social_instagram' => 'https://instagram.com/test',
		'social_tiktok'    => '',
		'periods'          => [
			[ 'from' => '2026-12-24', 'to' => '2026-12-26', 'label' => 'Kerst', 'open' => '', 'close' => '' ],
		],
	],
	'agent_abilities' => [ 'enabled' => true ],
];

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

// Site-info: gegevens uit de instellingen, gesplitst in company en social.
$info = $m->site_info();

assert( $info['company']['name'] === 'Testbedrijf' );
assert( $info['company']['address_formatted'] === 'Kerkstraat 12, 1234 AB Amsterdam' );
assert( $info['company']['logo_url'] === 'https://example.test/logo.svg' );

// Alleen social_*-sleutels met een waarde; het prefix is eraf.
assert( array_keys( $info['social'] ) === [ 'instagram' ] );
assert( ! isset( $info['company']['instagram'] ) );

// Zeven weekdagen, zondag standaard gesloten — en 2026-08-16 is een zondag.
assert( count( $info['opening_hours'] ) === 7 );
assert( $info['opening_hours'][0]['day'] === 'Maandag' );
assert( $info['opening_hours'][6]['closed'] === true );
assert( $info['today']['closed'] === true );

// Periodes komen mee; vandaag valt er niet in en de shop is niet gesloten.
assert( $info['periods'][0]['label'] === 'Kerst' );
assert( $info['today']['period'] === null );
assert( $info['today']['shop_closed'] === false );

// Frontend-uitvoer: leeg zolang geen module iets registreerde.
assert( $info['frontend_output'] === [] );

// Zoals PDK_Site_Settings het doet — inclusief markup-beschrijving.
pdk_register_frontend_output( 'openingstijden', [ PDK_Site_Settings::class, 'opening_hours_html' ], 'markup-uitleg' );
$out = $m->site_info()['frontend_output']['openingstijden'];

assert( $out['shortcode'] === '[openingstijden]' );
assert( $out['template_hook'] === "do_action( 'pdk_openingstijden' )" );
assert( $out['markup'] === 'markup-uitleg' );

// html_now is écht gerenderd, niet beschreven: de agent ziet de klassen.
assert( str_contains( $out['html_now'], '<table class="pdk-openingstijden">' ) );
assert( str_contains( $out['html_now'], 'pdk-openingstijden__row--closed' ) );
assert( substr_count( $out['html_now'], '<tr' ) === 7 );

// De agent krijgt te zien welke modules aan staan en welke helpers bestaan.
assert( $info['modules_enabled']['agent_abilities'] === true );
assert( $info['modules_enabled']['custom_css'] === false );
assert( isset( $info['php_helpers']['pdk_company_address()'] ) );

// Zonder capability: lezen mag (permission_callback regelt dat), schrijven niet.
$GLOBALS['can_edit'] = false;
assert( $m->write( [ 'file' => 'js', 'content' => 'alert(1)' ] )->get_error_code() === 'forbidden' );

// Opruimen — ook .htaccess, die pdk_ensure_storage_dir() aanmaakt.
foreach ( array_diff( scandir( PDK_STORAGE_DIR ), [ '.', '..' ] ) as $f ) {
	unlink( PDK_STORAGE_DIR . $f );
}
rmdir( PDK_STORAGE_DIR );

echo "OK\n";
