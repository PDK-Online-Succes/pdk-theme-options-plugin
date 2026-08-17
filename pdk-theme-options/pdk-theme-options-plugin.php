<?php
/**
 * Plugin Name: PDK Theme Options
 * Plugin URI:  https://github.com/PDK-Online-Succes/pdk-theme-options-plugin
 * Description: Centrale beheerplugin voor PDK Online Succes klanten. Modulaire WordPress/WooCommerce functionaliteiten in één plugin.
 * Version:     2.3.1
 * Author:      PDK Online Succes
 * Author URI:  https://pdk.nl
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pdk-theme-options
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * WC requires at least: 7.0
 * WC tested up to:      9.9
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'PDK_PLUGIN_VERSION', '2.3.1' );
define( 'PDK_PLUGIN_FILE',    __FILE__ );
define( 'PDK_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'PDK_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );
define( 'PDK_PLUGIN_SLUG',    'pdk-theme-options-plugin' );
define( 'PDK_GITHUB_REPO',    'PDK-Online-Succes/pdk-theme-options-plugin' );

// Storage outside the plugin folder — survives plugin updates.
define( 'PDK_STORAGE_DIR', WP_CONTENT_DIR . '/uploads/pdk-theme-options/' );
define( 'PDK_STORAGE_URL', WP_CONTENT_URL . '/uploads/pdk-theme-options/' );

// Custom capability for editing PHP/CSS/JS files.
define( 'PDK_CAP_EDIT_CODE', 'pdk_edit_custom_code' );

// Detecteer of de plugin als must-use plugin draait.
define( 'PDK_IS_MU_PLUGIN', defined( 'WPMU_PLUGIN_DIR' ) && 0 === strpos( __FILE__, WPMU_PLUGIN_DIR ) );

// Bootstrap.
require_once PDK_PLUGIN_DIR . 'includes/helpers.php';
require_once PDK_PLUGIN_DIR . 'includes/class-pdk-loader.php';
require_once PDK_PLUGIN_DIR . 'includes/class-pdk-settings.php';
require_once PDK_PLUGIN_DIR . 'includes/class-pdk-updater.php';
require_once PDK_PLUGIN_DIR . 'includes/class-pdk-admin.php';
require_once PDK_PLUGIN_DIR . 'includes/class-pdk-plugin.php';

/**
 * Returns the main plugin instance (singleton).
 */
function pdk_theme_options(): PDK_Plugin {
	return PDK_Plugin::get_instance();
}

pdk_theme_options();

register_activation_hook( __FILE__, [ 'PDK_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PDK_Plugin', 'deactivate' ] );
