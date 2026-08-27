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

class PDK_Loader {}

/** Alleen wat de security-module gebruikt. */
class PDK_Settings {
	public static function get( string $module = '', string $key = '' ) {
		return $GLOBALS['settings'][ $module ][ $key ] ?? null;
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

array_map( 'unlink', glob( $mu_dir . '/*' ) ?: [] );
rmdir( $mu_dir );

echo "\n" . ( $fouten ? "{$fouten} test(s) gefaald\n" : "Alle tests geslaagd\n" );
exit( $fouten ? 1 : 0 );
