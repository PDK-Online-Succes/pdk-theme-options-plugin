<?php
/**
 * Bouwt/ververst de demo-pagina "CSS Framework demo" (slug css-framework-demo) op dev.local.
 *
 *   wpl --user=1 eval-file tests/live/css-framework-demo.php     (zie css-framework-slice1.md, "Demo-pagina")
 *
 * Idempotent: bestaat de pagina al, dan wordt de inhoud vervangen.
 *
 * ALLE verwachte waarden en alle zichtbare labels worden in de browser berekend uit de CSS-tokens op :root
 * (getComputedStyle(document.documentElement).getPropertyValue('--pdk-step-0') enz.): clamp(<min>rem, <b>rem ± <a>cqi, <max>rem)
 * wordt geparsed en geëvalueerd voor de containerbreedte W; regelhoogte = --pdk-lh-* × fs + --pdk-leading-*.
 * Na een wijziging van de instellingen is de demo dus direct goed, ZONDER opnieuw te genereren. Alleen dit is gebakken in de HTML
 * en vereist regeneratie: (1) data-vmin/data-vmax (min/max viewport; niet in de CSS), (2) de structuur: welke stappen bestaan
 * (steps_up/steps_down) en welke ruimte-paren, en daarmee de kop->stap-koppeling.
 * Alle demo-styling zit in een <style> met uitsluitend .demo-*-klassen.
 */
if ( ! class_exists( 'PDK_CSS_Framework' ) ) { fwrite( STDERR, "PDK_CSS_Framework niet geladen\n" ); exit( 1 ); }
if ( ! current_user_can( 'edit_pages' ) ) { fwrite( STDERR, "Gebruik --user=1\n" ); exit( 1 ); }

$c  = PDK_CSS_Framework::config();
$tk = [];   // alleen de NAMEN van de fluid tokens (structuur); de waarden komen live uit de CSS
foreach ( PDK_CSS_Framework::tokens( $c ) as $t ) { if ( isset( $t['min'] ) ) { $tk[] = substr( $t['name'], 2 ); } }

$e   = static fn( $s ) => esc_html( (string) $s );
$m   = static fn( array $a ) => ' data-m="' . esc_attr( wp_json_encode( $a ) ) . '"';
$L   = static fn( $t ) => '<small class="demo-lbl" dir="ltr"><span class="demo-t" data-t="' . esc_attr( $t ) . '"></span> <span class="demo-rds"></span></small>';
$sec = static fn( $title, $inner, $cls = '', $attr = '' ) => '<div class="demo-blk"><div class="demo-h">' . esc_html( $title ) . '</div><section class="demo-sec ' . $cls . '"' . $attr . '>' . $inner . '</section></div>';
$cap = static fn( $txt, array $ent = [ [ 'info' ] ], $f = '' ) => '<div class="demo-cap"' . ( $f ? ' data-f="1"' : '' ) . $m( $ent ) . '>' . $L( $txt ) . '</div>';
$T   = static fn( $name ) => '{tok:' . $name . '} = {fl:' . $name . '}';   // sjabloon: ruwe tokenwaarde + px @min -> px @max

$F   = '--pdk-flow-space';
$FH  = '--pdk-flow-heading-space';
$G   = '--pdk-gutter';
$blocks = [];

