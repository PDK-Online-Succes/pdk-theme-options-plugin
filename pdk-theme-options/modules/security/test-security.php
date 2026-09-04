<?php
/**
 * Zelftest voor de security-module.
 * Draaien met:
 *   php modules/security/test-security.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );

$mu_dir = sys_get_temp_dir() . '/pdk-mu-test-' . getmypid();
mkdir( $mu_dir, 0777, true );
define( 'WPMU_PLUGIN_DIR', $mu_dir );

$GLOBALS['options']     = [];
$GLOBALS['transients']  = [];
$GLOBALS['deactivated'] = [];
$GLOBALS['mails']       = [];
$GLOBALS['hooks']       = [];
$GLOBALS['did']         = [ 'muplugins_loaded' => 1 ]; // Standaard: reguliere plugin-modus.

function trailingslashit( string $pad ): string {
	return rtrim( $pad, '/\\' ) . '/';
}
function add_action( string $hook, $cb, int $prio = 10, int $args = 1 ): bool {
	$GLOBALS['hooks'][ $hook ][] = $cb;
	return true;
}
function add_filter( string $hook, $cb, int $prio = 10, int $args = 1 ): bool {
	$GLOBALS['hooks'][ $hook ][] = $cb;
	return true;
}
function remove_action( string $hook, $cb, int $prio = 10 ): bool {
	$GLOBALS['removed'][] = $hook . ':' . $cb;
	return true;
}
function did_action( string $hook ): int {
	return (int) ( $GLOBALS['did'][ $hook ] ?? 0 );
}
function get_option( string $key, $default = false ) {
	return $GLOBALS['options'][ $key ] ?? $default;
}
function update_option( string $key, $value, $autoload = null ): bool {
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function delete_option( string $key ): bool {
	unset( $GLOBALS['options'][ $key ] );
	return true;
}
function get_transient( string $key ) {
	return $GLOBALS['transients'][ $key ] ?? false;
}
function set_transient( string $key, $value, int $ttl = 0 ): bool {
	$GLOBALS['transients'][ $key ] = $value;
	return true;
}
function wp_delete_file( string $pad ): void {
	unlink( $pad );
}
function deactivate_plugins( array $plugins ): void {
	$GLOBALS['deactivated'] = $plugins;
}
function wp_mail( string $to, string $subject, string $body ): bool {
	$GLOBALS['mails'][] = compact( 'to', 'subject', 'body' );
	return true;
}
function get_bloginfo( string $veld ): string {
	return 'Testsite';
}
function __( string $text, string $domain = '' ): string {
	return $text;
}
function esc_html( string $text ): string {
	return $text;
}
function esc_html__( string $text, string $domain = '' ): string {
	return $text;
}

function is_multisite(): bool {
	return false;
}
function current_user_can( string $cap ): bool {
	return true;
}
function is_user_logged_in(): bool {
	return (bool) ( $GLOBALS['logged_in'] ?? false );
}
function sanitize_text_field( string $waarde ): string {
	return trim( $waarde );
}
function wp_unslash( $waarde ) {
	return $waarde;
}
function is_wp_error( $ding ): bool {
	return $ding instanceof WP_Error;
}

class WP_Error {
	public function __construct( public string $code = '', public string $message = '', public array $data = [] ) {}
}

class PDK_Loader {}

/** Alleen wat de security-module gebruikt. */
class PDK_Settings {

	/** Spiegelt de 'security'-defaults uit class-pdk-settings.php. */
	private const DEFAULTS = [
		'required_plugins'    => [],
		'xmlrpc_disable'      => true,
		'rest_require_login'  => true,
		'rest_protect_routes' => [ '/wp/v2/' ],
		'rest_allow_ips'      => [],
	];

	public static function get( string $module = '', string $key = '' ) {
		return $GLOBALS['settings'][ $module ][ $key ] ?? null;
	}

	public static function get_with_default( string $module, string $key ) {
		return $GLOBALS['settings'][ $module ][ $key ] ?? self::DEFAULTS[ $key ] ?? null;
	}
}

require_once __DIR__ . '/class-pdk-security.php';

/** Blokkeert niet echt, maar gooit — zo is de firewall testbaar. */
class PDK_Security_Test extends PDK_Security {
	protected function forbid( string $reden ): void {
		throw new RuntimeException( $reden );
	}

	/** Draait de firewall los van de constructor. */
	public function firewall(): void {
		$this->block_suspicious_headers();
	}
}

