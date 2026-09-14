<?php
/**
 * Zelftest voor de zuivere logica van de IMGX-module: bestandsnaamgeving,
 * verwijdergaranties, formaatherkenning, srcset-mapping en de markup-omzetting.
 * Draait op kale PHP met een handvol WordPress-stubs, dus zonder WordPress:
 *
 *   php tests/test-imgx.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

// Pad naar de plugin; de tests staan bewust buiten de map die uitgeleverd wordt.
$pdk = dirname( __DIR__ ) . '/pdk-theme-options';

define( 'ABSPATH', __DIR__ . '/' );
define( 'IMGX_VERSION', '1.0.0' );

$GLOBALS['imgx_uploads_base'] = str_replace( '\\', '/', sys_get_temp_dir() ) . '/imgx-smoke-uploads';
@mkdir( $GLOBALS['imgx_uploads_base'] . '/2026/09', 0777, true );

$GLOBALS['imgx_options'] = array();
$GLOBALS['imgx_meta']    = array();
$GLOBALS['imgx_filters'] = array();

function add_filter( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['imgx_filters'][ $tag ][] = $cb; return true; }
function remove_filter( $tag, $cb, $prio = 10 ) { return true; }
function add_action( ...$a ) { return true; }
function do_action( ...$a ) {}
function apply_filters( $tag, $value, ...$args ) {
	foreach ( $GLOBALS['imgx_filters'][ $tag ] ?? array() as $cb ) { $value = $cb( $value, ...$args ); }
	return $value;
}
function __( $s, $d = null ) { return $s; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return str_replace( '&', '&#038;', (string) $s ); }
function wp_specialchars_decode( $s, $q = null ) { return html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_basename( $p, $suffix = '' ) { return urldecode( basename( str_replace( array( '\\', '://' ), '/', $p ), $suffix ) ); }
function wp_get_upload_dir() { return array( 'basedir' => $GLOBALS['imgx_uploads_base'], 'baseurl' => 'https://example.org/uploads', 'error' => false ); }
function get_option( $k, $d = false ) { return $GLOBALS['imgx_options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['imgx_options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['imgx_options'][ $k ] ); return true; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['imgx_meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $v ) { $GLOBALS['imgx_meta'][ $id ][ $key ] = $v; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['imgx_meta'][ $id ][ $key ] ); return true; }
function wp_get_attachment_metadata( $id ) { return $GLOBALS['imgx_attachment_meta'] ?? array(); }
function get_attached_file( $id ) { return $GLOBALS['imgx_uploads_base'] . '/2026/09/photo.jpg'; }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function did_action( $t ) { return 1; }
function is_feed() { return false; }
function is_embed() { return false; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_delete_file( $p ) { @unlink( $p ); }
function wp_image_editor_supports( $a ) { return true; }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( $u ) : parse_url( $u, $c ); }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function get_post_type( $id ) { return $GLOBALS['imgx_post_type'][ $id ] ?? 'attachment'; }
function get_post_mime_type( $id ) { return $GLOBALS['imgx_post_mime'][ $id ] ?? 'image/jpeg'; }
function add_option( $k, $v, $d = '', $a = 'no' ) { if ( isset( $GLOBALS['imgx_options'][ $k ] ) ) { return false; } $GLOBALS['imgx_options'][ $k ] = $v; return true; }
function wp_next_scheduled( $hook, $args = array() ) { return false; }
function wp_schedule_single_event( $ts, $hook, $args = array() ) {}
function wp_unschedule_hook( $hook ) {}
class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

/** Minimal $wpdb stub: resolves _wp_attached_file lookups from a fixture map. */
class IMGX_Smoke_WPDB {
	public $postmeta = 'wp_postmeta';
	public $attached = array();
	public $queries  = 0;
	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
		foreach ( $args as $a ) {
			$sql = preg_replace( '/%s/', "'" . addslashes( (string) $a ) . "'", $sql, 1 );
		}
		return $sql;
	}
	public function get_results( $sql ) {
		$this->queries++;
		preg_match_all( "/'([^']*)'/", $sql, $m );
		$out = array();
		foreach ( $m[1] as $path ) {
			if ( isset( $this->attached[ $path ] ) ) {
				$out[] = (object) array( 'post_id' => $this->attached[ $path ], 'meta_value' => $path );
			}
		}
		return $out;
	}
}
$GLOBALS['wpdb'] = new IMGX_Smoke_WPDB();