// ---- 1. Typografie ----------------------------------------------------------------------------------------------
$h = $cap( 'Type scale: font-size = clamp(min, …cqi, max) of the section width (container); line-height = {lr:heading} for headings and {lr:body} for p. Flow margin-top: ' . $T( $F ) . '; before h2-h4: ' . $T( $FH ) . '.' );
$map = [ 'h1' => (int) $c['steps_up'], 'h2' => (int) $c['steps_up'] - 1, 'h3' => (int) $c['steps_up'] - 2, 'h4' => (int) $c['steps_up'] - 3, 'h5' => (int) $c['steps_up'] - 4, 'h6' => (int) $c['steps_up'] - 5, 'p' => 0 ];
$i   = 0;
foreach ( $map as $tag => $step ) {
	$step = 'p' === $tag ? 0 : max( $step, 0 );
	$st   = '--pdk-step-' . $step;
	$kind = 'p' === $tag ? 'body' : 'heading';
	$mt   = 0 === $i ? null : ( in_array( $tag, [ 'h2', 'h3', 'h4' ], true ) ? $FH : $F );
	$ent  = [ [ 'fs', $st ], [ 'lh', $st, $kind ], [ 'ink', $st, 1.15 ], null === $mt ? [ 'z' ] : [ 'mt', $mt ] ];
	$txt  = $tag . ' — step-' . $step . ' — {fl:' . $st . '} — line-height = {lr:' . $kind . '} — margin-top ' . ( null === $mt ? '0 (first after caption)' : '{tok:' . $mt . '} = {fl:' . $mt . '}' );
	$h   .= "<$tag class=\"demo-b\"" . $m( $ent ) . '>' . ( 'p' === $tag ? 'Paragraph text' : 'Heading ' . substr( $tag, 1 ) ) . $L( $txt ) . "</$tag>";
	$i++;
}
$h .= $cap( 'Scale --pdk-step-' . -(int) $c['steps_down'] . ' … --pdk-step-' . (int) $c['steps_up'] . ' (divs with font-size: var(--pdk-step-n))', [ [ 'info' ] ] );
for ( $s = -(int) $c['steps_down']; $s <= (int) $c['steps_up']; $s++ ) {
	$h .= '<div class="demo-b demo-st-' . str_replace( '-', 'm', (string) $s ) . '"' . $m( [ [ 'fs', '--pdk-step-' . $s ] ] ) . '>Aa step-' . $s . $L( 'step-' . $s . ' — {fl:--pdk-step-' . $s . '}' ) . '</div>';
}
$blocks[] = $sec( '1. Type scale + base h1-h6 / p (inside a section, so flow margins apply)', $h );

// ---- 2. Spaceschaal --------------------------------------------------------------------------------------------
$h    = $cap( 'Bars: inline-size = var(--pdk-space-x). Bar width must equal the expected px at this section width.' );
$bars = []; $barcss = [];
foreach ( $tk as $k ) { if ( 0 === strpos( $k, 'pdk-space-' ) ) { $bars[ '--' . $k ] = '--' . $k; } }
$bars['--pdk-gutter']       = '--pdk-gutter';
$bars['--pdk-popout-space'] = '--pdk-popout-space';
foreach ( $bars as $name => $tokn ) {
	$vc = ltrim( str_replace( [ '--pdk-space-', '--pdk-' ], [ '', '' ], $name ), '-' );
	$barcss[ $vc ] = $tokn;
	$h .= '<div class="demo-row"' . $m( [ [ 'bw', $tokn ] ] ) . '><div class="demo-bar-s demo-sp-' . $vc . '"></div>' . $L( $name . ( $name !== '--pdk-gutter' && $name !== '--pdk-popout-space' ? '' : ' = {tok:' . $name . '}' ) . ' — {fl:' . $tokn . '}' ) . '</div>';
}
$blocks[] = $sec( '2. Space scale (--pdk-space-*, one-up pairs, extra pairs)', $h );

