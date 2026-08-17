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
			'description'         => __( 'Overschrijft het gekozen klantbestand met de opgegeven inhoud. PHP wordt geweigerd bij een syntaxfout. Lees het bestand eerst — de vorige inhoud gaat verloren.', 'pdk-theme-options' ),
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
