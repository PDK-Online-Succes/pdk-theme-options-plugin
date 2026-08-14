<?php
/**
 * Module: Site Instellingen (Carbon Fields migratie)
 *
 * Vervangt de Carbon Fields-integratie uit de oorspronkelijke
 * pdk-theme-options-plugin. Beheert:
 *  - Favicon
 *  - Pagina-editor uitschakelen (Gutenberg op pagina's)
 *  - Klantgegevens (logo, bedrijfsinfo, contactgegevens, social media)
 *
 * Deze module is ALTIJD actief — geen toggle nodig.
 * Klantgegevens zijn opvraagbaar via pdk_site_setting( $key ).
 */

defined( 'ABSPATH' ) || exit;

class PDK_Site_Settings {

	public function __construct( PDK_Loader $loader ) {
		$loader->add_action( 'wp_head', $this, 'output_favicon', 1 );
		$loader->add_action( 'init',    $this, 'maybe_disable_page_editor' );

		// Shortcode [openingstijden] + template-hook do_action( 'pdk_openingstijden' ).
		pdk_register_frontend_output( 'openingstijden', [ self::class, 'opening_hours_html' ] );
	}

	/** Voegt de favicon-link toe aan de <head>. */
	public function output_favicon(): void {
		$url = PDK_Settings::get_with_default( 'site_settings', 'favicon_url' );
		if ( ! $url ) {
			return;
		}

		printf(
			'<link rel="icon" href="%s">' . "\n",
			esc_url( $url )
		);
	}

	/** Schakelt de Gutenberg-editor uit op paginatype 'page' indien ingesteld. */
	public function maybe_disable_page_editor(): void {
		if ( ! PDK_Settings::get_with_default( 'site_settings', 'disable_page_editor' ) ) {
			return;
		}

		add_filter( 'use_block_editor_for_post_type', static function ( bool $use, string $post_type ): bool {
			if ( 'page' === $post_type ) {
				return false;
			}
			return $use;
		}, 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Openingstijden
	// -------------------------------------------------------------------------

	/**
	 * Openingstijden per weekdag, met terugval op de standaardwaarden.
	 * Sleutels 1 (maandag) t/m 7 (zondag).
	 *
	 * @return array<int,array{closed:bool,open:string,close:string}>
	 */
	public static function opening_hours(): array {
		$defaults = PDK_Settings::get_defaults()['site_settings']['opening_hours'];
		$saved    = (array) PDK_Settings::get( 'site_settings', 'opening_hours' );

		$hours = [];
		foreach ( $defaults as $index => $day ) {
			$hours[ $index ] = array_merge( $day, (array) ( $saved[ $index ] ?? [] ) );
		}

		return $hours;
	}

	/**
	 * Openingstijden als HTML-tabel.
	 *
	 * Shortcode:  [openingstijden title="Openingstijden bouwshop"]
	 * Template:   do_action( 'pdk_openingstijden' );
	 *             do_action( 'pdk_openingstijden', [ 'title' => 'Openingstijden' ] );
	 */
	public static function opening_hours_html( array $atts = [] ): string {
		$rows = '';

		foreach ( pdk_day_labels() as $index => $label ) {
			$day = self::opening_hours()[ $index ];

			// Dicht, of een onvolledige tijd ingevuld → geldt als gesloten.
			$is_closed = ! empty( $day['closed'] ) || '' === $day['open'] || '' === $day['close'];
			$time      = $is_closed
				? __( 'Gesloten', 'pdk-theme-options' )
				: $day['open'] . ' - ' . $day['close'];

			$rows .= sprintf(
				'<tr class="pdk-openingstijden__row%s"><th scope="row">%s</th><td>%s</td></tr>',
				$is_closed ? ' pdk-openingstijden__row--closed' : '',
				esc_html( $label ),
				esc_html( $time )
			);
		}

		$title = ! empty( $atts['title'] )
			? sprintf( '<h3 class="pdk-openingstijden__title">%s</h3>', esc_html( $atts['title'] ) )
			: '';

		return $title . '<table class="pdk-openingstijden"><tbody>' . $rows . '</tbody></table>';
	}
}

/**
 * Hulpfunctie voor gebruik in thema's en andere plugins.
 * Geeft een klantgegeven terug uit de site-instellingen.
 *
 * Gebruik: pdk_site_setting( 'company_phone' )
 *
 * @param string $key  Sleutel uit site_settings (bijv. 'company_name', 'social_instagram').
 * @return string      Escaped waarde, of lege string.
 */
function pdk_site_setting( string $key ): string {
	return esc_html( (string) PDK_Settings::get_with_default( 'site_settings', $key ) );
}

/**
 * Geeft de URL van het klantlogo terug (niet ge-escaped, voor gebruik in src=).
 */
function pdk_client_logo_url(): string {
	return esc_url( (string) PDK_Settings::get_with_default( 'site_settings', 'client_logo' ) );
}

/**
 * Geeft het volledige bedrijfsadres terug als opgemaakte string.
 * Voorbeeld: "Kerkstraat 12, 1234 AB Amsterdam"
 */
function pdk_company_address(): string {
	$street  = PDK_Settings::get_with_default( 'site_settings', 'company_street' );
	$number  = PDK_Settings::get_with_default( 'site_settings', 'company_number' );
	$zipcode = PDK_Settings::get_with_default( 'site_settings', 'company_zipcode' );
	$city    = PDK_Settings::get_with_default( 'site_settings', 'company_city' );

	$line1 = trim( $street . ' ' . $number );
	$line2 = trim( $zipcode . ' ' . $city );

	$parts = array_filter( [ $line1, $line2 ] );
	return esc_html( implode( ', ', $parts ) );
}

/**
 * Geeft de openingstijden-tabel terug als HTML-string, voor gebruik in thema's.
 * Wie liever een hook gebruikt: do_action( 'pdk_openingstijden' ).
 */
function pdk_opening_hours_html( string $title = '' ): string {
	return PDK_Site_Settings::opening_hours_html( $title ? [ 'title' => $title ] : [] );
}
