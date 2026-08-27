<?php
/**
 * Module: Security
 *
 * Vijf maatregelen die ALTIJD draaien — alleen de lijst met verplichte plugins
 * is instelbaar, de rest staat vast in de code:
 *
 *  1. Header-firewall — blokkeert requests met verdachte custom headers
 *     (eval-via-header backdoors).
 *  2. MU-plugin blacklist — verwijdert ongewenste bestanden uit mu-plugins/.
 *  3. Plugin-blacklist — deactiveert gevaarlijke plugins op slug.
 *  4. Verplichte plugins — mailt de beheerder zodra er één uit staat. Welke dat
 *     zijn is wél instelbaar, via de Security-tab.
 *  5. Integriteitscontrole van mu-plugins/ — mailt de beheerder bij afwijking.
 *
 * 1 en 2 draaien direct bij het laden van de module, dus nog vóór `init`.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Security {

	/** Vingerafdrukken van mu-plugins/, sha256 per bestandsnaam. */
	private const MU_HASH_OPTION = 'pdk_mu_hashes';

	/** Laatst gemelde lijst met verplichte plugins die uit stonden. */
	private const MISSING_OPTION = 'pdk_missing_required_plugins';

	/**
	 * MU-plugins die altijd verwijderd worden. Exacte bestandsnamen.
	 */
	private const MU_BLACKLIST = [
		'installatron_hide_status_test.php',
		'automation-by-installatron.php',
		'test-mu-plugin.php',
	];

	/**
	 * Glob-patronen, bv. door een scanner in quarantaine gezette bestanden.
	 */
	private const MU_BLACKLIST_PATTERNS = [
		'*.suspected',
	];

	/**
	 * Plugins die nooit actief mogen zijn. Alleen de slug (mapnaam), dus
	 * 'wp-file-manager', niet 'wp-file-manager/file_folder_manager.php'.
	 */
	private const DANGEROUS_PLUGINS = [
		'wp-file-manager',
		'wtec-webp',
	];

	public function __construct( PDK_Loader $loader ) {
		// Zo vroeg mogelijk: de module wordt geladen vóór `init`.
		$this->block_suspicious_headers();

		// In MU-modus laadt de plugin nog binnen de mu-plugins-lus, dus vóór
		// `muplugins_loaded`. In reguliere plugin-modus is die hook al gepasseerd
		// en zou hij nooit meer vuren — dan meteen uitvoeren.
		if ( did_action( 'muplugins_loaded' ) ) {
			$this->remove_blacklisted_muplugins();
		} else {
			add_action( 'muplugins_loaded', [ $this, 'remove_blacklisted_muplugins' ], 1 );
		}

		add_action( 'admin_init', [ $this, 'deactivate_dangerous_plugins' ] );
		add_action( 'init', [ $this, 'check_mu_integrity' ], 0 );
		add_action( 'init', [ $this, 'check_required_plugins' ], 0 );
		add_action( 'admin_notices', [ $this, 'show_required_plugins_notice' ] );
	}

	// -------------------------------------------------------------------------
	// 1. Header-firewall
	// -------------------------------------------------------------------------

	/**
	 * Blokkeert requests met een verdachte custom header. Backdoors smokkelen
	 * hun payload vaak binnen via een header met een hex-naam (HTTP_F5C4F24).
	 */
	protected function block_suspicious_headers(): void {
		foreach ( $_SERVER as $key => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( 0 !== strpos( (string) $key, 'HTTP_' ) ) {
				continue;
			}

			// Headernaam die alleen uit hex bestaat — geen echte client stuurt dat.
			if ( preg_match( '/^[A-F0-9]{6,10}$/i', substr( (string) $key, 5 ) ) ) {
				$this->forbid( "verdachte header {$key}" );
			}

			// Headerwaarde die op PHP-code lijkt.
			if ( is_string( $value ) && preg_match( '/(eval|base64_decode|system|exec|assert)\s*\(/i', $value ) ) {
				$this->forbid( "verdachte payload in header {$key}: " . substr( $value, 0, 200 ) );
			}
		}
	}

	/** Logt en beëindigt het request met een 403. */
	protected function forbid( string $reden ): void {
		error_log( '[PDK Security] Geblokkeerd — ' . $reden ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		http_response_code( 403 );
		exit( 'Forbidden' );
	}

	// -------------------------------------------------------------------------
	// 2. MU-plugin blacklist
	// -------------------------------------------------------------------------

	/**
	 * Verwijdert geblacklistte MU-plugins.
	 */
	public function remove_blacklisted_muplugins(): void {
		$mu_dir = $this->mu_dir();

		if ( ! $mu_dir ) {
			return;
		}

		foreach ( self::MU_BLACKLIST as $bestand ) {
			if ( file_exists( $mu_dir . $bestand ) ) {
				wp_delete_file( $mu_dir . $bestand );
				error_log( "[PDK Security] MU-plugin verwijderd (blacklist): {$bestand}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		foreach ( self::MU_BLACKLIST_PATTERNS as $pattern ) {
			foreach ( glob( $mu_dir . $pattern ) ?: [] as $pad ) {
				wp_delete_file( $pad );
				error_log( '[PDK Security] MU-plugin verwijderd (patroon): ' . basename( $pad ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	// -------------------------------------------------------------------------
	// 3. Plugin-blacklist
	// -------------------------------------------------------------------------

	/**
	 * Deactiveert gevaarlijke plugins en meldt dat in de admin.
	 *
	 * ponytail: kijkt alleen naar `active_plugins`, dus niet naar
	 * netwerk-geactiveerde plugins op multisite — voeg `active_sitewide_plugins`
	 * toe zodra er een multisite-klant is.
	 */
	public function deactivate_dangerous_plugins(): void {
		$actief = array_filter(
			(array) get_option( 'active_plugins', [] ),
			static fn( $basename ) => in_array( strtok( (string) $basename, '/' ), self::DANGEROUS_PLUGINS, true )
		);

		if ( ! $actief ) {
			return;
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( array_values( $actief ) );

		$slugs = array_map( static fn( $basename ) => strtok( (string) $basename, '/' ), $actief );
		error_log( '[PDK Security] Plugin gedeactiveerd (blacklist): ' . implode( ', ', $slugs ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		add_action(
			'admin_notices',
			static function () use ( $slugs ): void {
				echo '<div class="notice notice-error is-dismissible"><p>';
				printf(
					/* translators: %s: komma-gescheiden lijst met plugin-slugs */
					esc_html__( 'Deze plugin(s) zijn geblokkeerd en automatisch gedeactiveerd: %s', 'pdk-theme-options' ),
					esc_html( implode( ', ', $slugs ) )
				);
				echo '</p></div>';
			}
		);
	}

	// -------------------------------------------------------------------------
	// 4. Plugins die actief moeten blijven
	// -------------------------------------------------------------------------

	/**
	 * Slugs van plugins die aangemerkt zijn als verplicht, maar niet actief zijn.
	 *
	 * @return string[] Gesorteerd, zodat twee controles vergelijkbaar zijn.
	 */
	public static function missing_required_plugins(): array {
		$vereist = (array) ( PDK_Settings::get( 'security', 'required_plugins' ) ?: [] );

		if ( ! $vereist ) {
			return [];
		}

		$actief = (array) get_option( 'active_plugins', [] );

		if ( is_multisite() ) {
			$actief = array_merge( $actief, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}

		$actieve_slugs = array_map( static fn( $basename ) => strtok( (string) $basename, '/' ), $actief );
		$ontbreekt     = array_values( array_diff( $vereist, $actieve_slugs ) );

		sort( $ontbreekt );

		return $ontbreekt;
	}

	/**
	 * Mailt de beheerder zodra een verplichte plugin niet meer actief is.
	 *
	 * Mailt alleen als de lijst met ontbrekende plugins verandert — anders volgt
	 * bij elke pageload dezelfde melding. Komt alles weer goed, dan wordt de
	 * stand gewist zodat een volgende uitval opnieuw gemeld wordt.
	 */
	public function check_required_plugins(): void {
		$ontbreekt = self::missing_required_plugins();
		$gemeld    = (array) get_option( self::MISSING_OPTION, [] );

		if ( $ontbreekt === $gemeld ) {
			return;
		}

		update_option( self::MISSING_OPTION, $ontbreekt, false );

		if ( ! $ontbreekt ) {
			return;
		}

		$bericht = sprintf(
			/* translators: 1: sitenaam, 2: komma-gescheiden lijst met plugin-slugs */
			__( "Op %1\$s is een plugin die actief moet blijven niet meer actief:\n\n%2\$s\n\nControleer de site en zet de plugin(s) terug aan.", 'pdk-theme-options' ),
			get_bloginfo( 'name' ),
			implode( "\n", $ontbreekt )
		);

		error_log( '[PDK Security] Verplichte plugin niet actief: ' . implode( ', ', $ontbreekt ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		wp_mail(
			(string) get_option( 'admin_email' ),
			sprintf(
				/* translators: %s: sitenaam */
				__( 'Verplichte plugin niet meer actief — %s', 'pdk-theme-options' ),
				get_bloginfo( 'name' )
			),
			$bericht
		);
	}

	/** Melding in de admin zolang er een verplichte plugin uit staat. */
	public function show_required_plugins_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ontbreekt = self::missing_required_plugins();

		if ( ! $ontbreekt ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		printf(
			/* translators: %s: komma-gescheiden lijst met plugin-slugs */
			esc_html__( 'Deze plugin(s) moeten actief blijven, maar staan uit: %s', 'pdk-theme-options' ),
			esc_html( implode( ', ', $ontbreekt ) )
		);
		echo '</p></div>';
	}

	// -------------------------------------------------------------------------
	// 5. Integriteitscontrole van mu-plugins/
	// -------------------------------------------------------------------------

	/**
	 * Mailt de beheerder zodra er een MU-plugin bijkomt, wijzigt of verdwijnt.
	 * Maximaal één keer per uur; de baseline staat in een optie, niet in
	 * mu-plugins/ zelf — daar kan een backdoor hem anders gewoon bijwerken.
	 *
	 * Eerste run legt de baseline vast zonder te mailen (trust-on-first-use),
	 * net als pdk_seed_file_hashes() voor de code-bestanden.
	 */
	public function check_mu_integrity(): void {
		$mu_dir = $this->mu_dir();

		if ( ! $mu_dir || get_transient( 'pdk_mu_integrity_checked' ) ) {
			return;
		}

		set_transient( 'pdk_mu_integrity_checked', 1, HOUR_IN_SECONDS );

		$huidig = [];
		foreach ( glob( $mu_dir . '*.php' ) ?: [] as $pad ) {
			$huidig[ basename( $pad ) ] = (string) hash_file( 'sha256', $pad );
		}

		$baseline = get_option( self::MU_HASH_OPTION );

		if ( ! is_array( $baseline ) ) {
			update_option( self::MU_HASH_OPTION, $huidig, false );
			return;
		}

		$nieuw      = array_keys( array_diff_key( $huidig, $baseline ) );
		$verwijderd = array_keys( array_diff_key( $baseline, $huidig ) );
		$gewijzigd  = array_keys(
			array_filter(
				$huidig,
				static fn( $hash, $naam ) => isset( $baseline[ $naam ] ) && ! hash_equals( (string) $baseline[ $naam ], $hash ),
				ARRAY_FILTER_USE_BOTH
			)
		);

		if ( ! $nieuw && ! $gewijzigd && ! $verwijderd ) {
			return;
		}

		// Baseline meteen bijwerken, anders volgt elk uur dezelfde mail.
		update_option( self::MU_HASH_OPTION, $huidig, false );

		$regels = [ __( 'De integriteitscontrole van mu-plugins/ geeft een afwijking:', 'pdk-theme-options' ) ];

		foreach ( [ 'Nieuw' => $nieuw, 'Gewijzigd' => $gewijzigd, 'Verwijderd' => $verwijderd ] as $label => $bestanden ) {
			if ( $bestanden ) {
				$regels[] = $label . ': ' . implode( ', ', $bestanden );
			}
		}

		$bericht = implode( "\n", $regels );
		error_log( '[PDK Security] ' . str_replace( "\n", ' | ', $bericht ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		wp_mail(
			(string) get_option( 'admin_email' ),
			sprintf(
				/* translators: %s: sitenaam */
				__( 'MU-plugins gewijzigd — %s', 'pdk-theme-options' ),
				get_bloginfo( 'name' )
			),
			$bericht
		);
	}

	/** Pad naar mu-plugins/ met slash, of '' als de map niet bestaat. */
	private function mu_dir(): string {
		if ( ! defined( 'WPMU_PLUGIN_DIR' ) || ! is_dir( WPMU_PLUGIN_DIR ) ) {
			return '';
		}

		return trailingslashit( WPMU_PLUGIN_DIR );
	}
}