// ---- 3. Flow ---------------------------------------------------------------------------------------------------
$fe = static fn( $k ) => [ 'mt', $k ];
$h  = $cap( 'Flow: margin between text elements = ' . $T( $F ) . '; before h2-h4 = ' . $T( $FH ) . '. The gap between the tinted blocks is the margin; the first element after a non-text element has 0.' );
$h .= '<h2 class="demo-b"' . $m( [ [ 'z' ] ] ) . '>h2 first' . $L( 'h2 — first child after caption — margin-top 0' ) . '</h2>';
$h .= '<p class="demo-b"' . $m( [ $fe( $F ) ] ) . '>p after h2' . $L( 'p after h2 — margin-top = ' . $T( $F ) ) . '</p>';
$h .= '<p class="demo-b"' . $m( [ $fe( $F ) ] ) . '>p after p' . $L( 'p after p — margin-top = ' . $T( $F ) ) . '</p>';
$h .= '<h3 class="demo-b"' . $m( [ $fe( $FH ) ] ) . '>h3 after p' . $L( 'h3 after p — margin-top = ' . $T( $FH ) ) . '</h3>';
$h .= '<ul class="demo-b"' . $m( [ $fe( $F ) ] ) . '><li>ul after h3' . $L( 'ul after h3 — margin-top = ' . $T( $F ) ) . '</li></ul>';
$h .= '<h4 class="demo-b"' . $m( [ $fe( $FH ) ] ) . '>h4 after ul' . $L( 'h4 after ul — margin-top = ' . $T( $FH ) ) . '</h4>';
$h .= '<blockquote class="demo-b"' . $m( [ $fe( $F ) ] ) . '>blockquote after h4' . $L( 'blockquote after h4 — margin-top = ' . $T( $F ) ) . '</blockquote>';
$h .= '<h5 class="demo-b"' . $m( [ $fe( $F ) ] ) . '>h5 after blockquote' . $L( 'h5 — margin-top = {tok:' . $F . '} (only h2-h4 get the larger space)' ) . '</h5>';
$h .= '<div class="pdk-flow-off demo-fo">'
	. '<p class="demo-b"' . $m( [ [ 'z' ] ] ) . '>p in .pdk-flow-off' . $L( '.pdk-flow-off > p — margin-top 0 (blocks touch)' ) . '</p>'
	. '<p class="demo-b"' . $m( [ [ 'z' ] ] ) . '>p in .pdk-flow-off' . $L( '.pdk-flow-off > p after p — margin-top 0' ) . '</p>'
	. '<h3 class="demo-b"' . $m( [ [ 'z' ] ] ) . '>h3 in .pdk-flow-off' . $L( '.pdk-flow-off > h3 — margin-top 0 (not {tok:' . $FH . '})' ) . '</h3></div>';
$blocks[] = $sec( '3. Flow rule + .pdk-flow-off', $h );

// ---- 4. Banen --------------------------------------------------------------------------------------------------
$lane_txt = [
	'wide'         => 'default lane (no class) — wide = min(site-width {tok:--pdk-site-width} = {px:--pdk-site-width}, section − 2×gutter)',
	'content'      => '.pdk-content — content = min({tok:--pdk-measure}, section − 2×gutter); the measure is measured live (1ch below)',
	'popout'       => '.pdk-popout — popout = content + 2×popout-space (shrinks when there is no room)',
	'popout-start' => '.pdk-popout-start — popout-start edge → content end',
	'popout-end'   => '.pdk-popout-end — content start → popout-end edge',
	'wide-start'   => '.pdk-wide-start — wide-start edge → content end',
	'wide-end'     => '.pdk-wide-end — content start → wide-end edge',
	'full'         => '.pdk-full — full = section width (edge to edge)',
	'full-start'   => '.pdk-full-start — section start edge → wide end',
	'full-end'     => '.pdk-full-end — wide start → section end edge',
];
$lanes = static function ( array $names ) use ( $lane_txt, $L, $m ) {
	$o = '';
	foreach ( $names as $k => $n ) {
		$probe = 0 === $k ? '<div class="demo-pw"><div class="demo-pi demo-pr-c" data-pr="c"></div><div class="demo-pi demo-pr-g" data-pr="g"></div><div class="demo-pi demo-pr-p" data-pr="p"></div><div class="demo-pi demo-pr-w" data-pr="w"></div></div>' : '';
		$o .= '<div class="' . ( 'wide' === $n ? '' : 'pdk-' . $n . ' ' ) . 'demo-l demo-l-' . $n . '"' . $m( [ [ 'lane', $n ] ] ) . '>' . $probe . $L( $lane_txt[ $n ] ) . '</div>';
	}
	return $o;
};
$tokens_txt = 'gutter = {tok:--pdk-gutter} ({fl:--pdk-gutter}) · site-width {tok:--pdk-site-width} · measure {tok:--pdk-measure} · popout-space = {tok:--pdk-popout-space} ({fl:--pdk-popout-space})';
$names = array_keys( $lane_txt );
$h  = $cap( 'Lanes of the section grid. ' . $tokens_txt . '. Widths: LTR/RTL are equal; start = logical start offset.' ) . $lanes( $names );
$blocks[] = $sec( '4. Section = grid: lanes (each its own tint; the empty tint at the edges is the gutter / outer tracks)', $h );

