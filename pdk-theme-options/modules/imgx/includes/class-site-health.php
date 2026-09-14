<?php
/**
 * Site Health integration.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * Adds one status test and a debug information section.
 */
class Site_Health {

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
	 * Constructor.
	 *
	 * @param Settings     $settings     Settings.
	 * @param Capabilities $capabilities Capability detection.
	 */
	public function __construct( Settings $settings, Capabilities $capabilities ) {
		$this->settings     = $settings;
		$this->capabilities = $capabilities;
	}

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'site_status_tests', array( $this, 'register_test' ) );
		add_filter( 'debug_information', array( $this, 'debug_information' ) );
	}

	/**
	 * Adds the IMGX encoder test.
	 *
	 * @param array $tests Registered tests.
	 * @return array
	 */
	public function register_test( $tests ) {
		$tests['direct']['imgx_encoders'] = array(
			'label' => __( 'Ondersteuning voor moderne afbeeldingsformaten', 'pdk-theme-options' ),
			'test'  => array( $this, 'run_test' ),
		);

		return $tests;
	}

	/**
	 * Runs the encoder test.
	 *
	 * @return array
	 */
	public function run_test() {
		$requested = $this->settings->requested_formats();
		$missing   = array();

		foreach ( $requested as $format ) {
			if ( ! $this->capabilities->supports( $format ) ) {
				$missing[] = $format;
			}
		}

		$available = array_diff( $requested, $missing );

		$description = $available
			? sprintf(
				/* translators: 1: list of supported formats, 2: image editor name. */
				esc_html__( 'IMGX kan %1$s coderen met %2$s.', 'pdk-theme-options' ),
				esc_html( strtoupper( implode( ', ', $available ) ) ),
				esc_html( $this->capabilities->editor_name() )
			)
			: sprintf(
				/* translators: %s: image editor name. */
				esc_html__( 'IMGX kan geen van de gekozen formaten coderen met %s, er worden dus geen afbeeldingen gegenereerd.', 'pdk-theme-options' ),
				esc_html( $this->capabilities->editor_name() )
			);

		$result = array(
			'label'       => __( 'IMGX kan moderne afbeeldingsformaten genereren', 'pdk-theme-options' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Prestaties', 'pdk-theme-options' ),
				'color' => 'blue',
			),
			'description' => '<p>' . $description . '</p>',
			'actions'     => '',
			'test'        => 'imgx_encoders',
		);

		if ( count( $missing ) === count( $requested ) ) {
			$result['status'] = 'recommended';
			$result['label']  = __( 'IMGX kan geen enkel modern afbeeldingsformaat genereren', 'pdk-theme-options' );
		} elseif ( $missing ) {
			$result['status'] = 'recommended';
			$result['label']  = __( 'IMGX kan maar een deel van de gekozen formaten genereren', 'pdk-theme-options' );
		}

		if ( $missing ) {
			$reasons = '';

			foreach ( $missing as $format ) {
				$reasons .= '<li><strong>' . esc_html( strtoupper( $format ) ) . '</strong>: '
					. esc_html( $this->capabilities->reason( $format ) ) . '</li>';
			}

			$result['description'] .= '<ul>' . $reasons . '</ul>';
			$result['actions']      = sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( \PDK_ImgX::tab_url() ),
				esc_html__( 'IMGX-instellingen openen', 'pdk-theme-options' )
			);
		}

		return $result;
	}

	/**
	 * Adds an IMGX section to Site Health -> Info.
	 *
	 * @param array $info Debug information.
	 * @return array
	 */
	public function debug_information( $info ) {
		$report = $this->capabilities->get();

		$info['imgx'] = array(
			'label'  => __( 'IMGX', 'pdk-theme-options' ),
			'fields' => array(
				'version'      => array(
					'label' => __( 'Versie', 'pdk-theme-options' ),
					'value' => IMGX_VERSION,
				),
				'webp'         => array(
					'label' => __( 'WebP-codering', 'pdk-theme-options' ),
					'value' => ! empty( $report['webp'] ) ? __( 'beschikbaar', 'pdk-theme-options' ) : __( 'niet beschikbaar', 'pdk-theme-options' ),
				),
				'avif'         => array(
					'label' => __( 'AVIF-codering', 'pdk-theme-options' ),
					'value' => ! empty( $report['avif'] ) ? __( 'beschikbaar', 'pdk-theme-options' ) : __( 'niet beschikbaar', 'pdk-theme-options' ),
				),
				'editor'       => array(
					'label' => __( 'Afbeeldingsbewerker', 'pdk-theme-options' ),
					'value' => $this->capabilities->editor_name(),
				),
				'mode'         => array(
					'label' => __( 'Generatiemodus', 'pdk-theme-options' ),
					'value' => (string) $this->settings->get( 'mode' ),
				),
				'picture'      => array(
					'label' => __( 'Picture-vervanging', 'pdk-theme-options' ),
					'value' => $this->settings->get( 'picture_enabled' ) ? __( 'aan', 'pdk-theme-options' ) : __( 'uit', 'pdk-theme-options' ),
				),
				'webp_quality' => array(
					'label' => __( 'WebP-kwaliteit', 'pdk-theme-options' ),
					'value' => (string) $this->settings->get( 'webp_quality' ),
				),
				'avif_quality' => array(
					'label' => __( 'AVIF-kwaliteit', 'pdk-theme-options' ),
					'value' => (string) $this->settings->get( 'avif_quality' ),
				),
			),
		);

		return $info;
	}
}
