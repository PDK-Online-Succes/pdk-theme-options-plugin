<?php
/**
 * Per-attachment orchestration and media lifecycle integration.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * Turns one attachment into a complete set of sidecar variants, and keeps that set in
 * sync with the WordPress media lifecycle.
 */
class Generator {

	/**
	 * Cron hook used for asynchronous generation.
	 */
	const CRON_HOOK = 'imgx_generate_variants';

	/**
	 * Prefix of the per-attachment lock option.
	 */
	const LOCK_PREFIX = 'imgx_lock_';

	/**
	 * Seconds after which a lock is considered abandoned.
	 */
	const LOCK_TTL = 300;

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
	 * Converter.
	 *
	 * @var Converter
	 */
	private $converter;

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
	 * @param Converter    $converter    Converter.
	 * @param Logger       $logger       Logger.
	 */
	public function __construct( Settings $settings, Capabilities $capabilities, Converter $converter, Logger $logger ) {
		$this->settings     = $settings;
		$this->capabilities = $capabilities;
		$this->converter    = $converter;
		$this->logger       = $logger;
	}

	/**
	 * Registers the lifecycle hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'on_generate_metadata' ), 20, 2 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'on_update_metadata' ), 20, 2 );
		add_action( 'delete_attachment', array( $this, 'on_delete_attachment' ), 10, 1 );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled' ), 10, 1 );
	}

	/**
	 * Source mime types IMGX converts.
	 *
	 * GIF is excluded because animation cannot be reproduced reliably by either editor,
	 * and SVG is vector data with nothing to convert.
	 *
	 * @return string[]
	 */
	public static function source_mime_types() {
		/**
		 * Filters which source mime types get sidecar variants.
		 *
		 * @param string[] $mime_types Mime types.
		 */
		$types = apply_filters( 'imgx_source_mime_types', array( 'image/jpeg', 'image/png' ) );

		return array_values( array_filter( array_map( 'strval', (array) $types ) ) );
	}

