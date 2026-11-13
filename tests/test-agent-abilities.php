<?php
/**
 * Zelftest voor de agent-abilities callbacks en de PHP-syntaxcontrole.
 * Draaien met:
 *   php modules/agent-abilities/test-agent-abilities.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

// Pad naar de plugin; de tests staan bewust buiten de map die uitgeleverd wordt.
$pdk = dirname( __DIR__ ) . '/pdk-theme-options';

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
function apply_filters( string $hook, $value, ...$args ) {
	return $value;
}
function add_shortcode( string $tag, callable $cb ): void {}
function add_action( string $hook, $cb, int $prio = 10, int $args = 1 ): void {}
function add_filter( string $hook, $cb, int $prio = 10, int $args = 1 ): void {}
function get_option( string $key, $default = false ) {
	if ( 'active_plugins' === $key ) {
		return [];
	}

	if ( 'pdk_file_hashes' === $key ) {
		return $GLOBALS['file_hashes'] ?? $default;
	}

	return $GLOBALS['options'] ?? $default;
}
function update_option( string $key, $value, $autoload = null ): bool {
	$GLOBALS[ 'pdk_file_hashes' === $key ? 'file_hashes' : 'options' ] = $value;
	return true;
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

require_once $pdk . '/includes/helpers.php';
require_once $pdk . '/includes/class-pdk-settings.php';
require_once $pdk . '/modules/site-settings/class-pdk-site-settings.php';
require_once $pdk . '/modules/agent-abilities/class-pdk-agent-abilities.php';

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

// Element Studio-abilities: zonder de module worden ze niet geregistreerd.
$GLOBALS['registered'] = [];
function wp_register_ability( string $name, array $args ): void {
	$GLOBALS['registered'][]            = $name;
	$GLOBALS['registered_args'][ $name ] = $args; // AC-020: de annotaties en meta controleren
}
$m->register_abilities();
assert( ! preg_grep( '#oxygen#', $GLOBALS['registered'] ) );

require_once $pdk . '/modules/oxygen-elements/class-pdk-oxygen-elements.php';

$GLOBALS['registered'] = [];
$m->register_abilities();
assert( count( preg_grep( '#oxygen-element#', $GLOBALS['registered'] ) ) === 3 );

// Een bestaand element, zoals Element Studio het aanmaakt.
$el = PDK_Oxygen_Elements::dir() . 'elements/Kaart';
mkdir( $el, 0777, true );
mkdir( PDK_Oxygen_Elements::dir() . 'macros', 0777, true );
file_put_contents( $el . '/element.php', "<?php\nnamespace PdkElements;\nclass Kaart {}\n" );
file_put_contents( $el . '/html.twig', '<div>kaart</div>' );
file_put_contents( PDK_Oxygen_Elements::dir() . 'macros/knop.twig', '{# knop #}' );

$list = $m->list_oxygen();
assert( $list['elements'] === [ [ 'name' => 'Kaart', 'slug' => 'PdkElements\Kaart', 'files' => [ 'element.php', 'html.twig' ] ] ] );
assert( $list['macros'] === [ 'knop' ] && $list['presets'] === [] );
assert( str_contains( $list['instructions'], 'Element Studio' ) );

// Lezen: via element + file en via path.
assert( $m->read_oxygen( [ 'element' => 'Kaart', 'file' => 'html.twig' ] ) === [ 'path' => 'oxygen-elements/elements/Kaart/html.twig', 'content' => '<div>kaart</div>' ] );
assert( $m->read_oxygen( [ 'path' => 'macros/knop.twig' ] )['content'] === '{# knop #}' );

// Schrijven: geldig, en kapotte PHP wordt geweigerd zonder het bestand te raken.
assert( $m->write_oxygen( [ 'path' => 'elements/Kaart/html.twig', 'content' => '<p>nieuw</p>' ] ) === [ 'saved' => true, 'path' => 'oxygen-elements/elements/Kaart/html.twig', 'bytes' => 12 ] );
assert( file_get_contents( $el . '/html.twig' ) === '<p>nieuw</p>' );
$bad = $m->write_oxygen( [ 'path' => 'elements/Kaart/element.php', 'content' => "<?php\nclass {\n" ] );
assert( $bad->get_error_code() === 'php_parse_error' );
assert( str_contains( file_get_contents( $el . '/element.php' ), 'class Kaart' ) );

// De poort: een element dat nog niet bestaat moet eerst in de builder worden aangemaakt.
foreach ( [
	$m->read_oxygen( [ 'element' => 'Bestaatniet', 'file' => 'element.php' ] ),
	$m->write_oxygen( [ 'path' => 'elements/Bestaatniet/element.php', 'content' => '<?php' ] ),
	$m->write_oxygen( [ 'path' => 'macros/weg.twig', 'content' => 'x' ] ),
] as $gate ) {
	assert( $gate->get_error_code() === 'element_not_found' );
	assert( str_contains( $gate->get_error_message(), 'Element Studio' ) );
}
assert( ! file_exists( PDK_Oxygen_Elements::dir() . 'elements/Bestaatniet' ) );
assert( ! file_exists( PDK_Oxygen_Elements::dir() . 'macros/weg.twig' ) );

// Bestaand element, ontbrekend bestand: niet aanmaken.
assert( $m->write_oxygen( [ 'path' => 'elements/Kaart/default.css', 'content' => 'a{}' ] )->get_error_code() === 'file_not_found' );
assert( ! file_exists( $el . '/default.css' ) );

// Padtruc en onbekend bestand.
assert( $m->read_oxygen( [ 'path' => 'elements/../../custom-functions.php' ] )->get_error_code() === 'pdk_invalid_path' );
assert( $m->read_oxygen( [ 'element' => '..', 'file' => 'element.php' ] )->get_error_code() === 'pdk_invalid_path' );
assert( $m->read_oxygen( [ 'path' => 'elements/Kaart/geheim.txt' ] )->get_error_code() === 'pdk_invalid_path' );

assert( isset( $m->site_info()['oxygen_elements'] ) );

// AC-020 (brief §7.4): de 12 SEO Health Check-abilities. Zonder de klasse of met is_active() false: geen enkele seo-*.
$seo_names = [ 'seo-get-status', 'seo-list-issues', 'seo-list-pages', 'seo-get-scan-history', 'seo-get-launch-checks', 'seo-get-headings', 'seo-start-scan', 'seo-rescan-post', 'seo-update-meta', 'seo-update-image-alt', 'seo-set-heading-level', 'seo-insert-h1' ];
$seo_reg   = static function () use ( $m ): array {
	$GLOBALS['registered'] = $GLOBALS['registered_args'] = [];
	$m->register_abilities();
	return array_values( preg_grep( '#^pdk-theme-options/seo-#', $GLOBALS['registered'] ) );
};
assert( ! class_exists( 'PDK_SEO_Health_Check', false ) );
assert( $seo_reg() === [] ); // AC-020: geen klasse -> geen seo-abilities

require_once $pdk . '/modules/seo-health-check/class-pdk-seo-health-check.php';
require_once $pdk . '/modules/seo-health-check/includes/class-seohc-issue-types.php';
require_once $pdk . '/modules/seo-health-check/includes/class-seohc-repository.php';
assert( PDK_SEO_Health_Check::is_active() === false );
assert( $seo_reg() === [] ); // AC-020: klasse aanwezig maar is_active() false -> geen seo-abilities

( new ReflectionClass( 'PDK_SEO_Health_Check' ) )->setStaticPropertyValue( 'booted', true );
assert( PDK_SEO_Health_Check::is_active() === true );
$seo = $seo_reg();
$exp = array_map( static fn( $n ) => 'pdk-theme-options/' . $n, $seo_names );
assert( count( $seo ) === 12 ); // precies 12 seo-namen
assert( array_diff( $exp, $seo ) === [] && array_diff( $seo, $exp ) === [] );
$read_only = [ 'seo-get-status', 'seo-list-issues', 'seo-list-pages', 'seo-get-scan-history', 'seo-get-launch-checks', 'seo-get-headings' ];
$destruct  = [ 'seo-update-meta', 'seo-update-image-alt', 'seo-set-heading-level', 'seo-insert-h1' ];
foreach ( $seo_names as $n ) {
	$a = $GLOBALS['registered_args'][ 'pdk-theme-options/' . $n ];
	assert( $a['category'] === 'pdk-theme-options', "category {$n}" );
	assert( $a['meta']['show_in_rest'] === true, "show_in_rest {$n}" );
	assert( $a['meta']['mcp']['public'] === true, "mcp.public {$n}" );
	assert( $a['meta']['annotations']['readonly'] === in_array( $n, $read_only, true ), "readonly {$n}" );
	assert( $a['meta']['annotations']['destructive'] === in_array( $n, $destruct, true ), "destructive {$n}" );
	assert( $a['input_schema']['additionalProperties'] === false, "additionalProperties {$n}" );
	assert( is_callable( $a['permission_callback'] ) && is_callable( $a['execute_callback'] ), "callbacks {$n}" );
}
assert( count( array_filter( $read_only, static fn( $n ) => $GLOBALS['registered_args'][ 'pdk-theme-options/' . $n ]['meta']['annotations']['readonly'] ) ) === 6 );
// Schema-grenzen uit §7.2.
$sc = static fn( string $n ): array => $GLOBALS['registered_args'][ 'pdk-theme-options/' . $n ]['input_schema'];
assert( $sc( 'seo-list-issues' )['properties']['per_page']['maximum'] === 100 && $sc( 'seo-list-issues' )['properties']['page']['minimum'] === 1 );
assert( $sc( 'seo-set-heading-level' )['properties']['level']['maximum'] === 6 && $sc( 'seo-set-heading-level' )['properties']['index']['minimum'] === 0 );
assert( $sc( 'seo-update-meta' )['properties']['value']['maxLength'] === 500 && $sc( 'seo-update-image-alt' )['properties']['value']['maxLength'] === 125 );
assert( $sc( 'seo-insert-h1' )['properties']['text']['maxLength'] === 200 && $sc( 'seo-insert-h1' )['properties']['text']['minLength'] === 1 );
// Het zijn dezelfde 6 gewone abilities + SEO: is_active() weer uit -> weer geen seo-abilities.
( new ReflectionClass( 'PDK_SEO_Health_Check' ) )->setStaticPropertyValue( 'booted', false );
assert( $seo_reg() === [] );
echo "AC-020 OK\n";

// Zonder capability: lezen mag (permission_callback regelt dat), schrijven niet.
$GLOBALS['can_edit'] = false;
assert( $m->write( [ 'file' => 'js', 'content' => 'alert(1)' ] )->get_error_code() === 'forbidden' );

// Opruimen — ook .htaccess, die pdk_ensure_storage_dir() aanmaakt, en de Element Studio-mappen.
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( PDK_STORAGE_DIR, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $f ) {
	$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
}
rmdir( PDK_STORAGE_DIR );

echo "OK\n";
