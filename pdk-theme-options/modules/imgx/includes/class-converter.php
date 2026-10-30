<?php
/**
 * Encodes one source file into one sidecar file.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * The only class that touches WP_Image_Editor.
 *
 * It never opens, rewrites or deletes the source file; it reads it and writes a separate
 * destination. Every result is verified by magic bytes before it is reported as success.
 */
class Converter {

	/**
	 * Nesting depth of the image_editor_output_format lock.
	 *
	 * @var int
	 */
	private static $output_format_lock = 0;

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
	 * @param Logger       $logger       Logger.
	 */
	public function __construct( Settings $settings, Capabilities $capabilities, Logger $logger ) {
		$this->settings     = $settings;
		$this->capabilities = $capabilities;
		$this->logger       = $logger;
	}

	/**
	 * Neutralises third-party image_editor_output_format filters for the duration of a save.
	 *
	 * Another plugin mapping image/webp to something else would silently make IMGX write
	 * the wrong format to a .webp path.
	 *
	 * @return void
	 */
	public static function lock_output_format() {
		if ( 0 === self::$output_format_lock ) {
			add_filter( 'image_editor_output_format', array( __CLASS__, 'no_output_format' ), PHP_INT_MAX );
		}

		++self::$output_format_lock;
	}

	/**
	 * Releases the output format lock.
	 *
	 * @return void
	 */
	public static function unlock_output_format() {
		if ( self::$output_format_lock > 0 ) {
			--self::$output_format_lock;
		}

		if ( 0 === self::$output_format_lock ) {
			remove_filter( 'image_editor_output_format', array( __CLASS__, 'no_output_format' ), PHP_INT_MAX );
		}
	}

	/**
	 * Returns an empty output format map.
	 *
	 * @return array
	 */
	public static function no_output_format() {
		return array();
	}

