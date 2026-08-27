<?php
/**
 * Zelftest voor de libraries-module.
 * Draaien met:
 *   php modules/libraries/test-libraries.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'PDK_STORAGE_DIR', sys_get_temp_dir() . '/pdk-lib-test-' . getmypid() . '/' );
define( 'PDK_STORAGE_URL', 'https://example.test/uploads/pdk-theme-options/' );
define( 'PDK_LIBRARY_SUBDIR', 'libraries/' );

mkdir( PDK_STORAGE_DIR . 'libraries', 0777, true );

$GLOBALS['settings'] = [];
$GLOBALS['styles']   = [];
$GLOBALS['scripts']  = [];

function pdk_file_is_tampered( string $bestand ): bool {
	return in_array( $bestand, $GLOBALS['tampered'] ?? [], true );
}
function sanitize_title( string $text ): string {
	return strtolower( preg_replace( '/[^a-zA-Z0-9]+/', '-', $text ) );
}
function wp_enqueue_style( string $handle, string $src, array $deps = [], $ver = false ): void {
	$GLOBALS['styles'][ $handle ] = [ 'src' => $src, 'ver' => $ver ];
}
function wp_enqueue_script( string $handle, string $src, array $deps = [], $ver = false, $footer = false ): void {
	$GLOBALS['scripts'][ $handle ] = [ 'src' => $src, 'ver' => $ver, 'footer' => $footer ];
}

class PDK_Loader {
	public function add_action( string $hook, $obj, string $method, int $prio = 10, int $args = 1 ): void {}
}

class PDK_Settings {
	public static function get( string $module = '', string $key = '' ) {
		return $GLOBALS['settings'][ $module ][ $key ] ?? null;
	}
}

require_once __DIR__ . '/class-pdk-libraries.php';

$fouten = 0;
function check( string $naam, bool $ok ): void {
	global $fouten;
	echo ( $ok ? "  ok   " : "  FOUT " ) . $naam . "\n";
	if ( ! $ok ) {
		$fouten++;
	}
}

$dir = PDK_Libraries::dir();

echo "Scannen\n";

check( 'lege map geeft lege lijst', [] === PDK_Libraries::scan() );

file_put_contents( $dir . '20-slider-init.js', 'new Glide(".x").mount();' );
file_put_contents( $dir . '10-glide.min.js', 'var Glide;' );
file_put_contents( $dir . 'glide.core.css', '.glide{}' );
file_put_contents( $dir . 'leesmij.txt', 'geen library' );
file_put_contents( $dir . 'kwaad.php', '<?php // nooit laden' );

check(
	'alleen js en css, alfabetisch',
	[ '10-glide.min.js', '20-slider-init.js', 'glide.core.css' ] === PDK_Libraries::scan()
);

echo "\nInladen\n";

( new PDK_Libraries( new PDK_Loader() ) )->enqueue();

check( 'css als style', isset( $GLOBALS['styles']['pdk-lib-glide-core'] ) );
check( 'js als script', isset( $GLOBALS['scripts']['pdk-lib-10-glide-min'] ) );
check( 'beide js-bestanden geladen', 2 === count( $GLOBALS['scripts'] ) );
check( 'js in de footer', true === $GLOBALS['scripts']['pdk-lib-10-glide-min']['footer'] );
check(
	'url wijst naar de libraries-map',
	'https://example.test/uploads/pdk-theme-options/libraries/glide.core.css' === $GLOBALS['styles']['pdk-lib-glide-core']['src']
);
check( 'versie is de filemtime', (string) filemtime( $dir . 'glide.core.css' ) === $GLOBALS['styles']['pdk-lib-glide-core']['ver'] );
check( 'php-bestand wordt genegeerd', ! isset( $GLOBALS['scripts']['pdk-lib-kwaad'] ) );

echo "\nUitzetten\n";

$GLOBALS['settings'] = [ 'libraries' => [ 'disabled' => [ '20-slider-init.js' ] ] ];
$GLOBALS['scripts']  = [];
$GLOBALS['styles']   = [];

check( 'uitgezet bestand telt als uit', ! PDK_Libraries::is_enabled( '20-slider-init.js' ) );
check( 'de rest blijft aan', PDK_Libraries::is_enabled( '10-glide.min.js' ) );

( new PDK_Libraries( new PDK_Loader() ) )->enqueue();

check( 'uitgezet bestand laadt niet', ! isset( $GLOBALS['scripts']['pdk-lib-20-slider-init'] ) );
check( 'ingeschakeld bestand laadt wel', isset( $GLOBALS['scripts']['pdk-lib-10-glide-min'] ) );
check( 'uitgezet bestand blijft op schijf staan', file_exists( $dir . '20-slider-init.js' ) );

echo "\nIntegriteitscontrole\n";

$GLOBALS['settings'] = [];
$GLOBALS['scripts']  = [];
$GLOBALS['styles']   = [];
$GLOBALS['tampered'] = [ 'libraries/10-glide.min.js' ];

check( 'rel_path gebruikt de submap', 'libraries/10-glide.min.js' === PDK_Libraries::rel_path( '10-glide.min.js' ) );

( new PDK_Libraries( new PDK_Loader() ) )->enqueue();

check( 'gemanipuleerd bestand wordt niet geladen', ! isset( $GLOBALS['scripts']['pdk-lib-10-glide-min'] ) );
check( 'de rest laadt gewoon door', isset( $GLOBALS['scripts']['pdk-lib-20-slider-init'] ) );
check( 'css laadt gewoon door', isset( $GLOBALS['styles']['pdk-lib-glide-core'] ) );

$GLOBALS['tampered'] = [];

echo "\nLeeg bestand\n";

$GLOBALS['settings'] = [];
$GLOBALS['scripts']  = [];
file_put_contents( $dir . 'leeg.js', '' );
( new PDK_Libraries( new PDK_Loader() ) )->enqueue();
check( 'leeg bestand wordt overgeslagen', ! isset( $GLOBALS['scripts']['pdk-lib-leeg'] ) );

array_map( 'unlink', glob( $dir . '*' ) ?: [] );
rmdir( $dir );
rmdir( PDK_STORAGE_DIR );

echo "\n" . ( $fouten ? "{$fouten} test(s) gefaald\n" : "Alle tests geslaagd\n" );
exit( $fouten ? 1 : 0 );
