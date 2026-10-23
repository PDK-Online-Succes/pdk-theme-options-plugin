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
function add_action( ...$a ) { $GLOBALS['imgx_actions'][] = array( $a[0], $a[1] ?? null ); return true; }
function do_action( ...$a ) {}
function apply_filters( $tag, $value, ...$args ) {
	foreach ( $GLOBALS['imgx_filters'][ $tag ] ?? array() as $cb ) { $value = $cb( $value, ...$args ); }
	return $value;
}
function __( $s, $d = null ) { return $s; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); } // like WP: no double-encoding
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
function get_attached_file( $id ) { return $GLOBALS['imgx_attached'][ $id ] ?? $GLOBALS['imgx_uploads_base'] . '/2026/09/photo.jpg'; }
function is_admin() { return ! empty( $GLOBALS['imgx_is_admin'] ); }
function wp_doing_ajax() { return false; }
function did_action( $t ) { return 1; }
function is_feed() { return ! empty( $GLOBALS['imgx_is_feed'] ); }
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

/* ==========================================================================
 * LQIP (docs/imgx-lqip-brief.md). Acceptance tests, written before the
 * implementation (Mode A): every check below is expected-red until the
 * feature exists. A missing class member makes the check FAIL, never abort
 * the file: all calls into not-yet-existing API go through lq_call().
 * ========================================================================== */

/** Runs $fn; a thrown Throwable (missing method/constant, TypeError) yields $default. */
function lq_call( callable $fn, $default = null ) {
	try {
		return $fn();
	} catch ( \Throwable $e ) {
		return $default;
	}
}

/** check() for a closure that may touch missing API. */
function lq_check( $label, callable $fn ) {
	check( $label, (bool) lq_call( $fn, false ), 'false, or threw (symbol missing)' );
}

function lq_img_tag( $html ) {
	return preg_match( '#<img\b[^>]*>#i', (string) $html, $m ) ? $m[0] : '';
}

/** Decoded value of the style attribute of a tag, or null. */
function lq_style( $tag ) {
	return preg_match( '#\sstyle\s*=\s*(["\'])(.*?)\1#is', (string) $tag, $m ) ? html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) : null;
}

/** Whether a decoded style carries the four AC-106 declarations for $url. */
function lq_has_bg( $style, $url ) {
	return is_string( $style )
		&& false !== strpos( $style, 'background-image:url("' . $url . '")' )
		&& false !== strpos( $style, 'background-size:cover' )
		&& false !== strpos( $style, 'background-repeat:no-repeat' )
		&& false !== strpos( $style, 'background-position:center' );
}

/** Applies settings over the defaults and drops every cache the renderer keeps. */
function lq_cfg( array $over ) {
	global $settings, $renderer;
	update_option( IMGX\Settings::OPTION, array_merge( IMGX\Settings::defaults(), $over ) );
	$settings->flush_cache();
	$renderer->reset();
	IMGX\Variants::flush_cache();
}

/** Registry of attachment 42: webp+avif per size, optionally an lqip per size. */
function lq_registry( $lqip = true, $formats = true ) {
	$sizes = array(
		'full'   => 'photo',
		'medium' => 'photo-300x200',
		'large'  => 'photo-1024x683',
	);
	$out = array();
	foreach ( $sizes as $size => $stem ) {
		if ( $formats ) {
			$out[ $size ] = array( 'avif' => "$stem.avif", 'webp' => "$stem.webp" );
		}
		if ( $lqip ) {
			$out[ $size ]['lqip'] = "$stem.lqip.jpg";
		}
	}
	IMGX\Variants::save( 42, array( 'sizes' => $out ) );
	IMGX\Variants::flush_cache( 42 );
}

/** A PNG with the given IHDR colour type and tRNS placement (none|before|after IDAT). */
function lq_png( $colour_type, $trns = 'none' ) {
	$chunk = function ( $type, $data ) { return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) ); };
	$b     = "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', pack( 'NNCCCCC', 1, 1, 8, $colour_type, 0, 0, 0 ) );
	if ( 'before' === $trns ) { $b .= $chunk( 'tRNS', str_repeat( "\0", 6 ) ); }
	$b .= $chunk( 'IDAT', gzcompress( "\0\0\0\0\0\0\0\0" ) );
	if ( 'after' === $trns ) { $b .= $chunk( 'tRNS', str_repeat( "\0", 6 ) ); }
	return $b . $chunk( 'IEND', '' );
}

$lq_dir   = "$base/2026/09";
$lq_url   = 'https://example.org/uploads/2026/09/';
$lq_large = $lq_url . 'photo-1024x683.lqip.jpg';
$lq_meta  = $GLOBALS['imgx_attachment_meta']; // metadata of attachment 42, restored where a test swaps it.

$lq_img   = '<img src="' . $lq_url . 'photo-1024x683.jpg" class="wp-image-42" alt="" />';
$lq_img_o = '<img src="' . $lq_url . 'photo-1024x683.jpg" class="wp-image-42" alt="">';

/* ---------- AC-101: setting, sanitiser, signature ---------- */

check( 'AC-101: lqip_enabled defaults to 0', array_key_exists( 'lqip_enabled', IMGX\Settings::defaults() ) && 0 === IMGX\Settings::defaults()['lqip_enabled'] );
check( "AC-101: sanitize('1') -> 1", 1 === ( $settings->sanitize( array( 'lqip_enabled' => '1' ) )['lqip_enabled'] ?? null ) );
check( "AC-101: sanitize('on') -> 1", 1 === ( $settings->sanitize( array( 'lqip_enabled' => 'on' ) )['lqip_enabled'] ?? null ) );
check( 'AC-101: sanitize(missing) -> 0', 0 === ( $settings->sanitize( array() )['lqip_enabled'] ?? null ) );
check( "AC-101: sanitize('') -> 0", 0 === ( $settings->sanitize( array( 'lqip_enabled' => '' ) )['lqip_enabled'] ?? null ) );

