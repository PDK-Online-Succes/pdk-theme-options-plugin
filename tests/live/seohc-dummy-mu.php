<?php
/**
 * Dev-hulp voor de SEO Health Check-tests (brief §5). Alleen voor dev.local:
 * kopieer naar wp-content/mu-plugins/seohc-dummy.php. Werkt uitsluitend op posts met de meta `_seohc_dummy`.
 *
 *  - seo_health_check_seo_meta: zet description + custom_description als de post `_seohc_dummy_description` heeft
 *    en de provider (Yoast/Rank Math) zelf geen eigen beschrijving levert.
 *  - pre_http_request: WP_Error voor URL's met `seohc-dummy-s17` (rendered_fetch_failed).
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'seo_health_check_seo_meta',
	static function ( $result, $post ) {
		if ( '' === (string) get_post_meta( $post->ID, '_seohc_dummy', true ) ) {
			return $result;
		}
		// Alleen als terugval: een echte beschrijving van Yoast/Rank Math gaat voor, anders maskeert de hulp de provider.
		$description = (string) get_post_meta( $post->ID, '_seohc_dummy_description', true );
		if ( '' !== $description && empty( $result['custom_description'] ) ) {
			$result['description']        = $description;
			$result['custom_description'] = true;
		}
		return $result;
	},
	10,
	2
);

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( false !== strpos( (string) $url, 'seohc-dummy-s17' ) ) {
			return new WP_Error( 'seohc_dummy_fetch', 'SEOHC dummy: pagina bewust niet bereikbaar.' );
		}
		return $pre;
	},
	10,
	3
);