// ---- 5. RTL ----------------------------------------------------------------------------------------------------
$h = $cap( 'dir="rtl" on the section: the grid mirrors, so -start sits on the right edge. Expected values are the same widths; start offset is measured from the right.' )
	. $lanes( [ 'wide', 'content', 'popout-start', 'wide-end', 'full-start' ] );
$blocks[] = $sec( '5. RTL section (dir="rtl")', $h, '', ' dir="rtl"' );

// ---- 6. .pdk-off -----------------------------------------------------------------------------------------------
$h = '<div class="demo-b">' . $L( '.pdk-off section — no framework grid: display block, container-type normal, child = full section width (no lanes, no gutters)' ) . '</div>';
$blocks[] = $sec( '6. .pdk-off section', $h, 'pdk-off', $m( [ [ 'disp', 'block' ], [ 'ct', 'normal' ], [ 'cw' ] ] ) );

// ---- 7. .pdk-container -----------------------------------------------------------------------------------------
$h  = $cap( '.pdk-container: container-type inline-size, so cqi tokens follow the container (max 600px), not the viewport. Expected values are computed for the container width.' );
$h .= '<div class="demo-w"><div class="pdk-container demo-b"' . $m( [ [ 'ct', 'inline-size' ], [ 'info' ] ] ) . '>' . $L( '.pdk-container max 600px' )
	. '<h1' . $m( [ [ 'fs', '--pdk-step-' . (int) $c['steps_up'] ], [ 'lh', '--pdk-step-' . (int) $c['steps_up'], 'heading' ], [ 'ink', '--pdk-step-' . (int) $c['steps_up'], 1.15 ] ] ) . '>h1' . $L( 'h1 — step-' . (int) $c['steps_up'] . ' at the container width — {fl:--pdk-step-' . (int) $c['steps_up'] . '}' ) . '</h1>'
	. '<p' . $m( [ [ 'fs', '--pdk-step-0' ], [ 'lh', '--pdk-step-0', 'body' ], [ 'ink', '--pdk-step-0', 1.15 ], $fe( $F ) ] ) . '>p' . $L( 'p — step-0 at the container width — {fl:--pdk-step-0} — margin-top {tok:' . $F . '}' ) . '</p></div></div>';
$blocks[] = $sec( '7. .pdk-container', $h );

