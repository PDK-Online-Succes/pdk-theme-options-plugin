<?php
/**
 * Module: Custom PHP Functions
 *
 * Laadt het klantbestand custom-functions.php uit de storage-map en registreert
 * een reeks standaard WordPress-optimalisaties die voor alle PDK-sites van
 * toepassing zijn:
 *
 *  - Verwijder Gutenberg block library CSS op de frontend
 *  - Verwijder Dashicons voor niet-ingelogde bezoekers
 *  - Schakel automatische update-e-mails uit
 *  - SVG-uploads toestaan
 *  - Shortcodes: [pdk_year], [bloginfo]
 *  - WP File Manager deactiveren indien geactiveerd
 *  - WooCommerce: productcategorie-omschrijving, archief-titels, kassavalidatie
 */

defined( 'ABSPATH' ) || exit;

class PDK_Custom_Functions {

	public function __construct( PDK_Loader $loader ) {
		// Klantbestand laden.
		$loader->add_action( 'after_setup_theme', $this, 'load_custom_functions', 99 );

		// WordPress-optimalisaties via loader (methode-callbacks op $this).
		$loader->add_action( 'wp_enqueue_scripts', $this, 'dequeue_block_library_css' );
		$loader->add_action( 'wp_enqueue_scripts', $this, 'remove_dashicons_non_logged_in' );
		$loader->add_action( 'admin_init',         $this, 'deactivate_wp_file_manager' );
		$loader->add_filter( 'upload_mimes',       $this, 'allow_svg_uploads' );
		$loader->add_filter( 'wp_check_filetype_and_ext', $this, 'fix_svg_filetype', 10, 4 );

		// Automatische update-e-mails uitschakelen (directe WordPress-functie-callbacks).
		add_filter( 'auto_plugin_update_send_email',                        '__return_false' );
		add_filter( 'auto_theme_update_send_email',                         '__return_false' );
		add_filter( 'auto_core_update_send_email',                          '__return_false' );
		add_filter( 'wp_mail_smtp_reports_emails_summary_is_disabled',      '__return_true' );

		// Shortcodes.
		add_shortcode( 'pdk_year',  [ $this, 'shortcode_year' ] );
		add_shortcode( 'bloginfo',  [ $this, 'shortcode_bloginfo' ] );

		// WooCommerce: uitgesteld naar plugins_loaded prio 20.
		add_action( 'plugins_loaded', [ $this, 'init_woocommerce' ], 20 );
	}

	// -------------------------------------------------------------------------
	// Klantbestand
	// -------------------------------------------------------------------------

	public function load_custom_functions(): void {
		$file = PDK_STORAGE_DIR . 'custom-functions.php';

		if ( ! file_exists( $file ) ) {
			return;
		}

		// Integriteitscontrole: klopt de vingerafdruk niet meer, dan is het
		// bestand buiten de editor om gewijzigd. Niet laden — een backdoor die
		// zichzelf hier bijschrijft draait dan nooit. De admin krijgt een melding.
		if ( pdk_file_is_tampered( 'custom-functions.php' ) ) {
			return;
		}

		include_once $file;
	}

	// -------------------------------------------------------------------------
	// WordPress-optimalisaties
	// -------------------------------------------------------------------------

