<?php
/**
 * Zelftest voor de SKU-opschoonregel. Draaien met:
 *   php tests/test-sku-restriction.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

// Pad naar de plugin; de tests staan bewust buiten de map die uitgeleverd wordt.
$pdk = dirname( __DIR__ ) . '/pdk-theme-options';

define( 'ABSPATH', __DIR__ );

function add_action( $hook, $cb, $prio = 10, $args = 1 ): void {}
function __( string $text, string $domain = '' ): string {
	return $text;
}
function esc_html( string $text ): string {
	return $text;
}

class PDK_Loader {}

require_once $pdk . '/modules/sku-restriction/class-pdk-sku-restriction.php';

$s = [ PDK_SKU_Restriction::class, 'sanitize' ];

// Punt blijft behouden — de toevoeging op het origineel.
assert( $s( 'ART.1234' ) === 'ART.1234' );
assert( $s( '12.5.300' ) === '12.5.300' );

// Spaties worden koppeltekens, dubbele koppeltekens vallen samen.
assert( $s( 'blauwe  steen 40' ) === 'blauwe-steen-40' );
assert( $s( 'a--b' ) === 'a-b' );

// Ongeldige tekens vervallen; hoofdletters blijven staan.
assert( $s( 'AB/CD_12#' ) === 'ABCD12' );
assert( $s( 'ëéxx' ) === 'xx' );

// Koppeltekens aan begin en eind worden getrimd.
assert( $s( '-abc-' ) === 'abc' );

// Alleen ongeldige tekens -> leeg (aanroeper slaat zo'n SKU over).
assert( $s( '###' ) === '' );

echo "OK\n";
