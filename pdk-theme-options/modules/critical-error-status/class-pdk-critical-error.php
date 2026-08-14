<?php
/**
 * Module: Critical Error Status
 *
 * Geeft een HTTP 500-statuscode terug bij kritieke PHP-fouten (E_ERROR, E_PARSE, enz.).
 * Logt de fout naar debug.log als WP_DEBUG_LOG actief is.
 *
 * Gevoelige foutdetails worden NOOIT naar de browser gestuurd;
 * alleen beheerders kunnen de loggegevens inzien.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Critical_Error_Status {

	public function __construct( PDK_Loader $loader ) {
		// Gebruik een directe add_action; de shutdown-handler moet absoluut lopen.
		add_action( 'shutdown', [ $this, 'handle_shutdown' ], PHP_INT_MAX );
	}

	public function handle_shutdown(): void {
		$error = error_get_last();

		if ( ! $error ) {
			return;
		}

		$fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ];

		if ( ! in_array( $error['type'], $fatal_types, true ) ) {
			return;
		}

		// Stuur HTTP 500 als de headers nog niet zijn verstuurd.
		if ( ! headers_sent() ) {
			status_header( 500 );
		}

		// Log alleen als WP_DEBUG_LOG actief is — nooit publiek tonen.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$message = sprintf(
				"[PDK Critical Error][%s] %s in %s on line %d\n",
				gmdate( 'Y-m-d H:i:s' ),
				$error['message'],
				$error['file'],
				$error['line']
			);
			error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
