<?php
/**
 * Settings screen, tools and Media Library integration.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * Everything that only exists inside wp-admin.
 */
class Admin {

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'imgx';

	/**
	 * Transient caching the library statistics.
	 */
	const STATS_TRANSIENT = 'imgx_stats';

	/**
	 * How long the statistics stay cached.
	 */
	const STATS_TTL = 300;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Capability detection.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Generator.
	 *
	 * @var Generator
	 */
	private $generator;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Settings     $settings     Settings.
	 * @param Capabilities $capabilities Capability detection.
	 * @param Generator    $generator    Generator.
	 * @param Logger       $logger       Logger.
	 */
	public function __construct( Settings $settings, Capabilities $capabilities, Generator $generator, Logger $logger ) {
		$this->settings     = $settings;
		$this->capabilities = $capabilities;
		$this->generator    = $generator;
		$this->logger       = $logger;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Geen eigen menupagina: de instellingen staan als tab in PDK Tools,
		// zie PDK_ImgX::render_inline().
		add_action( 'admin_init', array( $this, 'register_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_imgx_recheck', array( $this, 'handle_recheck' ) );
		add_action( 'admin_post_imgx_clear_errors', array( $this, 'handle_clear_errors' ) );

		add_filter( 'manage_media_columns', array( $this, 'media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'media_column_content' ), 10, 2 );
	}

	/**
	 * Registers the Settings API sections and fields.
	 *
	 * @return void
	 */
	public function register_fields() {
		add_settings_section(
			'imgx_formats',
			__( 'Formaten', 'pdk-theme-options' ),
			array( $this, 'section_formats' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'imgx_mode',
			__( 'Generatiemodus', 'pdk-theme-options' ),
			array( $this, 'field_mode' ),
			self::PAGE_SLUG,
			'imgx_formats'
		);

		add_settings_section(
			'imgx_quality',
			__( 'Kwaliteit', 'pdk-theme-options' ),
			array( $this, 'section_quality' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'imgx_webp_quality',
			__( 'WebP-kwaliteit', 'pdk-theme-options' ),
			array( $this, 'field_quality' ),
			self::PAGE_SLUG,
			'imgx_quality',
			array( 'format' => 'webp' )
		);

		add_settings_field(
			'imgx_avif_quality',
			__( 'AVIF-kwaliteit', 'pdk-theme-options' ),
			array( $this, 'field_quality' ),
			self::PAGE_SLUG,
			'imgx_quality',
			array( 'format' => 'avif' )
		);

		add_settings_section(
			'imgx_markup',
			__( 'Markup', 'pdk-theme-options' ),
			array( $this, 'section_markup' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'imgx_picture_enabled',
			__( 'Vervanging door picture-element', 'pdk-theme-options' ),
			array( $this, 'field_picture' ),
			self::PAGE_SLUG,
			'imgx_markup'
		);

		add_settings_field(
			'imgx_whole_page',
			__( 'Paginabuilders', 'pdk-theme-options' ),
			array( $this, 'field_whole_page' ),
			self::PAGE_SLUG,
			'imgx_markup'
		);

		add_settings_section(
			'imgx_generation',
			__( 'Genereren', 'pdk-theme-options' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'imgx_generate_on_upload',
			__( 'Nieuwe uploads', 'pdk-theme-options' ),
			array( $this, 'field_upload' ),
			self::PAGE_SLUG,
			'imgx_generation'
		);

		add_settings_field(
			'imgx_debug',
			__( 'Debug-logging', 'pdk-theme-options' ),
			array( $this, 'field_debug' ),
			self::PAGE_SLUG,
			'imgx_generation'
		);
	}

	/**
	 * Formats section description.
	 *
	 * @return void
	 */
	public function section_formats() {
		echo '<p>' . esc_html__( 'IMGX schrijft extra bestanden naast je originelen. De originele JPEG of PNG wordt nooit gewijzigd, hercomprimeerd of verwijderd.', 'pdk-theme-options' ) . '</p>';

		$this->render_support_table();
	}

	/**
	 * Quality section description.
	 *
	 * @return void
	 */
	public function section_quality() {
		echo '<p>' . esc_html__( 'AVIF- en WebP-kwaliteit staan niet op dezelfde schaal. AVIF rond 45-60 komt ongeveer overeen met JPEG 80-85; WebP heeft voor hetzelfde resultaat een merkbaar hoger getal nodig.', 'pdk-theme-options' ) . '</p>';
	}

	/**
	 * Markup section description.
	 *
	 * @return void
	 */
	public function section_markup() {
		echo '<p>' . esc_html__( 'Een afbeelding in een <picture> staat een niveau dieper in de DOM. Een thema met een selector als ".card > img" matcht dan niet meer en heeft ".card picture > img" of ".card img" nodig. Zet dit uit als je thema daarvan afhankelijk is; de gegenereerde bestanden blijven staan, er hoeft niets opnieuw gegenereerd te worden als je het weer aanzet.', 'pdk-theme-options' ) . '</p>';
		echo '<p>' . esc_html__( 'De <picture> neemt de classes van de afbeelding zelf over, zodat CSS die de afbeelding via een class positioneert of opmaakt blijft werken. De afbeelding houdt die classes ook, dus een class die marges of padding zet geldt nu voor allebei en telt op. Met het filter imgx_picture_class haal je zo\'n class van de wrapper af.', 'pdk-theme-options' ) . '</p>';
	}

	/**
	 * Generation mode radios.
	 *
	 * @return void
	 */
	public function field_mode() {
		$current = (string) $this->settings->get( 'mode' );

		$choices = array(
			'avif_webp' => __( 'AVIF + WebP (aanbevolen)', 'pdk-theme-options' ),
			'avif'      => __( 'Alleen AVIF', 'pdk-theme-options' ),
			'webp'      => __( 'Alleen WebP', 'pdk-theme-options' ),
		);

		echo '<fieldset>';

		foreach ( $choices as $value => $label ) {
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="radio" name="%1$s[mode]" value="%2$s"%3$s> %4$s</label>',
				esc_attr( Settings::OPTION ),
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $label )
			);
		}

		echo '</fieldset>';

		$unavailable = array();

		foreach ( $this->settings->requested_formats() as $format ) {
			if ( ! $this->capabilities->supports( $format ) ) {
				$unavailable[] = strtoupper( $format );
			}
		}

		if ( $unavailable ) {
			echo '<p class="notice notice-warning inline" style="padding:8px 12px;margin-top:10px">';
			printf(
				/* translators: %s: comma separated list of format names. */
				esc_html__( 'Deze server kan geen %s coderen, er worden daarvoor dus geen bestanden gegenereerd. Zie de tabel hierboven.', 'pdk-theme-options' ),
				esc_html( implode( ', ', $unavailable ) )
			);
			echo '</p>';
		}
	}

	/**
	 * Quality number input.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function field_quality( $args ) {
		$format = isset( $args['format'] ) ? $args['format'] : 'webp';
		$key    = $format . '_quality';

		printf(
			'<input type="number" min="1" max="100" step="1" class="small-text" name="%1$s[%2$s]" value="%3$s">',
			esc_attr( Settings::OPTION ),
			esc_attr( $key ),
			esc_attr( (string) $this->settings->get( $key ) )
		);

		echo ' <span class="description">';
		echo 'avif' === $format
			? esc_html__( 'Standaard 50.', 'pdk-theme-options' )
			: esc_html__( 'Standaard 82.', 'pdk-theme-options' );
		echo '</span>';
	}

	/**
	 * Picture replacement checkbox.
	 *
	 * @return void
	 */
	public function field_picture() {
		printf(
			'<label><input type="checkbox" name="%1$s[picture_enabled]" value="1"%2$s> %3$s</label>',
			esc_attr( Settings::OPTION ),
			checked( (bool) $this->settings->get( 'picture_enabled' ), true, false ),
			esc_html__( 'Geschikte <img>-elementen vervangen door <picture>', 'pdk-theme-options' )
		);
	}

	/**
	 * Checkbox voor de paginabrede transformatie.
	 *
	 * @return void
	 */
	public function field_whole_page() {
		printf(
			'<label><input type="checkbox" name="%1$s[whole_page]" value="1"%2$s> %3$s</label>',
			esc_attr( Settings::OPTION ),
			checked( (bool) $this->settings->get( 'whole_page' ), true, false ),
			esc_html__( 'Ook afbeeldingen omzetten die buiten de berichtinhoud staan', 'pdk-theme-options' )
		);

		echo '<p class="description">' . esc_html__( 'Terugval voor paginabuilders die hun eigen sjabloon opbouwen: daar komen de gewone contentfilters nooit langs, dus blijven hun afbeeldingen onaangeroerd. Met deze optie leest IMGX de hele pagina na in plaats van alleen de berichtinhoud. Voor Oxygen, Oxygen Classic en Breakdance is dit niet nodig — die hebben een eigen koppeling en werken altijd. Laat hem uit bij een gewoon thema: het scheelt werk per paginaweergave.', 'pdk-theme-options' ) . '</p>';

		if ( $this->settings->get( 'whole_page' ) && ! $this->settings->get( 'picture_enabled' ) ) {
			echo '<p class="notice notice-warning inline" style="padding:8px 12px">' . esc_html__( 'Vervanging door picture-element staat uit, dus er wordt niets omgezet — ook niet met deze optie aan.', 'pdk-theme-options' ) . '</p>';
		}
	}

	/**
	 * Upload behaviour fields.
	 *
	 * @return void
	 */
	public function field_upload() {
		printf(
			'<label><input type="checkbox" name="%1$s[generate_on_upload]" value="1"%2$s> %3$s</label><br><br>',
			esc_attr( Settings::OPTION ),
			checked( (bool) $this->settings->get( 'generate_on_upload' ), true, false ),
			esc_html__( 'Varianten genereren voor nieuwe uploads', 'pdk-theme-options' )
		);

		$mode = (string) $this->settings->get( 'upload_mode' );

		foreach (
			array(
				'async' => __( 'Op de achtergrond, kort na het uploaden (aanbevolen)', 'pdk-theme-options' ),
				'sync'  => __( 'Direct tijdens het uploaden', 'pdk-theme-options' ),
			) as $value => $label
		) {
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="radio" name="%1$s[upload_mode]" value="%2$s"%3$s> %4$s</label>',
				esc_attr( Settings::OPTION ),
				esc_attr( $value ),
				checked( $mode, $value, false ),
				esc_html( $label )
			);
		}

		echo '<p class="description">' . esc_html__( 'AVIF-codering kan seconden per afbeelding kosten. Direct genereren riskeert een timeout op shared hosting; op de achtergrond genereren gebruikt WP-Cron en draait bij een van de volgende paginaweergaven.', 'pdk-theme-options' ) . '</p>';

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON && 'async' === $mode ) {
			echo '<p class="notice notice-warning inline" style="padding:8px 12px">' . esc_html__( 'WP-Cron staat uit op deze site. Zorg dat een echte cronjob wp-cron.php draait, of gebruik de knoppen hieronder om afbeeldingen te genereren.', 'pdk-theme-options' ) . '</p>';
		}
	}

	/**
	 * Debug checkbox.
	 *
	 * @return void
	 */
	public function field_debug() {
		printf(
			'<label><input type="checkbox" name="%1$s[debug]" value="1"%2$s> %3$s</label>',
			esc_attr( Settings::OPTION ),
			checked( (bool) $this->settings->get( 'debug' ), true, false ),
			esc_html__( 'Gedetailleerde conversiemeldingen naar de PHP-foutlog schrijven (vereist WP_DEBUG)', 'pdk-theme-options' )
		);
	}

	/**
	 * Enqueues the batch UI assets on our screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen om te bepalen welke assets nodig zijn.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( 'toplevel_page_' . \PDK_Admin::PAGE_SLUG !== $hook || \PDK_ImgX::TAB !== $tab ) {
			return;
		}

		wp_enqueue_style( 'imgx-admin', IMGX_URL . 'assets/css/imgx-admin.css', array(), IMGX_VERSION );
		wp_enqueue_script( 'imgx-admin', IMGX_URL . 'assets/js/imgx-admin.js', array(), IMGX_VERSION, true );

		wp_localize_script(
			'imgx-admin',
			'imgxAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Batch_Processor::NONCE_ACTION ),
				'i18n'    => array(
					'confirmAll'    => __( 'Alle NextGen-afbeeldingen opnieuw genereren? Bestaande gegenereerde bestanden worden verwijderd en opnieuw opgebouwd. Je originele afbeeldingen blijven ongemoeid.', 'pdk-theme-options' ),
					'confirmDelete' => __( 'Alle door IMGX gegenereerde bestanden verwijderen? Je originele afbeeldingen blijven ongemoeid. Je kunt ze daarna opnieuw genereren.', 'pdk-theme-options' ),
					'running'       => __( 'Bezig…', 'pdk-theme-options' ),
					'done'          => __( 'Klaar.', 'pdk-theme-options' ),
					'cancelled'     => __( 'Gestopt.', 'pdk-theme-options' ),
					'failed'        => __( 'Het verzoek is mislukt. Kijk in je serverfoutlog.', 'pdk-theme-options' ),
				),
			)
		);
	}

	/**
	 * Rendert de instellingen binnen de PDK-tab. Geen eigen .wrap of <h1> —
	 * die staan al om de tab heen.
	 *
	 * @return void
	 */
	public function render_inline() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Je hebt geen toegang tot deze pagina.', 'pdk-theme-options' ) );
		}

		$stats = $this->stats();

		?>
		<div class="imgx-wrap">
			<?php
			// options.php stuurt terug met ?settings-updated. WordPress toont die
			// melding alleen automatisch onder Instellingen, niet op een eigen
			// menupagina — dus hier zelf.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
			// options.php zet de melding weg onder de slug 'general', niet onder
			// de optienaam — dus geen slug meegeven, anders komt er niets.
			if ( isset( $_GET['settings-updated'] ) ) {
				settings_errors();
			}
			?>

			<?php if ( isset( $_GET['imgx-rechecked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice. ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Serverondersteuning is opnieuw gecontroleerd.', 'pdk-theme-options' ); ?></p>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( Settings::GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Mediabibliotheek', 'pdk-theme-options' ); ?></h2>

			<table class="widefat striped imgx-stats">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Afbeeldingen gescand', 'pdk-theme-options' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Afbeeldingen met varianten', 'pdk-theme-options' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $stats['registered'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Afbeeldingen zonder varianten', 'pdk-theme-options' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $stats['missing'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Geoptimaliseerde varianten', 'pdk-theme-options' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $stats['files'] ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<p class="imgx-actions">
				<button type="button" class="button button-primary" data-imgx-run="missing">
					<?php esc_html_e( 'Ontbrekende afbeeldingen genereren', 'pdk-theme-options' ); ?>
				</button>
				<button type="button" class="button" data-imgx-run="all">
					<?php esc_html_e( 'Alle NextGen-afbeeldingen opnieuw genereren', 'pdk-theme-options' ); ?>
				</button>
				<button type="button" class="button button-link-delete" data-imgx-run="delete">
					<?php esc_html_e( 'Alle gegenereerde bestanden verwijderen', 'pdk-theme-options' ); ?>
				</button>
				<button type="button" class="button imgx-cancel" hidden>
					<?php esc_html_e( 'Stoppen', 'pdk-theme-options' ); ?>
				</button>
			</p>

			<div class="imgx-progress" hidden>
				<div class="imgx-progress-bar"><span style="width:0%"></span></div>
				<p class="imgx-progress-text" role="status" aria-live="polite"></p>
				<ul class="imgx-progress-errors"></ul>
			</div>

			<p class="description">
				<?php esc_html_e( 'Grote bibliotheken gaan veel sneller via de commandoregel: wp imgx generate', 'pdk-theme-options' ); ?>
			</p>

			<hr>

			<h2><?php esc_html_e( 'Serverondersteuning', 'pdk-theme-options' ); ?></h2>
			<?php $this->render_support_table(); ?>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="imgx_recheck">
				<?php wp_nonce_field( 'imgx_recheck' ); ?>
				<?php submit_button( __( 'Serverondersteuning opnieuw controleren', 'pdk-theme-options' ), 'secondary', 'submit', false ); ?>
			</form>

			<?php $this->render_errors(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the encoder support table.
	 *
	 * @return void
	 */
	private function render_support_table() {
		$report = $this->capabilities->get();

		?>
		<table class="widefat striped imgx-support">
			<tbody>
				<?php foreach ( array( 'avif', 'webp' ) as $format ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( strtoupper( $format ) ); ?></th>
						<td>
							<?php if ( ! empty( $report[ $format ] ) ) : ?>
								<span class="imgx-yes" aria-hidden="true">&#10003;</span>
								<?php esc_html_e( 'Ondersteund', 'pdk-theme-options' ); ?>
							<?php else : ?>
								<span class="imgx-no" aria-hidden="true">&#10007;</span>
								<?php esc_html_e( 'Niet beschikbaar', 'pdk-theme-options' ); ?>
								<span class="description"><?php echo esc_html( $this->capabilities->reason( $format ) ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Afbeeldingsbewerker', 'pdk-theme-options' ); ?></th>
					<td><?php echo esc_html( $this->capabilities->editor_name() ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders the recent error list.
	 *
	 * @return void
	 */
	private function render_errors() {
		$errors = $this->logger->recent_errors();

		if ( ! $errors ) {
			return;
		}

		?>
		<hr>
		<h2><?php esc_html_e( 'Recente conversiefouten', 'pdk-theme-options' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Wanneer', 'pdk-theme-options' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Melding', 'pdk-theme-options' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $errors as $error ) : ?>
				<tr>
					<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $error['time'] ) ); ?></td>
					<td><?php echo esc_html( (string) $error['message'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="imgx_clear_errors">
			<?php wp_nonce_field( 'imgx_clear_errors' ); ?>
			<?php submit_button( __( 'Foutenlijst leegmaken', 'pdk-theme-options' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Handles the re-check button.
	 *
	 * @return void
	 */
	public function handle_recheck() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Je hebt geen rechten om dit te doen.', 'pdk-theme-options' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'imgx_recheck' );

		$this->capabilities->refresh();

		wp_safe_redirect( \PDK_ImgX::tab_url( array( 'imgx-rechecked' => '1' ) ) );
		exit;
	}

	/**
	 * Handles the clear-errors button.
	 *
	 * @return void
	 */
	public function handle_clear_errors() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Je hebt geen rechten om dit te doen.', 'pdk-theme-options' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'imgx_clear_errors' );

		$this->logger->clear_errors();

		wp_safe_redirect( \PDK_ImgX::tab_url() );
		exit;
	}

	/**
	 * Library statistics, cached because counting variant files reads every registry row.
	 *
	 * ponytail: full table scan of the IMGX meta rows, cached for five minutes. If a site
	 * ever grows large enough for that to hurt, store a running counter instead.
	 *
	 * @return array
	 */
	private function stats() {
		$cached = get_transient( self::STATS_TRANSIENT );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$counts = wp_count_attachments();
		$total  = 0;

		foreach ( Generator::source_mime_types() as $mime ) {
			if ( isset( $counts->$mime ) ) {
				$total += (int) $counts->$mime;
			}
		}

		$registered = Variants::count_registered();

		$stats = array(
			'total'      => $total,
			'registered' => $registered,
			'missing'    => max( 0, $total - $registered ),
			'files'      => Variants::count_files(),
		);

		set_transient( self::STATS_TRANSIENT, $stats, self::STATS_TTL );

		return $stats;
	}

	/**
	 * Adds the NextGen column to the Media Library list view.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function media_column( $columns ) {
		$columns['imgx'] = __( 'NextGen', 'pdk-theme-options' );

		return $columns;
	}

	/**
	 * Renders the NextGen column.
	 *
	 * Reads one post meta value, which WP_Query has already primed for the whole page.
	 *
	 * @param string $column        Column key.
	 * @param int    $attachment_id Attachment ID.
	 * @return void
	 */
	public function media_column_content( $column, $attachment_id ) {
		if ( 'imgx' !== $column ) {
			return;
		}

		if ( ! $this->generator->is_supported_attachment( $attachment_id ) ) {
			echo '<span class="imgx-muted">&mdash;</span>';

			return;
		}

		$variants = Variants::get( $attachment_id );
		$present  = array();

		foreach ( $variants['sizes'] as $formats ) {
			foreach ( array_keys( $formats ) as $format ) {
				$present[ $format ] = true;
			}
		}

		$out = array();

		foreach ( array( 'avif', 'webp' ) as $format ) {
			$out[] = sprintf(
				'<span class="%1$s">%2$s %3$s</span>',
				isset( $present[ $format ] ) ? 'imgx-yes' : 'imgx-no',
				esc_html( strtoupper( $format ) ),
				isset( $present[ $format ] ) ? '&#10003;' : '&#10007;'
			);
		}

		echo wp_kses_post( implode( '<br>', $out ) );
	}
}
