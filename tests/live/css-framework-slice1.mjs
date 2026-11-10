// Live-checks (L-delen) voor Slice 1 van de module CSS Framework op http://dev.local.
// Normatief: docs/css-framework-brief.md (AC-CSS-007, -024, -009, -019, -020, -022, -023, -010 deels).
//
//   node tests/live/css-framework-slice1.mjs            # alle cases
//   node tests/live/css-framework-slice1.mjs 007 020    # alleen die AC's
//
// Voorwaarden: zie tests/live/css-framework-slice1.md (plugin gekopieerd naar mu-plugins, module aan,
// WP-CLI-omgevingsvariabelen). Het script zet Oxygen per case aan/uit via WP-CLI en herstelt de beginstand.
//
// Methode: de testmarkup wordt in de frontend van dev.local in de <body> gezet (document.body.innerHTML).
// De stylesheets uit <head> (Oxygens normalize, global-styles, css-framework.css, custom-style.css) blijven
// dus gewoon van kracht; er hoeft geen Oxygen-pagina gebouwd te worden. Gemeten wordt met getComputedStyle
// en getBoundingClientRect (probe-methode uit de brief: de gutter/flow-ruimte wordt als <i>-probe gemeten).

import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { writeFileSync, readFileSync, existsSync, mkdirSync } from 'node:fs';
import { homedir } from 'node:os';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const require = createRequire( import.meta.url );
// Playwright is niet standaard globaal aanwezig. Volgorde: $PLAYWRIGHT_DIR, globale npm-root, ./node_modules, tests/live/node_modules.
// Installeren (eenmalig, gepind):  npm i --prefix "$TEMP/pw" playwright@1.49.1 && npx --prefix "$TEMP/pw" playwright install chromium
// en daarna:                       export PLAYWRIGHT_DIR="$TEMP/pw/node_modules/playwright"
function loadPlaywright() {
	const here = path.dirname( fileURLToPath( import.meta.url ) );
	const cands = [ process.env.PLAYWRIGHT_DIR, path.join( execSync( 'npm root -g' ).toString().trim(), 'playwright' ), path.resolve( 'node_modules', 'playwright' ), path.join( here, 'node_modules', 'playwright' ) ].filter( Boolean );
	for ( const c of cands ) { try { return require( c ); } catch ( e ) { /* volgende */ } }
	console.error( 'Playwright niet gevonden. Geprobeerd:\n  ' + cands.join( '\n  ' ) + '\nInstalleer: npm i --prefix "$TEMP/pw" playwright@1.49.1 && npx --prefix "$TEMP/pw" playwright install chromium\nZet dan PLAYWRIGHT_DIR="$TEMP/pw/node_modules/playwright".' );
	process.exit( 2 );
}
const { chromium } = loadPlaywright();

const BASE   = process.env.DEV_URL || 'http://dev.local';
const SITE   = process.env.DEV_PATH || path.join( homedir(), 'Local Sites', 'dev', 'app', 'public' );
const PHP    = process.env.LOCAL_PHP;      // .../lightning-services/php-8.5.12+0/bin/win64/php.exe
const INI    = process.env.LOCAL_PHP_INI;  // .../Local/run/3APS_fKWy/conf/php/php.ini
const WPCLI  = process.env.WPCLI || 'C:\\Program Files (x86)\\Local\\resources\\extraResources\\bin\\wp-cli\\wp-cli.phar';
const TOL    = 0.05;                       // px-tolerantie uit de brief
const STORE  = path.join( SITE, 'wp-content', 'uploads', 'pdk-theme-options' );

if ( ! PHP || ! INI ) { console.error( 'Zet LOCAL_PHP en LOCAL_PHP_INI (zie het .md-bestand).' ); process.exit( 2 ); }

// Local's php.ini print PHP-startupwaarschuwingen (bv. php_imagick.dll) op stdout: eruit filteren, anders is elke vergelijking fout.
const NOISE = /^(PHP )?(Warning|Notice|Deprecated)\b.*$/i;
const wp = ( ...args ) => execFileSync( PHP, [ '-c', INI, WPCLI, `--path=${ SITE }`, ...args ], { encoding: 'utf8' } ).split( /\r?\n/ ).filter( ( l ) => ! NOISE.test( l.trim() ) ).join( '\n' ).trim();
const wpEval = ( code ) => wp( 'eval', code );
const oxygenActive = () => wp( 'plugin', 'list', '--name=oxygen', '--field=status' ) === 'active';
const setOxygen = ( on ) => { if ( on !== oxygenActive() ) { wp( 'plugin', on ? 'activate' : 'deactivate', 'oxygen' ); } };
const setModernNormalize = ( on ) => wpEval( `\\Breakdance\\Settings\\set_setting("modern_normalize_enabled", ${ on ? 'true' : 'false' });` );
const getModernNormalize = () => wpEval( 'echo \\Breakdance\\Settings\\get_bool_setting("modern_normalize_enabled") ? "true" : "false";' );
const moduleOn = ( on ) => wpEval( `$o=get_option("pdk_theme_options",[]); $o["css_framework"]["enabled"]=${ on ? 'true' : 'false' }; update_option("pdk_theme_options",$o);` );
const cssFile = path.join( STORE, 'custom-style.css' );

