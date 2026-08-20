/**
 * Rooktest voor de gebouwde editor-bundel.
 * Draaien met:  npm test
 *
 * Controleert wat er stuk kan gaan zonder dat de build klaagt: dat de bundel
 * de markup uit class-pdk-admin.php herkent, de textarea vervangt én
 * synchroon houdt, en dat de diff-knop een merge-view opent.
 */

import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const bundle = readFileSync( new URL( '../pdk-theme-options/assets/js/editor.bundle.js', import.meta.url ), 'utf8' );

// Dezelfde markup als render_tab_code_editor().
const dom = new JSDOM(
	`<body>
		<button type="button" data-pdk-diff-toggle data-label-on="Vergelijk" data-label-off="Terug" hidden>Vergelijk</button>
		<div class="pdk-editor" data-diff="0">
			<textarea class="pdk-code-editor" name="file_content" data-lang="php">&lt;?php
$a = 1;</textarea>
			<textarea class="pdk-code-baseline" hidden readonly>&lt;?php</textarea>
		</div>
	</body>`,
	{ runScripts: 'outside-only', pretendToBeVisual: true }
);

// jsdom doet geen layout; CodeMirror meet wél. Nul-afmetingen zijn genoeg —
// we testen gedrag, geen pixels.
const zeroRect = () => ( { top: 0, left: 0, bottom: 0, right: 0, width: 0, height: 0 } );
dom.window.Range.prototype.getClientRects = () => [];
dom.window.Range.prototype.getBoundingClientRect = zeroRect;
dom.window.Element.prototype.getClientRects = () => [];

dom.window.eval( bundle );

const doc      = dom.window.document;
const textarea = doc.querySelector( '.pdk-code-editor' );
const toggle   = doc.querySelector( '[data-pdk-diff-toggle]' );

// De editor staat er, de textarea blijft bestaan als drager voor het formulier.
assert.ok( doc.querySelector( '.pdk-editor .cm-editor' ), 'CodeMirror is gemount' );
assert.strictEqual( textarea.style.display, 'none', 'textarea is verborgen' );
assert.ok( doc.querySelector( '.cm-content' ).textContent.includes( '$a = 1;' ), 'inhoud is geladen' );

// Typen in de editor moet in de textarea landen — anders slaat het formulier
// de oude inhoud op.
const view = dom.window.pdkEditors[ 0 ];
view.dispatch( { changes: { from: view.state.doc.length, insert: '\n// erbij' } } );
assert.ok( textarea.value.endsWith( '// erbij' ), 'textarea loopt mee met de editor' );

// Diff-knop: zichtbaar gemaakt door de bundel en opent een merge-view.
assert.strictEqual( toggle.hidden, false, 'diff-knop is zichtbaar bij een aanwezige back-up' );
toggle.dispatchEvent( new dom.window.MouseEvent( 'click', { bubbles: true } ) );
assert.ok( doc.querySelector( '.pdk-diff .cm-mergeView' ), 'merge-view is geopend' );
assert.strictEqual( toggle.textContent, 'Terug', 'knoplabel wisselt' );

toggle.dispatchEvent( new dom.window.MouseEvent( 'click', { bubbles: true } ) );
assert.ok( ! doc.querySelector( '.pdk-diff .cm-mergeView' ), 'merge-view is opgeruimd' );
assert.strictEqual( toggle.textContent, 'Vergelijk', 'knoplabel wisselt terug' );

console.log( 'OK' );
