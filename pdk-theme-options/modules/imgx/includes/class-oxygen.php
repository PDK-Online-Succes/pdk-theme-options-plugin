<?php
/**
 * Oxygen and Breakdance integration.
 *
 * @package IMGX
 */

namespace IMGX;

defined( 'ABSPATH' ) || exit;

/**
 * Adds picture markup to pages built with Oxygen, in either of its two generations.
 *
 * Neither generation goes through the hooks IMGX normally uses. Both render their own
 * `<img>` without a `wp-image-<id>` class, never call `wp_get_attachment_image()`, and
 * output the page without ever applying the `the_content` filters — verified by probe on
 * both, where IMGX's `the_content` callback was registered but never invoked.
 *
 * **Oxygen 6 and Breakdance** share one rendering engine. Images come from a Twig macro
 * as `class="oxy-image"` / `class="breakdance-image-object"`, and `the_content` is
 * replaced at priority PHP_INT_MIN and then echoed by the builder's own page template;
 * `simulate_the_content()` re-applies the content filters only when the site enables
 * Oxygen's "Simulate the_content" setting, which is off by default.
 * `breakdance_render_rendered_html` fires once for every document that engine renders —
 * page body, header template, footer template — which makes it the one hook covering the
 * whole page. Oxygen fires it under both the `breakdance_` and the `oxygen_` prefix, so
 * hooking the `breakdance_` name covers both products without doing the work twice.
 *
 * **Oxygen Classic** (4.x) is a different codebase: shortcode components rendered into a
 * `$template_content` global, which `oxygen-main-template.php` echoes directly. Images
 * come out as `class="ct-image"`. The `ct_before_builder` action fires in that template
 * after the content has been built and immediately before it is echoed, so rewriting the
 * global there is precise: it touches the builder's page body and nothing in `wp_head`,
 * `wp_footer` or the admin bar, and needs no output buffering.
 */
class Oxygen {

	/**
	 * Frontend renderer.
	 *
	 * @var Picture_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param Picture_Renderer $renderer Frontend renderer.
	 */
	public function __construct( Picture_Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Registers the integration.
	 *
	 * The filter simply never fires when neither builder is active, so there is nothing
	 * to detect up front.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Oxygen 6 and Breakdance.
		add_filter( 'breakdance_render_rendered_html', array( $this, 'filter_rendered_html' ), 20, 1 );

		// Oxygen Classic 4.x.
		add_action( 'ct_before_builder', array( $this, 'filter_classic_template_content' ), 20 );
	}

	/**
	 * Rewrites Oxygen Classic's rendered page body.
	 *
	 * `oxygen-main-template.php` builds the page into the `$template_content` global and
	 * echoes it a few lines after firing `ct_before_builder`, with no filter in between,
	 * so the global itself is the interception point.
	 *
	 * @return void
	 */
	public function filter_classic_template_content() {
		global $template_content;

		if ( ! is_string( $template_content ) || '' === $template_content ) {
			return;
		}

		if ( false === stripos( $template_content, '<img' ) ) {
			return;
		}

		// SHOW_CT_BUILDER means the builder shell is rendering; it discards the global
		// anyway, and the canvas must show exactly what the designer is editing.
		if ( defined( 'SHOW_CT_BUILDER' ) ) {
			return;
		}

		if ( $this->is_builder_request() || ! $this->renderer->is_active() ) {
			return;
		}

		$template_content = $this->renderer->transform( $template_content, 'oxygen-classic' );
	}

	/**
	 * Wraps eligible images in one rendered builder document.
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	public function filter_rendered_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		if ( false === stripos( $html, '<img' ) ) {
			return $html;
		}

		if ( $this->is_builder_request() ) {
			return $html;
		}

		if ( ! $this->renderer->is_active() ) {
			return $html;
		}

		return $this->renderer->transform( $html, 'oxygen' );
	}

	/**
	 * Whether this request renders inside the builder itself.
	 *
	 * The editor canvas is a frontend request, so the usual is_admin() guard does not
	 * catch it. Rewriting markup there would show the designer something other than what
	 * they are editing.
	 *
	 * @return bool
	 */
	private function is_builder_request() {
		foreach (
			array(
				'\Breakdance\isRequestFromBuilderIframe',
				'\Breakdance\isRequestFromGutenbergIframe',
				'\Breakdance\DesignLibrary\isRequestFromDesignLibraryModal',
			) as $check
		) {
			if ( function_exists( $check ) && call_user_func( $check ) ) {
				return true;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context detection.
		$request = $_GET;

		foreach ( array( 'breakdance', 'breakdance_iframe', 'oxygen_iframe', 'ct_builder', 'ct_inner' ) as $key ) {
			if ( isset( $request[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}
}
