/* PDK Theme Options — Admin JavaScript */
/* global pdkAdmin, wp */

(function ($) {
	'use strict';

	// Sub-tabs binnen een tab (bv. Site Instellingen). Alle secties staan in
	// hetzelfde formulier — dit toont er één, opslaan bewaart ze allemaal.
	var SUBTAB_KEY = 'pdkActiveSubtab';

	function showSubtab(slug) {
		var $tabs = $('.pdk-subtabs .nav-tab');
		if (!$tabs.length) return;

		if (!slug || !$tabs.filter('[data-subtab="' + slug + '"]').length) {
			slug = $tabs.first().data('subtab');
		}

		$tabs.removeClass('nav-tab-active').filter('[data-subtab="' + slug + '"]').addClass('nav-tab-active');
		$('.pdk-subtab').hide().filter('#' + slug).show();

		try {
			window.sessionStorage.setItem(SUBTAB_KEY, slug);
		} catch (e) {
			// Privémodus zonder sessionStorage: tab werkt, onthouden niet.
		}
	}

	$(document).on('click', '.pdk-subtabs .nav-tab', function (e) {
		e.preventDefault();
		showSubtab($(this).data('subtab'));
	});

	if ($('.pdk-subtabs').length) {
		var stored = null;
		try {
			stored = window.sessionStorage.getItem(SUBTAB_KEY);
		} catch (e) {}
		// Hash wint van de onthouden keuze (deelbare link naar een sectie).
		showSubtab(window.location.hash.replace('#', '') || stored);
	}

	// Bevestiging bij verwijderacties.
	$(document).on('submit', 'form:has(input[name="pdk_lang_action"])', function (e) {
		var action = $(this).find('input[name="pdk_lang_action"]').val();
		if (action === 'remove_orphaned' || action === 'remove_language') {
			if (!window.confirm('Weet je zeker dat je deze bestanden wilt verwijderen?')) {
				e.preventDefault();
			}
		}
	});

	// De code-editor zelf zit in editor.bundle.js (CodeMirror 6); die vervangt
	// de textarea en houdt hem gesynchroniseerd, zodat opslaan blijft werken.

	// Mediabibliotheek-kiezer voor URL-velden (favicon, logo). Het veld blijft
	// een URL — thema-helpers en de favicon-output veranderen dus niet.
	var frame = null;

	$(document).on('click', '.pdk-media-pick', function (e) {
		e.preventDefault();

		var $field = $(this).closest('.pdk-media-field');

		// Eén frame hergebruiken, maar wél per veld opnieuw bedraden.
		if (frame) frame.off('select');
		if (!frame) {
			frame = wp.media({
				title: pdkAdmin.mediaTitle,
				button: { text: pdkAdmin.mediaButton },
				multiple: false
			});
		}

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var url = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;

			$field.find('input[type="url"]').val(attachment.url);
			$field.find('.pdk-media-preview').attr('src', url).show();
		});

		frame.open();
	});

	$(document).on('click', '.pdk-media-clear', function (e) {
		e.preventDefault();

		var $field = $(this).closest('.pdk-media-field');
		$field.find('input[type="url"]').val('');
		$field.find('.pdk-media-preview').removeAttr('src').hide();
	});

	// Toon "opgeslagen" bericht na redirect met ?saved=1.
	if (window.location.search.indexOf('saved=1') > -1) {
		var $notice = $('<div class="notice notice-success is-dismissible"><p>' + (pdkAdmin ? pdkAdmin.savedText : 'Opgeslagen!') + '</p></div>');
		$('.pdk-wrap h1').after($notice);
	}

}(jQuery));