// ---- config-veilig: de opgeslagen css_framework-config (bijv. font_min = 16 op dev.local) blijft behouden -------
// Begin: bewaar de volledige opgeslagen config, zet via PDK_CSS_Framework::save() tijdelijk de defaults (bestand en Oxygen-sync
// gaan mee). Einde (finally): zet de originele config via save() terug. Mislukt dat, dan volgt een duidelijke foutmelding met de JSON.
const cfgGuard = { orig: null };
const saveResult = ( r ) => { try { return JSON.parse( r ); } catch ( e ) { return { saved: false, errors: { parse: r } }; } };
function cfgDefaults() {
	cfgGuard.orig = wpEval( '$o=get_option("pdk_theme_options",[]); echo wp_json_encode( $o["css_framework"] ?? [] );' );
	console.log( `config: opgeslagen css_framework-config bewaard: ${ cfgGuard.orig }` );
	const r = saveResult( wpEval( '$d=PDK_Settings::get_defaults()["css_framework"]; unset($d["enabled"]); $r=PDK_CSS_Framework::save($d); echo wp_json_encode(["saved"=>$r["saved"],"errors"=>$r["errors"]]);' ) );
	console.log( `config: defaults tijdelijk ingesteld via save() (bestand + Oxygen-sync): saved=${ r.saved }` );
	if ( ! r.saved ) { throw new Error( 'defaults instellen mislukt: ' + JSON.stringify( r.errors ) ); }
}
function cfgRestore() {
	if ( cfgGuard.orig === null ) { return; }
	const b64 = Buffer.from( cfgGuard.orig ).toString( 'base64' );
	try {
		const r = saveResult( wpEval( `$c=json_decode(base64_decode("${ b64 }"),true); unset($c["enabled"]); $r=PDK_CSS_Framework::save((array)$c); echo wp_json_encode(["saved"=>$r["saved"],"errors"=>$r["errors"]]);` ) );
		if ( r.saved ) { console.log( 'config: originele css_framework-config teruggezet via save()' ); return; }
		console.error( `FOUT: originele config NIET teruggezet (${ JSON.stringify( r.errors ) }). Originele opgeslagen config, handmatig herstellen:\n${ cfgGuard.orig }` );
	} catch ( e ) {
		console.error( `FOUT: terugzetten van de originele config mislukte (${ e.message }). Originele opgeslagen config, handmatig herstellen:\n${ cfgGuard.orig }` );
	}
	process.exitCode = 1;
}


// ---- resultaten -------------------------------------------------------------
const results = [];
const check = ( ac, label, ok, detail = '' ) => { results.push( { ac, label, ok } ); console.log( `${ ok ? 'ok  ' : 'FOUT' } AC-CSS-${ ac } ${ label }${ ok ? '' : '  -> ' + detail }` ); };
const near = ( a, b ) => Math.abs( a - b ) <= TOL;
const OXYGEN_OFF = process.env.PDK_LIVE_OXYGEN_OFF === '1';
const wanted = process.argv.slice( 2 );
const run = ( ac ) => wanted.length === 0 || wanted.includes( ac );

// ---- testmarkup -------------------------------------------------------------
const LOREM = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
// AC-CSS-007: probes VÓÓR de section (zelfde context als de sporen); de section is zelf het grid (V10).
// #ch en #m staan (zoals in de brief) in een overflow:hidden-wrapper binnen de section, dus op de basis-font-size van de section (V12). Extra: #lt (losse tekst).
const MARKUP_007 = `
<i id="g" style="display:block;inline-size:var(--pdk-gutter)"></i><i id="ps" style="display:block;inline-size:var(--pdk-popout-space)"></i>
<p id="p0">${ LOREM }</p>
<section id="s">
  <div id="d"><p id="p1">${ LOREM }</p>
    <div class="pdk-container" style="inline-size:800px"><p id="p2">${ LOREM }</p></div>
    <div class="pdk-grid" id="gr"><p>1</p><p>2</p><p>3</p><p>4</p></div>
  </div>
  <div id="c" class="pdk-content"></div><div id="po" class="pdk-popout"></div><div id="pst" class="pdk-popout-start"></div>
  <div id="wst" class="pdk-wide-start"></div><div id="wen" class="pdk-wide-end"></div><div id="f" class="pdk-full"></div>
  <div id="fst" class="pdk-full-start"></div><div id="fen" class="pdk-full-end"></div>
  <div id="mp" style="overflow:hidden"><i id="ch" style="display:block;font-style:normal;inline-size:1ch"></i><i id="m" style="display:block;font-style:normal;inline-size:var(--pdk-measure)"></i></div>
  <div id="lt" class="pdk-content">losse tekst</div>
  <div style="position:fixed;inset-block-end:0;inset-inline-start:0;inline-size:10px;block-size:10px" id="fx"></div>
</section>
<section class="pdk-off"><div id="o"><p>${ LOREM }</p></div></section>`;

// Eigen pagina voor 320px: GEEN vaste 800px-container, anders meet scrollWidth die in plaats van het framework.
const MARKUP_320 = `<section><div><p>${ LOREM }</p><div class="pdk-grid"><p>1</p><p>2</p><p>3</p><p>4</p></div></div><p class="pdk-popout">p</p><p class="pdk-full-start">f</p><p class="pdk-wide-end">w</p></section>`;

const MARKUP_020 = `<section><div><h1 id="h1">Kop</h1><p id="p">Tekst</p><h6 id="h6">Kopje</h6></div></section>`;

const MARKUP_022 = `
<i id="g" style="display:block;inline-size:var(--pdk-gutter)"></i><span id="m" style="display:block;inline-size:var(--pdk-measure)"></span>
<section id="s"><i id="fs" style="display:block;inline-size:var(--pdk-flow-space)"></i><i id="fh" style="display:block;inline-size:var(--pdk-flow-heading-space)"></i>
  <div id="d"><p id="a">${ LOREM }</p><p id="b">${ LOREM }</p><h2 id="h">Tussenkop</h2><ul id="u"><li>een</li></ul></div>
  <p id="x">${ LOREM }</p><p id="y" class="pdk-content">${ LOREM }</p></section>
<div class="pdk-grid" id="gr"><p>1</p><p>2</p><p>3</p></div>
<div class="pdk-flow-off" style="display:flex;flex-direction:column;gap:10px"><p>x</p><p id="c">y</p></div>`;