lq_cfg( array( 'lqip_enabled' => 0 ) );
check( 'AC-101: signature byte-identical with lqip off', 'avif_webp-82-50' === $settings->encoding_signature() );
lq_cfg( array( 'lqip_enabled' => 1 ) );
check( 'AC-101: signature gets -lqip with lqip on', 'avif_webp-82-50-lqip' === $settings->encoding_signature(), $settings->encoding_signature() );

/* ---------- AC-105: file naming, guarded deletion, registry, prune ---------- */

check( 'AC-105: Files::LQIP_KEY is "lqip"', 'lqip' === lq_call( function () { return IMGX\Files::LQIP_KEY; } ) );
check( 'AC-105: MIME_TYPES stays avif/webp only', array( 'avif', 'webp' ) === array_keys( IMGX\Files::MIME_TYPES ) || array( 'webp', 'avif' ) === array_keys( IMGX\Files::MIME_TYPES ) );

// Assumption (brief is silent on the signature): the LQIP reuses sidecar_path() with the format key 'lqip'.
$lq_sc = function ( $src, array $owned = array() ) { return IMGX\Files::sidecar_path( $src, 'lqip', $owned, 7 ); };
check( 'AC-105: sidecar_path lqip, free folder', "$lq_dir/lqa-1024x768.lqip.jpg" === lq_call( function () use ( $lq_sc, $lq_dir ) { return $lq_sc( "$lq_dir/lqa-1024x768.jpg" ); } ) );
file_put_contents( "$lq_dir/lqb.lqip.jpg", 'foreign' );
check( 'AC-105: foreign photo.lqip.jpg -> photo.jpg.lqip.jpg', "$lq_dir/lqb.jpg.lqip.jpg" === lq_call( function () use ( $lq_sc, $lq_dir ) { return $lq_sc( "$lq_dir/lqb.jpg" ); } ) );
file_put_contents( "$lq_dir/lqb.jpg.lqip.jpg", 'foreign' );
$lq_hash = substr( md5( 'lqb.jpg|7' ), 0, 6 );
check( 'AC-105: second collision -> photo-<hash6>.lqip.jpg', "$lq_dir/lqb-$lq_hash.lqip.jpg" === lq_call( function () use ( $lq_sc, $lq_dir ) { return $lq_sc( "$lq_dir/lqb.jpg" ); } ) );
check( 'AC-105: owned lqip basename is reused', "$lq_dir/lqb.lqip.jpg" === lq_call( function () use ( $lq_sc, $lq_dir ) { return $lq_sc( "$lq_dir/lqb.jpg", array( 'lqb.lqip.jpg' ) ); } ) );

file_put_contents( "$lq_dir/lqc.lqip.jpg", 'x' );
check( 'AC-105: delete_variant removes x.lqip.jpg inside uploads', true === IMGX\Files::delete_variant( "$lq_dir/lqc.lqip.jpg" ) && ! file_exists( "$lq_dir/lqc.lqip.jpg" ) );
foreach ( array( 'lqd.jpg', 'lqd.jpeg', 'lqd.png' ) as $lq_orig ) {
	file_put_contents( "$lq_dir/$lq_orig", 'x' );
	// Not an AC-105 symptom of the missing feature: this must hold before and after.
	check( "AC-105/NFR-102: delete_variant refuses $lq_orig", false === IMGX\Files::delete_variant( "$lq_dir/$lq_orig" ) && file_exists( "$lq_dir/$lq_orig" ) );
}
$lq_outside = str_replace( '\\', '/', sys_get_temp_dir() ) . '/imgx-outside-uploads.lqip.jpg';
file_put_contents( $lq_outside, 'x' );
check( 'AC-105/NFR-102: delete_variant refuses .lqip.jpg outside uploads', false === IMGX\Files::delete_variant( $lq_outside ) && file_exists( $lq_outside ) );
@unlink( $lq_outside );

$lq_norm = IMGX\Variants::normalize( array(
	'sizes'   => array(
		'large' => array( 'lqip' => 'photo-1024x683.lqip.jpg' ),
		'evil'  => array( 'lqip' => '../../evil.lqip.jpg' ),
	),
	'skipped' => array( 'full' => array( 'lqip' => 'has-alpha' ) ),
) );
check( 'AC-105: normalize keeps lqip basename in sizes', ( $lq_norm['sizes']['large']['lqip'] ?? null ) === 'photo-1024x683.lqip.jpg' );
check( 'AC-105: normalize keeps lqip reason in skipped', ( $lq_norm['skipped']['full']['lqip'] ?? null ) === 'has-alpha' );
check( 'AC-105: normalize rejects lqip value with a path', ! isset( $lq_norm['sizes']['evil'] ) );

