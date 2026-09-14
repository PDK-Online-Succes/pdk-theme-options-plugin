<?php
/**
 * AJAX-batchrunner voor hergeneratie van afbeeldingsmaten (FR-006).
 *
 * Zelfde patroon als IMGX' Batch_Processor
 * (modules/imgx/includes/class-batch-processor.php:18-195): state in één
 * optie, stapgrootte 3, tijdslimiet 15 s per stap, modi missing/all. Eigen
 * optie `pdk_image_sizes_batch` (B9/B10) — geen aanroep van IMGX-code (B1).
 *
 * Per bijlage wordt altijd de volledige maatset opnieuw opgebouwd via
 * wp_generate_attachment_metadata() en opgeslagen via
 * wp_update_attachment_metadata() (B13) — dat laatste laat IMGX' eigen
 * `wp_update_attachment_metadata`-filter vuren zodra IMGX actief is (FR-010),
 * zonder dat deze klasse daar iets van hoeft te weten.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Image_Sizes_Batch {

	/** Optie met de lopende (of laatst onderbroken) run. */
	const STATE_OPTION = 'pdk_image_sizes_batch';

	/** Nonce-actie voor alle AJAX-endpoints van deze batch. */
	const NONCE_ACTION = 'pdk_image_sizes_batch';

	/** Bijlagen per stap, conform IMGX' DEFAULT_BATCH_SIZE. */
	const DEFAULT_BATCH_SIZE = 3;

	/** Seconden waarna een stap teruggeeft, ook met bijlagen te gaan. */
	const STEP_TIME_LIMIT = 15;

	/** Toegestane modi (B13). */
	const MODES = [ 'missing', 'all' ];

	/** Mime-types die WordPress als afbeelding met submaten behandelt. */
	const IMAGE_MIME_TYPES = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];

	public function __construct( PDK_Loader $loader ) {
		$loader->add_action( 'wp_ajax_pdk_image_sizes_batch_start',  $this, 'ajax_start' );
		$loader->add_action( 'wp_ajax_pdk_image_sizes_batch_step',   $this, 'ajax_step' );
		$loader->add_action( 'wp_ajax_pdk_image_sizes_batch_cancel', $this, 'ajax_cancel' );
	}

	/** Controleert rechten en nonce, of beëindigt het verzoek (trust boundary). */
	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Je hebt geen rechten om dit te doen.', 'pdk-theme-options' ) ], 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	/** Huidige run, of een lege array als er niets loopt. */
	public static function current_state(): array {
		$state = get_option( self::STATE_OPTION, [] );

		return is_array( $state ) ? $state : [];
	}

	/**
	 * Start een nieuwe run. Weigert als er al één loopt (B9/AC-031) — de
	 * bestaande status blijft dan ongewijzigd.
	 */
	public function ajax_start(): void {
		$this->authorize();

		$existing = self::current_state();
		if ( ! empty( $existing['mode'] ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Er loopt al een hergeneratie. Wacht tot die klaar is of stop hem eerst.', 'pdk-theme-options' ) ],
				409
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorize() heeft de nonce al gecontroleerd.
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'missing';

		if ( ! in_array( $mode, self::MODES, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Onbekende hergeneratiemodus.', 'pdk-theme-options' ) ], 400 );
		}

		$state = [
			'mode'      => $mode,
			'offset'    => 0,
			'total'     => $this->count_attachments(),
			'processed' => 0,
			'generated' => 0,
			'skipped'   => 0,
			'errors'    => [],
			'started'   => time(),
		];

		update_option( self::STATE_OPTION, $state, false );

		wp_send_json_success( $this->public_state( $state, false ) );
	}

	/** Verwerkt één stap van de lopende run. */
	public function ajax_step(): void {
		$this->authorize();

		$state = self::current_state();

		if ( empty( $state['mode'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Er loopt geen hergeneratie.', 'pdk-theme-options' ) ], 409 );
		}

		$state = $this->run_step( $state );
		$done  = $state['offset'] >= $state['total'];

		if ( $done ) {
			delete_option( self::STATE_OPTION );
		} else {
			update_option( self::STATE_OPTION, $state, false );
		}

		wp_send_json_success( $this->public_state( $state, $done ) );
	}

	/** Stopt de lopende run. Al gegenereerde bestanden blijven staan (B2). */
	public function ajax_cancel(): void {
		$this->authorize();

		delete_option( self::STATE_OPTION );

		wp_send_json_success( [ 'cancelled' => true ] );
	}

	/**
	 * Verwerkt één slice van de wachtrij.
	 *
	 * @param array $state Huidige state.
	 * @return array Bijgewerkte state.
	 */
	public function run_step( array $state ): array {
		$ids = $this->query_ids( (int) $state['offset'], self::DEFAULT_BATCH_SIZE );

		if ( ! $ids ) {
			$state['offset'] = (int) $state['total'];
			return $state;
		}

		// wp_generate_attachment_metadata() staat in een bestand dat buiten
		// wp-admin niet automatisch geladen is — ook niet tijdens een AJAX-request.
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$started = microtime( true );

		foreach ( $ids as $id ) {
			++$state['offset'];
			++$state['processed'];

			if ( 'missing' === $state['mode'] && ! self::needs_generation( $id ) ) {
				++$state['skipped'];
			} else {
				$file = get_attached_file( $id );

				if ( ! $file || ! file_exists( $file ) ) {
					// AC-029: fout vastleggen, doorgaan met de volgende bijlage.
					$state['errors'][] = sprintf(
						/* translators: %d: bijlage-ID. */
						__( '#%d: bronbestand ontbreekt of is onleesbaar.', 'pdk-theme-options' ),
						$id
					);
				} else {
					$metadata = wp_generate_attachment_metadata( $id, $file );

					if ( ! is_array( $metadata ) || empty( $metadata ) ) {
						$state['errors'][] = sprintf(
							/* translators: %d: bijlage-ID. */
							__( '#%d: genereren van de maten is mislukt.', 'pdk-theme-options' ),
							$id
						);
					} else {
						// De normale WordPress-weg: dit laat wp_update_attachment_metadata
						// vuren, waar IMGX zelf aan hangt als die module actief is (AC-023).
						wp_update_attachment_metadata( $id, $metadata );
						++$state['generated'];
					}
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
	 * Of een bijlage minstens één actieve, toepasbare maat mist (AC-016/AC-026).
	 * "Toepasbaar" = het origineel is groot genoeg om die maat te leveren —
	 * anders zou WordPress hem toch overslaan.
	 */
	public static function needs_generation( int $attachment_id ): bool {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $metadata ) || empty( $metadata['sizes'] ) ) {
			return true;
		}

		$orig_w = (int) ( $metadata['width'] ?? 0 );
		$orig_h = (int) ( $metadata['height'] ?? 0 );

		foreach ( PDK_Image_Sizes::registered() as $key => $def ) {
			if ( ! PDK_Image_Sizes::is_enabled( $key ) || isset( $metadata['sizes'][ $key ] ) ) {
				continue;
			}

			$width  = (int) ( $def['width'] ?? 0 );
			$height = (int) ( $def['height'] ?? 0 );

			// Beide afmetingen groter dan het origineel: WordPress zou deze maat
			// toch nooit maken, dus telt hij niet mee als "ontbrekend".
			$te_groot = $orig_w && $width > $orig_w && ( ! $height || ( $orig_h && $height > $orig_h ) );

			if ( ! $te_groot ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Haalt een slice bijlage-ID's op.
	 *
	 * @return int[]
	 */
	private function query_ids( int $offset, int $size ): array {
		$query = new WP_Query(
			[
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => self::IMAGE_MIME_TYPES,
				'fields'                 => 'ids',
				'posts_per_page'         => $size,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'cache_results'          => false,
			]
		);

		return array_map( 'intval', $query->posts );
	}

	/** Aantal bijlagen dat in aanmerking komt voor hergeneratie. */
	public function count_attachments(): int {
		$counts = wp_count_attachments();
		$total  = 0;

		foreach ( self::IMAGE_MIME_TYPES as $mime ) {
			if ( isset( $counts->$mime ) ) {
				$total += (int) $counts->$mime;
			}
		}

		return $total;
	}

	/**
	 * Vormt de state om voor de browser — ook bruikbaar buiten deze klasse om
	 * (bijvoorbeeld om bij het laden van de tab de opgeslagen voortgang van een
	 * onderbroken run te tonen, AC-013).
	 */
	public static function shape_for_browser( array $state ): array {
		$done = ( (int) ( $state['offset'] ?? 0 ) ) >= ( (int) ( $state['total'] ?? 0 ) );

		return self::to_public_state( $state, $done );
	}

	/** Vormt de state om voor de browser. */
	private function public_state( array $state, bool $done ): array {
		return self::to_public_state( $state, $done );
	}

	/** @see public_state() */
	private static function to_public_state( array $state, bool $done ): array {
		$total   = max( 0, (int) $state['total'] );
		$offset  = min( $total, max( 0, (int) $state['offset'] ) );
		$percent = $total > 0 ? (int) floor( ( $offset / $total ) * 100 ) : 100;

		return [
			'mode'      => (string) $state['mode'],
			'total'     => $total,
			'processed' => (int) $state['processed'],
			'generated' => (int) $state['generated'],
			'skipped'   => (int) $state['skipped'],
			'errors'    => array_values( array_map( 'strval', (array) $state['errors'] ) ),
			'percent'   => $done ? 100 : $percent,
			'done'      => $done,
		];
	}
}