const MARKUP_019 = `<div class="woocommerce" id="wc" style="font-size:31px"><section id="ws"><div id="wd">${ LOREM }</div></section></div><section id="plain"><div>${ LOREM }</div></section>`;

async function open( browser, width, markup, url = BASE + '/' ) {
	const ctx  = await browser.newContext( { viewport: { width, height: 900 } } );
	const page = await ctx.newPage();
	await page.goto( url, { waitUntil: 'load' } );
	if ( markup ) { await page.evaluate( ( m ) => { document.body.innerHTML = m; }, markup ); }
	return page;
}
const css = ( page, sel, prop ) => page.evaluate( ( [ s, p ] ) => getComputedStyle( document.querySelector( s ) )[ p ], [ sel, prop ] );
const px  = async ( page, sel, prop ) => parseFloat( await css( page, sel, prop ) );
const rect = ( page, sel ) => page.evaluate( ( s ) => { const r = document.querySelector( s ).getBoundingClientRect(); return { left: r.left, right: r.right, width: r.width, top: r.top }; }, sel );

const NEAR = 0.5;   // breedtes uit probes: ±0,5px (brief §7)
const nearW = ( a, b ) => Math.abs( a - b ) <= NEAR;

/** De banen van de section (V10) en hun varianten. `rtl`: AC-CSS-024 (start/end omgedraaid, breedtes gelijk). */
async function baan( page, ac, w, rtl = false ) {
	const t = rtl ? 'RTL' : 'LTR';
	const L = ( r ) => ( rtl ? r.right : r.left );     // start-rand
	const R = ( r ) => ( rtl ? r.left : r.right );     // eind-rand
	const [ s, g, ps, m, d, c, po, pst, wst, wen, f, fst, fen, ch ] = await Promise.all( [ '#s', '#g', '#ps', '#m', '#d', '#c', '#po', '#pst', '#wst', '#wen', '#f', '#fst', '#fen', '#ch' ].map( ( x ) => rect( page, x ) ) );
	const tag = `[${ t } @${ w }]`;
	const ctr = ( r, lbl ) => check( ac, `${ tag } ${ lbl } gecentreerd in #s (${ ( r.left - s.left ).toFixed( 2 ) } / ${ ( s.right - r.right ).toFixed( 2 ) })`, nearW( r.left - s.left, s.right - r.right ) );
	const sec = await page.evaluate( () => { const c = getComputedStyle( document.querySelector( '#s' ) ); return { display: c.display, ct: c.containerType, pad: [ c.paddingInlineStart, c.paddingInlineEnd, c.paddingBlockStart, c.paddingBlockEnd, c.marginInlineStart, c.marginInlineEnd ] }; } );
	check( ac, `${ tag } #s: display grid, container-type inline-size, geen padding of marge (${ sec.display }, ${ sec.ct }, ${ sec.pad })`, sec.display === 'grid' && sec.ct === 'inline-size' && sec.pad.every( ( v ) => parseFloat( v ) === 0 ) );
	const wide = Math.min( s.width - 2 * g.width, 1440 );
	if ( w >= 1440 + 2 * g.width ) {
		check( ac, `${ tag } wide: w(#d) = min(w(#s) − 2·w(#g), 1440) = ${ wide.toFixed( 2 ) } (${ d.width.toFixed( 2 ) })`, nearW( d.width, wide ) );
		ctr( d, '#d' );
		check( ac, `${ tag } V12: w(#m) = 65 × w(#ch) ±1px (${ m.width.toFixed( 2 ) } vs ${ ( 65 * ch.width ).toFixed( 2 ) })`, Math.abs( m.width - 65 * ch.width ) <= 1 );
		const content = Math.min( m.width, s.width - 2 * g.width );
		check( ac, `${ tag } content: w(#c) = min(w(#m), w(#s) − 2·w(#g)) = ${ content.toFixed( 2 ) } (${ c.width.toFixed( 2 ) })`, nearW( c.width, content ) );
		check( ac, `${ tag } V12: w(#c) = 65 × w(1ch-probe in de section) ±1px (${ c.width.toFixed( 2 ) } vs ${ ( 65 * ch.width ).toFixed( 2 ) })`, Math.abs( c.width - 65 * ch.width ) <= 1 );
		ctr( c, '#c' );
		check( ac, `${ tag } popout: w(#po) = w(#c) + 2·w(#ps) = ${ ( c.width + 2 * ps.width ).toFixed( 2 ) } (${ po.width.toFixed( 2 ) })`, nearW( po.width, c.width + 2 * ps.width ) );
		ctr( po, '#po' );
		check( ac, `${ tag } #pst loopt van start(#po) tot eind(#c)`, nearW( L( pst ), L( po ) ) && nearW( R( pst ), R( c ) ) );
		check( ac, `${ tag } #wst loopt van start(#d) tot eind(#c); #wen van start(#c) tot eind(#d)`, nearW( L( wst ), L( d ) ) && nearW( R( wst ), R( c ) ) && nearW( L( wen ), L( c ) ) && nearW( R( wen ), R( d ) ) );
		check( ac, `${ tag } full: w(#f) = w(#s); #fst van start(#s) tot eind(#d); #fen van start(#d) tot eind(#s)`, nearW( f.width, s.width ) && nearW( L( fst ), L( s ) ) && nearW( R( fst ), R( d ) ) && nearW( L( fen ), L( d ) ) && nearW( R( fen ), R( s ) ) );
		if ( rtl ) {
			check( ac, `[RTL] #fst staat aan de RECHTER rand: R(#fst) = R(#s), L(#fst) = L(#d) (${ fst.right.toFixed( 2 ) } / ${ s.right.toFixed( 2 ) }; ${ fst.left.toFixed( 2 ) } / ${ d.left.toFixed( 2 ) })`, nearW( fst.right, s.right ) && nearW( fst.left, d.left ) );
			check( ac, '[RTL] #fen staat aan de LINKER rand: L(#fen) = L(#s), R(#fen) = R(#d)', nearW( fen.left, s.left ) && nearW( fen.right, d.right ) );
			check( ac, '[RTL] #wst loopt van R(#d) tot L(#c); #pst van R(#po) tot L(#c) (start-kant is rechts)', nearW( wst.right, d.right ) && nearW( wst.left, c.left ) && nearW( pst.right, po.right ) && nearW( pst.left, c.left ) );
		}
	} else {
		const smal = s.width - 2 * g.width;
		check( ac, `${ tag } smal: w(#c) = w(#po) = w(#d) = w(#s) − 2·w(#g) = ${ smal.toFixed( 2 ) } (${ c.width.toFixed( 2 ) }, ${ po.width.toFixed( 2 ) }, ${ d.width.toFixed( 2 ) })`, nearW( c.width, smal ) && nearW( po.width, smal ) && nearW( d.width, smal ) );
		check( ac, `${ tag } smal: w(#f) = w(#s) (${ f.width.toFixed( 2 ) })`, nearW( f.width, s.width ) );
	}
	return { d, c, po, f, m, ch };
}

