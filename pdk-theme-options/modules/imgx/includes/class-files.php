<?php
/**
 * Filesystem and URL helpers.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * Path building, sidecar naming, format sniffing and guarded deletion.
 *
 * Every method is static and side-effect free apart from delete_variant(), which is the
 * only place in the plugin allowed to remove a file.
 */
class Files {

	/**
	 * Extensions IMGX is ever allowed to write or delete.
	 */
	const VARIANT_EXTENSIONS = array( 'webp', 'avif' );

	/**
	 * Mime type per supported variant format.
	 */
	const MIME_TYPES = array(
		'avif' => 'image/avif',
		'webp' => 'image/webp',
	);

	/**
	 * Cached realpath of the uploads base directory.
	 *
	 * @var string|null
	 */
	private static $uploads_basedir = null;

	/**
	 * Returns the resolved uploads base directory, or an empty string when unavailable.
	 *
	 * @return string
	 */
	public static function uploads_basedir() {
		if ( null !== self::$uploads_basedir ) {
			return self::$uploads_basedir;
		}

		$uploads = wp_get_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			self::$uploads_basedir = '';

			return self::$uploads_basedir;
		}

		$real = realpath( $uploads['basedir'] );

		self::$uploads_basedir = self::normalize( false === $real ? $uploads['basedir'] : $real );

		return self::$uploads_basedir;
	}

	/**
	 * Resets the cached uploads directory. Used by the test suite and by multisite switches.
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$uploads_basedir = null;
	}

	/**
	 * Converts Windows separators and strips a trailing slash.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public static function normalize( $path ) {
		return rtrim( str_replace( '\\', '/', (string) $path ), '/' );
	}

	/**
	 * Whether a path resolves to a location inside the uploads directory.
	 *
	 * Uses the parent directory for files that do not exist yet, so it can be called
	 * before writing as well as before deleting.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function is_inside_uploads( $path ) {
		$basedir = self::uploads_basedir();

		if ( '' === $basedir || '' === (string) $path ) {
			return false;
		}

		$target = file_exists( $path ) ? realpath( $path ) : realpath( dirname( $path ) );

		if ( false === $target ) {
			return false;
		}

		$target = self::normalize( $target );

		return $target === $basedir || 0 === strpos( $target . '/', $basedir . '/' );
	}

	/**
	 * Deletes a generated variant file.
	 *
	 * Refuses anything that is not a .webp/.avif file inside the uploads directory, so no
	 * code path in the plugin can remove an original image.
	 *
	 * @param string $path Absolute path.
	 * @return bool True when the file is gone afterwards.
	 */
	public static function delete_variant( $path ) {
		$path      = (string) $path;
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, self::VARIANT_EXTENSIONS, true ) ) {
			return false;
		}

		if ( ! self::is_inside_uploads( $path ) ) {
			return false;
		}

		if ( ! file_exists( $path ) ) {
			return true;
		}

		wp_delete_file( $path );

		return ! file_exists( $path );
	}

	/**
	 * Builds the sidecar path for a source file.
	 *
	 * Prefers photo.webp. Because photo.jpg and photo.png can legitimately coexist in one
	 * folder and would both want photo.webp, an existing file that this attachment does
	 * not own falls back to photo.jpg.webp and then to photo-<hash>.webp.
	 *
	 * @param string $source_path    Absolute path to the source image.
	 * @param string $format         Variant format key: webp or avif.
	 * @param array  $owned          Basenames already recorded for this attachment.
	 * @param int    $attachment_id  Attachment ID, used to salt the last-resort hash.
	 * @return string Absolute destination path.
	 */
	public static function sidecar_path( $source_path, $format, array $owned = array(), $attachment_id = 0 ) {
		$dir      = self::normalize( dirname( $source_path ) );
		$basename = wp_basename( $source_path );
		$ext      = pathinfo( $basename, PATHINFO_EXTENSION );
		$stem     = ( '' !== $ext ) ? substr( $basename, 0, - ( strlen( $ext ) + 1 ) ) : $basename;

		$candidates = array(
			$stem . '.' . $format,
			$basename . '.' . $format,
			$stem . '-' . substr( md5( $basename . '|' . (int) $attachment_id ), 0, 6 ) . '.' . $format,
		);

		foreach ( $candidates as $candidate ) {
			$path = $dir . '/' . $candidate;

			if ( in_array( $candidate, $owned, true ) || ! file_exists( $path ) ) {
				return $path;
			}
		}

		// Every candidate is taken by a foreign file; overwrite nothing, use the hashed one.
		return $dir . '/' . end( $candidates );
	}

	/**
	 * Detects an image format from its magic bytes.
	 *
	 * Deliberately independent of wp_get_image_mime() and of the file extension, so a
	 * mislabelled or truncated encoder output is caught.
	 *
	 * @param string $path Absolute path.
	 * @return string Format key (jpeg, png, gif, webp, avif) or an empty string.
	 */
	public static function sniff_format( $path ) {
		if ( ! is_readable( $path ) ) {
			return '';
		}

		$handle = @fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged -- Reading 16 bytes; WP_Filesystem would read the whole file.

		if ( ! $handle ) {
			return '';
		}

		$bytes = fread( $handle, 16 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( false === $bytes || strlen( $bytes ) < 12 ) {
			return '';
		}

		if ( "\xFF\xD8\xFF" === substr( $bytes, 0, 3 ) ) {
			return 'jpeg';
		}

		if ( "\x89PNG\r\n\x1a\n" === substr( $bytes, 0, 8 ) ) {
			return 'png';
		}

		if ( 'GIF8' === substr( $bytes, 0, 4 ) ) {
			return 'gif';
		}

		if ( 'RIFF' === substr( $bytes, 0, 4 ) && 'WEBP' === substr( $bytes, 8, 4 ) ) {
			return 'webp';
		}

		if ( 'ftyp' === substr( $bytes, 4, 4 ) ) {
			$brand = substr( $bytes, 8, 4 );

			if ( in_array( $brand, array( 'avif', 'avis', 'mif1', 'msf1' ), true ) ) {
				return 'avif';
			}
		}

		return '';
	}

	/**
	 * Replaces the final path segment of a URL, preserving scheme, host, query and fragment.
	 *
	 * @param string $url          Original URL.
	 * @param string $new_basename Replacement basename.
	 * @return string
	 */
	public static function swap_basename_in_url( $url, $new_basename ) {
		$url = (string) $url;

		$suffix   = '';
		$hash_pos = strpos( $url, '#' );
		if ( false !== $hash_pos ) {
			$suffix = substr( $url, $hash_pos );
			$url    = substr( $url, 0, $hash_pos );
		}

		$query_pos = strpos( $url, '?' );
		if ( false !== $query_pos ) {
			$suffix = substr( $url, $query_pos ) . $suffix;
			$url    = substr( $url, 0, $query_pos );
		}

		$slash_pos = strrpos( $url, '/' );
		if ( false === $slash_pos ) {
			return $new_basename . $suffix;
		}

		return substr( $url, 0, $slash_pos + 1 ) . $new_basename . $suffix;
	}

	/**
	 * Extracts the filename from a URL, ignoring query strings and fragments.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function basename_from_url( $url ) {
		$url = (string) $url;

		$cut = strcspn( $url, '?#' );
		$url = substr( $url, 0, $cut );

		return wp_basename( rawurldecode( $url ) );
	}
}
