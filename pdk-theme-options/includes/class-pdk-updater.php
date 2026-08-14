<?php
/**
 * Lichtgewicht GitHub-updater zonder externe dependencies.
 *
 * Controleert het laatste GitHub-release-tag via de GitHub API en informeert
 * WordPress als er een nieuwere versie beschikbaar is.
 *
 * Klantbestanden in wp-content/uploads/pdk-theme-options/ worden NOOIT
 * overschreven door updates omdat die buiten de pluginmap staan.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Updater {

	private string $github_repo;  // bijv. 'PDK-Online-Succes/pdk-theme-options-plugin'
	private string $plugin_file;  // absoluut pad naar hoofd-PHP-bestand
	private string $current_version;
	private string $plugin_slug;
	private string $transient_key;

	public function __construct( string $github_repo, string $plugin_file, string $current_version ) {
		$this->github_repo     = $github_repo;
		$this->plugin_file     = $plugin_file;
		$this->current_version = $current_version;
		$this->plugin_slug     = plugin_basename( $plugin_file );
		$this->transient_key   = 'pdk_updater_' . md5( $github_repo );
	}

	public function register( PDK_Loader $loader ): void {
		$loader->add_filter( 'pre_set_site_transient_update_plugins', $this, 'check_for_update', 10, 1 );
		$loader->add_filter( 'plugins_api',                           $this, 'plugin_info',      10, 3 );
		$loader->add_filter( 'upgrader_source_selection',             $this, 'fix_source_dir',   10, 4 );
	}

	/**
	 * Haalt de release-info op van GitHub (gecached via transient, 12 uur).
	 */
	private function get_remote_release(): ?object {
		$cached = get_transient( $this->transient_key );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$url      = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
		$response = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			],
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $this->transient_key, false, HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! isset( $data->tag_name ) ) {
			set_transient( $this->transient_key, false, HOUR_IN_SECONDS );
			return null;
		}

		set_transient( $this->transient_key, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Voegt update-informatie toe aan de WordPress-updatetransient.
	 *
	 * @param object $transient
	 * @return object
	 */
	public function check_for_update( object $transient ): object {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_remote_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = ltrim( $release->tag_name, 'v' );

		if ( version_compare( $remote_version, $this->current_version, '>' ) ) {
			$transient->response[ $this->plugin_slug ] = (object) [
				'slug'        => dirname( $this->plugin_slug ),
				'plugin'      => $this->plugin_slug,
				'new_version' => $remote_version,
				'url'         => "https://github.com/{$this->github_repo}",
				'package'     => $release->zipball_url ?? '',
				'icons'       => [],
				'banners'     => [],
				'tested'      => get_bloginfo( 'version' ),
				'requires_php'=> '8.0',
			];
		}

		return $transient;
	}

	/**
	 * Vult het Plugin Information-venster in dat WordPress toont.
	 *
	 * @param false|object|\WP_Error $result
	 * @param string                 $action
	 * @param object                 $args
	 * @return false|object
	 */
	public function plugin_info( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) {
			return $result;
		}

		$release = $this->get_remote_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = ltrim( $release->tag_name, 'v' );

		return (object) [
			'name'          => 'PDK Theme Options',
			'slug'          => dirname( $this->plugin_slug ),
			'version'       => $remote_version,
			'author'        => '<a href="https://pdk.nl">PDK Online Succes</a>',
			'homepage'      => "https://github.com/{$this->github_repo}",
			'download_link' => $release->zipball_url ?? '',
			'sections'      => [
				'description'  => nl2br( esc_html( $release->body ?? '' ) ),
				'changelog'    => nl2br( esc_html( $release->body ?? '' ) ),
			],
			'requires'      => '6.0',
			'requires_php'  => '8.0',
			'tested'        => get_bloginfo( 'version' ),
			'last_updated'  => $release->published_at ?? '',
		];
	}

	/**
	 * GitHub-zipballs bevatten een map met hash in de naam.
	 * Dit herschrijft de map naar de correcte plugin-slug.
	 *
	 * @param string      $source
	 * @param string      $remote_source
	 * @param \WP_Upgrader $upgrader
	 * @param array       $hook_extra
	 * @return string|\WP_Error
	 */
	public function fix_source_dir( string $source, string $remote_source, $upgrader, array $hook_extra ) {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
			return $source;
		}

		$correct_dir = trailingslashit( $remote_source ) . dirname( $this->plugin_slug ) . '/';

		if ( $source === $correct_dir ) {
			return $source;
		}

		if ( ! $wp_filesystem->move( $source, $correct_dir ) ) {
			return new WP_Error( 'pdk_rename_failed', __( 'Pluginmap kon niet worden hernoemd na update.', 'pdk-theme-options' ) );
		}

		return $correct_dir;
	}

	/** Verwijdert de gecachte release (na handmatige controle). */
	public function flush_cache(): void {
		delete_transient( $this->transient_key );
	}
}
