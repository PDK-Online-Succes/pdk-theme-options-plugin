<?php
/**
 * Module: Afbeeldingsmaten
 *
 * Toont de geregistreerde WordPress-afbeeldingsmaten (`thumbnail`, `medium`, ...),
 * laat een beheerder maten aan/uit zetten en eigen maten (voorvoegsel `pdk_`)
 * aanmaken en weer verwijderen.
 *
 * Uitzetten is een blocklist, geen allowlist: een maat die nergens in
 * `image_sizes.disabled` voorkomt is actief, ook als hij pas later door een
 * thema of plugin wordt geregistreerd. Zelfde patroon als
 * PDK_Libraries::disabled(). `thumbnail` is vergrendeld — wp-admin en vrijwel
 * elk thema leunen daarop.
 *
 * Uitzetten verwijdert niets van schijf en stopt alleen de generatie voor
 * NIEUWE uploads (intermediate_image_sizes_advanced). Bestaande bestanden en
 * bestaande metadata blijven ongemoeid.
 *
 * Hergeneratie in batches (FR-006) staat in een eigen klasse
 * (includes/class-image-sizes-batch.php), zelfde patroon als IMGX'
 * Batch_Processor. De samenwerking met IMGX-sidecars (FR-010) vergt geen
 * eigen code: hergeneratie slaat metadata op via wp_update_attachment_metadata(),
 * waar IMGX zelf aan hangt als die module actief is — geen aanroep van
 * IMGX-code hier (B1).
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-image-sizes-batch.php';

class PDK_Image_Sizes {

	/** Maat die nooit uitgeschakeld kan worden. */
	const LOCKED_SIZE = 'thumbnail';


	public function __construct( PDK_Loader $loader ) {
		// Prioriteit 20: laat thema's en andere plugins hun add_image_size()
		// eerst draaien op de gebruikelijke 'init'/'after_setup_theme' momenten.
		$loader->add_action( 'init', $this, 'register_custom_sizes', 20 );
		$loader->add_filter( 'intermediate_image_sizes_advanced', $this, 'filter_generation' );
		$loader->add_filter( 'image_size_names_choose', $this, 'filter_size_choices' );

		new PDK_Image_Sizes_Batch( $loader );
	}

	// -------------------------------------------------------------------------
	// Opgeslagen instellingen
	// -------------------------------------------------------------------------

	/**
	 * Uitgeschakelde maatsleutels. Blocklist: alles wat hier niet in staat is
	 * actief, ook een sleutel die nog nooit is opgeslagen.
	 *
	 * @return string[]
	 */
	public static function disabled(): array {
		return array_map( 'strval', (array) ( PDK_Settings::get( 'image_sizes', 'disabled' ) ?: [] ) );
	}

	/**
	 * Eigen maten: sleutel (altijd `pdk_`-voorvoegsel) => ['width'=>int,'height'=>int,'crop'=>bool].
	 *
	 * @return array<string,array{width:int,height:int,crop:bool}>
	 */
	public static function custom(): array {
		return (array) ( PDK_Settings::get( 'image_sizes', 'custom' ) ?: [] );
	}

	/** Alle door WordPress geregistreerde maten, kern + eigen + thema. */
	public static function registered(): array {
		return function_exists( 'wp_get_registered_image_subsizes' ) ? wp_get_registered_image_subsizes() : [];
	}

	public static function is_locked( string $key ): bool {
		return self::LOCKED_SIZE === $key;
	}

	public static function is_custom( string $key ): bool {
		return 0 === strpos( $key, 'pdk_' );
	}

	/** `thumbnail` is altijd actief; voor de rest geldt de blocklist. */
	public static function is_enabled( string $key ): bool {
		if ( self::is_locked( $key ) ) {
			return true;
		}

		return ! in_array( $key, self::disabled(), true );
	}

	/**
	 * Saneert een lijst geposte maatsleutels tot een veilige blocklist:
	 * bekende sleutelvorm, geen duplicaten, `thumbnail` er altijd uit
	 * (AC-007) — ook als een geprepareerde POST hem erin zet.
	 *
	 * @param mixed[] $keys
	 * @return string[]
	 */
	public static function sanitize_disabled_list( array $keys ): array {
		$clean = array_map( 'sanitize_key', $keys );
		$clean = array_unique( array_filter( $clean, 'strlen' ) );

		return array_values( array_diff( $clean, [ self::LOCKED_SIZE ] ) );
	}

	/** Sleutel die overal (kern + eigen) al bestaat — voor de collision-check bij aanmaken. */
	public static function key_exists( string $key ): bool {
		return array_key_exists( $key, self::registered() ) || array_key_exists( $key, self::custom() );
	}

	/**
	 * Botsingscontrole voor het aanmaken van een eigen maat (AC-009, D-1-fix).
	 * Een core-maatnaam als "large" moet net zo goed worden geweigerd als een
	 * botsende eigen maat — dus zowel de kale ingevoerde naam als de
	 * voorvoegde sleutel worden gecontroleerd, niet alleen de laatste.
	 *
	 * @return string De botsende sleutel, of '' als er geen botsing is.
	 */
	public static function colliding_key( string $slug, string $prefixed_key ): string {
		if ( '' !== $slug && self::key_exists( $slug ) ) {
			return $slug;
		}

		if ( self::key_exists( $prefixed_key ) ) {
			return $prefixed_key;
		}

		return '';
	}

	/** `pdk_<gesaneerde-naam>`, of lege string als er na sanering niets overblijft. */
	public static function generate_key( string $name ): string {
		$slug = sanitize_key( $name );

		return '' === $slug ? '' : 'pdk_' . $slug;
	}

	/** Minstens één van breedte/hoogte moet een positief getal zijn (0 = proportioneel, net als medium_large). */
	public static function has_valid_dimensions( int $width, int $height ): bool {
		return $width > 0 || $height > 0;
	}

	// -------------------------------------------------------------------------
	// Hooks
	// -------------------------------------------------------------------------

	/**
	 * Registreert elke eigen maat via add_image_size() — ongeacht of hij aan
	 * of uit staat, net als WordPress zijn eigen kernmaten altijd registreert.
	 * Of hij ook daadwerkelijk gegenereerd wordt, bepaalt filter_generation().
	 */
	public function register_custom_sizes(): void {
		foreach ( self::custom() as $key => $def ) {
			if ( ! self::is_custom( $key ) ) {
				continue; // Defensief: alleen eigen sleutels registreren wij hier.
			}

			add_image_size(
				$key,
				(int) ( $def['width'] ?? 0 ),
				(int) ( $def['height'] ?? 0 ),
				! empty( $def['crop'] )
			);
		}
	}

	/**
	 * Haalt uitgeschakelde maten uit de generatielijst vóórdat WordPress ze
	 * voor een nieuwe upload aanmaakt (FR-007). De blocklist wint altijd,
	 * ook als de maat hier pas net (opnieuw) in staat (FR-002/AC-028).
	 *
	 * @param array $sizes Maatnaam => afmetingsdefinitie.
	 * @return array
	 */
	public function filter_generation( array $sizes ): array {
		foreach ( array_keys( $sizes ) as $key ) {
			if ( ! self::is_enabled( $key ) ) {
				unset( $sizes[ $key ] );
			}
		}

		return $sizes;
	}

	/**
	 * Eigen maten aanbieden in de maatkiezer van de blok-editor (B15/AC-027).
	 *
	 * Bewust additief: alleen de eigen pdk_-maten komen erbij, de rest van
	 * $sizes blijft zoals hij binnenkwam. Een eerdere versie bouwde de lijst
	 * opnieuw op vanuit een vaste set van vier, wat keuzes weggooide die een
	 * ander filter met lagere prioriteit had toegevoegd. Dat is gemeten: een
	 * testfilter op prioriteit 5 verdween uit het resultaat.
	 *
	 * Maten die hier verder in staan — medium_large, 1536x1536, 2048x2048 —
	 * komen van Oxygen/Breakdance, dat zelf aan deze hook hangt. Niet aan ons
	 * om die van een ander plugin te verwijderen.
	 */
	public function filter_size_choices( array $sizes ): array {
		foreach ( array_keys( self::custom() ) as $key ) {
			$sizes[ $key ] = $sizes[ $key ] ?? $key;
		}

		return $sizes;
	}
}
