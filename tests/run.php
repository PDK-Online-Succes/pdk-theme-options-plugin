<?php
/**
 * Draait alle zelftests achter elkaar.
 *
 *   php tests/run.php
 *
 * De tests staan bewust buiten pdk-theme-options/: de installer pakt alleen die
 * map uit de zipball, dus zo komt er geen testcode op een klantsite terecht.
 * Elk testbestand is los te draaien met `php tests/<naam>.php`.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$files = glob( __DIR__ . '/test-*.php' );
sort( $files );

$mislukt = [];

foreach ( $files as $file ) {
	$naam = basename( $file );

	// Elk testbestand definieert zijn eigen ABSPATH en stubs, dus ze kunnen niet
	// in één proces samen. Een subproces per bestand houdt ze uit elkaars vaarwater.
	$output = [];
	$code   = 0;
	exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $output, $code );

	$laatste = '';
	foreach ( array_reverse( $output ) as $regel ) {
		if ( '' !== trim( $regel ) ) {
			$laatste = trim( $regel );
			break;
		}
	}

	// Een test die niets afdrukt maar wel 0 teruggeeft, telt niet als geslaagd:
	// dan is er iets stukgelopen voordat er ook maar iets gecontroleerd is.
	$ok = ( 0 === $code && '' !== $laatste );

	if ( ! $ok ) {
		$mislukt[] = $naam;
	}

	printf( "%-32s %s  %s\n", $naam, $ok ? 'OK  ' : 'FOUT', $laatste );

	if ( ! $ok ) {
		foreach ( $output as $regel ) {
			echo '    ' . $regel . "\n";
		}
	}
}

echo "\n" . count( $files ) . ' bestanden, ' . count( $mislukt ) . " mislukt\n";

exit( $mislukt ? 1 : 0 );