// sidecar_source_still_exists() is private; reach it by reflection.
$lq_src_exists = function ( $basename ) use ( $generator, $lq_dir ) {
	$m = new ReflectionMethod( $generator, 'sidecar_source_still_exists' );
	$m->setAccessible( true );
	return $m->invoke( $generator, $lq_dir, $basename );
};
file_put_contents( "$lq_dir/lqe-1024x768.jpg", 'x' );
file_put_contents( "$lq_dir/lqf.jpg", 'x' );
check( 'AC-105: source exists for photo-1024x768.lqip.jpg while photo-1024x768.jpg exists', true === lq_call( function () use ( $lq_src_exists ) { return $lq_src_exists( 'lqe-1024x768.lqip.jpg' ); } ) );
check( 'AC-105: source exists for photo.jpg.lqip.jpg while photo.jpg exists', true === lq_call( function () use ( $lq_src_exists ) { return $lq_src_exists( 'lqf.jpg.lqip.jpg' ); } ) );
check( 'AC-105: no source -> false for lqip-only leftover', false === lq_call( function () use ( $lq_src_exists ) { return $lq_src_exists( 'lqg-300x200.lqip.jpg' ); } ) );

// prune_stale_sizes(): an LQIP whose source is gone is deleted, one whose source remains is kept.
$GLOBALS['imgx_attached'][55] = "$lq_dir/lq6.jpg";
file_put_contents( "$lq_dir/lq6.jpg", 'x' );
file_put_contents( "$lq_dir/lq7-old.lqip.jpg", 'x' );
file_put_contents( "$lq_dir/lq8-old2.lqip.jpg", 'x' );
file_put_contents( "$lq_dir/lq8-old2.jpg", 'x' );
IMGX\Variants::save( 55, array( 'sizes' => array(
	'old'  => array( 'lqip' => 'lq7-old.lqip.jpg' ),
	'old2' => array( 'lqip' => 'lq8-old2.lqip.jpg' ),
) ) );
apply_filters( 'wp_update_attachment_metadata', array( 'file' => '2026/09/lq6.jpg', 'sizes' => array() ), 55 );
check( 'AC-105: prune removes an lqip whose source disappeared', ! file_exists( "$lq_dir/lq7-old.lqip.jpg" ) );
check( 'AC-105: prune keeps an lqip while its source still exists', file_exists( "$lq_dir/lq8-old2.lqip.jpg" ) );
unset( $GLOBALS['imgx_attached'][55] );

/* ---------- AC-104 (H): alpha helper, has-alpha registration, needs_generation ---------- */

// Assumption (brief D4 names no method): Files::has_alpha( $path ) reads the PNG bytes of a file.
$lq_alpha = function ( $bytes ) use ( $lq_dir ) {
	file_put_contents( "$lq_dir/lq-fixture.png", $bytes );
	return IMGX\Files::has_alpha( "$lq_dir/lq-fixture.png" );
};
check( 'AC-104: IHDR colour type byte sits at offset 25 in the fixtures', 6 === ord( lq_png( 6 )[25] ) && 2 === ord( lq_png( 2 )[25] ) );
check( 'AC-104: colour type 6 -> alpha', true === lq_call( function () use ( $lq_alpha ) { return $lq_alpha( lq_png( 6 ) ); } ) );
check( 'AC-104: colour type 4 -> alpha', true === lq_call( function () use ( $lq_alpha ) { return $lq_alpha( lq_png( 4 ) ); } ) );
check( 'AC-104: colour type 2 without tRNS -> no alpha', false === lq_call( function () use ( $lq_alpha ) { return $lq_alpha( lq_png( 2 ) ); } ) );
check( 'AC-104: colour type 2 with tRNS before IDAT -> alpha', true === lq_call( function () use ( $lq_alpha ) { return $lq_alpha( lq_png( 2, 'before' ) ); } ) );
check( 'AC-104: colour type 2 with tRNS after IDAT -> no alpha', false === lq_call( function () use ( $lq_alpha ) { return $lq_alpha( lq_png( 2, 'after' ) ); } ) );
check( 'AC-104: JPEG -> no alpha', false === lq_call( function () use ( $lq_alpha ) { return $lq_alpha( "\xFF\xD8\xFF\xE0" . str_repeat( "\0", 64 ) ); } ) );

// generate() on an alpha PNG, JPEG-only server (imgx_enabled_formats = []) so only the LQIP path runs.
// The alpha decision must be taken before any converter call.
$GLOBALS['imgx_attached'][77]   = "$lq_dir/lqalpha.png";
$GLOBALS['imgx_post_mime'][77]  = 'image/png';
$GLOBALS['imgx_attachment_meta'] = array( 'file' => '2026/09/lqalpha.png', 'sizes' => array() );
file_put_contents( "$lq_dir/lqalpha.png", lq_png( 6 ) );
$GLOBALS['imgx_filters']['imgx_enabled_formats'] = array( function () { return array(); } );
lq_cfg( array( 'lqip_enabled' => 1 ) );
$lq_gen = new IMGX\Generator( $settings, $caps, new IMGX\Converter( $settings, $caps, new IMGX\Logger() ), new IMGX\Logger() );
check( 'AC-104: needs_generation true before generate() for an alpha PNG (alpha is only learned by trying)', true === $lq_gen->needs_generation( 77 ) );
$lq_gen->generate( 77 );
$lq_reg = IMGX\Variants::get( 77 );
check( 'AC-104: skipped[full][lqip] === has-alpha', ( $lq_reg['skipped']['full']['lqip'] ?? null ) === 'has-alpha', json_encode( $lq_reg['skipped'] ) );
check( 'AC-104: no .lqip.jpg written for the alpha PNG', array() === glob( "$lq_dir/lqalpha*.lqip.jpg" ) );
check( 'AC-104: needs_generation false after one generate() (has-alpha is not retryable)', false === $lq_gen->needs_generation( 77 ) );
// Even under a changed signature (stale skipped entries) has-alpha stays skipped.
$lq_reg              = IMGX\Variants::get( 77 );
$lq_reg['signature'] = 'something-else';
IMGX\Variants::save( 77, $lq_reg );
check( 'AC-104: has-alpha stays non-retryable under a stale signature', false === $lq_gen->needs_generation( 77 ) && 'has-alpha' === ( IMGX\Variants::get( 77 )['skipped']['full']['lqip'] ?? null ) );
$GLOBALS['imgx_filters']['imgx_enabled_formats'] = array();
unset( $GLOBALS['imgx_attached'][77], $GLOBALS['imgx_post_mime'][77] );
$GLOBALS['imgx_attachment_meta'] = $lq_meta;

