<?php
/**
 * Zelftest voor de Element Studio-module (PDK_Oxygen_Elements).
 * Draaien met:
 *   php tests/test-oxygen-elements.php
 */

// Stub van Oxygen: registreert wat de module aanmeldt. Moet in een eigen
// namespace-blok, dus staat de rest van het bestand in het globale blok.
namespace Breakdance\ElementStudio {
	function registerSaveLocation( $dir, $ns, $type, $label, $advanced, $exclude = false ) {
		$GLOBALS['locations'][] = compact( 'dir', 'ns', 'type', 'label', 'advanced' );
	}

	// Stub van Oxygens controller: elementen melden zich aan in de publieke $elements.
	class ElementStudioController {
		public array $elements = [];
		public static function getInstance(): self {
			static $i;
			return $i ??= new self();
		}
	}
}

namespace {
	if ( PHP_SAPI !== 'cli' ) {
		exit;
	}

	$pdk = dirname( __DIR__ ) . '/pdk-theme-options';

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'WP_PLUGIN_DIR', sys_get_temp_dir() . '/pdk-oxel-test-' . getmypid() . '/plugins' );

	define( 'PDK_STORAGE_DIR', dirname( WP_PLUGIN_DIR ) . '/uploads/pdk-theme-options/' );

	mkdir( WP_PLUGIN_DIR, 0777, true );
	$GLOBALS['locations'] = [];

	function wp_mkdir_p( string $dir ): bool {
		return is_dir( $dir ) || mkdir( $dir, 0777, true );
	}

	function pdk_ensure_storage_dir(): bool {
		return true;
	}

	class PDK_Loader {
		public array $hooks = [];
		public function add_action( string $hook, $obj, string $method, int $prio = 10, int $args = 1 ): void {
			$this->hooks[] = [ $hook, $method, $prio ];
		}
	}

	require_once $pdk . '/modules/oxygen-elements/class-pdk-oxygen-elements.php';

	$fouten = 0;
	function check( string $naam, bool $ok ): void {
		global $fouten;
		if ( ! $ok ) {
			$fouten++;
		}
		echo ( $ok ? '  ok   ' : '  FOUT ' ) . $naam . "\n";
	}

	echo "Registratie\n";
	$loader = new PDK_Loader();
	$module = new PDK_Oxygen_Elements( $loader );
	check( 'haakt op breakdance_loaded vóór Oxygens scan (prio 9)', [ 'breakdance_loaded', 'register_save_locations', 9 ] === $loader->hooks[0] );
	check( 'haakt op breakdance_loaded ná Oxygens scan (prio 11) voor het padherstel', [ 'breakdance_loaded', 'fix_element_paths', 11 ] === $loader->hooks[1] );
	check( 'builder_active() ziet de Oxygen-functie', PDK_Oxygen_Elements::builder_active() );

	$module->register_save_locations();
	$types = array_column( $GLOBALS['locations'], 'type' );
	sort( $types );
	check( 'drie locaties: element, macro, preset', [ 'element', 'macro', 'preset' ] === $types );
	check( 'pad relatief aan WP_PLUGIN_DIR, naar uploads, zonder voorloopslash', '../uploads/pdk-theme-options/oxygen-elements/elements' === $GLOBALS['locations'][0]['dir'] );
	check( 'namespace is niet die van Oxygen', ! in_array( $GLOBALS['locations'][0]['ns'], [ 'OxygenElements', 'EssentialElements' ], true ) );
	check( 'niet alleen voor "advanced users"', false === $GLOBALS['locations'][0]['advanced'] );
	check( 'submappen aangemaakt', is_dir( PDK_STORAGE_DIR . 'oxygen-elements/elements' ) && is_dir( PDK_STORAGE_DIR . 'oxygen-elements/macros' ) && is_dir( PDK_STORAGE_DIR . 'oxygen-elements/presets' ) );

	// Oxygen globt WP_PLUGIN_DIR . '/' . <locatie> . '/*/*.php' — dat moet op dezelfde map uitkomen als dir().
	check( 'Oxygens glob vindt de map via het opgegeven pad', realpath( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $GLOBALS['locations'][0]['dir'] ) === realpath( PDK_Oxygen_Elements::dir() . 'elements' ) );

	check( 'relative_path() geeft hetzelfde pad als de registratie', '../uploads/pdk-theme-options/oxygen-elements/elements' === PDK_Oxygen_Elements::relative_path( 'element' ) );
	check( 'geen fout gemeld', null === PDK_Oxygen_Elements::$error );

	echo "\nScannen\n";
	$d = PDK_Oxygen_Elements::dir();
	mkdir( $d . 'elements/Docs_Demo_Card' );
	file_put_contents( $d . 'elements/Docs_Demo_Card/element.php', "<?php\n\nnamespace PdkElements;\n\nclass DocsDemoCard extends \\Breakdance\\Elements\\Element {}\n" );
	mkdir( $d . 'elements/Leeg' ); // map zonder element.php telt niet
	file_put_contents( $d . 'macros/buttons.twig', '' );
	file_put_contents( $d . 'presets/Spacing.php', '<?php' );

	$found = PDK_Oxygen_Elements::scan();
	check( 'element gevonden, lege map genegeerd', [ 'Docs_Demo_Card' ] === $found['element'] );
	check( 'macro gevonden', [ 'buttons' ] === $found['macro'] );
	check( 'preset gevonden', [ 'Spacing' ] === $found['preset'] );
	check( 'slug uit element.php gelezen', 'PdkElements\\DocsDemoCard' === PDK_Oxygen_Elements::slug( 'Docs_Demo_Card' ) );
	check( 'slug van ontbrekend element is leeg', '' === PDK_Oxygen_Elements::slug( 'Bestaat_Niet' ) );

	echo "\nBestandspaden (file_rel_path)\n";
	file_put_contents( $d . 'elements/Docs_Demo_Card/html.twig', '<div></div>' );
	check( 'geldig elementbestand', 'oxygen-elements/elements/Docs_Demo_Card/html.twig' === PDK_Oxygen_Elements::file_rel_path( 'elements/Docs_Demo_Card/html.twig' ) );
	check( 'element.php toegestaan', 'oxygen-elements/elements/Docs_Demo_Card/element.php' === PDK_Oxygen_Elements::file_rel_path( 'elements/Docs_Demo_Card/element.php' ) );
	check( '.. geweigerd', null === PDK_Oxygen_Elements::file_rel_path( 'elements/../../x/html.twig' ) && null === PDK_Oxygen_Elements::file_rel_path( 'elements/Docs_Demo_Card/../element.php' ) );
	check( 'backslash geweigerd', null === PDK_Oxygen_Elements::file_rel_path( 'elements\Docs_Demo_Card\html.twig' ) );
	check( 'onbekende bestandsnaam geweigerd', null === PDK_Oxygen_Elements::file_rel_path( 'elements/Docs_Demo_Card/evil.php' ) );
	check( 'niet-bestaand bestand geweigerd', null === PDK_Oxygen_Elements::file_rel_path( 'elements/Docs_Demo_Card/css.twig' ) );
	check( 'newline na naam geweigerd', null === PDK_Oxygen_Elements::file_rel_path( "elements/Docs_Demo_Card/html.twig\n" ) );
	check( 'macro toegestaan', 'oxygen-elements/macros/buttons.twig' === PDK_Oxygen_Elements::file_rel_path( 'macros/buttons.twig' ) );
	check( 'preset toegestaan', 'oxygen-elements/presets/Spacing.php' === PDK_Oxygen_Elements::file_rel_path( 'presets/Spacing.php' ) );
	check( 'macro met .php geweigerd', null === PDK_Oxygen_Elements::file_rel_path( 'macros/buttons.php' ) );

	check( 'parse() geeft type, naam en bestand zonder bestaanscheck', [ 'type' => 'element', 'name' => 'Nieuw', 'file' => 'html.twig', 'rel' => 'oxygen-elements/elements/Nieuw/html.twig' ] === PDK_Oxygen_Elements::parse( 'elements/Nieuw/html.twig' ) && 'macro' === PDK_Oxygen_Elements::parse( 'macros/x.twig' )['type'] );
	check( 'parse() weigert onbekend bestand en ..', null === PDK_Oxygen_Elements::parse( 'elements/Nieuw/evil.php' ) && null === PDK_Oxygen_Elements::parse( 'elements/../x/html.twig' ) );

	echo "