$plugin_dir = $pdk . '/modules/imgx/includes/';
foreach ( array( 'files', 'settings', 'capabilities', 'variants', 'logger', 'converter', 'picture-renderer', 'generator' ) as $c ) {
	require_once $plugin_dir . 'class-' . $c . '.php';
}

$pass = 0;
$fail = 0;
function check( $label, $condition, $extra = '' ) {
	global $pass, $fail;
	if ( $condition ) { ++$pass; return; }
	++$fail;
	echo "FAIL: {$label}" . ( $extra ? "\n      {$extra}" : '' ) . "\n";
}

/* ---------- Files ---------- */

$base = $GLOBALS['imgx_uploads_base'];

check( 'sidecar simple', IMGX\Files::sidecar_path( "$base/2026/09/photo.jpg", 'webp' ) === "$base/2026/09/photo.webp" );
check( 'sidecar dotted name', IMGX\Files::sidecar_path( "$base/2026/09/my.photo.final.jpg", 'avif' ) === "$base/2026/09/my.photo.final.avif" );
check( 'sidecar uppercase ext', IMGX\Files::sidecar_path( "$base/2026/09/image.JPG", 'webp' ) === "$base/2026/09/image.webp" );
check( 'sidecar jpeg ext', IMGX\Files::sidecar_path( "$base/2026/09/special-name.jpeg", 'avif' ) === "$base/2026/09/special-name.avif" );

file_put_contents( "$base/2026/09/collide.webp", 'foreign' );
check(
	'collision falls back',
	IMGX\Files::sidecar_path( "$base/2026/09/collide.png", 'webp', array(), 7 ) === "$base/2026/09/collide.png.webp",
	IMGX\Files::sidecar_path( "$base/2026/09/collide.png", 'webp', array(), 7 )
);
check( 'owned file reused', IMGX\Files::sidecar_path( "$base/2026/09/collide.jpg", 'webp', array( 'collide.webp' ), 7 ) === "$base/2026/09/collide.webp" );

file_put_contents( "$base/2026/09/keepme.jpg", 'x' );
check( 'never deletes originals', false === IMGX\Files::delete_variant( "$base/2026/09/keepme.jpg" ) && file_exists( "$base/2026/09/keepme.jpg" ) );
check( 'refuses traversal', false === IMGX\Files::delete_variant( "$base/../evil.webp" ) );
check( 'deletes own variant', true === IMGX\Files::delete_variant( "$base/2026/09/collide.webp" ) );

check( 'url swap keeps query', IMGX\Files::swap_basename_in_url( 'https://cdn.x/u/photo.jpg?v=3#f', 'photo.avif' ) === 'https://cdn.x/u/photo.avif?v=3#f' );
check( 'basename from url', IMGX\Files::basename_from_url( 'https://x/a/b/photo.jpg?x=1#y' ) === 'photo.jpg' );
check( 'basename urldecoded', IMGX\Files::basename_from_url( 'https://x/a/my%20photo.jpg' ) === 'my photo.jpg' );

$png = "$base/2026/09/probe.png";
file_put_contents( $png, "\x89PNG\r\n\x1a\n" . str_repeat( "\0", 32 ) );
check( 'sniff png', IMGX\Files::sniff_format( $png ) === 'png' );
copy( $png, "$base/2026/09/lying.webp" );
check( 'sniff ignores extension', IMGX\Files::sniff_format( "$base/2026/09/lying.webp" ) === 'png' );
check( 'sniff missing file', IMGX\Files::sniff_format( "$base/nope.webp" ) === '' );

