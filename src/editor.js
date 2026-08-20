/**
 * PDK Theme Options — code-editor (CodeMirror 6).
 *
 * Bewust ZONDER linters: de editor kleurt alleen, hij keurt niet af. Nieuwe
 * syntax (CSS Nesting, PHP 8.5, ES2026) levert dus nooit een valse foutmelding.
 * De echte PHP-syntaxcontrole gebeurt server-side bij het opslaan met
 * token_get_all() — die kent per definitie de PHP-versie van de site zelf.
 *
 * Bouwen:  npm run build   (uitvoer: pdk-theme-options/assets/js/editor.bundle.js)
 * Updaten: npm run update
 */

import { EditorView, basicSetup } from 'codemirror';
import { EditorState } from '@codemirror/state';
import { keymap } from '@codemirror/view';
import { indentUnit } from '@codemirror/language';
import { indentWithTab } from '@codemirror/commands';
import { php } from '@codemirror/lang-php';
import { css } from '@codemirror/lang-css';
import { javascript } from '@codemirror/lang-javascript';
import { MergeView } from '@codemirror/merge';
import { oneDark } from '@codemirror/theme-one-dark';

const LANGUAGES = {
	php: () => php(),
	css: () => css(),
	javascript: () => javascript(),
};

const theme = EditorView.theme({
	'&': { height: '70vh', fontSize: '13px', border: '1px solid #8c8f94', borderRadius: '3px' },
	'&.cm-focused': { outline: 'none', borderColor: '#2271b1', boxShadow: '0 0 0 1px #2271b1' },
	'.cm-scroller': { fontFamily: '"SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace', lineHeight: '1.6' },
});

function baseExtensions( lang ) {
	return [
		basicSetup,
		keymap.of( [ indentWithTab ] ),
		indentUnit.of( '\t' ),
		oneDark,
		theme,
		( LANGUAGES[ lang ] || LANGUAGES.php )(),
	];
}

/**
 * Eén tab = één textarea. De textarea blijft bestaan en wordt bij elke
 * wijziging bijgewerkt, zodat het gewone formulier (en Ctrl+S) blijft werken
 * en de editor zonder JavaScript terugvalt op een simpel tekstveld.
 */
function initEditor( wrap ) {
	const textarea = wrap.querySelector( '.pdk-code-editor' );
	const baseline = wrap.querySelector( '.pdk-code-baseline' );
	const toggle   = document.querySelector( '[data-pdk-diff-toggle]' );

	if ( ! textarea ) {
		return;
	}

	const host = document.createElement( 'div' );
	wrap.insertBefore( host, textarea );
	textarea.style.display = 'none';

	const view = new EditorView( {
		parent: host,
		state: EditorState.create( {
			doc: textarea.value,
			extensions: [
				baseExtensions( textarea.dataset.lang ),
				EditorView.updateListener.of( ( update ) => {
					if ( update.docChanged ) {
						textarea.value = update.state.doc.toString();
					}
				} ),
			],
		} ),
	} );

	// Handle voor de rooktest en voor debuggen vanuit de console.
	( window.pdkEditors = window.pdkEditors || [] ).push( view );

	if ( ! toggle || ! baseline ) {
		return;
	}

	// Diff-weergave: laatst via de editor opgeslagen versie (.bak) links,
	// de huidige inhoud rechts. Alleen lezen — bewerken doe je in de editor.
	const diffHost = document.createElement( 'div' );
	diffHost.className = 'pdk-diff';
	diffHost.style.display = 'none';
	wrap.insertBefore( diffHost, host );

	let merge = null;

	const showDiff = ( on ) => {
		if ( on && ! merge ) {
			merge = new MergeView( {
				parent: diffHost,
				orientation: 'a-b',
				highlightChanges: true,
				gutter: true,
				collapseUnchanged: { margin: 3, minSize: 6 },
				a: {
					doc: baseline.value,
					extensions: [ baseExtensions( textarea.dataset.lang ), EditorState.readOnly.of( true ) ],
				},
				b: {
					doc: view.state.doc.toString(),
					extensions: [ baseExtensions( textarea.dataset.lang ), EditorState.readOnly.of( true ) ],
				},
			} );
		} else if ( ! on && merge ) {
			merge.destroy();
			merge = null;
		}

		diffHost.style.display = on ? '' : 'none';
		host.style.display     = on ? 'none' : '';
		toggle.textContent     = on ? toggle.dataset.labelOff : toggle.dataset.labelOn;
	};

	toggle.hidden = false;
	toggle.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		showDiff( diffHost.style.display === 'none' );
	} );

	if ( wrap.dataset.diff === '1' ) {
		showDiff( true );
	}
}

document.querySelectorAll( '.pdk-editor' ).forEach( initEditor );
