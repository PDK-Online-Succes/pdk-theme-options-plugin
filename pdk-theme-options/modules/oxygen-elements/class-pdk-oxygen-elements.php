<?php
/**
 * Module: Oxygen Element Studio
 *
 * Registreert een eigen opslaglocatie voor Element Studio (Oxygen 6 / Breakdance),
 * zodat elementen, macro's en presets die een ontwikkelaar in de builder maakt
 * in wp-content/uploads/pdk-theme-options/oxygen-elements/ belanden — net als
 * custom-functions.php: buiten de pluginmap (overleeft een plugin-update) én
 * buiten de first-party mappen van Oxygen (overleeft een Oxygen-update).
 *
 * Oxygen zet overal WP_PLUGIN_DIR vóór het opgegeven pad en valideert niet dat
 * het binnen de pluginmap blijft, dus een relatief pad (../uploads/...) werkt
 * voor opslaan, laden en verwijderen. Oxygen laadt elke registratie zelf: op
 * breakdance_loaded prioriteit 10 globt het <locatie>/*\/*.php en include't die.
 * Deze module meldt de locatie dus aan vóór dat moment (prioriteit 9).
 *
 * Eén haak blijft over: de gegenereerde element.php meldt zichzelf aan met
 * getDirectoryPathRelativeToPluginFolder(__DIR__), en dat geeft buiten de
 * pluginmap een ABSOLUUT pad terug. Daarom herschrijft fix_element_paths() op
 * prioriteit 11 (na Oxygens scan) die paden naar het relatieve pad.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Oxygen_Elements {

	/**
	 * PHP-namespace én slug-voorvoegsel van alle elementen op deze locatie.
	 * Element Studio genereert `namespace PdkElements;` en de slug
	 * `PdkElements\<Naam>`. Mag niet OxygenElements of EssentialElements zijn.
	 */
	const NAMESPACE_ = 'PdkElements';

	const TYPES = [ 'element' => 'elements', 'macro' => 'macros', 'preset' => 'presets' ];

	/** Foutmelding als er geen relatief pad van de pluginmap naar de opslagmap bestaat. */
	public static ?string $error = null;

	public function __construct( PDK_Loader $loader ) {
		// Vóór Oxygens eigen scan op prioriteit 10. Niet oxygen_loaded: die
		// vuurt pas ná die scan en is dus altijd te laat.
		$loader->add_action( 'breakdance_loaded', $this, 'register_save_locations', 9 );
		// Ná die scan: dan staan de elementen in Oxygens lijst.
		$loader->add_action( 'breakdance_loaded', $this, 'fix_element_paths', 11 );
	}

	/** Oxygen 6 (of Breakdance) is geladen — de functie bestaat pas na breakdance_loaded-bootstrap. */
	public static function builder_active(): bool {
		return function_exists( '\Breakdance\ElementStudio\registerSaveLocation' );
	}

	public static function dir(): string {
		return PDK_STORAGE_DIR . 'oxygen-elements/';
	}

	/** Pad met één soort scheidingsteken en zonder afsluitend scheidingsteken. */
	private static function norm( string $path ): string {
		return rtrim( str_replace( [ '\\', '/' ], DIRECTORY_SEPARATOR, $path ), DIRECTORY_SEPARATOR );
	}

	private static function same( string $a, string $b ): bool {
		return DIRECTORY_SEPARATOR === '\\' ? 0 === strcasecmp( $a, $b ) : $a === $b;
	}

	/**
	 * Basis van het pad zoals Oxygen het wil: relatief aan realpath(WP_PLUGIN_DIR),
	 * met `/`, zonder voorloopslash en zonder submap (bijv. ../uploads/pdk-theme-options/oxygen-elements),
	 * plus het echte pad van de map. Eén keer per request berekend: dit loopt ook op de frontend.
	 * Null als er geen gemeenschappelijk begin is (andere schijf) of het pad niet klopt.
	 *
	 * @return array{rel:string,target:string}|null
	 */
	private static function base(): ?array {
		static $base = false;
		if ( false !== $base ) {
			return $base;
		}
		$base = null;

		// Alleen bij de eerste keer of als er mappen ontbreken (schrijft ook .htaccess).
		is_dir( self::dir() . 'presets' ) || self::ensure_dirs();

		$plugins = realpath( WP_PLUGIN_DIR );
		$target  = realpath( self::dir() );
		if ( false === $plugins || false === $target || ! is_dir( self::dir() . 'presets' ) ) {
			$missing     = false === $plugins ? WP_PLUGIN_DIR : self::dir();
			self::$error = sprintf( __( 'De map %s bestaat niet of kon niet worden aangemaakt.', 'pdk-theme-options' ), $missing );
			return null;
		}

		$from   = explode( DIRECTORY_SEPARATOR, self::norm( $plugins ) );
		$to     = explode( DIRECTORY_SEPARATOR, self::norm( $target ) );
		$common = 0;
		while ( isset( $from[ $common ], $to[ $common ] ) && self::same( $from[ $common ], $to[ $common ] ) ) {
			$common++;
		}
		if ( 0 === $common ) {
			self::$error = __( 'Er is geen relatief pad van de pluginmap naar de uploads-map (andere schijf?). De Element Studio-locatie is niet geregistreerd.', 'pdk-theme-options' );
			return null;
		}

		$rel = implode( '/', array_merge( array_fill( 0, count( $from ) - $common, '..' ), array_slice( $to, $common ) ) );

		// Rondreis: komt WP_PLUGIN_DIR + $rel wel uit op de opslagmap?
		$check = realpath( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $rel );
		if ( false === $check || ! self::same( self::norm( $check ), self::norm( $target ) ) ) {
			self::$error = __( 'Het relatieve pad komt niet uit op de opslagmap (symlink op de plugins-map?). De Element Studio-locatie is niet geregistreerd.', 'pdk-theme-options' );
			return null;
		}

		return $base = [ 'rel' => $rel, 'target' => self::norm( $target ) ];
	}

	/** Pad zoals Oxygen het wil voor één type (bijv. ../uploads/pdk-theme-options/oxygen-elements/elements), of null. */
	public static function relative_path( string $type ): ?string {
		$base = self::base();
		return null === $base ? null : $base['rel'] . '/' . self::TYPES[ $type ];
	}

	public function register_save_locations(): void {
		foreach ( self::TYPES as $type => $_ ) {
			$path = self::relative_path( $type );
			if ( null === $path ) {
				return; // self::$error is door base() gezet.
			}

			\Breakdance\ElementStudio\registerSaveLocation( $path, self::NAMESPACE_, $type, 'PDK Elementen', false );
		}
	}

	/**
	 * Zet het absolute pad dat element.php zelf registreert om naar het relatieve
	 * pad; anders plakt Oxygen bij "Open element → Save" WP_PLUGIN_DIR ervoor.
	 *
	 * ponytail: leunt op ElementStudioController::$elements, publiek in Oxygen 6.2.
	 * Wordt dat private, dan terug naar een locatie in de pluginmap.
	 */
	public function fix_element_paths(): void {
		$class = '\Breakdance\ElementStudio\ElementStudioController';
		$base  = self::base();
		if ( ! class_exists( $class ) || null === $base ) {
			return;
		}

		$rel  = $base['rel'] . '/elements';
		$root = $base['target'] . DIRECTORY_SEPARATOR . 'elements' . DIRECTORY_SEPARATOR;

		foreach ( $class::getInstance()->elements as &$element ) {
			$path = self::norm( $element['directoryPath'] ?? '' );
			if ( self::same( substr( $path, 0, strlen( $root ) ), $root ) ) {
				$element['directoryPath'] = $rel . '/' . str_replace( DIRECTORY_SEPARATOR, '/', substr( $path, strlen( $root ) ) );
			}
		}
	}

	/** De vier bestanden van een element die de tab laat bewerken. */
	const ELEMENT_FILES = [ 'element.php', 'html.twig', 'css.twig', 'default.css' ];

	/**
	 * Ontleedt een ?edit=-waarde op syntax, zonder te kijken of het bestand bestaat.
	 * Beveiligingsgrens: alleen deze vaste vormen en een naam van [A-Za-z0-9_-].
	 *
	 * @return array{type:string,name:string,file:?string,rel:string}|null
	 */
	public static function parse( string $edit ): ?array {
		$rel = 'oxygen-elements/' . $edit;
		if ( preg_match( '#^elements/([A-Za-z0-9_-]+)/([a-z.]+)$#D', $edit, $m ) ) {
			return in_array( $m[2], self::ELEMENT_FILES, true )
				? [ 'type' => 'element', 'name' => $m[1], 'file' => $m[2], 'rel' => $rel ]
				: null;
		}
		if ( preg_match( '#^macros/([A-Za-z0-9_-]+)\.twig$#D', $edit, $m ) ) {
			return [ 'type' => 'macro', 'name' => $m[1], 'file' => null, 'rel' => $rel ];
		}
		if ( preg_match( '#^presets/([A-Za-z0-9_-]+)\.php$#D', $edit, $m ) ) {
			return [ 'type' => 'preset', 'name' => $m[1], 'file' => null, 'rel' => $rel ];
		}
		return null;
	}

	/** Pad relatief aan PDK_STORAGE_DIR (`oxygen-elements/...`) van een geldig én bestaand bestand, of null. */
	public static function file_rel_path( string $edit ): ?string {
		$p = self::parse( $edit );
		return $p && is_file( self::dir() . $edit ) ? $p['rel'] : null;
	}

	/** Maakt de drie submappen aan; Element Studio doet dat anders pas bij de eerste save. */
	public static function ensure_dirs(): bool {
		// Schrijft ook .htaccess en index.php in de opslagmap.
		function_exists( 'pdk_ensure_storage_dir' ) && pdk_ensure_storage_dir();

		$ok = true;
		foreach ( self::TYPES as $sub ) {
			$ok = wp_mkdir_p( self::dir() . $sub ) && $ok;
		}
		return $ok;
	}

	/**
	 * Wat er op schijf staat, per type. Elementen zijn mappen met een element.php,
	 * macro's .twig-bestanden, presets .php-bestanden — precies wat Oxygen globt.
	 *
	 * @return array<string,string[]> type => namen (zonder pad/extensie).
	 */
	public static function scan(): array {
		$found = [];

		foreach ( self::TYPES as $type => $sub ) {
			$pattern = match ( $type ) {
				'element' => '*/element.php',
				'macro'   => '*.twig',
				default   => '*.php',
			};

			$names = [];
			foreach ( glob( self::dir() . $sub . '/' . $pattern ) ?: [] as $path ) {
				$names[] = 'element' === $type
					? basename( dirname( $path ) )
					: pathinfo( $path, PATHINFO_FILENAME );
			}
			sort( $names );
			$found[ $type ] = $names;
		}

		return $found;
	}

	/**
	 * Slug van een element zoals Oxygen hem kent: de volledige klassenaam
	 * (`slug()` in element.php geeft __CLASS__ terug). Gelezen uit het bestand
	 * zelf, zodat hij ook klopt als iemand de map hernoemt.
	 */
	public static function slug( string $dirname ): string {
		$php = (string) @file_get_contents( self::dir() . 'elements/' . $dirname . '/element.php' );
		preg_match( '/^namespace\s+([\w\\\\]+)\s*;/m', $php, $ns );
		preg_match( '/^class\s+(\w+)/m', $php, $cls );

		return isset( $cls[1] ) ? ( $ns[1] ?? self::NAMESPACE_ ) . '\\' . $cls[1] : '';
	}
}