	/**
	 * Converts one file.
	 *
	 * @param string $source_path Absolute path to an existing JPEG or PNG.
	 * @param string $dest_path   Absolute destination path; its extension must match $format.
	 * @param string $format      Variant format key: webp, avif or lqip (JPEG-placeholder, zie save_lqip()).
	 * @param array  $context     Optional context: attachment_id, size.
	 * @return string|WP_Error Absolute path of the written file, or an error.
	 */
	public function convert( $source_path, $dest_path, $format, array $context = array() ) {
		$context = wp_parse_args(
			$context,
			array(
				'attachment_id' => 0,
				'size'          => 'full',
			)
		);

		$lqip = ( Files::LQIP_KEY === $format );

		if ( ! $lqip && ! isset( Files::MIME_TYPES[ $format ] ) ) {
			return new WP_Error( 'imgx_unknown_format', __( 'Onbekend afbeeldingsformaat opgevraagd.', 'pdk-theme-options' ) );
		}

		// LQIP is een gewone JPEG: alleen JPEG-schrijven is nodig, niet de WebP/AVIF-probe.
		if ( $lqip ? ! wp_image_editor_supports( array( 'mime_type' => 'image/jpeg' ) ) : ! $this->capabilities->supports( $format ) ) {
			return new WP_Error(
				'imgx_unsupported_format',
				sprintf(
					/* translators: %s: image format name. */
					__( 'Deze server kan geen %s coderen.', 'pdk-theme-options' ),
					strtoupper( $format )
				)
			);
		}

		if ( ! is_readable( $source_path ) ) {
			return new WP_Error( 'imgx_source_missing', __( 'Het bronbestand ontbreekt of is niet leesbaar.', 'pdk-theme-options' ) );
		}

		$source_size = (int) filesize( $source_path );

		if ( $source_size < 1 ) {
			return new WP_Error( 'imgx_source_empty', __( 'Het bronbestand is leeg.', 'pdk-theme-options' ) );
		}

		if ( ! Files::is_inside_uploads( $dest_path ) ) {
			return new WP_Error( 'imgx_destination_rejected', __( 'Het doel ligt buiten de uploadsmap.', 'pdk-theme-options' ) );
		}

		$dest_dir = dirname( $dest_path );

		if ( ! wp_mkdir_p( $dest_dir ) || ! wp_is_writable( $dest_dir ) ) {
			return new WP_Error( 'imgx_not_writable', __( 'De doelmap is niet beschrijfbaar.', 'pdk-theme-options' ) );
		}

		$mime    = $lqip ? 'image/jpeg' : Files::MIME_TYPES[ $format ];
		$quality = $lqip ? $this->lqip_start_quality( $context ) : $this->quality( $format, $context );

		self::lock_output_format();

		$written      = '';
		$dest_existed = file_exists( $dest_path );
		$error   = null;

		try {
			$editor = wp_get_image_editor( $source_path );

			if ( is_wp_error( $editor ) ) {
				$error = $editor;
			} else {
				$editor->set_quality( $quality );

				/**
				 * Fires just before a variant is written.
				 *
				 * Gives developers access to the configured WP_Image_Editor, for example to
				 * set Imagick encoder options such as heic:speed.
				 *
				 * @param \WP_Image_Editor $editor  Configured editor.
				 * @param string           $format  Target format key.
				 * @param int              $quality Quality that was set.
				 * @param array            $context Attachment id and size.
				 */
				do_action( 'imgx_before_save', $editor, $format, $quality, $context );

				$saved = $lqip
						? $this->save_lqip( $editor, $dest_path, $mime, $quality )
						: $editor->save( $dest_path, $mime );

				if ( is_wp_error( $saved ) ) {
					$error = $saved;
				} elseif ( ! is_array( $saved ) || empty( $saved['path'] ) ) {
					$error = new WP_Error( 'imgx_no_output', __( 'De afbeeldingsbewerker gaf geen bestand terug.', 'pdk-theme-options' ) );
				} else {
					$written = $saved['path'];
				}
			}
		} catch ( \Throwable $e ) {
			$error = new WP_Error( 'imgx_encoder_exception', $e->getMessage() );
		} finally {
			self::unlock_output_format();
		}

		if ( $error instanceof WP_Error ) {
			if ( '' !== $written ) {
				Files::delete_variant( $written );
			}

			// Een eerdere lus-ronde kan het doel al geschreven hebben; zonder registratie
			// zou het volgende run dat bestand voor een vreemd bestand aanzien.
			if ( $lqip && ! $dest_existed ) {
				Files::delete_variant( $dest_path );
			}

			return $error;
		}

		return $this->verify( $written, $dest_path, $format, $source_size );
	}

	/**
	 * Validates what the encoder actually wrote.
	 *
	 * @param string $written     Path reported by the editor.
	 * @param string $expected    Path IMGX asked for.
	 * @param string $format      Format key.
	 * @param int    $source_size Size of the source file in bytes.
	 * @return string|WP_Error
	 */
	private function verify( $written, $expected, $format, $source_size ) {
		if ( Files::normalize( $written ) !== Files::normalize( $expected ) ) {
			// A filter or the editor redirected the output; do not keep a file we did not ask for.
			Files::delete_variant( $written );

			return new WP_Error( 'imgx_unexpected_path', __( 'De afbeeldingsbewerker schreef naar een onverwacht pad.', 'pdk-theme-options' ) );
		}

		clearstatcache( true, $written );

		if ( ! file_exists( $written ) ) {
			return new WP_Error( 'imgx_write_failed', __( 'Het gegenereerde bestand kon niet worden weggeschreven.', 'pdk-theme-options' ) );
		}

		$size = (int) filesize( $written );

		if ( $size < 1 ) {
			Files::delete_variant( $written );

			return new WP_Error( 'imgx_write_empty', __( 'Het gegenereerde bestand is leeg.', 'pdk-theme-options' ) );
		}

		if ( Files::sniff_format( $written ) !== ( Files::LQIP_KEY === $format ? 'jpeg' : $format ) ) {
			Files::delete_variant( $written );

			return new WP_Error(
				'imgx_invalid_output',
				sprintf(
					/* translators: %s: image format name. */
					__( 'Het gegenereerde bestand is geen geldige %s.', 'pdk-theme-options' ),
					strtoupper( $format )
				)
			);
		}

		/**
		 * Filters whether to keep a variant that is larger than its source.
		 *
		 * Small PNG icons and already optimised JPEGs regularly encode larger; serving
		 * those would be a regression rather than an optimisation.
		 *
		 * @param bool   $keep   Whether to keep it. Default false.
		 * @param string $format Format key.
		 */
		$keep_larger = (bool) apply_filters( 'imgx_keep_larger_variant', false, $format );

		if ( ! $keep_larger && $size >= $source_size ) {
			Files::delete_variant( $written );

			return new WP_Error(
				'imgx_larger_than_source',
				__( 'Het gegenereerde bestand is groter dan het origineel en is daarom weggegooid.', 'pdk-theme-options' )
			);
		}

		return $written;
	}

