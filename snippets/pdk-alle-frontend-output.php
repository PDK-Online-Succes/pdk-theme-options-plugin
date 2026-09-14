<?php
/**
 * Toont alle frontend-uitvoer van PDK Theme Options in één keer.
 *
 * Plak dit in PDK Theme Options → Custom Functions, maak een lege pagina
 * en zet daar de shortcode [pdk_alle_output] op.
 *
 * Attributen:
 *   [pdk_alle_output]              → alleen de gerenderde uitvoer
 *   [pdk_alle_output debug="1"]    → met naam, shortcode en markup-beschrijving
 *
 * Let op: modules die niet ingeschakeld zijn staan hier niet tussen, en een
 * ingeschakelde module kan een lege string teruggeven (levertijd buiten een
 * productpagina, vakantiemelding buiten een vakantieperiode).
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'pdk_alle_output', function ( $atts = [] ): string {
	$atts  = shortcode_atts( [ 'debug' => '0' ], (array) $atts, 'pdk_alle_output' );
	$debug = '1' === (string) $atts['debug'];

	if ( ! function_exists( 'pdk_frontend_outputs' ) ) {
		return '<p>PDK Theme Options is niet actief.</p>';
	}

	// Kop + toelichting per blok, alleen in debugmodus.
	$head = static function ( string $title, string $usage, string $note = '' ) use ( $debug ): string {
		if ( ! $debug ) {
			return '';
		}

		$html = '<h2 style="margin:0 0 .25rem">' . esc_html( $title ) . '</h2>'
			. '<p style="margin:0 0 .5rem"><code>' . esc_html( $usage ) . '</code></p>';

		if ( '' !== $note ) {
			$html .= '<p style="margin:0 0 .75rem;opacity:.7">' . esc_html( $note ) . '</p>';
		}

		return $html;
	};

	$section = static function ( string $inner ): string {
		return '<section class="pdk-alle-output__item" style="margin:0 0 2rem">' . $inner . '</section>';
	};

	$leeg = '<p style="opacity:.6"><em>Niet ingevuld in de instellingen.</em></p>';
	$html = '';

	// -------------------------------------------------------------------------
	// Shortcodes/hooks uit het register (openingstijden, levertijd, vakantie…)
	// -------------------------------------------------------------------------
	foreach ( pdk_frontend_outputs() as $name => $item ) {
		$rendered = (string) call_user_func( $item['render'], [] );

		$html .= $section(
			$head( $name, '[' . $name . ']  ·  do_action( \'pdk_' . $name . '\' )', $item['markup'] )
			. ( '' !== trim( $rendered )
				? $rendered
				: '<p style="opacity:.6"><em>Geen uitvoer in deze context.</em></p>' )
		);
	}

	// -------------------------------------------------------------------------
	// Logo, klantgegevens en social — geen shortcode, alleen PHP-helpers.
	// -------------------------------------------------------------------------
	$logo  = function_exists( 'pdk_client_logo_url' ) ? pdk_client_logo_url() : '';
	$html .= $section(
		$head( 'Logo', 'pdk_client_logo_url()', 'Geeft de URL van het klantlogo terug; zelf in een <img> zetten.' )
		. ( $logo
			? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( pdk_site_setting( 'company_name' ) ) . '" style="max-width:240px;height:auto">'
			: $leeg )
	);

	$velden = [
		'company_name'    => 'Bedrijfsnaam',
		'company_street'  => 'Straat',
		'company_number'  => 'Huisnummer',
		'company_zipcode' => 'Postcode',
		'company_city'    => 'Plaats',
		'company_phone'   => 'Telefoon',
		'company_email'   => 'E-mail',
	];

	$rijen = '';
	foreach ( $velden as $key => $label ) {
		$waarde = pdk_site_setting( $key );
		$rijen .= '<tr><th scope="row" style="text-align:left;padding-right:1rem">' . esc_html( $label ) . '</th>'
			. '<td>' . ( '' !== $waarde ? $waarde : '<span style="opacity:.6">—</span>' ) . '</td></tr>';
	}

	$adres = function_exists( 'pdk_company_address' ) ? pdk_company_address() : '';
	$rijen .= '<tr><th scope="row" style="text-align:left;padding-right:1rem">Adres (opgemaakt)</th>'
		. '<td>' . ( '' !== $adres ? $adres : '<span style="opacity:.6">—</span>' ) . '</td></tr>';

	$html .= $section(
		$head( 'Klantgegevens', 'pdk_site_setting( \'company_phone\' )  ·  pdk_company_address()', 'Beide geven een ge-escapete string terug — de opmaak maak je zelf.' )
		. '<table><tbody>' . $rijen . '</tbody></table>'
	);

	$socials = [
		'social_facebook'  => 'Facebook',
		'social_instagram' => 'Instagram',
		'social_linkedin'  => 'LinkedIn',
		'social_twitter'   => 'X / Twitter',
		'social_youtube'   => 'YouTube',
		'social_tiktok'    => 'TikTok',
	];

	$links = '';
	foreach ( $socials as $key => $label ) {
		$url = PDK_Settings::get_with_default( 'site_settings', $key );
		if ( ! $url ) {
			continue;
		}

		$links .= '<li><a href="' . esc_url( $url ) . '" rel="noopener" target="_blank">' . esc_html( $label ) . '</a></li>';
	}

	$html .= $section(
		$head( 'Social media', 'pdk_site_setting( \'social_instagram\' )', 'Losse URL per kanaal; alleen ingevulde kanalen staan hier.' )
		. ( $links ? '<ul>' . $links . '</ul>' : $leeg )
	);

	return $html;
} );