// Alpha is decided once, on the ORIGINAL file, and applied to every size: WP's GD editor writes
// PNG sub-sizes as RGBA even for an opaque original, so per-size detection would misfire.
$lq_dec = function ( $id, $orig_type, $sub_type, $refuse = false ) use ( $lq_dir, $settings, $caps ) {
	$GLOBALS['imgx_attached'][ $id ]  = "$lq_dir/lqo$id.png";
	$GLOBALS['imgx_post_mime'][ $id ] = 'image/png';
	$GLOBALS['imgx_attachment_meta']  = array( 'file' => "2026/09/lqo$id.png", 'sizes' => array( 'thumbnail' => array( 'file' => "lqo$id-300x200.png" ) ) );
	file_put_contents( "$lq_dir/lqo$id.png", lq_png( $orig_type ) );
	file_put_contents( "$lq_dir/lqo$id-300x200.png", lq_png( $sub_type ) );
	$GLOBALS['imgx_filters']['imgx_enabled_formats'] = array( function () { return array(); } );
	lq_cfg( array( 'lqip_enabled' => 1 ) );
	$g = new IMGX\Generator( $settings, $caps, new IMGX\Converter( $settings, $caps, new IMGX\Logger() ), new IMGX\Logger() );
	if ( $refuse ) { $GLOBALS['imgx_filters']['imgx_should_generate_image'] = array( function () { return false; } ); } // attempted -> recorded as filtered, converter never runs
	$g->generate( $id );
	$GLOBALS['imgx_filters']['imgx_should_generate_image'] = array();
	$GLOBALS['imgx_filters']['imgx_enabled_formats'] = array();
	return IMGX\Variants::get( $id )['skipped'];
};
// wp_get_original_image_path() is deliberately left undefined here (the fallback to get_attached_file()).
$lq_sk = $lq_dec( 78, 2, 6, true );
check( 'AC-104: opaque original (type 2) + RGBA sub-size (type 6): full not skipped has-alpha', 'filtered' === ( $lq_sk['full']['lqip'] ?? null ), json_encode( $lq_sk ) );
check( 'AC-104: opaque original (type 2) + RGBA sub-size (type 6): sub-size not skipped has-alpha', 'filtered' === ( $lq_sk['thumbnail']['lqip'] ?? null ), json_encode( $lq_sk ) );
$lq_sk = $lq_dec( 79, 6, 2 );
check( 'AC-104: alpha original (type 6) + opaque sub-size (type 2): full skipped has-alpha', 'has-alpha' === ( $lq_sk['full']['lqip'] ?? null ), json_encode( $lq_sk ) );
check( 'AC-104: alpha original (type 6) + opaque sub-size (type 2): sub-size skipped has-alpha too', 'has-alpha' === ( $lq_sk['thumbnail']['lqip'] ?? null ), json_encode( $lq_sk ) );
// The original path comes from wp_get_original_image_path() when WordPress has it (big-image scaling: attached file is the -scaled copy).
function wp_get_original_image_path( $id ) { return $GLOBALS['imgx_original'][ $id ] ?? false; }
$GLOBALS['imgx_original'][80] = "$lq_dir/lqo80-orig.png";
file_put_contents( "$lq_dir/lqo80-orig.png", lq_png( 6 ) );
$GLOBALS['imgx_attached'][80] = "$lq_dir/lqo80.png";
$lq_sk = $lq_dec( 80, 2, 2 ); // attached (scaled) file is opaque, the true original has alpha
check( 'AC-104: alpha decided on wp_get_original_image_path() when present', 'has-alpha' === ( $lq_sk['full']['lqip'] ?? null ) && 'has-alpha' === ( $lq_sk['thumbnail']['lqip'] ?? null ), json_encode( $lq_sk ) );
$lq_sk = $lq_dec( 81, 2, 6, true ); // original path returns false -> fall back to the attached file
check( 'AC-104: wp_get_original_image_path() false -> falls back to the attached file', 'has-alpha' !== ( $lq_sk['full']['lqip'] ?? null ) && 'filtered' === ( $lq_sk['thumbnail']['lqip'] ?? null ), json_encode( $lq_sk ) );
foreach ( array( 78, 79, 80, 81 ) as $lq_i ) { unset( $GLOBALS['imgx_attached'][ $lq_i ], $GLOBALS['imgx_post_mime'][ $lq_i ] ); }
$GLOBALS['imgx_attachment_meta'] = $lq_meta;

/* ---------- AC-107 (H) / AC-109 (H): needs_generation without WebP/AVIF, and after enabling lqip ---------- */