$fouten = 0;
function check( string $naam, bool $ok ): void {
	global $fouten;
	echo ( $ok ? "  ok   " : "  FOUT " ) . $naam . "\n";
	if ( ! $ok ) {
		$fouten++;
	}
}

/** @return string De blokkadereden, of '' als het request doorgelaten wordt. */
function firewall_op( array $server ): string {
	$_SERVER = $server;
	try {
		( new PDK_Security_Test( new PDK_Loader() ) )->firewall();
		return '';
	} catch ( RuntimeException $e ) {
		return $e->getMessage();
	}
}

echo "Header-firewall\n";

check(
	'gewone headers komen erdoor',
	'' === firewall_op(
		[
			'HTTP_HOST'            => 'example.test',
			'HTTP_USER_AGENT'      => 'Mozilla/5.0',
			'HTTP_ACCEPT_LANGUAGE' => 'nl-NL,nl;q=0.9',
			'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
			'REQUEST_METHOD'       => 'GET',
		]
	)
);

check(
	'hex-headernaam wordt geblokkeerd',
	str_contains( firewall_op( [ 'HTTP_F5C4F24' => 'x' ] ), 'HTTP_F5C4F24' )
);

check(
	'PHP-code in een headerwaarde wordt geblokkeerd',
	str_contains( firewall_op( [ 'HTTP_X_FOO' => 'eval(base64_decode("aGk="))' ] ), 'HTTP_X_FOO' )
);

check(
	'niet-HTTP_-servervariabelen worden genegeerd',
	'' === firewall_op( [ 'REDIRECT_URL' => 'system(' ] )
);

echo "\nMU-plugin blacklist\n";

$_SERVER = [];
file_put_contents( $mu_dir . '/test-mu-plugin.php', '<?php // weg' );
file_put_contents( $mu_dir . '/kwaad.php.suspected', 'payload' );
file_put_contents( $mu_dir . '/eigen-loader.php', '<?php // blijft' );

// MU-modus: muplugins_loaded moet nog vuren, dus opruimen gebeurt via de hook.
$GLOBALS['did']   = [];
$GLOBALS['hooks'] = [];
new PDK_Security_Test( new PDK_Loader() );

check( 'MU-modus: opruimen hangt aan muplugins_loaded', isset( $GLOBALS['hooks']['muplugins_loaded'][0] ) );
check( 'MU-modus: nog niets verwijderd vóór de hook', file_exists( $mu_dir . '/test-mu-plugin.php' ) );

( $GLOBALS['hooks']['muplugins_loaded'][0] )();

check( 'blacklist-bestand verwijderd', ! file_exists( $mu_dir . '/test-mu-plugin.php' ) );
check( 'patroon *.suspected verwijderd', ! file_exists( $mu_dir . '/kwaad.php.suspected' ) );
check( 'eigen MU-plugin blijft staan', file_exists( $mu_dir . '/eigen-loader.php' ) );

// Reguliere plugin-modus: de hook is al gepasseerd, dus meteen opruimen.
$GLOBALS['did'] = [ 'muplugins_loaded' => 1 ];
file_put_contents( $mu_dir . '/test-mu-plugin.php', '<?php // weg' );
new PDK_Security_Test( new PDK_Loader() );

check( 'plugin-modus: direct verwijderd', ! file_exists( $mu_dir . '/test-mu-plugin.php' ) );

echo "\nPlugin-blacklist op slug\n";

$GLOBALS['options']['active_plugins'] = [
	'woocommerce/woocommerce.php',
	'wp-file-manager/file_folder_manager.php',
	'wtec-webp/wtec-webp.php',
];

( new PDK_Security_Test( new PDK_Loader() ) )->deactivate_dangerous_plugins();

check(
	'alleen de geblacklistte slugs worden gedeactiveerd',
	[ 'wp-file-manager/file_folder_manager.php', 'wtec-webp/wtec-webp.php' ] === $GLOBALS['deactivated']
);

$GLOBALS['deactivated']               = [];
$GLOBALS['options']['active_plugins'] = [ 'woocommerce/woocommerce.php' ];
( new PDK_Security_Test( new PDK_Loader() ) )->deactivate_dangerous_plugins();
check( 'schone site: niets gedeactiveerd', [] === $GLOBALS['deactivated'] );

echo "\nVerplichte plugins\n";