// ---- 8. .pdk-grid ----------------------------------------------------------------------------------------------
$grid = static function ( string $cls, string $caption ) use ( $m, $L ) {
	$g  = '<div class="demo-gw ' . $cls . '"><div class="demo-pw"><i class="demo-pi demo-gmp"></i></div>';
	$g .= '<div class="demo-cap" data-f="1"' . $m( [ [ 'gap', '--pdk-gutter' ], [ 'cols' ] ] ) . '>' . $L( $caption ) . '</div>';
	$g .= '<div class="pdk-grid">';
	for ( $i = 1; $i <= 6; $i++ ) { $g .= '<p class="demo-b"' . $m( [ [ 'z' ] ] ) . '>Item ' . $i . $L( 'p in .pdk-grid — margin-top 0' ) . '</p>'; }
	return $g . '</div></div>';
};
$h  = $cap( 'Auto-grid: .pdk-grid puts as many columns next to each other as fit, with at least --pdk-grid-min per column and a gap of --pdk-gutter ({tok:--pdk-gutter} = {fl:--pdk-gutter}). Narrower container → fewer columns; no breakpoints needed. Below: the default minimum, then the same grid with a smaller minimum.' );
$h .= $grid( '', 'Default: --pdk-grid-min = {gt} ≈ {gm}px at this text size per column minimum. Live: {cols} column(s) for 6 items; narrow the window and the count drops.' );
$h .= $grid( 'demo-gm20', 'Same grid with --pdk-grid-min: {gt} (class demo-gm20 on the wrapper) ≈ {gm}px minimum per column → more columns fit than above. Live: {cols} column(s).' );
$blocks[] = $sec( '8. Auto-grid .pdk-grid (columns in ch of the text size)', $h );