	/**
	 * Startkwaliteit van de LQIP-lus.
	 *
	 * @param array $context Attachment id and size.
	 * @return int
	 */
	private function lqip_start_quality( array $context ) {
		/**
		 * Filters the quality the LQIP loop starts at (default 10).
		 *
		 * @param int    $quality       Quality, 1-100.
		 * @param int    $attachment_id Attachment ID.
		 * @param string $size          Image size key.
		 */
		$quality = apply_filters( 'imgx_lqip_start_quality', 10, $context['attachment_id'], $context['size'] );

		return (int) max( 1, min( 100, (int) $quality ) );
	}

	/**
	 * Schrijft de LQIP: JPEG op de pixelafmeting van de bron, met de laagste kwaliteit
	 * waarbij het bestand minstens 0,055 bits per pixel haalt (Chromium telt een
	 * afbeelding vanaf 0,05 bpp als LCP-kandidaat). Plafond q=60; een resultaat op het
	 * plafond blijft staan, ook als het onder het doel zit.
	 *
	 * @param \WP_Image_Editor $editor  Editor met de bron geladen.
	 * @param string           $dest    Absolute destination path.
	 * @param string           $mime    Mime type (image/jpeg).
	 * @param int              $quality Startkwaliteit.
	 * @return array|WP_Error Resultaat van WP_Image_Editor::save().
	 */
	private function save_lqip( $editor, $dest, $mime, $quality ) {
		$dimensions = $editor->get_size();
		$target     = (int) ceil( $dimensions['width'] * $dimensions['height'] * 0.055 / 8 );
		$ceiling    = 60;

		// WP_Image_Editor::get_output_format() roept set_quality() zonder argument aan als de
		// uitvoer-mime van de bron afwijkt (PNG naar JPEG) en zet de kwaliteit dan terug naar
		// de standaard. Deze filters dwingen de lus-kwaliteit af.
		$pin = static function () use ( &$quality ) {
			return $quality;
		};

		add_filter( 'wp_editor_set_quality', $pin, PHP_INT_MAX );
		add_filter( 'jpeg_quality', $pin, PHP_INT_MAX );

		try {
			while ( true ) {
				$editor->set_quality( $quality );

				$saved = $editor->save( $dest, $mime );

				if ( is_wp_error( $saved ) || ! is_array( $saved ) || empty( $saved['path'] ) || $quality >= $ceiling ) {
					return $saved;
				}

				clearstatcache( true, $saved['path'] );

				if ( (int) filesize( $saved['path'] ) >= $target ) {
					return $saved;
				}

				$quality = min( $ceiling, $quality + 5 );
			}
		} finally {
			remove_filter( 'wp_editor_set_quality', $pin, PHP_INT_MAX );
			remove_filter( 'jpeg_quality', $pin, PHP_INT_MAX );
		}
	}

