<?php
/**
 * Module: Custom Login Page — vaste PDK-huisstijl
 *
 * Past de WordPress-loginpagina aan met de vaste PDK Online Succes huisstijl.
 * Er zijn geen aanpasbare instellingen: logo, kleuren en achtergrond zijn vaste
 * assets uit de plugin. De module kan aan/uit worden gezet via de Modules-tab.
 *
 * Assets (meegekomen met de plugin, niet te verwijderen bij updates):
 *  assets/img/logo-pdk-online-succes.svg — SVG-logo
 *  assets/img/login-background.png       — achtergrondfoto
 */

defined( 'ABSPATH' ) || exit;

class PDK_Login_Page {

	public function __construct( PDK_Loader $loader ) {
		$loader->add_action( 'login_head',        $this, 'output_inline_css' );
		$loader->add_filter( 'login_headerurl',   $this, 'logo_url' );
		$loader->add_filter( 'login_headertext',  $this, 'logo_title' );
	}

	public function output_inline_css(): void {
		$logo_url = esc_url( PDK_PLUGIN_URL . 'assets/img/logo-pdk-online-succes.svg' );
		$bg_url   = esc_url( PDK_PLUGIN_URL . 'assets/img/login-background.png' );
		?>
		<style id="pdk-login-inline">
		body.login {
			background-image: url('<?php echo $bg_url; ?>');
			background-size: cover;
			background-position: center center;
			background-repeat: no-repeat;
			background-attachment: fixed;
		}

		body.login h1 a {
			background-image: none, url('<?php echo $logo_url; ?>');
			background-size: contain;
			background-repeat: no-repeat;
			background-position: center;
			width: 300px;
			height: 90px;
			display: block;
		}

		#login {
			padding: 10% 0 0;
		}

		#login form {
			border-color: #d65c00;
			border-radius: 6px;
			box-shadow: 0 4px 20px rgba(0, 0, 0, .2);
		}

		.login label {
			font-size: 13px;
		}

		input[type="text"]:focus,
		input[type="password"]:focus,
		input[type="email"]:focus {
			border-color: #d65c00;
			box-shadow: 0 0 0 1px #d65c00;
		}

		.wp-core-ui .button-primary {
			background: #d65c00;
			border-color: #b54e00 #b54e00 #8c3d00;
			font-weight: 600;
			text-shadow: none;
			box-shadow: none;
			border-radius: 4px;
		}

		.wp-core-ui .button-primary:hover,
		.wp-core-ui .button-primary:focus {
			background: #b54e00;
			border-color: #8c3d00;
			box-shadow: 0 0 0 1px #d65c00;
		}

		.login #nav a,
		.login #backtoblog a {
			color: #2a2070;
			text-shadow: 0 0 8px rgba(255,255,255,.9), 0 0 4px rgba(255,255,255,.7);
			text-decoration: none;
			font-weight: 600;
		}

		.login #nav a:hover,
		.login #backtoblog a:hover {
			color: #d65c00;
			text-shadow: 0 0 8px rgba(255,255,255,.9);
		}

		input[type="checkbox"]:checked::before {
			content: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath d='M14.83 4.89l1.34.94-5.81 8.38H9.02L5.78 9.67l1.34-1.25 2.57 2.4z' fill='%23d65c00'/%3E%3C/svg%3E");
		}

		.login .privacy-policy-page-link a {
			color: #fff;
			text-shadow: 0 1px 2px rgba(0,0,0,.5);
		}

		@media screen and (max-height: 550px) {
			#login { padding: 20px 0; }
		}
		</style>
		<?php
	}

	public function logo_url(): string {
		return 'https://pdk.nl';
	}

	public function logo_title(): string {
		return 'Mogelijk gemaakt door PDK Online Succes';
	}
}
