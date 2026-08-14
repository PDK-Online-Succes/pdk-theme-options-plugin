<?php
/**
 * Zelftest voor de levertijd-berekening. Draaien met:
 *   php modules/delivery-time/test-delivery-time.php
 *
 * Stubt de handvol WordPress-functies die general_text() gebruikt.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ );

$GLOBALS['test_now']      = '2026-08-14 10:00'; // vrijdag
$GLOBALS['test_settings'] = [];

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
	return $text;
}
function esc_html__( string $text, string $domain = '' ): string {
	return $text;
}
function esc_attr( string $text ): string {
	return $text;
}
function add_shortcode( $tag, $cb ): void {}
function add_action( $hook, $cb, $prio = 10, $args = 1 ): void {}
function pdk_register_frontend_output( string $name, callable $render ): void {}
function pdk_day_labels(): array {
	return [ 1 => 'Maandag', 2 => 'Dinsdag', 3 => 'Woensdag', 4 => 'Donderdag', 5 => 'Vrijdag', 6 => 'Zaterdag', 7 => 'Zondag' ];
}

class PDK_Loader {}

class PDK_Settings {
	public static function get_defaults(): array {
		return [
			'delivery_time' => [
				'enabled'     => false,
				'days'        => [
					1 => [ 'enabled' => true,  'cutoff' => '22:00' ],
					2 => [ 'enabled' => true,  'cutoff' => '22:00' ],
					3 => [ 'enabled' => true,  'cutoff' => '22:00' ],
					4 => [ 'enabled' => true,  'cutoff' => '22:00' ],
					5 => [ 'enabled' => true,  'cutoff' => '22:00' ],
					6 => [ 'enabled' => false, 'cutoff' => '17:00' ],
					7 => [ 'enabled' => true,  'cutoff' => '17:00' ],
				],
				'exceptions'  => [],
				'text_before' => 'Voor {cutoff} uur besteld, {dag} verzonden (indien op voorraad)',
				'text_after'  => 'Na {cutoff} uur besteld? Verzending op {volgende_dag}.',
			],
		];
	}

	/** @return mixed */
	public static function get( string $module = '', string $key = '' ) {
		return '' === $key ? $GLOBALS['test_settings'] : ( $GLOBALS['test_settings'][ $key ] ?? null );
	}
}

require_once __DIR__ . '/class-pdk-delivery-time.php';

// 1. Vrijdag 10:00, ruim voor de cutoff → vandaag verzonden.
assert( PDK_Delivery_Time::general_text() === 'Voor 22.00 uur besteld, vandaag verzonden (indien op voorraad)' );

// 2. Vrijdag 23:00, na de cutoff → zaterdag staat uit, dus zondag 16-8.
$GLOBALS['test_now'] = '2026-08-14 23:00';
assert( PDK_Delivery_Time::general_text() === 'Na 22.00 uur besteld? Verzending op zondag 16-8.' );

// 3. Zelfde moment, maar zondag valt binnen een uitzonderingsperiode → maandag 17-8.
$GLOBALS['test_settings'] = [ 'exceptions' => [ [ 'from' => '2026-08-15', 'to' => '2026-08-16', 'label' => 'Bedrijfsuitje' ] ] ];
assert( PDK_Delivery_Time::general_text() === 'Na 22.00 uur besteld? Verzending op maandag 17-8.' );

// 4. Zaterdag (niet-verzenddag) → tekst_na met de cutoff van zaterdag zelf.
$GLOBALS['test_settings'] = [];
$GLOBALS['test_now']      = '2026-08-15 09:00';
assert( PDK_Delivery_Time::general_text() === 'Na 17.00 uur besteld? Verzending op zondag 16-8.' );

// 5. Geen enkele verzenddag ingesteld → lege tekst, geen oneindige lus.
$GLOBALS['test_settings'] = [ 'days' => array_fill_keys( range( 1, 7 ), [ 'enabled' => false, 'cutoff' => '22:00' ] ) ];
assert( PDK_Delivery_Time::general_text() === '' );

echo "OK\n";
