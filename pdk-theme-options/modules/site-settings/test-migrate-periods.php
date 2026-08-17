<?php
/**
 * Zelftest voor de periode-migratie (2.1.0 → 2.2.0). Draaien met:
 *   php modules/site-settings/test-migrate-periods.php
 *
 * Gaat mis hier iets, dan verliezen bestaande sites hun ingevulde kerst- en
 * vakantiedata — vandaar een eigen test.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ );

$GLOBALS['test_option'] = [];

function get_option( string $key, $default = false ) {
	return $GLOBALS['test_option'];
}
function __( string $text, string $domain = '' ): string {
	return $text;
}
function esc_html( string $text ): string {
	return $text;
}
function esc_url( string $url ): string {
	return $url;
}
function current_time( string $format ) {
	return ( new DateTimeImmutable( '2026-12-21', wp_timezone() ) )->format( $format );
}
function wp_timezone(): DateTimeZone {
	return new DateTimeZone( 'Europe/Amsterdam' );
}
function update_option( string $key, $value ): bool {
	$GLOBALS['test_option'] = $value;
	return true;
}
function pdk_day_labels(): array {
	return [ 1 => 'Maandag', 2 => 'Dinsdag', 3 => 'Woensdag', 4 => 'Donderdag', 5 => 'Vrijdag', 6 => 'Zaterdag', 7 => 'Zondag' ];
}
function pdk_register_frontend_output( string $name, callable $render ): void {}

class PDK_Loader {}

class PDK_Settings {
	const OPTION_KEY = 'pdk_theme_options';

	public static function get_defaults(): array {
		return [ 'site_settings' => [ 'opening_hours' => [] ] ];
	}

	/** @return mixed */
	public static function get( string $module = '', string $key = '' ) {
		if ( '' === $module ) {
			return $GLOBALS['test_option'];
		}

		$opts = $GLOBALS['test_option'][ $module ] ?? [];

		return '' === $key ? $opts : ( $opts[ $key ] ?? null );
	}
}

require_once __DIR__ . '/class-pdk-site-settings.php';

// 1. Uitzonderingsdata van Levertijden worden gesloten periodes.
$GLOBALS['test_option'] = [
	'delivery_time' => [
		'enabled'    => true,
		'exceptions' => [
			[ 'from' => '2026-12-25', 'to' => '2026-12-26', 'label' => 'Kerst' ],
			[ 'from' => '2027-01-01', 'to' => '',           'label' => 'Nieuwjaar' ],
		],
	],
];
PDK_Site_Settings::migrate_periods();

$periods = $GLOBALS['test_option']['site_settings']['periods'];
assert( count( $periods ) === 2 );
assert( $periods[0] === [ 'from' => '2026-12-25', 'to' => '2026-12-26', 'label' => 'Kerst', 'open' => '', 'close' => '', 'close_shop' => false ] );
assert( $periods[1]['to'] === '2027-01-01' ); // lege "tot" valt terug op "van"
assert( ! isset( $GLOBALS['test_option']['delivery_time']['exceptions'] ) );
assert( $GLOBALS['test_option']['delivery_time']['enabled'] === true ); // rest blijft staan

// 2. Vakantiedatums worden een periode mét webshop-sluiting.
$GLOBALS['test_option'] = [
	'vacation_mode' => [ 'enabled' => true, 'start_date' => '2027-02-15', 'end_date' => '2027-02-22' ],
];
PDK_Site_Settings::migrate_periods();

$periods = $GLOBALS['test_option']['site_settings']['periods'];
assert( count( $periods ) === 1 );
assert( $periods[0]['from'] === '2027-02-15' && $periods[0]['to'] === '2027-02-22' );
assert( $periods[0]['close_shop'] === true );
assert( ! isset( $GLOBALS['test_option']['vacation_mode']['start_date'] ) );

// 3. Dezelfde periode in beide modules wordt één rij, niet twee.
$GLOBALS['test_option'] = [
	'delivery_time' => [ 'exceptions' => [ [ 'from' => '2027-02-15', 'to' => '2027-02-22', 'label' => 'Bedrijfsvakantie' ] ] ],
	'vacation_mode' => [ 'start_date' => '2027-02-15', 'end_date' => '2027-02-22' ],
];
PDK_Site_Settings::migrate_periods();

$periods = $GLOBALS['test_option']['site_settings']['periods'];
assert( count( $periods ) === 1 );
assert( $periods[0]['label'] === 'Bedrijfsvakantie' );
assert( $periods[0]['close_shop'] === true );

// 4. Alleen een startdatum ingevuld → periode van één dag.
$GLOBALS['test_option'] = [ 'vacation_mode' => [ 'start_date' => '2027-03-01', 'end_date' => '' ] ];
PDK_Site_Settings::migrate_periods();
assert( $GLOBALS['test_option']['site_settings']['periods'][0] === [
	'from' => '2027-03-01', 'to' => '2027-03-01', 'label' => 'Vakantie',
	'open' => '', 'close' => '', 'close_shop' => true,
] );

// 5. Vakantiemodus zonder datums levert geen periode op — die site sluit
//    handmatig via de module-toggle en moet dat blijven doen.
$GLOBALS['test_option'] = [ 'vacation_mode' => [ 'enabled' => true, 'start_date' => '', 'end_date' => '' ] ];
PDK_Site_Settings::migrate_periods();
assert( $GLOBALS['test_option']['site_settings']['periods'] === [] );

// 6. Draait maar één keer: bestaande periodes worden niet overschreven.
$GLOBALS['test_option'] = [
	'site_settings' => [ 'periods' => [ [ 'from' => '2027-05-05', 'to' => '2027-05-05', 'label' => 'Bevrijdingsdag', 'open' => '', 'close' => '', 'close_shop' => false ] ] ],
	'delivery_time' => [ 'exceptions' => [ [ 'from' => '2026-12-25', 'to' => '2026-12-26', 'label' => 'Kerst' ] ] ],
];
PDK_Site_Settings::migrate_periods();
assert( count( $GLOBALS['test_option']['site_settings']['periods'] ) === 1 );
assert( $GLOBALS['test_option']['site_settings']['periods'][0]['label'] === 'Bevrijdingsdag' );

// 7. Bestaande site zonder oude data → lege lijst, geen fouten.
$GLOBALS['test_option'] = [ 'site_settings' => [ 'company_name' => 'PDK' ] ];
PDK_Site_Settings::migrate_periods();
assert( $GLOBALS['test_option']['site_settings']['periods'] === [] );
assert( $GLOBALS['test_option']['site_settings']['company_name'] === 'PDK' );

// 8. Optie bestaat nog helemaal niet: niets schrijven. Zou de migratie hier de
//    optie aanmaken, dan slaat maybe_first_run() de standaardwaarden en de
//    storage-bestanden over.
$GLOBALS['test_option'] = false;
PDK_Site_Settings::migrate_periods();
assert( $GLOBALS['test_option'] === false );

echo "OK\n";
