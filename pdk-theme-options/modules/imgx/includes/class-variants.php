<?php
/**
 * Reading and writing the per-attachment variant registry.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * The single source of truth for "which NextGen files exist for this attachment".
 *
 * Only basenames are stored: every WordPress sub-size lives in the same directory as the
 * file referenced by _wp_attached_file, so the directory is derivable and storing it per
 * entry would be redundant and would break on migration.
 */
class Variants {

	/**
	 * Post meta key.
	 */
	const META_KEY = '_imgx_variants';

	/**
	 * Post meta key van de tiny-placeholder (kale base64 van een JPEG van 16 px).
	 */
	const TINY_KEY = '_imgx_lqip_tiny';

	/**
	 * Maximale lengte van de base64-string; groter wordt niet opgeslagen.
	 */
	const TINY_MAX = 2048;

	/**
	 * Structure version, so a future format change can be migrated.
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Per-request cache of the source-basename lookup maps.
	 *
	 * @var array<int, array>
	 */
	private static $map_cache = array();

	/**
	 * Returns the normalised registry for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	public static function get( $attachment_id ) {
		$data = get_post_meta( (int) $attachment_id, self::META_KEY, true );

		return self::normalize( $data );
	}

	/**
	 * Persists the registry.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $data          Registry.
	 * @return void
	 */
	public static function save( $attachment_id, array $data ) {
		$attachment_id = (int) $attachment_id;
		$data          = self::normalize( $data );

		$data['version']   = self::SCHEMA_VERSION;
		$data['generated'] = time();

		if ( empty( $data['sizes'] ) && empty( $data['skipped'] ) ) {
			self::delete( $attachment_id );

			return;
		}

		update_post_meta( $attachment_id, self::META_KEY, $data );

		unset( self::$map_cache[ $attachment_id ] );
	}

	/**
	 * Geeft de tiny-placeholder (bare base64) of een lege string. Valideert ook bij het lezen,
	 * want de waarde komt ongeëscaped in een data-URI terecht.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function get_tiny( $attachment_id ) {
		$tiny = get_post_meta( (int) $attachment_id, self::TINY_KEY, true );

		return self::valid_tiny( $tiny ) ? $tiny : '';
	}

	/**
	 * Of een waarde een toegestane tiny-base64 is.
	 *
	 * @param mixed $tiny Waarde.
	 * @return bool
	 */
	public static function valid_tiny( $tiny ) {
		return is_string( $tiny ) && strlen( $tiny ) <= self::TINY_MAX && 1 === preg_match( '/\A[A-Za-z0-9+\/]+={0,2}\z/', $tiny );
	}

	/**
	 * Pixelafmetingen [breedte, hoogte] van het bestand met deze basename, uit de al geladen
	 * attachment-metadata (geen extra query), of een lege array als ze onbekend zijn.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $basename      Basename van het bronbestand (full/scaled of een sub-size).
	 * @return int[]
	 */
	public static function dimensions( $attachment_id, $basename ) {
		$meta = wp_get_attachment_metadata( (int) $attachment_id );

		if ( ! is_array( $meta ) ) {
			return array();
		}

		$infos = array( $meta );

		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			$infos = array_merge( $infos, array_values( $meta['sizes'] ) );
		}

		foreach ( $infos as $info ) {
			$file = isset( $info['file'] ) && is_string( $info['file'] ) ? wp_basename( $info['file'] ) : '';

			if ( $file === $basename && ! empty( $info['width'] ) && ! empty( $info['height'] ) ) {
				return array( (int) $info['width'], (int) $info['height'] );
			}
		}

