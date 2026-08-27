<?php
/**
 * Module: Libraries
 *
 * Laadt losse JS- en CSS-bestanden uit uploads/pdk-theme-options/libraries/ op
 * de frontend — bedoeld voor kant-en-klare bibliotheken als Glide.js, Swiper of
 * Splide. Uploaden en aan/uit zetten gebeurt op de Libraries-tab.
 *
 * Alles staat buiten de pluginmap, dus updates raken de bestanden niet.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Libraries {

	public function __construct( PDK_Loader $loader ) {
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue' );
	}

	public static function allowed_extensions(): array {
		return [ 'js', 'css' ];
	}

	public static function dir(): string {
		return PDK_STORAGE_DIR . PDK_LIBRARY_SUBDIR;
	}

	public static function url(): string {
		return PDK_STORAGE_URL . PDK_LIBRARY_SUBDIR;
	}

	/** Pad relatief aan PDK_STORAGE_DIR — de sleutel van de integriteitscontrole. */
	public static function rel_path( string $bestand ): string {
		return PDK_LIBRARY_SUBDIR . $bestand;
	}

	/**
	 * Alle geüploade bestanden, alfabetisch.
	 *
	 * De volgorde van laden is de volgorde van deze lijst: zet een cijfer voor
	 * de bestandsnaam (10-swiper.min.js, 20-slider-init.js) als het uitmaakt.
	 *
	 * @return string[] Bestandsnamen, zonder pad.
	 */
	public static function scan(): array {
		$bestanden = [];

		foreach ( self::allowed_extensions() as $ext ) {
			foreach ( glob( self::dir() . '*.' . $ext ) ?: [] as $pad ) {
				$bestanden[] = basename( $pad );
			}
		}

		sort( $bestanden );

		return $bestanden;
	}

	/**
	 * Uitgezette bestanden blijven staan, maar worden niet ingeladen.
	 * Alles wat niet expliciet uit staat, laadt — dus een nieuwe upload werkt meteen.
	 */
	public static function disabled(): array {
		return array_map( 'strval', (array) ( PDK_Settings::get( 'libraries', 'disabled' ) ?: [] ) );
	}

	public static function is_enabled( string $bestand ): bool {
		return ! in_array( $bestand, self::disabled(), true );
	}

	/** Handle waarmee WordPress het bestand registreert. */
	public static function handle( string $bestand ): string {
		return 'pdk-lib-' . sanitize_title( pathinfo( $bestand, PATHINFO_FILENAME ) );
	}

	public function enqueue(): void {
		foreach ( self::scan() as $bestand ) {
			if ( ! self::is_enabled( $bestand ) ) {
				continue;
			}

			$pad = self::dir() . $bestand;

			if ( ! is_readable( $pad ) || 0 === filesize( $pad ) ) {
				continue;
			}

			// Buiten de editor om gewijzigd → niet uitserveren, net als bij
			// custom-script.js. Zie pdk_file_is_tampered().
			if ( pdk_file_is_tampered( self::rel_path( $bestand ) ) ) {
				continue;
			}

			$handle = self::handle( $bestand );
			$versie = (string) filemtime( $pad ); // Cache-busting bij een nieuwe upload.

			if ( 'css' === strtolower( pathinfo( $bestand, PATHINFO_EXTENSION ) ) ) {
				wp_enqueue_style( $handle, self::url() . $bestand, [], $versie );
				continue;
			}

			wp_enqueue_script( $handle, self::url() . $bestand, [], $versie, true );
		}
	}
}