file_put_contents( "$base/2026/09/real.webp", 'RIFF' . "\x20\0\0\0" . 'WEBPVP8 ' . str_repeat( "\0", 16 ) );
check( 'sniff webp', IMGX\Files::sniff_format( "$base/2026/09/real.webp" ) === 'webp' );
file_put_contents( "$base/2026/09/real.avif", "\0\0\0\x20" . 'ftypavif' . str_repeat( "\0", 16 ) );
check( 'sniff avif', IMGX\Files::sniff_format( "$base/2026/09/real.avif" ) === 'avif' );
file_put_contents( "$base/2026/09/real.jpg", "\xFF\xD8\xFF\xE0" . str_repeat( "\0", 16 ) );
check( 'sniff jpeg', IMGX\Files::sniff_format( "$base/2026/09/real.jpg" ) === 'jpeg' );
file_put_contents( "$base/2026/09/empty.webp", '' );
check( 'sniff empty file', IMGX\Files::sniff_format( "$base/2026/09/empty.webp" ) === '' );

/* ---------- Picture_Renderer ---------- */

$GLOBALS['imgx_attachment_meta'] = array(
	'file'  => '2026/09/photo.jpg',
	'sizes' => array(
		'medium' => array( 'file' => 'photo-300x200.jpg' ),
		'large'  => array( 'file' => 'photo-1024x683.jpg' ),
	),
);

IMGX\Variants::save(
	42,
	array(
		'sizes' => array(
			'full'   => array( 'avif' => 'photo.avif', 'webp' => 'photo.webp' ),
			'medium' => array( 'avif' => 'photo-300x200.avif', 'webp' => 'photo-300x200.webp' ),
			'large'  => array( 'avif' => 'photo-1024x683.avif', 'webp' => 'photo-1024x683.webp' ),
		),
	)
);

update_option( IMGX\Settings::OPTION, IMGX\Settings::defaults() );
update_option( IMGX\Capabilities::OPTION, array( 'avif' => true, 'webp' => true, 'fingerprint' => 'x', 'notes' => array() ) );

$settings = new IMGX\Settings();
$caps     = new class( new IMGX\Logger() ) extends IMGX\Capabilities {
	public function get() { return array( 'avif' => true, 'webp' => true, 'editor' => 'GD', 'notes' => array() ); }
};
$renderer = new IMGX\Picture_Renderer( $settings, $caps );

$img = '<img fetchpriority="high" decoding="async" width="1024" height="683"'
	. ' src="https://example.org/uploads/2026/09/photo-1024x683.jpg"'
	. ' class="wp-image-42 size-large" alt="A &quot;quoted&quot; alt" title="T"'
	. ' style="border-radius:4px" data-lazy="off" aria-describedby="c1" loading="lazy"'
	. ' srcset="https://example.org/uploads/2026/09/photo-300x200.jpg 300w,'
	. ' https://example.org/uploads/2026/09/photo-1024x683.jpg 1024w,'
	. ' https://example.org/uploads/2026/09/photo.jpg 1600w"'
	. ' sizes="(max-width: 1024px) 100vw, 1024px" />';

$html = $renderer->wrap_img( $img, 42, 'test' );

check( 'wraps in picture', 0 === strpos( $html, '<picture' ) && substr( $html, -10 ) === '</picture>' );
check( 'img preserved verbatim', false !== strpos( $html, $img ) );
check( 'avif before webp', strpos( $html, 'image/avif' ) < strpos( $html, 'image/webp' ) );
check( 'webp before img', strpos( $html, 'image/webp' ) < strpos( $html, '<img' ) );
check( 'avif candidates', false !== strpos( $html, 'photo-300x200.avif 300w' ) && false !== strpos( $html, 'photo.avif 1600w' ) );
check( 'sizes on all three', 3 === substr_count( $html, 'sizes="(max-width: 1024px) 100vw, 1024px"' ), $html );

