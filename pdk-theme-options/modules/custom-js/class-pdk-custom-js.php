<?php
/**
 * Module: Custom JavaScript
 *
 * Laadt het klantbestand custom-script.js onderaan de pagina op de frontend.
 * Versioning via filemtime voor cache-busting.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Custom_JS {

	public function __construct( PDK_Loader $loader ) {
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue' );
	}

	public function enqueue(): void {
		$file = PDK_STORAGE_DIR . 'custom-script.js';

		if ( ! file_exists( $file ) || 0 === filesize( $file ) ) {
			return;
		}

		// Buiten de editor om gewijzigd → niet uitserveren (zie pdk_file_is_tampered).
		if ( pdk_file_is_tampered( 'custom-script.js' ) ) {
			return;
		}

		wp_enqueue_script(
			'pdk-custom-script',
			PDK_STORAGE_URL . 'custom-script.js',
			[],
			filemtime( $file ),
			true // In footer.
		);
	}
}