// Attachment 88: no registry at all. JPEG-only server.
$GLOBALS['imgx_filters']['imgx_enabled_formats'] = array( function () { return array(); } );
lq_cfg( array( 'lqip_enabled' => 1 ) );
check( 'AC-107: JPEG-only + lqip on -> needs_generation true', true === $lq_gen->needs_generation( 88 ) );
lq_cfg( array( 'lqip_enabled' => 0 ) );
check( 'AC-107: JPEG-only + lqip off -> needs_generation false (unchanged)', false === $lq_gen->needs_generation( 88 ) );
$GLOBALS['imgx_filters']['imgx_enabled_formats'] = array();

// Attachment 89: only WebP/AVIF registered, then lqip gets enabled.
IMGX\Variants::save( 89, array( 'sizes' => array( 'full' => array( 'avif' => 'photo.avif', 'webp' => 'photo.webp' ) ) ) );
lq_cfg( array( 'lqip_enabled' => 0 ) );
check( 'AC-109: webp/avif complete + lqip off -> needs_generation false', false === $lq_gen->needs_generation( 89 ) );
lq_cfg( array( 'lqip_enabled' => 1 ) );
check( 'AC-109: webp/avif complete, lqip turned on -> needs_generation true', true === $lq_gen->needs_generation( 89 ) );
IMGX\Variants::save( 89, array( 'sizes' => array( 'full' => array( 'avif' => 'photo.avif', 'webp' => 'photo.webp', 'lqip' => 'photo.lqip.jpg' ) ) ) );
check( 'AC-109: once lqip is registered -> needs_generation false again', false === $lq_gen->needs_generation( 89 ) );

/* ---------- AC-106 (H): renderer output ---------- */

lq_cfg( array( 'lqip_enabled' => 1 ) );
lq_registry( true );

$lq_out = $renderer->wrap_img( $lq_img, 42, 'test' );
$lq_tag = lq_img_tag( $lq_out );
check( 'AC-106: style carries the four declarations with the swapped lqip url', lq_has_bg( lq_style( $lq_tag ), $lq_large ), $lq_tag );
check( 'AC-106: url(...) quotes are esc_attr-encoded in the attribute', false !== strpos( $lq_tag, 'url(&quot;' . $lq_large . '&quot;)' ), $lq_tag );
foreach ( array_filter( explode( '<source', $lq_out ) ) as $lq_part ) {
	if ( 0 === strpos( $lq_part, ' type=' ) ) {
		check( 'AC-106: no <source> refers to an lqip', false === strpos( strstr( $lq_part, '>', true ), 'lqip' ), $lq_part );
	}
}
check( 'AC-106: picture still wrapped (picture_enabled on) and sources are avif/webp', false !== strpos( $lq_out, '<picture' ) && false !== strpos( $lq_out, 'image/avif' ) );

$lq_tag = lq_img_tag( $renderer->wrap_img( '<img src="' . $lq_url . 'photo-1024x683.jpg" class="wp-image-42" alt="" style="border:0" />', 42, 'test' ) );
$lq_st  = lq_style( $lq_tag );
check( 'AC-106: existing style="border:0" survives next to the declarations', is_string( $lq_st ) && false !== strpos( $lq_st, 'border:0' ) && lq_has_bg( $lq_st, $lq_large ), $lq_tag );
check( 'AC-106: existing style keeps its double quotes', 1 === preg_match( '#\sstyle="border:0#', $lq_tag ), $lq_tag );

$lq_tag = lq_img_tag( $renderer->wrap_img( "<img src=\"{$lq_url}photo-1024x683.jpg\" class=\"wp-image-42\" alt=\"\" style='border:0' />", 42, 'test' ) );
check( 'AC-106: existing style=\'border:0\' keeps its single quotes', 1 === preg_match( "#\sstyle='border:0[^']*'#", $lq_tag ) && lq_has_bg( lq_style( $lq_tag ), $lq_large ), $lq_tag );

foreach ( array( 'self-closing' => array( $lq_img, '#style="[^"]*"\s*/>$#' ), 'plain' => array( $lq_img_o, '#style="[^"]*"\s*>$#' ) ) as $lq_kind => $lq_case ) {
	$lq_tag = lq_img_tag( $renderer->wrap_img( $lq_case[0], 42, 'test' ) );
	$lq_rest = preg_replace( '#\sstyle\s*=\s*"[^"]*"#', '', $lq_tag );
	check( "AC-106: no style ($lq_kind): inserted right before the closing bracket", 1 === preg_match( $lq_case[1], $lq_tag ), $lq_tag );
	check( "AC-106: no style ($lq_kind): rest of the tag is byte-identical (ignoring whitespace)", preg_replace( '/\s+/', '', $lq_rest ) === preg_replace( '/\s+/', '', $lq_case[0] ) && lq_has_bg( lq_style( $lq_tag ), $lq_large ), $lq_tag );
}

foreach ( array( 'background:red', 'background-image:none', 'border:0;background-image:url(x.png)' ) as $lq_bg ) {
	$lq_in = '<img src="' . $lq_url . 'photo-1024x683.jpg" class="wp-image-42" alt="" style="' . $lq_bg . '" />';
	check( "AC-106: existing style '$lq_bg' -> img tag untouched", lq_img_tag( $renderer->wrap_img( $lq_in, 42, 'test' ) ) === $lq_in );
}

// The src size has no lqip entry -> no background, even though other sizes have one.
IMGX\Variants::save( 42, array( 'sizes' => array(
	'full'  => array( 'avif' => 'photo.avif', 'webp' => 'photo.webp', 'lqip' => 'photo.lqip.jpg' ),
	'large' => array( 'avif' => 'photo-1024x683.avif', 'webp' => 'photo-1024x683.webp' ),
) ) );
IMGX\Variants::flush_cache( 42 );
check( 'AC-106: src size without lqip entry -> no background', false === strpos( $renderer->wrap_img( $lq_img, 42, 'test' ), 'background' ) );
lq_registry( true );

