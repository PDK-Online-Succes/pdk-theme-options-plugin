<?php
/**
 * Option storage and the Settings API registration.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the single imgx_settings option.
 */
class Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'imgx_settings';

	/**
	 * Settings group used by the Settings API.
	 */
	const GROUP = 'imgx_settings_group';

	/**
	 * Available generation modes.
	 */
	const MODES = array( 'avif_webp', 'avif', 'webp' );

	/**
	 * Runtime cache of the resolved option.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Registers the option with the Settings API.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'flush_cache' ) );
	}

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'mode'               => 'avif_webp',
			'webp_quality'       => 82,
			'avif_quality'       => 50,
			'picture_enabled'    => 1,
			'whole_page'         => 0,
			'generate_on_upload' => 1,
			'upload_mode'        => 'async',
			'debug'              => 0,
		);
	}

	/**
	 * Writes the defaults on activation without clobbering an existing configuration.
	 *
	 * @return void
	 */
	public static function install_defaults() {
		$current = get_option( self::OPTION, array() );

		if ( ! is_array( $current ) ) {
			$current = array();
		}

		update_option( self::OPTION, array_merge( self::defaults(), $current ) );
	}

	/**
	 * Registers the option and its sanitiser.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Drops the runtime cache.
	 *
	 * @return void
	 */
	public function flush_cache() {
		$this->cache = null;
	}

	/**
	 * Returns every setting, merged over the defaults.
	 *
	 * @return array
	 */
	public function all() {
		if ( null === $this->cache ) {
			$stored = get_option( self::OPTION, array() );

			if ( ! is_array( $stored ) ) {
				$stored = array();
			}

			$this->cache = array_merge( self::defaults(), $stored );
		}

		return $this->cache;
	}

	/**
	 * Returns a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is unknown.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$all = $this->all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Sanitises the submitted settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = self::defaults();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$clean = array();

		$mode          = isset( $input['mode'] ) ? sanitize_key( $input['mode'] ) : $defaults['mode'];
		$clean['mode'] = in_array( $mode, self::MODES, true ) ? $mode : $defaults['mode'];

		$clean['webp_quality'] = $this->clamp_quality( $input['webp_quality'] ?? $defaults['webp_quality'], $defaults['webp_quality'] );
		$clean['avif_quality'] = $this->clamp_quality( $input['avif_quality'] ?? $defaults['avif_quality'], $defaults['avif_quality'] );

		$clean['picture_enabled']    = empty( $input['picture_enabled'] ) ? 0 : 1;
		$clean['whole_page']         = empty( $input['whole_page'] ) ? 0 : 1;
		$clean['generate_on_upload'] = empty( $input['generate_on_upload'] ) ? 0 : 1;
		$clean['debug']              = empty( $input['debug'] ) ? 0 : 1;

		$upload_mode          = isset( $input['upload_mode'] ) ? sanitize_key( $input['upload_mode'] ) : $defaults['upload_mode'];
		$clean['upload_mode'] = in_array( $upload_mode, array( 'async', 'sync' ), true ) ? $upload_mode : $defaults['upload_mode'];

		$this->cache = null;

		return $clean;
	}

	/**
	 * Clamps a quality value into 1..100.
	 *
	 * @param mixed $value    Submitted value.
	 * @param int   $fallback Value to use when the input is not numeric.
	 * @return int
	 */
	private function clamp_quality( $value, $fallback ) {
		if ( ! is_numeric( $value ) ) {
			return (int) $fallback;
		}

		return (int) max( 1, min( 100, (int) $value ) );
	}

	/**
	 * Vingerafdruk van de instellingen die de uitkomst van een codering bepalen.
	 *
	 * Staat bij de variantenregistratie opgeslagen. Wijkt hij af, dan is een
	 * eerder overgeslagen variant (te groot geworden bij de oude kwaliteit) het
	 * opnieuw proberen waard.
	 *
	 * @return string
	 */
	public function encoding_signature() {
		return implode(
			'-',
			array(
				(string) $this->get( 'mode' ),
				(string) (int) $this->get( 'webp_quality' ),
				(string) (int) $this->get( 'avif_quality' ),
			)
		);
	}

	/**
	 * Formats requested by the configured mode, before capability filtering.
	 *
	 * Ordered by preference: AVIF first, WebP second.
	 *
	 * @return string[]
	 */
	public function requested_formats() {
		switch ( $this->get( 'mode' ) ) {
			case 'avif':
				return array( 'avif' );
			case 'webp':
				return array( 'webp' );
			default:
				return array( 'avif', 'webp' );
		}
	}

	/**
	 * Formats that are both requested and actually supported by this server.
	 *
	 * @param Capabilities $capabilities Capability service.
	 * @return string[]
	 */
	public function enabled_formats( Capabilities $capabilities ) {
		$formats = array();

		foreach ( $this->requested_formats() as $format ) {
			if ( $capabilities->supports( $format ) ) {
				$formats[] = $format;
			}
		}

		/**
		 * Filters the globally enabled variant formats.
		 *
		 * @param string[] $formats Format keys, ordered by preference.
		 */
		$formats = apply_filters( 'imgx_enabled_formats', $formats );

		return array_values(
			array_intersect( array( 'avif', 'webp' ), array_map( 'strval', (array) $formats ) )
		);
	}
}
