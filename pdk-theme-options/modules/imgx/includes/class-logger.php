<?php
/**
 * Opt-in logging and a small rolling error buffer for the admin screen.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * Writes to the PHP error log only when debugging is explicitly enabled, and keeps the
 * most recent errors in an option so the settings screen can show something useful.
 */
class Logger {

	/**
	 * Option holding the rolling error buffer.
	 */
	const ERRORS_OPTION = 'imgx_recent_errors';

	/**
	 * How many errors to keep.
	 */
	const MAX_ERRORS = 30;

	/**
	 * Whether verbose logging is on. Resolved lazily so Settings need not be injected.
	 *
	 * @var bool|null
	 */
	private $debug = null;

	/**
	 * Writes a debug line. No-op unless both WP_DEBUG and the IMGX debug setting are on.
	 *
	 * @param string $message Message.
	 * @param array  $context Optional context, appended as JSON.
	 * @return void
	 */
	public function debug( $message, array $context = array() ) {
		if ( ! $this->is_debug() ) {
			return;
		}

		$line = 'IMGX: ' . $message;
		if ( $context ) {
			$line .= ' ' . wp_json_encode( $context );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Guarded by an explicit debug setting.
		error_log( $line );
	}

	/**
	 * Records an error: always stored in the rolling buffer, logged only when debugging.
	 *
	 * @param string $message Human readable message.
	 * @param array  $context Optional context.
	 * @return void
	 */
	public function error( $message, array $context = array() ) {
		$this->debug( $message, $context );

		$errors = get_option( self::ERRORS_OPTION, array() );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}

		array_unshift(
			$errors,
			array(
				'time'    => time(),
				'message' => (string) $message,
				'context' => array_map( 'strval', $context ),
			)
		);

		update_option( self::ERRORS_OPTION, array_slice( $errors, 0, self::MAX_ERRORS ), false );
	}

	/**
	 * Returns the rolling error buffer, newest first.
	 *
	 * @return array
	 */
	public function recent_errors() {
		$errors = get_option( self::ERRORS_OPTION, array() );

		return is_array( $errors ) ? $errors : array();
	}

	/**
	 * Empties the rolling error buffer.
	 *
	 * @return void
	 */
	public function clear_errors() {
		delete_option( self::ERRORS_OPTION );
	}

	/**
	 * Whether verbose logging is enabled.
	 *
	 * @return bool
	 */
	private function is_debug() {
		if ( null === $this->debug ) {
			$settings    = get_option( Settings::OPTION, array() );
			$this->debug = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
				&& is_array( $settings )
				&& ! empty( $settings['debug'] );
		}

		return $this->debug;
	}
}