	/**
	 * Maakt de tiny-placeholder: JPEG met een langste zijde van 16 px op vaste kwaliteit 40,
	 * als kale base64. Het tijdelijke bestand wordt altijd opgeruimd.
	 *
	 * @param string $source Absoluut pad van de bron.
	 * @return string Base64, of een lege string als het niet lukt.
	 */
	public function tiny_base64( $source ) {
		if ( ! is_readable( $source ) || ! wp_image_editor_supports( array( 'mime_type' => 'image/jpeg' ) ) ) {
			return '';
		}

		$editor = wp_get_image_editor( $source );

		if ( is_wp_error( $editor ) ) {
			return '';
		}

		$size = $editor->get_size();

		if ( empty( $size['width'] ) || empty( $size['height'] ) ) {
			return '';
		}

		$ratio = 16 / max( $size['width'], $size['height'] );

		if ( is_wp_error( $editor->resize( max( 1, (int) round( $size['width'] * $ratio ) ), max( 1, (int) round( $size['height'] * $ratio ) ), false ) ) ) {
			return '';
		}

		// Zie save_lqip(): get_output_format() zet de kwaliteit terug bij een mime-wissel.
		$pin = static function () {
			return 40;
		};

		add_filter( 'wp_editor_set_quality', $pin, PHP_INT_MAX );
		add_filter( 'jpeg_quality', $pin, PHP_INT_MAX );

		$base  = wp_tempnam( 'imgx-tiny' );
		$tmp   = $base . '.jpg';
		$bytes = false;
		$saved = null;

		// Een image_editor_output_format-map van derden mag de JPEG niet in een andere indeling schrijven.
		self::lock_output_format();

		try {
			$editor->set_quality( 40 );

			$saved = $editor->save( $tmp, 'image/jpeg' );

			if ( ! is_wp_error( $saved ) && is_array( $saved ) && ! empty( $saved['path'] ) ) {
				$bytes = file_get_contents( $saved['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Lokaal tempbestand.
			}

			// Alleen echte JPEG-bytes mogen in de data-URI.
			if ( false !== $bytes && 'jpeg' !== Files::sniff_format( $saved['path'] ) ) {
				$bytes = false;
			}
		} finally {
			self::unlock_output_format();
			remove_filter( 'wp_editor_set_quality', $pin, PHP_INT_MAX );
			remove_filter( 'jpeg_quality', $pin, PHP_INT_MAX );

			$files = array( $base, $tmp );

			if ( is_array( $saved ) && ! empty( $saved['path'] ) ) {
				$files[] = $saved['path'];
			}

			foreach ( array_unique( $files ) as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}

		return $bytes ? base64_encode( $bytes ) : '';
	}

	/**
	 * Resolves the encoder quality for a format.
	 *
	 * @param string $format  Format key.
	 * @param array  $context Attachment id and size.
	 * @return int
	 */
	private function quality( $format, array $context ) {
		$quality = (int) $this->settings->get( 'avif' === $format ? 'avif_quality' : 'webp_quality' );

		if ( 'avif' === $format ) {
			/**
			 * Filters the AVIF encoder quality.
			 *
			 * AVIF quality is not on the same perceptual scale as JPEG or WebP: roughly
			 * 45-60 matches JPEG 80-85 at a fraction of the size.
			 *
			 * @param int    $quality       Quality, 1-100.
			 * @param int    $attachment_id Attachment ID.
			 * @param string $size          Image size key.
			 */
			$quality = apply_filters( 'imgx_avif_quality', $quality, $context['attachment_id'], $context['size'] );
		} else {
			/**
			 * Filters the WebP encoder quality.
			 *
			 * @param int    $quality       Quality, 1-100.
			 * @param int    $attachment_id Attachment ID.
			 * @param string $size          Image size key.
			 */
			$quality = apply_filters( 'imgx_webp_quality', $quality, $context['attachment_id'], $context['size'] );
		}

		return (int) max( 1, min( 100, (int) $quality ) );
	}
}