$GLOBALS['mails']                     = [];
$GLOBALS['options']['active_plugins'] = [ 'woocommerce/woocommerce.php', 'wordfence/wordfence.php' ];
$GLOBALS['settings']                  = [ 'security' => [ 'required_plugins' => [ 'woocommerce', 'wordfence' ] ] ];

$security = new PDK_Security_Test( new PDK_Loader() );

$security->check_required_plugins();
check( 'alles actief: geen mail', [] === $GLOBALS['mails'] );

// Wordfence gaat uit.
$GLOBALS['options']['active_plugins'] = [ 'woocommerce/woocommerce.php' ];
$security->check_required_plugins();
check( 'uitgezette plugin wordt gemeld', [ 'wordfence' ] === PDK_Security::missing_required_plugins() );
check( 'mail verstuurd', 1 === count( $GLOBALS['mails'] ) );
check( 'mail noemt de plugin', str_contains( $GLOBALS['mails'][0]['body'] ?? '', 'wordfence' ) );

// Zelfde situatie op de volgende pageload: niet nóg een mail.
$security->check_required_plugins();
check( 'geen herhaalde mail bij dezelfde stand', 1 === count( $GLOBALS['mails'] ) );

// Er valt er nog één uit: dat is een nieuwe stand, dus wel een mail.
$GLOBALS['options']['active_plugins'] = [];
$security->check_required_plugins();
check( 'tweede uitval mailt opnieuw', 2 === count( $GLOBALS['mails'] ) );
check(
	'tweede mail noemt beide plugins',
	str_contains( $GLOBALS['mails'][1]['body'] ?? '', 'woocommerce' )
	&& str_contains( $GLOBALS['mails'][1]['body'] ?? '', 'wordfence' )
);

// Alles weer aan: stand wissen, geen mail.
$GLOBALS['options']['active_plugins'] = [ 'woocommerce/woocommerce.php', 'wordfence/wordfence.php' ];
$security->check_required_plugins();
check( 'herstel mailt niet', 2 === count( $GLOBALS['mails'] ) );
check( 'stand is gewist', [] === $GLOBALS['options']['pdk_missing_required_plugins'] );

// Opnieuw uitvallen na herstel moet weer melden.
$GLOBALS['options']['active_plugins'] = [ 'woocommerce/woocommerce.php' ];
$security->check_required_plugins();
check( 'uitval na herstel mailt weer', 3 === count( $GLOBALS['mails'] ) );

$GLOBALS['settings'] = [];
$GLOBALS['mails']    = [];
check( 'lege lijst: niets te melden', [] === PDK_Security::missing_required_plugins() );

echo "\nIntegriteitscontrole mu-plugins/\n";

$security = new PDK_Security_Test( new PDK_Loader() );

// Eerste run: baseline vastleggen, geen mail.
$security->check_mu_integrity();
check( 'eerste run legt baseline vast', isset( $GLOBALS['options']['pdk_mu_hashes']['eigen-loader.php'] ) );
check( 'eerste run mailt niet', [] === $GLOBALS['mails'] );

// Tweede run binnen het uur: transient houdt de controle tegen.
file_put_contents( $mu_dir . '/backdoor.php', '<?php // kwaad' );
$security->check_mu_integrity();
check( 'tweede run binnen het uur slaat over', [] === $GLOBALS['mails'] );

// Uur verstreken: nieuw, gewijzigd en verwijderd bestand moeten gemeld worden.
$GLOBALS['transients'] = [];
file_put_contents( $mu_dir . '/eigen-loader.php', '<?php // bijgeschreven' );
$security->check_mu_integrity();

$bericht = $GLOBALS['mails'][0]['body'] ?? '';
check( 'afwijking wordt gemaild', 1 === count( $GLOBALS['mails'] ) );
check( 'nieuw bestand gemeld', str_contains( $bericht, 'Nieuw: backdoor.php' ) );
check( 'gewijzigd bestand gemeld', str_contains( $bericht, 'Gewijzigd: eigen-loader.php' ) );

$GLOBALS['transients'] = [];
$GLOBALS['mails']      = [];
unlink( $mu_dir . '/backdoor.php' );
$security->check_mu_integrity();
check( 'verwijderd bestand gemeld', str_contains( $GLOBALS['mails'][0]['body'] ?? '', 'Verwijderd: backdoor.php' ) );