// ---- AC-CSS-007 (Oxygen INACTIEF, standaardthema, <html dir="ltr">) --------------------
const rootFont = ( page ) => page.evaluate( () => parseFloat( getComputedStyle( document.documentElement ).fontSize ) );
async function ac007( browser ) {
	const mWidth = {};
	for ( const w of [ 400, 1600 ] ) {
		const page = await open( browser, w, MARKUP_007 );
		check( '007', `root-font-size 16px`, near( await rootFont( page ), 16 ) );
		const p1 = await px( page, '#p1', 'fontSize' ), p0 = await px( page, '#p0', 'fontSize' ), p2 = await px( page, '#p2', 'fontSize' ), lt = await px( page, '#lt', 'fontSize' );
		if ( w === 400 ) {
			check( '007', `type @400: #p1 en #p0 = 18px (${ p1 }, ${ p0 })`, near( p1, 18 ) && near( p0, 18 ) );
			check( '007', `V12 @400: losse tekst in de section (#lt) = 18px (${ lt })`, near( lt, 18 ) );
		} else {
			check( '007', `type @1600: #p1 en #p0 = 20px (${ p1 }, ${ p0 })`, near( p1, 20 ) && near( p0, 20 ) );
			check( '007', `type @1600: #p2 = 18,769px volgt de 800px-container, niet de viewport (${ p2 })`, Math.abs( p2 - 18.769 ) <= 0.05 );
			check( '007', `V12 @1600: losse tekst in de section (#lt) = 20px (${ lt })`, near( lt, 20 ) );
		}
		const fsS = await px( page, '#s', 'fontSize' ), fsCh = await px( page, '#ch', 'fontSize' );
		check( '007', `V12 @${ w }: font-size van #s en #ch = ${ w === 400 ? 18 : 20 }px (${ fsS }, ${ fsCh })`, near( fsS, w === 400 ? 18 : 20 ) && near( fsCh, w === 400 ? 18 : 20 ) );
		const b = await baan( page, '007', w );
		mWidth[ w ] = b.m.width;
		const off = await css( page, 'section.pdk-off', 'containerType' ), offd = await css( page, 'section.pdk-off', 'display' );
		const so = await rect( page, 'section.pdk-off' ), o = await rect( page, '#o' );
		const fsOff = await px( page, 'section.pdk-off', 'fontSize' ), fsBody = await px( page, 'body', 'fontSize' );
		check( '007', `opt-out @${ w }: font-size van .pdk-off = body (${ fsOff } vs ${ fsBody })`, near( fsOff, fsBody ) );
		check( '007', `opt-out @${ w }: .pdk-off display block, container-type normal, w(#o) = w(section) (${ offd }, ${ off }, ${ o.width }/${ so.width })`, offd === 'block' && off === 'normal' && nearW( o.width, so.width ) );
		const cols = await page.evaluate( () => getComputedStyle( document.querySelector( '#gr' ) ).gridTemplateColumns.split( ' ' ).filter( ( t ) => parseFloat( t ) > 0.5 ).length ); // ingeklapte auto-fit-sporen (0px) tellen niet mee
		// V14: --pdk-grid-min is in ch (default 30ch). Verwacht aantal = min(4 items, max(1, floor((breedte + gap) / (min(grid-min-px, breedte) + gap)))); grid-min-px via een absolute probe in dezelfde context.
		const exp = await page.evaluate( () => { const g = document.querySelector( '#gr' ), p = document.createElement( 'i' ); p.style.cssText = 'display:block;position:absolute;visibility:hidden;inline-size:var(--pdk-grid-min)'; g.parentElement.appendChild( p ); const m = p.getBoundingClientRect().width; p.remove(); const gap = parseFloat( getComputedStyle( g ).columnGap ), cw = g.clientWidth; return { m, gap, cw, n: Math.min( g.children.length, Math.max( 1, Math.floor( ( cw + gap ) / ( Math.min( m, cw ) + gap ) ) ) ) }; } );
		check( '007', `grid @${ w }: ${ exp.n } kolom(men) bij grid-min ${ exp.m.toFixed( 1 ) }px (30ch), breedte ${ exp.cw }, gap ${ exp.gap.toFixed( 1 ) } (${ cols })`, cols === exp.n );
		if ( w === 1600 ) { check( '007', `grid @1600: meer dan 1 kolom (${ cols })`, cols > 1 ); }
		if ( w === 400 ) { check( '007', `grid @400: 1 kolom (${ cols })`, cols === 1 ); }
		// position:fixed blijft aan de viewport hangen (R-CSS-02)
		const fx = await rect( page, '#fx' );
		check( '007', `fixed @${ w }: element blijft aan de viewport (left ${ fx.left }, top ${ fx.top })`, near( fx.left, 0 ) && near( fx.top, 900 - 10 ) );
		await page.context().close();
	}
	check( '007', `V12: w(#m) bij 1600 (${ mWidth[ 1600 ]?.toFixed( 2 ) }) > w(#m) bij 400 (${ mWidth[ 400 ]?.toFixed( 2 ) })`, mWidth[ 1600 ] > mWidth[ 400 ] );
	const page = await open( browser, 320, MARKUP_320 );   // eigen pagina, zonder 800px-container
	const sw = await page.evaluate( () => [ document.documentElement.scrollWidth, document.documentElement.clientWidth ] );
	check( '007', `@320: geen horizontale scroll (${ sw[ 0 ] } <= ${ sw[ 1 ] })`, sw[ 0 ] <= sw[ 1 ] );
	await page.context().close();
}