Padherstel (prio 11)
";
	$rel  = PDK_Oxygen_Elements::relative_path( 'element' );
	$abs  = str_replace( '/', DIRECTORY_SEPARATOR, realpath( $d . 'elements/Docs_Demo_Card' ) );
	$buit = '/elders/plugins/ander-element';
	$ctl  = \Breakdance\ElementStudio\ElementStudioController::getInstance();
	$ctl->elements = [
		[ 'slug' => 'PdkElements\DocsDemoCard', 'directoryPath' => $abs ],
		[ 'slug' => 'Ander', 'directoryPath' => $buit ],
	];
	$module->fix_element_paths();
	check( 'absoluut pad van ons element wordt relatief', $rel . '/Docs_Demo_Card' === $ctl->elements[0]['directoryPath'] );
	check( 'herschreven pad komt via WP_PLUGIN_DIR op de elementmap uit', realpath( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $ctl->elements[0]['directoryPath'] ) === realpath( $d . 'elements/Docs_Demo_Card' ) );
	check( 'element buiten onze map blijft ongemoeid', $buit === $ctl->elements[1]['directoryPath'] );

	// Opruimen.
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( WP_PLUGIN_DIR ), FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $f ) {
		$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
	}
	rmdir( dirname( WP_PLUGIN_DIR ) );

	echo "\n" . ( $fouten ? "{$fouten} test(s) gefaald\n" : "Alle tests geslaagd\n" );
	exit( $fouten ? 1 : 0 );
}