$GLOBALS['transients'] = [];
$GLOBALS['mails']      = [];
$security->check_mu_integrity();
check( 'geen wijziging: geen tweede mail', [] === $GLOBALS['mails'] );

echo "\nREST API afschermen\n";

$security            = new PDK_Security_Test( new PDK_Loader() );
$GLOBALS['settings'] = [];
$GLOBALS['logged_in'] = false;
$_SERVER              = [ 'REMOTE_ADDR' => '198.51.100.5' ];

/** @return mixed Uitkomst van de filter. */
function rest_check( $result = null, string $route = '/wp/v2/users' ) {
	global $security;
	$GLOBALS['wp']                        = new stdClass();
	$GLOBALS['wp']->query_vars            = [ 'rest_route' => $route ];
	return $security->restrict_rest_api( $result );
}

// Waar het om begonnen is: gebruikersnamen zijn niet op te halen.
check( 'gebruikerslijst wordt geweigerd', is_wp_error( rest_check() ) );
check( 'weigering is een 401', 401 === ( rest_check()->data['status'] ?? 0 ) );
check( 'ook de rest van core is dicht', is_wp_error( rest_check( null, '/wp/v2/posts' ) ) );

/**
 * De hele reden voor een blocklist in plaats van een allowlist: een
 * betaalwebhook is een POST zonder cookie. Blokkeer je die, dan komt een
 * geslaagde betaling nooit binnen en blijft de order op 'in afwachting'.
 * Geen van deze routes hoeft ergens aangemeld te worden — ze zijn simpelweg
 * niet beschermd.
 */
foreach (
	[
		'Mollie'       => '/mollie/v1/webhook',
		'PayPal'       => '/paypal/v1/incoming',
		'Stripe'       => '/wc-stripe/v1/webhook',
		'MultiSafepay' => '/multisafepay/v1/notification',
		'Adyen'        => '/pronamic-pay/adyen/v1/notifications',
		'Store API'    => '/wc/store/v1/cart',
		'Contact Form' => '/contact-form-7/v1/contact-forms/12/feedback',
		'oEmbed'       => '/oembed/1.0/embed',
		'Onbekend'     => '/gateway-die-in-2027-bestaat/v3/webhook',
	] as $naam => $route
) {
	check( "{$naam} komt erdoor ({$route})", null === rest_check( null, $route ) );
}

$GLOBALS['logged_in'] = true;
check( 'ingelogd komt overal doorheen', null === rest_check() );
$GLOBALS['logged_in'] = false;

// Prefix-match mag niet te ruim zijn.
check( 'route die er net op lijkt blijft open', null === rest_check( null, '/wp/v3/users' ) );

// WordPress matcht zijn routes hoofdletterongevoelig; de afscherming ook.
check( 'hoofdletters glippen er niet langs', is_wp_error( rest_check( null, '/WP/V2/users' ) ) );
check( 'gemengde schrijfwijze ook niet', is_wp_error( rest_check( null, '/Wp/v2/Users' ) ) );

$GLOBALS['settings'] = [ 'security' => [ 'rest_protect_routes' => [ '/wc/store/' ] ] ];
check( 'zelf toegevoegd prefix wordt beschermd', is_wp_error( rest_check( null, '/wc/store/cart' ) ) );
check( 'core is dan niet meer beschermd', null === rest_check( null, '/wp/v2/users' ) );

$GLOBALS['settings'] = [ 'security' => [ 'rest_protect_routes' => [] ] ];
check( 'lege lijst: niets beschermd', null === rest_check() );

$_SERVER             = [ 'REMOTE_ADDR' => '198.51.100.5' ];
$GLOBALS['settings'] = [ 'security' => [ 'rest_allow_ips' => [ '198.51.100.5' ] ] ];
check( 'IP op de whitelist komt bij core', null === rest_check() );

$_SERVER = [ 'REMOTE_ADDR' => '203.0.113.9' ];
check( 'ander IP wordt geweigerd', is_wp_error( rest_check() ) );

$GLOBALS['settings'] = [ 'security' => [ 'rest_require_login' => false ] ];
check( 'afscherming uit: alles komt erdoor', null === rest_check() );

$GLOBALS['settings'] = [];
check( 'eerdere authenticatie (true) wordt niet overruled', true === rest_check( true ) );
$eerdere = new WP_Error( 'anders', 'x' );
check( 'eerdere fout wordt niet overruled', $eerdere === rest_check( $eerdere ) );

