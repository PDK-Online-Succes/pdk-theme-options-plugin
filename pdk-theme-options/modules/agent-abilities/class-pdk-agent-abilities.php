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

		// Element Studio-bestanden: alleen als de module aan staat en Oxygen actief is.
		if ( class_exists( 'PDK_Oxygen_Elements' ) ) {
			$this->register_oxygen_abilities();
		}
	}

	/** Lezen en bewerken van bestaande Element Studio-bestanden; aanmaken kan hier bewust niet. */
	private function register_oxygen_abilities(): void {
		$address = [
			'path'    => [
				'type'        => 'string',
				'description' => __( 'Pad zoals list-oxygen-elements het toont: elements/<Naam>/<bestand>, macros/<naam>.twig of presets/<Naam>.php.', 'pdk-theme-options' ),
			],
			'element' => [
				'type'        => 'string',
				'description' => __( 'Alternatief voor path: de mapnaam van het element. Combineer met file.', 'pdk-theme-options' ),
			],
			'file'    => [
				'type'        => 'string',
				'enum'        => PDK_Oxygen_Elements::ELEMENT_FILES,
				'description' => __( 'Alternatief voor path: het bestand binnen het element.', 'pdk-theme-options' ),
			],
		];
		$meta = static fn( bool $readonly ): array => [
			'show_in_rest' => true,
			'mcp'          => [ 'public' => true ],
			'annotations'  => [ 'readonly' => $readonly, 'destructive' => ! $readonly, 'idempotent' => true ],
		];

		wp_register_ability( self::CATEGORY . '/list-oxygen-elements', [
			'label'               => __( 'Oxygen-elementen tonen', 'pdk-theme-options' ),
			'description'         => __( "Geeft de Element Studio-elementen, macro's en presets van deze site terug, met per element de aanwezige bestanden. Begin hier. Elementen kunnen niet via deze API worden aangemaakt: de instructies in het resultaat leggen uit hoe dat in de Oxygen-builder gaat.", 'pdk-theme-options' ),
			'category'            => self::CATEGORY,
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [ 'type' => 'object' ],
			'permission_callback' => 'pdk_current_user_can_edit_code',
			'execute_callback'    => [ $this, 'list_oxygen' ],
			'meta'                => $meta( true ),
		] );

		wp_register_ability( self::CATEGORY . '/read-oxygen-element-file', [
			'label'               => __( 'Oxygen-elementbestand lezen', 'pdk-theme-options' ),
			'description'         => __( 'Geeft de inhoud van een bestaand Element Studio-bestand terug. Bestaat het element nog niet, dan volgt een fout: maak het dan eerst aan in de Oxygen-builder.', 'pdk-theme-options' ),
			'category'            => self::CATEGORY,
			'input_schema'        => [ 'type' => 'object', 'properties' => $address ],
			'output_schema'       => [ 'type' => 'object', 'properties' => [ 'path' => [ 'type' => 'string' ], 'content' => [ 'type' => 'string' ] ] ],
			'permission_callback' => 'pdk_current_user_can_edit_code',
			'execute_callback'    => [ $this, 'read_oxygen' ],
			'meta'                => $meta( true ),
		] );

		wp_register_ability( self::CATEGORY . '/write-oxygen-element-file', [
			'label'               => __( 'Oxygen-elementbestand opslaan', 'pdk-theme-options' ),
			'description'         => __( 'Overschrijft een bestaand Element Studio-bestand. element.php wordt geweigerd bij een syntaxfout (Twig wordt niet gecontroleerd). Lees het bestand eerst. Bestaat het element nog niet, dan volgt een fout: maak het eerst aan in de Oxygen-builder. Open het element daarna opnieuw in Element Studio om het resultaat te zien.', 'pdk-theme-options' ),
			'category'            => self::CATEGORY,
			'input_schema'        => [
				'type'       => 'object',
				'properties' => $address + [
					'content' => [ 'type' => 'string', 'description' => __( 'De volledige nieuwe inhoud van het bestand.', 'pdk-theme-options' ) ],
				],
				'required'   => [ 'content' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [ 'saved' => [ 'type' => 'boolean' ], 'path' => [ 'type' => 'string' ], 'bytes' => [ 'type' => 'integer' ] ],
			],
			'permission_callback' => 'pdk_current_user_can_edit_code',
			'execute_callback'    => [ $this, 'write_oxygen' ],
			'meta'                => $meta( false ),
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

	public function list_oxygen(): array {
		$found    = PDK_Oxygen_Elements::scan();
		$elements = [];

		foreach ( $found['element'] as $name ) {
			$elements[] = [
				'name'  => $name,
				'slug'  => PDK_Oxygen_Elements::slug( $name ),
				'files' => array_values( array_filter(
					PDK_Oxygen_Elements::ELEMENT_FILES,
					static fn( string $f ): bool => is_file( PDK_Oxygen_Elements::dir() . 'elements/' . $name . '/' . $f )
				) ),
			];
		}

		return [
			'elements'     => $elements,
			'macros'       => $found['macro'],
			'presets'      => $found['preset'],
			'dir'          => PDK_Oxygen_Elements::dir(),
			'instructions' => __( 'Elementen kunnen niet via deze API worden aangemaakt. Maak een nieuw element aan in de Oxygen-builder: Element Studio (Ctrl+Alt+E of het ⋯-menu → Element Studio) → New element → opslaglocatie "PDK Elementen". Roep daarna deze ability opnieuw aan en bewerk de bestanden hier. Open het element na het opslaan opnieuw in Element Studio om het resultaat te zien. element.php wordt bij het opslaan op PHP-syntaxfouten gecontroleerd, Twig niet.', 'pdk-theme-options' ),
		];
	}

	/** @return array|WP_Error */
	public function read_oxygen( $input ) {
		$rel = self::oxygen_rel( $input );

		if ( is_wp_error( $rel ) ) {
			return $rel;
		}

		return [ 'path' => $rel, 'content' => (string) file_get_contents( PDK_STORAGE_DIR . $rel ) ];
	}

	/** @return array|WP_Error */
	public function write_oxygen( $input ) {
		$rel = self::oxygen_rel( $input );

		if ( is_wp_error( $rel ) ) {
			return $rel;
		}

		$content = (string) ( $input['content'] ?? '' );
		$result  = pdk_write_storage_file( $rel, $content );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [ 'saved' => true, 'path' => $rel, 'bytes' => strlen( $content ) ];
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

		// Eén regel, alleen als de Element Studio-module draait.
		$oxygen = class_exists( 'PDK_Oxygen_Elements' )
			? [ 'oxygen_elements' => __( 'Module oxygen_elements is aan: list-oxygen-elements, read-oxygen-element-file en write-oxygen-element-file bewerken bestaande Element Studio-elementen; nieuwe maak je in de Oxygen-builder.', 'pdk-theme-options' ) ]
			: [];

		return $oxygen + [
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

	/**
	 * De poort: vertaalt path of element+file naar een bestaand bestand
	 * (`oxygen-elements/...`). Bestaat het element nog niet, dan stopt de agent
	 * hier met de opdracht het eerst in de builder aan te maken; we maken nooit
	 * zelf mappen of bestanden aan.
	 *
	 * @return string|WP_Error
	 */
	private static function oxygen_rel( $input ) {
		$input = is_array( $input ) ? $input : [];
		$edit  = (string) ( $input['path'] ?? '' );

		if ( '' === $edit && isset( $input['element'], $input['file'] ) ) {
			$edit = 'elements/' . $input['element'] . '/' . $input['file'];
		}

		$edit = str_replace( '\\', '/', $edit );
		$p    = PDK_Oxygen_Elements::parse( $edit );

		if ( null === $p ) {
			return new WP_Error( 'pdk_invalid_path', __( 'Ongeldig pad. Gebruik elements/<Naam>/<bestand>, macros/<naam>.twig of presets/<Naam>.php, of element + file.', 'pdk-theme-options' ) );
		}

		if ( is_file( PDK_Oxygen_Elements::dir() . $edit ) ) {
			return $p['rel'];
		}

		// Geldig, maar niet aanwezig: bepaal waarom.
		if ( 'element' !== $p['type'] || ! is_dir( PDK_Oxygen_Elements::dir() . 'elements/' . $p['name'] ) ) {
			return new WP_Error(
				'element_not_found',
				sprintf(
					/* translators: %s: element-, macro- of presetnaam */
					__( "Element '%s' bestaat nog niet. Maak het eerst aan in de Oxygen-builder: Element Studio → New element → opslaglocatie 'PDK Elementen'. Roep daarna list-oxygen-elements opnieuw aan en ga dan verder met bewerken.", 'pdk-theme-options' ),
					$p['name']
				)
			);
		}

		return new WP_Error(
			'file_not_found',
			sprintf(
				/* translators: %s: bestandspad */
				__( "Bestand '%s' bestaat niet. Bestanden worden hier alleen bewerkt, niet aangemaakt: maak ze aan in Element Studio.", 'pdk-theme-options' ),
				$edit
			)
		);
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