// imgx_verify_variant_files: file absent -> no background; present -> background.
$GLOBALS['imgx_filters']['imgx_verify_variant_files'] = array( function () { return true; } );
@unlink( "$lq_dir/photo-1024x683.lqip.jpg" );
check( 'AC-106: verify on + lqip file absent -> no background', false === strpos( $renderer->wrap_img( $lq_img, 42, 'test' ), 'background-image' ) );
file_put_contents( "$lq_dir/photo-1024x683.lqip.jpg", "\xFF\xD8\xFF\xE0" . str_repeat( "\0", 16 ) );
check( 'AC-106: verify on + lqip file present -> background', lq_has_bg( lq_style( lq_img_tag( $renderer->wrap_img( $lq_img, 42, 'test' ) ) ), $lq_large ) );
$GLOBALS['imgx_filters']['imgx_verify_variant_files'] = array();

// An <img> that already sits in a <picture> is left alone.
$lq_pic = '<picture><source type="image/webp" srcset="x.webp">' . $lq_img . '</picture>';
check( 'AC-106: img inside an existing picture -> unchanged', $renderer->transform( $lq_pic ) === $lq_pic );

// loading="lazy" gets it too (V4); an existing style is kept; imgx_picture_html receives the final <img> (D9).
$lq_seen = array();
$GLOBALS['imgx_filters']['imgx_picture_html'] = array( function ( $html, $img_html ) use ( &$lq_seen ) { $lq_seen[] = $img_html; return $html; } );
$lq_tag = lq_img_tag( $renderer->wrap_img( $img, 42, 'test' ) );
check( 'AC-106: loading="lazy" image gets the background', false !== strpos( $lq_tag, 'loading="lazy"' ) && lq_has_bg( lq_style( $lq_tag ), $lq_large ), $lq_tag );
check( 'AC-106: lazy image keeps style="border-radius:4px"', false !== strpos( (string) lq_style( $lq_tag ), 'border-radius:4px' ) );
check( 'AC-106: imgx_picture_html gets the img WITH the lqip style as 2nd argument', 1 === count( $lq_seen ) && lq_has_bg( lq_style( $lq_seen[0] ), $lq_large ) );
$GLOBALS['imgx_filters']['imgx_picture_html'] = array();

// NFR-104: hostile characters in the src can neither break out of url("...") nor out of the attribute.
lq_cfg( array( 'lqip_enabled' => 1, 'picture_enabled' => 0 ) );
$lq_hostile = '<img src="' . $lq_url . 'photo-1024x683.jpg?x=&quot;&#039;()\\&#32;&#10;z" class="wp-image-42" alt="" />';
$lq_tag     = lq_img_tag( $renderer->wrap_img( $lq_hostile, 42, 'test' ) );
$lq_st      = (string) lq_style( $lq_tag );
preg_match( '#background-image:url\("([^"]*)"\)#', $lq_st, $lq_m );
$lq_u = $lq_m[1] ?? '';
check( 'NFR-104: hostile src yields a url("...") declaration', '' !== $lq_u && 0 === strpos( $lq_u, $lq_url . 'photo-1024x683.lqip.jpg' ), $lq_tag );
check( 'NFR-104: none of " \' ( ) \\ space newline survive raw in the css url', '' !== $lq_u && 0 === preg_match( '#[\\\\"\'() \r\n]#', $lq_u ), $lq_u );
check( 'NFR-104: they are percent-encoded', '' !== $lq_u && 5 <= preg_match_all( '#%(22|27|28|29|5C|20|0A)#i', $lq_u ), $lq_u );
check( 'NFR-104: the style attribute is not broken open (exactly one style attr, tag still closes)', 1 === substr_count( $lq_tag, ' style=' ) && '/>' === substr( $lq_tag, -2 ) );
// NFR-104, entity smuggling: "&amp;quot;" in the src decodes (once) to the literal text "&quot;", which a
// non-double-encoding esc_attr() leaves alone and the browser then turns into a real quote.
$lq_smuggle = '<img src="' . $lq_url . 'photo-1024x683.jpg?x=&amp;quot;);background:url(//evil)" class="wp-image-42" alt="" />';
$lq_tag     = lq_img_tag( $renderer->wrap_img( $lq_smuggle, 42, 'test' ) );
$lq_st      = (string) lq_style( $lq_tag ); // browser view: attribute value after one entity decode
check( 'NFR-104: smuggled &quot; still yields background-image:url("...")', 1 === preg_match( '#background-image:url\("[^"]*"\)#', $lq_st ), $lq_tag );
check( 'NFR-104: decoded style holds exactly the two delimiting double quotes', 2 === substr_count( $lq_st, '"' ), $lq_st );
check( 'NFR-104: no injected background:url(//evil) declaration', '' !== $lq_st && false === strpos( $lq_st, 'background:url(' ) && false === strpos( $lq_st, ';background:' ), $lq_st );
check( 'NFR-104: decoded url contains no raw ( ) ;', 1 === preg_match( '#background-image:url\("([^"();]*)"\)#', $lq_st ), $lq_st );

