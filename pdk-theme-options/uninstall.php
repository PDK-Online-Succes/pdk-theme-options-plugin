<?php
/**
 * Wordt uitgevoerd als de plugin via WordPress-admin wordt verwijderd.
 *
 * BEWUST NIET verwijderd:
 * - wp-content/uploads/pdk-theme-options/  (klantbestanden)
 *
 * Wél verwijderd:
 * - De plugin-opties in de database.
 * - De custom capability van alle rollen.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Verwijder plugin-opties.
delete_option( 'pdk_theme_options' );
delete_option( 'pdk_file_hashes' );
delete_site_option( 'pdk_theme_options' ); // Multisite.

// Boekhouding van de security-module.
delete_option( 'pdk_mu_hashes' );
delete_option( 'pdk_missing_required_plugins' );
delete_option( 'pdk_rest_blocked_routes' );

// Afbeeldingsmaten-module: alleen de vluchtige batchstatus staat in een eigen
// optie. De instellingen zelf (disabled/custom) staan in pdk_theme_options,
// dat hierboven al verwijderd wordt.
delete_option( 'pdk_image_sizes_batch' );

// IMGX-module. De gegenereerde .webp/.avif-bestanden blijven bewust staan:
// honderdduizenden bestanden verwijderen in een uninstall-hook loopt in een
// timeout. Gebruik vooraf "Delete all generated files" of `wp imgx delete`.
delete_option( 'imgx_settings' );
delete_option( 'imgx_capabilities' );
delete_option( 'imgx_batch_state' );
delete_option( 'imgx_recent_errors' );
delete_transient( 'imgx_stats' );

// Nog niet uitgevoerde generatietaken. Losse events verdwijnen na het vuren,
// maar op een site zonder werkende cron blijven ze staan.
require_once __DIR__ . '/modules/imgx/includes/class-generator.php';
IMGX\Generator::clear_scheduled_events();

// Verwijder gecachte GitHub-release-transients.
global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pdk_updater_%' OR option_name LIKE '_transient_timeout_pdk_updater_%'"
);

// Per-attachment generatie-locks van IMGX en de variantenregistratie.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'imgx_lock_' ) . '%'
	)
);
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_imgx_variants' ] );

// Verwijder de custom capability van alle rollen.
$cap = 'pdk_edit_custom_code';
foreach ( wp_roles()->roles as $role_name => $_ ) {
	$role = get_role( $role_name );
	if ( $role ) {
		$role->remove_cap( $cap );
	}
}

// Verwijder de cap ook van individuele gebruikers.
$users = get_users( [ 'fields' => 'ID', 'meta_key' => $cap ] );
foreach ( $users as $user_id ) {
	$user = get_userdata( $user_id );
	if ( $user ) {
		$user->remove_cap( $cap );
	}
}
