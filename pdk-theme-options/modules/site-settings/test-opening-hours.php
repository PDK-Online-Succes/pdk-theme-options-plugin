<?php
/**
 * Zelftest voor de openingstijden-uitvoer. Draaien met:
 *   php modules/site-settings/test-opening-hours.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ );

$GLOBALS['test_hours']   = [];
$GLOBALS['test_periods'] = [];
$GLOBALS['test_now']     = '2026-12-21'; // maandag; kerst valt die week op vr/za

function current_time( string $format ) {
	return ( new DateTimeImmutable( $GLOBALS['test_now'], wp_timezone() ) )->format( $format );
}
function wp_timezone(): DateTimeZone {
	return new DateTimeZone( 'Europe/Amsterdam' );
}
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
		return 'periods' === $key ? $GLOBALS['test_periods'] : $GLOBALS['test_hours'];
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

// -----------------------------------------------------------------------------
// Afwijkende periodes — vandaag is maandag 21-12-2026.
// -----------------------------------------------------------------------------

// 6. Kerst (vr 25 t/m za 26 dec) zonder tijden → die twee dagen gesloten,
//    de rest van de week onaangeroerd, met melding erboven.
$GLOBALS['test_periods'] = [ [ 'from' => '2026-12-25', 'to' => '2026-12-26', 'label' => 'Kerst' ] ];
$html = PDK_Site_Settings::opening_hours_html();
assert( str_contains( $html, '<th scope="row">Vrijdag</th><td>Gesloten</td>' ) );
assert( str_contains( $html, '<th scope="row">Zaterdag</th><td>Gesloten</td>' ) );
assert( str_contains( $html, '<th scope="row">Maandag</th><td>07:00 - 17:30</td>' ) );
assert( str_contains( $html, 'Let op: afwijkende openingstijden i.v.m. Kerst' ) );

// 7. Een periode met tijden overschrijft de weekdagregel — óók op zondag,
//    die normaal gesloten is.
$GLOBALS['test_periods'] = [ [ 'from' => '2026-12-01', 'to' => '2026-12-31', 'label' => 'Zomerperiode', 'open' => '09:00', 'close' => '16:00' ] ];
$html = PDK_Site_Settings::opening_hours_html();
assert( str_contains( $html, '<th scope="row">Maandag</th><td>09:00 - 16:00</td>' ) );
assert( str_contains( $html, '<th scope="row">Zondag</th><td>09:00 - 16:00</td>' ) );
assert( ! str_contains( $html, 'Gesloten' ) );

// 8. Zonder periodes geen melding.
$GLOBALS['test_periods'] = [];
assert( ! str_contains( PDK_Site_Settings::opening_hours_html(), 'Let op:' ) );

// 9. Periodes buiten de komende zeven dagen raken de tabel niet.
$GLOBALS['test_periods'] = [ [ 'from' => '2027-07-01', 'to' => '2027-07-31', 'label' => 'Zomer' ] ];
$html = PDK_Site_Settings::opening_hours_html();
assert( str_contains( $html, '<th scope="row">Maandag</th><td>07:00 - 17:30</td>' ) );
assert( ! str_contains( $html, 'Let op:' ) );

// 10. Bij overlap wint de vroegst begonnen periode, en de lijst staat op
//     begindatum gesorteerd ongeacht invoervolgorde.
$GLOBALS['test_periods'] = [
	[ 'from' => '2026-12-25', 'to' => '2026-12-25', 'label' => 'Later', 'open' => '10:00', 'close' => '12:00' ],
	[ 'from' => '2026-12-20', 'to' => '2026-12-31', 'label' => 'Eerder', 'open' => '08:00', 'close' => '14:00' ],
];
assert( PDK_Site_Settings::active_period( '2026-12-25' )['label'] === 'Eerder' );
assert( count( PDK_Site_Settings::matching_periods( '2026-12-25' ) ) === 2 );

// 11. Sluitingsdag: periode zonder tijden telt als dicht, mét tijden als open.
$GLOBALS['test_periods'] = [ [ 'from' => '2026-12-25', 'to' => '2026-12-26', 'label' => 'Kerst' ] ];
assert( PDK_Site_Settings::is_closed_on( '2026-12-25' ) === true );
assert( PDK_Site_Settings::is_closed_on( '2026-12-21' ) === false ); // maandag, gewone week
assert( PDK_Site_Settings::is_closed_on( '2026-12-27' ) === true );  // zondag, weekdagregel

// 12. Webshop-sluiting stuurt alleen de vakantiemodus aan, niet de tabel.
assert( PDK_Site_Settings::has_shop_closures() === false );
$GLOBALS['test_periods'] = [ [ 'from' => '2026-12-19', 'to' => '2026-12-23', 'label' => 'Vakantie', 'close_shop' => true ] ];
assert( PDK_Site_Settings::has_shop_closures() === true );
assert( PDK_Site_Settings::shop_closed_now() === true );  // 21-12 valt erin
$GLOBALS['test_now'] = '2026-12-24';
assert( PDK_Site_Settings::shop_closed_now() === false ); // erbuiten

// 13. Een rij zonder begindatum telt niet mee.
$GLOBALS['test_periods'] = [ [ 'from' => '', 'to' => '2026-12-26', 'label' => 'Half ingevuld' ] ];
assert( PDK_Site_Settings::periods() === [] );

echo "OK\n";