// An existing style with an entity keeps its meaning (no double-escaping, no loss).
$lq_ent = '<img src="' . $lq_url . 'photo-1024x683.jpg" class="wp-image-42" alt="" style="font-family:a&amp;b" />';
$lq_st  = (string) lq_style( lq_img_tag( $renderer->wrap_img( $lq_ent, 42, 'test' ) ) );
check( 'NFR-104: existing style with &amp; round-trips (decodes to a&b, not a&amp;b)', false !== strpos( $lq_st, 'font-family:a&b' ) && false === strpos( $lq_st, 'a&amp;b' ) && lq_has_bg( $lq_st, $lq_large ), $lq_st );
lq_cfg( array( 'lqip_enabled' => 1 ) );

/* ---------- AC-102 (H): lqip off -> byte-identical output ---------- */

lq_cfg( array( 'lqip_enabled' => 0 ) );
lq_registry( true );
check( 'AC-102: fixture registry really holds lqip entries (guards against a vacuous pass)', 'photo-1024x683.lqip.jpg' === ( IMGX\Variants::get( 42 )['sizes']['large']['lqip'] ?? null ) );
$lq_with = array( $renderer->wrap_img( $img, 42, 'test' ), $renderer->transform( $content ), $renderer->filter_attachment_image( $lq_img, 42 ) );
lq_registry( false );
$lq_without = array( $renderer->wrap_img( $img, 42, 'test' ), $renderer->transform( $content ), $renderer->filter_attachment_image( $lq_img, 42 ) );
check( 'AC-102: lqip off, registry with lqip == registry without (wrap_img, transform, filter_attachment_image)', $lq_with === $lq_without );
check( 'AC-102: lqip off -> no lqip/background-image in any output', false === strpos( implode( '', $lq_with ), 'lqip' ) && false === strpos( implode( '', $lq_with ), 'background-image' ) );

/* ---------- AC-107 (H): JPEG-only server, only lqip registered ---------- */

$GLOBALS['imgx_filters']['imgx_enabled_formats'] = array( function () { return array(); } );
lq_cfg( array( 'lqip_enabled' => 1 ) );
lq_registry( true, false );
check( 'AC-107: is_active() true with no formats + lqip on', true === $renderer->is_active() );
$lq_t = $renderer->transform( '<p>' . $lq_img . '</p>' );
check( 'AC-107: transform() gives a bare <img> (no <picture>/<source>)', false === strpos( $lq_t, '<picture' ) && false === strpos( $lq_t, '<source' ) );
check( 'AC-107: transform() adds the AC-106 background', lq_has_bg( lq_style( lq_img_tag( $lq_t ) ), $lq_large ), $lq_t );
$GLOBALS['wpdb']->attached = array( '2026/09/photo.jpg' => 42 );
$renderer->reset();
$lq_oxy = '<img class="oxy-image" src="' . $lq_url . 'photo-1024x683.jpg" alt="">';
check( 'AC-107: builder img (no wp-image class, oxygen context) gets background, no picture', lq_has_bg( lq_style( lq_img_tag( $renderer->transform( $lq_oxy, 'oxygen' ) ) ), $lq_large ) && false === strpos( $renderer->transform( $lq_oxy, 'oxygen' ), '<picture' ) );
$GLOBALS['wpdb']->attached = array();
lq_cfg( array( 'lqip_enabled' => 0 ) );
check( 'AC-107: no formats + lqip off -> is_active() false (unchanged)', false === $renderer->is_active() );
$GLOBALS['imgx_filters']['imgx_enabled_formats'] = array();

/* ---------- AC-111 (H): picture_enabled = 0 is independent of lqip ---------- */

lq_registry( true );
lq_cfg( array( 'lqip_enabled' => 1, 'picture_enabled' => 0 ) );
$lq_fc = '<figure>' . $lq_img . '</figure>';
$lq_outs = array(
	'filter_content'          => $renderer->filter_content( $lq_fc ),
	'filter_attachment_image' => $renderer->filter_attachment_image( $lq_img, 42 ),
	'wrap_img'                => $renderer->wrap_img( $lq_img, 42, 'direct' ),
);
foreach ( $lq_outs as $lq_name => $lq_o ) {
	check( "AC-111: picture off + lqip on, $lq_name: no <picture>, no <source>", false === strpos( $lq_o, '<picture' ) && false === strpos( $lq_o, '<source' ), $lq_o );
	check( "AC-111: picture off + lqip on, $lq_name: img carries the background", lq_has_bg( lq_style( lq_img_tag( $lq_o ) ), $lq_large ), $lq_o );
}

lq_cfg( array( 'lqip_enabled' => 0, 'picture_enabled' => 0 ) );
check( 'AC-111: picture off + lqip off, filter_content: byte-identical', $renderer->filter_content( $lq_fc ) === $lq_fc );
check( 'AC-111: picture off + lqip off, filter_attachment_image: byte-identical', $renderer->filter_attachment_image( $lq_img, 42 ) === $lq_img );
check( 'AC-111: picture off + lqip off, direct wrap_img: byte-identical (D9)', $renderer->wrap_img( $lq_img, 42, 'direct' ) === $lq_img );

// The other gates of is_active() still apply with lqip on.
lq_cfg( array( 'lqip_enabled' => 1, 'picture_enabled' => 0 ) );
$GLOBALS['imgx_is_admin'] = true;
check( 'AC-111: is_admin() -> no background', false === strpos( $renderer->filter_content( $lq_fc ), 'background-image' ) );
$GLOBALS['imgx_is_admin'] = false;
$GLOBALS['imgx_is_feed']  = true;
check( 'AC-111: is_feed() -> no background', false === strpos( $renderer->filter_content( $lq_fc ), 'background-image' ) );
$GLOBALS['imgx_is_feed'] = false;
$GLOBALS['imgx_filters']['imgx_enable_picture'] = array( function () { return false; } );
check( 'AC-111: imgx_enable_picture=false -> no background', false === strpos( $renderer->filter_content( $lq_fc ), 'background-image' ) );
$GLOBALS['imgx_filters']['imgx_enable_picture'] = array();
check( 'AC-111: control, all gates open again -> background present', lq_has_bg( lq_style( lq_img_tag( $renderer->filter_content( $lq_fc ) ) ), $lq_large ) );

