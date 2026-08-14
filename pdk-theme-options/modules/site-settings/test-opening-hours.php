<?php
/**
 * Zelftest voor de openingstijden-uitvoer. Draaien met:
 *   php modules/site-settings/test-opening-hours.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ );

$GLOBALS['test_hours'] = [];

function __( string $text, string $domain = '' ): string {
	return $text;
}
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}
function esc_url( string $url ): string {
	return $url;
}
function pdk_day_labels(): array {
	return [ 1 => 'Maandag', 2 => 'Dinsdag', 3 => 'Woensdag', 4 => 'Donderdag', 5 => 'Vrijdag', 6 => 'Zaterdag', 7 => 'Zondag' ];
}
function pdk_register_frontend_output( string $name, callable $render ): void {}

class PDK_Loader {}

class PDK_Settings {
	public static function get_defaults(): array {
		return [
			'site_settings' => [
				'opening_hours' => [
					1 => [ 'closed' => false, 'open' => '07:00', 'close' => '17:30' ],
					2 => [ 'closed' => false, 'open' => '07:00', 'close' => '17:30' ],
					3 => [ 'closed' => false, 'open' => '07:00', 'close' => '17:30' ],
					4 => [ 'closed' => false, 'open' => '07:00', 'close' => '17:30' ],
					5 => [ 'closed' => false, 'open' => '07:00', 'close' => '17:30' ],
					6 => [ 'closed' => false, 'open' => '08:00', 'close' => '12:30' ],
					7 => [ 'closed' => true,  'open' => '',      'close' => '' ],
				],
			],
		];
	}

	/** @return mixed */
	public static function get( string $module = '', string $key = '' ) {
		return $GLOBALS['test_hours'];
	}

	/** @return mixed */
	public static function get_with_default( string $module, string $key ) {
		return '';
	}
}

require_once __DIR__ . '/class-pdk-site-settings.php';

// 1. Standaardwaarden: maandag open, zondag gesloten.
$html = PDK_Site_Settings::opening_hours_html();
assert( str_contains( $html, '<th scope="row">Maandag</th><td>07:00 - 17:30</td>' ) );
assert( str_contains( $html, '<th scope="row">Zaterdag</th><td>08:00 - 12:30</td>' ) );
assert( str_contains( $html, '<th scope="row">Zondag</th><td>Gesloten</td>' ) );
assert( ! str_contains( $html, '<h3' ) ); // geen titel zonder att

// 2. Opgeslagen waarde overschrijft de standaard, ook per los veld.
$GLOBALS['test_hours'] = [ 1 => [ 'open' => '09:00' ] ];
$html = PDK_Site_Settings::opening_hours_html();
assert( str_contains( $html, '<th scope="row">Maandag</th><td>09:00 - 17:30</td>' ) );

// 3. "Gesloten" aangevinkt wint van ingevulde tijden.
$GLOBALS['test_hours'] = [ 3 => [ 'closed' => true, 'open' => '07:00', 'close' => '17:30' ] ];
$html = PDK_Site_Settings::opening_hours_html();
assert( str_contains( $html, '<th scope="row">Woensdag</th><td>Gesloten</td>' ) );
assert( str_contains( $html, 'pdk-openingstijden__row--closed' ) );

// 4. Halve tijd ingevuld telt als gesloten (geen "07:00 - ").
$GLOBALS['test_hours'] = [ 5 => [ 'closed' => false, 'open' => '07:00', 'close' => '' ] ];
$html = PDK_Site_Settings::opening_hours_html();
assert( str_contains( $html, '<th scope="row">Vrijdag</th><td>Gesloten</td>' ) );

// 5. Titel-attribuut wordt ge-escaped meegerenderd.
$GLOBALS['test_hours'] = [];
$html = PDK_Site_Settings::opening_hours_html( [ 'title' => 'Openingstijden <bouwshop>' ] );
assert( str_contains( $html, '<h3 class="pdk-openingstijden__title">Openingstijden &lt;bouwshop&gt;</h3>' ) );

echo "OK\n";
