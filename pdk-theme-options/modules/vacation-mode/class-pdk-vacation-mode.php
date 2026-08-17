<?php
/**
 * Module: WooCommerce Vacation Mode
 *
 * Schakelt de webshop tijdelijk uit tijdens een vakantieperiode.
 *
 * BELANGRIJK — hooks worden uitgesteld naar plugins_loaded (prio 20) zodat
 * WooCommerce gegarandeerd beschikbaar is op het moment van registratie,
 * ongeacht de volgorde waarin WordPress plugins laadt.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Vacation_Mode {

	private array $settings = [];

	public function __construct( PDK_Loader $loader ) {
		// Gebruik add_action direct (niet via loader) zodat we op plugins_loaded
		// kunnen wachten — de loader->run() is dan al voorbij.
		add_action( 'plugins_loaded', [ $this, 'maybe_init' ], 20 );

		// Shortcode [vakantiemelding] + template-hook do_action( 'pdk_vakantiemelding' ):
		// toont de melding op een eigen plek in het thema. Geeft niets terug
		// wanneer de vakantiemodus (nog) niet actief is.
		pdk_register_frontend_output(
			'vakantiemelding',
			[ $this, 'render_notice' ],
			'<div class="pdk-vacation-notice">het ingestelde bericht (wp_kses_post, dus HTML mag)</div>. '
			. 'Geeft een lege string zolang de vakantiemodus niet actief is. Los hiervan plaatst de module dezelfde melding '
			. 'automatisch boven de shoploop, de productpagina, de winkelwagen en de kassa; in de shoploop vervangt '
			. '<span class="pdk-vacation-label button disabled"> de bestelknop.'
		);
	}

	/** Melding als HTML — leeg zolang de vakantiemodus niet actief is. */
	public function render_notice( array $atts = [] ): string {
		if ( empty( $this->settings ) ) {
			// Shortcode kan vóór maybe_init() renderen — instellingen zelf laden.
			$this->settings = array_merge(
				PDK_Settings::get_defaults()['vacation_mode'],
				(array) PDK_Settings::get( 'vacation_mode' )
			);
		}

		if ( ! $this->is_active_now() ) {
			return '';
		}

		return '<div class="pdk-vacation-notice">' . wp_kses_post( $this->get_message() ) . '</div>';
	}

	/** Wordt aangeroepen op plugins_loaded prio 20 — WooCommerce is nu beschikbaar. */
	public function maybe_init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->settings = array_merge(
			PDK_Settings::get_defaults()['vacation_mode'],
			(array) PDK_Settings::get( 'vacation_mode' )
		);

		if ( ! $this->is_active_now() ) {
			return;
		}

		// Meldingen op relevante pagina's.
		add_action( 'woocommerce_before_shop_loop',      [ $this, 'show_notice' ], 5 );
		add_action( 'woocommerce_before_single_product', [ $this, 'show_notice' ], 5 );
		add_action( 'woocommerce_before_cart',           [ $this, 'show_notice' ], 5 );
		add_action( 'woocommerce_before_checkout_form',  [ $this, 'show_notice' ], 5 );

		if ( ! empty( $this->settings['disable_add_to_cart'] ) ) {
			// Markeer producten als niet-koopbaar → WooCommerce verbergt de knop.
			add_filter( 'woocommerce_is_purchasable',        [ $this, 'disable_purchasable' ],     10, 2 );
			// Blokkeer directe add-to-cart POSTs (bijv. via URL of AJAX).
			add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'block_add_to_cart' ],      10, 2 );
			// Verberg de knop in shop-overzichten als terugval.
			add_filter( 'woocommerce_loop_add_to_cart_link',  [ $this, 'replace_loop_button' ],    10, 2 );
		}

		if ( ! empty( $this->settings['disable_checkout'] ) ) {
			// Redirect weg van de checkout voordat de pagina rendert.
			add_action( 'template_redirect',           [ $this, 'redirect_from_checkout' ] );
			// Extra blokkade bij cart-validatie en checkout-submit.
			add_action( 'woocommerce_check_cart_items', [ $this, 'add_checkout_error' ] );
			add_action( 'woocommerce_checkout_process', [ $this, 'add_checkout_error' ] );
		}
	}

	/**
	 * Controleert of de vakantiemodus nu actief moet zijn.
	 *
	 * De datums staan in de gedeelde periodelijst (Site Instellingen →
	 * Afwijkende dagen); een periode met "Webshop sluiten" zet de modus aan.
	 * Staat er geen enkele sluiting gepland, dan telt de module-toggle zelf als
	 * "nu dicht" — zo blijft de knop bruikbaar om de shop handmatig te sluiten.
	 */
	private function is_active_now(): bool {
		if ( ! PDK_Site_Settings::has_shop_closures() ) {
			return true;
		}

		return PDK_Site_Settings::shop_closed_now();
	}

	private function get_message(): string {
		return $this->settings['message'] ?: __( 'De webshop is tijdelijk gesloten.', 'pdk-theme-options' );
	}

	// -------------------------------------------------------------------------
	// Hook-callbacks
	// -------------------------------------------------------------------------

	public function show_notice(): void {
		wc_add_notice( wp_kses_post( $this->get_message() ), 'notice' );
	}

	/** @param bool       $purchasable
	 *  @param \WC_Product $product */
	public function disable_purchasable( bool $purchasable, $product ): bool {
		return false;
	}

	/** @param bool $valid
	 *  @param int  $product_id */
	public function block_add_to_cart( bool $valid, int $product_id ): bool {
		wc_add_notice( wp_kses_post( $this->get_message() ), 'error' );
		return false;
	}

	/** @param string     $link
	 *  @param \WC_Product $product */
	public function replace_loop_button( string $link, $product ): string {
		return sprintf(
			'<span class="pdk-vacation-label button disabled">%s</span>',
			esc_html__( 'Tijdelijk niet beschikbaar', 'pdk-theme-options' )
		);
	}

	/** Stuurt bezoekers die naar de checkout gaan terug naar de winkelpagina. */
	public function redirect_from_checkout(): void {
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}

		// Niet redirecten op de order-ontvangen pagina — bestelling is al geplaatst.
		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
			wc_add_notice( wp_kses_post( $this->get_message() ), 'notice' );
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}
	}

	public function add_checkout_error(): void {
		wc_add_notice( wp_kses_post( $this->get_message() ), 'error' );
	}
}
