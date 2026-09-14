<?php
/**
 * IMGX — WebP/AVIF sidecar-afbeeldingen.
 *
 * De module is de originele IMGX-plugin, ongewijzigd overgenomen in
 * modules/imgx/includes/ met een eigen namespace (\IMGX\). Deze klasse is de
 * lijm: constanten, autoloader, boot en de brug naar de PDK-instellingentab.
 *
 * De IMGX-opties staan bewust in hun eigen option (`imgx_settings`) en niet in
 * pdk_theme_options — dat scheelt het herschrijven van de complete Settings
 * API-registratie van IMGX, en de opties overleven zo een import/export los
 * van de rest.
 */

defined( 'ABSPATH' ) || exit;

class PDK_ImgX {

	/** Tab-slug binnen de PDK-instellingenpagina. */
	const TAB = 'imgx';

	public function __construct( PDK_Loader $loader ) {
		if ( ! defined( 'IMGX_VERSION' ) ) {
			define( 'IMGX_VERSION', PDK_PLUGIN_VERSION );
			define( 'IMGX_FILE',    __FILE__ );
			define( 'IMGX_DIR',     __DIR__ . '/' );
			define( 'IMGX_URL',     PDK_PLUGIN_URL . 'modules/imgx/' );
		}

		spl_autoload_register( [ self::class, 'autoload' ] );

		// IMGX hangt zijn hooks op via zijn eigen composition root; die draait
		// op plugins_loaded, net als in de losse plugin.
		$loader->add_action( 'plugins_loaded', $this, 'boot' );
	}

	/** \IMGX\Picture_Renderer → includes/class-picture-renderer.php */
	public static function autoload( string $class_name ): void {
		if ( 0 !== strpos( $class_name, 'IMGX\\' ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( 'IMGX\\' ) );
		$file     = IMGX_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	public function boot(): void {
		// De losse IMGX-plugin staat nog geïnstalleerd en heeft de klassen al
		// gedefinieerd — dan draait die en doen wij niets, anders vuurt elke
		// hook dubbel.
		if ( defined( 'IMGX_MIN_WP' ) ) {
			return;
		}

		\IMGX\Plugin::instance()->boot();
	}

	/** URL van de IMGX-tab; IMGX redirect daar naartoe na een actie. */
	public static function tab_url( array $args = [] ): string {
		return add_query_arg(
			array_merge( [ 'page' => PDK_Admin::PAGE_SLUG, 'tab' => self::TAB ], $args ),
			admin_url( 'admin.php' )
		);
	}

	/** Rendert de IMGX-instellingen binnen de PDK-tab (eigen forms). */
	public static function render_inline(): void {
		$admin = \IMGX\Plugin::instance()->admin();

		if ( $admin ) {
			$admin->render_inline();
		}
	}
}
