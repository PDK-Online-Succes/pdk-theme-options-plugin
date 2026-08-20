<?php
/**
 * Module: Custom CSS
 *
 * Laadt het klantbestand custom-style.css op de frontend.
 * Versioning via filemtime voor cache-busting.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Custom_CSS {

	public function __construct( PDK_Loader $loader ) {
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue' );
	}

	public function enqueue(): void {
		$file = PDK_STORAGE_DIR . 'custom-style.css';

		if ( ! file_exists( $file ) || 0 === filesize( $file ) ) {
			return;
		}

		// Buiten de editor om gewijzigd → niet uitserveren (zie pdk_file_is_tampered).
		if ( pdk_file_is_tampered( 'custom-style.css' ) ) {
			return;
		}

		wp_enqueue_style(
			'pdk-custom-style',
			PDK_STORAGE_URL . 'custom-style.css',
			[],
			filemtime( $file )
		);
	}
}