	/**
	 * Removes every scheduled generation event. Used on deactivation.
	 *
	 * @return void
	 */
	public static function clear_scheduled_events() {
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Reacts to a finished upload.
	 *
	 * The metadata is returned unmodified; this filter is used purely as an event.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public function on_generate_metadata( $metadata, $attachment_id ) {
		if ( ! $this->settings->get( 'generate_on_upload' ) ) {
			return $metadata;
		}

		if ( ! $this->is_supported_attachment( $attachment_id ) ) {
			return $metadata;
		}

		if ( 'sync' === $this->settings->get( 'upload_mode' ) ) {
			// The metadata is not saved yet at this point, so hand it over directly.
			$this->generate( $attachment_id, array( 'metadata' => $metadata ) );
		} else {
			$this->schedule( $attachment_id );
		}

		return $metadata;
	}

	/**
	 * Reacts to metadata changes, for example after regenerating thumbnails.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public function on_update_metadata( $metadata, $attachment_id ) {
		if ( ! is_array( $metadata ) || ! $this->is_supported_attachment( $attachment_id ) ) {
			return $metadata;
		}

		$variants = Variants::get( $attachment_id );

		if ( empty( $variants['sizes'] ) && empty( $variants['skipped'] ) ) {
			// Nothing generated yet; the upload hook or a batch run owns this attachment.
			return $metadata;
		}

		if ( ! $this->is_intermediate_metadata_save() ) {
			$this->prune_stale_sizes( $attachment_id, $metadata, $variants );
		}

		if ( $this->settings->get( 'generate_on_upload' ) && $this->needs_generation( $attachment_id, $metadata ) ) {
			$this->schedule( $attachment_id );
		}

		return $metadata;
	}

	/**
	 * Deletes generated sidecars when an attachment is permanently deleted.
	 *
	 * Fires before WordPress removes the originals, and only ever touches files this
	 * plugin recorded itself.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function on_delete_attachment( $attachment_id ) {
		$this->delete_variants( $attachment_id );
	}

	/**
	 * Cron callback.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function run_scheduled( $attachment_id ) {
		$this->generate( (int) $attachment_id );
	}

	/**
	 * Schedules asynchronous generation for an attachment.
	 *
	 * WordPress de-duplicates identical single events within a ten minute window, so a
	 * burst of metadata updates results in one run.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function schedule( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$args          = array( $attachment_id );

		if ( wp_next_scheduled( self::CRON_HOOK, $args ) ) {
			return;
		}

		wp_schedule_single_event( time() + 15, self::CRON_HOOK, $args );
	}

	/**
	 * Whether an attachment is a local image IMGX can convert.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function is_supported_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id < 1 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		return in_array( get_post_mime_type( $attachment_id ), self::source_mime_types(), true );
	}

	/**
	 * Formats to produce for a specific attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $context       Either generate or render.
	 * @return string[]
	 */
	public function formats_for( $attachment_id, $context = 'generate' ) {
		$formats = $this->settings->enabled_formats( $this->capabilities );

		/**
		 * Filters the variant formats for a single attachment.
		 *
		 * @param string[] $formats       Format keys, ordered by preference.
		 * @param int      $attachment_id Attachment ID.
		 * @param string   $context       Either generate or render.
		 */
		$formats = apply_filters( 'imgx_formats', $formats, (int) $attachment_id, $context );

		return array_values(
			array_intersect( array( 'avif', 'webp' ), array_map( 'strval', (array) $formats ) )
		);
	}

	/**
	 * Whether anything is still missing for this attachment.
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param array|null $metadata      Optional metadata override.
	 * @return bool
	 */
	public function needs_generation( $attachment_id, $metadata = null ) {
		if ( ! $this->is_supported_attachment( $attachment_id ) ) {
			return false;
		}

		$formats = $this->formats_for( $attachment_id );

		if ( ! $formats ) {
			return false;
		}

		$targets = $this->build_targets( $attachment_id, $metadata );

		if ( ! $targets ) {
			return false;
		}

		$variants = Variants::get( $attachment_id );

		$stale = $this->skipped_is_stale( $variants );

		foreach ( $targets as $size => $target ) {
			foreach ( $formats as $format ) {
				if ( isset( $variants['sizes'][ $size ][ $format ] ) ) {
					continue;
				}

				if ( isset( $variants['skipped'][ $size ][ $format ] )
					&& ! $this->skip_is_retryable( $variants['skipped'][ $size ][ $format ], $stale ) ) {
					continue;
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Of de overgeslagen varianten onder andere codeerinstellingen zijn vastgelegd.
	 *
	 * @param array $variants Registratie.
	 * @return bool
	 */
	private function skipped_is_stale( array $variants ) {
		return ( isset( $variants['signature'] ) ? $variants['signature'] : '' )
			!== $this->settings->encoding_signature();
	}

	/**
	 * Of een overgeslagen variant het opnieuw proberen waard is.
	 *
	 * Alleen larger-than-source hangt van de kwaliteit af: bij een lagere
	 * kwaliteit kan hetzelfde bestand nu wél kleiner uitvallen dan het origineel.
	 * Een door een filter geweigerde variant staat los van de instellingen en
	 * blijft dus overgeslagen.
	 *
	 * @param string $reason Reden zoals vastgelegd in de registratie.
	 * @param bool   $stale  Of de vingerafdruk afwijkt.
	 * @return bool
	 */
	private function skip_is_retryable( $reason, $stale ) {
		return $stale && 'larger-than-source' === $reason;
	}

	/**
	 * Generates the missing (or all) variants for one attachment.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $args          {
	 *     Optional arguments.
	 *
	 *     @type bool  $force    Regenerate variants that already exist. Default false.
	 *     @type array $metadata Metadata override, for use before it has been saved.
	 * }
	 * @return array {
	 *     @type int   $generated Number of files written.
	 *     @type int   $skipped   Number of variants deliberately not written.
	 *     @type int   $existing  Number of variants that were already present.
	 *     @type array $errors    Human readable error strings.
	 * }
	 */
	public function generate( $attachment_id, array $args = array() ) {
		$attachment_id = (int) $attachment_id;

		$args = wp_parse_args(
			$args,
			array(
				'force'    => false,
				'metadata' => null,
			)
		);

		$result = array(
			'generated' => 0,
			'skipped'   => 0,
			'existing'  => 0,
			'errors'    => array(),
		);

		if ( ! $this->is_supported_attachment( $attachment_id ) ) {
			$result['errors'][] = __( 'Deze bijlage is geen lokale afbeelding die om te zetten is.', 'pdk-theme-options' );

			return $result;
		}

		$formats = $this->formats_for( $attachment_id );

		if ( ! $formats ) {
			$result['errors'][] = __( 'Geen enkel afbeeldingsformaat is tegelijk ingeschakeld en ondersteund door deze server.', 'pdk-theme-options' );

			return $result;
		}

		if ( ! $this->acquire_lock( $attachment_id ) ) {
			$result['errors'][] = __( 'Een ander proces genereert al varianten voor deze afbeelding.', 'pdk-theme-options' );

			return $result;
		}

		try {
			$targets  = $this->build_targets( $attachment_id, $args['metadata'] );
			$variants = Variants::get( $attachment_id );

			if ( $args['force'] ) {
				$this->delete_recorded_files( $attachment_id, $variants );
				$variants['sizes']   = array();
				$variants['skipped'] = array();
			}

			// Several registered sizes routinely share one file; convert each file once.
			$by_basename = array();

			foreach ( $targets as $size => $path ) {
				$by_basename[ wp_basename( $path ) ][] = $size;
			}

			foreach ( $targets as $size => $path ) {
				$basename = wp_basename( $path );
				$siblings = $by_basename[ $basename ];

				if ( $siblings[0] !== $size ) {
					// Handled by the first size key that referenced this file.
					continue;
				}

				foreach ( $formats as $format ) {
					$outcome = $this->generate_one( $attachment_id, $size, $path, $format, $variants );

					switch ( $outcome['status'] ) {
						case 'generated':
							++$result['generated'];
							break;
						case 'existing':
							++$result['existing'];
							break;
						case 'skipped':
							++$result['skipped'];
							break;
						default:
							$result['errors'][] = $outcome['message'];
							break;
					}

					// Mirror the outcome onto every size key that shares this file.
					foreach ( $siblings as $sibling ) {
						if ( isset( $variants['sizes'][ $size ][ $format ] ) ) {
							$variants['sizes'][ $sibling ][ $format ] = $variants['sizes'][ $size ][ $format ];
						} elseif ( isset( $variants['skipped'][ $size ][ $format ] ) ) {
							$variants['skipped'][ $sibling ][ $format ] = $variants['skipped'][ $size ][ $format ];
						}
					}
				}
			}

			// Pas hier, nadat de lus de oude vingerafdruk heeft kunnen vergelijken.
			$variants['signature'] = $this->settings->encoding_signature();

			Variants::save( $attachment_id, $variants );
		} catch ( \Throwable $e ) {
			$result['errors'][] = $e->getMessage();
			$this->logger->error( 'Unexpected failure while generating variants.', array( 'attachment' => $attachment_id, 'error' => $e->getMessage() ) );
		} finally {
			$this->release_lock( $attachment_id );
		}

		/**
		 * Fires after an attachment has been processed.
		 *
		 * @param int   $attachment_id Attachment ID.
		 * @param array $result        Counters and errors.
		 */
		do_action( 'imgx_after_generate_attachment', $attachment_id, $result );

		return $result;
	}

	/**
	 * Generates a single variant and records it in the registry.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size          Size key.
	 * @param string $path          Absolute source path.
	 * @param string $format        Format key.
	 * @param array  $variants      Registry, passed by reference.
	 * @return array{status:string,message:string}
	 */
	private function generate_one( $attachment_id, $size, $path, $format, array &$variants ) {
		if ( isset( $variants['sizes'][ $size ][ $format ] ) ) {
			$existing = dirname( $path ) . '/' . $variants['sizes'][ $size ][ $format ];

			if ( file_exists( $existing ) ) {
				return array(
					'status'  => 'existing',
					'message' => '',
				);
			}

			unset( $variants['sizes'][ $size ][ $format ] );
		}

		if ( isset( $variants['skipped'][ $size ][ $format ] ) ) {
			if ( ! $this->skip_is_retryable(
				$variants['skipped'][ $size ][ $format ],
				$this->skipped_is_stale( $variants )
			) ) {
				return array(
					'status'  => 'skipped',
					'message' => '',
				);
			}

			// Kwaliteit is sindsdien gewijzigd: opnieuw coderen en kijken of het
			// resultaat nu wél kleiner is dan het origineel.
			unset( $variants['skipped'][ $size ][ $format ] );
		}

		/**
		 * Filters whether to generate one specific variant.
		 *
		 * @param bool   $should        Whether to generate. Default true.
		 * @param int    $attachment_id Attachment ID.
		 * @param string $size          Size key.
		 * @param string $format        Format key.
		 */
		if ( ! apply_filters( 'imgx_should_generate_image', true, $attachment_id, $size, $format ) ) {
			$variants['skipped'][ $size ][ $format ] = 'filtered';

			return array(
				'status'  => 'skipped',
				'message' => '',
			);
		}

		$dest = Files::sidecar_path( $path, $format, Variants::basenames( $attachment_id ), $attachment_id );

		$written = $this->converter->convert(
			$path,
			$dest,
			$format,
			array(
				'attachment_id' => $attachment_id,
				'size'          => $size,
			)
		);

		if ( is_wp_error( $written ) ) {
			if ( 'imgx_larger_than_source' === $written->get_error_code() ) {
				$variants['skipped'][ $size ][ $format ] = 'larger-than-source';

				return array(
					'status'  => 'skipped',
					'message' => '',
				);
			}

			/**
			 * Fires when a variant could not be generated.
			 *
			 * @param int       $attachment_id Attachment ID.
			 * @param string    $size          Size key.
			 * @param string    $format        Format key.
			 * @param \WP_Error $error         The failure.
			 */
			do_action( 'imgx_generation_error', $attachment_id, $size, $format, $written );

			$message = sprintf(
				/* translators: 1: image size key, 2: format name, 3: error message. */
				__( '%1$s / %2$s: %3$s', 'pdk-theme-options' ),
				$size,
				strtoupper( $format ),
				$written->get_error_message()
			);

			$this->logger->error(
				$message,
				array(
					'attachment' => (string) $attachment_id,
					'code'       => $written->get_error_code(),
				)
			);

			return array(
				'status'  => 'error',
				'message' => $message,
			);
		}

		$variants['sizes'][ $size ][ $format ] = wp_basename( $written );

		unset( $variants['skipped'][ $size ][ $format ] );

		/**
		 * Fires after a variant file was written and verified.
		 *
		 * @param int    $attachment_id Attachment ID.
		 * @param string $size          Size key.
		 * @param string $format        Format key.
		 * @param string $path          Absolute path of the new file.
		 */
		do_action( 'imgx_after_generate_variant', $attachment_id, $size, $format, $written );

		return array(
			'status'  => 'generated',
			'message' => '',
		);
	}

	/**
	 * Builds the "size key => absolute source path" work list from attachment metadata.
	 *
	 * WordPress metadata is the source of truth; nothing is assumed about which sizes
	 * exist, and every entry is checked against the filesystem.
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param array|null $metadata      Optional metadata override.
	 * @return array<string, string>
	 */
	public function build_targets( $attachment_id, $metadata = null ) {
		$attachment_id = (int) $attachment_id;
		$file          = get_attached_file( $attachment_id );

		if ( ! $file || ! Files::is_inside_uploads( $file ) ) {
			return array();
		}

		$targets = array();

		if ( file_exists( $file ) ) {
			$targets['full'] = Files::normalize( $file );
		}

		if ( null === $metadata ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
		}

		if ( ! is_array( $metadata ) || empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return $targets;
		}

		$dir = Files::normalize( dirname( $file ) );

		foreach ( $metadata['sizes'] as $size => $info ) {
			if ( empty( $info['file'] ) || ! is_string( $info['file'] ) ) {
				continue;
			}

			$basename = wp_basename( $info['file'] );

			if ( '' === $basename ) {
				continue;
			}

			$path = $dir . '/' . $basename;

			if ( file_exists( $path ) ) {
				$targets[ (string) $size ] = $path;
			}
		}

		return $targets;
	}

	/**
	 * Deletes every sidecar recorded for an attachment and removes the registry.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Deleted absolute paths.
	 */
	public function delete_variants( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$variants      = Variants::get( $attachment_id );
		$deleted       = $this->delete_recorded_files( $attachment_id, $variants );

		Variants::delete( $attachment_id );

		/**
		 * Fires after an attachment's sidecars were removed.
		 *
		 * @param int   $attachment_id Attachment ID.
		 * @param array $deleted       Deleted absolute paths.
		 */
		do_action( 'imgx_after_delete_variants', $attachment_id, $deleted );

		return $deleted;
	}

	/**
	 * Removes the files listed in a registry. Never touches originals.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $variants      Registry.
	 * @return array Deleted absolute paths.
	 */
	private function delete_recorded_files( $attachment_id, array $variants ) {
		$file = get_attached_file( (int) $attachment_id );

		if ( ! $file ) {
			return array();
		}

		$dir     = Files::normalize( dirname( $file ) );
		$deleted = array();
		$seen    = array();

		foreach ( $variants['sizes'] as $formats ) {
			foreach ( $formats as $basename ) {
				if ( isset( $seen[ $basename ] ) ) {
					continue;
				}

				$seen[ $basename ] = true;
				$path              = $dir . '/' . $basename;

				if ( Files::delete_variant( $path ) ) {
					$deleted[] = $path;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Whether the current `wp_update_attachment_metadata` call is one of the progressive
	 * saves `wp_create_image_subsizes()` makes after every submaat, rather than the final
	 * save with the complete set.
	 *
	 * WordPress has saved metadata incrementally since 5.3 so a time-out cannot wipe
	 * progress; `$metadata['sizes']` is therefore genuinely incomplete during those calls,
	 * not because the attachment has no more sizes. The call stack is the only reliable
	 * signal: `wp_create_image_subsizes()` is still on it while those interim saves run,
	 * and is gone by the time the final save (or any other caller) fires this filter.
	 *
	 * @return bool
	 */
	private function is_intermediate_metadata_save() {
		if ( ! function_exists( 'debug_backtrace' ) ) {
			return false;
		}

		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

		foreach ( $trace as $frame ) {
			if ( isset( $frame['function'] ) && 'wp_create_image_subsizes' === $frame['function'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Drops registry entries whose source size no longer exists.
	 *
	 * The generated file is removed only when its source is gone too, so a temporarily
	 * unregistered image size does not cause an expensive regeneration cycle.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $metadata      New attachment metadata.
	 * @param array $variants      Registry, passed by reference.
	 * @return void
	 */
	private function prune_stale_sizes( $attachment_id, array $metadata, array &$variants ) {
		$targets = $this->build_targets( $attachment_id, $metadata );

		// Geen enkel doel betekent dat het bronbestand niet te bereiken is —
		// een offload-plugin, een verplaatste uploadsmap, een realpath() die
		// faalt. Dat is iets anders dan "elke maat is verouderd". Zonder deze
		// grens zou de hele registratie hieronder gewist worden en stopt de
		// <picture>-uitvoer terwijl de bestanden gewoon op schijf staan.
		if ( ! $targets ) {
			return;
		}

		$changed = false;

		$file = get_attached_file( (int) $attachment_id );
		$dir  = $file ? Files::normalize( dirname( $file ) ) : '';

		foreach ( array( 'sizes', 'skipped' ) as $bucket ) {
			foreach ( array_keys( $variants[ $bucket ] ) as $size ) {
				if ( isset( $targets[ $size ] ) ) {
					continue;
				}

				if ( 'sizes' === $bucket && '' !== $dir ) {
					foreach ( $variants[ $bucket ][ $size ] as $basename ) {
						// Only remove the sidecar when its source really disappeared. This is
						// a second, independent guard next to is_intermediate_metadata_save():
						// that one can fail open if WordPress ever renames its internal
						// function; this one fails closed regardless, because it checks the
						// filesystem instead of the call stack.
						if ( ! $this->basename_still_referenced( $variants, $size, $basename )
							&& ! $this->sidecar_source_still_exists( $dir, $basename ) ) {
							Files::delete_variant( $dir . '/' . $basename );
						}
					}
				}

				unset( $variants[ $bucket ][ $size ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			Variants::save( $attachment_id, $variants );
		}
	}

	/**
	 * Whether a variant basename is still used by another size key.
	 *
	 * @param array  $variants Registry.
	 * @param string $exclude  Size key being removed.
	 * @param string $basename Variant basename.
	 * @return bool
	 */
	private function basename_still_referenced( array $variants, $exclude, $basename ) {
		foreach ( $variants['sizes'] as $size => $formats ) {
			if ( (string) $size === (string) $exclude ) {
				continue;
			}

			if ( in_array( $basename, $formats, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the source image a sidecar was generated from is still on disk.
	 *
	 * `Files::sidecar_path()` names a sidecar in one of three ways, all derived from the
	 * source's own filename: `<stem>.<format>`, `<basename>.<format>` (collision fallback),
	 * or `<stem>-<hash>.<format>` (double collision). This reverses that naming to look for
	 * the source candidate on disk, independently of whatever the metadata currently says.
	 * A sidecar is never deleted while its source still exists, even if the caller believes
	 * the size is stale.
	 *
	 * @param string $dir      Normalized directory the sidecar lives in.
	 * @param string $basename Sidecar basename.
	 * @return bool
	 */
	private function sidecar_source_still_exists( $dir, $basename ) {
		$extension = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, Files::VARIANT_EXTENSIONS, true ) ) {
			return false;
		}

		$without_format = substr( $basename, 0, - ( strlen( $extension ) + 1 ) );

		// Collision fallback: the sidecar was named after the full original basename
		// (e.g. photo.png.webp), so the candidate below already carries its own extension.
		if ( file_exists( $dir . '/' . $without_format ) ) {
			return true;
		}

		// Double collision fallback: strip the trailing "-<hash>" sidecar_path() adds.
		$stem = preg_replace( '/-[0-9a-f]{6}$/', '', $without_format );

		foreach ( self::source_extensions() as $source_extension ) {
			if ( file_exists( $dir . '/' . $stem . '.' . $source_extension ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * File extensions IMGX ever converts from.
	 *
	 * @return string[]
	 */
	private static function source_extensions() {
		return array( 'jpg', 'jpeg', 'png' );
	}

	/**
	 * Takes the per-attachment lock.
	 *
	 * add_option() inserts into a column with a unique index, so exactly one concurrent
	 * caller wins. Locks older than LOCK_TTL are treated as abandoned.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function acquire_lock( $attachment_id ) {
		$key = self::LOCK_PREFIX . (int) $attachment_id;

		if ( add_option( $key, time(), '', 'no' ) ) {
			return true;
		}

		$held = (int) get_option( $key, 0 );

		if ( $held > 0 && ( time() - $held ) < self::LOCK_TTL ) {
			return false;
		}

		// Stale lock from a fatal or a killed worker; take it over.
		update_option( $key, time(), false );

		return true;
	}

	/**
	 * Releases the per-attachment lock.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function release_lock( $attachment_id ) {
		delete_option( self::LOCK_PREFIX . (int) $attachment_id );
	}
}
