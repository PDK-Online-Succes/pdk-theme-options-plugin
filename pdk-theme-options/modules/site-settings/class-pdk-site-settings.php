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
		// Direct, niet op een hook: de vakantiemodus en levertijden lezen de
		// periodes zodra ze geladen worden — dat is nog vóór 'init'.
		self::migrate_periods();

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
	 * Openingstijden als HTML-tabel voor de komende zeven dagen.
	 *
	 * Elke weekdag krijgt de eerstvolgende concrete datum toegewezen, zodat een
	 * afwijkende periode (kerst, zomerperiode) op de juiste dag landt. Loopt er
	 * zo'n periode, dan verschijnt er een melding boven de tabel.
	 *
	 * Shortcode:  [openingstijden title="Openingstijden bouwshop"]
	 * Template:   do_action( 'pdk_openingstijden' );
	 *             do_action( 'pdk_openingstijden', [ 'title' => 'Openingstijden' ] );
	 */
	public static function opening_hours_html( array $atts = [] ): string {
		$hours  = self::opening_hours();
		$labels = pdk_day_labels();
		$dates  = self::upcoming_week();
		$rows   = '';
		$notes  = [];

		foreach ( $labels as $index => $label ) {
			$day    = $hours[ $index ];
			$period = self::active_period( $dates[ $index ] ?? '' );

			// Een periode overschrijft de weekdagregel; lege tijden = gesloten.
			if ( $period ) {
				$day = [ 'closed' => false, 'open' => $period['open'], 'close' => $period['close'] ];

				if ( '' !== $period['label'] ) {
					$notes[ $period['label'] ] = $period['label'];
				}
			}

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

		$notice = $notes
			? sprintf(
				'<p class="pdk-openingstijden__notice">%s</p>',
				esc_html( sprintf(
					/* translators: %s: omschrijving(en) van de periode, bijv. "Kerst" */
					__( 'Let op: afwijkende openingstijden i.v.m. %s', 'pdk-theme-options' ),
					implode( ', ', $notes )
				) )
			)
			: '';

		return $title . $notice . '<table class="pdk-openingstijden"><tbody>' . $rows . '</tbody></table>';
	}

	// -------------------------------------------------------------------------
	// Afwijkende periodes (kerst, zomerperiode, bedrijfsvakantie)
	// -------------------------------------------------------------------------

	/**
	 * Alle ingestelde periodes, oplopend op begindatum.
	 *
	 * @return array<int,array{from:string,to:string,label:string,open:string,close:string,close_shop:bool}>
	 */
	public static function periods(): array {
		$periods = [];

		foreach ( (array) PDK_Settings::get( 'site_settings', 'periods' ) as $row ) {
			if ( empty( $row['from'] ) ) {
				continue;
			}

			$periods[] = [
				'from'       => (string) $row['from'],
				'to'         => ! empty( $row['to'] ) ? (string) $row['to'] : (string) $row['from'],
				'label'      => (string) ( $row['label'] ?? '' ),
				'open'       => (string) ( $row['open'] ?? '' ),
				'close'      => (string) ( $row['close'] ?? '' ),
				'close_shop' => ! empty( $row['close_shop'] ),
			];
		}

		usort( $periods, static fn( array $a, array $b ): int => strcmp( $a['from'], $b['from'] ) );

		return $periods;
	}

	/**
	 * Alle periodes waar een datum binnen valt — periodes mogen overlappen.
	 *
	 * @param string $ymd Datum als Y-m-d; leeg is vandaag.
	 * @return array<int,array>
	 */
	public static function matching_periods( string $ymd = '' ): array {
		$ymd = $ymd ?: current_time( 'Y-m-d' );

		return array_values( array_filter(
			self::periods(),
			static fn( array $p ): bool => $ymd >= $p['from'] && $ymd <= $p['to']
		) );
	}

	/**
	 * De periode die op een datum geldt — de vroegst begonnene bij overlap.
	 *
	 * @param string $ymd Datum als Y-m-d; leeg is vandaag.
	 */
	public static function active_period( string $ymd = '' ): ?array {
		return self::matching_periods( $ymd )[0] ?? null;
	}

	/** Is er nu een periode die de webshop sluit? Stuurt de vakantiemodus aan. */
	public static function shop_closed_now(): bool {
		foreach ( self::matching_periods() as $period ) {
			if ( $period['close_shop'] ) {
				return true;
			}
		}

		return false;
	}

	/** Staat er überhaupt een webshop-sluiting gepland? */
	public static function has_shop_closures(): bool {
		foreach ( self::periods() as $period ) {
			if ( $period['close_shop'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is er op deze datum gesloten? Een periode zonder tijden telt als dicht,
	 * anders beslist de weekdagregel.
	 *
	 * @param string $ymd Datum als Y-m-d; leeg is vandaag.
	 */
	public static function is_closed_on( string $ymd = '' ): bool {
		$ymd    = $ymd ?: current_time( 'Y-m-d' );
		$period = self::active_period( $ymd );

		if ( $period ) {
			return '' === $period['open'] || '' === $period['close'];
		}

		$day = self::opening_hours()[ (int) ( new DateTimeImmutable( $ymd, wp_timezone() ) )->format( 'N' ) ];

		return ! empty( $day['closed'] ) || '' === $day['open'] || '' === $day['close'];
	}

	/**
	 * Verhuist de losse periodes van vóór 2.2.0 naar site_settings.periods:
	 * de uitzonderingsdata van Levertijden en de start/einddatum van de
	 * Vakantiemodus. Draait eenmalig; zonder deze stap verliezen bestaande
	 * sites hun ingevulde kerst- en vakantiedata.
	 */
	public static function migrate_periods(): void {
		// Bewust get_option() en niet PDK_Settings::get(): die geeft een lege
		// array als de optie nog niet bestaat. Zou de migratie dan schrijven,
		// dan denkt maybe_first_run() dat de plugin al geïnitialiseerd is en
		// blijven de standaardwaarden en storage-bestanden achterwege.
		$options = get_option( PDK_Settings::OPTION_KEY, false );

		if ( ! is_array( $options ) ) {
			return;
		}

		// Al gemigreerd, of een verse installatie met de standaardwaarden.
		if ( isset( $options['site_settings']['periods'] ) ) {
			return;
		}

		$periods = [];

		foreach ( (array) ( $options['delivery_time']['exceptions'] ?? [] ) as $row ) {
			if ( empty( $row['from'] ) ) {
				continue;
			}

			$periods[] = [
				'from'       => (string) $row['from'],
				'to'         => ! empty( $row['to'] ) ? (string) $row['to'] : (string) $row['from'],
				'label'      => (string) ( $row['label'] ?? '' ),
				'open'       => '', // uitzonderingsdata waren altijd volledig dicht
				'close'      => '',
				'close_shop' => false,
			];
		}

		$start = (string) ( $options['vacation_mode']['start_date'] ?? '' );
		$end   = (string) ( $options['vacation_mode']['end_date'] ?? '' );

		if ( '' !== $start || '' !== $end ) {
			$from = $start ?: $end;
			$to   = $end ?: $start;

			// Viel de vakantie samen met een uitzonderingsdatum, dan is het één
			// periode — die krijgt de webshop-sluiting erbij in plaats van een
			// tweede rij met dezelfde datums.
			$merged = false;
			foreach ( $periods as $i => $period ) {
				if ( $period['from'] === $from && $period['to'] === $to ) {
					$periods[ $i ]['close_shop'] = true;
					$merged                      = true;
					break;
				}
			}

			if ( ! $merged ) {
				$periods[] = [
					'from'       => $from,
					'to'         => $to,
					'label'      => __( 'Vakantie', 'pdk-theme-options' ),
					'open'       => '',
					'close'      => '',
					'close_shop' => true,
				];
			}
		}

		$options['site_settings']['periods'] = $periods;

		unset( $options['delivery_time']['exceptions'], $options['vacation_mode']['start_date'], $options['vacation_mode']['end_date'] );

		update_option( PDK_Settings::OPTION_KEY, $options );
	}

	/**
	 * Weekdagindex (1-7) → eerstvolgende datum vanaf vandaag.
	 *
	 * @return array<int,string>
	 */
	private static function upcoming_week(): array {
		$today = new DateTimeImmutable( current_time( 'Y-m-d' ), wp_timezone() );
		$dates = [];

		for ( $i = 0; $i < 7; $i++ ) {
			$date = $today->modify( "+{$i} days" );

			$dates[ (int) $date->format( 'N' ) ] = $date->format( 'Y-m-d' );
		}

		return $dates;
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