	public function dequeue_block_library_css(): void {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'wc-blocks-style' );
	}

	public function remove_dashicons_non_logged_in(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		wp_deregister_style( 'dashicons' );
	}

	public function allow_svg_uploads( array $mimes ): array {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
		return $mimes;
	}

	/** WordPress controleert SVG niet via finfo — goedkeuren op extensie. */
	public function fix_svg_filetype( array $data, string $file, string $filename, ?array $mimes ): array {
		if ( '' === $data['ext'] && '' === $data['type'] ) {
			$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			if ( 'svg' === $ext || 'svgz' === $ext ) {
				$data['ext']             = $ext;
				$data['type']            = 'image/svg+xml';
				$data['proper_filename'] = $filename;
			}
		}
		return $data;
	}

	public function deactivate_wp_file_manager(): void {
		if ( ! is_plugin_active( 'wp-file-manager/file_folder_manager.php' ) ) {
			return;
		}

		deactivate_plugins( 'wp-file-manager/file_folder_manager.php' );

		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'PDK Theme Options: WP File Manager is gedeactiveerd vanwege een beveiligingsrisico.', 'pdk-theme-options' );
			echo '</p></div>';
		} );
	}

	// -------------------------------------------------------------------------
	// Shortcodes
	// -------------------------------------------------------------------------

	public function shortcode_year(): string {
		return (string) date( 'Y' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
	}

	/** [bloginfo name="..."] */
	public function shortcode_bloginfo( array $atts ): string {
		$atts = shortcode_atts( [ 'name' => 'name' ], $atts, 'bloginfo' );
		return get_bloginfo( sanitize_key( $atts['name'] ) );
	}

	// -------------------------------------------------------------------------
	// WooCommerce-integratie (geladen als WC beschikbaar is)
	// -------------------------------------------------------------------------

	public function init_woocommerce(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Productcategorie korte omschrijving.
		add_action( 'product_cat_add_form_fields',  [ $this, 'wc_cat_add_field' ] );
		add_action( 'product_cat_edit_form_fields', [ $this, 'wc_cat_edit_field' ], 10 );
		add_action( 'created_product_cat',          [ $this, 'wc_cat_save_field' ], 10, 1 );
		add_action( 'edited_product_cat',           [ $this, 'wc_cat_save_field' ], 10, 1 );

		register_meta( 'term', 'short_description', [
			'object_subtype'    => 'product_cat',
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'wp_kses_post',
			'auth_callback'     => function () {
				return current_user_can( 'manage_product_terms' );
			},
			'show_in_rest'      => false,
		] );

		// Archieftitels opschonen.
		add_filter( 'get_the_archive_title', [ $this, 'clean_archive_title' ], 10 );

		// Kassavalidatie: adres moet een huisnummer bevatten.
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_checkout_address' ] );
	}

	/** Formulierveld toevoegen (nieuwe categorie). */
	public function wc_cat_add_field(): void {
		?>
		<div class="form-field">
			<label for="product_cat_short_description"><?php esc_html_e( 'Korte omschrijving', 'pdk-theme-options' ); ?></label>
			<textarea id="product_cat_short_description" name="product_cat_short_description" rows="5" cols="40"></textarea>
			<p class="description"><?php esc_html_e( 'Opvraagbaar via display_short_description() in het thema.', 'pdk-theme-options' ); ?></p>
		</div>
		<?php
	}

	/** Formulierveld tonen (bestaande categorie). */
	public function wc_cat_edit_field( \WP_Term $term ): void {
		$value = get_term_meta( $term->term_id, 'short_description', true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="product_cat_short_description"><?php esc_html_e( 'Korte omschrijving', 'pdk-theme-options' ); ?></label>
			</th>
			<td>
				<textarea id="product_cat_short_description" name="product_cat_short_description" rows="5" cols="40"><?php echo esc_textarea( $value ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Opvraagbaar via display_short_description() in het thema.', 'pdk-theme-options' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/** Meta opslaan na aanmaken of bewerken categorie. */
	public function wc_cat_save_field( int $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = isset( $_POST['product_cat_short_description'] )
			? wp_kses_post( wp_unslash( $_POST['product_cat_short_description'] ) )
			: '';

		update_term_meta( $term_id, 'short_description', $value );
	}

	/** Verwijder "Categorie:", "Tag:", "Auteur:" voorvoegsels uit archieftitels. */
	public function clean_archive_title( string $title ): string {
		return preg_replace( '/^[^:]+:\s*/', '', $title ) ?? $title;
	}

	/** Vereist dat een huisnummer aanwezig is in het factuuradres. */
	public function validate_checkout_address(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$address1 = sanitize_text_field( wp_unslash( $_POST['billing_address_1'] ?? '' ) );
		// phpcs:enable

		if ( $address1 && ! preg_match( '/\d/', $address1 ) ) {
			wc_add_notice(
				__( 'Vul een geldig adres in inclusief huisnummer.', 'pdk-theme-options' ),
				'error'
			);
		}
	}
}