// Missing candidate must be dropped, never guessed.
$v = IMGX\Variants::get( 42 );
unset( $v['sizes']['large']['avif'] );
IMGX\Variants::save( 42, $v );
IMGX\Variants::flush_cache( 42 );
$html2 = $renderer->wrap_img( $img, 42, 'test' );
check( 'missing candidate dropped', false === strpos( $html2, 'photo-1024x683.avif' ) );
check( 'other avif candidates kept', false !== strpos( $html2, 'photo-300x200.avif 300w' ) );
check( 'webp untouched', false !== strpos( $html2, 'photo-1024x683.webp 1024w' ) );

// Format with zero candidates must be omitted entirely.
$v = IMGX\Variants::get( 42 );
foreach ( array_keys( $v['sizes'] ) as $s ) { unset( $v['sizes'][ $s ]['avif'] ); }
IMGX\Variants::save( 42, $v );
IMGX\Variants::flush_cache( 42 );
$html3 = $renderer->wrap_img( $img, 42, 'test' );
check( 'empty format omitted', false === strpos( $html3, 'image/avif' ) && false !== strpos( $html3, 'image/webp' ) );

// Restore full registry.
IMGX\Variants::save( 42, array( 'sizes' => array(
	'full'   => array( 'avif' => 'photo.avif', 'webp' => 'photo.webp' ),
	'medium' => array( 'avif' => 'photo-300x200.avif', 'webp' => 'photo-300x200.webp' ),
	'large'  => array( 'avif' => 'photo-1024x683.avif', 'webp' => 'photo-1024x683.webp' ),
) ) );
IMGX\Variants::flush_cache( 42 );

// External / data URLs.
$ext = '<img src="https://external-site.com/image.jpg" class="wp-image-42" alt="" />';
check( 'external url untouched', $renderer->wrap_img( $ext, 42, 't' ) === $ext );
$data = '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" class="wp-image-42" alt="" />';
check( 'data url untouched', $renderer->wrap_img( $data, 42, 't' ) === $data );

// src only, no srcset.
$srconly = '<img src="https://example.org/uploads/2026/09/photo-300x200.jpg" class="wp-image-42" alt="" />';
$h = $renderer->wrap_img( $srconly, 42, 't' );
check( 'src-only builds source', false !== strpos( $h, 'srcset="https://example.org/uploads/2026/09/photo-300x200.avif"' ), $h );
check( 'src-only has no sizes', false === strpos( str_replace( $srconly, '', $h ), 'sizes=' ) );

// Content transformation.
$content = '<figure class="wp-block-image">' . $img . '<figcaption>Hi</figcaption></figure>';
$t = $renderer->transform( $content );
check( 'content wrapped in place', false !== strpos( $t, '<figure class="wp-block-image"><picture' ) && false !== strpos( $t, '</picture><figcaption>Hi</figcaption>' ) );
check( 'single picture', 1 === substr_count( $t, '<picture' ) );

// Nested picture prevention.
$existing = '<picture><source type="image/webp" srcset="x.webp">' . $img . '</picture>';
check( 'existing picture untouched', $renderer->transform( $existing ) === $existing );

// Image outside an existing picture is still handled.
$mixed = '<picture>' . $img . '</picture><p>t</p>' . $img;
$tm = $renderer->transform( $mixed );
check( 'mixed content', 2 === substr_count( $tm, '<picture' ) && 1 === substr_count( $tm, 'imgx-picture' ), $tm );

// No wp-image class and no matching attachment -> left alone.
$plain = '<img src="https://example.org/uploads/2026/09/photo.jpg" alt="" />';
check( 'unknown image ignored', $renderer->transform( $plain ) === $plain );

/* ---------- page builder markup (Oxygen 6 / Breakdance / Bricks) ---------- */

// These builders emit their own <img> with no wp-image-<id> class, so the attachment has
// to be resolved from the URL. Teach the stub which file belongs to attachment 42.
$GLOBALS['wpdb']->attached = array( '2026/09/photo.jpg' => 42 );
$renderer->reset();

