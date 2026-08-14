<?php
/**
 * Globale hulpfuncties voor de PDK Theme Options plugin.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Controleert of de huidige gebruiker de code-editor capability heeft.
 *
 * Bewust GEEN administrator-fallback: ook beheerders mogen code-bestanden
 * niet bewerken tenzij de capability expliciet aan hen is toegekend via
 * de Rechten-tab. Dit beperkt de schade bij een gecompromitteerd beheerders-
 * account.
 */
function pdk_current_user_can_edit_code(): bool {
	return current_user_can( PDK_CAP_EDIT_CODE );
}

/**
 * Maakt de storage-map aan als die nog niet bestaat en beveiligt hem.
 */
function pdk_ensure_storage_dir(): bool {
	$dir = PDK_STORAGE_DIR;

	if ( ! is_dir( $dir ) ) {
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
	}

	$htaccess = $dir . '.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		file_put_contents(
			$htaccess,
			"Options -Indexes\n<FilesMatch \"\\.php$\">\n  Deny from all\n</FilesMatch>\n"
		);
	}

	$index = $dir . 'index.php';
	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, "<?php // Silence is golden.\n" );
	}

	return true;
}

/**
 * Maakt een leeg bestand aan in de storage-map als het nog niet bestaat.
 */
function pdk_maybe_create_storage_file( string $filename, string $placeholder = '' ): bool {
	pdk_ensure_storage_dir();
	$path = PDK_STORAGE_DIR . $filename;

	if ( ! file_exists( $path ) ) {
		return false !== file_put_contents( $path, $placeholder );
	}

	return true;
}

/**
 * Schrijft naar een storage-bestand met capability-check.
 *
 * @return true|\WP_Error
 */
function pdk_write_storage_file( string $filename, string $content ) {
	if ( ! pdk_current_user_can_edit_code() ) {
		return new WP_Error( 'forbidden', __( 'Je hebt geen rechten om dit bestand te bewerken.', 'pdk-theme-options' ) );
	}

	pdk_ensure_storage_dir();

	// Voorkom pad-traversal.
	$filename = basename( $filename );
	$path     = PDK_STORAGE_DIR . $filename;

	if ( false === file_put_contents( $path, $content ) ) {
		return new WP_Error( 'write_error', __( 'Bestand kon niet worden opgeslagen.', 'pdk-theme-options' ) );
	}

	return true;
}

/**
 * Geeft de URL van een storage-bestand terug met filemtime als cache-buster.
 */
function pdk_storage_file_url( string $filename ): string {
	$path = PDK_STORAGE_DIR . $filename;
	$ver  = file_exists( $path ) ? filemtime( $path ) : PDK_PLUGIN_VERSION;

	return add_query_arg( 'ver', $ver, PDK_STORAGE_URL . $filename );
}

/**
 * Nederlandse dagnamen, geïndexeerd 1 (maandag) t/m 7 (zondag) — gelijk aan date('N').
 *
 * @return array<int,string>
 */
function pdk_day_labels(): array {
	return [
		1 => __( 'Maandag', 'pdk-theme-options' ),
		2 => __( 'Dinsdag', 'pdk-theme-options' ),
		3 => __( 'Woensdag', 'pdk-theme-options' ),
		4 => __( 'Donderdag', 'pdk-theme-options' ),
		5 => __( 'Vrijdag', 'pdk-theme-options' ),
		6 => __( 'Zaterdag', 'pdk-theme-options' ),
		7 => __( 'Zondag', 'pdk-theme-options' ),
	];
}

/**
 * Maakt frontend-uitvoer van een module op twee manieren beschikbaar:
 *
 *  - shortcode:    [naam]                      (in editor/widgets)
 *  - action-hook:  do_action( 'pdk_naam' )     (in template-bestanden)
 *
 * De callback krijgt de shortcode-attributen mee en geeft klaargezette,
 * ge-escapete HTML terug. Eén registratie per module — geen dubbele bedrading.
 */
function pdk_register_frontend_output( string $name, callable $render ): void {
	add_shortcode( $name, static function ( $atts = [] ) use ( $render ): string {
		return (string) $render( (array) $atts );
	} );

	add_action( 'pdk_' . $name, static function ( array $atts = [] ) use ( $render ): void {
		echo $render( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callback levert al ge-escapete HTML.
	} );
}

/**
 * Controleert of WooCommerce beschikbaar is.
 *
 * De plugin laadt modules al vóór `plugins_loaded`; de WooCommerce-klasse
 * bestaat dan mogelijk nog niet. Daarom als terugval de lijst met actieve
 * plugins, zodat WC-afhankelijke modules ook vroeg correct gedetecteerd worden.
 */
function pdk_woocommerce_active(): bool {
	if ( class_exists( 'WooCommerce' ) ) {
		return true;
	}

	$active = (array) get_option( 'active_plugins', [] );

	if ( is_multisite() ) {
		$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
	}

	return in_array( 'woocommerce/woocommerce.php', $active, true );
}
