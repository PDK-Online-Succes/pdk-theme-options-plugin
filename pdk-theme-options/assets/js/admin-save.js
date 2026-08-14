/**
 * PDK Theme Options — Ctrl+S / Cmd+S opslaan in de hele WordPress-admin.
 *
 * Werkt op de klassieke editor (berichten, pagina's, WooCommerce-producten),
 * op instellingenpagina's en op de PDK Tools-tabs. De blok-editor bindt Ctrl+S
 * zelf al, daar doet dit script bewust niets.
 *
 * Volgorde is opzettelijk: "Concept opslaan" gaat vóór "Publiceren", zodat
 * Ctrl+S op een concept nooit per ongeluk publiceert.
 */
(function () {
	'use strict';

	function findSaveButton() {
		return (
			document.querySelector('#save-post') ||            // concept opslaan
			document.querySelector('#publish') ||              // bijwerken (al gepubliceerd)
			document.querySelector('#submit') ||               // WP-instellingenpagina's
			document.querySelector('.wrap form input[type="submit"].button-primary') ||
			document.querySelector('.wrap form button[type="submit"].button-primary')
		);
	}

	document.addEventListener('keydown', function (e) {
		if (!(e.ctrlKey || e.metaKey) || e.altKey) return;
		if (!e.key || e.key.toLowerCase() !== 's') return;

		// Blok-editor heeft een eigen Ctrl+S — niet dubbel opslaan.
		if (document.querySelector('.block-editor')) return;

		var button = findSaveButton();
		if (!button || button.disabled) return;

		e.preventDefault();

		// Focus weghalen zodat de laatste toetsaanslag in een veld meegaat
		// (TinyMCE/CodeMirror synchroniseren op blur).
		if (document.activeElement && document.activeElement.blur) {
			document.activeElement.blur();
		}

		button.click();
	});
}());