// ---- Randgevallen van de section: [hidden], section.pdk-grid, site_width-begrenzing (AC-CSS-006/-007) ---------------------------
const saveCfg = ( cfg ) => saveResult( wpEval( `$r=PDK_CSS_Framework::save(json_decode(base64_decode("${ Buffer.from( JSON.stringify( cfg ) ).toString( 'base64' ) }"),true)); echo wp_json_encode(["saved"=>$r["saved"],"errors"=>$r["errors"]]);` ) );
async function acEdge( browser ) {
	let page = await open( browser, 1600, '<section hidden id="hs"><div>verborgen</div></section><section id="vs"><div>zichtbaar</div></section>' );
	check( '007', `<section hidden> heeft display none (${ await css( page, '#hs', 'display' ) }); een gewone section blijft grid`, await css( page, '#hs', 'display' ) === 'none' && await css( page, '#vs', 'display' ) === 'grid' );
	await page.context().close();
	page = await open( browser, 1600, '<section class="pdk-grid" id="gs"><div class="k">1</div><div class="k">2</div><div class="k">3</div></section>' );
	const g = await page.evaluate( () => { const s = document.querySelector( '#gs' ), r = [ ...s.querySelectorAll( '.k' ) ].map( ( e ) => e.getBoundingClientRect() ); return { tops: r.map( ( x ) => Math.round( x.top ) ), lefts: [ ...new Set( r.map( ( x ) => Math.round( x.left ) ) ) ], cols: getComputedStyle( s ).gridTemplateColumns.split( ' ' ).filter( ( t ) => parseFloat( t ) > 0.5 ).length, gc: getComputedStyle( s.querySelector( '.k' ) ).gridColumnStart }; } );
	check( '007', `section.pdk-grid met 3 kaarten @1600: naast elkaar (zelfde top, ${ g.lefts.length } verschillende lefts), 3 kolommen, geen baanplaatsing (${ JSON.stringify( g ) })`, new Set( g.tops ).size === 1 && g.lefts.length >= 3 && g.cols === 3 && g.gc === 'auto' );
	await page.context().close();
	// site_width 20rem (320px): wide, popout en content blijven binnen 320px; de config wordt tijdelijk gezet en direct teruggezet.
	const orig = JSON.parse( wpEval( 'echo wp_json_encode( PDK_CSS_Framework::config() );' ) ).site_width;
	try {
		const r = saveCfg( { site_width: 20 } );
		check( '007', 'tijdelijk site_width = 20 ingesteld', r.saved === true, JSON.stringify( r.errors ) );
		page = await open( browser, 1600, MARKUP_007 );
		const w = await Promise.all( [ '#d', '#po', '#c' ].map( ( x ) => rect( page, x ) ) );
		check( '007', `site_width 20rem @1600: wide ${ w[ 0 ].width.toFixed( 2 ) }, popout ${ w[ 1 ].width.toFixed( 2 ) }, content ${ w[ 2 ].width.toFixed( 2 ) } allemaal <= 320.5`, w.every( ( x ) => x.width <= 320 + NEAR ) );
		await page.context().close();
	} finally {
		const r2 = saveCfg( { site_width: orig } );
		check( '007', `site_width teruggezet naar ${ orig }`, r2.saved === true, JSON.stringify( r2.errors ) );
	}
}

// ---- AC-CSS-024 (Oxygen INACTIEF, <html dir="rtl">, 1600) --------------------------------
async function ac024( browser ) {
	const ltr = await open( browser, 1600, MARKUP_007 );
	const a = await baan( ltr, '024', 1600, false );
	await ltr.context().close();
	const page = await open( browser, 1600, MARKUP_007 );
	await page.evaluate( () => { document.documentElement.dir = 'rtl'; } );
	const b = await baan( page, '024', 1600, true );
	check( '024', 'breedtes van #d, #c, #po en #f in RTL gelijk aan LTR', [ 'd', 'c', 'po', 'f' ].every( ( k ) => nearW( a[ k ].width, b[ k ].width ) ) );
	await page.context().close();
}

