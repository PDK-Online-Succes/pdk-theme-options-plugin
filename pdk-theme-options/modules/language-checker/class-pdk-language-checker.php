<?php
/**
 * Module: Taalcontrole
 *
 * Beheerpagina voor:
 *  1. Geïnstalleerde WordPress-kerntalen verwijderen (wp_get_installed_translations).
 *  2. Verweesde plugin-/thema-vertaalbestanden opsporen en verwijderen.
 *
 * De UI wordt gerenderd als tabblad binnen PDK Tools via render_inline().
 * POST-acties worden afgehandeld via admin_init (redirect vóór header-output).
 */

defined( 'ABSPATH' ) || exit;

class PDK_Language_Checker {

	const NONCE_LANG   = 'pdk_lang_remove_core';
	const NONCE_ORPHAN = 'pdk_lang_orphan';

	public function __construct( PDK_Loader $loader ) {
		$loader->add_action( 'admin_init',    $this, 'handle_actions' );
		$loader->add_action( 'admin_notices', $this, 'show_notices' );
	}

	/** Verwerkt POST-acties vóór header-output. */
	public function handle_actions(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['pdk_lang_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:enable

		$action = sanitize_key( $_POST['pdk_lang_action'] ); // phpcs:ignore

		if ( 'uninstall_languages' === $action ) {
			check_admin_referer( self::NONCE_LANG );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$locales  = array_map( 'sanitize_text_field', (array) ( $_POST['languages'] ?? [] ) );
			$active   = get_locale();
			$count    = 0;

			foreach ( $locales as $locale ) {
				if ( $locale === $active ) {
					continue; // Actieve taal nooit verwijderen.
				}
				$count += self::do_uninstall_language( $locale );
			}

			set_transient( 'pdk_lang_notice', sprintf(
				/* translators: %d: aantal verwijderde bestanden */
				_n( '%d taalbestand verwijderd.', '%d taalbestanden verwijderd.', $count, 'pdk-theme-options' ),
				$count
			), 30 );
		}

		if ( 'remove_orphaned' === $action ) {
			check_admin_referer( self::NONCE_ORPHAN );
			$count = self::do_remove_orphaned();
			set_transient( 'pdk_lang_notice', sprintf(
				/* translators: %d: aantal verwijderde bestanden */
				_n( '%d verweesd bestand verwijderd.', '%d verweesde bestanden verwijderd.', $count, 'pdk-theme-options' ),
				$count
			), 30 );
		}

		wp_safe_redirect( add_query_arg(
			[ 'page' => PDK_Admin::PAGE_SLUG, 'tab' => 'language_checker', 'saved' => '1' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function show_notices(): void {
		$notice = get_transient( 'pdk_lang_notice' );
		if ( $notice ) {
			delete_transient( 'pdk_lang_notice' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
		}
	}

	/**
	 * Rendert de taalcontrole-UI als inline-tab binnen PDK Tools.
	 * Wordt aangeroepen vanuit PDK_Admin::render_tab_language_checker().
	 *
	 * LET OP: de admin-pagina mag voor dit tabblad GEEN wrapper-<form> openen
	 * omdat render_inline() eigen <form>-elementen bevat.
	 */
	public static function render_inline(): void {
		$core_langs = self::get_core_languages();
		$active     = get_locale();
		$orphaned   = self::find_orphaned_files();
		?>

		<!-- Sectie 1: Kerntalen -->
		<h2><?php esc_html_e( 'Geïnstalleerde WordPress-kerntalen', 'pdk-theme-options' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Verwijder taalbestanden die je niet meer nodig hebt. De actieve taal kan niet worden verwijderd.', 'pdk-theme-options' ); ?>
		</p>

		<?php if ( $core_langs ) : ?>
		<form method="post" style="margin-bottom:2em;">
			<?php wp_nonce_field( self::NONCE_LANG ); ?>
			<input type="hidden" name="pdk_lang_action" value="uninstall_languages">

			<table class="widefat striped" style="max-width:640px;margin-bottom:12px;">
				<thead><tr>
					<th style="width:32px;"><input type="checkbox" id="pdk-lang-select-all" title="<?php esc_attr_e( 'Alles selecteren', 'pdk-theme-options' ); ?>"></th>
					<th><?php esc_html_e( 'Taal (locale)', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Status', 'pdk-theme-options' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $core_langs as $locale ) : ?>
					<tr>
						<td>
							<?php if ( $locale === $active ) : ?>
								&mdash;
							<?php else : ?>
								<input type="checkbox" name="languages[]" value="<?php echo esc_attr( $locale ); ?>">
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $locale ); ?></code></td>
						<td>
							<?php if ( $locale === $active ) : ?>
								<span style="color:#2271b1;font-weight:600;"><?php esc_html_e( 'Actief', 'pdk-theme-options' ); ?></span>
							<?php else : ?>
								<span style="color:#888;"><?php esc_html_e( 'Geïnstalleerd', 'pdk-theme-options' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Geselecteerde talen verwijderen', 'pdk-theme-options' ), 'delete', 'submit', false ); ?>
		</form>

		<script>
		document.getElementById('pdk-lang-select-all').addEventListener('change', function() {
			document.querySelectorAll('input[name="languages[]"]').forEach(function(cb) {
				cb.checked = this.checked;
			}, this);
		});
		</script>
		<?php else : ?>
			<p><?php esc_html_e( 'Geen extra kerntalen geïnstalleerd (naast de actieve taal).', 'pdk-theme-options' ); ?></p>
		<?php endif; ?>

		<!-- Sectie 2: Verweesde vertaalbestanden -->
		<h2 style="margin-top:1.5em;"><?php esc_html_e( 'Verweesde vertaalbestanden', 'pdk-theme-options' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Vertaalbestanden voor plugins of thema\'s die niet meer geïnstalleerd zijn.', 'pdk-theme-options' ); ?>
		</p>

		<?php if ( $orphaned ) : ?>
			<p><?php printf(
				esc_html( _n( '%d verweesd bestand gevonden:', '%d verweesde bestanden gevonden:', count( $orphaned ), 'pdk-theme-options' ) ),
				count( $orphaned )
			); ?></p>
			<ul class="pdk-font-list" style="margin-bottom:12px;">
				<?php foreach ( $orphaned as $f ) : ?>
					<li><code><?php echo esc_html( basename( $f ) ); ?></code></li>
				<?php endforeach; ?>
			</ul>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_ORPHAN ); ?>
				<input type="hidden" name="pdk_lang_action" value="remove_orphaned">
				<?php submit_button( __( 'Alle verweesde bestanden verwijderen', 'pdk-theme-options' ), 'delete' ); ?>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'Geen verweesde vertaalbestanden gevonden.', 'pdk-theme-options' ); ?></p>
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Interne hulpmethoden
	// -------------------------------------------------------------------------

	/**
	 * Haal alle geïnstalleerde WordPress-kerntalen op via de officiële API.
	 * Geeft locales terug gesorteerd op naam, inclusief de actieve locale.
	 */
	private static function get_core_languages(): array {
		$installed = wp_get_installed_translations( 'core' );
		$locales   = array_keys( $installed['default'] ?? [] );

		// Voeg de actieve locale toe als die er nog niet in zit
		// (Engels/default heeft geen bestand maar is altijd actief).
		$active = get_locale();
		if ( $active && 'en_US' !== $active && ! in_array( $active, $locales, true ) ) {
			$locales[] = $active;
		}

		sort( $locales );
		return $locales;
	}

	/**
	 * Verwijdert ALLE taalbestanden voor een locale in drie mappen:
	 *  - WP_LANG_DIR root       (nl_NL.mo, admin-nl_NL.mo, nl_NL.l10n.php, nl_NL-{hash}.json …)
	 *  - WP_LANG_DIR/plugins/   (classic-editor-nl_NL.mo, woocommerce-nl_NL-admin.json …)
	 *  - WP_LANG_DIR/themes/    (storefront-nl_NL.mo, storefront-nl_NL.l10n.php …)
	 *
	 * Twee glob-patronen per map:
	 *  1. *{locale}.*  → locale gevolgd door een punt   (nl_NL.mo, admin-nl_NL.l10n.php …)
	 *  2. *{locale}-*  → locale gevolgd door een streep (nl_NL-{hash}.json, woocommerce-nl_NL-app.json …)
	 */
	private static function do_uninstall_language( string $locale ): int {
		if ( ! $locale ) {
			return 0;
		}

		$locale = sanitize_file_name( $locale );
		$count  = 0;
		$dirs   = [
			WP_LANG_DIR,
			WP_LANG_DIR . '/plugins',
			WP_LANG_DIR . '/themes',
		];

		foreach ( $dirs as $dir ) {
			$base     = trailingslashit( $dir );
			$matches = [
				...( glob( $base . "*{$locale}.*" ) ?: [] ), // nl_NL.mo, admin-nl_NL.l10n.php …
				...( glob( $base . "*{$locale}-*"  ) ?: [] ), // nl_NL-{hash}.json, woocommerce-nl_NL-app.json …
			];

			foreach ( array_unique( $matches ) as $file ) {
				if ( is_file( $file ) && @unlink( $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$count++;
				}
			}
		}

		return $count;
	}

	private static function find_orphaned_files(): array {
		$orphaned    = [];
		$dirs        = [ WP_LANG_DIR . '/plugins', WP_LANG_DIR . '/themes' ];
		$known_slugs = [];

		foreach ( get_plugins() as $path => $_ ) {
			$known_slugs[] = strtolower( dirname( $path ) );
			$known_slugs[] = strtolower( basename( $path, '.php' ) );
		}
		foreach ( wp_get_themes() as $slug => $_ ) {
			$known_slugs[] = strtolower( $slug );
		}

		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			foreach ( glob( trailingslashit( $dir ) . '*.{mo,po}', GLOB_BRACE ) ?: [] as $file ) {
				$filename = pathinfo( $file, PATHINFO_FILENAME );

				// Verwijder locale-achtervoegsel (bijv. -nl_NL, -fr_FR, -de_DE_formal)
				// VOOR het lowercase maken — anders herkent _[A-Z]{2} niet meer na strtolower().
				$slug = strtolower( (string) preg_replace(
					'/-[a-z]{2,3}(_[A-Z]{2,3}(_[a-z_]+)?)?(_v\d+)?$/',
					'',
					$filename
				) );

				if ( ! in_array( $slug, $known_slugs, true ) ) {
					$orphaned[] = $file;
				}
			}
		}

		return $orphaned;
	}

	private static function do_remove_orphaned(): int {
		$count = 0;
		foreach ( self::find_orphaned_files() as $file ) {
			if ( @unlink( $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$count++;
			}
		}
		return $count;
	}
}