$oxy = '<img class="oxy-image-2-100 oxy-image"'
	. ' src="https://example.org/uploads/2026/09/photo-1024x683.jpg" loading="lazy"'
	. ' srcset="https://example.org/uploads/2026/09/photo-300x200.jpg 300w,'
	. ' https://example.org/uploads/2026/09/photo-1024x683.jpg 1024w"'
	. ' sizes="(max-width: 1024px) 100vw, 1024px">';

$h = $renderer->transform( $oxy, 'oxygen' );
check( 'builder img resolved by url', false !== strpos( $h, 'imgx-picture' ), $h );
check( 'builder img keeps original', false !== strpos( $h, $oxy ) );
check( 'builder avif srcset', false !== strpos( $h, 'photo-300x200.avif 300w' ) && false !== strpos( $h, 'photo-1024x683.avif 1024w' ) );
check( 'builder webp srcset', false !== strpos( $h, 'photo-300x200.webp 300w' ) );

// A sub-size src must resolve through the full-size _wp_attached_file row.
$sub = '<img class="oxy-image" src="https://example.org/uploads/2026/09/photo-300x200.jpg" alt="">';
check( 'sub-size src resolves', false !== strpos( $renderer->transform( $sub, 'oxygen' ), 'photo-300x200.avif' ) );

// A foreign host must never be rewritten: the variant would 404 over there.
$foreign = '<img class="oxy-image" src="https://someone-else.example/uploads/2026/09/photo.jpg" alt="">';
check( 'foreign host not rewritten', $renderer->transform( $foreign, 'oxygen' ) === $foreign );

// Protocol-relative and root-relative URLs are still ours.
$rootrel = '<img class="oxy-image" src="/uploads/2026/09/photo.jpg" alt="">';
check( 'root-relative url resolves', false !== strpos( $renderer->transform( $rootrel, 'oxygen' ), 'photo.avif' ) );

// Data URIs and placeholders must not hit the database at all.
$renderer->reset();
$GLOBALS['wpdb']->queries = 0;
$lazy = '<img class="oxy-image" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="">';
check( 'data uri untouched', $renderer->transform( $lazy, 'oxygen' ) === $lazy );
check( 'data uri costs no query', 0 === $GLOBALS['wpdb']->queries );

// Batching: many builder images on one page must cost a single query.
$renderer->reset();
$GLOBALS['wpdb']->queries = 0;
$page = str_repeat( $oxy . '<p>x</p>', 12 );
$out  = $renderer->transform( $page, 'oxygen' );
check( 'all builder images wrapped', 12 === substr_count( $out, 'imgx-picture' ), substr_count( $out, 'imgx-picture' ) . ' wrapped' );
check( 'batched into one query', 1 === $GLOBALS['wpdb']->queries, $GLOBALS['wpdb']->queries . ' queries' );

// Repeated documents in one request (header, body, footer) reuse the cache.
$GLOBALS['wpdb']->queries = 0;
$renderer->transform( $oxy, 'oxygen' );
$renderer->transform( $oxy, 'oxygen' );
check( 'lookups cached per request', 0 === $GLOBALS['wpdb']->queries, $GLOBALS['wpdb']->queries . ' queries' );

// A builder image already inside a theme picture is still left alone.
$wrapped = '<picture><source type="image/webp" srcset="x.webp">' . $oxy . '</picture>';
check( 'builder img inside picture untouched', $renderer->transform( $wrapped, 'oxygen' ) === $wrapped );

// Oxygen Classic markup: id attribute, ct-image class, no wp-image class.
$classic = '<img  id="image-2-2" alt="" src="https://example.org/uploads/2026/09/photo.jpg"'
	. ' class="ct-image" srcset="https://example.org/uploads/2026/09/photo.jpg 400w,'
	. ' https://example.org/uploads/2026/09/photo-300x200.jpg 300w"'
	. ' sizes="(max-width: 400px) 100vw, 400px" />';

