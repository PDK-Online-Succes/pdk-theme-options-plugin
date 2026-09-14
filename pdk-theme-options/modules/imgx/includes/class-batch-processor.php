<?php
/**
 * AJAX batch runner for the existing Media Library.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * Processes the library in small, resumable steps.
 *
 * State lives in a single option so a closed browser tab does not lose progress, and a
 * step stops early once it has been running long enough to risk max_execution_time.
 */
class Batch_Processor {

	/**
	 * Option holding the current run.
	 */
	const STATE_OPTION = 'imgx_batch_state';

	/**
	 * Nonce action for every AJAX endpoint.
	 */
	const NONCE_ACTION = 'imgx_batch';

	/**
	 * Attachments per step, before filtering.
	 */
	const DEFAULT_BATCH_SIZE = 3;

	/**
	 * Seconds after which a step returns, even with attachments left in its slice.
	 */
	const STEP_TIME_LIMIT = 15;

	/**
	 * Supported run modes.
	 */
	const MODES = array( 'missing', 'all', 'delete' );

	/**
	 * Generator.
	 *
	 * @var Generator
	 */
	private $generator;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Generator $generator Generator.
	 * @param Settings  $settings  Settings.
	 */
	public function __construct( Generator $generator, Settings $settings ) {
		$this->generator = $generator;
		$this->settings  = $settings;
	}

	/**
	 * Registers the AJAX endpoints.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_imgx_batch_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_imgx_batch_step', array( $this, 'ajax_step' ) );
		add_action( 'wp_ajax_imgx_batch_cancel', array( $this, 'ajax_cancel' ) );
	}

	/**
	 * Capability required for every batch action.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability required to run IMGX batch jobs.
		 *
		 * @param string $capability Capability name.
		 */
		return (string) apply_filters( 'imgx_batch_capability', 'manage_options' );
	}

	/**
	 * Verifies nonce and capability, or ends the request.
	 *
	 * @return void
	 */
	private function authorize() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Je hebt geen rechten om dit te doen.', 'pdk-theme-options' ) ), 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	/**
	 * Starts a new run.
	 *
	 * @return void
	 */
	public function ajax_start() {
		$this->authorize();

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'missing';

		if ( ! in_array( $mode, self::MODES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Onbekende batchmodus.', 'pdk-theme-options' ) ), 400 );
		}

		$state = array(
			'mode'      => $mode,
			'offset'    => 0,
			'total'     => $this->count_attachments(),
			'processed' => 0,
			'generated' => 0,
			'skipped'   => 0,
			'deleted'   => 0,
			'errors'    => array(),
			'started'   => time(),
		);

		update_option( self::STATE_OPTION, $state, false );

		wp_send_json_success( $this->public_state( $state, false ) );
	}

	/**
	 * Processes one step.
	 *
	 * @return void
	 */
	public function ajax_step() {
		$this->authorize();

		$state = get_option( self::STATE_OPTION, array() );

		if ( ! is_array( $state ) || empty( $state['mode'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Er loopt geen batch.', 'pdk-theme-options' ) ), 409 );
		}

		// A state written by an older version may be missing keys.
		$state = wp_parse_args(
			$state,
			array(
				'mode'      => 'missing',
				'offset'    => 0,
				'total'     => 0,
				'processed' => 0,
				'generated' => 0,
				'skipped'   => 0,
				'deleted'   => 0,
				'errors'    => array(),
				'started'   => time(),
			)
		);

		$state = $this->run_step( $state );

		$done = $state['offset'] >= $state['total'];

		if ( $done ) {
			delete_option( self::STATE_OPTION );
			delete_transient( Admin::STATS_TRANSIENT );
		} else {
			update_option( self::STATE_OPTION, $state, false );
		}

		wp_send_json_success( $this->public_state( $state, $done ) );
	}

	/**
	 * Cancels the current run.
	 *
	 * @return void
	 */
	public function ajax_cancel() {
		$this->authorize();

		delete_option( self::STATE_OPTION );
		delete_transient( Admin::STATS_TRANSIENT );

		wp_send_json_success( array( 'cancelled' => true ) );
	}

	/**
	 * Runs one slice of the queue.
	 *
	 * @param array $state Current state.
	 * @return array Updated state.
	 */
	public function run_step( array $state ) {
		/**
		 * Filters how many attachments are handled per batch step.
		 *
		 * @param int $size Attachments per step.
		 */
		$size = (int) apply_filters( 'imgx_batch_size', self::DEFAULT_BATCH_SIZE );
		$size = max( 1, min( 50, $size ) );

		$ids = $this->query_ids( (int) $state['offset'], $size );

		if ( ! $ids ) {
			$state['offset'] = (int) $state['total'];

			return $state;
		}

		$started = microtime( true );

		foreach ( $ids as $id ) {
			++$state['offset'];
			++$state['processed'];

			if ( 'delete' === $state['mode'] ) {
				$deleted           = $this->generator->delete_variants( $id );
				$state['deleted'] += count( $deleted );
			} else {
				if ( 'missing' === $state['mode'] && ! $this->generator->needs_generation( $id ) ) {
					++$state['skipped'];
					continue;
				}

				$result = $this->generator->generate( $id, array( 'force' => 'all' === $state['mode'] ) );

				$state['generated'] += (int) $result['generated'];
				$state['skipped']   += (int) $result['skipped'];

				foreach ( $result['errors'] as $error ) {
					$state['errors'][] = sprintf(
						/* translators: 1: attachment ID, 2: error message. */
						__( '#%1$d: %2$s', 'pdk-theme-options' ),
						$id,
						$error
					);
				}
			}

			if ( ( microtime( true ) - $started ) > self::STEP_TIME_LIMIT ) {
				break;
			}
		}

		$state['errors'] = array_slice( $state['errors'], -50 );

		return $state;
	}

	/**
	 * Fetches a slice of convertible attachment IDs.
	 *
	 * @param int $offset Offset.
	 * @param int $size   Slice size.
	 * @return int[]
	 */
	private function query_ids( $offset, $size ) {
		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => Generator::source_mime_types(),
				'fields'                 => 'ids',
				'posts_per_page'         => $size,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'cache_results'          => false,
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Counts convertible attachments.
	 *
	 * @return int
	 */
	public function count_attachments() {
		$counts = wp_count_attachments();
		$total  = 0;

		foreach ( Generator::source_mime_types() as $mime ) {
			if ( isset( $counts->$mime ) ) {
				$total += (int) $counts->$mime;
			}
		}

		return $total;
	}

	/**
	 * Current run, or an empty array.
	 *
	 * @return array
	 */
	public function current_state() {
		$state = get_option( self::STATE_OPTION, array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Shapes the state for the browser.
	 *
	 * @param array $state Internal state.
	 * @param bool  $done  Whether the run finished.
	 * @return array
	 */
	private function public_state( array $state, $done ) {
		$total   = max( 0, (int) $state['total'] );
		$offset  = min( $total, max( 0, (int) $state['offset'] ) );
		$percent = $total > 0 ? (int) floor( ( $offset / $total ) * 100 ) : 100;

		return array(
			'mode'      => (string) $state['mode'],
			'total'     => $total,
			'processed' => (int) $state['processed'],
			'generated' => (int) $state['generated'],
			'skipped'   => (int) $state['skipped'],
			'deleted'   => (int) $state['deleted'],
			'errors'    => array_values( array_map( 'strval', (array) $state['errors'] ) ),
			'percent'   => $done ? 100 : $percent,
			'done'      => (bool) $done,
		);
	}
}
