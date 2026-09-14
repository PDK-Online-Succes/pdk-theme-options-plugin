<?php
/**
 * Runtime detection of what this server can actually encode.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * Probes the active WP_Image_Editor by really encoding a tiny image.
 *
 * Extension names and PHP versions are not trusted: function_exists( 'imageavif' ) can be
 * true on a build without libavif, and Imagick::queryFormats() can list formats whose
 * delegate cannot write.
 */
class Capabilities {

	/**
	 * Option storing the cached probe result.
	 */
	const OPTION = 'imgx_capabilities';

	/**
	 * Probe image dimensions. Small enough to be instant, large enough for every encoder.
	 */
	const PROBE_SIZE = 16;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Runtime cache.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Returns the capability report, probing when the cache is missing or stale.
	 *
	 * @return array {
	 *     @type bool   $avif        Whether AVIF can be written.
	 *     @type bool   $webp        Whether WebP can be written.
	 *     @type string $editor      Selected WP_Image_Editor implementation.
	 *     @type int    $checked     Timestamp of the probe.
	 *     @type string $fingerprint Environment fingerprint.
	 *     @type array  $notes       Per-format failure reasons.
	 * }
	 */
	public function get() {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$stored = get_option( self::OPTION, array() );

		if (
			is_array( $stored )
			&& isset( $stored['fingerprint'] )
			&& $stored['fingerprint'] === $this->fingerprint()
		) {
			$this->cache = $stored;

			return $this->cache;
		}

		return $this->refresh();
	}

	/**
	 * Forces a fresh probe and stores the result.
	 *
	 * @return array
	 */
	public function refresh() {
		$report = $this->detect();

		update_option( self::OPTION, $report, false );

		$this->cache = $report;

		return $report;
	}

	/**
	 * Whether a format can be produced on this server.
	 *
	 * @param string $format Format key.
	 * @return bool
	 */
	public function supports( $format ) {
		$report = $this->get();

		return ! empty( $report[ $format ] );
	}

	/**
	 * Human readable reason a format is unavailable.
	 *
	 * @param string $format Format key.
	 * @return string
	 */
	public function reason( $format ) {
		$report = $this->get();

		return isset( $report['notes'][ $format ] ) ? (string) $report['notes'][ $format ] : '';
	}

	/**
	 * Short name of the active image editor implementation.
	 *
	 * @return string
	 */
	public function editor_name() {
		$report = $this->get();
		$class  = isset( $report['editor'] ) ? (string) $report['editor'] : '';

		if ( false !== strpos( $class, 'Imagick' ) ) {
			return 'Imagick';
		}

		if ( false !== strpos( $class, 'GD' ) ) {
			return 'GD';
		}

		return $class ? $class : __( 'geen', 'pdk-theme-options' );
	}

	/**
	 * Runs the probes.
	 *
	 * @return array
	 */
	private function detect() {
		$editor = $this->editor_class();

		$report = array(
			'avif'        => false,
			'webp'        => false,
			'editor'      => $editor,
			'checked'     => time(),
			'fingerprint' => $this->fingerprint(),
			'notes'       => array(),
		);

		if ( '' === $editor ) {
			$report['notes']['avif'] = __( 'Er is geen afbeeldingsbewerker (GD of Imagick) beschikbaar.', 'pdk-theme-options' );
			$report['notes']['webp'] = $report['notes']['avif'];

			return $report;
		}

		$source = $this->write_probe_source();

		if ( '' === $source ) {
			$report['notes']['avif'] = __( 'Kon geen testafbeelding naar de tijdelijke map schrijven.', 'pdk-theme-options' );
			$report['notes']['webp'] = $report['notes']['avif'];

			return $report;
		}

		foreach ( array( 'avif', 'webp' ) as $format ) {
			$result = $this->probe( $source, $format );

			if ( true === $result ) {
				$report[ $format ] = true;
			} else {
				$report['notes'][ $format ] = (string) $result;
			}
		}

		wp_delete_file( $source );

		return $report;
	}

	/**
	 * Really encodes the probe image into a format and verifies the bytes written.
	 *
	 * @param string $source Absolute path to the probe PNG.
	 * @param string $format Format key.
	 * @return true|string True on success, otherwise a human readable reason.
	 */
	private function probe( $source, $format ) {
		$mime = Files::MIME_TYPES[ $format ];

		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) {
			/* translators: %s: image format name. */
			return sprintf( __( 'De actieve afbeeldingsbewerker meldt geen ondersteuning voor %s.', 'pdk-theme-options' ), strtoupper( $format ) );
		}

