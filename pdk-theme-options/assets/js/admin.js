/* PDK Theme Options — Admin JavaScript */
/* global pdkAdmin */

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

	// Code-editor: Tab-toets invoegen als spaties.
	$(document).on('keydown', '.pdk-code-editor', function (e) {
		if (e.key !== 'Tab') return;
		e.preventDefault();

		var start = this.selectionStart;
		var end   = this.selectionEnd;
		var val   = this.value;

		this.value           = val.substring(0, start) + '\t' + val.substring(end);
		this.selectionStart  = start + 1;
		this.selectionEnd    = start + 1;
	});

	// Toon "opgeslagen" bericht na redirect met ?saved=1.
	if (window.location.search.indexOf('saved=1') > -1) {
		var $notice = $('<div class="notice notice-success is-dismissible"><p>' + (pdkAdmin ? pdkAdmin.savedText : 'Opgeslagen!') + '</p></div>');
		$('.pdk-wrap h1').after($notice);
	}

}(jQuery));