$h = $renderer->transform( $classic, 'oxygen-classic' );
check( 'oxygen classic wrapped', false !== strpos( $h, 'imgx-picture' ), $h );
check( 'oxygen classic keeps id and class', false !== strpos( $h, 'id="image-2-2"' ) && false !== strpos( $h, 'class="ct-image"' ) );
check( 'oxygen classic avif srcset', false !== strpos( $h, 'photo.avif 400w' ) && false !== strpos( $h, 'photo-300x200.avif 300w' ) );
check( 'oxygen classic img verbatim', false !== strpos( $h, $classic ) );

/* ---------- the image's classes are carried over to <picture> ---------- */

check( 'picture inherits builder class', false !== strpos( $h, '<picture class="imgx-picture ct-image">' ), $h );
check( 'picture never inherits the id', false === strpos( $h, '<picture class="imgx-picture ct-image" id=' ) );

// Multiple classes keep their order, imgx-picture first.
$multi = '<img class="oxy-image-2-100 oxy-image" src="https://example.org/uploads/2026/09/photo.jpg" alt="">';
check(
	'picture inherits every class',
	false !== strpos( $renderer->transform( $multi, 'oxygen' ), '<picture class="imgx-picture oxy-image-2-100 oxy-image">' ),
	$renderer->transform( $multi, 'oxygen' )
);

// A class attribute is not required.
$noclass = '<img src="https://example.org/uploads/2026/09/photo.jpg" alt="">';
check( 'no class attribute still works', false !== strpos( $renderer->transform( $noclass, 'oxygen' ), '<picture class="imgx-picture">' ) );

// Escaped entities in the class attribute survive one decode/encode round trip.
$entity = '<img class="a&amp;b keep" src="https://example.org/uploads/2026/09/photo.jpg" alt="">';
check( 'class entities not double-escaped', false !== strpos( $renderer->transform( $entity, 'oxygen' ), 'class="imgx-picture a&amp;b keep"' ), $renderer->transform( $entity, 'oxygen' ) );

// Duplicates are collapsed.
$dupe = '<img class="ct-image ct-image" src="https://example.org/uploads/2026/09/photo.jpg" alt="">';
check( 'duplicate classes collapsed', false !== strpos( $renderer->transform( $dupe, 'oxygen' ), '<picture class="imgx-picture ct-image">' ) );

// Developers can drop a class from the wrapper without touching the img.
$GLOBALS['imgx_filters']['imgx_picture_class'][] = function ( $classes ) {
	return array_values( array_diff( $classes, array( 'ct-image' ) ) );
};
$filtered = $renderer->transform( $classic, 'oxygen-classic' );
check( 'imgx_picture_class can drop a class', false !== strpos( $filtered, '<picture class="imgx-picture">' ), $filtered );
check( 'filter leaves the img class alone', false !== strpos( $filtered, 'class="ct-image"' ) );
$GLOBALS['imgx_filters']['imgx_picture_class'] = array();

$GLOBALS['wpdb']->attached = array();
$renderer->reset();

// Kill switch.
update_option( IMGX\Settings::OPTION, array_merge( IMGX\Settings::defaults(), array( 'picture_enabled' => 0 ) ) );
$settings->flush_cache();
check( 'kill switch', false === $renderer->is_active() && $renderer->filter_content( $content ) === $content );
update_option( IMGX\Settings::OPTION, IMGX\Settings::defaults() );
$settings->flush_cache();

// Registry rejects paths and unknown formats.
$clean = IMGX\Variants::normalize( array( 'sizes' => array(
	'full'   => array( 'webp' => '../../evil.webp' ),
	'medium' => array( 'webp' => 'photo-300x200.webp' ),
	'large'  => array( 'jxl' => 'photo.jxl' ),
) ) );
check( 'registry rejects paths', ! isset( $clean['sizes']['full'] ) );
check( 'registry keeps basenames', $clean['sizes']['medium']['webp'] === 'photo-300x200.webp' );
check( 'registry rejects unknown format', ! isset( $clean['sizes']['large'] ) );

