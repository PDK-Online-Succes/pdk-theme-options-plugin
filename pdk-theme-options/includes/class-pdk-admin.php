<?php
/**
 * Admin-instellingenpagina voor PDK Theme Options.
 *
 * Tabs die altijd zichtbaar zijn:
 *  - Modules         (overzicht + toggles)
 *  - Site Instellingen (favicon, klantgegevens — Carbon Fields migratie)
 *  - Rechten         (wie mag code-bestanden bewerken)
 *
 * Module-specifieke tabs (alleen zichtbaar als de module actief is):
 *  - PHP Functions, Custom CSS, Custom JS, Fonts, Vakantiemodus, Taalcontrole
 *
 * Login Pagina heeft geen instellingentab — vaste PDK-stijl.
 * Critical Error Status heeft geen toggle — altijd actief.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Admin {

	private PDK_Loader $loader;
	const PAGE_SLUG    = 'pdk-theme-options';
	const NONCE_ACTION = 'pdk_save_settings';

	/** Module-sleutels die een eigen tab krijgen (alleen als ingeschakeld). */
	private const MODULE_TABS = [
		'custom_functions' => 'PHP Functions',
		'custom_css'       => 'Custom CSS',
		'custom_js'        => 'Custom JS',
		'custom_fonts'     => 'Fonts',
		'vacation_mode'    => 'Vakantiemodus',
		'delivery_time'    => 'Levertijden',
		'language_checker' => 'Taalcontrole',
	];

	/** Tabs met een code-editor → het bestand in de storage-map. */
	private const CODE_TABS = [
		'custom_functions' => 'custom-functions.php',
		'custom_css'       => 'custom-style.css',
		'custom_js'        => 'custom-script.js',
	];

	public function __construct( PDK_Loader $loader ) {
		$this->loader = $loader;
	}

	public function register_hooks(): void {
		$this->loader->add_action( 'admin_menu',                         $this, 'add_menu_page' );
		$this->loader->add_action( 'admin_enqueue_scripts',              $this, 'enqueue_assets' );
		$this->loader->add_action( 'admin_post_pdk_save_settings',       $this, 'handle_save' );
		$this->loader->add_action( 'admin_post_pdk_save_fonts_display',  $this, 'handle_save_fonts_display' );
		$this->loader->add_action( 'admin_post_pdk_font_upload',         $this, 'handle_font_upload' );
		$this->loader->add_action( 'admin_post_pdk_library_upload',      $this, 'handle_library_upload' );
		$this->loader->add_action( 'admin_post_pdk_font_delete',         $this, 'handle_font_delete' );
		$this->loader->add_action( 'admin_notices',                      $this, 'show_integrity_notice' );
		$this->loader->add_action( 'admin_post_pdk_file_integrity',      $this, 'handle_integrity_action' );
	}

	// -------------------------------------------------------------------------
	// Integriteit van de code-bestanden
	// -------------------------------------------------------------------------

	/**
	 * Waarschuwt zodra een code-bestand buiten de editor om is gewijzigd.
	 * Het bestand wordt tot die tijd niet meer geladen of uitgeserveerd.
	 */
	public function show_integrity_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tampered = pdk_tampered_files();
		if ( ! $tampered ) {
			return;
		}

		$ap_url = admin_url( 'admin-post.php' );
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'PDK Theme Options — bestand gewijzigd buiten de editor om', 'pdk-theme-options' ); ?></strong><br>
				<?php esc_html_e( 'De inhoud komt niet overeen met wat er via de editor is opgeslagen. Het bestand wordt niet geladen tot je hieronder kiest wat er moet gebeuren.', 'pdk-theme-options' ); ?>
			</p>
			<?php foreach ( $tampered as $filename ) : ?>
				<p>
					<code><?php echo esc_html( $filename ); ?></code>
					<a class="button button-small" href="<?php echo esc_url( add_query_arg(
						pdk_is_library_file( $filename )
							? [ 'page' => self::PAGE_SLUG, 'tab' => 'libraries', 'edit' => basename( $filename ), 'diff' => 1 ]
							: [ 'page' => self::PAGE_SLUG, 'tab' => array_search( $filename, self::CODE_TABS, true ), 'diff' => 1 ],
						admin_url( 'admin.php' )
					) ); ?>"><?php esc_html_e( 'Bekijk de wijziging', 'pdk-theme-options' ); ?></a>
					<?php if ( ! pdk_current_user_can_edit_code() ) : ?>
						— <?php esc_html_e( 'alleen een gebruiker met code-editor rechten kan dit afhandelen.', 'pdk-theme-options' ); ?>
					<?php endif; ?>
					<?php foreach ( pdk_current_user_can_edit_code() ? [ 'restore' => __( 'Herstel back-up', 'pdk-theme-options' ), 'accept' => __( 'Wijziging vertrouwen', 'pdk-theme-options' ) ] : [] as $op => $label ) : ?>
						<form method="post" action="<?php echo esc_url( $ap_url ); ?>" style="display:inline;margin-left:8px;">
							<?php wp_nonce_field( 'pdk_file_integrity' ); ?>
							<input type="hidden" name="action" value="pdk_file_integrity">
							<input type="hidden" name="op" value="<?php echo esc_attr( $op ); ?>">
							<input type="hidden" name="file" value="<?php echo esc_attr( $filename ); ?>">
							<button class="button button-small"><?php echo esc_html( $label ); ?></button>
						</form>
					<?php endforeach; ?>
				</p>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * "Herstel back-up" zet de laatst opgeslagen versie terug, "Wijziging
	 * vertrouwen" legt de huidige inhoud vast als nieuwe vingerafdruk.
	 * Beide vereisen de code-capability — niet alleen manage_options.
	 */
	public function handle_integrity_action(): void {
		check_admin_referer( 'pdk_file_integrity' );

		if ( ! pdk_current_user_can_edit_code() ) {
			wp_die( esc_html__( 'Je hebt geen rechten om code-bestanden te bewerken.', 'pdk-theme-options' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// pdk_storage_rel_path() laat alleen de libraries-submap door, de rest
		// wordt tot een kale bestandsnaam teruggebracht.
		$file = pdk_storage_rel_path( (string) wp_unslash( $_POST['file'] ?? '' ) );
		$op   = sanitize_key( $_POST['op'] ?? '' );
		// phpcs:enable

		if ( in_array( $file, pdk_watched_files(), true ) ) {
			$path = PDK_STORAGE_DIR . $file;

			if ( 'restore' === $op && file_exists( $path . '.bak' ) ) {
				$good = (string) file_get_contents( $path . '.bak' ); // phpcs:ignore
				if ( true === pdk_write_storage_file( $file, $good ) ) {
					// De schrijfactie zette de gehackte versie in .bak — terugdraaien,
					// zodat een tweede "Herstel" niet de hack terugzet.
					copy( $path, $path . '.bak' );
				}
			} elseif ( 'accept' === $op && file_exists( $path ) ) {
				pdk_store_file_hash( $file, (string) file_get_contents( $path ) ); // phpcs:ignore
			}
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	public function add_menu_page(): void {
		add_menu_page(
			__( 'PDK Theme Options', 'pdk-theme-options' ),
			__( 'PDK Tools', 'pdk-theme-options' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_settings_page' ],
			'dashicons-admin-tools',
			80
		);
	}

	public function enqueue_assets( string $hook ): void {
		// Ctrl+S opslaan: op élke admin-pagina, niet alleen op de PDK-tabs.
		wp_enqueue_script(
			'pdk-admin-save',
			PDK_PLUGIN_URL . 'assets/js/admin-save.js',
			[],
			PDK_PLUGIN_VERSION,
			true
		);

		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'pdk-admin',
			PDK_PLUGIN_URL . 'assets/css/admin.css',
			[],
			PDK_PLUGIN_VERSION
		);

		// Mediabibliotheek voor de favicon- en logo-velden.
		wp_enqueue_media();

		wp_enqueue_script(
			'pdk-admin',
			PDK_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			PDK_PLUGIN_VERSION,
			true
		);

		// CodeMirror 6-bundel: alleen op de tabs met een code-editor (671 kB).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$huidige_tab = sanitize_key( $_GET['tab'] ?? '' );
		$code_editor = isset( self::CODE_TABS[ $huidige_tab ] )
			// De Libraries-tab heeft alleen een editor als er een bestand open staat.
			|| ( 'libraries' === $huidige_tab && ! empty( $_GET['edit'] ) );
		// phpcs:enable

		if ( $code_editor ) {
			wp_enqueue_script(
				'pdk-code-editor',
				PDK_PLUGIN_URL . 'assets/js/editor.bundle.js',
				[],
				PDK_PLUGIN_VERSION,
				true
			);
		}

		wp_localize_script( 'pdk-admin', 'pdkAdmin', [
			'nonce'       => wp_create_nonce( 'pdk_admin_js' ),
			'savedText'   => __( 'Opgeslagen!', 'pdk-theme-options' ),
			'mediaTitle'  => __( 'Afbeelding kiezen', 'pdk-theme-options' ),
			'mediaButton' => __( 'Deze gebruiken', 'pdk-theme-options' ),
		] );
	}

	// -------------------------------------------------------------------------
	// Opslaan
	// -------------------------------------------------------------------------

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Geen toegang.', 'pdk-theme-options' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$tab = isset( $_POST['pdk_tab'] ) ? sanitize_key( $_POST['pdk_tab'] ) : 'modules';

		switch ( $tab ) {
			case 'modules':
				$this->save_modules();
				break;
			case 'site_settings':
				$this->save_site_settings();
				break;
			case 'vacation_mode':
				$this->save_vacation_mode();
				break;
			case 'delivery_time':
				if ( class_exists( 'PDK_Delivery_Time' ) ) {
					PDK_Delivery_Time::save();
				}
				break;
			case 'custom_fonts':
				$this->save_custom_fonts();
				break;
			case 'libraries':
				$this->save_libraries();
				break;
			case 'security':
				$this->save_security();
				break;
			case 'permissions':
				$this->save_permissions();
				break;
			case 'custom_functions':
			case 'custom_css':
			case 'custom_js':
				$this->save_code_file( $tab );
				return; // save_code_file doet zelf een redirect bij fouten.
		}
		// phpcs:enable

		wp_safe_redirect( add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'tab' => $tab, 'saved' => '1' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	private function save_modules(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$labels = PDK_Settings::module_labels();
		$data   = [];

		foreach ( array_keys( $labels ) as $module ) {
			// Toggle is uitgeschakeld zolang de afhankelijkheid ontbreekt —
			// de opgeslagen voorkeur niet overschrijven met "uit".
			if ( PDK_Settings::module_unavailable( $module ) ) {
				continue;
			}

			$data[ $module ]['enabled'] = ! empty( $_POST['modules'][ $module ]['enabled'] );
		}

		PDK_Settings::update( $data );
		// phpcs:enable
	}

	private function save_site_settings(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hours  = [];
		$posted = (array) ( $_POST['site_settings']['opening_hours'] ?? [] );

		foreach ( PDK_Settings::get_defaults()['site_settings']['opening_hours'] as $index => $day ) {
			$open  = sanitize_text_field( wp_unslash( $posted[ $index ]['open'] ?? '' ) );
			$close = sanitize_text_field( wp_unslash( $posted[ $index ]['close'] ?? '' ) );

			$hours[ $index ] = [
				'closed' => ! empty( $posted[ $index ]['closed'] ),
				'open'   => preg_match( '/^\d{2}:\d{2}$/', $open ) ? $open : '',
				'close'  => preg_match( '/^\d{2}:\d{2}$/', $close ) ? $close : '',
			];
		}

		$periods = [];
		foreach ( (array) ( $_POST['site_settings']['periods'] ?? [] ) as $row ) {
			if ( ! empty( $row['delete'] ) ) {
				continue;
			}

			$from = sanitize_text_field( wp_unslash( $row['from'] ?? '' ) );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
				continue; // Lege of ongeldige rij overslaan.
			}

			$to = sanitize_text_field( wp_unslash( $row['to'] ?? '' ) );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) || $to < $from ) {
				$to = $from;
			}

			$open  = sanitize_text_field( wp_unslash( $row['open'] ?? '' ) );
			$close = sanitize_text_field( wp_unslash( $row['close'] ?? '' ) );

			// Eén van de twee tijden ingevuld is geen halve openstelling —
			// dan geldt de periode als volledig gesloten.
			if ( ! preg_match( '/^\d{2}:\d{2}$/', $open ) || ! preg_match( '/^\d{2}:\d{2}$/', $close ) ) {
				$open  = '';
				$close = '';
			}

			$periods[] = [
				'from'       => $from,
				'to'         => $to,
				'label'      => sanitize_text_field( wp_unslash( $row['label'] ?? '' ) ),
				'open'       => $open,
				'close'      => $close,
				'close_shop' => ! empty( $row['close_shop'] ),
			];
		}

		// Bewust géén PDK_Settings::update(): die merget recursief, waardoor
		// verwijderde periode-rijen zouden blijven staan. array_merge vervangt
		// de hele lijst.
		$options = (array) PDK_Settings::get();

		$options['site_settings'] = array_merge( (array) ( $options['site_settings'] ?? [] ), [
				'favicon_url'         => esc_url_raw( $_POST['site_settings']['favicon_url'] ?? '' ),
				'disable_page_editor' => ! empty( $_POST['site_settings']['disable_page_editor'] ),
				'client_logo'         => esc_url_raw( $_POST['site_settings']['client_logo'] ?? '' ),
				'company_name'        => sanitize_text_field( $_POST['site_settings']['company_name'] ?? '' ),
				'company_street'      => sanitize_text_field( $_POST['site_settings']['company_street'] ?? '' ),
				'company_number'      => sanitize_text_field( $_POST['site_settings']['company_number'] ?? '' ),
				'company_zipcode'     => sanitize_text_field( $_POST['site_settings']['company_zipcode'] ?? '' ),
				'company_city'        => sanitize_text_field( $_POST['site_settings']['company_city'] ?? '' ),
				'company_phone'       => sanitize_text_field( $_POST['site_settings']['company_phone'] ?? '' ),
				'company_email'       => sanitize_email( $_POST['site_settings']['company_email'] ?? '' ),
				'social_facebook'     => esc_url_raw( $_POST['site_settings']['social_facebook'] ?? '' ),
				'social_instagram'    => esc_url_raw( $_POST['site_settings']['social_instagram'] ?? '' ),
				'social_linkedin'     => esc_url_raw( $_POST['site_settings']['social_linkedin'] ?? '' ),
				'social_twitter'      => esc_url_raw( $_POST['site_settings']['social_twitter'] ?? '' ),
				'social_youtube'      => esc_url_raw( $_POST['site_settings']['social_youtube'] ?? '' ),
				'social_tiktok'       => esc_url_raw( $_POST['site_settings']['social_tiktok'] ?? '' ),
				'opening_hours'      => $hours,
				'periods'            => $periods,
		] );

		update_option( PDK_Settings::OPTION_KEY, $options );
		// phpcs:enable
	}

	private function save_vacation_mode(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		PDK_Settings::update( [
			'vacation_mode' => [
				'message'             => wp_kses_post( $_POST['vacation_mode']['message'] ?? '' ),
				'disable_checkout'    => ! empty( $_POST['vacation_mode']['disable_checkout'] ),
				'disable_add_to_cart' => ! empty( $_POST['vacation_mode']['disable_add_to_cart'] ),
				// Periodes worden opgeslagen bij Site Instellingen → Afwijkende dagen.
			],
		] );
		// phpcs:enable
	}

	private function save_custom_fonts(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		PDK_Settings::update( [
			'custom_fonts' => [
				'display' => sanitize_key( $_POST['custom_fonts']['display'] ?? 'swap' ),
			],
		] );
		// phpcs:enable
	}

	private function save_permissions(): void {
		// wp-config is leidend — dan valt hier niets te wijzigen.
		if ( null !== pdk_config_code_editors() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$allowed_users = array_map( 'absint', (array) ( $_POST['allowed_users'] ?? [] ) );

		// Haal de cap weg bij ROLLEN. Oudere installaties hebben hem op de
		// administrator-rol staan; remove_cap() op een gebruiker haalt een
		// rol-cap niet weg, waardoor uitvinken van een beheerder niets deed.
		foreach ( array_keys( wp_roles()->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && $role->has_cap( PDK_CAP_EDIT_CODE ) ) {
				$role->remove_cap( PDK_CAP_EDIT_CODE );
			}
		}

		// Verwijder cap van ALLE niet-primaire gebruikers.
		$all_users = get_users( [ 'fields' => 'ID' ] );
		foreach ( $all_users as $uid ) {
			$user = get_userdata( $uid );
			if ( $user ) {
				$user->remove_cap( PDK_CAP_EDIT_CODE );
			}
		}

		// Hergeef cap aan geselecteerde gebruikers.
		foreach ( $allowed_users as $uid ) {
			$user = get_userdata( $uid );
			if ( $user ) {
				$user->add_cap( PDK_CAP_EDIT_CODE );
			}
		}
		// phpcs:enable
	}

	private function save_code_file( string $tab ): void {
		if ( ! pdk_current_user_can_edit_code() ) {
			wp_die( esc_html__( 'Je hebt geen rechten om code-bestanden te bewerken.', 'pdk-theme-options' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$content = wp_unslash( $_POST['file_content'] ?? '' );

		$result = pdk_write_storage_file( self::CODE_TABS[ $tab ], $content );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				[ 'page' => self::PAGE_SLUG, 'tab' => $tab, 'error' => rawurlencode( $result->get_error_message() ) ],
				admin_url( 'admin.php' )
			) );
			exit;
		}

		wp_safe_redirect( add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'tab' => $tab, 'saved' => '1' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Font-handlers (display-instelling, upload, verwijderen)
	// -------------------------------------------------------------------------

	public function handle_save_fonts_display(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Geen toegang.', 'pdk-theme-options' ), 403 );
		}

		check_admin_referer( 'pdk_save_fonts_display' );

		$allowed_outputs = [ 'inline', 'file' ];
		$css_output      = sanitize_key( $_POST['custom_fonts']['css_output'] ?? 'inline' ); // phpcs:ignore
		if ( ! in_array( $css_output, $allowed_outputs, true ) ) {
			$css_output = 'inline';
		}

		PDK_Settings::update( [
			'custom_fonts' => [
				'display'    => sanitize_key( $_POST['custom_fonts']['display'] ?? 'swap' ), // phpcs:ignore
				'css_output' => $css_output,
			],
		] );

		wp_safe_redirect( add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'tab' => 'custom_fonts', 'saved' => '1' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function handle_font_upload(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Geen toegang.', 'pdk-theme-options' ), 403 );
		}

		check_admin_referer( 'pdk_font_upload' );

		$redirect = add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'tab' => 'custom_fonts' ],
			admin_url( 'admin.php' )
		);

		if ( empty( $_FILES['pdk_font'] ) || $_FILES['pdk_font']['error'] !== UPLOAD_ERR_OK ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( __( 'Geen bestand ontvangen of uploadfout.', 'pdk-theme-options' ) ), $redirect ) );
			exit;
		}

		$allowed = class_exists( 'PDK_Custom_Fonts' ) ? PDK_Custom_Fonts::allowed_extensions() : [ 'woff2', 'woff', 'ttf', 'otf' ];
		$ext     = strtolower( pathinfo( $_FILES['pdk_font']['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed, true ) ) {
			wp_safe_redirect( add_query_arg(
				'error',
				rawurlencode( sprintf( __( 'Ongeldig bestandstype. Toegestaan: %s.', 'pdk-theme-options' ), implode( ', ', $allowed ) ) ),
				$redirect
			) );
			exit;
		}

		// Bestandsnaam saneren: alleen a-z, A-Z, 0-9, koppeltekens, underscore, punt.
		$clean_name = preg_replace( '/[^a-zA-Z0-9._-]/', '', basename( $_FILES['pdk_font']['name'] ) );
		if ( ! $clean_name ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( __( 'Ongeldige bestandsnaam.', 'pdk-theme-options' ) ), $redirect ) );
			exit;
		}

		$font_dir = class_exists( 'PDK_Custom_Fonts' ) ? PDK_Custom_Fonts::font_dir() : WP_CONTENT_DIR . '/uploads/fonts/';

		if ( ! wp_mkdir_p( $font_dir ) ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( __( 'Kan de fontmap niet aanmaken.', 'pdk-theme-options' ) ), $redirect ) );
			exit;
		}

		$destination = $font_dir . $clean_name;

		if ( ! move_uploaded_file( $_FILES['pdk_font']['tmp_name'], $destination ) ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( __( 'Uploaden mislukt (schrijfrechten?).', 'pdk-theme-options' ) ), $redirect ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'saved', '1', $redirect ) );
		exit;
	}

	public function handle_font_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Geen toegang.', 'pdk-theme-options' ), 403 );
		}

		check_admin_referer( 'pdk_font_delete' );

		$redirect = add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'tab' => 'custom_fonts' ],
			admin_url( 'admin.php' )
		);

		$font_dir  = class_exists( 'PDK_Custom_Fonts' ) ? PDK_Custom_Fonts::font_dir() : WP_CONTENT_DIR . '/uploads/fonts/';
		$allowed   = class_exists( 'PDK_Custom_Fonts' ) ? PDK_Custom_Fonts::allowed_extensions() : [ 'woff2', 'woff', 'ttf', 'otf' ];
		$filename  = sanitize_file_name( $_POST['pdk_font_file'] ?? '' ); // phpcs:ignore
		$ext       = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$full_path = $font_dir . $filename;

		// Controleer: geldige extensie, geen padtraversal, bestand bestaat in fontmap.
		if (
			! in_array( $ext, $allowed, true ) ||
			! str_starts_with( realpath( $full_path ) ?: '', realpath( $font_dir ) ?: '' ) ||
			! is_file( $full_path )
		) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( __( 'Ongeldig bestand.', 'pdk-theme-options' ) ), $redirect ) );
			exit;
		}

		@unlink( $full_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		wp_safe_redirect( add_query_arg( 'saved', '1', $redirect ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'modules';

		// Als de gevraagde tab een uitgeschakelde module is, val terug op modules.
		if ( array_key_exists( $tab, self::MODULE_TABS ) && ! PDK_Settings::is_module_enabled( $tab ) ) {
			$tab = 'modules';
		}

		// Tabs met eigen forms (geen wrapper-form nodig — anders geneste forms).
		$standalone_tabs = [ 'language_checker', 'custom_fonts', 'libraries' ];
		$use_form        = ! in_array( $tab, $standalone_tabs, true );
		?>
		<div class="wrap pdk-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php $this->render_notices(); ?>

			<nav class="nav-tab-wrapper">
				<?php $this->render_tabs( $tab ); ?>
			</nav>

			<?php if ( $use_form ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pdk-settings-form">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="action"  value="pdk_save_settings">
				<input type="hidden" name="pdk_tab" value="<?php echo esc_attr( $tab ); ?>">

				<div class="pdk-tab-content">
					<?php $this->render_tab_content( $tab ); ?>
				</div>

				<?php submit_button( __( 'Instellingen opslaan', 'pdk-theme-options' ) ); ?>
			</form>
			<?php else : ?>
			<div class="pdk-tab-content" style="margin-top:1em;">
				<?php $this->render_tab_content( $tab ); ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Instellingen opgeslagen.', 'pdk-theme-options' ) . '</p></div>';
		}
		if ( ! empty( $_GET['error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( rawurldecode( $_GET['error'] ) ) . '</p></div>';
		}
		// phpcs:enable
	}

	/**
	 * Rendert de tab-navigatie.
	 * Module-specifieke tabs verschijnen ALLEEN als de betreffende module actief is.
	 */
	private function render_tabs( string $current ): void {
		// Altijd zichtbare tabs.
		$tabs = [
			'modules'       => __( 'Modules', 'pdk-theme-options' ),
			'site_settings' => __( 'Site Instellingen', 'pdk-theme-options' ),
			'security'      => __( 'Security', 'pdk-theme-options' ),
			'permissions'   => __( 'Rechten', 'pdk-theme-options' ),
		];

		// Module-tabs: alleen tonen als de module ingeschakeld is.
		$module_tab_labels = [
			'custom_functions' => __( 'PHP Functions', 'pdk-theme-options' ),
			'custom_css'       => __( 'Custom CSS', 'pdk-theme-options' ),
			'custom_js'        => __( 'Custom JS', 'pdk-theme-options' ),
			'custom_fonts'     => __( 'Fonts', 'pdk-theme-options' ),
			'libraries'        => __( 'Libraries', 'pdk-theme-options' ),
			'vacation_mode'    => __( 'Vakantiemodus', 'pdk-theme-options' ),
			'delivery_time'    => __( 'Levertijden', 'pdk-theme-options' ),
			'language_checker' => __( 'Language Cleaner', 'pdk-theme-options' ),
		];

		foreach ( $module_tab_labels as $module => $label ) {
			if ( PDK_Settings::is_module_enabled( $module ) ) {
				$tabs[ $module ] = $label;
			}
		}

		foreach ( $tabs as $slug => $label ) {
			$class = ( $slug === $current ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			$url   = add_query_arg( [ 'page' => self::PAGE_SLUG, 'tab' => $slug ], admin_url( 'admin.php' ) );
			printf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}
	}

	private function render_tab_content( string $tab ): void {
		switch ( $tab ) {
			case 'modules':
				$this->render_tab_modules();
				break;
			case 'site_settings':
				$this->render_tab_site_settings();
				break;
			case 'security':
				$this->render_tab_security();
				break;
			case 'permissions':
				$this->render_tab_permissions();
				break;
			case 'custom_functions':
				$this->render_tab_code_editor( 'custom-functions.php', 'php' );
				break;
			case 'custom_css':
				$this->render_tab_code_editor( 'custom-style.css', 'css' );
				break;
			case 'custom_js':
				$this->render_tab_code_editor( 'custom-script.js', 'javascript' );
				break;
			case 'custom_fonts':
				$this->render_tab_fonts();
				break;
			case 'libraries':
				$this->render_tab_libraries();
				break;
			case 'vacation_mode':
				$this->render_tab_vacation();
				break;
			case 'delivery_time':
				if ( class_exists( 'PDK_Delivery_Time' ) ) {
					PDK_Delivery_Time::render_inline();
				}
				break;
			case 'language_checker':
				$this->render_tab_language_checker();
				break;
		}
	}

	// -------------------------------------------------------------------------
	// Tab: Modules
	// -------------------------------------------------------------------------

	private function render_tab_modules(): void {
		$labels       = PDK_Settings::module_labels();
		$descriptions = PDK_Settings::module_descriptions();
		?>
		<p class="description" style="margin:12px 0 16px;">
			<?php esc_html_e( 'Schakel modules in of uit. Ingeschakelde modules krijgen een eigen tabblad voor verdere instellingen.', 'pdk-theme-options' ); ?>
		</p>

		<div class="pdk-always-on-notice">
			<strong><?php esc_html_e( 'Altijd actief:', 'pdk-theme-options' ); ?></strong>
			<?php esc_html_e( 'Critical Error Status (HTTP 500 bij fatale fouten) en Site Instellingen zijn altijd ingeschakeld.', 'pdk-theme-options' ); ?>
		</div>

		<table class="form-table pdk-modules-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Module', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Omschrijving', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Actief', 'pdk-theme-options' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $labels as $module => $label ) :
				$unavailable = PDK_Settings::module_unavailable( $module );
			?>
				<tr<?php echo $unavailable ? ' style="opacity:.6;"' : ''; ?>>
					<td><strong><?php echo esc_html( $label ); ?></strong></td>
					<td>
						<?php echo esc_html( $descriptions[ $module ] ?? '' ); ?>
						<?php if ( $unavailable ) : ?>
							<br><span style="color:#d63638;font-weight:600;"><?php esc_html_e( 'WooCommerce is niet actief — deze module kan niet worden ingeschakeld.', 'pdk-theme-options' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<label class="pdk-toggle">
							<input
								type="checkbox"
								name="modules[<?php echo esc_attr( $module ); ?>][enabled]"
								value="1"
								<?php checked( PDK_Settings::is_module_enabled( $module ) ); ?>
								<?php disabled( $unavailable ); ?>
							>
							<span class="pdk-toggle-slider"></span>
						</label>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab: Site Instellingen (Carbon Fields migratie)
	// -------------------------------------------------------------------------

	private function render_tab_site_settings(): void {
		$s = array_merge(
			PDK_Settings::get_defaults()['site_settings'],
			(array) PDK_Settings::get( 'site_settings' )
		);

		// Sub-tabs: alle secties blijven in hetzelfde formulier staan (één keer
		// opslaan bewaart álles) — JS toont alleen de actieve sectie.
		$sections = [
			'basis'            => __( 'Basis', 'pdk-theme-options' ),
			'klantgegevens'    => __( 'Klantgegevens', 'pdk-theme-options' ),
			'openingstijden'   => __( 'Openingstijden', 'pdk-theme-options' ),
			'afwijkende-dagen' => __( 'Afwijkende dagen', 'pdk-theme-options' ),
			'social'           => __( 'Social Media', 'pdk-theme-options' ),
		];
		?>
		<nav class="nav-tab-wrapper pdk-subtabs" style="margin:12px 0 0;">
			<?php foreach ( $sections as $slug => $label ) : ?>
				<a href="#<?php echo esc_attr( $slug ); ?>" class="nav-tab" data-subtab="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<div class="pdk-subtab" id="basis">
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Favicon', 'pdk-theme-options' ); ?></th>
				<td>
					<?php $this->render_media_field(
						'favicon_url',
						$s['favicon_url'],
						__( 'Kies een .ico, .png of .svg uit de mediabibliotheek, of vul een externe URL in.', 'pdk-theme-options' )
					); ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Pagina-editor uitschakelen', 'pdk-theme-options' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="site_settings[disable_page_editor]" value="1" <?php checked( $s['disable_page_editor'] ); ?>>
						<?php esc_html_e( 'Gutenberg uitschakelen voor paginatype "Pagina"', 'pdk-theme-options' ); ?>
					</label>
				</td>
			</tr>
		</table>

		</div><!-- /basis -->

		<div class="pdk-subtab" id="klantgegevens">
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Logo', 'pdk-theme-options' ); ?></th>
				<td>
					<?php $this->render_media_field(
						'client_logo',
						$s['client_logo'],
						__( 'Opvraagbaar in thema via pdk_client_logo_url()', 'pdk-theme-options' )
					); ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Bedrijfsnaam', 'pdk-theme-options' ); ?></th>
				<td><input type="text" name="site_settings[company_name]" value="<?php echo esc_attr( $s['company_name'] ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Straat', 'pdk-theme-options' ); ?></th>
				<td>
					<input type="text" name="site_settings[company_street]" value="<?php echo esc_attr( $s['company_street'] ?? '' ); ?>" class="regular-text" style="width:60%;">
					<input type="text" name="site_settings[company_number]" value="<?php echo esc_attr( $s['company_number'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Nr', 'pdk-theme-options' ); ?>" style="width:20%;margin-left:4px;">
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Postcode', 'pdk-theme-options' ); ?></th>
				<td>
					<input type="text" name="site_settings[company_zipcode]" value="<?php echo esc_attr( $s['company_zipcode'] ?? '' ); ?>" style="width:30%;margin-right:8px;" placeholder="1234 AB">
					<input type="text" name="site_settings[company_city]" value="<?php echo esc_attr( $s['company_city'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Plaatsnaam', 'pdk-theme-options' ); ?>" style="width:50%;">
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Telefoonnummer', 'pdk-theme-options' ); ?></th>
				<td><input type="text" name="site_settings[company_phone]" value="<?php echo esc_attr( $s['company_phone'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'E-mailadres', 'pdk-theme-options' ); ?></th>
				<td><input type="email" name="site_settings[company_email]" value="<?php echo esc_attr( $s['company_email'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
		</table>

		<p class="description">
			<?php esc_html_e( 'Opvragen in je thema: pdk_site_setting("company_name"), pdk_company_address() voor het volledige adres.', 'pdk-theme-options' ); ?>
		</p>

		</div><!-- /klantgegevens -->

		<div class="pdk-subtab" id="openingstijden">
		<p class="description" style="margin-bottom:12px;">
			<?php esc_html_e( 'Toon ze met de shortcode [openingstijden] of in een template met do_action( \'pdk_openingstijden\' ). Vink "Gesloten" aan voor een dag zonder openingstijden.', 'pdk-theme-options' ); ?>
		</p>
		<?php $hours = class_exists( 'PDK_Site_Settings' ) ? PDK_Site_Settings::opening_hours() : []; ?>
		<table class="widefat" style="max-width:600px;margin-bottom:20px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Dag', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Van', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Tot', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Gesloten', 'pdk-theme-options' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( pdk_day_labels() as $index => $label ) :
				$day = $hours[ $index ] ?? [ 'closed' => false, 'open' => '', 'close' => '' ];
			?>
				<tr>
					<td><?php echo esc_html( $label ); ?></td>
					<td><input type="time" name="site_settings[opening_hours][<?php echo esc_attr( $index ); ?>][open]" value="<?php echo esc_attr( $day['open'] ); ?>"></td>
					<td><input type="time" name="site_settings[opening_hours][<?php echo esc_attr( $index ); ?>][close]" value="<?php echo esc_attr( $day['close'] ); ?>"></td>
					<td><input type="checkbox" name="site_settings[opening_hours][<?php echo esc_attr( $index ); ?>][closed]" value="1" <?php checked( ! empty( $day['closed'] ) ); ?>></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		</div><!-- /openingstijden -->

		<div class="pdk-subtab" id="afwijkende-dagen">
		<p class="description" style="margin-bottom:12px;">
			<?php esc_html_e( 'Periodes die afwijken van de vaste weekindeling: kerst, oud en nieuw, een zomerperiode of een bedrijfsvakantie. Eén keer invullen — de openingstijden, de levertijden en de vakantiemodus gebruiken allemaal deze lijst.', 'pdk-theme-options' ); ?>
		</p>
		<p class="description" style="margin-bottom:12px;">
			<strong><?php esc_html_e( 'Openingstijden leeg laten', 'pdk-theme-options' ); ?></strong>
			<?php esc_html_e( '= die dagen volledig gesloten, en er wordt niet verzonden. Vul je wél tijden in, dan gelden die afwijkende tijden en gaat het verzenden gewoon door. "Tot"-datum is alleen nodig bij een periode van meerdere dagen.', 'pdk-theme-options' ); ?>
		</p>
		<?php
		$periods   = class_exists( 'PDK_Site_Settings' ) ? PDK_Site_Settings::periods() : [];
		$periods[] = [ 'from' => '', 'to' => '', 'label' => '', 'open' => '', 'close' => '', 'close_shop' => false ]; // altijd één lege invoerrij
		?>
		<table class="widefat" style="max-width:1000px;margin-bottom:10px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Van datum', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Tot datum', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Omschrijving', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Open van', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Tot', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Webshop sluiten', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Verwijderen', 'pdk-theme-options' ); ?></th>
				</tr>
			</thead>
			<tbody id="pdk-periods">
			<?php foreach ( $periods as $i => $row ) : ?>
				<tr>
					<td><input type="date" name="site_settings[periods][<?php echo esc_attr( $i ); ?>][from]" value="<?php echo esc_attr( $row['from'] ); ?>"></td>
					<td><input type="date" name="site_settings[periods][<?php echo esc_attr( $i ); ?>][to]" value="<?php echo esc_attr( $row['to'] ); ?>"></td>
					<td><input type="text" class="regular-text" name="site_settings[periods][<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'bv. Kerst', 'pdk-theme-options' ); ?>"></td>
					<td><input type="time" name="site_settings[periods][<?php echo esc_attr( $i ); ?>][open]" value="<?php echo esc_attr( $row['open'] ); ?>"></td>
					<td><input type="time" name="site_settings[periods][<?php echo esc_attr( $i ); ?>][close]" value="<?php echo esc_attr( $row['close'] ); ?>"></td>
					<td><input type="checkbox" name="site_settings[periods][<?php echo esc_attr( $i ); ?>][close_shop]" value="1" <?php checked( ! empty( $row['close_shop'] ) ); ?>></td>
					<td>
						<?php if ( ! empty( $row['from'] ) ) : ?>
							<input type="checkbox" name="site_settings[periods][<?php echo esc_attr( $i ); ?>][delete]" value="1">
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button" id="pdk-periods-add-row">+ <?php esc_html_e( 'Rij toevoegen', 'pdk-theme-options' ); ?></button>
		</p>
		<p class="description">
			<?php esc_html_e( '"Webshop sluiten" schakelt de vakantiemodus in tijdens die periode — bestellen wordt dan geblokkeerd. Laat dit uit voor een gewone sluitingsdag zoals kerst, waarop de webshop gewoon bestellingen mag aannemen en alleen de levertijd opschuift. De module Vakantiemodus moet daarvoor wel aan staan.', 'pdk-theme-options' ); ?>
		</p>
		<script>
		( function () {
			var counter = <?php echo (int) count( $periods ); ?>;
			var tbody   = document.getElementById( 'pdk-periods' );

			document.getElementById( 'pdk-periods-add-row' ).addEventListener( 'click', function () {
				var rows = tbody.querySelectorAll( 'tr' );
				var row  = rows[ rows.length - 1 ].cloneNode( true );

				row.querySelectorAll( 'input' ).forEach( function ( input ) {
					input.name = input.name.replace( /\[\d+\]/, '[' + counter + ']' );
					if ( input.type === 'checkbox' ) {
						input.checked = false;
					} else {
						input.value = '';
					}
				} );

				tbody.appendChild( row );
				counter++;
			} );
		} )();
		</script>

		</div><!-- /afwijkende-dagen -->

		<div class="pdk-subtab" id="social">
		<table class="form-table">
			<?php
			$socials = [
				'social_facebook'  => 'Facebook',
				'social_instagram' => 'Instagram',
				'social_linkedin'  => 'LinkedIn',
				'social_twitter'   => 'X / Twitter',
				'social_youtube'   => 'YouTube',
				'social_tiktok'    => 'TikTok',
			];
			foreach ( $socials as $key => $label ) :
			?>
			<tr>
				<th><?php echo esc_html( $label ); ?></th>
				<td><input type="url" name="site_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_url( $s[ $key ] ); ?>" class="regular-text" placeholder="https://"></td>
			</tr>
			<?php endforeach; ?>
		</table>
		</div><!-- /social -->
		<?php
	}

	/**
	 * URL-veld met mediabibliotheek-kiezer. De waarde blijft een URL, zodat
	 * pdk_client_logo_url() en de favicon-output ongewijzigd blijven werken.
	 */
	private function render_media_field( string $key, string $value, string $description ): void {
		?>
		<div class="pdk-media-field">
			<img src="<?php echo esc_url( $value ); ?>" class="pdk-media-preview" alt="" <?php echo $value ? '' : 'style="display:none;"'; ?>>
			<p>
				<input type="url" name="site_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_url( $value ); ?>" class="regular-text">
				<button type="button" class="button pdk-media-pick"><?php esc_html_e( 'Kies uit mediabibliotheek', 'pdk-theme-options' ); ?></button>
				<button type="button" class="button-link pdk-media-clear"><?php esc_html_e( 'Verwijderen', 'pdk-theme-options' ); ?></button>
			</p>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab: Code Editor (PHP / CSS / JS)
	// -------------------------------------------------------------------------

	private function render_tab_code_editor( string $filename, string $lang ): void {
		if ( ! pdk_current_user_can_edit_code() ) {
			echo '<div class="notice notice-error" style="margin:16px 0;"><p>';
			esc_html_e( 'Je hebt geen rechten om dit bestand te bewerken. Vraag een bevoegde gebruiker om je toe te voegen via de Rechten-tab.', 'pdk-theme-options' );
			echo '</p></div>';
			// Verberg de submit-knop via JS-class op het form.
			echo '<style>.pdk-settings-form #submit{display:none}</style>';
			return;
		}

		$path     = PDK_STORAGE_DIR . $filename;
		$content  = file_exists( $path ) ? file_get_contents( $path ) : ''; // phpcs:ignore
		$baseline = file_exists( $path . '.bak' ) ? file_get_contents( $path . '.bak' ) : null; // phpcs:ignore
		$tampered = pdk_file_is_tampered( $filename );

		// Vanuit de integriteitsmelding komt men binnen met ?diff=1 — dan opent
		// de vergelijking meteen, zodat je ziet wat er is bijgeschreven.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$open_diff = $tampered || ! empty( $_GET['diff'] );
		?>
		<?php if ( $tampered ) : ?>
			<div class="notice notice-error inline" style="margin:0 0 12px;">
				<p><?php esc_html_e( 'Dit bestand is buiten de editor om gewijzigd en wordt niet geladen. Vergelijk hieronder met de laatst opgeslagen versie.', 'pdk-theme-options' ); ?></p>
			</div>
		<?php endif; ?>
		<p class="description">
			<?php
			printf(
				esc_html__( 'Bestand: %s — wordt niet overschreven bij plugin-updates.', 'pdk-theme-options' ),
				'<code>' . esc_html( $path ) . '</code>'
			);
			?>
			<?php if ( null !== $baseline ) : ?>
				<button
					type="button"
					class="button button-small"
					data-pdk-diff-toggle
					data-label-on="<?php esc_attr_e( 'Vergelijk met laatst opgeslagen versie', 'pdk-theme-options' ); ?>"
					data-label-off="<?php esc_attr_e( 'Terug naar de editor', 'pdk-theme-options' ); ?>"
					hidden
				><?php echo esc_html( $open_diff ? __( 'Terug naar de editor', 'pdk-theme-options' ) : __( 'Vergelijk met laatst opgeslagen versie', 'pdk-theme-options' ) ); ?></button>
			<?php endif; ?>
		</p>
		<div class="pdk-editor" data-diff="<?php echo $open_diff && null !== $baseline ? '1' : '0'; ?>">
			<textarea
				name="file_content"
				id="pdk-code-editor"
				class="pdk-code-editor"
				data-lang="<?php echo esc_attr( $lang ); ?>"
				rows="30"
				spellcheck="false"
			><?php echo esc_textarea( $content ); ?></textarea>
			<?php if ( null !== $baseline ) : ?>
				<textarea class="pdk-code-baseline" hidden readonly><?php echo esc_textarea( $baseline ); ?></textarea>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab: Fonts
	// -------------------------------------------------------------------------

	private function render_tab_fonts(): void {
		$display  = PDK_Settings::get_with_default( 'custom_fonts', 'display' ) ?: 'swap';
		$fonts    = class_exists( 'PDK_Custom_Fonts' ) ? PDK_Custom_Fonts::scan_fonts() : [];
		$font_dir = class_exists( 'PDK_Custom_Fonts' ) ? PDK_Custom_Fonts::font_dir() : WP_CONTENT_DIR . '/uploads/fonts/';
		$ap_url   = esc_url( admin_url( 'admin-post.php' ) );
		?>

		<!-- Sectie 1: Instellingen -->
		<h2><?php esc_html_e( 'Instellingen', 'pdk-theme-options' ); ?></h2>
		<?php $css_output = PDK_Settings::get_with_default( 'custom_fonts', 'css_output' ) ?: 'inline'; ?>
		<form method="post" action="<?php echo $ap_url; // phpcs:ignore ?>">
			<?php wp_nonce_field( 'pdk_save_fonts_display' ); ?>
			<input type="hidden" name="action" value="pdk_save_fonts_display">
			<table class="form-table" style="margin-bottom:0;">
				<tr>
					<th style="width:220px;"><?php esc_html_e( 'Font-display', 'pdk-theme-options' ); ?></th>
					<td>
						<select name="custom_fonts[display]">
							<?php foreach ( [ 'auto', 'block', 'swap', 'fallback', 'optional' ] as $d ) : ?>
								<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $display, $d ); ?>><?php echo esc_html( $d ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Aanbevolen: swap (geen onzichtbare tekst tijdens laden).', 'pdk-theme-options' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'CSS-uitvoer', 'pdk-theme-options' ); ?></th>
					<td>
						<select name="custom_fonts[css_output]">
							<option value="inline" <?php selected( $css_output, 'inline' ); ?>><?php esc_html_e( 'Inline (in &lt;head&gt;)', 'pdk-theme-options' ); ?></option>
							<option value="file"   <?php selected( $css_output, 'file' ); ?>><?php esc_html_e( 'Extern bestand (gecached)', 'pdk-theme-options' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Extern bestand is sneller door browser-caching. Vereist schrijfrechten op uploads/fonts/.', 'pdk-theme-options' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Opslaan', 'pdk-theme-options' ), 'secondary', 'submit', false ); ?>
		</form>

		<hr style="margin:1.5em 0;">

		<!-- Sectie 2: Geïnstalleerde fonts -->
		<h2><?php esc_html_e( 'Geïnstalleerde fonts', 'pdk-theme-options' ); ?></h2>

		<?php if ( empty( $fonts ) ) : ?>
			<p class="description">
				<?php printf(
					esc_html__( 'Geen fonts gevonden in %s', 'pdk-theme-options' ),
					'<code>' . esc_html( $font_dir ) . '</code>'
				); ?>
			</p>
		<?php else : ?>
			<?php foreach ( $fonts as $family => $variants ) : ?>
			<div style="margin-bottom:1.5em;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:4px;">
				<h3 style="margin:0 0 4px;">
					<span style="font-family:'<?php echo esc_attr( $family ); ?>',sans-serif;font-size:1.6em;">
						<?php echo esc_html( $family ); ?>
					</span>
					<span style="color:#888;font-size:.8em;font-weight:normal;margin-left:8px;">
						(<?php echo count( $variants ); ?> <?php echo count( $variants ) === 1 ? esc_html__( 'variant', 'pdk-theme-options' ) : esc_html__( 'varianten', 'pdk-theme-options' ); ?>)
					</span>
				</h3>
				<p style="font-family:'<?php echo esc_attr( $family ); ?>',sans-serif;color:#555;margin:4px 0 12px;font-size:.95em;">
					<?php esc_html_e( 'The quick brown fox jumps over the lazy dog — 0123456789', 'pdk-theme-options' ); ?>
				</p>
				<table class="widefat" style="margin:0;">
					<thead><tr>
						<th><?php esc_html_e( 'Gewicht', 'pdk-theme-options' ); ?></th>
						<th><?php esc_html_e( 'Naam', 'pdk-theme-options' ); ?></th>
						<th><?php esc_html_e( 'Bestandsnaam', 'pdk-theme-options' ); ?></th>
						<th><?php esc_html_e( 'Formaat', 'pdk-theme-options' ); ?></th>
						<th><?php esc_html_e( 'Grootte', 'pdk-theme-options' ); ?></th>
						<th></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $variants as $v ) :
						$weight_label = class_exists( 'PDK_Custom_Fonts' ) ? PDK_Custom_Fonts::weight_label( $v['weight'], $v['style'] ) : $v['weight'];
					?>
						<tr>
							<td><?php echo esc_html( $v['weight'] ); ?></td>
							<td><?php echo esc_html( $weight_label ); ?></td>
							<td><code style="font-size:.85em;"><?php echo esc_html( $v['file'] ); ?></code></td>
							<td><?php echo esc_html( strtoupper( pathinfo( $v['file'], PATHINFO_EXTENSION ) ) ); ?></td>
							<td style="white-space:nowrap;"><?php echo esc_html( size_format( $v['size'] ) ); ?></td>
							<td>
								<form method="post" action="<?php echo $ap_url; // phpcs:ignore ?>" style="margin:0;">
									<?php wp_nonce_field( 'pdk_font_delete' ); ?>
									<input type="hidden" name="action"         value="pdk_font_delete">
									<input type="hidden" name="pdk_font_file"  value="<?php echo esc_attr( $v['file'] ); ?>">
									<button type="submit" class="button button-small" style="color:#d63638;border-color:#d63638;"
										onclick="return confirm('<?php esc_attr_e( 'Weet je zeker dat je dit font wilt verwijderen?', 'pdk-theme-options' ); ?>')">
										<?php esc_html_e( 'Verwijderen', 'pdk-theme-options' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<hr style="margin:1.5em 0;">

		<!-- Sectie 3: Font uploaden -->
		<h2><?php esc_html_e( 'Font uploaden', 'pdk-theme-options' ); ?></h2>
		<p class="description" style="margin-bottom:12px;">
			<?php printf(
				esc_html__( 'Ondersteunde formaten: %s. Bestandsnaamconventie: %s', 'pdk-theme-options' ),
				'<code>woff2, woff, ttf, otf</code>',
				'<code>FamilyName-Bold.woff2</code>'
			); ?>
		</p>
		<form method="post" action="<?php echo $ap_url; // phpcs:ignore ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'pdk_font_upload' ); ?>
			<input type="hidden" name="action" value="pdk_font_upload">
			<p>
				<input type="file" name="pdk_font" accept=".woff2,.woff,.ttf,.otf" required style="margin-right:8px;">
				<?php submit_button( __( 'Uploaden', 'pdk-theme-options' ), 'secondary', 'submit', false ); ?>
			</p>
		</form>
		<p class="description">
			<?php printf(
				esc_html__( 'Bestanden worden opgeslagen in: %s', 'pdk-theme-options' ),
				'<code>' . esc_html( $font_dir ) . '</code>'
			); ?>
		</p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab: Vakantiemodus
	// -------------------------------------------------------------------------

	private function render_tab_vacation(): void {
		$s = array_merge(
			PDK_Settings::get_defaults()['vacation_mode'],
			(array) PDK_Settings::get( 'vacation_mode' )
		);
		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Melding', 'pdk-theme-options' ); ?></th>
				<td>
					<textarea name="vacation_mode[message]" rows="4" class="large-text"><?php echo esc_textarea( $s['message'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'HTML is toegestaan.', 'pdk-theme-options' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Winkelwagen blokkeren', 'pdk-theme-options' ); ?></th>
				<td><label><input type="checkbox" name="vacation_mode[disable_add_to_cart]" value="1" <?php checked( $s['disable_add_to_cart'] ); ?>> <?php esc_html_e( '"In winkelwagen"-knoppen uitschakelen', 'pdk-theme-options' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Checkout blokkeren', 'pdk-theme-options' ); ?></th>
				<td><label><input type="checkbox" name="vacation_mode[disable_checkout]" value="1" <?php checked( $s['disable_checkout'] ); ?>> <?php esc_html_e( 'Afrekenproces uitschakelen (redirect naar winkel)', 'pdk-theme-options' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Periode', 'pdk-theme-options' ); ?></th>
				<td>
					<?php
					$periods_url = esc_url( add_query_arg(
						[ 'page' => self::PAGE_SLUG, 'tab' => 'site_settings' ],
						admin_url( 'admin.php' )
					) ) . '#afwijkende-dagen';

					$closures = class_exists( 'PDK_Site_Settings' )
						? array_filter( PDK_Site_Settings::periods(), static fn( array $p ): bool => $p['close_shop'] )
						: [];

					if ( $closures ) :
						?>
						<ul style="margin:0 0 8px;">
						<?php foreach ( $closures as $p ) : ?>
							<li>
								<strong><?php echo esc_html( $p['label'] ?: __( '(zonder omschrijving)', 'pdk-theme-options' ) ); ?></strong>
								— <?php echo esc_html( $p['from'] === $p['to'] ? $p['from'] : $p['from'] . ' t/m ' . $p['to'] ); ?>
							</li>
						<?php endforeach; ?>
						</ul>
						<p class="description">
							<?php
							printf(
								/* translators: %s: link naar Site Instellingen → Afwijkende dagen */
								esc_html__( 'De webshop sluit automatisch tijdens deze periodes. Beheren bij %s.', 'pdk-theme-options' ),
								'<a href="' . $periods_url . '">' . esc_html__( 'Site Instellingen → Afwijkende dagen', 'pdk-theme-options' ) . '</a>'
							);
							?>
						</p>
					<?php else : ?>
						<p class="description" style="color:#d63638;font-weight:600;">
							<?php esc_html_e( 'Er is geen periode met "Webshop sluiten" ingesteld — de webshop is daarom nú dicht zolang deze module aan staat.', 'pdk-theme-options' ); ?>
						</p>
						<p class="description">
							<?php
							printf(
								/* translators: %s: link naar Site Instellingen → Afwijkende dagen */
								esc_html__( 'Wil je vooruit plannen in plaats van direct sluiten? Voeg een periode toe bij %s en vink daar "Webshop sluiten" aan.', 'pdk-theme-options' ),
								'<a href="' . $periods_url . '">' . esc_html__( 'Site Instellingen → Afwijkende dagen', 'pdk-theme-options' ) . '</a>'
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab: Taalcontrole (inline — geen aparte Tools-pagina meer)
	// -------------------------------------------------------------------------

	private function render_tab_language_checker(): void {
		// Delegeer aan de Language Checker module als die geladen is.
		if ( class_exists( 'PDK_Language_Checker' ) ) {
			PDK_Language_Checker::render_inline();
		}
	}

	// -------------------------------------------------------------------------
	// Tab: Rechten
	// -------------------------------------------------------------------------

	// -------------------------------------------------------------------------
	// Libraries-tab: losse JS/CSS-bestanden (Glide.js, Swiper, Splide, …)
	// -------------------------------------------------------------------------

	/**
	 * Uploaden en aan/uit zetten van JS- en CSS-bestanden.
	 *
	 * Een JS-bestand uploaden is code op de site zetten, dus dat vraagt dezelfde
	 * rechten als de code-editor — niet alleen manage_options.
	 */
	private function render_tab_libraries(): void {
		$mag_uploaden = current_user_can( PDK_CAP_EDIT_CODE );
		$bestanden    = PDK_Libraries::scan();
		$ap_url       = esc_url( admin_url( 'admin-post.php' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$bewerken = basename( (string) wp_unslash( $_GET['edit'] ?? '' ) );

		if ( '' !== $bewerken && in_array( $bewerken, $bestanden, true ) ) {
			$this->render_library_editor( $bewerken );
			return;
		}
		?>
		<p>
			<?php esc_html_e( 'Upload hier kant-en-klare JS- en CSS-bestanden — bijvoorbeeld Glide.js, Swiper of Splide. Elk ingeschakeld bestand laadt op alle frontend-pagina\'s: CSS in de head, JS in de footer.', 'pdk-theme-options' ); ?>
		</p>
		<p class="description">
			<?php
			printf(
				/* translators: %s: map waarin de bestanden staan */
				esc_html__( 'De bestanden staan in %s, dus buiten de pluginmap: een plugin-update raakt ze niet. Laadvolgorde is alfabetisch — zet er een cijfer voor (10-swiper.min.js, 20-slider-init.js) als de volgorde uitmaakt.', 'pdk-theme-options' ),
				'<code>' . esc_html( PDK_Libraries::dir() ) . '</code>'
			);
			?>
		</p>

		<?php if ( ! $mag_uploaden ) : ?>
			<div class="notice notice-warning inline" style="margin:16px 0;">
				<p><?php esc_html_e( 'Je mag deze bestanden niet wijzigen. Uploaden en verwijderen vraagt code-editor rechten — zie de Rechten-tab.', 'pdk-theme-options' ); ?></p>
			</div>
		<?php else : ?>
			<form method="post" action="<?php echo $ap_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" enctype="multipart/form-data" style="margin:16px 0;">
				<?php wp_nonce_field( 'pdk_library_upload' ); ?>
				<input type="hidden" name="action" value="pdk_library_upload">
				<input type="file" name="pdk_library" accept=".js,.css" required>
				<?php submit_button( __( 'Uploaden', 'pdk-theme-options' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<?php if ( ! $bestanden ) : ?>
			<p><em><?php esc_html_e( 'Nog geen bestanden geüpload.', 'pdk-theme-options' ); ?></em></p>
			<?php return; ?>
		<?php endif; ?>

		<form method="post" action="<?php echo $ap_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<input type="hidden" name="action"  value="pdk_save_settings">
			<input type="hidden" name="pdk_tab" value="libraries">

			<table class="widefat striped" style="max-width:800px;">
				<thead>
					<tr>
						<th style="width:80px;"><?php esc_html_e( 'Laden', 'pdk-theme-options' ); ?></th>
						<th><?php esc_html_e( 'Bestand', 'pdk-theme-options' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( 'Grootte', 'pdk-theme-options' ); ?></th>
						<th style="width:110px;"><?php esc_html_e( 'Actie', 'pdk-theme-options' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $bestanden as $bestand ) : ?>
						<tr>
							<td>
								<input
									type="checkbox"
									name="libraries_enabled[]"
									value="<?php echo esc_attr( $bestand ); ?>"
									<?php checked( PDK_Libraries::is_enabled( $bestand ) ); ?>
									<?php disabled( ! $mag_uploaden ); ?>
								>
							</td>
							<td>
								<code><?php echo esc_html( $bestand ); ?></code>
								<?php if ( pdk_file_is_tampered( PDK_Libraries::rel_path( $bestand ) ) ) : ?>
									<span style="color:#b32d2e;font-weight:600;margin-left:8px;"><?php esc_html_e( 'gewijzigd buiten de editor — wordt niet geladen', 'pdk-theme-options' ); ?></span>
								<?php endif; ?>
								<a href="<?php echo esc_url( PDK_Libraries::url() . $bestand ); ?>" target="_blank" rel="noopener" style="margin-left:8px;"><?php esc_html_e( 'bekijk', 'pdk-theme-options' ); ?></a>
							</td>
							<td><?php echo esc_html( size_format( (int) filesize( PDK_Libraries::dir() . $bestand ) ) ); ?></td>
							<td>
								<?php if ( $mag_uploaden ) : ?>
									<a href="<?php echo esc_url( add_query_arg(
										[ 'page' => self::PAGE_SLUG, 'tab' => 'libraries', 'edit' => $bestand ],
										admin_url( 'admin.php' )
									) ); ?>"><?php esc_html_e( 'Bewerken', 'pdk-theme-options' ); ?></a>
									|
									<button
										type="submit"
										class="button-link delete"
										style="color:#b32d2e;"
										name="pdk_delete_file"
										value="<?php echo esc_attr( $bestand ); ?>"
										onclick="return confirm('<?php echo esc_js( __( 'Dit bestand definitief verwijderen?', 'pdk-theme-options' ) ); ?>');"
									><?php esc_html_e( 'Verwijderen', 'pdk-theme-options' ); ?></button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $mag_uploaden ) : ?>
				<?php submit_button( __( 'Opslaan', 'pdk-theme-options' ) ); ?>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Eén library-bestand bewerken, met dezelfde beveiliging als de code-tabs:
	 * vingerafdruk bij opslaan, .bak van de vorige versie, en niet uitserveren
	 * zodra het bestand buiten de editor om verandert.
	 */
	private function render_library_editor( string $bestand ): void {
		$terug = add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'tab' => 'libraries' ],
			admin_url( 'admin.php' )
		);

		$lang = 'css' === strtolower( pathinfo( $bestand, PATHINFO_EXTENSION ) ) ? 'css' : 'javascript';
		?>
		<p>
			<a href="<?php echo esc_url( $terug ); ?>">&larr; <?php esc_html_e( 'Terug naar de lijst', 'pdk-theme-options' ); ?></a>
		</p>
		<h2 style="margin-top:0;"><code><?php echo esc_html( $bestand ); ?></code></h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pdk-settings-form">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<input type="hidden" name="action"  value="pdk_save_settings">
			<input type="hidden" name="pdk_tab" value="libraries">
			<input type="hidden" name="pdk_edit_file" value="<?php echo esc_attr( $bestand ); ?>">

			<?php $this->render_tab_code_editor( PDK_Libraries::rel_path( $bestand ), $lang ); ?>

			<?php submit_button( __( 'Bestand opslaan', 'pdk-theme-options' ) ); ?>
		</form>
		<?php
	}

	private function save_libraries(): void {
		if ( ! current_user_can( PDK_CAP_EDIT_CODE ) ) {
			wp_die( esc_html__( 'Geen toegang.', 'pdk-theme-options' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$bewerkt = basename( (string) wp_unslash( $_POST['pdk_edit_file'] ?? '' ) );

		if ( '' !== $bewerkt ) {
			$this->save_library_file( $bewerkt, (string) wp_unslash( $_POST['file_content'] ?? '' ) );
			return;
		}
		// phpcs:enable

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// De verwijderknop zit in hetzelfde formulier — die heeft voorrang.
		$te_verwijderen = sanitize_text_field( wp_unslash( $_POST['pdk_delete_file'] ?? '' ) );

		if ( '' !== $te_verwijderen ) {
			$this->delete_library_file( $te_verwijderen );
			return;
		}

		$aangevinkt = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['libraries_enabled'] ?? [] ) );
		// phpcs:enable

		// Opgeslagen wordt wat NIET laadt, zodat een nieuwe upload meteen werkt.
		$uit = array_values( array_diff( PDK_Libraries::scan(), $aangevinkt ) );

		// Niet via PDK_Settings::update(): array_replace_recursive() voegt lijsten
		// per index samen, waardoor een weer ingeschakeld bestand uit zou blijven.
		$options                           = (array) get_option( PDK_Settings::OPTION_KEY, [] );
		$options['libraries']['disabled']  = $uit;
		update_option( PDK_Settings::OPTION_KEY, $options );
	}

	/** Schrijft de editor-inhoud weg en gaat terug naar dat bestand. */
	private function save_library_file( string $bestand, string $content ): void {
		$redirect = add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'tab' => 'libraries', 'edit' => $bestand ],
			admin_url( 'admin.php' )
		);

		if ( ! in_array( $bestand, PDK_Libraries::scan(), true ) ) {
			$this->redirect_with_error( __( 'Onbekend bestand.', 'pdk-theme-options' ), $redirect );
		}

		$result = pdk_write_storage_file( PDK_Libraries::rel_path( $bestand ), $content );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( $result->get_error_message(), $redirect );
		}

		wp_safe_redirect( add_query_arg( 'saved', '1', $redirect ) );
		exit;
	}

	public function handle_library_upload(): void {
		if ( ! current_user_can( PDK_CAP_EDIT_CODE ) ) {
			wp_die( esc_html__( 'Geen toegang.', 'pdk-theme-options' ), 403 );
		}

		check_admin_referer( 'pdk_library_upload' );

		$redirect = add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'tab' => 'libraries' ],
			admin_url( 'admin.php' )
		);

		if ( empty( $_FILES['pdk_library'] ) || UPLOAD_ERR_OK !== $_FILES['pdk_library']['error'] ) {
			$this->redirect_with_error( __( 'Geen bestand ontvangen of uploadfout.', 'pdk-theme-options' ), $redirect );
		}

		// Bewust geen sanitize_file_name(): die maakt van glide.min.js een
		// glide.min_.js. Alleen a-z, 0-9, punt, koppelteken en underscore
		// overhouden is hier genoeg — de extensie wordt hieronder afgedwongen.
		$naam = preg_replace( '/[^a-zA-Z0-9._-]/', '', basename( (string) $_FILES['pdk_library']['name'] ) );
		$ext  = strtolower( pathinfo( (string) $naam, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, PDK_Libraries::allowed_extensions(), true ) ) {
			$this->redirect_with_error(
				sprintf(
					/* translators: %s: toegestane extensies */
					__( 'Ongeldig bestandstype. Toegestaan: %s.', 'pdk-theme-options' ),
					implode( ', ', PDK_Libraries::allowed_extensions() )
				),
				$redirect
			);
		}

		// Alleen a-z, 0-9, punt, koppelteken en underscore — en de naam mag na het
		// schonen niet leeg zijn of met een punt beginnen (.htaccess-achtige namen).
		$naam = preg_replace( '/[^a-zA-Z0-9._-]/', '', $naam );

		if ( '' === (string) $naam || str_starts_with( (string) $naam, '.' ) ) {
			$this->redirect_with_error( __( 'Ongeldige bestandsnaam.', 'pdk-theme-options' ), $redirect );
		}

		pdk_ensure_storage_dir();

		if ( ! wp_mkdir_p( PDK_Libraries::dir() ) ) {
			$this->redirect_with_error( __( 'Kan de libraries-map niet aanmaken.', 'pdk-theme-options' ), $redirect );
		}

		if ( ! is_uploaded_file( $_FILES['pdk_library']['tmp_name'] )
			|| ! move_uploaded_file( $_FILES['pdk_library']['tmp_name'], PDK_Libraries::dir() . $naam ) ) {
			$this->redirect_with_error( __( 'Uploaden mislukt (schrijfrechten?).', 'pdk-theme-options' ), $redirect );
		}

		// Vingerafdruk van wat er net geüpload is — vanaf nu telt elke wijziging
		// buiten de editor om als manipulatie.
		pdk_store_file_hash(
			PDK_Libraries::rel_path( $naam ),
			(string) file_get_contents( PDK_Libraries::dir() . $naam ) // phpcs:ignore
		);

		wp_safe_redirect( add_query_arg( 'saved', '1', $redirect ) );
		exit;
	}

	/** Verwijdert één bestand uit de libraries-map en gaat terug naar de tab. */
	private function delete_library_file( string $bestand ): void {
		$redirect = add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'tab' => 'libraries' ],
			admin_url( 'admin.php' )
		);

		// Alleen bestanden die er echt staan — geen ../-trucs.
		if ( ! in_array( $bestand, PDK_Libraries::scan(), true ) ) {
			$this->redirect_with_error( __( 'Onbekend bestand.', 'pdk-theme-options' ), $redirect );
		}

		wp_delete_file( PDK_Libraries::dir() . $bestand );

		wp_safe_redirect( add_query_arg( 'saved', '1', $redirect ) );
		exit;
	}

	/** Terug naar de tab met een foutmelding. Beëindigt het request. */
	private function redirect_with_error( string $melding, string $redirect ): void {
		wp_safe_redirect( add_query_arg( 'error', rawurlencode( $melding ), $redirect ) );
		exit;
	}

	/**
	 * Security-tab: aanvinken welke plugins actief moeten blijven.
	 *
	 * De rest van de security-module (header-firewall, blacklists, MU-integriteit)
	 * heeft bewust geen instellingen — die staan vast in de code.
	 */
	private function render_tab_security(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$alle      = get_plugins();
		$vereist   = (array) ( PDK_Settings::get( 'security', 'required_plugins' ) ?: [] );
		$actief    = (array) get_option( 'active_plugins', [] );
		$ontbreekt = PDK_Security::missing_required_plugins();
		?>
		<p>
			<?php esc_html_e( 'Vink de plugins aan die op deze site altijd actief moeten blijven. Zodra er één uit gaat, krijgt de beheerder een mail en verschijnt er een melding in de admin.', 'pdk-theme-options' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'De plugin wordt niet automatisch heractiveerd — dat blijft een bewuste handeling.', 'pdk-theme-options' ); ?>
		</p>

		<?php if ( $ontbreekt ) : ?>
			<div class="notice notice-error inline" style="margin:16px 0;">
				<p>
					<?php
					printf(
						/* translators: %s: komma-gescheiden lijst met plugin-slugs */
						esc_html__( 'Nu niet actief: %s', 'pdk-theme-options' ),
						'<strong>' . esc_html( implode( ', ', $ontbreekt ) ) . '</strong>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<table class="form-table">
			<tr>
				<th style="width:220px;"><?php esc_html_e( 'Moeten actief blijven', 'pdk-theme-options' ); ?></th>
				<td>
					<?php foreach ( $alle as $basename => $data ) : ?>
						<?php $slug = strtok( $basename, '/' ); ?>
						<label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
							<input
								type="checkbox"
								name="security_required[]"
								value="<?php echo esc_attr( $slug ); ?>"
								<?php checked( in_array( $slug, $vereist, true ) ); ?>
							>
							<span>
								<?php echo esc_html( $data['Name'] ); ?>
								<code style="color:#888;"><?php echo esc_html( $slug ); ?></code>
								<?php if ( ! in_array( $basename, $actief, true ) ) : ?>
									<span style="color:#d63638;">— <?php esc_html_e( 'niet actief', 'pdk-theme-options' ); ?></span>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	private function save_security(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$posted = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['security_required'] ?? [] ) );
		// phpcs:enable

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Alleen slugs die echt geïnstalleerd zijn — meteen de sanitisatie.
		$installed = array_map( static fn( $basename ) => strtok( (string) $basename, '/' ), array_keys( get_plugins() ) );
		$vereist   = array_values( array_intersect( $installed, $posted ) );

		// Niet via PDK_Settings::update(): array_replace_recursive() voegt lijsten
		// per index samen, waardoor een uitgevinkte plugin zou blijven staan.
		$options                                  = (array) get_option( PDK_Settings::OPTION_KEY, [] );
		$options['security']['required_plugins']  = $vereist;
		update_option( PDK_Settings::OPTION_KEY, $options );
	}

	private function render_tab_permissions(): void {
		$all_users = get_users( [ 'orderby' => 'display_name' ] );
		$locked    = pdk_config_code_editors();
		?>
		<?php if ( null !== $locked ) : ?>
			<div class="notice notice-info inline" style="margin:0 0 16px;">
				<p>
					<strong><?php esc_html_e( 'Vastgezet in wp-config.php', 'pdk-theme-options' ); ?></strong> —
					<?php esc_html_e( 'de constante PDK_CODE_EDITORS bepaalt wie code mag bewerken. Niemand kan dat hier of via de database wijzigen; pas wp-config.php aan.', 'pdk-theme-options' ); ?>
				</p>
				<p><code>define( 'PDK_CODE_EDITORS', '<?php echo esc_html( implode( ',', $locked ) ); ?>' );</code></p>
			</div>
		<?php endif; ?>
		<p>
			<?php esc_html_e( 'Selecteer welke gebruikers de custom PHP-, CSS- en JS-bestanden mogen bewerken. Administrators krijgen deze rechten NIET automatisch — ze moeten hier expliciet worden aangewezen.', 'pdk-theme-options' ); ?>
		</p>
		<p class="description" style="color:#d63638;font-weight:600;">
			<?php esc_html_e( 'Beveiligingstip: wijs zo min mogelijk gebruikers aan. Code-toegang geeft directe schrijfrechten op de server.', 'pdk-theme-options' ); ?>
		</p>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Code-editors', 'pdk-theme-options' ); ?></th>
				<td>
					<?php foreach ( $all_users as $user ) : ?>
						<label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
							<input
								type="checkbox"
								name="allowed_users[]"
								value="<?php echo esc_attr( $user->ID ); ?>"
								<?php checked( user_can( $user->ID, PDK_CAP_EDIT_CODE ) ); ?>
								<?php disabled( null !== $locked ); ?>
							>
							<span>
								<?php echo esc_html( $user->display_name ); ?>
								<span style="color:#888;">( <?php echo esc_html( $user->user_email ); ?> — <?php echo esc_html( implode( ', ', $user->roles ) ); ?> )</span>
							</span>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>
		<?php
	}
}