// ---- AC-CSS-020: drie runs ----------------------------------------------------
async function ac020( browser, label, margin = true ) {
	// V13: line-height = lh-ratio × font-size + leading (px). Ratio's en leadings uit de config; de literalen hieronder pinnen de defaults (1.1 / 1.3 / 0.25rem).
	const cfg = JSON.parse( wpEval( 'echo wp_json_encode( PDK_CSS_Framework::config() );' ) );
	const lhH = ( fs ) => cfg.lh_heading_ratio * fs + cfg.leading_heading * 16, lhB = ( fs ) => cfg.lh_body_ratio * fs + cfg.leading_body * 16;
	for ( const [ w, h1, p, l1x, h6x, h6lx, lpx ] of [ [ 400, 44.7904, 18, 53.2694, 18, 23.8, 27.4 ], [ 1600, 61.0352, 20, 71.1387, 20, 26, 30 ] ] ) {
		const page = await open( browser, w, MARKUP_020 );
		const f1 = await px( page, '#h1', 'fontSize' ), l1 = await px( page, '#h1', 'lineHeight' );
		const fp = await px( page, '#p', 'fontSize' ), lp = await px( page, '#p', 'lineHeight' );
		check( '020', `[${ label }] @${ w }: h1 font-size ${ h1 }px (${ f1 })`, near( f1, h1 ) );
		const f6 = await px( page, '#h6', 'fontSize' ), l6 = await px( page, '#h6', 'lineHeight' );
		check( '020', `[${ label }] @${ w }: V13 h1 line-height = 1.1 × font-size + 4px = ${ l1x } (${ l1 })`, near( l1, l1x ) && near( l1, lhH( h1 ) ) );
		check( '020', `[${ label }] @${ w }: V13 h1 line-height >= 1.15 × font-size (ink guard) (${ ( l1 / f1 ).toFixed( 3 ) })`, l1 / f1 >= 1.15 );
		check( '020', `[${ label }] @${ w }: V13 h6 ${ h6x }px / ${ h6lx }px (${ f6 } / ${ l6 })`, near( f6, h6x ) && near( l6, h6lx ) && near( l6, lhH( h6x ) ) );
		check( '020', `[${ label }] @${ w }: V13 p ${ p }px / ${ lpx }px (${ fp } / ${ lp })`, near( fp, p ) && near( lp, lpx ) && near( lp, lhB( p ) ) );
		if ( margin ) {   // D-L2: bij de default normalize (h1 { margin: .67em 0 }) wordt de marge niet gecontroleerd
			const mb = await px( page, '#h1', 'marginBlockStart' );
			check( '020', `[${ label }] @${ w }: h1 (eerste kind) margin-block-start 0px (${ mb })`, near( mb, 0 ) );
		}
		await page.context().close();
	}
}
async function ac020Custom( browser, label ) {
	// custom-style.css met h1 { font-size: 10px } (module Custom CSS aan) -> eigen CSS wint.
	const had = existsSync( cssFile ) ? readFileSync( cssFile, 'utf8' ) : null;
	const origCustom = wpEval( 'echo (int) PDK_Settings::is_module_enabled("custom_css");' );
	wpEval( '$o=get_option("pdk_theme_options",[]); $o["custom_css"]["enabled"]=true; update_option("pdk_theme_options",$o);' );
	mkdirSync( STORE, { recursive: true } );
	writeFileSync( cssFile, ( had ?? '' ) + '\nh1 { font-size: 10px; }\n' );
	wpEval( 'pdk_store_file_hash("custom-style.css", file_get_contents(PDK_STORAGE_DIR."custom-style.css"));' );
	try {
		const page = await open( browser, 1600, MARKUP_020 );
		const f1 = await px( page, '#h1', 'fontSize' );
		check( '020', `[${ label }] custom-style.css h1 { font-size: 10px } wint (${ f1 })`, near( f1, 10 ) );
		await page.context().close();
	} finally {
		if ( had === null ) { wpEval( '@unlink(PDK_STORAGE_DIR."custom-style.css");' ); } else { writeFileSync( cssFile, had ); wpEval( 'pdk_store_file_hash("custom-style.css", file_get_contents(PDK_STORAGE_DIR."custom-style.css"));' ); }
		if ( origCustom === '0' ) { wpEval( '$o=get_option("pdk_theme_options",[]); $o["custom_css"]["enabled"]=false; update_option("pdk_theme_options",$o);' ); }
	}
}

