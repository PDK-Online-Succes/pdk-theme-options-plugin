<?php
/**
 * Wist alle SEO Health Check-data (tabellen, opties, transients, uploads-map,
 * geplande events, usermeta). Wordt geladen door de uninstall.php van PDK
 * Theme Options; de body is die van de losse plugin (0.10.1), met twee
 * afwijkingen:
 *
 * (a) Is de losse plugin actief (elk pad dat eindigt op /seo-health-check.php,
 *     netwerkbreed of op de betreffende site), dan wordt niets gewist: de losse
 *     plugin gebruikt dezelfde tabellen en opties.
 * (b) Op multisite worden alle sites doorlopen (de bron stopt bij 100).
 *
 * @package SEO_Health_Check
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! function_exists( 'seohc_uninstall_standalone_in' ) ) {
	/**
	 * Staat de losse plugin in deze lijst met plugin-paden?
	 *
	 * @param array $plugins Paden zoals in active_plugins.
	 * @return bool
	 */
	function seohc_uninstall_standalone_in( $plugins ) {
		foreach ( (array) $plugins as $plugin ) {
			if ( 'seo-health-check.php' === $plugin || '/seo-health-check.php' === substr( (string) $plugin, -21 ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'seohc_uninstall_site' ) ) {
	/**
	 * Deletes the tables, options, transients and scheduled events of the current site.
	 */
	function seohc_uninstall_site() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing the plugin's own tables.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}seohc_issues" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}seohc_pages" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}seohc_links" );

		// Link check cache.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_seohc_link_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_seohc_link_' ) . '%'
			)
		);
		// phpcs:enable

		// Kept reports name every page of the site, so they go too.
		$seohc_uploads = wp_get_upload_dir();
		$seohc_folder  = trailingslashit( $seohc_uploads['basedir'] ) . 'seo-health-check';
		// scandir, not glob: glob skips the .htaccess the mailer puts in this folder.
		foreach ( is_dir( $seohc_folder ) ? (array) scandir( $seohc_folder ) : array() as $seohc_file ) {
			if ( '.' !== $seohc_file && '..' !== $seohc_file ) {
				wp_delete_file( $seohc_folder . '/' . $seohc_file );
			}
		}
		if ( is_dir( $seohc_folder ) ) {
			rmdir( $seohc_folder ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- the plugin's own folder.
		}

		foreach ( array( 'seohc_settings', 'seohc_db_version', 'seohc_scan_state', 'seohc_scan_lock', 'seohc_launch_results', 'seohc_link_cache_generation', 'seohc_scan_history', 'seohc_schedule', 'seohc_report_state' ) as $option ) {
			delete_option( $option );
		}

		delete_site_transient( 'seohc_latest_release' );

		wp_unschedule_hook( 'seohc_scheduled_scan' );
		wp_unschedule_hook( 'seohc_process_batch' );
		wp_unschedule_hook( 'seohc_scan_single_post' );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'seohc_process_batch' );
		}

		delete_metadata( 'user', 0, 'seohc_issues_per_page', '', true );
	}
}

if ( is_multisite() ) {
	$seohc_network = function_exists( 'get_site_option' ) ? get_site_option( 'active_sitewide_plugins', array() ) : array();

	// Netwerkbreed actief: niets wissen.
	if ( ! seohc_uninstall_standalone_in( array_keys( (array) $seohc_network ) ) ) {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $seohc_site_id ) {
			switch_to_blog( $seohc_site_id );
			if ( ! seohc_uninstall_standalone_in( get_option( 'active_plugins', array() ) ) ) {
				seohc_uninstall_site();
			}
			restore_current_blog();
		}
	}
} elseif ( ! seohc_uninstall_standalone_in( get_option( 'active_plugins', array() ) ) ) {
	seohc_uninstall_site();
}
