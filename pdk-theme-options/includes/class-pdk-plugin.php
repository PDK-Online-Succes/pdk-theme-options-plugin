<?php
/**
 * Hoofd-orchestrator van de plugin.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Plugin {

	private static ?PDK_Plugin $instance = null;
	private PDK_Loader $loader;

	/**
	 * Modules met een toggle. Critical Error Status staat hier NIET in —
	 * die wordt altijd geladen, ongeacht instellingen.
	 */
	private array $module_map = [
		'custom_functions' => [ 'file' => 'modules/custom-functions/class-pdk-custom-functions.php', 'class' => 'PDK_Custom_Functions' ],
		'custom_css'       => [ 'file' => 'modules/custom-css/class-pdk-custom-css.php',             'class' => 'PDK_Custom_CSS' ],
		'custom_js'        => [ 'file' => 'modules/custom-js/class-pdk-custom-js.php',               'class' => 'PDK_Custom_JS' ],
		'custom_fonts'     => [ 'file' => 'modules/custom-fonts/class-pdk-custom-fonts.php',         'class' => 'PDK_Custom_Fonts' ],
		'login_page'       => [ 'file' => 'modules/login-page/class-pdk-login-page.php',             'class' => 'PDK_Login_Page' ],
		'vacation_mode'    => [ 'file' => 'modules/vacation-mode/class-pdk-vacation-mode.php',       'class' => 'PDK_Vacation_Mode' ],
		'delivery_time'    => [ 'file' => 'modules/delivery-time/class-pdk-delivery-time.php',       'class' => 'PDK_Delivery_Time' ],
		'sku_restriction'  => [ 'file' => 'modules/sku-restriction/class-pdk-sku-restriction.php',   'class' => 'PDK_SKU_Restriction' ],
		'language_checker' => [ 'file' => 'modules/language-checker/class-pdk-language-checker.php', 'class' => 'PDK_Language_Checker' ],
		'agent_abilities'  => [ 'file' => 'modules/agent-abilities/class-pdk-agent-abilities.php',   'class' => 'PDK_Agent_Abilities' ],
	];

	private function __construct() {
		$this->loader = new PDK_Loader();
		$this->init();
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function init(): void {
		// Security: eerst, want de header-firewall moet vóór al het andere lopen.
		require_once PDK_PLUGIN_DIR . 'modules/security/class-pdk-security.php';
		new PDK_Security( $this->loader );

		// Kritieke module: altijd laden, geen afhankelijkheid van instellingen.
		require_once PDK_PLUGIN_DIR . 'modules/critical-error-status/class-pdk-critical-error.php';
		new PDK_Critical_Error_Status( $this->loader );

		// Site-instellingen (favicon, klantgegevens) — altijd geladen.
		require_once PDK_PLUGIN_DIR . 'modules/site-settings/class-pdk-site-settings.php';
		new PDK_Site_Settings( $this->loader );

		// Tekst-domein.
		$this->loader->add_action( 'plugins_loaded', $this, 'load_textdomain' );

		// WooCommerce HPOS-compatibiliteit.
		$this->loader->add_action( 'before_woocommerce_init', $this, 'declare_wc_compat' );

		// Admin-pagina.
		$admin = new PDK_Admin( $this->loader );
		$admin->register_hooks();

		// GitHub-updater: alleen in reguliere plugin-modus (niet in MU — WP trackt MU niet).
		if ( ! PDK_IS_MU_PLUGIN ) {
			$updater = new PDK_Updater( PDK_GITHUB_REPO, PDK_PLUGIN_FILE, PDK_PLUGIN_VERSION );
			$updater->register( $this->loader );
		}

		// Eerste-keer initialisatie — werkt ook als MU-plugin (geen activatie-hook).
		$this->loader->add_action( 'admin_init',    $this, 'maybe_first_run' );

		// Baseline voor de integriteitscontrole op bestaande installaties.
		add_action( 'admin_init', 'pdk_seed_file_hashes' );
		$this->loader->add_action( 'admin_notices', $this, 'show_first_run_notice' );

		// Optionele modules — alleen laden wanneer ingeschakeld.
		$this->load_modules();

		$this->loader->run();
	}

	private function load_modules(): void {
		foreach ( $this->module_map as $module_key => $config ) {
			// is_module_enabled() geeft false zodra een afhankelijkheid (WooCommerce)
			// ontbreekt — zulke modules worden dus nooit ingeladen.
			if ( ! PDK_Settings::is_module_enabled( $module_key ) ) {
				continue;
			}

			require_once PDK_PLUGIN_DIR . $config['file'];
			new $config['class']( $this->loader );
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'pdk-theme-options',
			false,
			dirname( plugin_basename( PDK_PLUGIN_FILE ) ) . '/languages'
		);
	}

	public function declare_wc_compat(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				PDK_PLUGIN_FILE,
				true
			);
		}
	}

	public static function activate(): void {
		pdk_ensure_storage_dir();

		pdk_maybe_create_storage_file(
			'custom-functions.php',
			"<?php\n/**\n * Eigen PHP-functies.\n * Dit bestand wordt NIET overschreven bij plugin-updates.\n */\n\ndefined( 'ABSPATH' ) || exit;\n"
		);
		pdk_maybe_create_storage_file(
			'custom-style.css',
			"/*\n * Eigen CSS-stijlen.\n * Dit bestand wordt NIET overschreven bij plugin-updates.\n */\n"
		);
		pdk_maybe_create_storage_file(
			'custom-script.js',
			"/*\n * Eigen JavaScript.\n * Dit bestand wordt NIET overschreven bij plugin-updates.\n */\n"
		);

		if ( ! get_option( PDK_Settings::OPTION_KEY ) ) {
			add_option( PDK_Settings::OPTION_KEY, PDK_Settings::get_defaults() );
		}

		// Geef de code-editor-capability ALLEEN aan de gebruiker die de plugin
		// activeert — niet aan alle beheerders. Extra gebruikers worden via de
		// Rechten-tab aangewezen.
		$activating_user_id = get_current_user_id();
		if ( $activating_user_id ) {
			$user = get_userdata( $activating_user_id );
			if ( $user ) {
				$user->add_cap( PDK_CAP_EDIT_CODE );
			}
		}

		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Initialiseert de plugin bij eerste gebruik — ook als MU-plugin, waarbij
	 * de activatie-hook nooit vuurt. Draait op admin_init (eenmalig).
	 */
	public function maybe_first_run(): void {
		// Al eerder geïnitialiseerd.
		if ( get_option( PDK_Settings::OPTION_KEY ) !== false ) {
			return;
		}

		// Maak storage-bestanden aan en sla standaardopties op.
		pdk_ensure_storage_dir();

		pdk_maybe_create_storage_file(
			'custom-functions.php',
			"<?php\n/**\n * Eigen PHP-functies.\n * Dit bestand wordt NIET overschreven bij plugin-updates.\n */\n\ndefined( 'ABSPATH' ) || exit;\n"
		);
		pdk_maybe_create_storage_file(
			'custom-style.css',
			"/*\n * Eigen CSS-stijlen.\n * Dit bestand wordt NIET overschreven bij plugin-updates.\n */\n"
		);
		pdk_maybe_create_storage_file(
			'custom-script.js',
			"/*\n * Eigen JavaScript.\n * Dit bestand wordt NIET overschreven bij plugin-updates.\n */\n"
		);

		add_option( PDK_Settings::OPTION_KEY, PDK_Settings::get_defaults() );

		// Geef de code-editor-capability aan de huidige ingelogde beheerder.
		// In MU-modus is dit de eerste beheerder die de admin bezoekt na installatie.
		$uid = get_current_user_id();
		if ( $uid && user_can( $uid, 'manage_options' ) ) {
			$user = get_userdata( $uid );
			if ( $user ) {
				$user->add_cap( PDK_CAP_EDIT_CODE );
			}
		}

		// Toon eenmalig een welkomstmelding met link naar de Rechten-tab.
		set_transient( 'pdk_first_run_notice', true, HOUR_IN_SECONDS );

		flush_rewrite_rules();
	}

	public function show_first_run_notice(): void {
		if ( ! get_transient( 'pdk_first_run_notice' ) ) {
			return;
		}

		delete_transient( 'pdk_first_run_notice' );

		$rechten_url = add_query_arg(
			[ 'page' => PDK_Admin::PAGE_SLUG, 'tab' => 'permissions' ],
			admin_url( 'admin.php' )
		);

		echo '<div class="notice notice-success is-dismissible"><p>';
		printf(
			/* translators: %s: URL naar de Rechten-tab */
			esc_html__( 'PDK Theme Options is geïnstalleerd. Wijs code-editor rechten toe via de %s.', 'pdk-theme-options' ),
			'<a href="' . esc_url( $rechten_url ) . '">' . esc_html__( 'Rechten-tab', 'pdk-theme-options' ) . '</a>'
		);
		echo '</p></div>';
	}
}