// ---- AC-CSS-022 (Oxygen ACTIEF + Modern Normalize AAN) -----------------------
async function ac022( browser ) {
	for ( const [ w, fs, fh ] of [ [ 400, 18, 36 ], [ 1600, 20, 40 ] ] ) {
		const page = await open( browser, w, MARKUP_022 );
		const wFs = ( await rect( page, '#fs' ) ).width, wFh = ( await rect( page, '#fh' ) ).width;
		const mb = ( sel ) => px( page, sel, 'marginBlockStart' );
		check( '022', `@${ w }: probe flow-space = ${ fs }px (${ wFs })`, near( wFs, fs ) );
		check( '022', `@${ w }: #b margin-block-start = flow-space (${ await mb( '#b' ) })`, near( await mb( '#b' ), wFs ) );
		check( '022', `@${ w }: #h margin-block-start = flow-heading-space ${ fh }px (${ await mb( '#h' ) })`, near( await mb( '#h' ), wFh ) && near( wFh, fh ) );
		check( '022', `@${ w }: #u margin-block-start = flow-space`, near( await mb( '#u' ), wFs ) );
		check( '022', `@${ w }: #a (eerste kind) margin-block-start 0px`, near( await mb( '#a' ), 0 ) );
		// #x en #y zijn grid-items van de section: marge = flow-space, geen verdubbeling.
		const x = await page.evaluate( () => document.querySelector( '#x' ).getBoundingClientRect().bottom ), y = await page.evaluate( () => document.querySelector( '#y' ).getBoundingClientRect().top );
		check( '022', `@${ w }: #y margin-block-start = flow-space (${ await mb( '#y' ) })`, near( await mb( '#y' ), wFs ) );
		check( '022', `@${ w }: afstand onderkant #x tot bovenkant #y = die marge, geen verdubbeling (${ ( y - x ).toFixed( 2 ) } vs ${ wFs })`, near( y - x, wFs ) );
		const gridMargins = await page.evaluate( () => [ ...document.querySelectorAll( '#gr > p' ) ].map( ( e ) => parseFloat( getComputedStyle( e ).marginBlockStart ) ) );
		check( '022', `@${ w }: elke <p> in .pdk-grid margin-block-start 0 (${ gridMargins })`, gridMargins.every( ( m ) => near( m, 0 ) ) );
		check( '022', `@${ w }: #c in .pdk-flow-off margin-block-start 0`, near( await mb( '#c' ), 0 ) );
		// D-L1-regressie met Modern Normalize aan: #d staat op de wide-baan (gecentreerd, begrensd op de sitebreedte).
		const s = await rect( page, '#s' ), g = await rect( page, '#g' ), d = await rect( page, '#d' );
		const wide = Math.min( s.width - 2 * g.width, 1440 );
		check( '022', `@${ w }: D-L1 — #d op de wide-baan: w = min(w(#s) − 2·w(#g), 1440) = ${ wide.toFixed( 2 ) } (${ d.width.toFixed( 2 ) })`, nearW( d.width, wide ) );
		check( '022', `@${ w }: D-L1 — #d gecentreerd in #s (${ ( d.left - s.left ).toFixed( 2 ) } / ${ ( s.right - d.right ).toFixed( 2 ) })`, nearW( d.left - s.left, s.right - d.right ) );
		await page.context().close();
	}
}

// ---- AC-CSS-019 (Oxygen INACTIEF): uitweg-snippet ----------------------------
async function ac019( browser ) {
	const snippet = '\n.woocommerce section { display: block; container-type: normal; font-size: inherit; }\n';
	const had = existsSync( cssFile ) ? readFileSync( cssFile, 'utf8' ) : null;
	const origCustom = wpEval( 'echo (int) PDK_Settings::is_module_enabled("custom_css");' );
	wpEval( '$o=get_option("pdk_theme_options",[]); $o["custom_css"]["enabled"]=true; update_option("pdk_theme_options",$o);' );
	const stamp = () => wpEval( 'pdk_store_file_hash("custom-style.css", file_get_contents(PDK_STORAGE_DIR."custom-style.css"));' );
	mkdirSync( STORE, { recursive: true } );
	try {
		writeFileSync( cssFile, had ?? '' ); stamp();
		let page = await open( browser, 1600, MARKUP_019 );
		check( '019', 'zonder snippet: section in .woocommerce heeft display grid en container-type inline-size', await css( page, '#ws', 'containerType' ) === 'inline-size' && await css( page, '#ws', 'display' ) === 'grid' );
		const fsNo = await px( page, '#ws', 'fontSize' );
		check( '019', `zonder snippet: font-size van de section = step-0 (20px @1600), niet die van .woocommerce (${ fsNo })`, near( fsNo, 20 ) );
			await page.context().close();
		writeFileSync( cssFile, ( had ?? '' ) + snippet ); stamp();
		page = await open( browser, 1600, MARKUP_019 );
		check( '019', 'met snippet: display block en container-type normal', await css( page, '#ws', 'containerType' ) === 'normal' && await css( page, '#ws', 'display' ) === 'block' );
			const fsWith = await px( page, '#ws', 'fontSize' ), fsWc = await px( page, '#wc', 'fontSize' );
			check( '019', `met snippet: font-size van de section = die van .woocommerce (${ fsWith } vs ${ fsWc })`, near( fsWith, fsWc ) && near( fsWc, 31 ) );
			const ws = await rect( page, '#ws' ), wd = await rect( page, '#wd' );
			check( '019', `met snippet: de div is even breed als de section (${ wd.width.toFixed( 2 ) } vs ${ ws.width.toFixed( 2 ) })`, nearW( wd.width, ws.width ) );
		check( '019', 'met snippet: gewone section buiten .woocommerce blijft grid + inline-size', await css( page, '#plain', 'containerType' ) === 'inline-size' && await css( page, '#plain', 'display' ) === 'grid' );
		await page.context().close();
	} finally {
		if ( had === null ) { wpEval( '@unlink(PDK_STORAGE_DIR."custom-style.css");' ); } else { writeFileSync( cssFile, had ); stamp(); }
		if ( origCustom === '0' ) { wpEval( '$o=get_option("pdk_theme_options",[]); $o["custom_css"]["enabled"]=false; update_option("pdk_theme_options",$o);' ); }
	}
}

