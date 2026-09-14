<?php
/**
 * Composition root.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * Instantiates and wires every service, then registers their hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Capability detection service.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Single-file converter.
	 *
	 * @var Converter
	 */
	private $converter;

	/**
	 * Per-attachment generator.
	 *
	 * @var Generator
	 */
	private $generator;

	/**
	 * Frontend renderer.
	 *
	 * @var Picture_Renderer
	 */
	private $renderer;

	/**
	 * Batch processor.
	 *
	 * @var Batch_Processor
	 */
	private $batch;

	/**
	 * Admin screen, only built inside wp-admin.
	 *
	 * @var Admin|null
	 */
	private $admin = null;

	/**
	 * Returns the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor; use instance().
	 */
	private function __construct() {
		$this->logger       = new Logger();
		$this->settings     = new Settings();
		$this->capabilities = new Capabilities( $this->logger );
		$this->converter    = new Converter( $this->settings, $this->capabilities, $this->logger );
		$this->generator    = new Generator( $this->settings, $this->capabilities, $this->converter, $this->logger );
		$this->renderer     = new Picture_Renderer( $this->settings, $this->capabilities );
		$this->batch        = new Batch_Processor( $this->generator, $this->settings );
	}

	/**
	 * Registers hooks. Safe to call more than once.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Geen eigen textdomain-load: de teksten staan Nederlands in de code
		// onder het textdomain 'pdk-theme-options', dat de plugin zelf laadt.

		// Multisite: elke site heeft eigen opties, eigen uploadsmap en eigen
		// bijlagen. De caches hieronder staan op ID of op bestandsnaam, en die
		// botsen tussen sites — bijlage 42 bestaat overal. Zonder deze reset
		// zou na switch_to_blog() de registratie van de vórige site gelden.
		add_action( 'switch_blog', array( $this, 'flush_blog_caches' ) );

		$this->settings->register_hooks();
		$this->generator->register_hooks();
		$this->renderer->register_hooks();
		$this->batch->register_hooks();

		// Oxygen, Oxygen Classic en Breakdance renderen buiten the_content om;
		// deze klasse haakt op hun eigen render-filters.
		$oxygen = new Oxygen( $this->renderer );
		$oxygen->register_hooks();

		if ( is_admin() ) {
			$this->admin = new Admin( $this->settings, $this->capabilities, $this->generator, $this->logger );
			$this->admin->register_hooks();
		}

		$site_health = new Site_Health( $this->settings, $this->capabilities );
		$site_health->register_hooks();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			CLI::register( $this->generator, $this->capabilities, $this->settings );
		}
	}

	/**
	 * Settings service.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Capability detection service.
	 *
	 * @return Capabilities
	 */
	public function capabilities() {
		return $this->capabilities;
	}

	/**
	 * Converter service.
	 *
	 * @return Converter
	 */
	public function converter() {
		return $this->converter;
	}

	/**
	 * Generator service.
	 *
	 * @return Generator
	 */
	public function generator() {
		return $this->generator;
	}

	/**
	 * Frontend renderer. Public so themes can wrap markup IMGX does not see itself.
	 *
	 * @return Picture_Renderer
	 */
	public function renderer() {
		return $this->renderer;
	}

	/**
	 * Leegt alle caches die per site verschillen. Hangt aan switch_blog.
	 *
	 * @return void
	 */
	public function flush_blog_caches() {
		Files::reset_cache();
		Variants::flush_cache();
		$this->settings->flush_cache();
		$this->renderer->reset();
	}

	/**
	 * Admin screen. Null outside wp-admin.
	 *
	 * @return Admin|null
	 */
	public function admin() {
		return $this->admin;
	}

	/**
	 * Logger.
	 *
	 * @return Logger
	 */
	public function logger() {
		return $this->logger;
	}

	/**
	 * Blocked: singletons are not cloneable.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Blocked: singletons are not serialisable.
	 *
	 * @throws \LogicException Always.
	 * @return void
	 */
	public function __wakeup() {
		throw new \LogicException( 'IMGX\Plugin cannot be unserialized.' );
	}
}
