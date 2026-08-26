<?php
/**
 * Plugin Name: PDK MU Installer
 * Plugin URI:  https://github.com/PDK-Online-Succes/pdk-theme-options-plugin
 * Description: Installeert en update PDK Theme Options als must-use plugin, rechtstreeks vanuit GitHub Releases. Must-use plugins zijn altijd actief en kunnen niet per ongeluk gedeactiveerd worden — maar WordPress kan ze zelf niet installeren of bijwerken. Deze plugin doet dat wel.
 * Version:     1.1.0
 * Author:      PDK Online Succes
 * Author URI:  https://pdk.nl
 * License:     GPL-2.0-or-later
 * Text Domain: pdk-mu-installer
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

class PDK_MU_Installer {

	/** GitHub-repository met de releases. */
	const REPO = 'PDK-Online-Succes/pdk-theme-options-plugin';

	/** Mapnaam binnen mu-plugins/ en naam van het hoofdbestand daarin. */
	const DIR_NAME  = 'pdk-theme-options';
	const MAIN_FILE = 'pdk-theme-options-plugin.php';

	const PAGE_SLUG = 'pdk-mu-installer';
	const TRANSIENT = 'pdk_mu_installer_release';

	public static function boot(): void {
		$self = new self();

		add_action( 'admin_menu',                       [ $self, 'add_menu' ] );
		add_action( 'admin_post_pdk_mu_install',        [ $self, 'handle_install' ] );
		add_action( 'admin_post_pdk_mu_remove',         [ $self, 'handle_remove' ] );
		add_action( 'admin_post_pdk_mu_refresh',        [ $self, 'handle_refresh' ] );
		add_action( 'admin_notices',                    [ $self, 'maybe_show_notice' ] );
	}

	// -------------------------------------------------------------------------
	// Paden en versies
	// -------------------------------------------------------------------------

	public static function mu_dir(): string {
		return trailingslashit( WPMU_PLUGIN_DIR ) . self::DIR_NAME;
	}

	/**
	 * De loader die WordPress daadwerkelijk inleest (mu-plugins laadt geen submappen).
	 *
	 * WordPress laadt must-use plugins in alfabetische volgorde — er is geen
	 * prioriteit. De `00-`-prefix zet ons dus vóór elke andere MU-plugin, zodat
	 * de header-firewall en de blacklist-opruiming als eerste draaien.
	 */
	public static function loader_file(): string {
		return trailingslashit( WPMU_PLUGIN_DIR ) . '00-' . self::DIR_NAME . '.php';
	}

	/** De loadernaam van vóór 1.1.0, zonder `00-`-prefix. Wordt opgeruimd. */
	public static function legacy_loader_file(): string {
		return trailingslashit( WPMU_PLUGIN_DIR ) . self::DIR_NAME . '.php';
	}

	public static function main_file(): string {
		return self::mu_dir() . '/' . self::MAIN_FILE;
	}

	/** Geïnstalleerde versie uit de plugin-header, of '' als er niets staat. */
	public static function installed_version(): string {
		if ( ! file_exists( self::main_file() ) ) {
			return '';
		}

		$data = get_file_data( self::main_file(), [ 'Version' => 'Version' ] );

		return $data['Version'] ?? '';
	}

	/**
	 * Laatste release van GitHub, 12 uur gecached.
	 * Geeft null terug bij een netwerkfout of een repo zonder releases.
	 */
	public static function latest_release(): ?object {
		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			[
				'timeout' => 15,
				'headers' => [
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::TRANSIENT, false, HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! isset( $data->tag_name ) ) {
			set_transient( self::TRANSIENT, false, HOUR_IN_SECONDS );
			return null;
		}

		set_transient( self::TRANSIENT, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}

	public static function latest_version(): string {
		$release = self::latest_release();

		return $release ? ltrim( $release->tag_name, 'v' ) : '';
	}

	public static function update_available(): bool {
		$installed = self::installed_version();
		$latest    = self::latest_version();

		return '' !== $installed && '' !== $latest && version_compare( $latest, $installed, '>' );
	}

	// -------------------------------------------------------------------------
	// Admin-pagina
	// -------------------------------------------------------------------------

	public function add_menu(): void {
		add_submenu_page(
			'plugins.php',
			__( 'PDK MU-plugin', 'pdk-mu-installer' ),
			__( 'PDK MU-plugin', 'pdk-mu-installer' ),
			'install_plugins',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/** Melding op elke admin-pagina zolang er iets te doen is. */
	public function maybe_show_notice(): void {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		// Niet op de eigen pagina — daar staat de status al.
		if ( isset( $_GET['page'] ) && self::PAGE_SLUG === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$url = admin_url( 'plugins.php?page=' . self::PAGE_SLUG );

		if ( '' === self::installed_version() ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'PDK Theme Options is nog niet geïnstalleerd als must-use plugin.', 'pdk-mu-installer' ),
				esc_url( $url ),
				esc_html__( 'Nu installeren', 'pdk-mu-installer' )
			);
			return;
		}

		if ( self::update_available() ) {
			printf(
				'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
				sprintf(
					/* translators: %s: versienummer */
					esc_html__( 'Er is een nieuwe versie van de PDK MU-plugin beschikbaar (%s).', 'pdk-mu-installer' ),
					esc_html( self::latest_version() )
				),
				esc_url( $url ),
				esc_html__( 'Bijwerken', 'pdk-mu-installer' )
			);
		}
	}

	public function render_page(): void {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		$installed = self::installed_version();
		$latest    = self::latest_version();
		$release   = self::latest_release();
		$ap_url    = esc_url( admin_url( 'admin-post.php' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PDK MU-plugin', 'pdk-mu-installer' ); ?></h1>

			<?php $this->render_notices(); ?>

			<table class="widefat" style="max-width:700px;margin:16px 0;">
				<tbody>
					<tr>
						<th style="width:220px;"><?php esc_html_e( 'Geïnstalleerde versie', 'pdk-mu-installer' ); ?></th>
						<td>
							<?php if ( $installed ) : ?>
								<strong><?php echo esc_html( $installed ); ?></strong>
							<?php else : ?>
								<em><?php esc_html_e( 'Niet geïnstalleerd', 'pdk-mu-installer' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Laatste release', 'pdk-mu-installer' ); ?></th>
						<td>
							<?php if ( $latest ) : ?>
								<strong><?php echo esc_html( $latest ); ?></strong>
								<?php if ( ! empty( $release->published_at ) ) : ?>
									<span style="color:#787c82;">
										(<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $release->published_at ) ) ); ?>)
									</span>
								<?php endif; ?>
							<?php else : ?>
								<em><?php esc_html_e( 'Kon GitHub niet bereiken of er is nog geen release gepubliceerd.', 'pdk-mu-installer' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Installatiemap', 'pdk-mu-installer' ); ?></th>
						<td><code><?php echo esc_html( self::mu_dir() ); ?></code></td>
					</tr>
				</tbody>
			</table>

			<p>
				<form method="post" action="<?php echo $ap_url; // phpcs:ignore ?>" style="display:inline;">
					<?php wp_nonce_field( 'pdk_mu_install' ); ?>
					<input type="hidden" name="action" value="pdk_mu_install">
					<?php
					if ( ! $installed ) {
						submit_button( __( 'Installeren', 'pdk-mu-installer' ), 'primary', 'submit', false );
					} elseif ( self::update_available() ) {
						submit_button( sprintf( __( 'Bijwerken naar %s', 'pdk-mu-installer' ), $latest ), 'primary', 'submit', false );
					} else {
						submit_button( __( 'Opnieuw installeren', 'pdk-mu-installer' ), 'secondary', 'submit', false );
					}
					?>
				</form>

				<form method="post" action="<?php echo $ap_url; // phpcs:ignore ?>" style="display:inline;margin-left:6px;">
					<?php wp_nonce_field( 'pdk_mu_refresh' ); ?>
					<input type="hidden" name="action" value="pdk_mu_refresh">
					<?php submit_button( __( 'Nu op updates controleren', 'pdk-mu-installer' ), 'secondary', 'submit', false ); ?>
				</form>

				<?php if ( $installed ) : ?>
				<form method="post" action="<?php echo $ap_url; // phpcs:ignore ?>" style="display:inline;margin-left:6px;">
					<?php wp_nonce_field( 'pdk_mu_remove' ); ?>
					<input type="hidden" name="action" value="pdk_mu_remove">
					<button type="submit" class="button" style="color:#d63638;border-color:#d63638;"
						onclick="return confirm('<?php esc_attr_e( 'De must-use plugin wordt van de server verwijderd. Klantbestanden in uploads/ blijven staan. Doorgaan?', 'pdk-mu-installer' ); ?>')">
						<?php esc_html_e( 'MU-plugin verwijderen', 'pdk-mu-installer' ); ?>
					</button>
				</form>
				<?php endif; ?>
			</p>

			<?php if ( $release && ! empty( $release->body ) ) : ?>
				<h2><?php esc_html_e( 'Wat er in deze release zit', 'pdk-mu-installer' ); ?></h2>
				<div style="max-width:700px;padding:12px 16px;background:#fff;border:1px solid #dcdcde;">
					<?php echo wp_kses_post( nl2br( esc_html( $release->body ) ) ); ?>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Hoe dit werkt', 'pdk-mu-installer' ); ?></h2>
			<p style="max-width:700px;">
				<?php
				printf(
					/* translators: 1: loader-bestand, 2: pluginmap */
					esc_html__( 'Bij installeren wordt de release van GitHub gehaald en uitgepakt naar %2$s. Daarnaast wordt %1$s aangemaakt: must-use plugins laden geen submappen, dus dat bestandje laadt de plugin in. De naam begint met 00- omdat WordPress must-use plugins op alfabet laadt — zo draait de PDK-plugin vóór alle andere. Deze installer mag daarna gewoon actief blijven — hij controleert elke 12 uur op een nieuwe release en meldt het in de admin.', 'pdk-mu-installer' ),
					'<code>' . esc_html( basename( self::loader_file() ) ) . '</code>',
					'<code>' . esc_html( self::mu_dir() ) . '</code>'
				);
				?>
			</p>
			<p style="max-width:700px;">
				<?php esc_html_e( 'Deactiveer je deze installer, dan blijft de must-use plugin gewoon draaien — die staat immers buiten het pluginsysteem. Gebruik "MU-plugin verwijderen" om hem echt weg te halen.', 'pdk-mu-installer' ); ?>
			</p>
		</div>
		<?php
	}

	private function render_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['pdk_done'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( rawurldecode( wp_unslash( $_GET['pdk_done'] ) ) ) . '</p></div>';
		}
		if ( ! empty( $_GET['pdk_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( rawurldecode( wp_unslash( $_GET['pdk_error'] ) ) ) . '</p></div>';
		}
		// phpcs:enable
	}

	// -------------------------------------------------------------------------
	// Acties
	// -------------------------------------------------------------------------

	public function handle_refresh(): void {
		$this->guard( 'pdk_mu_refresh' );

		delete_transient( self::TRANSIENT );

		$latest = self::latest_version();

		$this->redirect_done(
			$latest
				? sprintf( __( 'Controle uitgevoerd. Laatste release: %s.', 'pdk-mu-installer' ), $latest )
				: __( 'Controle uitgevoerd, maar GitHub gaf geen release terug.', 'pdk-mu-installer' )
		);
	}

	public function handle_install(): void {
		$this->guard( 'pdk_mu_install' );

		$result = $this->install();

		if ( is_wp_error( $result ) ) {
			$this->redirect_error( $result->get_error_message() );
		}

		$this->redirect_done(
			sprintf(
				/* translators: %s: versienummer */
				__( 'PDK Theme Options %s is geïnstalleerd als must-use plugin.', 'pdk-mu-installer' ),
				self::installed_version()
			)
		);
	}

	public function handle_remove(): void {
		$this->guard( 'pdk_mu_remove' );

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			$this->redirect_error( $fs->get_error_message() );
		}

		global $wp_filesystem;

		if ( is_dir( self::mu_dir() ) ) {
			$wp_filesystem->delete( self::mu_dir(), true );
		}
		foreach ( [ self::loader_file(), self::legacy_loader_file() ] as $loader ) {
			if ( file_exists( $loader ) ) {
				$wp_filesystem->delete( $loader );
			}
		}

		if ( file_exists( self::main_file() ) ) {
			$this->redirect_error( __( 'Verwijderen is mislukt — controleer de schrijfrechten op de mu-plugins map.', 'pdk-mu-installer' ) );
		}

		$this->redirect_done( __( 'De must-use plugin is verwijderd. Klantbestanden in uploads/ zijn blijven staan.', 'pdk-mu-installer' ) );
	}

	// -------------------------------------------------------------------------
	// Installatie
	// -------------------------------------------------------------------------

	/**
	 * Haalt de laatste release op en zet die in mu-plugins/.
	 *
	 * @return true|WP_Error
	 */
	private function install() {
		$release = self::latest_release();

		if ( ! $release || empty( $release->zipball_url ) ) {
			return new WP_Error( 'pdk_no_release', __( 'Geen release gevonden op GitHub. Controleer of de repository publiek is en een release heeft.', 'pdk-mu-installer' ) );
		}

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}

		global $wp_filesystem;

		$zip = download_url( $release->zipball_url, 60 );
		if ( is_wp_error( $zip ) ) {
			return $zip;
		}

		// Uitpakken naar een tijdelijke map binnen wp-content/upgrade/.
		$temp = trailingslashit( WP_CONTENT_DIR ) . 'upgrade/pdk-mu-' . wp_generate_password( 8, false );
		$unzipped = unzip_file( $zip, $temp );
		@unlink( $zip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( is_wp_error( $unzipped ) ) {
			$wp_filesystem->delete( $temp, true );
			return $unzipped;
		}

		// GitHub-zipballs pakken uit in <repo>-<hash>/; zoek daarbinnen de pluginmap.
		$source = $this->locate_plugin_dir( $temp );
		if ( ! $source ) {
			$wp_filesystem->delete( $temp, true );
			return new WP_Error(
				'pdk_bad_package',
				sprintf(
					/* translators: %s: verwacht pad in het pakket */
					__( 'Het pakket bevat geen %s — de release heeft niet de verwachte indeling.', 'pdk-mu-installer' ),
					self::DIR_NAME . '/' . self::MAIN_FILE
				)
			);
		}

		if ( ! wp_mkdir_p( WPMU_PLUGIN_DIR ) ) {
			$wp_filesystem->delete( $temp, true );
			return new WP_Error( 'pdk_no_mu_dir', __( 'De map mu-plugins kon niet worden aangemaakt.', 'pdk-mu-installer' ) );
		}

		// Oude installatie weghalen zodat verwijderde bestanden niet blijven hangen.
		if ( is_dir( self::mu_dir() ) ) {
			$wp_filesystem->delete( self::mu_dir(), true );
		}

		$copied = copy_dir( $source, self::mu_dir() );
		$wp_filesystem->delete( $temp, true );

		if ( is_wp_error( $copied ) ) {
			return $copied;
		}

		// Loader schrijven: mu-plugins laadt alleen PHP-bestanden in de hoofdmap,
		// geen submappen. Bewust zelf gegenereerd zodat de inhoud niet afhangt
		// van wat er in het pakket zit.
		$loader = "<?php\n"
			. "/**\n"
			. " * PDK Theme Options — must-use loader.\n"
			. " * Automatisch aangemaakt door PDK MU Installer. Niet handmatig bewerken:\n"
			. " * dit bestand wordt bij elke (her)installatie overschreven.\n"
			. " */\n\n"
			. "defined( 'ABSPATH' ) || exit;\n\n"
			. "require_once __DIR__ . '/" . self::DIR_NAME . "/" . self::MAIN_FILE . "';\n";

		if ( ! $wp_filesystem->put_contents( self::loader_file(), $loader, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'pdk_loader_failed', __( 'De bestanden zijn geplaatst, maar het loader-bestand kon niet worden geschreven.', 'pdk-mu-installer' ) );
		}

		// Loader van vóór 1.1.0 opruimen, anders staan er twee.
		if ( file_exists( self::legacy_loader_file() ) ) {
			$wp_filesystem->delete( self::legacy_loader_file() );
		}

		return true;
	}

	/**
	 * Zoekt in het uitgepakte pakket de map die de plugin bevat.
	 * Werkt zowel voor een zipball (<repo>-<hash>/pdk-theme-options/) als voor
	 * een zip waarin de pluginmap direct in de root staat.
	 */
	private function locate_plugin_dir( string $temp ): ?string {
		$candidates = [ trailingslashit( $temp ) . self::DIR_NAME ];

		foreach ( (array) glob( trailingslashit( $temp ) . '*', GLOB_ONLYDIR ) as $dir ) {
			$candidates[] = trailingslashit( $dir ) . self::DIR_NAME;
		}

		foreach ( $candidates as $candidate ) {
			if ( file_exists( trailingslashit( $candidate ) . self::MAIN_FILE ) ) {
				return trailingslashit( $candidate );
			}
		}

		return null;
	}

	/**
	 * Initialiseert WP_Filesystem.
	 *
	 * ponytail: alleen directe schrijftoegang (FS_METHOD 'direct'). Hosts die
	 * FTP-gegevens eisen krijgen een duidelijke foutmelding in plaats van een
	 * inlogformulier — voeg request_filesystem_credentials() toe als een klant
	 * daar tegenaan loopt.
	 *
	 * @return true|WP_Error
	 */
	private function filesystem() {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! WP_Filesystem() ) {
			return new WP_Error(
				'pdk_no_filesystem',
				__( 'WordPress heeft geen directe schrijftoegang tot de server. Stel FS_METHOD op "direct" in of controleer de bestandsrechten.', 'pdk-mu-installer' )
			);
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Hulpfuncties
	// -------------------------------------------------------------------------

	private function guard( string $nonce_action ): void {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html__( 'Geen toegang.', 'pdk-mu-installer' ), 403 );
		}

		check_admin_referer( $nonce_action );
	}

	private function redirect_done( string $message ): void {
		wp_safe_redirect( add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'pdk_done' => rawurlencode( $message ) ],
			admin_url( 'plugins.php' )
		) );
		exit;
	}

	private function redirect_error( string $message ): void {
		wp_safe_redirect( add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'pdk_error' => rawurlencode( $message ) ],
			admin_url( 'plugins.php' )
		) );
		exit;
	}
}

PDK_MU_Installer::boot();

/**
 * Bij activeren de gecachte release weggooien, zodat de statuspagina meteen
 * de actuele versie toont. Installeren gebeurt bewust met een knop en niet
 * hier: dan is een mislukte download of schrijfrechtenfout zichtbaar in plaats
 * van verstopt in een activeringsfout.
 */
register_activation_hook( __FILE__, static function (): void {
	delete_transient( PDK_MU_Installer::TRANSIENT );
} );