echo "\nGeweigerde routes onthouden\n";

$GLOBALS['options']['pdk_rest_blocked_routes'] = [];
$GLOBALS['settings']                           = [];
$_SERVER                                       = [ 'REMOTE_ADDR' => '203.0.113.9' ];

rest_check( null, '/wp/v2/users' );
check( 'geweigerde route wordt onthouden', isset( PDK_Security::blocked_routes()['/wp/v2/users'] ) );

// Een bot die dezelfde route blijft proberen mag de optie niet stukschrijven.
$voor = PDK_Security::blocked_routes();
rest_check( null, '/wp/v2/users' );
check( 'zelfde route binnen het uur wordt niet opnieuw geschreven', $voor === PDK_Security::blocked_routes() );

// Een scan op steeds nieuwe routes kost anders per verzoek een schrijfactie en
// drukt de echte melding uit de lijst.
for ( $i = 0; $i < 30; $i++ ) {
	rest_check( null, "/wp/v2/scan{$i}" );
}
$na = PDK_Security::blocked_routes();
check( 'scan levert geen 30 schrijfacties op', 1 === count( $na ) );
check( 'scan drukt de echte melding niet weg', isset( $na['/wp/v2/users'] ) );

// Regeleindes uit de route: die komt van de bezoeker en gaat de foutlog in.
$GLOBALS['options']['pdk_rest_blocked_routes'] = [];
rest_check( null, "/wp/v2/users\n[PDK Security] nep" );
check( 'regeleinde in de route wordt eruit gehaald', ! str_contains( implode( '', array_keys( PDK_Security::blocked_routes() ) ), "\n" ) );

// Niet meer dan BLOCKED_MAX onthouden, anders groeit de optie ongelimiteerd.
$oud = [];
for ( $i = 0; $i < 25; $i++ ) {
	$oud[ "/wp/v2/oud{$i}" ] = time() - 2 * HOUR_IN_SECONDS;
}
$GLOBALS['options']['pdk_rest_blocked_routes'] = $oud;
rest_check( null, '/wp/v2/users' );
check( 'lijst blijft begrensd op 20', 20 === count( PDK_Security::blocked_routes() ) );

// Doorgelaten routes horen er niet in te komen — anders vult een webshop de
// lijst binnen een minuut met checkout-verkeer.
$GLOBALS['options']['pdk_rest_blocked_routes'] = [];
rest_check( null, '/mollie/v1/webhook' );
check( 'doorgelaten route wordt niet gelogd', [] === PDK_Security::blocked_routes() );

rest_check( null, '/wp/v2/users' );
PDK_Security::clear_blocked_routes();
check( 'lijst leegmaken werkt', [] === PDK_Security::blocked_routes() );

echo "\nXML-RPC uit\n";

$GLOBALS['hooks']   = [];
$GLOBALS['removed'] = [];
$security->harden_xmlrpc();

check( 'xmlrpc_enabled wordt afgevangen', [ '__return_false' ] === ( $GLOBALS['hooks']['xmlrpc_enabled'] ?? [] ) );
check( 'methodelijst wordt leeggemaakt', [ '__return_empty_array' ] === ( $GLOBALS['hooks']['xmlrpc_methods'] ?? [] ) );
check( 'RSD-link wordt verwijderd', in_array( 'wp_head:rsd_link', $GLOBALS['removed'], true ) );

$header_filter = $GLOBALS['hooks']['wp_headers'][0] ?? null;
check(
	'X-Pingback-header verdwijnt',
	is_callable( $header_filter )
	&& [ 'Content-Type' => 'text/html' ] === $header_filter( [ 'X-Pingback' => '/xmlrpc.php', 'Content-Type' => 'text/html' ] )
);

$GLOBALS['hooks']    = [];
$GLOBALS['removed']  = [];
$GLOBALS['settings'] = [ 'security' => [ 'xmlrpc_disable' => false ] ];
$security->harden_xmlrpc();
check( 'uitgezet: XML-RPC blijft ongemoeid', [] === $GLOBALS['hooks'] && [] === $GLOBALS['removed'] );

$GLOBALS['settings'] = [];

array_map( 'unlink', glob( $mu_dir . '/*' ) ?: [] );
rmdir( $mu_dir );

echo "\n" . ( $fouten ? "{$fouten} test(s) gefaald\n" : "Alle tests geslaagd\n" );
exit( $fouten ? 1 : 0 );