// Registratie bewaart de instellingen-vingerafdruk, en weigert onzin. Daarop
// hangt het opnieuw proberen van een variant die eerder te groot uitviel.
$sig = IMGX\Variants::normalize( array( 'signature' => 'avif_webp-82-50' ) );
check( 'registry keeps signature', $sig['signature'] === 'avif_webp-82-50' );
check( 'registry rejects non-string signature', IMGX\Variants::normalize( array( 'signature' => array( 'x' ) ) )['signature'] === '' );
check( 'signature defaults empty', IMGX\Variants::normalize( array() )['signature'] === '' );
check( 'signature tracks quality', $settings->encoding_signature() === 'avif_webp-82-50' );

update_option( IMGX\Settings::OPTION, array_merge( IMGX\Settings::defaults(), array( 'avif_quality' => 30 ) ) );
$settings->flush_cache();
check( 'signature changes with quality', $settings->encoding_signature() === 'avif_webp-82-30' );
update_option( IMGX\Settings::OPTION, IMGX\Settings::defaults() );
$settings->flush_cache();

// Settings sanitisation.
$s = $settings->sanitize( array( 'mode' => 'jpegxl', 'webp_quality' => '250', 'avif_quality' => '-9', 'upload_mode' => '../etc' ) );
check( 'sanitize mode', $s['mode'] === 'avif_webp' );
check( 'sanitize quality clamp', $s['webp_quality'] === 100 && $s['avif_quality'] === 1 );
check( 'sanitize upload mode', $s['upload_mode'] === 'async' );
check( 'sanitize non-array', $settings->sanitize( 'nope' ) === IMGX\Settings::defaults() );
check( 'format order avif first', $settings->requested_formats() === array( 'avif', 'webp' ) );

/* ---------- D-3: intermediate wp_create_image_subsizes() saves must not prune ---------- */

// wp_create_image_subsizes() saves metadata after every sub-size since WP 5.3. Reproduce
// that call stack for real: this function calls the filter exactly like WordPress core does,
// so Generator::is_intermediate_metadata_save() sees it on the backtrace.
function wp_create_image_subsizes( $metadata, $attachment_id ) {
	return apply_filters( 'wp_update_attachment_metadata', $metadata, $attachment_id );
}

$settings_gen = new IMGX\Settings();
update_option( IMGX\Settings::OPTION, array_merge( IMGX\Settings::defaults(), array( 'generate_on_upload' => 0 ) ) );
$settings_gen->flush_cache();

$caps_gen = new class( new IMGX\Logger() ) extends IMGX\Capabilities {
	public function get() { return array( 'avif' => true, 'webp' => true, 'editor' => 'GD', 'notes' => array() ); }
};
$generator = new IMGX\Generator( $settings_gen, $caps_gen, new IMGX\Converter( $settings_gen, $caps_gen, new IMGX\Logger() ), new IMGX\Logger() );
$generator->register_hooks();

// Attachment #11: an original plus the three sub-sizes it will end up with. The generic
// get_attached_file() stub always returns .../photo.jpg regardless of $id, so the original
// is created under that exact name.
foreach ( array( 'photo.jpg', 'photo11-medium.jpg', 'photo11-thumb.jpg', 'photo11-banner.jpg' ) as $f ) {
	file_put_contents( "$base/2026/09/$f", 'x' );
}

// The registry already has all four sizes recorded from an earlier, completed run.
IMGX\Variants::save(
	11,
	array(
		'sizes' => array(
			'full'      => array( 'webp' => 'photo.webp' ),
			'medium'    => array( 'webp' => 'photo11-medium.webp' ),
			'thumbnail' => array( 'webp' => 'photo11-thumb.webp' ),
			'pdk_banner'=> array( 'webp' => 'photo11-banner.webp' ),
		),
	)
);
$registry_before = IMGX\Variants::get( 11 );

// Measured sequence: wp_create_image_subsizes() saves progressively incomplete metadata.
$m0 = array( 'file' => '2026/09/photo.jpg', 'sizes' => array() );
wp_create_image_subsizes( $m0, 11 );
check( 'D-3: sizes=0 intermediate save leaves the registry untouched', IMGX\Variants::get( 11 ) === $registry_before );

