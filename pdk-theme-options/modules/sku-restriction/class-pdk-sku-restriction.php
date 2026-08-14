<?php
/**
 * Module: SKU Beperken & Valideren (WooCommerce)
 *
 * Beperkt SKU's tot a-z, A-Z, 0-9, punt en koppelteken. Spaties worden een
 * koppelteken, ongeldige tekens vervallen. Bij opslaan wordt de opgeschoonde
 * waarde op uniciteit gecontroleerd — botst die, dan blijft de oude SKU staan
 * en volgt een foutmelding.
 *
 * Eenmalige conversie van bestaande SKU's via WP-CLI:
 *   wp pdk sku-convert          (dry-run)
 *   wp pdk sku-convert --live   (schrijft de wijzigingen weg)
 */

defined( 'ABSPATH' ) || exit;

class PDK_SKU_Restriction {

	public function __construct( PDK_Loader $loader ) {
		// WooCommerce-functies zijn pas op plugins_loaded beschikbaar.
		add_action( 'plugins_loaded', [ $this, 'maybe_init' ], 20 );
	}

	public function maybe_init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'woocommerce_admin_process_product_object', [ $this, 'process_sku' ], 20, 1 );
		add_action( 'admin_footer', [ $this, 'output_input_filter' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'pdk sku-convert', [ $this, 'cli_convert' ] );
		}
	}

	/**
	 * Opschoonregel — één plek, gebruikt door de admin-opslag, de live-filter
	 * (als JS-equivalent) en de CLI-conversie.
	 *
	 * Toegestaan: a-z A-Z 0-9 . -
	 */
	public static function sanitize( string $sku ): string {
		$clean = preg_replace( '/\s+/', '-', $sku );            // spaties -> koppelteken
		$clean = preg_replace( '/[^a-zA-Z0-9.-]/', '', $clean ); // ongeldige tekens weg
		$clean = preg_replace( '/-+/', '-', $clean );            // dubbele koppeltekens -> één

		return trim( $clean, '-' );                              // begin/eind trimmen
	}

	// -------------------------------------------------------------------------
	// Opslaan in de admin
	// -------------------------------------------------------------------------

	/** @param \WC_Product $product */
	public function process_sku( $product ): void {
		$sku = $product->get_sku( 'edit' );

		if ( '' === $sku ) {
			return;
		}

		$clean = self::sanitize( $sku );

		// Niets veranderd? Dan heeft WooCommerce de uniciteit al gecontroleerd.
		if ( $clean === $sku ) {
			return;
		}

		// Uniciteit van de OPGESCHOONDE waarde controleren.
		if ( '' !== $clean && ! wc_product_has_unique_sku( $product->get_id(), $clean ) ) {
			$existing_id = wc_get_product_id_by_sku( $clean );

			// Oude SKU herstellen zodat er niets verkeerds wordt opgeslagen.
			$product->set_sku( (string) get_post_meta( $product->get_id(), '_sku', true ) );

			WC_Admin_Meta_Boxes::add_error(
				sprintf(
					/* translators: 1: opgeschoonde SKU, 2: product-ID dat die SKU al heeft */
					__( 'De ingevoerde SKU is na opschonen "%1$s", maar die bestaat al bij product #%2$d. Pas de SKU aan; het product is niet met deze SKU opgeslagen.', 'pdk-theme-options' ),
					esc_html( $clean ),
					$existing_id
				)
			);
			return;
		}

		$product->set_sku( $clean );
	}

	/** Live opschonen tijdens typen/plakken in het SKU-veld. */
	public function output_input_filter(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}
		?>
		<script>
		jQuery( function ( $ ) {
			$( document ).on( 'input', '#_sku', function () {
				var value = this.value;
				var clean = value
					.replace( /\s+/g, '-' )           // spaties -> koppelteken
					.replace( /[^a-zA-Z0-9.-]/g, '' ) // ongeldige tekens weg
					.replace( /-+/g, '-' );           // dubbele koppeltekens -> één
				// Geen trim tijdens typen — anders kun je geen '-' of '.' zetten.
				if ( clean !== value ) {
					this.value = clean;
				}
			} );
		} );
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// WP-CLI: eenmalige conversie van bestaande SKU's
	// -------------------------------------------------------------------------

	public function cli_convert( array $args, array $assoc_args ): void {
		global $wpdb;

		$live = isset( $assoc_args['live'] );

		// Alle producten én variaties met een niet-lege SKU.
		$rows = $wpdb->get_results(
			"SELECT p.ID, pm.meta_value AS sku
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sku'
			 WHERE p.post_type IN ('product', 'product_variation')
			   AND pm.meta_value <> ''"
		);

		WP_CLI::log( sprintf( '%d producten met een SKU gevonden. Modus: %s', count( $rows ), $live ? 'LIVE' : 'DRY-RUN' ) );

		$claimed   = []; // clean_sku => post_id (binnen deze run geclaimd)
		$changed   = 0;
		$unchanged = 0;
		$conflicts = 0;
		$emptied   = 0;

		foreach ( $rows as $row ) {
			$id       = (int) $row->ID;
			$original = $row->sku;
			$clean    = self::sanitize( $original );

			if ( $clean === $original ) {
				$unchanged++;
				continue;
			}

			// SKU zou leeg worden (bestond alleen uit ongeldige tekens).
			if ( '' === $clean ) {
				$emptied++;
				WP_CLI::warning( sprintf( '#%d: "%s" wordt na opschonen LEEG - overgeslagen.', $id, $original ) );
				continue;
			}

			$db_conflict  = ! wc_product_has_unique_sku( $id, $clean );
			$run_conflict = isset( $claimed[ $clean ] ) && $claimed[ $clean ] !== $id;

			if ( $db_conflict || $run_conflict ) {
				$conflicts++;
				$with = $run_conflict
					? '#' . $claimed[ $clean ] . ' (deze run)'
					: '#' . wc_get_product_id_by_sku( $clean ) . ' (database)';
				WP_CLI::warning( sprintf( '#%d: "%s" -> "%s" BOTST met %s - overgeslagen.', $id, $original, $clean, $with ) );
				continue;
			}

			$claimed[ $clean ] = $id;
			$changed++;
			WP_CLI::log( sprintf( '#%d: "%s" -> "%s"', $id, $original, $clean ) );

			if ( $live ) {
				$product = wc_get_product( $id );
				if ( $product ) {
					$product->set_sku( $clean );
					$product->save();
				}
			}
		}

		WP_CLI::log( '----------------------------------------' );
		WP_CLI::log( sprintf( 'Ongewijzigd : %d', $unchanged ) );
		WP_CLI::log( sprintf( 'Te wijzigen : %d', $changed ) );
		WP_CLI::log( sprintf( 'Conflicten  : %d (handmatig oplossen)', $conflicts ) );
		WP_CLI::log( sprintf( 'Leeg gewor. : %d (overgeslagen)', $emptied ) );

		if ( $live ) {
			WP_CLI::success( 'Conversie uitgevoerd.' );
		} else {
			WP_CLI::success( 'Dry-run klaar. Voer uit met --live om te schrijven.' );
		}
	}
}