// ---- AC-CSS-009 (L): volgorde van de <link>-regels en aantal ---------------------
async function ac009( label ) {
	const html = await ( await fetch( BASE + '/' ) ).text();
	const links = [ ...html.matchAll( /<link[^>]+>/g ) ].map( ( m ) => m[ 0 ] );
	const idx = ( re ) => links.findIndex( ( l ) => re.test( l ) );
	const iNorm = idx( /normalize/i ), iFw = idx( /css-framework\.css\?ver=/ ), iCu = idx( /custom-style\.css/ );
	const count = ( html.match( /css-framework\.css\?ver=/g ) || [] ).length;
	check( '009', `[${ label }] css-framework.css?ver= komt precies 1x voor (${ count })`, count === 1 );
	check( '009', `[${ label }] volgorde: framework (${ iFw }) voor custom-style (${ iCu })`, iFw >= 0 && iCu > iFw );
	// WordPress print global styles inline: <style id="global-styles-inline-css">. Vergelijk posities in de volledige HTML.
	const posGs = html.search( /id=['"](wp-)?global-styles-inline-css/ ), posFw = html.search( /css-framework\.css\?ver=/ );
	if ( label.includes( 'oxygen actief' ) ) {
		// Met Oxygen actief print WP global-styles in de footer (onschadelijk: oxygen-zero heeft geen theme.json): ná het framework en ná </main> / alle head-links.
		const posMain = html.lastIndexOf( '</main>' ), posHeadEnd = html.indexOf( '</head>' );
		check( '009', `[${ label }] global-styles inline staat ná het framework (pos ${ posGs } > ${ posFw }) en in de footer (ná </head> ${ posHeadEnd }${ posMain >= 0 ? ' en </main> ' + posMain : '' })`, posGs > posFw && posGs > posHeadEnd && ( posMain < 0 || posGs > posMain ) );
	} else {
		check( '009', `[${ label }] volgorde: global-styles inline (pos ${ posGs }) voor framework (pos ${ posFw })`, posGs >= 0 && posFw > posGs );
	}
	if ( label.includes( 'oxygen actief' ) ) { check( '009', `[${ label }] volgorde: normalize (${ iNorm }) voor framework (${ iFw })`, iNorm >= 0 && iNorm < iFw ); }
	return { iFw, iCu };
}

// ---- main -------------------------------------------------------------------------
const begin = { oxygen: oxygenActive(), normalize: null, module: wpEval( 'echo (int) PDK_Settings::is_module_enabled("css_framework");' ) === '1' };
const browser = await chromium.launch();
try {
	moduleOn( true );
	cfgDefaults();

	// Oxygen wordt NOOIT standaard uitgezet (wens van de gebruiker): de Oxygen-inactief-cases draaien alleen met PDK_LIVE_OXYGEN_OFF=1.
	if ( ! OXYGEN_OFF ) { console.log( 'note: Oxygen-inactief-cases overgeslagen (Oxygen blijft actief). Zet PDK_LIVE_OXYGEN_OFF=1 om Oxygen tijdelijk uit te zetten voor AC-007/-019/-020/-009 zonder Oxygen; de beginstand wordt in finally hersteld.' ); }
	if ( OXYGEN_OFF && ( run( '007' ) || run( '024' ) || run( '019' ) || run( '020' ) || run( '009' ) ) ) {
		setOxygen( false );
		if ( run( '007' ) ) { await ac007( browser ); await acEdge( browser ); }
		if ( run( '024' ) ) { await ac024( browser ); }
		if ( run( '019' ) ) { await ac019( browser ); }
		if ( run( '020' ) ) { await ac020( browser, 'Oxygen inactief' ); await ac020Custom( browser, 'Oxygen inactief' ); }
		if ( run( '009' ) ) { await ac009( 'oxygen inactief' ); }
	}
	if ( run( '007' ) || run( '024' ) || run( '019' ) || run( '020' ) || run( '022' ) || run( '009' ) ) {
		setOxygen( true );
		begin.normalize = getModernNormalize();
		try {
			setModernNormalize( true );
			console.log( 'note: AC-007/-024/-019 draaien hier met Oxygen actief en Modern Normalize aan (body-marge 0 door de reset)' );
			if ( run( '007' ) ) { await ac007( browser ); await acEdge( browser ); }
			if ( run( '024' ) ) { await ac024( browser ); }
			if ( run( '019' ) ) { await ac019( browser ); }
			if ( run( '020' ) ) { await ac020( browser, 'Oxygen actief, Modern Normalize aan (primair, V7)' ); await ac020Custom( browser, 'Oxygen actief, Modern Normalize aan' ); }
			if ( run( '022' ) ) { await ac022( browser ); }
			if ( run( '009' ) ) { await ac009( 'oxygen actief, Modern Normalize aan' ); }
			setModernNormalize( false );
			if ( run( '020' ) ) { await ac020( browser, 'Oxygen actief, default normalize', false ); await ac020Custom( browser, 'Oxygen actief, default normalize' ); }
		} finally {
			setModernNormalize( begin.normalize === 'true' );
		}
	}
	if ( run( '009' ) ) {
		moduleOn( false );
		const html = await ( await fetch( BASE + '/' ) ).text();
		check( '009', 'module uit: css-framework.css?ver= komt 0x voor', ( html.match( /css-framework\.css\?ver=/g ) || [] ).length === 0 );
		check( '009', 'module uit: custom-style.css laadt nog steeds', /custom-style\.css/.test( html ) );
	}
} finally {
	await browser.close();
	moduleOn( true );            // de klasse moet geladen zijn om de config terug te zetten
	cfgRestore();
	moduleOn( begin.module );    // module-stand herstellen, ook na een fout in de module-uit-stap
	setOxygen( begin.oxygen );   // beginstand herstellen (normaal: actief)
	console.log( `\nOxygen hersteld naar: ${ oxygenActive() ? 'actief' : 'inactief' } (begin: ${ begin.oxygen ? 'actief' : 'inactief' }); module css_framework: ${ begin.module ? 'aan' : 'uit' }` );
}
const fouten = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length } checks, ${ fouten } mislukt` );
process.exit( fouten ? 1 : 0 );