$m1 = array( 'file' => '2026/09/photo.jpg', 'sizes' => array( 'medium' => array( 'file' => 'photo11-medium.jpg' ) ) );
wp_create_image_subsizes( $m1, 11 );
check( 'D-3: sizes=1 intermediate save leaves the registry untouched', IMGX\Variants::get( 11 ) === $registry_before );

$m2 = array(
	'file'  => '2026/09/photo.jpg',
	'sizes' => array(
		'medium'    => array( 'file' => 'photo11-medium.jpg' ),
		'thumbnail' => array( 'file' => 'photo11-thumb.jpg' ),
	),
);
wp_create_image_subsizes( $m2, 11 );
check( 'D-3: sizes=2 intermediate save leaves the registry untouched', IMGX\Variants::get( 11 ) === $registry_before );

$m3 = array(
	'file'  => '2026/09/photo.jpg',
	'sizes' => array(
		'medium'     => array( 'file' => 'photo11-medium.jpg' ),
		'thumbnail'  => array( 'file' => 'photo11-thumb.jpg' ),
		'pdk_banner' => array( 'file' => 'photo11-banner.jpg' ),
	),
);
wp_create_image_subsizes( $m3, 11 );
check( 'D-3: sizes=3 intermediate save (still inside wp_create_image_subsizes) leaves the registry untouched', IMGX\Variants::get( 11 ) === $registry_before );

// Final save with the exact same, complete metadata, but this time NOT on the
// wp_create_image_subsizes call stack. Nothing is actually stale, so nothing should change.
apply_filters( 'wp_update_attachment_metadata', $m3, 11 );
check( 'D-3: final save with an unchanged, complete size set leaves the registry untouched', IMGX\Variants::get( 11 ) === $registry_before );

check( 'D-3: sidecar files were never deleted during the intermediate saves', file_exists( "$base/2026/09/photo11-banner.webp" ) === false /* never created */ && file_exists( "$base/2026/09/photo11-thumb.jpg" ) );

// The fix must not disable pruning altogether: a final save (outside the
// wp_create_image_subsizes stack) that genuinely no longer lists pdk_banner must still drop it.
$m_final_without_banner = array(
	'file'  => '2026/09/photo.jpg',
	'sizes' => array(
		'medium'    => array( 'file' => 'photo11-medium.jpg' ),
		'thumbnail' => array( 'file' => 'photo11-thumb.jpg' ),
	),
);
apply_filters( 'wp_update_attachment_metadata', $m_final_without_banner, 11 );
$registry_after = IMGX\Variants::get( 11 );
check( 'D-3: a real final save still prunes a size that genuinely disappeared', ! isset( $registry_after['sizes']['pdk_banner'] ) );
check( 'D-3: the real final save keeps the sizes that are still present', isset( $registry_after['sizes']['medium'] ) && isset( $registry_after['sizes']['thumbnail'] ) && isset( $registry_after['sizes']['full'] ) );

// An attachment that never had any sidecars must behave exactly as before the fix: the
// early "nothing generated yet" return, regardless of whether it happens on the
// wp_create_image_subsizes stack or not.
$empty_registry = IMGX\Variants::get( 999 );
check( 'D-3: attachment with no prior variants has an empty registry to start with', empty( $empty_registry['sizes'] ) && empty( $empty_registry['skipped'] ) );
wp_create_image_subsizes( $m0, 999 );
check( 'D-3: attachment with no prior variants stays untouched after an intermediate save', IMGX\Variants::get( 999 ) === $empty_registry );
apply_filters( 'wp_update_attachment_metadata', $m3, 999 );
check( 'D-3: attachment with no prior variants stays untouched after a final save too', IMGX\Variants::get( 999 ) === $empty_registry );

update_option( IMGX\Settings::OPTION, IMGX\Settings::defaults() );
$settings_gen->flush_cache();

echo "\n{$pass} passed, {$fail} failed\n";

// Cleanup.
array_map( 'unlink', glob( "$base/2026/09/*" ) );
exit( $fail > 0 ? 1 : 0 );