		return array();
	}

	/**
	 * Removes the registry.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function delete( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		delete_post_meta( $attachment_id, self::META_KEY );
		delete_post_meta( $attachment_id, self::TINY_KEY );

		unset( self::$map_cache[ $attachment_id ] );
	}

	/**
	 * Clears the per-request lookup map cache.
	 *
	 * @param int|null $attachment_id Specific attachment, or null for all.
	 * @return void
	 */
	public static function flush_cache( $attachment_id = null ) {
		if ( null === $attachment_id ) {
			self::$map_cache = array();

			return;
		}

		unset( self::$map_cache[ (int) $attachment_id ] );
	}

	/**
	 * Coerces stored data into the expected shape.
	 *
	 * @param mixed $data Raw meta value.
	 * @return array
	 */
	public static function normalize( $data ) {
		$clean = array(
			'version'   => self::SCHEMA_VERSION,
			'generated' => 0,
			'signature' => '',
			'sizes'     => array(),
			'skipped'   => array(),
		);

		if ( ! is_array( $data ) ) {
			return $clean;
		}

		$clean['generated'] = isset( $data['generated'] ) ? (int) $data['generated'] : 0;

		// Instellingen-vingerafdruk waaronder deze registratie is opgebouwd, zie
		// Settings::encoding_signature(). Ontbreekt hij (registratie van vóór
		// deze versie), dan blijft hij leeg en telt hij als afwijkend.
		if ( isset( $data['signature'] ) && is_string( $data['signature'] ) ) {
			$clean['signature'] = substr( $data['signature'], 0, 64 );
		}

		foreach ( array( 'sizes', 'skipped' ) as $bucket ) {
			if ( empty( $data[ $bucket ] ) || ! is_array( $data[ $bucket ] ) ) {
				continue;
			}

			foreach ( $data[ $bucket ] as $size => $formats ) {
				if ( ! is_array( $formats ) ) {
					continue;
				}

				foreach ( $formats as $format => $value ) {
					if ( ( ! isset( Files::MIME_TYPES[ $format ] ) && Files::LQIP_KEY !== $format ) || ! is_string( $value ) || '' === $value ) {
						continue;
					}

					// Basenames only: reject anything that looks like a path.
					if ( 'sizes' === $bucket && wp_basename( $value ) !== $value ) {
						continue;
					}

					$clean[ $bucket ][ (string) $size ][ $format ] = $value;
				}
			}
		}

		return $clean;
	}

	/**
	 * Every variant basename recorded for an attachment, de-duplicated.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string[]
	 */
	public static function basenames( $attachment_id ) {
		$data      = self::get( $attachment_id );
		$basenames = array();

		foreach ( $data['sizes'] as $formats ) {
			foreach ( $formats as $basename ) {
				$basenames[ $basename ] = true;
			}
		}

		return array_keys( $basenames );
	}

	/**
	 * Builds the "source basename => variant basenames" map used by the renderer.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, array<string, string>>
	 */
	public static function lookup_map( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( isset( self::$map_cache[ $attachment_id ] ) ) {
			return self::$map_cache[ $attachment_id ];
		}

		$variants = self::get( $attachment_id );

		if ( empty( $variants['sizes'] ) ) {
			self::$map_cache[ $attachment_id ] = array();

			return array();
		}

		$meta = wp_get_attachment_metadata( $attachment_id );
		$map  = array();

		if ( is_array( $meta ) && ! empty( $meta['file'] ) && ! empty( $variants['sizes']['full'] ) ) {
			$map[ wp_basename( $meta['file'] ) ] = $variants['sizes']['full'];
		}

		if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size => $info ) {
				if ( empty( $info['file'] ) || empty( $variants['sizes'][ $size ] ) ) {
					continue;
				}

				$map[ wp_basename( $info['file'] ) ] = $variants['sizes'][ $size ];
			}
		}

		self::$map_cache[ $attachment_id ] = $map;

		return $map;
	}

	/**
	 * Counts attachments that have a variant registry, for the admin statistics.
	 *
	 * @return int
	 */
	public static function count_registered() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached by the caller.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_KEY
			)
		);
	}

	/**
	 * Counts individual recorded variant files, for the admin statistics.
	 *
	 * @return int
	 */
	public static function count_files() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached by the caller.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_KEY
			)
		);

		$total = 0;

		foreach ( (array) $rows as $row ) {
			$data = maybe_unserialize( $row );

			if ( ! is_array( $data ) || empty( $data['sizes'] ) || ! is_array( $data['sizes'] ) ) {
				continue;
			}

			foreach ( $data['sizes'] as $formats ) {
				if ( is_array( $formats ) ) {
					$total += count( $formats );
				}
			}
		}

		return $total;
	}
}
