<?php
/**
 * Module: Levertijden (WooCommerce)
 *
 * Algemene levertijdtekst per weekdag met cutoff-tijd, plus uitzonderingsdata
 * (kerst, bedrijfsuitje). Per product kan via het productveld `pdk_edt` een
 * uitzondering ingevuld worden; die heeft altijd voorrang.
 *
 * Output via de shortcode [levertijd] op een productpagina.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Delivery_Time {

	const PRODUCT_META = 'pdk_edt';

	public function __construct( PDK_Loader $loader ) {
		// Shortcode [levertijd] + template-hook do_action( 'pdk_levertijd' ).
		pdk_register_frontend_output(
			'levertijd',
			[ $this, 'shortcode' ],
			'<h4 class="eta__title">Levertijd: </h4> gevolgd door <p class="eta__content">de levertijdtekst</p>. '
			. 'Geeft een lege string buiten een productpagina (er is dan geen $product) en wanneer er geen tekst is ingesteld. '
			. 'De tekst komt uit het productveld pdk_edt, of anders uit de algemene instelling met {cutoff}, {dag} en '
			. '{volgende_dag} al ingevuld. Opmaak via Custom CSS op .eta__title en .eta__content.'
		);

		// Productveld: WooCommerce-functies zijn pas op plugins_loaded beschikbaar.
		add_action( 'plugins_loaded', [ $this, 'init_product_field' ], 20 );
	}

	public function init_product_field(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'woocommerce_product_options_tax',  [ $this, 'render_product_field' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_field' ] );
	}

	// -------------------------------------------------------------------------
	// Instellingen
	// -------------------------------------------------------------------------

	/** @return array<int,string> */
	public static function day_labels(): array {
		return pdk_day_labels();
	}

	/** Instellingen met terugval op de standaardwaarden. */
	private static function settings(): array {
		$defaults = PDK_Settings::get_defaults()['delivery_time'];
		$saved    = (array) PDK_Settings::get( 'delivery_time' );
		$settings = array_merge( $defaults, $saved );

		// Per dag apart samenvoegen — een deels opgeslagen dag mag geen
		// ontbrekende cutoff opleveren.
		$days = [];
		foreach ( $defaults['days'] as $index => $day ) {
			$days[ $index ] = array_merge( $day, (array) ( $settings['days'][ $index ] ?? [] ) );
		}
		$settings['days'] = $days;

		return $settings;
	}

	// -------------------------------------------------------------------------
	// Berekening
	// -------------------------------------------------------------------------

	/** Berekent de algemene levertijdtekst op basis van dag, tijd en uitzonderingen. */
	public static function general_text(): string {
		$settings = self::settings();
		$days     = $settings['days'];
		$labels   = self::day_labels();

		$today     = new DateTimeImmutable( current_time( 'Y-m-d' ), wp_timezone() );
		$today_idx = (int) $today->format( 'N' );
		$now       = current_time( 'H:i' );
		$current   = $days[ $today_idx ] ?? [ 'enabled' => false, 'cutoff' => '' ];

		// Vandaag is een verzenddag, nog vóór de cutoff en geen sluitingsdag.
		if (
			! empty( $current['enabled'] )
			&& $now < $current['cutoff']
			&& ! self::is_closed_period( $today->format( 'Y-m-d' ) )
		) {
			return str_replace(
				[ '{cutoff}', '{dag}' ],
				[ self::format_time( $current['cutoff'] ), __( 'vandaag', 'pdk-theme-options' ) ],
				$settings['text_before']
			);
		}

		// Anders: eerstvolgende kalenderdag die wél verzendt (skipt uitgevinkte
		// weekdagen én sluitingsdagen, ook bij een meerdaagse periode).
		for ( $i = 1; $i <= 60; $i++ ) {
			$date  = $today->modify( "+{$i} days" );
			$index = (int) $date->format( 'N' );

			if ( empty( $days[ $index ]['enabled'] ) || self::is_closed_period( $date->format( 'Y-m-d' ) ) ) {
				continue;
			}

			return str_replace(
				[ '{cutoff}', '{volgende_dag}' ],
				[
					self::format_time( $current['cutoff'] ),
					strtolower( $labels[ $index ] ) . ' ' . $date->format( 'j-n' ),
				],
				$settings['text_after']
			);
		}

		// Geen enkele verzenddag binnen 60 dagen — geen tekst tonen.
		return '';
	}

	/** '22:00' → '22.00' (NL-schrijfwijze). */
	private static function format_time( string $time ): string {
		return str_replace( ':', '.', $time );
	}

	/**
	 * Valt een datum in een periode waarop de deur dicht is?
	 *
	 * Periodes staan centraal in Site Instellingen → Afwijkende dagen. Een
	 * periode zónder openingstijden telt als sluitingsdag en verzendt dus niet;
	 * een periode met afwijkende tijden (zomerperiode) verzendt gewoon volgens
	 * de weekdagregel hierboven.
	 */
	private static function is_closed_period( string $ymd ): bool {
		$period = PDK_Site_Settings::active_period( $ymd );

		return $period && ( '' === $period['open'] || '' === $period['close'] );
	}

	// -------------------------------------------------------------------------
	// Frontend
	// -------------------------------------------------------------------------

	public function shortcode( array $atts = [] ): string {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		// Product-uitzondering heeft altijd voorrang op de algemene instellingen.
		$text = (string) $product->get_meta( self::PRODUCT_META );

		if ( '' === $text ) {
			$text = self::general_text();
		}

		if ( '' === $text ) {
			return '';
		}

		return '<h4 class="eta__title">' . esc_html__( 'Levertijd:', 'pdk-theme-options' ) . ' </h4>'
			. '<p class="eta__content">' . esc_html( $text ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Productveld (uitzondering per product)
	// -------------------------------------------------------------------------

	public function render_product_field(): void {
		woocommerce_wp_text_input( [
			'id'          => self::PRODUCT_META,
			'label'       => __( 'Levertijd (uitzondering)', 'pdk-theme-options' ),
			'placeholder' => __( 'bv. 1-2 werkdagen', 'pdk-theme-options' ),
			'desc_tip'    => true,
			'description' => __( 'Alleen invullen als dit product afwijkt van de algemene levertijd-instellingen (PDK Tools > Levertijden). Leeg laten voor de standaardtekst.', 'pdk-theme-options' ),
		] );
	}

	public function save_product_field( int $post_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce controleert de nonce vóór deze hook.
		if ( ! isset( $_POST[ self::PRODUCT_META ] ) ) {
			return; // Veld niet meegestuurd (bijv. quick edit) — bestaande waarde behouden.
		}

		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return;
		}

		$product->update_meta_data( self::PRODUCT_META, sanitize_text_field( wp_unslash( $_POST[ self::PRODUCT_META ] ) ) );
		$product->save();
		// phpcs:enable
	}

	// -------------------------------------------------------------------------
	// Admin-tab
	// -------------------------------------------------------------------------

	/** Verwerkt het opslaan vanaf de Levertijden-tab. */
	public static function save(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce is al gecontroleerd in PDK_Admin::handle_save().
		$posted   = (array) ( $_POST['delivery_time'] ?? [] );
		$defaults = PDK_Settings::get_defaults()['delivery_time'];

		$days = [];
		foreach ( $defaults['days'] as $index => $day ) {
			$cutoff = sanitize_text_field( wp_unslash( $posted['days'][ $index ]['cutoff'] ?? '' ) );

			$days[ $index ] = [
				'enabled' => ! empty( $posted['days'][ $index ]['enabled'] ),
				'cutoff'  => preg_match( '/^\d{2}:\d{2}$/', $cutoff ) ? $cutoff : $day['cutoff'],
			];
		}

		$options = (array) PDK_Settings::get();

		// Bewust géén PDK_Settings::update(): die merget recursief, waardoor een
		// uitgevinkte verzenddag ingeschakeld zou blijven. Dit blok wordt vervangen.
		$options['delivery_time'] = [
			'enabled'     => PDK_Settings::get( 'delivery_time', 'enabled' ) ?? $defaults['enabled'],
			'days'        => $days,
			'text_before' => sanitize_text_field( wp_unslash( $posted['text_before'] ?? $defaults['text_before'] ) ),
			'text_after'  => sanitize_text_field( wp_unslash( $posted['text_after'] ?? $defaults['text_after'] ) ),
		];

		update_option( PDK_Settings::OPTION_KEY, $options );
		// phpcs:enable
	}

	/** Rendert de tab-inhoud binnen het bestaande instellingenformulier. */
	public static function render_inline(): void {
		$settings = self::settings();
		$labels   = self::day_labels();
		?>
		<p class="description" style="margin:12px 0 16px;">
			<?php esc_html_e( 'Stel per dag in of er die dag verzonden wordt en tot welk tijdstip een bestelling nog dezelfde dag meegaat. Plaats de shortcode [levertijd] op de productpagina. Per product kan via het productveld "Levertijd (uitzondering)" een afwijkende tekst ingesteld worden; die heeft voorrang.', 'pdk-theme-options' ); ?>
		</p>

		<h2><?php esc_html_e( 'Verzenddagen', 'pdk-theme-options' ); ?></h2>
		<table class="widefat" style="max-width:600px;margin-bottom:20px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Dag', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Verzendt deze dag', 'pdk-theme-options' ); ?></th>
					<th><?php esc_html_e( 'Tot welk tijdstip', 'pdk-theme-options' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $labels as $index => $label ) : ?>
				<tr>
					<td><?php echo esc_html( $label ); ?></td>
					<td>
						<input type="checkbox" name="delivery_time[days][<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( ! empty( $settings['days'][ $index ]['enabled'] ) ); ?>>
					</td>
					<td>
						<input type="time" name="delivery_time[days][<?php echo esc_attr( $index ); ?>][cutoff]" value="<?php echo esc_attr( $settings['days'][ $index ]['cutoff'] ); ?>">
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Sluitingsdagen', 'pdk-theme-options' ); ?></h2>
		<p class="description" style="margin-bottom:20px;">
			<?php
			printf(
				/* translators: %s: link naar Site Instellingen → Afwijkende dagen */
				esc_html__( 'Dagen zonder verzending (kerst, bedrijfsvakantie) staan bij %s. Daar gelden ze meteen ook voor de openingstijden, dus je vult ze maar op één plek in. Een periode zonder openingstijden telt als niet-verzenddag; een periode met afwijkende tijden verzendt gewoon volgens de weekdagen hierboven.', 'pdk-theme-options' ),
				'<a href="' . esc_url( add_query_arg(
					[ 'page' => PDK_Admin::PAGE_SLUG, 'tab' => 'site_settings' ],
					admin_url( 'admin.php' )
				) ) . '#afwijkende-dagen">' . esc_html__( 'Site Instellingen → Afwijkende dagen', 'pdk-theme-options' ) . '</a>'
			);
			?>
		</p>

		<h2 style="margin-top:1.5em;"><?php esc_html_e( 'Teksten', 'pdk-theme-options' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="pdk_text_before"><?php esc_html_e( 'Tekst vóór cutoff-tijd', 'pdk-theme-options' ); ?></label></th>
				<td>
					<input type="text" class="large-text" id="pdk_text_before" name="delivery_time[text_before]" value="<?php echo esc_attr( $settings['text_before'] ); ?>">
					<p class="description"><?php esc_html_e( 'Beschikbare tags:', 'pdk-theme-options' ); ?> <code>{cutoff}</code>, <code>{dag}</code></p>
				</td>
			</tr>
			<tr>
				<th><label for="pdk_text_after"><?php esc_html_e( 'Tekst ná cutoff-tijd / niet-verzenddag', 'pdk-theme-options' ); ?></label></th>
				<td>
					<input type="text" class="large-text" id="pdk_text_after" name="delivery_time[text_after]" value="<?php echo esc_attr( $settings['text_after'] ); ?>">
					<p class="description"><?php esc_html_e( 'Beschikbare tags:', 'pdk-theme-options' ); ?> <code>{cutoff}</code>, <code>{volgende_dag}</code></p>
				</td>
			</tr>
		</table>

		<p class="description">
			<strong><?php esc_html_e( 'Voorbeeld nu:', 'pdk-theme-options' ); ?></strong>
			<?php echo esc_html( self::general_text() ?: __( '(geen verzenddag gevonden)', 'pdk-theme-options' ) ); ?>
		</p>
		<?php
	}
}
