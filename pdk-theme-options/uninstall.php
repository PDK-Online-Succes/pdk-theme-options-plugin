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
delete_site_option( 'pdk_theme_options' ); // Multisite.

// Verwijder gecachte GitHub-release-transients.
global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pdk_updater_%' OR option_name LIKE '_transient_timeout_pdk_updater_%'"
);

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
