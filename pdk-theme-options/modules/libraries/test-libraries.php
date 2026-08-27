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
	const OPTION_KEY = 'pdk_theme_options';

	public static function get( string $module = '', string $key = '' ) {
		return $GLOBALS['settings'][ $module ][ $key ] ?? null;
	}
}

function get_option( string $key, $default = false ) {
	return $GLOBALS['options'][ $key ] ?? $default;
}
function update_option( string $key, $value, $autoload = null ): bool {
	$GLOBALS['options'][ $key ] = $value;
	return true;
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

// Opnieuw uploaden van een naam die ooit is uitgezet moet gewoon laden.
$GLOBALS['options'] = [ 'pdk_theme_options' => [ 'libraries' => [ 'disabled' => [ '20-slider-init.js' ] ] ] ];
PDK_Libraries::forget_disabled( '20-slider-init.js' );
$GLOBALS['settings'] = [ 'libraries' => [ 'disabled' => $GLOBALS['options']['pdk_theme_options']['libraries']['disabled'] ] ];

check( 'na forget_disabled staat het bestand weer aan', PDK_Libraries::is_enabled( '20-slider-init.js' ) );

echo "\nSourcemaps\n";

$GLOBALS['settings'] = [];
$GLOBALS['scripts']  = [];
$GLOBALS['styles']   = [];

file_put_contents( $dir . '10-glide.min.js.map', '{"version":3}' );
file_put_contents( $dir . 'glide.core.css.map', '{"version":3}' );

check( 'sourcemaps staan in de lijst', in_array( '10-glide.min.js.map', PDK_Libraries::scan(), true ) );
check( 'map is niet laadbaar', ! PDK_Libraries::is_enqueueable( '10-glide.min.js.map' ) );
check( 'js is wel laadbaar', PDK_Libraries::is_enqueueable( '10-glide.min.js' ) );
check( 'css is wel laadbaar', PDK_Libraries::is_enqueueable( 'glide.core.css' ) );

( new PDK_Libraries( new PDK_Loader() ) )->enqueue();

check( 'sourcemap wordt niet ingeladen als script', ! isset( $GLOBALS['scripts']['pdk-lib-10-glide-min-js'] ) );
check( 'sourcemap wordt niet ingeladen als style', ! isset( $GLOBALS['styles']['pdk-lib-glide-core-css'] ) );
check( 'aantal scripts blijft 2', 2 === count( $GLOBALS['scripts'] ) );
check( 'aantal styles blijft 1', 1 === count( $GLOBALS['styles'] ) );

unlink( $dir . '10-glide.min.js.map' );
unlink( $dir . 'glide.core.css.map' );

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