		$dest   = substr( $source, 0, -4 ) . '.' . $format;
		$reason = '';

		Converter::lock_output_format();

		try {
			$editor = wp_get_image_editor( $source, array( 'mime_type' => 'image/png' ) );

			if ( is_wp_error( $editor ) ) {
				$reason = $editor->get_error_message();
			} else {
				$saved = $editor->save( $dest, $mime );

				if ( is_wp_error( $saved ) ) {
					$reason = $saved->get_error_message();
				} elseif ( ! is_array( $saved ) || empty( $saved['path'] ) ) {
					$reason = __( 'De afbeeldingsbewerker gaf geen bestand terug.', 'pdk-theme-options' );
				} else {
					$dest = $saved['path'];

					if ( Files::sniff_format( $dest ) !== $format ) {
						/* translators: %s: image format name. */
						$reason = sprintf( __( 'De encoder leverde een bestand op dat geen geldige %s is.', 'pdk-theme-options' ), strtoupper( $format ) );
					}
				}
			}
		} catch ( \Throwable $e ) {
			$reason = $e->getMessage();
		} finally {
			Converter::unlock_output_format();
		}

		if ( file_exists( $dest ) ) {
			wp_delete_file( $dest );
		}

		if ( '' !== $reason ) {
			$this->logger->debug( 'capability probe failed', array( 'format' => $format, 'reason' => $reason ) );

			return $reason;
		}

		return true;
	}

	/**
	 * Writes a small PNG into the temporary directory for the probe to read.
	 *
	 * @return string Absolute path, or an empty string on failure.
	 */
	private function write_probe_source() {
		$dir = trailingslashit( get_temp_dir() );

		if ( ! wp_is_writable( $dir ) ) {
			return '';
		}

		$path = $dir . 'imgx-probe-' . wp_generate_password( 12, false, false ) . '.png';

		try {
			if ( extension_loaded( 'imagick' ) && class_exists( '\Imagick' ) ) {
				$image = new \Imagick();
				$image->newImage( self::PROBE_SIZE, self::PROBE_SIZE, new \ImagickPixel( 'rgb(120,140,160)' ) );
				$image->setImageFormat( 'png' );
				$image->writeImage( $path );
				$image->clear();
			} elseif ( function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagepng' ) ) {
				$image = imagecreatetruecolor( self::PROBE_SIZE, self::PROBE_SIZE );
				imagefilledrectangle( $image, 0, 0, self::PROBE_SIZE - 1, self::PROBE_SIZE - 1, imagecolorallocate( $image, 120, 140, 160 ) );
				imagepng( $image, $path );
				imagedestroy( $image );
			} else {
				return '';
			}
		} catch ( \Throwable $e ) {
			$this->logger->debug( 'probe source could not be created', array( 'reason' => $e->getMessage() ) );

			return '';
		}

		return file_exists( $path ) && filesize( $path ) > 0 ? $path : '';
	}

	/**
	 * Class name of the image editor WordPress would select.
	 *
	 * @return string
	 */
	private function editor_class() {
		if ( ! function_exists( '_wp_image_editor_choose' ) ) {
			return '';
		}

		$class = _wp_image_editor_choose( array( 'mime_type' => 'image/jpeg' ) );

		return is_string( $class ) ? $class : '';
	}

	/**
	 * Fingerprint of everything that could change the answer.
	 *
	 * @return string
	 */
	private function fingerprint() {
		$parts = array(
			IMGX_VERSION,
			get_bloginfo( 'version' ),
			PHP_VERSION,
			$this->editor_class(),
		);

		if ( extension_loaded( 'imagick' ) && class_exists( '\Imagick' ) ) {
			try {
				$version = \Imagick::getVersion();
				$parts[] = isset( $version['versionString'] ) ? $version['versionString'] : 'imagick';
			} catch ( \Throwable $e ) {
				$parts[] = 'imagick-unknown';
			}
		}

		if ( function_exists( 'gd_info' ) ) {
			$info    = gd_info();
			$parts[] = isset( $info['GD Version'] ) ? $info['GD Version'] : 'gd';
			$parts[] = ! empty( $info['WebP Support'] ) ? 'gd-webp' : '';
			$parts[] = ! empty( $info['AVIF Support'] ) ? 'gd-avif' : '';
		}

		return md5( implode( '|', $parts ) );
	}
}