// ---- Pagina ----------------------------------------------------------------------------------------------------
$css = <<<'CSS'
.demo-root{background:#fff;color:#1c2430;font-family:system-ui,sans-serif}
.demo-bar{position:sticky;top:0;z-index:9;background:#1c2430;color:#fff;font:13px/1.4 ui-monospace,Consolas,monospace;padding:8px 12px}
.demo-bar-bad{background:#a40000}
.demo-intro{padding:16px 12px;font:14px/1.5 system-ui,sans-serif}
.demo-blk{padding:20px 12px 28px;border-top:4px solid #d8dee8}
.demo-h{font:700 15px/1.3 system-ui,sans-serif;padding:0 0 10px}
.demo-sec{background:#eef3fa;outline:1px dashed #7d93b2}
.demo-cap{background:#dfe8f5;box-shadow:inset 0 0 0 1px #b9c8de}
.demo-b{background:#fff4d6;box-shadow:inset 0 0 0 1px #e6c76a}
.demo-b2{background:#e3f3ea;box-shadow:inset 0 0 0 1px #8cc7a4}
.demo-l{box-shadow:inset 0 0 0 1px rgba(0,0,0,.3)}
.demo-l-wide{background:#dbeafe}.demo-l-content{background:#dcfce7}.demo-l-popout{background:#fef9c3}.demo-l-popout-start{background:#fde68a}.demo-l-popout-end{background:#fed7aa}
.demo-l-wide-start{background:#bfdbfe}.demo-l-wide-end{background:#c7d2fe}.demo-l-full{background:#fbcfe8}.demo-l-full-start{background:#f5d0fe}.demo-l-full-end{background:#e9d5ff}
.demo-row{display:flex;align-items:center;gap:12px;padding-block:6px;padding-inline:12px;background:#fff4d6}
.demo-bar-s{flex:none;display:block;block-size:16px;background:#60a5fa}
.demo-gw{display:block;padding-block:8px}
.demo-gm20{--pdk-grid-min:20ch}
.demo-gmp{inline-size:var(--pdk-grid-min)}
.demo-pr-c{inline-size:var(--pdk-measure)}.demo-pr-g{inline-size:var(--pdk-gutter)}.demo-pr-p{inline-size:var(--pdk-popout-space)}.demo-pr-w{inline-size:var(--pdk-site-width)}
.demo-w{max-inline-size:600px;inline-size:100%}
.demo-pw{display:block;inline-size:0;block-size:0;overflow:hidden}
.demo-pi{display:block;block-size:1px}
.demo-b>.demo-lbl{margin-block-start:6px}
.demo-lbl{display:block;margin:0;padding:4px 12px 6px;font:400 12px/1.4 ui-monospace,Consolas,monospace;letter-spacing:0;text-transform:none;color:#334155}
.demo-cap>.demo-lbl{padding-block:8px}
.demo-rd{display:inline-block;margin-inline-end:8px;padding:1px 6px;border-radius:3px}
.demo-ok{background:#d1fae5;color:#065f46}
.demo-rd-bad{background:#fecaca;color:#991b1b;font-weight:700}
CSS;

$js = <<<'JS'
(()=>{const R=document.getElementById('demo-root'),V0=+R.dataset.vmin,V1=+R.dataset.vmax,cs=e=>getComputedStyle(e),n=parseFloat,f2=v=>v.toFixed(2),
RS=cs(document.documentElement),RF=n(RS.fontSize),T=k=>RS.getPropertyValue(k).trim(),
CL=/^clamp\(\s*(-?[\d.]+)rem,\s*(-?[\d.]+)rem\s*([+-])\s*(-?[\d.]+)cqi,\s*(-?[\d.]+)rem\s*\)$/;
/* expected px of a token for container width W: var() chains, clamp(min, b ± a·W/100, max), rem */
const P=(k,W)=>{const v=T(k),m=v.match(/^var\((--[\w-]+)\)$/);if(m)return P(m[1],W);const c=v.match(CL);
 if(c){const a=(c[3]=='-'?-1:1)*n(c[4]);/* a is in cqi: a*W/100 is already px; min/max/b are rem */return Math.min(n(c[5])*RF,Math.max(n(c[1])*RF,n(c[2])*RF+a*W/100))}
 return /rem$/.test(v)?n(v)*RF:n(v)};
const E=k=>{const v=T(k),m=v.match(/^var\((--[\w-]+)\)$/);if(m)return E(m[1]);const c=v.match(CL);return c?[n(c[1])*RF,n(c[5])*RF]:[P(k,0),P(k,0)]};
const lp=(k)=>n(T('--pdk-leading-'+k))*RF,
 gw=e=>e.closest('.demo-gw'),gmp=e=>gw(e).querySelector('.demo-gmp').getBoundingClientRect().width,
 cols=e=>cs(gw(e).querySelector('.pdk-grid')).gridTemplateColumns.split(' ').filter(x=>n(x)>0.5).length;
const X=(s,el)=>s.replace(/\{(\w+)(?::([^}]*))?\}/g,(_,f,a)=>({
 fl:()=>{const[x,y]=E(a);return f2(x)+'px @'+V0+' → '+f2(y)+'px @'+V1},
 lr:()=>T('--pdk-lh-'+a)+' × font-size + '+f2(lp(a))+'px',
 tok:()=>T(a),px:()=>f2(P(a,innerWidth))+'px',
 gt:()=>cs(gw(el)).getPropertyValue('--pdk-grid-min').trim(),gm:()=>f2(gmp(el)),cols:()=>cols(el)}[f]||(()=>''))());
const fmt=v=>Array.isArray(v)?v.map(f2).join(' / '):typeof v=='number'?f2(v)+'px':v;
const H={
fs:(t,W,k)=>[n(cs(t).fontSize),P(k,W)],
lh:(t,W,k,kind)=>[n(cs(t).lineHeight),n(T('--pdk-lh-'+kind))*P(k,W)+lp(kind)],
ink:(t,W,k,m)=>{const q=n(cs(t).lineHeight)/n(cs(t).fontSize);return q>=m?['ink ≥ '+m,'ink ≥ '+m]:['ink '+f2(q)+' < '+m,'ink ≥ '+m]},
mt:(t,W,k)=>[n(cs(t).marginBlockStart),P(k,W)],
z:t=>[n(cs(t).marginBlockStart),0],
bw:(t,W,k)=>[t.firstElementChild.getBoundingClientRect().width,P(k,W)],
gap:(t,W,k)=>[n(cs(t).columnGap),P(k,W)],
disp:(t,W,v)=>[cs(t).display,v],ct:(t,W,v)=>[cs(t).containerType,v],
cw:t=>[t.firstElementChild.getBoundingClientRect().width,t.clientWidth],
cols:t=>{const g=n(cs(t).columnGap),m=gmp(t),w=t.clientWidth;
 return[''+cols(t),''+Math.min(t.children.length,Math.max(1,Math.floor((w+g)/(Math.min(m,w)+g))))]},
lane:(t,W,k)=>{const s=t.parentElement,S=s.clientWidth,pr=q=>s.querySelector('[data-pr='+q+']').getBoundingClientRect().width,
 g=pr('g'),a=S-2*g,c=Math.min(pr('c'),pr('w'),a),w=Math.min(pr('w'),a),p=c+2*Math.min(pr('p'),Math.max(0,(pr('w')-pr('c'))/2),(a-c)/4),
 T2={wide:[(S-w)/2,w],content:[(S-c)/2,c],popout:[(S-p)/2,p],'popout-start':[(S-p)/2,(p+c)/2],'popout-end':[(S-c)/2,(p+c)/2],
 'wide-start':[(S-w)/2,(w+c)/2],'wide-end':[(S-c)/2,(w+c)/2],full:[0,S],'full-start':[0,(S+w)/2],'full-end':[(S-w)/2,(S+w)/2]},
 r=t.getBoundingClientRect(),sr=s.getBoundingClientRect(),rtl=cs(s).direction=='rtl';
 return[[rtl?sr.right-r.right:r.left-sr.left,r.width],T2[k],.5]}};
function run(){let bad=0,tot=0;
 document.querySelectorAll('.demo-t').forEach(e=>{e.textContent=X(e.dataset.t,e)});
 document.querySelectorAll('[data-m]').forEach(el=>{
  const t=el.dataset.f?el.nextElementSibling:el,c=t.parentElement.closest('section:not(.pdk-off),.pdk-container'),W=c?c.clientWidth:innerWidth,
   box=el.querySelector('.demo-rds'),sec=t.closest('section');box.textContent='';
  JSON.parse(el.dataset.m).forEach(([k,...a])=>{const s=document.createElement('span');s.className='demo-rd';
   if(k=='info'){const p=sec&&sec.querySelector('[data-pr=c]'),r=q=>sec.querySelector('[data-pr='+q+']').getBoundingClientRect().width;
    s.textContent='width '+f2(W)+'px · viewport '+innerWidth+'px'+(p?' · measure='+f2(r('c'))+'px (1ch='+f2(r('c')/n(T('--pdk-measure')))+') · gutter '+f2(r('g'))+'px · popout-space '+f2(r('p'))+'px':'');
   }else{const[g,e2,tol=.1]=H[k](t,W,...a);tot++;
    const ok=Array.isArray(g)?g.every((x,i)=>Math.abs(x-e2[i])<=tol):typeof g=='number'?Math.abs(g-e2)<=tol:g===e2;
    s.classList.add(ok?'demo-ok':'demo-rd-bad');bad+=!ok;
    s.textContent=k+(k=='lane'?' start/width ':' ')+fmt(g)+(ok?' ✓':' ≠ expected '+fmt(e2)+' ✗')}
   box.append(s)})});
 const b=document.getElementById('demo-status');b.textContent='viewport '+innerWidth+'px · '+tot+' checks · mismatches: '+bad;
 b.classList.toggle('demo-bar-bad',bad>0);R.dataset.bad=bad;R.dataset.tot=tot}
addEventListener('resize',run);addEventListener('load',run);document.fonts&&document.fonts.ready.then(run);run()})();
JS;

// Gegenereerde klassen (Oxygen's html-to-page laat inline style="" vallen).
foreach ( $barcss as $vc => $var ) { $css .= "\n.demo-sp-" . $vc . '{inline-size:var(' . $var . ')}'; }
for ( $s = -(int) $c['steps_down']; $s <= (int) $c['steps_up']; $s++ ) { $css .= "\n.demo-st-" . str_replace( '-', 'm', (string) $s ) . '{font-size:var(--pdk-step-' . $s . ')}'; }
$js = "document.querySelectorAll('.demo-b').forEach(e=>{if([...e.parentElement.children].indexOf(e)%2)e.classList.add('demo-b2')});" . $js;

$intro = 'Every element shows its expected value and, in the coloured pill, the live measured value. The expected values are NOT baked in: the script reads the tokens on :root (--pdk-step-*, --pdk-space-*, --pdk-lh-*, --pdk-leading-*, --pdk-grid-min, …), evaluates clamp(min, b ± a·W/100, max) for the container width W and compares. Green = equal within tolerance (0.1px; lanes 0.5px), red = mismatch. Resize the window: the bar at the top counts mismatches. After a settings change only the page itself needs a reload (only the viewport range and the list of steps are baked in). Tints: section #eef3fa, each lane its own colour; the gap between tinted blocks is a margin.';
$html  = '<style>' . $css . '</style><div class="demo-root" id="demo-root" data-vmin="' . (int) $c['min_viewport'] . '" data-vmax="' . (int) $c['max_viewport'] . '">'
	. '<div class="demo-bar" id="demo-status" role="status">…</div>'
	. '<div class="demo-intro">' . $e( $intro ) . ' <span class="demo-t" data-t="Current tokens: viewport ' . (int) $c['min_viewport'] . '→' . (int) $c['max_viewport'] . 'px (data-vmin/data-vmax), step-0 = {fl:--pdk-step-0}, line-height headings {lr:heading}, p {lr:body}, grid-min {tok:--pdk-grid-min}."></span></div>'
	. implode( '', $blocks ) . '</div>';

// Oxygen-pagina via de eigen abilities (oxygen/create-post, html-to-page, edit-post); Oxygen moet actief zijn.
foreach ( [ 'create-post', 'html-to-page', 'edit-post' ] as $a ) { if ( ! wp_get_ability( "oxygen/$a" ) ) { fwrite( STDERR, "ability oxygen/$a ontbreekt (Oxygen actief?)\n" ); exit( 1 ); } }
$old = get_page_by_path( 'css-framework-demo', OBJECT, 'page' );
if ( $old ) { wp_delete_post( $old->ID, true ); } // html-to-page voegt alleen toe: dus opnieuw aanmaken i.p.v. bijwerken
$r = wp_get_ability( 'oxygen/create-post' )->execute( [ 'title' => 'CSS Framework demo', 'status' => 'publish', 'post_type' => 'page' ] );
if ( is_wp_error( $r ) ) { fwrite( STDERR, $r->get_error_message() . "\n" ); exit( 1 ); }
$id = (int) ( $r['post_id'] ?? $r['id'] ?? 0 );
wp_update_post( [ 'ID' => $id, 'post_name' => 'css-framework-demo' ] );
$r = wp_get_ability( 'oxygen/html-to-page' )->execute( [ 'post_id' => $id, 'html' => $html ] );
if ( is_wp_error( $r ) ) { fwrite( STDERR, $r->get_error_message() . "\n" ); exit( 1 ); }
echo 'warnings: ' . wp_json_encode( $r['warnings'] ?? [] ) . "\n";
$r = wp_get_ability( 'oxygen/edit-post' )->execute( [ 'post_id' => $id, 'operations' => [ [ 'op' => 'insert', 'payload' => [ 'element_type' => 'OxygenElements\JavaScriptCode', 'properties' => [ 'content' => [ 'content' => [ 'javascript_code' => $js ] ] ] ] ] ] ] );
if ( is_wp_error( $r ) ) { fwrite( STDERR, $r->get_error_message() . "\n" ); exit( 1 ); }
echo 'page ' . $id . ' → ' . get_permalink( $id ) . "\n";
