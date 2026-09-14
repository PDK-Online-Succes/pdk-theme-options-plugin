<?php
/**
 * WP-CLI commands.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * The practical way to process a large Media Library: no HTTP timeout, no browser tab.
 */
class CLI {

	/**
	 * Generator.
	 *
	 * @var Generator
	 */
	private $generator;

	/**
	 * Capability detection.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Registers the command with WP-CLI.
	 *
	 * @param Generator    $generator    Generator.
	 * @param Capabilities $capabilities Capability detection.
	 * @param Settings     $settings     Settings.
	 * @return void
	 */
	public static function register( Generator $generator, Capabilities $capabilities, Settings $settings ) {
		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'imgx', new self( $generator, $capabilities, $settings ) );
	}

	/**
	 * Constructor.
	 *
	 * @param Generator    $generator    Generator.
	 * @param Capabilities $capabilities Capability detection.
	 * @param Settings     $settings     Settings.
	 */
	public function __construct( Generator $generator, Capabilities $capabilities, Settings $settings ) {
		$this->generator    = $generator;
		$this->capabilities = $capabilities;
		$this->settings     = $settings;
	}

	/**
	 * Toont wat deze server kan coderen en hoe ver de bibliotheek verwerkt is.
	 *
	 * ## EXAMPLES
	 *
	 *     wp imgx status
	 *
	 * @return void
	 */
	public function status() {
		$report = $this->capabilities->refresh();

		\WP_CLI::line( 'Afbeeldingsbewerker: ' . $this->capabilities->editor_name() );
		\WP_CLI::line( 'AVIF:                ' . ( ! empty( $report['avif'] ) ? 'ondersteund' : 'niet beschikbaar - ' . $this->capabilities->reason( 'avif' ) ) );
		\WP_CLI::line( 'WebP:                ' . ( ! empty( $report['webp'] ) ? 'ondersteund' : 'niet beschikbaar - ' . $this->capabilities->reason( 'webp' ) ) );
		\WP_CLI::line( 'Modus:               ' . (string) $this->settings->get( 'mode' ) );
		\WP_CLI::line( 'Ingeschakeld:        ' . ( implode( ', ', $this->settings->enabled_formats( $this->capabilities ) ) ?: 'geen' ) );
		\WP_CLI::line( 'Geregistreerd:       ' . Variants::count_registered() . ' bijlagen, ' . Variants::count_files() . ' variantbestanden' );
	}

	/**
	 * Genereert NextGen-varianten.
	 *
	 * ## OPTIONS
	 *
	 * [--ids=<ids>]
	 * : Bijlage-ID's, komma-gescheiden. Standaard de hele bibliotheek.
	 *
	 * [--force]
	 * : Bestaande varianten verwijderen en opnieuw opbouwen.
	 *
	 * [--batch=<n>]
	 * : Aantal bijlagen per query. Standaard 50.
	 *
	 * ## EXAMPLES
	 *
	 *     wp imgx generate
	 *     wp imgx generate --force
	 *     wp imgx generate --ids=12,34
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function generate( $args, $assoc_args ) {
		unset( $args );

		$force = ! empty( $assoc_args['force'] );
		$batch = isset( $assoc_args['batch'] ) ? max( 1, (int) $assoc_args['batch'] ) : 50;

		$ids = $this->resolve_ids( $assoc_args );

		if ( ! $ids ) {
			\WP_CLI::success( 'Niets te doen.' );

			return;
		}

		if ( ! $this->settings->enabled_formats( $this->capabilities ) ) {
			\WP_CLI::error( 'Geen enkel afbeeldingsformaat is tegelijk ingeschakeld en ondersteund door deze server. Draai: wp imgx status' );
		}

		$progress  = \WP_CLI\Utils\make_progress_bar( 'Genereren', count( $ids ) );
		$generated = 0;
		$skipped   = 0;
		$failed    = 0;

		foreach ( array_chunk( $ids, $batch ) as $chunk ) {
			foreach ( $chunk as $id ) {
				if ( ! $force && ! $this->generator->needs_generation( $id ) ) {
					++$skipped;
					$progress->tick();
					continue;
				}

				$result = $this->generator->generate( $id, array( 'force' => $force ) );

				$generated += (int) $result['generated'];
				$skipped   += (int) $result['skipped'];

				foreach ( $result['errors'] as $error ) {
					++$failed;
					\WP_CLI::warning( '#' . $id . ': ' . $error );
				}

				$progress->tick();
			}

			// Keep long runs from growing unbounded.
			$this->flush_caches();
		}

		$progress->finish();

		delete_transient( Admin::STATS_TRANSIENT );

		\WP_CLI::success( sprintf( '%d bestanden gegenereerd, %d overgeslagen, %d fouten.', $generated, $skipped, $failed ) );
	}

	/**
	 * Verwijdert elk bestand dat IMGX heeft gegenereerd. Originelen blijven ongemoeid.
	 *
	 * ## OPTIONS
	 *
	 * [--ids=<ids>]
	 * : Bijlage-ID's, komma-gescheiden. Standaard de hele bibliotheek.
	 *
	 * [--yes]
	 * : Sla de bevestigingsvraag over.
	 *
	 * ## EXAMPLES
	 *
	 *     wp imgx delete --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function delete( $args, $assoc_args ) {
		unset( $args );

		\WP_CLI::confirm( 'Alle door IMGX gegenereerde bestanden verwijderen? Je originele afbeeldingen blijven ongemoeid.', $assoc_args );

		$ids = $this->resolve_ids( $assoc_args );

		if ( ! $ids ) {
			\WP_CLI::success( 'Niets te doen.' );

			return;
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Verwijderen', count( $ids ) );
		$deleted  = 0;

		foreach ( $ids as $id ) {
			$deleted += count( $this->generator->delete_variants( $id ) );
			$progress->tick();
		}

		$progress->finish();

		delete_transient( Admin::STATS_TRANSIENT );

		\WP_CLI::success( sprintf( '%d bestanden verwijderd.', $deleted ) );
	}

	/**
	 * Resolves the attachment IDs to work on.
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return int[]
	 */
	private function resolve_ids( array $assoc_args ) {
		if ( ! empty( $assoc_args['ids'] ) ) {
			$ids = array_map( 'intval', preg_split( '/[\s,]+/', (string) $assoc_args['ids'] ) );

			return array_values( array_filter( $ids ) );
		}

		return get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => Generator::source_mime_types(),
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'suppress_filters'       => false,
			)
		);
	}

	/**
	 * Drops the object cache and query log so long runs do not exhaust memory.
	 *
	 * @return void
	 */
	private function flush_caches() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = array();
		Variants::flush_cache();

		if ( is_object( $wp_object_cache ) && property_exists( $wp_object_cache, 'cache' ) ) {
			$wp_object_cache->cache = array();
		}
	}
}
