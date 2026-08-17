<?php
/**
 * Module: Agent Abilities (MCP)
 *
 * Stelt de klantbestanden custom-functions.php, custom-style.css en
 * custom-script.js beschikbaar aan AI-agents via de WordPress Abilities API
 * (WP 6.9+). Een MCP-server zoals Agent Connector of de WordPress MCP Adapter
 * publiceert geregistreerde abilities automatisch als MCP-tools — deze plugin
 * praat dus geen MCP-protocol zelf.
 *
 * Toegang loopt via dezelfde capability als de code-editor: PDK_CAP_EDIT_CODE.
 * De gebruiker waarmee de agent inlogt moet die rechten hebben (Rechten-tab).
 * Schrijven gaat via pdk_write_storage_file(), inclusief PHP-syntaxcontrole.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Agent_Abilities {

	/** Ability-input → bestandsnaam in de storage-map. */
	private const FILES = [
		'php' => 'custom-functions.php',
		'css' => 'custom-style.css',
		'js'  => 'custom-script.js',
	];

	const CATEGORY = 'pdk-theme-options';

	public function __construct( PDK_Loader $loader ) {
		// Deze hooks bestaan alleen als de Abilities API aanwezig is — dat is
		// de enige benodigde versiecontrole.
		$loader->add_action( 'wp_abilities_api_categories_init', $this, 'register_category' );
		$loader->add_action( 'wp_abilities_api_init',            $this, 'register_abilities' );
	}

	public function register_category(): void {
		wp_register_ability_category( self::CATEGORY, [
			'label'       => __( 'PDK Theme Options', 'pdk-theme-options' ),
			'description' => __( 'Eigen PHP, CSS en JavaScript van deze site.', 'pdk-theme-options' ),
		] );
	}

	public function register_abilities(): void {
		$file_prop = [
			'type'        => 'string',
			'enum'        => array_keys( self::FILES ),
			'description' => __( 'Welk klantbestand: php (custom-functions.php), css (custom-style.css) of js (custom-script.js).', 'pdk-theme-options' ),
		];

		wp_register_ability( self::CATEGORY . '/read-custom-code', [
			'label'               => __( 'Eigen code lezen', 'pdk-theme-options' ),
			'description'         => __( 'Geeft de volledige inhoud van het gekozen klantbestand (PHP, CSS of JS) terug.', 'pdk-theme-options' ),
			'category'            => self::CATEGORY,
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [ 'file' => $file_prop ],
				'required'   => [ 'file' ],
			],
			'output_schema'       => [
				'type'        => 'string',
				'description' => __( 'De inhoud van het bestand; leeg als het nog niet bestaat.', 'pdk-theme-options' ),
			],
			'permission_callback' => 'pdk_current_user_can_edit_code',
			'execute_callback'    => [ $this, 'read' ],
			'meta'                => [
				'show_in_rest' => true,
				'mcp'          => [ 'public' => true ],
				'annotations'  => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
			],
		] );

		wp_register_ability( self::CATEGORY . '/write-custom-code', [
			'label'               => __( 'Eigen code opslaan', 'pdk-theme-options' ),
			'description'         => __( 'Overschrijft het gekozen klantbestand met de opgegeven inhoud. PHP wordt geweigerd bij een syntaxfout. Lees het bestand eerst — de vorige inhoud gaat verloren. Vraag get-site-info op vóór het schrijven en hergebruik de bestaande helpers en shortcodes in plaats van bedrijfsgegevens, adressen of openingstijden hard te coderen.', 'pdk-theme-options' ),
			'category'            => self::CATEGORY,
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'file'    => $file_prop,
					'content' => [
						'type'        => 'string',
						'description' => __( 'De volledige nieuwe inhoud van het bestand.', 'pdk-theme-options' ),
					],
				],
				'required'   => [ 'file', 'content' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'saved' => [ 'type' => 'boolean' ],
					'bytes' => [ 'type' => 'integer' ],
				],
			],
			'permission_callback' => 'pdk_current_user_can_edit_code',
			'execute_callback'    => [ $this, 'write' ],
			'meta'                => [
				'show_in_rest' => true,
				'mcp'          => [ 'public' => true ],
				'annotations'  => [ 'readonly' => false, 'destructive' => true, 'idempotent' => true ],
			],
		] );

		wp_register_ability( self::CATEGORY . '/get-site-info', [
			'label'               => __( 'Site-informatie en beschikbare helpers', 'pdk-theme-options' ),
			'description'         => __( 'Geeft de ingestelde bedrijfsgegevens, social media, openingstijden en afwijkende periodes van deze site terug, plus álle frontend-uitvoer die de plugin kan renderen: per shortcode en template-hook de HTML-opbouw met CSS-klassen én de HTML zoals die er nu uitkomt. Gebruik dit vóór het schrijven van PHP, CSS of JS, zodat gegevens uit de instellingen komen, bestaande shortcodes hergebruikt worden en CSS op de juiste klassen zit.', 'pdk-theme-options' ),
			'category'            => self::CATEGORY,
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [ 'type' => 'object' ],
			'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			'execute_callback'    => [ $this, 'site_info' ],
			'meta'                => [
				'show_in_rest' => true,
				'mcp'          => [ 'public' => true ],
				'annotations'  => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Callbacks
	// -------------------------------------------------------------------------

	/** @return string|WP_Error */
	public function read( $input ) {
		$filename = self::filename( $input );

		if ( is_wp_error( $filename ) ) {
			return $filename;
		}

		$path = PDK_STORAGE_DIR . $filename;

		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/** @return array|WP_Error */
	public function write( $input ) {
		$filename = self::filename( $input );

		if ( is_wp_error( $filename ) ) {
			return $filename;
		}

		$content = (string) ( $input['content'] ?? '' );
		$result  = pdk_write_storage_file( $filename, $content );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [ 'saved' => true, 'bytes' => strlen( $content ) ];
	}

	/**
	 * Alles wat een agent nodig heeft om de site te begrijpen: de ingevulde
	 * gegevens én de bestaande API om ze op te vragen. Eén ability, één ronde.
	 */
	public function site_info(): array {
		$company = [];
		$social  = [];

		// Sleutels uit de defaults halen — geen tweede lijst om bij te houden.
		foreach ( array_keys( PDK_Settings::get_defaults()['site_settings'] ) as $key ) {
			$is_company = str_starts_with( $key, 'company_' );

			if ( ! $is_company && ! str_starts_with( $key, 'social_' ) ) {
				continue; // openingstijden en periodes komen hieronder, als array.
			}

			$value = (string) PDK_Settings::get_with_default( 'site_settings', $key );

			if ( $is_company ) {
				$company[ substr( $key, 8 ) ] = $value;
			} elseif ( '' !== $value ) {
				$social[ substr( $key, 7 ) ] = $value;
			}
		}

		$labels = pdk_day_labels();
		$hours  = [];

		foreach ( PDK_Site_Settings::opening_hours() as $index => $day ) {
			$hours[] = [
				'day'    => $labels[ $index ] ?? (string) $index,
				'closed' => ! empty( $day['closed'] ) || '' === $day['open'] || '' === $day['close'],
				'open'   => $day['open'],
				'close'  => $day['close'],
			];
		}

		$modules = [];
		foreach ( PDK_Settings::module_labels() as $key => $label ) {
			$modules[ $key ] = PDK_Settings::is_module_enabled( $key );
		}

		return [
			'company'          => $company + [
				'address_formatted' => wp_specialchars_decode( pdk_company_address() ),
				'logo_url'          => pdk_client_logo_url(),
			],
			'social'           => $social,
			'opening_hours'    => $hours,
			'periods'          => PDK_Site_Settings::periods(),
			'today'            => [
				'date'         => current_time( 'Y-m-d' ),
				'closed'       => PDK_Site_Settings::is_closed_on(),
				'period'       => PDK_Site_Settings::active_period(),
				'shop_closed'  => PDK_Site_Settings::shop_closed_now(),
			],
			'modules_enabled'  => $modules,
			'php_helpers'      => [
				'pdk_site_setting( $key )' => __( 'Eén klantgegeven, ge-escaped. Sleutels: company_name, company_street, company_number, company_zipcode, company_city, company_phone, company_email, social_facebook, social_instagram, social_linkedin, social_twitter, social_youtube, social_tiktok.', 'pdk-theme-options' ),
				'pdk_company_address()'    => __( 'Volledig adres als "Kerkstraat 12, 1234 AB Amsterdam".', 'pdk-theme-options' ),
				'pdk_client_logo_url()'    => __( 'URL van het klantlogo.', 'pdk-theme-options' ),
				'pdk_opening_hours_html( $title )' => __( 'Openingstijden-tabel als HTML, inclusief afwijkende periodes.', 'pdk-theme-options' ),
				'PDK_Site_Settings::opening_hours()'   => __( 'Openingstijden per weekdag, 1 = maandag t/m 7 = zondag.', 'pdk-theme-options' ),
				'PDK_Site_Settings::periods()'         => __( 'Alle afwijkende periodes, oplopend op begindatum.', 'pdk-theme-options' ),
				'PDK_Site_Settings::active_period( $ymd )' => __( 'De periode die op een datum geldt, of null.', 'pdk-theme-options' ),
				'PDK_Site_Settings::is_closed_on( $ymd )'  => __( 'Is de zaak op die datum gesloten?', 'pdk-theme-options' ),
			],
			'frontend_output'  => self::frontend_output(),
			'shortcodes'       => [
				'[pdk_year]'            => __( 'Huidig jaartal (module Custom PHP Functions).', 'pdk-theme-options' ),
				'[bloginfo name="..."]' => __( 'Waarde uit get_bloginfo() (module Custom PHP Functions).', 'pdk-theme-options' ),
			],
			'other_output'     => [
				'favicon'      => __( '<link rel="icon"> in de <head>, uit de favicon-instelling.', 'pdk-theme-options' ),
				'custom_css'   => __( 'custom-style.css wordt op de frontend ingeladen als stylesheet-handle pdk-custom-style, met filemtime als versie. Alleen als de module Custom CSS aan staat en het bestand niet leeg is.', 'pdk-theme-options' ),
				'custom_js'    => __( 'custom-script.js wordt in de footer ingeladen als script-handle pdk-custom-script. Alleen als de module Custom JavaScript aan staat en het bestand niet leeg is.', 'pdk-theme-options' ),
				'custom_fonts' => __( '@font-face-blok in wp_head (prioriteit 5), inline of als bestand pdk-custom-fonts.css. Gebruik de font-family-namen in Custom CSS.', 'pdk-theme-options' ),
				'login_page'   => __( 'Vaste PDK-huisstijl op wp-login.php; geen frontend-uitvoer en niet configureerbaar.', 'pdk-theme-options' ),
				'product_cat'  => __( 'Productcategorieën hebben een extra term-meta short_description; opvragen met get_term_meta( $term_id, "short_description", true ).', 'pdk-theme-options' ),
			],
			'notes'            => __( 'Codeer bedrijfsgegevens, adressen en openingstijden nooit hard: gebruik bovenstaande helpers, dan blijft de klant ze zelf beheren via PDK Tools → Site Instellingen. De plugin levert geen frontend-CSS mee — opmaak van de uitvoer hoort in custom-style.css, op de klassen uit frontend_output.', 'pdk-theme-options' ),
		];
	}

	/**
	 * Alle frontend-uitvoer die modules via pdk_register_frontend_output()
	 * hebben aangemeld: shortcode, template-hook, de HTML-opbouw én wat het
	 * op dit moment daadwerkelijk teruggeeft. Alleen ingeschakelde modules
	 * staan erin — een shortcode van een uitgeschakelde module werkt niet.
	 */
	private static function frontend_output(): array {
		$output = [];

		foreach ( pdk_frontend_outputs() as $name => $item ) {
			$html = (string) call_user_func( $item['render'], [] );

			$output[ $name ] = [
				'shortcode'     => '[' . $name . ']',
				'template_hook' => "do_action( 'pdk_" . $name . "' )",
				'markup'        => $item['markup'],
				// Live gerenderd: zo ziet de agent de échte HTML, niet een
				// beschrijving ervan. Leeg betekent dat de uitvoer context
				// nodig heeft (een productpagina) of nu niets te melden heeft.
				'html_now'      => $html,
			];
		}

		return $output;
	}

	/** @return string|WP_Error */
	private static function filename( $input ) {
		$key = is_array( $input ) ? ( $input['file'] ?? '' ) : (string) $input;

		if ( ! isset( self::FILES[ $key ] ) ) {
			return new WP_Error(
				'pdk_unknown_file',
				sprintf(
					/* translators: %s: toegestane waarden */
					__( 'Onbekend bestand. Kies een van: %s.', 'pdk-theme-options' ),
					implode( ', ', array_keys( self::FILES ) )
				)
			);
		}

		return self::FILES[ $key ];
	}
}