/* ---------- AC-108 (H): preload ---------- */

lq_cfg( array( 'lqip_enabled' => 1, 'whole_page' => 1 ) );
lq_registry( true );
$lq_med_img  = '<img src="' . $lq_url . 'photo-300x200.jpg" class="wp-image-42" alt="">';
$lq_high_img = '<img fetchpriority="high" src="' . $lq_url . 'photo-1024x683.jpg" class="wp-image-42" alt="">';
$lq_doc      = '<!DOCTYPE html><html><head><title>t</title></head><body>' . $lq_med_img . $lq_high_img . '</body></html>';
$lq_page     = $renderer->filter_page( $lq_doc );
$lq_link     = '<link rel="preload" as="image" href="' . $lq_large . '" fetchpriority="high">';
check( 'AC-108: exactly one preload link', 1 === substr_count( $lq_page, 'rel="preload"' ), $lq_page );
check( 'AC-108: it is <link rel="preload" as="image" href="<lqip>" fetchpriority="high"> for the fetchpriority=high image', 1 === substr_count( $lq_page, $lq_link ) );
check( 'AC-108: placed before </head>', false !== strpos( $lq_page, $lq_link ) && strpos( $lq_page, $lq_link ) < strpos( $lq_page, '</head>' ) );
preg_match_all( '#<link\b[^>]*rel="preload"[^>]*>#', $lq_page, $lq_links );
$lq_bad = array_filter( $lq_links[0], function ( $l ) { return ! preg_match( '#href="[^"]*\.lqip\.jpg"#', $l ); } );
check( 'AC-108: never a preload for a hi-res or WebP/AVIF url', $lq_links[0] && ! $lq_bad, implode( ' ', $lq_bad ) );

// Second fixture: the high-priority img already sits in IMGX's own <picture>, style is esc_attr'd (&quot;).
$lq_style_attr = esc_attr( 'background-image:url("' . $lq_large . '");background-size:cover;background-repeat:no-repeat;background-position:center' );
$lq_wrapped    = '<picture class="imgx-picture"><source type="image/webp" srcset="' . $lq_url . 'photo-1024x683.webp">'
	. '<img fetchpriority="high" src="' . $lq_url . 'photo-1024x683.jpg" class="wp-image-42" alt="" style="' . $lq_style_attr . '"></picture>';
$lq_page2      = $renderer->filter_page( '<!DOCTYPE html><html><head><title>t</title></head><body>' . $lq_wrapped . '</body></html>' );
check( 'AC-108: img inside IMGX <picture> (entity-encoded style): exactly one preload for the lqip', 1 === substr_count( $lq_page2, 'rel="preload"' ) && 1 === substr_count( $lq_page2, $lq_link ), $lq_page2 );
check( 'AC-108: that preload is before </head>', false !== strpos( $lq_page2, $lq_link ) && strpos( $lq_page2, $lq_link ) < strpos( $lq_page2, '</head>' ) );

$lq_page3 = $renderer->filter_page( '<!DOCTYPE html><html><head></head><body><img fetchpriority="high" src="https://example.org/other.jpg" alt=""></body></html>' );
check( 'AC-108: high-priority img without lqip -> no preload', false === strpos( $lq_page3, 'rel="preload"' ) );

foreach ( array( 'fetchpriority="highx"', 'data-fetchpriority="high"', 'fetchpriority="low"' ) as $lq_fp ) {
	$lq_p = $renderer->filter_page( '<!DOCTYPE html><html><head></head><body><img ' . $lq_fp . ' src="' . $lq_url . 'photo-1024x683.jpg" class="wp-image-42" alt=""></body></html>' );
	check( "AC-108: $lq_fp is not high priority -> no preload", false === strpos( $lq_p, 'rel="preload"' ), $lq_p );
}

check( 'AC-108: same input through filter_content -> no preload', false === strpos( $renderer->filter_content( $lq_doc ), 'rel="preload"' ) );

$GLOBALS['imgx_actions'] = array();
( new IMGX\Picture_Renderer( $settings, $caps ) )->register_hooks();
$lq_buf = array_filter( $GLOBALS['imgx_actions'], function ( $a ) { return 'template_redirect' === $a[0] && is_array( $a[1] ) && 'start_buffer' === $a[1][1]; } );
check( 'AC-108: whole_page=1 registers start_buffer', 1 === count( $lq_buf ) );
lq_cfg( array( 'lqip_enabled' => 1, 'whole_page' => 0 ) );
$GLOBALS['imgx_actions'] = array();
( new IMGX\Picture_Renderer( $settings, $caps ) )->register_hooks();
$lq_buf = array_filter( $GLOBALS['imgx_actions'], function ( $a ) { return 'template_redirect' === $a[0]; } );
check( 'AC-108: whole_page=0 does not register start_buffer (so no preload)', 0 === count( $lq_buf ) );

lq_cfg( array() );

echo "\n{$pass} passed, {$fail} failed\n";

// Cleanup.
array_map( 'unlink', glob( "$base/2026/09/*" ) );
exit( $fail > 0 ? 1 : 0 );
