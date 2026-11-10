// Live-checks (L-delen) voor Slice 2 van de module CSS Framework op http://dev.local.
// Normatief: docs/css-framework-brief.md (AC-CSS-013, -016, -018; -017 is handmatig, zie het .md).
//
//   node tests/live/css-framework-slice2.mjs            # alles
//   node tests/live/css-framework-slice2.mjs 016        # alleen die AC's
//
// Voorwaarden: zie tests/live/css-framework-slice1.md §0 (plugin 2.13.0 als kopie in mu-plugins, LOCAL_PHP,
// LOCAL_PHP_INI, PLAYWRIGHT_DIR). Oxygen moet ACTIEF blijven: het script zet het aan als het uit staat en herstelt
// in `finally` de beginstand (normaal actief). De module-stand en de oxygen_variables_json_string-inhoud van
// vreemde variabelen worden niet aangeraakt.

import { execFileSync, execSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { homedir } from 'node:os';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const require = createRequire( import.meta.url );
function loadPlaywright() {
	const here = path.dirname( fileURLToPath( import.meta.url ) );
	const cands = [ process.env.PLAYWRIGHT_DIR, path.join( execSync( 'npm root -g' ).toString().trim(), 'playwright' ), path.resolve( 'node_modules', 'playwright' ), path.join( here, 'node_modules', 'playwright' ) ].filter( Boolean );
	for ( const c of cands ) { try { return require( c ); } catch ( e ) { /* volgende */ } }
	console.error( 'Playwright niet gevonden (zie slice1.md). Geprobeerd:\n  ' + cands.join( '\n  ' ) );
	process.exit( 2 );
}
const { chromium } = loadPlaywright();

const BASE  = process.env.DEV_URL || 'http://dev.local';
const SITE  = process.env.DEV_PATH || path.join( homedir(), 'Local Sites', 'dev', 'app', 'public' );
const PHP   = process.env.LOCAL_PHP;
const INI   = process.env.LOCAL_PHP_INI;
const WPCLI = process.env.WPCLI || 'C:\\Program Files (x86)\\Local\\resources\\extraResources\\bin\\wp-cli\\wp-cli.phar';
const TOL   = 0.05;
if ( ! PHP || ! INI ) { console.error( 'Zet LOCAL_PHP en LOCAL_PHP_INI (zie slice1.md).' ); process.exit( 2 ); }

const NOISE = /^(PHP )?(Warning|Notice|Deprecated)\b.*$/i;
const wp = ( ...args ) => execFileSync( PHP, [ '-c', INI, WPCLI, `--path=${ SITE }`, ...args ], { encoding: 'utf8' } ).split( /\r?\n/ ).filter( ( l ) => ! NOISE.test( l.trim() ) ).join( '\n' ).trim();
const wpEval = ( code, ...extra ) => wp( 'eval', code, ...extra );
const oxygenActive = () => wp( 'plugin', 'list', '--name=oxygen', '--field=status' ) === 'active';
const moduleOn = ( on ) => wpEval( `$o=get_option("pdk_theme_options",[]); $o["css_framework"]["enabled"]=${ on ? 'true' : 'false' }; update_option("pdk_theme_options",$o);` );
const abilities = ( on ) => wpEval( `$o=get_option("pdk_theme_options",[]); $o["agent_abilities"]["enabled"]=${ on ? 'true' : 'false' }; update_option("pdk_theme_options",$o);` );


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

const results = [];
const check = ( ac, label, ok, detail = '' ) => { results.push( { ac, label, ok } ); console.log( `${ ok ? 'ok  ' : 'FOUT' } AC-CSS-${ ac } ${ label }${ ok ? '' : '  -> ' + detail }` ); };
const near = ( a, b ) => Math.abs( a - b ) <= TOL;
const wanted = process.argv.slice( 2 );
const run = ( ac ) => wanted.length === 0 || wanted.includes( ac );
const STEP0 = 'clamp(1.125rem, 1.0769rem + 0.1923cqi, 1.25rem)';
const vars = () => JSON.parse( wp( 'option', 'get', 'oxygen_variables_json_string', '--format=json' ) || '[]' );
const parsed = ( v ) => ( typeof v === 'string' ? JSON.parse( v ) : v );
const byName = ( list, n ) => list.find( ( x ) => x.cssVariableName === n );
const savePdk = ( cfg = '[]' ) => wpEval( `echo wp_json_encode( PDK_CSS_Framework::save( ${ cfg } ) );` );

// ---- AC-CSS-013 (Oxygen inactief in de brief; de abilities zelf hangen er niet van af, dus ook actief geldig) ----
function ac013() {
	const out = wpEval( 'echo wp_json_encode( wp_get_ability( "pdk-theme-options/get-css-framework" )->execute( [] ) );', '--user=pdkmin' );
	let j = {}; try { j = JSON.parse( out ); } catch ( e ) { /* check faalt hieronder */ }
	check( '013', 'get-css-framework als pdkmin geeft JSON met tokens', Array.isArray( j.tokens ) && j.tokens.length > 20, out.slice( 0, 200 ) );
	const id = wp( 'user', 'create', 'pdk-cssfw-test', 'pdk-cssfw-test@example.test', '--role=editor', '--porcelain' );
	try {
		const r = wpEval( '$a = wp_get_ability( "pdk-theme-options/get-css-framework" ); $x = $a->execute( [] ); echo is_wp_error( $x ) ? "denied:" . $x->get_error_code() : "allowed";', `--user=${ id }` );
		check( '013', `redacteur (id ${ id }) wordt geweigerd (${ r })`, r.startsWith( 'denied' ) );
	} finally { wp( 'user', 'delete', id, '--yes' ); }
	abilities( true );
	moduleOn( false );
	try {
		const gone = wpEval( 'echo wp_get_ability( "pdk-theme-options/get-css-framework" ) ? "bestaat" : "weg";' );
		check( '013', `met de module uit bestaat de ability niet (${ gone })`, gone === 'weg' );
	} finally { moduleOn( true ); }
}

// ---- AC-CSS-016 (Oxygen ACTIEF) ----------------------------------------------------------------------------
async function ac016( browser ) {
	savePdk( '[]' );
	let list = parsed( vars() );
	const r = ( n ) => byName( list, n )?.value?.style;
	check( '016', 'oxygen_variables_json_string: pdk-step-0 style byte-gelijk aan de brief', r( 'pdk-step-0' ) === STEP0, String( r( 'pdk-step-0' ) ) );
	check( '016', 'pdk-site-width = 90rem en pdk-measure = 65ch', r( 'pdk-site-width' ) === '90rem' && r( 'pdk-measure' ) === '65ch' );
	check( '016', 'V13: pdk-lh-heading = 1.1 en pdk-lh-body = 1.3 zijn gesynchroniseerd (11 vaste tokens; aantal pdk-*-entries = tokens())', r( 'pdk-lh-heading' ) === '1.1' && r( 'pdk-lh-body' ) === '1.3' && list.filter( ( v ) => v.cssVariableName.startsWith( 'pdk-' ) ).length === Number( wpEval( 'echo count( PDK_CSS_Framework::tokens( PDK_CSS_Framework::config() ) );' ) ) );
	check( '016', 'geen pdk-bo-* variabelen gesynchroniseerd', ! list.some( ( v ) => v.cssVariableName.startsWith( 'pdk-bo-' ) ) );
	check( '016', 'namen zonder voorloop-- en geen dynamicData', list.filter( ( v ) => v.cssVariableName.startsWith( 'pdk-' ) ).every( ( v ) => ! v.cssVariableName.startsWith( '--' ) && ! ( 'dynamicData' in v ) ) );
	const ids = list.filter( ( v ) => v.cssVariableName.startsWith( 'pdk-' ) ).map( ( v ) => `${ v.cssVariableName }:${ v.id }` ), n1 = list.length;
	savePdk( '[]' );
	list = parsed( vars() );
	const ids2 = list.filter( ( v ) => v.cssVariableName.startsWith( 'pdk-' ) ).map( ( v ) => `${ v.cssVariableName }:${ v.id }` );
	check( '016', `tweede save: dezelfde id's en hetzelfde aantal entries (${ n1 } / ${ list.length })`, JSON.stringify( ids ) === JSON.stringify( ids2 ) && n1 === list.length );
	const colls = wp( 'option', 'get', 'oxygen_variables_collections_json_string', '--format=json' );
	check( '016', 'collectie "PDK Framework" staat in de collecties', colls.includes( 'PDK Framework' ), colls );

	const html = await ( await fetch( BASE + '/' ) ).text();
	const vlink = [ ...html.matchAll( /href=["']([^"']*(?:variables|global-settings|oxygen)[^"']*\.css[^"']*)["']/gi ) ].map( ( m ) => m[ 1 ] );
	let found = html.includes( '--pdk-step-0:' );
	for ( const u of vlink ) { if ( found ) { break; } try { found = ( await ( await fetch( new URL( u, BASE ) ) ).text() ).includes( '--pdk-step-0:' ); } catch ( e ) { /* volgende */ } }
	check( '016', 'Oxygens variabelenbestand op de frontend bevat --pdk-step-0: (niet byte-vergeleken: minificatie)', found );

	// Effect: css-framework.css geblokkeerd, alleen Oxygens definitie actief.
	for ( const [ w, p, h1 ] of [ [ 400, 18, null ], [ 1600, 20, 61.0352 ] ] ) {
		const ctx = await browser.newContext( { viewport: { width: w, height: 900 } } );
		await ctx.route( /css-framework\.css/, ( rt ) => rt.abort() );
		const page = await ctx.newPage();
		await page.goto( BASE + '/', { waitUntil: 'load' } );
		await page.evaluate( () => { document.body.innerHTML = '<div style="container-type:inline-size"><p id="p" style="font-size:var(--pdk-step-0)">x</p><h1 id="h" style="font-size:var(--pdk-step-5)">x</h1></div>'; } );
		const fp = await page.evaluate( () => parseFloat( getComputedStyle( document.querySelector( '#p' ) ).fontSize ) );
		check( '016', `@${ w } zonder css-framework.css: p met var(--pdk-step-0) = ${ p }px (${ fp })`, near( fp, p ) );
		if ( h1 ) { const fh = await page.evaluate( () => parseFloat( getComputedStyle( document.querySelector( '#h' ) ).fontSize ) ); check( '016', `@${ w } zonder css-framework.css: h1 step-5 = ${ h1 }px (${ fh })`, near( fh, h1 ) ); }
		await ctx.close();
	}
}

// ---- AC-CSS-018 ---------------------------------------------------------------------------------------------
async function ac018() {
	savePdk( '[]' );
	moduleOn( false );
	try {
		const html = await ( await fetch( BASE + '/' ) ).text();
		check( '018', 'module uit: geen pdk-css-framework op de frontend', ! /pdk-css-framework|css-framework\.css/.test( html ) );
		const list = parsed( vars() );
		check( '018', 'de pdk-*-variabelen staan nog in oxygen_variables_json_string (D12)', list.some( ( v ) => v.cssVariableName === 'pdk-step-0' ) );
	} finally { moduleOn( true ); }
}

// ---- main ---------------------------------------------------------------------------------------------------
const begin = { oxygen: oxygenActive(), module: wpEval( 'echo (int) PDK_Settings::is_module_enabled("css_framework");' ) === '1', abilities: wpEval( 'echo (int) PDK_Settings::is_module_enabled("agent_abilities");' ) === '1' };
const browser = await chromium.launch();
try {
	if ( ! begin.oxygen ) { wp( 'plugin', 'activate', 'oxygen' ); }   // slice 2 vraagt Oxygen actief
	moduleOn( true );
	cfgDefaults();
	if ( run( '013' ) ) { ac013(); }
	if ( run( '016' ) ) { await ac016( browser ); }
	if ( run( '018' ) ) { await ac018(); }
} finally {
	await browser.close();
	moduleOn( true );
	cfgRestore();
	moduleOn( begin.module );
	abilities( begin.abilities );
	if ( oxygenActive() !== begin.oxygen ) { wp( 'plugin', begin.oxygen ? 'activate' : 'deactivate', 'oxygen' ); }
	console.log( `\nOxygen hersteld naar: ${ oxygenActive() ? 'actief' : 'inactief' } (begin: ${ begin.oxygen ? 'actief' : 'inactief' }); module css_framework: ${ begin.module ? 'aan' : 'uit' }; agent_abilities: ${ begin.abilities ? 'aan' : 'uit' }` );
}
const fouten = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length } checks, ${ fouten } mislukt` );
process.exit( fouten ? 1 : 0 );
