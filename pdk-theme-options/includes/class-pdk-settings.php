<?php
/**
 * Centrale toegang tot plugin-opties.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Settings {

	const OPTION_KEY = 'pdk_theme_options';

	/**
	 * Modules die WooCommerce nodig hebben. Zonder actieve WooCommerce worden ze
	 * nooit geladen en tonen ze geen tab — dat voorkomt fatale fouten door
	 * ontbrekende WC-functies.
	 */
	const WC_MODULES = [ 'vacation_mode', 'delivery_time', 'sku_restriction' ];

	public static function module_requires_wc( string $module ): bool {
		return in_array( $module, self::WC_MODULES, true );
	}

	/** Module is ingeschakeld maar kan niet draaien (ontbrekende afhankelijkheid). */
	public static function module_unavailable( string $module ): bool {
		return self::module_requires_wc( $module ) && ! pdk_woocommerce_active();
	}

	/** @return mixed */
	public static function get( string $module = '', string $key = '' ) {
		$options = get_option( self::OPTION_KEY, [] );

		if ( '' === $module ) {
			return $options;
		}

		$module_opts = $options[ $module ] ?? [];

		if ( '' === $key ) {
			return $module_opts;
		}

		return $module_opts[ $key ] ?? null;
	}

	/** Controleert of een module actief is, met terugval op de standaardwaarde. */
	public static function is_module_enabled( string $module ): bool {
		// Afhankelijkheid ontbreekt → module telt als uit, ongeacht de instelling.
		if ( self::module_unavailable( $module ) ) {
			return false;
		}

		$defaults = self::get_defaults();
		$default  = $defaults[ $module ]['enabled'] ?? false;
		$saved    = self::get( $module, 'enabled' );

		return null !== $saved ? (bool) $saved : $default;
	}

	/** @return mixed */
	public static function get_with_default( string $module, string $key ) {
		$saved = self::get( $module, $key );

		if ( null !== $saved ) {
			return $saved;
		}

		return self::get_defaults()[ $module ][ $key ] ?? null;
	}

	public static function update( array $data ): bool {
		$current = get_option( self::OPTION_KEY, [] );
		$merged  = array_replace_recursive( $current, $data );

		return update_option( self::OPTION_KEY, $merged );
	}

	public static function get_defaults(): array {
		return [
			// ----------------------------------------------------------------
			// Site-brede instellingen (Carbon Fields migratie)
			// ----------------------------------------------------------------
			'site_settings' => [
				'favicon_url'         => '',
				'disable_page_editor' => false,
				'client_logo'         => '',
				'company_name'        => '',
				'company_street'      => '',
				'company_number'      => '',
				'company_zipcode'     => '',
				'company_city'        => '',
				'company_phone'       => '',
				'company_email'       => '',
				'social_facebook'     => '',
				'social_instagram'    => '',
				'social_linkedin'     => '',
				'social_twitter'      => '',
				'social_youtube'      => '',
				'social_tiktok'       => '',
				// Openingstijden per weekdag: 1 = maandag t/m 7 = zondag.
				'opening_hours'       => [
					1 => [ 'closed' => false, 'open' => '07:00', 'close' => '17:30' ],
					2 => [ 'closed' => false, 'open' => '07:00', 'close' => '17:30' ],
					3 => [ 'closed' => false, 'open' => '07:00', 'close' => '17:30' ],
					4 => [ 'closed' => false, 'open' => '07:00', 'close' => '17:30' ],
					5 => [ 'closed' => false, 'open' => '07:00', 'close' => '17:30' ],
					6 => [ 'closed' => false, 'open' => '08:00', 'close' => '12:30' ],
					7 => [ 'closed' => true,  'open' => '',      'close' => '' ],
				],
				/**
				 * Afwijkende periodes (kerst, zomerperiode, bedrijfsvakantie).
				 * Eén bron voor openingstijden, levertijden én vakantiemodus.
				 *
				 * Per rij: from/to (Y-m-d), label, open/close (leeg = gesloten),
				 * close_shop (webshop sluiten via de vakantiemodus).
				 */
				'periods'             => [],
			],

			// ----------------------------------------------------------------
			// Modules
			// ----------------------------------------------------------------
			'custom_functions' => [
				'enabled' => false,
			],
			'custom_css' => [
				'enabled' => false,
			],
			'custom_js' => [
				'enabled' => false,
			],
			'custom_fonts' => [
				'enabled'    => false,
				'display'    => 'swap',
				'css_output' => 'inline',
			],
			// Login page: geen configureerbare opties — vaste PDK-stijl.
			'login_page' => [
				'enabled' => false,
			],
			'vacation_mode' => [
				'enabled'             => false,
				'message'             => 'Wij zijn tijdelijk gesloten. Bedankt voor uw geduld.',
				'disable_checkout'    => true,
				'disable_add_to_cart' => true,
				// Periodes staan in site_settings.periods — zie PDK_Site_Settings.
			],
			'sku_restriction' => [
				'enabled' => false,
			],
			'delivery_time' => [
				'enabled'     => false,
				'days'        => [
					1 => [ 'enabled' => true,  'cutoff' => '22:00' ],
					2 => [ 'enabled' => true,  'cutoff' => '22:00' ],
					3 => [ 'enabled' => true,  'cutoff' => '22:00' ],
					4 => [ 'enabled' => true,  'cutoff' => '22:00' ],
					5 => [ 'enabled' => true,  'cutoff' => '22:00' ],
					6 => [ 'enabled' => false, 'cutoff' => '17:00' ],
					7 => [ 'enabled' => true,  'cutoff' => '17:00' ],
				],
				// Niet-verzenddagen komen uit site_settings.periods — zie PDK_Site_Settings.
				'text_before' => 'Voor {cutoff} uur besteld, {dag} verzonden (indien op voorraad)',
				'text_after'  => 'Na {cutoff} uur besteld? Verzending op {volgende_dag}.',
			],
			// critical_error_status heeft geen toggle — altijd actief.
			'language_checker' => [
				'enabled' => false,
			],
			'agent_abilities' => [
				'enabled' => false,
			],
		];
	}

	/**
	 * Modules die een toggle hebben in de admin.
	 * Critical Error Status wordt hier NIET opgenomen — altijd actief.
	 */
	public static function module_labels(): array {
		return [
			'custom_functions' => __( 'Custom PHP Functions', 'pdk-theme-options' ),
			'custom_css'       => __( 'Custom CSS', 'pdk-theme-options' ),
			'custom_js'        => __( 'Custom JavaScript', 'pdk-theme-options' ),
			'custom_fonts'     => __( 'Custom Fonts', 'pdk-theme-options' ),
			'login_page'       => __( 'Login Pagina (PDK-stijl)', 'pdk-theme-options' ),
			'vacation_mode'    => __( 'Vakantiemodus', 'pdk-theme-options' ),
			'delivery_time'    => __( 'Levertijden', 'pdk-theme-options' ),
			'sku_restriction'  => __( 'SKU Beperken & Valideren', 'pdk-theme-options' ),
			'language_checker' => __( 'Language Cleaner', 'pdk-theme-options' ),
			'agent_abilities'  => __( 'AI-agent toegang (MCP)', 'pdk-theme-options' ),
		];
	}

	public static function module_descriptions(): array {
		return [
			'custom_functions' => __( 'Eigen PHP-functies toevoegen zonder het thema te bewerken.', 'pdk-theme-options' ),
			'custom_css'       => __( 'Eigen CSS-stijlen laden op de frontend.', 'pdk-theme-options' ),
			'custom_js'        => __( 'Eigen JavaScript-code laden op de frontend.', 'pdk-theme-options' ),
			'custom_fonts'     => __( 'Lettertypen beheren vanuit de uploads/fonts/ map.', 'pdk-theme-options' ),
			'login_page'       => __( 'Vaste PDK-huisstijl toepassen op de WordPress-loginpagina. Geen aanpasbare instellingen.', 'pdk-theme-options' ),
			'vacation_mode'    => __( 'Webshop sluiten met een aangepaste melding, gepland via Afwijkende dagen (vereist WooCommerce).', 'pdk-theme-options' ),
			'delivery_time'    => __( 'Levertijd per weekdag met cutoff-tijd, shortcode [levertijd] (vereist WooCommerce).', 'pdk-theme-options' ),
			'sku_restriction'  => __( 'SKU\'s beperken tot a-z, A-Z, 0-9, punt en koppelteken; automatisch opschonen en duplicaten blokkeren (vereist WooCommerce).', 'pdk-theme-options' ),
			'language_checker' => __( 'Taalbestanden beheren en verweesde vertalingen opschonen.', 'pdk-theme-options' ),
			'agent_abilities'  => __( 'Eigen PHP, CSS en JS lees- en schrijfbaar maken voor een AI-agent via de Abilities API (WordPress 6.9+, bijvoorbeeld met de Agent Connector-plugin). De agent moet inloggen als gebruiker met code-editor rechten — zie de Rechten-tab.', 'pdk-theme-options' ),
		];
	}
}
