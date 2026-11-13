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

		// CSS Framework: alleen als die module draait.
		if ( class_exists( 'PDK_CSS_Framework' ) ) {
			$this->register_css_framework_abilities();
		}

		// Element Studio-bestanden: alleen als de module aan staat en Oxygen actief is.
		if ( class_exists( 'PDK_Oxygen_Elements' ) ) {
			$this->register_oxygen_abilities();
		}

		// SEO Health Check: alleen als de module zelf draait (niet als de losse plugin het doet).
		if ( class_exists( 'PDK_SEO_Health_Check' ) && PDK_SEO_Health_Check::is_active() ) {
			$this->register_seo_health_abilities();
		}
	}

	/**
	 * SEO Health Check: scanresultaten lezen, scans starten en gericht bewerken (meta,
	 * alt-tekst, koppen). Alles manage_options; schrijven ook edit_post op de post.
	 * Contenteditors weigeren builderpagina's (zie PDK_SEO_Health_Check::editable_reason).
	 * Normatief: docs/seo-health-check-brief.md §7.
	 */
	private function register_seo_health_abilities(): void {
		$admin    = static fn(): bool => current_user_can( 'manage_options' );
		$edit     = static fn( $input = null ): bool => self::seo_may_edit( (int) ( is_array( $input ) ? $input['post_id'] ?? 0 : 0 ) );
		$edit_alt = static fn( $input = null ): bool => self::seo_may_edit(
			(int) ( is_array( $input ) ? $input['attachment_id'] ?? 0 : 0 ),
			(int) ( is_array( $input ) ? $input['post_id'] ?? 0 : 0 )
		);

		$post_id  = [ 'type' => 'integer', 'minimum' => 1 ];
		$paging   = [
			'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
			'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
		];
		$object   = static function ( array $properties = [], array $required = [] ): array {
			$schema = [ 'type' => 'object', 'additionalProperties' => false ];
			if ( $properties ) {
				$schema['properties'] = $properties;
			} else {
				$schema['default'] = []; // zonder invoer: leeg object i.p.v. null (anders is de invoer ongeldig)
			}
			return $required ? $schema + [ 'required' => $required ] : $schema;
		};
		$register = function ( string $name, string $label, string $description, array $input, string $callback, $permission, bool $readonly, bool $destructive ): void {
			wp_register_ability( self::CATEGORY . '/' . $name, [
				'label'               => $label,
				'description'         => $description,
				'category'            => self::CATEGORY,
				'input_schema'        => $input,
				'output_schema'       => [ 'type' => 'object' ],
				'permission_callback' => $permission,
				'execute_callback'    => [ $this, $callback ],
				'meta'                => [
					'show_in_rest' => true,
					'mcp'          => [ 'public' => true ],
					// idempotent=false: de Abilities REST-route eist dan POST (idempotent+destructive wordt DELETE).
					'annotations'  => [ 'readonly' => $readonly, 'destructive' => $destructive, 'idempotent' => $readonly ],
				],
			] );
		};

		$register( 'seo-get-status', __( 'SEO-scanstatus', 'pdk-theme-options' ),
			__( 'Geeft de status van de SEO-scan (idle, running, done, cancelled), de voortgang, het totaal aantal pagina\'s, issues en de gemiddelde score, de gedetecteerde SEO-plugin (Yoast, Rank Math of geen) en of de scan op de achtergrond draait.', 'pdk-theme-options' ),
			$object(), 'seo_get_status', $admin, true, false );

		$register( 'seo-list-issues', __( 'SEO-issues tonen', 'pdk-theme-options' ),
			__( 'Geeft de issues van de laatste SEO-scan, gefilterd en gepagineerd. status: open (standaard), new (nieuw sinds de laatste volledige scan), unchanged of resolved (opgelost). editable_field zegt welk veld met seo-update-meta of seo-update-image-alt te herstellen is (seo_title, meta_description, alt); null betekent dat het in de content zit (koppen: seo-get-headings).', 'pdk-theme-options' ),
			$object( [
				'issue_type' => [ 'type' => 'string', 'enum' => array_keys( SEOHC_Issue_Types::all() ) ],
				'status'     => [ 'type' => 'string', 'enum' => [ 'open', 'new', 'unchanged', 'resolved' ] ],
				'post_id'    => $post_id,
				'severity'   => [ 'type' => 'string', 'enum' => [ SEOHC_Issue_Types::SEVERITY_ERROR, SEOHC_Issue_Types::SEVERITY_WARNING ] ],
			] + $paging ), 'seo_list_issues', $admin, true, false );

		$register( 'seo-list-pages', __( 'SEO-pagina-scores tonen', 'pdk-theme-options' ),
			__( 'Geeft de gescande pagina\'s met score (0-100), aantal issues en woorden, gefilterd op scoreband (good, fair, poor) of posttype, gepagineerd.', 'pdk-theme-options' ),
			$object( [
				'band'      => [ 'type' => 'string', 'enum' => array_keys( SEOHC_Repository::score_bands() ) ],
				'post_type' => [ 'type' => 'string' ],
			] + $paging ), 'seo_list_pages', $admin, true, false );

		$register( 'seo-get-scan-history', __( 'SEO-scanhistorie', 'pdk-theme-options' ),
			__( 'Geeft de samenvattingen van de afgeronde scans, oudste eerst: aantallen, wat er nieuw en opgelost was en de issues per type.', 'pdk-theme-options' ),
			$object(), 'seo_get_scan_history', $admin, true, false );

		$register( 'seo-get-launch-checks', __( 'Launch checks tonen', 'pdk-theme-options' ),
			__( 'Geeft de resultaten van de laatst uitgevoerde launch checks (id, phase, label, status, message). Voert de checks niet uit; zonder eerdere run is het resultaat leeg.', 'pdk-theme-options' ),
			$object(), 'seo_get_launch_checks', $admin, true, false );

		$register( 'seo-get-headings', __( 'Koppen van een pagina', 'pdk-theme-options' ),
			__( 'Geeft alle koppen (h1-h6) in de ruwe post_content in documentvolgorde: index, level, text, block en of de kop editable is (anders met reason: unsupported_block, builder_content, rendered_source, not_scannable, unfiltered_html of revisions_disabled). Gebruik de index en text voor seo-set-heading-level.', 'pdk-theme-options' ),
			$object( [ 'post_id' => $post_id ], [ 'post_id' ] ), 'seo_get_headings', $admin, true, false );

		$register( 'seo-start-scan', __( 'Volledige SEO-scan starten', 'pdk-theme-options' ),
			__( 'Start een volledige SEO-scan op de achtergrond. Geeft een fout als er al een scan loopt. Volg de voortgang met seo-get-status.', 'pdk-theme-options' ),
			$object(), 'seo_start_scan', $admin, false, false );

		$register( 'seo-rescan-post', __( 'Eén pagina opnieuw scannen', 'pdk-theme-options' ),
			__( 'Scant één gepubliceerde post opnieuw en geeft diens score en open issues terug.', 'pdk-theme-options' ),
			$object( [ 'post_id' => $post_id ], [ 'post_id' ] ), 'seo_rescan_post', $admin, false, false );

		$register( 'seo-update-meta', __( 'SEO-titel of meta description opslaan', 'pdk-theme-options' ),
			__( 'Slaat de SEO-titel of meta description van een post op via Yoast SEO of Rank Math (zonder een van beide volgt een fout). HTML wordt gestript; een lege waarde verwijdert het veld. Scant de post daarna opnieuw en geeft de resterende issues terug.', 'pdk-theme-options' ),
			$object( [
				'post_id' => $post_id,
				'field'   => [ 'type' => 'string', 'enum' => [ 'seo_title', 'meta_description' ] ],
				'value'   => [ 'type' => 'string', 'maxLength' => 500 ],
			], [ 'post_id', 'field', 'value' ] ), 'seo_update_meta', $edit, false, true );

		$register( 'seo-update-image-alt', __( 'Alt-tekst opslaan', 'pdk-theme-options' ),
			__( 'Zet de alt-tekst van een bijlage in de mediabibliotheek en, met post_id, ook op de <img> in de content van die post (alleen als de content bewerkbaar is; op builderpagina\'s blijft alleen de mediabibliotheek bijgewerkt en meldt content_skipped waarom). Maakt eerst een revisie en scant de post opnieuw.', 'pdk-theme-options' ),
			$object( [
				'attachment_id' => $post_id,
				'value'         => [ 'type' => 'string', 'maxLength' => 125 ],
				'post_id'       => $post_id,
			], [ 'attachment_id', 'value' ] ), 'seo_update_image_alt', $edit_alt, false, true );

		$register( 'seo-set-heading-level', __( 'Kopniveau wijzigen', 'pdk-theme-options' ),
			__( 'Wijzigt het niveau (h1-h6) van één kop in de ruwe post_content; alleen de tags (en bij core/heading het level-attribuut) veranderen. Lees eerst seo-get-headings: index en expected_text moeten kloppen, anders volgt een fout. Weigert builderpagina\'s en koppen buiten klassieke HTML of core/heading. Maakt eerst een revisie en scant opnieuw.', 'pdk-theme-options' ),
			$object( [
				'post_id'       => $post_id,
				'index'         => [ 'type' => 'integer', 'minimum' => 0 ],
				'expected_text' => [ 'type' => 'string' ],
				'level'         => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 6 ],
			], [ 'post_id', 'index', 'expected_text', 'level' ] ), 'seo_set_heading_level', $edit, false, true );

		$register( 'seo-insert-h1', __( 'H1 invoegen', 'pdk-theme-options' ),
			__( 'Voegt een H1 met de opgegeven tekst vooraan in de ruwe post_content in (als heading-blok bij blokcontent). Weigert als de pagina al een H1 heeft, als het thema de H1 zelf uitvoert, en op builderpagina\'s. Maakt eerst een revisie en scant opnieuw.', 'pdk-theme-options' ),
			$object( [
				'post_id' => $post_id,
				'text'    => [ 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 ],
			], [ 'post_id', 'text' ] ), 'seo_insert_h1', $edit, false, true );
	}

	/**
	 * CSS Framework: config en tokens lezen, config schrijven. Instellingen, geen code:
	 * daarom manage_options (net als de admin-tab) en niet PDK_CAP_EDIT_CODE. Dat is
	 * veilig omdat er alleen gevalideerde getallen en vaste namen in de CSS komen.
	 */
	private function register_css_framework_abilities(): void {
		$perm = static fn(): bool => current_user_can( 'manage_options' );
		$int  = [ 'type' => 'integer' ];
		$num  = [ 'type' => 'number' ];
		$str  = [ 'type' => 'string' ];

		wp_register_ability( self::CATEGORY . '/get-css-framework', [
			'label'               => __( 'CSS Framework lezen', 'pdk-theme-options' ),
			'description'         => __( 'Geeft de instellingen, alle tokens (naam en waarde), de beschikbare layoutklassen en de CSS-URL van het CSS Framework terug, plus of Oxygen en Modern Normalize aan staan.', 'pdk-theme-options' ),
			'category'            => self::CATEGORY,
			'input_schema'        => [ 'type' => 'object', 'additionalProperties' => false ],
			'output_schema'       => [ 'type' => 'object' ],
			'permission_callback' => $perm,
			'execute_callback'    => [ $this, 'get_css_framework' ],
			'meta'                => [
				'show_in_rest' => true,
				'mcp'          => [ 'public' => true ],
				'annotations'  => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
			],
		] );

		wp_register_ability( self::CATEGORY . '/update-css-framework', [
			'label'               => __( 'CSS Framework bijwerken', 'pdk-theme-options' ),
			'description'         => __( 'Werkt (een deel van) de instellingen van het CSS Framework bij: valideert, regenereert css-framework.css en synchroniseert de tokens naar Oxygen-variabelen (overschrijft daar de pdk-* variabelen). Ontbrekende sleutels houden hun waarde; bij een ongeldige waarde wordt niets opgeslagen.', 'pdk-theme-options' ),
			'category'            => self::CATEGORY,
			'input_schema'        => [
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'config' ],
				'properties'           => [
					'config' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => self::with_help( [
							'min_viewport' => $int, 'max_viewport' => $int, 'steps_up' => $int, 'steps_down' => $int,
							'font_min' => $num, 'font_max' => $num, 'ratio_min' => $num, 'ratio_max' => $num,
							'leading_body' => $num, 'leading_heading' => $num, 'grid_min' => $num, 'site_width' => $num, 'measure' => $num, 'lh_heading_ratio' => $num, 'lh_body_ratio' => $num,
							'gutter' => $str, 'popout_space' => $str, 'flow_space' => $str, 'flow_heading_space' => $str,
							'flow_enabled' => [ 'type' => 'boolean' ],
							'space_pairs'  => [ 'type' => 'array', 'items' => $str, 'maxItems' => 10 ],
						] ),
					],
				],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'permission_callback' => $perm,
			'execute_callback'    => [ $this, 'update_css_framework' ],
			'meta'                => [
				'show_in_rest' => true,
				'mcp'          => [ 'public' => true ],
				'annotations'  => [ 'readonly' => false, 'destructive' => true, 'idempotent' => true ],
			],
		] );
	}

	/** Zet de help uit PDK_CSS_Framework::fields() als description bij elke schema-eigenschap (één bron). */
	private static function with_help( array $properties ): array {
		foreach ( PDK_CSS_Framework::fields() as $key => $f ) {
			if ( isset( $properties[ $key ] ) ) {
				$properties[ $key ]['description'] = $f['help'];
			}
		}
		return $properties;
	}

	/** Tokens zonder de extra tabelkolommen: alleen name en value. */
	private static function css_tokens( array $config ): array {
		return array_map( static fn( $t ) => [ 'name' => $t['name'], 'value' => $t['value'] ], PDK_CSS_Framework::tokens( $config ) );
	}

	public function get_css_framework(): array {
		$config = PDK_CSS_Framework::config();

		return [
			'config'  => $config,
			'fields'  => PDK_CSS_Framework::fields(),
			'tokens'  => self::css_tokens( $config ),
			'classes' => PDK_CSS_Framework::CLASSES,
			'css_url' => PDK_STORAGE_URL . 'css-framework.css',
			'oxygen'  => [ 'active' => pdk_oxygen6_active(), 'modern_normalize' => PDK_CSS_Framework::modern_normalize_state() ],
		];
	}

	/** @return array|WP_Error */
	public function update_css_framework( $input ) {
		$new    = is_array( $input ) && is_array( $input['config'] ?? null ) ? $input['config'] : [];
		$result = PDK_CSS_Framework::save( $new );

		if ( ! $result['saved'] && isset( $result['errors']['file'] ) && 1 === count( $result['errors'] ) ) {
			return new WP_Error( 'pdk_file_write_failed', $result['errors']['file'], [ 'status' => 500, 'errors' => $result['errors'] ] );
		}
		if ( ! $result['saved'] ) {
			return new WP_Error(
				'pdk_invalid_config',
				__( 'Ongeldige configuratie, niets opgeslagen.', 'pdk-theme-options' ),
				[ 'errors' => $result['errors'] ]
			);
		}

		return [
			'saved'  => true,
			'config' => $result['config'],
			'tokens' => self::css_tokens( $result['config'] ),
			'oxygen' => $result['oxygen'],
		];
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

	// -------------------------------------------------------------------------
	// SEO Health Check-callbacks
	// -------------------------------------------------------------------------

	/** HTTP-status per foutcode voor de REST-route (zonder status geeft WP een 500). */
	private const SEO_STATUS = [
		'pdk_seo_not_found'          => 404,
		'ability_invalid_permissions' => 403,
		'seohc_forbidden'            => 403,
		'pdk_seo_scan_running'       => 409,
		'pdk_seo_heading_mismatch'   => 409,
		'pdk_seo_h1_exists'          => 409,
	];

	/** Zet data.status (404, 403, 409, anders 422: de aanvraag is geldig maar de post of staat staat het niet toe). */
	private static function seo_status( WP_Error $e ): WP_Error {
		if ( ! isset( $e->get_error_data()['status'] ) ) {
			$e->add_data( [ 'status' => self::SEO_STATUS[ $e->get_error_code() ] ?? 422 ] );
		}

		return $e;
	}

	private static function seo_error( string $code, string $message ): WP_Error {
		return self::seo_status( new WP_Error( $code, $message ) );
	}

	/**
	 * manage_options plus edit_post op elke opgegeven bestaande post. Een onbekende post
	 * laat de callback zelf als pdk_seo_not_found afhandelen. Staat ook in de schrijf-
	 * callbacks: een MCP-plugin kan de permission_callback vervangen (Agent Connector doet dat).
	 */
	private static function seo_may_edit( int ...$post_ids ): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		foreach ( $post_ids as $id ) {
			if ( $id && self::seo_post( $id ) && ! current_user_can( 'edit_post', $id ) ) {
				return false;
			}
		}

		return true;
	}

	/** Zelfde code als de Abilities API bij een geweigerde permission_callback. */
	private static function seo_forbidden(): WP_Error {
		return self::seo_error( 'ability_invalid_permissions', __( 'Je hebt geen rechten om deze post te bewerken.', 'pdk-theme-options' ) );
	}

	/** get_post() met een veilige 0: een ID <= 0 valt anders terug op de globale $post. */
	private static function seo_post( int $id ): ?WP_Post {
		return $id > 0 ? get_post( $id ) : null;
	}

	private static function seo_not_found(): WP_Error {
		return self::seo_error( 'pdk_seo_not_found', __( 'Deze post bestaat niet.', 'pdk-theme-options' ) );
	}

	/** Foutcode pdk_seo_<reden> voor een reden uit PDK_SEO_Health_Check::editable_reason(). */
	private static function seo_reason_error( string $reason ): WP_Error {
		$messages = [
			'not_scannable'      => __( 'Alleen gepubliceerde posts van de gescande posttypes zijn te bewerken (instelling Posttypes van SEO Health Check).', 'pdk-theme-options' ),
			'builder_content'    => __( 'De content van deze pagina staat niet in post_content (lege content of een builder zoals Oxygen, Elementor of Bricks). Pas de kop aan in de builder of de editor.', 'pdk-theme-options' ),
			'rendered_source'    => __( 'SEO Health Check scant de gerenderde pagina (instelling Content source = rendered), dus post_content is niet de bron. Pas de pagina in de editor of builder aan.', 'pdk-theme-options' ),
			'unfiltered_html'    => __( 'De huidige gebruiker mag geen ongefilterde HTML opslaan (unfiltered_html), dus WordPress zou de content bij het opslaan filteren.', 'pdk-theme-options' ),
			'revisions_disabled' => __( 'Revisies staan uit voor deze post, dus er kan geen herstelpunt worden gemaakt.', 'pdk-theme-options' ),
		];

		return self::seo_error( 'pdk_seo_' . $reason, $messages[ $reason ] ?? $reason );
	}

	/** Laadt de inline-edit-klasse; die staat normaal alleen in de admin geladen. */
	private static function seo_inline_edit(): void {
		if ( ! class_exists( 'SEOHC_Inline_Edit', false ) ) {
			require_once SEOHC_DIR . 'includes/admin/class-seohc-inline-edit.php';
		}
	}

	/** Scoreband-gegevens van één post, of null als hij niet (meer) gescand is. */
	private static function seo_score( int $post_id ): ?int {
		global $wpdb;
		$score = $wpdb->get_var( $wpdb->prepare( 'SELECT score FROM ' . SEOHC_Repository::pages_table() . ' WHERE post_id = %d', $post_id ) );

		return null === $score ? null : (int) $score;
	}

	/** Open issues van een post, kort: id, issue_type, details. */
	private static function seo_post_issues( int $post_id ): array {
		return array_map(
			static fn( $i ): array => [ 'id' => (int) $i->id, 'issue_type' => $i->issue_type, 'details' => $i->details ],
			SEOHC_Repository::get_issues( [ 'post_id' => $post_id, 'per_page' => 0 ] )
		);
	}

	/** Scant de post opnieuw (D18) en geeft score en open issues terug. */
	private static function seo_rescan( int $post_id ): array {
		if ( $post_id ) {
			SEOHC_Scan_Queue::scan_single( $post_id );
		}

		return [ 'post_id' => $post_id ?: null, 'score' => $post_id ? self::seo_score( $post_id ) : null, 'issues' => $post_id ? self::seo_post_issues( $post_id ) : [] ];
	}

	public function seo_get_status(): array {
		$state = SEOHC_Scan_Queue::get_state();

		return [
			'status'      => $state['status'],
			'processed'   => (int) $state['processed'],
			'total'       => (int) $state['total'],
			'started_at'  => $state['started_at'],
			'finished_at' => $state['finished_at'],
			'summary'     => SEOHC_Repository::summary(),
			'provider'    => SEOHC_SEO_Meta::provider_label(),
			'background'  => (bool) SEOHC_Scan_Queue::use_action_scheduler(),
		];
	}

	public function seo_list_issues( $input = null ): array {
		self::seo_inline_edit();
		$input  = is_array( $input ) ? $input : [];
		$status = (string) ( $input['status'] ?? 'open' );
		$args   = array_filter( [
			'issue_type' => $input['issue_type'] ?? '',
			'severity'   => $input['severity'] ?? '',
			'post_id'    => (int) ( $input['post_id'] ?? 0 ),
			'status'     => 'open' === $status ? '' : $status,
			'new_since'  => in_array( $status, [ 'new', 'unchanged' ], true ) ? SEOHC_Repository::scan_started_at() : '',
		] );
		$page     = (int) ( $input['page'] ?? 1 );
		$per_page = (int) ( $input['per_page'] ?? 20 );
		$fields   = SEOHC_Inline_Edit::fields_by_issue_type();

		$items = array_map(
			static fn( $i ): array => [
				'id'             => (int) $i->id,
				'post_id'        => (int) $i->post_id,
				'post_title'     => $i->post_title,
				'issue_type'     => $i->issue_type,
				'label'          => SEOHC_Issue_Types::label( $i->issue_type ),
				'severity'       => $i->severity,
				'details'        => $i->details,
				'object_id'      => (int) $i->object_id ?: null,
				'first_seen'     => $i->first_seen,
				'resolved_at'    => $i->resolved_at,
				'editable_field' => $fields[ $i->issue_type ] ?? null,
			],
			SEOHC_Repository::get_issues( $args + [ 'per_page' => $per_page, 'page' => $page ] )
		);

		return [ 'total' => SEOHC_Repository::count_issues( $args ), 'page' => $page, 'per_page' => $per_page, 'items' => $items ];
	}

	public function seo_list_pages( $input = null ): array {
		$input    = is_array( $input ) ? $input : [];
		$args     = array_filter( [ 'band' => $input['band'] ?? '', 'post_type' => $input['post_type'] ?? '' ] );
		$page     = (int) ( $input['page'] ?? 1 );
		$per_page = (int) ( $input['per_page'] ?? 20 );

		$items = array_map(
			static fn( $p ): array => [
				'post_id'        => (int) $p->post_id,
				'post_title'     => $p->post_title,
				'post_type'      => $p->post_type,
				'post_status'    => $p->post_status,
				'score'          => (int) $p->score,
				'band'           => SEOHC_Repository::band_for_score( (int) $p->score ),
				'previous_score' => null === $p->previous_score ? null : (int) $p->previous_score,
				'issue_count'    => (int) $p->issue_count,
				'word_count'     => (int) $p->word_count,
				'scanned_at'     => $p->scanned_at,
			],
			SEOHC_Repository::get_pages( $args + [ 'per_page' => $per_page, 'page' => $page ] )
		);

		return [ 'total' => SEOHC_Repository::count_pages( $args ), 'page' => $page, 'per_page' => $per_page, 'items' => $items ];
	}

	public function seo_get_scan_history(): array {
		return [ 'items' => SEOHC_Repository::run_history() ];
	}

	public function seo_get_launch_checks(): array {
		$saved = get_option( SEOHC_Launch_Checks::RESULTS_KEY );

		return is_array( $saved ) && isset( $saved['results'] )
			? [ 'time' => $saved['time'] ?? null, 'results' => array_values( (array) $saved['results'] ) ]
			: [ 'time' => null, 'results' => [] ];
	}

	/** @return array|WP_Error */
	public function seo_get_headings( $input ) {
		$post = self::seo_post( (int) ( $input['post_id'] ?? 0 ) );
		if ( ! $post ) {
			return self::seo_not_found();
		}

		// De reden geldt voor de hele post; een kop in een ander blok houdt zijn eigen reden.
		$reason   = PDK_SEO_Health_Check::editable_reason( $post );
		$headings = array_map(
			static function ( array $h ) use ( $reason ): array {
				if ( '' !== $reason ) {
					$h['editable'] = false;
					$h['reason']   = $reason;
				}
				return $h;
			},
			PDK_SEO_Health_Check::headings( $post->post_content )
		);

		return [ 'post_id' => $post->ID, 'editable' => '' === $reason, 'reason' => $reason ?: null, 'headings' => $headings ];
	}

	/** @return array|WP_Error */
	public function seo_start_scan() {
		if ( ! SEOHC_Scan_Queue::start() ) {
			return self::seo_error( 'pdk_seo_scan_running', __( 'Er loopt al een scan.', 'pdk-theme-options' ) );
		}

		return [ 'started' => true, 'status' => SEOHC_Scan_Queue::get_state()['status'] ];
	}

	/** @return array|WP_Error */
	public function seo_rescan_post( $input ) {
		$post = self::seo_post( (int) ( $input['post_id'] ?? 0 ) );
		if ( ! $post ) {
			return self::seo_not_found();
		}
		if ( ! SEOHC_Scanner::is_scannable( $post ) ) {
			return self::seo_reason_error( 'not_scannable' );
		}

		return [ 'rescanned' => true ] + self::seo_rescan( $post->ID );
	}

	/** @return array|WP_Error */
	public function seo_update_meta( $input ) {
		$post = self::seo_post( (int) $input['post_id'] );
		if ( ! $post ) {
			return self::seo_not_found();
		}
		if ( ! self::seo_may_edit( $post->ID ) ) {
			return self::seo_forbidden();
		}
		if ( 'none' === SEOHC_SEO_Meta::provider() ) {
			return self::seo_error( 'pdk_seo_no_seo_plugin', __( 'Activeer Yoast SEO of Rank Math om dit veld op te slaan.', 'pdk-theme-options' ) );
		}

		self::seo_inline_edit();
		$saved = SEOHC_Inline_Edit::save_meta( $post->ID, $input['field'], sanitize_text_field( $input['value'] ) );
		if ( is_wp_error( $saved ) ) {
			return self::seo_status( $saved );
		}

		return [ 'saved' => true ] + self::seo_rescan( $post->ID );
	}

	/** @return array|WP_Error */
	public function seo_update_image_alt( $input ) {
		$attachment = self::seo_post( (int) $input['attachment_id'] );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return self::seo_not_found();
		}

		$value   = sanitize_text_field( $input['value'] );
		$post    = ! empty( $input['post_id'] ) ? self::seo_post( (int) $input['post_id'] ) : null;
		$extra   = [];
		$post_id = 0;

		if ( ! empty( $input['post_id'] ) && ! $post ) {
			return self::seo_not_found();
		}
		if ( ! self::seo_may_edit( $attachment->ID, $post ? $post->ID : 0 ) ) {
			return self::seo_forbidden();
		}

		self::seo_inline_edit();

		if ( $post ) {
			$post_id = $post->ID;
			$reason  = PDK_SEO_Health_Check::editable_reason( $post );

			if ( '' === $reason ) {
				if ( false !== strpos( $post->post_content, '<img' ) ) {
					self::seo_ensure_revision( $post );
				}
			} else {
				$extra['content_skipped'] = $reason;
			}
		}

		// Post 0 laat de broncode de content overslaan: alleen de mediabibliotheek wordt bijgewerkt.
		$saved = SEOHC_Inline_Edit::save_alt( $attachment->ID, $value, $extra ? 0 : $post_id );
		if ( is_wp_error( $saved ) ) {
			return self::seo_status( $saved );
		}

		return [ 'saved' => true ] + self::seo_rescan( $post_id ) + $extra;
	}

	/** @return array|WP_Error */
	public function seo_set_heading_level( $input ) {
		return $this->seo_write_content( (int) $input['post_id'], static function ( string $content ) use ( $input ) {
			return PDK_SEO_Health_Check::set_heading_level( $content, (int) $input['index'], (string) $input['expected_text'], (int) $input['level'] );
		} );
	}

	/** @return array|WP_Error */
	public function seo_insert_h1( $input ) {
		$text = sanitize_text_field( $input['text'] );
		if ( '' === PDK_SEO_Health_Check::normalize_text( $text ) ) {
			return self::seo_error( 'pdk_seo_invalid_input', __( 'De tekst van de H1 is leeg na het verwijderen van HTML.', 'pdk-theme-options' ) );
		}

		return $this->seo_write_content( (int) $input['post_id'], static function ( string $content ) use ( $text ) {
			if ( SEOHC_Settings::get( 'theme_outputs_h1' ) ) {
				return new WP_Error( 'pdk_seo_theme_h1', __( 'Het thema voert zelf de H1 uit (instelling "theme outputs H1"), dus een H1 in de content zou er een tweede van maken.', 'pdk-theme-options' ) );
			}
			foreach ( PDK_SEO_Health_Check::headings( $content ) as $h ) {
				if ( 1 === $h['level'] ) {
					return new WP_Error( 'pdk_seo_h1_exists', __( 'Deze pagina heeft al een H1. Wijzig het niveau ervan met seo-set-heading-level.', 'pdk-theme-options' ) );
				}
			}

			return PDK_SEO_Health_Check::insert_h1( $content, $text );
		} );
	}

	/**
	 * Eén content-write (D20): controles in volgorde, daarna revisie, opslaan en rescan.
	 * Een fout wijzigt niets.
	 *
	 * @param callable(string): (string|WP_Error) $change Nieuwe content uit de huidige.
	 * @return array|WP_Error
	 */
	private function seo_write_content( int $post_id, callable $change ) {
		$post = self::seo_post( $post_id );
		if ( ! $post ) {
			return self::seo_not_found();
		}

		if ( ! self::seo_may_edit( $post->ID ) ) {
			return self::seo_forbidden();
		}

		$reason = PDK_SEO_Health_Check::editable_reason( $post );
		if ( '' !== $reason ) {
			return self::seo_reason_error( $reason );
		}

		$new = $change( $post->post_content );
		if ( is_wp_error( $new ) ) {
			return self::seo_status( $new );
		}

		if ( $new !== $post->post_content ) {
			self::seo_ensure_revision( $post );
			$result = wp_update_post( wp_slash( [ 'ID' => $post->ID, 'post_content' => $new ] ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return [ 'saved' => true ] + self::seo_rescan( $post->ID );
	}

	/** Zorgt dat er een revisie met de huidige content bestaat voordat die verandert (D20). */
	private static function seo_ensure_revision( WP_Post $post ): void {
		foreach ( wp_get_post_revisions( $post->ID ) as $revision ) {
			if ( $revision->post_content === $post->post_content ) {
				return;
			}
		}
		wp_save_post_revision( $post->ID );
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

		// Idem voor SEO Health Check.
		if ( class_exists( 'PDK_SEO_Health_Check' ) && PDK_SEO_Health_Check::is_active() ) {
			$oxygen['seo_health_check'] = __( 'Module seo_health_check is aan: seo-get-status, seo-list-issues, seo-list-pages, seo-get-scan-history, seo-get-launch-checks en seo-get-headings lezen scanresultaten; seo-start-scan en seo-rescan-post scannen; seo-update-meta, seo-update-image-alt, seo-set-heading-level en seo-insert-h1 bewerken posts (met een revisie en een rescan; builderpagina\'s worden geweigerd). Alleen voor beheerders.', 'pdk-theme-options' );
		}

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
				'css_framework' => __( 'css-framework.css (module CSS Framework): fluid tokens (--pdk-step-*, --pdk-space-*), basisstijlen voor h1-h6 en p, flow-marges tussen tekstelementen en de section als grid met de klassen .pdk-content, .pdk-popout(-start/-end), .pdk-wide-start/-end, .pdk-full(-start/-end), .pdk-grid, .pdk-container, .pdk-off en .pdk-flow-off. Lezen en wijzigen met get-css-framework en update-css-framework.', 'pdk-theme-options' ),
				'login_page'   => __( 'Vaste PDK-huisstijl op wp-login.php; geen frontend-uitvoer en niet configureerbaar.', 'pdk-theme-options' ),
				'product_cat'  => __( 'Productcategorieën hebben een extra term-meta short_description; opvragen met get_term_meta( $term_id, "short_description", true ).', 'pdk-theme-options' ),
			],
			'notes'            => __( 'Codeer bedrijfsgegevens, adressen en openingstijden nooit hard: gebruik bovenstaande helpers, dan blijft de klant ze zelf beheren via PDK Tools → Site Instellingen. De plugin levert geen frontend-CSS mee, behalve css-framework.css als de module CSS Framework aan staat — opmaak van de uitvoer hoort in custom-style.css, op de klassen uit frontend_output.', 'pdk-theme-options' ),
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
