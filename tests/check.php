<?php
/**
 * Check link parsing and admin helpers with isolated WordPress stubs.
 *
 * @package HrefScanner
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$wp_root = getenv( 'HREF_SCANNER_WP_ROOT' );
define( 'ABSPATH', rtrim( $wp_root ? $wp_root : dirname( __DIR__, 4 ), '/\\' ) . '/' );
define( 'WPINC', 'wp-includes' );
define( 'WP_DEBUG', false );
define( 'WP_ADMIN', true );

require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/load.php';
if ( is_file( ABSPATH . WPINC . '/compat-utf8.php' ) ) {
	require ABSPATH . WPINC . '/compat-utf8.php';
}
require ABSPATH . WPINC . '/compat.php';
if ( is_file( ABSPATH . WPINC . '/utf8.php' ) ) {
	require ABSPATH . WPINC . '/utf8.php';
}
require ABSPATH . WPINC . '/class-wp-error.php';
require ABSPATH . WPINC . '/class-wp-query.php';
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/kses.php';
require ABSPATH . WPINC . '/http.php';
require ABSPATH . WPINC . '/class-wp-http.php';
require ABSPATH . WPINC . '/general-template.php';
require dirname( __DIR__ ) . '/href-scanner.php';

/**
 * Return the fixture home URL.
 *
 * @param string $path Requested URL path.
 * @return string
 */
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

/**
 * Return the fixture WordPress URL.
 *
 * @param string $path Requested URL path.
 * @return string
 */
function site_url( $path = '' ) {
	return 'https://example.test/wp' . $path;
}

/**
 * Return a fixture option or its default.
 *
 * @param string $option Fixture option name.
 * @param mixed  $default_value Fallback option value.
 * @return mixed
 */
function get_option( $option, $default_value = false ) {
	return 'blog_charset' === $option ? 'UTF-8' : ( $GLOBALS['check_options'][ $option ] ?? $default_value );
}

/**
 * Return the fixture character-set option.
 *
 * @return array
 */
function wp_load_alloptions() {
	return array( 'blog_charset' => 'UTF-8' );
}

if ( ! function_exists( 'is_utf8_charset' ) ) {
	/**
	 * Check the fixture character set.
	 *
	 * @param string|null $blog_charset Explicit or saved fixture character set.
	 * @return bool
	 */
	function is_utf8_charset( $blog_charset = null ) {
		return in_array( strtolower( $blog_charset ?? get_option( 'blog_charset' ) ), array( 'utf-8', 'utf8' ), true );
	}
}

if ( ! function_exists( '_canonical_charset' ) ) {
	/**
	 * Return the fixture character set unchanged.
	 *
	 * @param string $charset Character set.
	 * @return string
	 */
	function _canonical_charset( $charset ) {
		return $charset;
	}
}

/**
 * Return untranslated fixture text.
 *
 * @param string $text Fixture text.
 * @return string
 */
function __( $text ) {
	return $text;
}

/**
 * Escape untranslated fixture text.
 *
 * @param string $text Fixture text.
 * @return string
 */
function esc_html__( $text ) {
	return esc_html( $text );
}

/**
 * Record a fixture settings-validation message.
 *
 * @param string $setting Setting name.
 * @param string $code Validation code.
 * @param string $message Regression failure or validation message.
 * @return void
 */
function add_settings_error( $setting, $code, $message ) {
	$GLOBALS['check_errors'][] = $message;
}

/**
 * Return registered fixture source types.
 *
 * @param array  $args Setting or query arguments.
 * @param string $output Names or objects.
 * @return array
 */
function get_post_types( $args = array(), $output = 'names' ) {
	$types = array();
	foreach ( array( 'post', 'page', 'attachment', 'revision', 'fixture_custom', 'fixture_no_editor', 'elt_external_link', 'elt_internal_link' ) as $name ) {
		$types[ $name ] = 'objects' === $output ? (object) array(
			'name'     => $name,
			'_builtin' => in_array( $name, array( 'post', 'page', 'attachment', 'revision' ), true ),
			'labels'   => (object) array( 'name' => $name ),
		) : $name;
	}
	return $types;
}

/**
 * Check editor support for a fixture source type.
 *
 * @param string $post_type Selected content or occurrence type.
 * @param string $feature Requested source feature.
 * @return bool
 */
function post_type_supports( $post_type, $feature ) {
	return 'editor' === $feature && in_array( $post_type, array( 'post', 'page', 'attachment', 'fixture_custom' ), true );
}

/**
 * Return a fixture admin URL.
 *
 * @param string $path Requested URL path.
 * @return string
 */
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}

/**
 * Append fixture query parameters to a URL.
 *
 * @param array  $args Setting or query arguments.
 * @param string $url Fixture URL.
 * @return string
 */
function add_query_arg( $args, $url ) {
	return $url . '?' . http_build_query( $args );
}

/**
 * Return the fixture unfiltered list URL.
 *
 * @return string
 */
function remove_query_arg() {
	return 'https://example.test/wp-admin/edit.php?post_type=elt_external_link'; }

/**
 * Append the fixture export nonce to a URL.
 *
 * @param string $url Fixture URL.
 * @return string
 */
function wp_nonce_url( $url ) {
	return htmlspecialchars( $url . '&_wpnonce=fixture-nonce', ENT_QUOTES );
}

/**
 * Return the isolated fixture screen.
 *
 * @return object
 */
function get_current_screen() {
	return $GLOBALS['check_screen'];
}

/**
 * Create a fixture nonce bound to its action.
 *
 * @param string $action Nonce action.
 * @return string
 */
function wp_create_nonce( $action ) {
	return 'fixture-' . $action;
}

/**
 * Verify a fixture nonce against its action.
 *
 * @param string $nonce Submitted nonce.
 * @param string $action Nonce action.
 * @return int|false
 */
function wp_verify_nonce( $nonce, $action ) {
	return wp_create_nonce( $action ) === $nonce ? 1 : false;
}

/**
 * Render the fixture filter nonce field.
 *
 * @param string $action Nonce action.
 * @param string $name Form field name.
 * @return void
 */
function wp_nonce_field( $action, $name ) {
	echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '">';
}

/**
 * Require a lightweight regression condition to hold.
 *
 * @param bool   $condition Required regression condition.
 * @param string $message Regression failure or validation message.
 * @throws RuntimeException If the regression expectation fails.
 * @return void
 */
function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

$check_options = array( 'href_scanner_internal_domains' => array( 'internal.example', '', '' ) );
check( href_scanner_sanitize_content_types( array( 'page', 'fixture_custom', 'fixture_no_editor', 'attachment', 'revision', 'elt_external_link', 'elt_internal_link', 'invalid', array( 'post' ) ) ) === array( 'page', 'fixture_custom' ), 'Only editor-supported Posts, Pages and custom types may be selected; core internal types and generated records must be excluded.' );
check( href_scanner_sanitize_content_types( array() ) === array(), 'The content-type selection may be cleared.' );
check( array_keys( href_scanner_content_types() ) === array( 'post', 'page', 'fixture_custom' ), 'Content types without editor support must be hidden.' );
ob_start();
href_scanner_content_types_field();
$default_types_html = ob_get_clean();
foreach ( array( 'post', 'page' ) as $content_type ) {
	check( strpos( $default_types_html, 'value="' . $content_type . '" checked=' ) !== false, 'Posts and Pages must be selected by default.' );
}
$check_options['href_scanner_content_types'] = array( 'post' );
ob_start();
href_scanner_content_types_field();
$saved_types_html = ob_get_clean();
check( strpos( $saved_types_html, 'value="page" checked=' ) === false, 'Saved selections must remain unchanged.' );
unset( $check_options['href_scanner_content_types'] );
$content = <<<'HTML'
<p><a href="https://WWW.external.test/a?x=1&amp;y=2" rel="ugc NOFOLLOW noopener">Café <strong>&amp; tea</strong></a>
<a href="//external.test/b" rel="dofollow">Second</a>
<a href="/target/#one">Target</a><a href="http://www.example.test/target">Target again</a>
<a href="../sibling/?page=2#part">Relative</a><a href="#section">Fragment</a>
<a href="?view=all">Query</a><a href="/download.pdf">File</a>
<a href="https://example.test.evil.test/">Lookalike</a>
<a href="/empty"><img src="image.png" alt="Image"></a>
<a href="https://external.test/sponsored" rel="sponsored ugc">Sponsored</a>
<a href="mailto:user@example.test">Email</a><a href="tel:123">Phone</a>
<a href="javascript:alert(1)">Script</a><a href="data:text/html,bad">Data</a>
<a href="">Empty</a><a>No href</a><a href="https://[bad/">Invalid</a></p>
<!-- <a href="https://comment.test">Comment</a> -->
<script>var example = '<a href="https://script.test">Script text</a>';</script>
HTML;

$links = href_scanner_extract_links( $content, 'https://example.test/articles/source/?p=12' );
check( count( $links ) === 11, 'Only valid HTTP(S) anchor occurrences should be captured.' );
check( 'Café & tea' === $links[0]['anchor'], 'Nested text, entities and UTF-8 must survive parsing.' );
check( 'https://www.external.test/a?x=1&y=2' === $links[0]['url'], 'URLs must retain decoded query parameters.' );
check( 'No Follow' === $links[0]['follow'], 'Nofollow tokens must be case insensitive.' );
check( $links[0]['group'] === $links[1]['group'], 'External domains must group www variants together.' );
check( 'Do Follow' === $links[1]['follow'], 'Links without nofollow should be Do Follow.' );
check( 'https://example.test/target' === $links[2]['group'], 'Internal targets must use the home origin.' );
check( $links[2]['group'] === $links[3]['group'], 'Internal scheme, www, fragment and slash variants must group together.' );
check( 'https://example.test/articles/sibling/?page=2#part' === $links[4]['url'], 'Relative paths must resolve against the source permalink.' );
check( 'https://example.test/articles/source/?p=12#section' === $links[5]['url'], 'Fragment links must retain source query parameters.' );
check( 'https://example.test/articles/source/?view=all' === $links[6]['url'], 'Query links must replace the source query.' );
check( 'https://example.test/download.pdf' === $links[7]['group'], 'File targets must not acquire a trailing slash.' );
check( 'elt_external_link' === $links[8]['post_type'], 'Lookalike hosts must remain external.' );
check( '' === $links[9]['anchor'], 'Image-only anchors must retain their empty text.' );
check( 'Do Follow' === $links[10]['follow'], 'Sponsored and ugc alone must not imply nofollow.' );
check( href_scanner_extract_links( '', 'https://example.test/post/' ) === array(), 'Empty posts must produce no records.' );
check( count( href_scanner_extract_links( '<a href="/broken"><strong>Unclosed', 'https://example.test/post/' ) ) === 1, 'Malformed HTML must still be scanned.' );
check( 2 === array_count_values( array_column( $links, 'group' ) )['https://example.test/target'], 'Repeated link occurrences must remain separate records.' );
$special_links = href_scanner_extract_links( '<a href="https://bücher.example/a">IDN</a><a href="/with space">Space</a><a href="/follow" rel="notnofollow">Follow</a>', 'https://example.test/post/' );
check( 'xn--bcher-kva.example' === $special_links[0]['group'], 'International domains must be normalized to usable hostnames.' );
check( 'https://example.test/with%20space' === $special_links[1]['url'], 'Spaces in URL paths must be encoded.' );
check( 'Do Follow' === $special_links[2]['follow'], 'Nofollow must be matched as a complete token.' );
$domain_links = href_scanner_extract_links( '<a href="/target/">Relative</a><a href="https://internal.example/target/#part">Configured</a><a href="http://WWW.INTERNAL.EXAMPLE/target">WWW</a><a href="//internal.example/target/">Protocol relative</a><a href="https://internal.example.evil.test/target/">Lookalike</a><a href="//external.test/target/">External</a>', 'https://example.test/post/' );
foreach ( array_slice( $domain_links, 0, 4 ) as $fixture_link ) {
	check( 'elt_internal_link' === $fixture_link['post_type'], 'Relative paths and configured domains must be internal.' );
	check( 'https://example.test/target' === $fixture_link['group'], 'Site and configured-domain links to the same page must share a report group.' );
}
check( 'elt_external_link' === $domain_links[4]['post_type'], 'Configured-domain lookalikes must remain external.' );
check( 'elt_external_link' === $domain_links[5]['post_type'], 'Protocol-relative links to external domains must remain external.' );
$relative_links = href_scanner_extract_links( '<a href="../target/">Path</a><a href="?page=2">Query</a><a href="#section">Fragment</a>', 'https://other.test/post/' );
check( array_unique( array_column( $relative_links, 'post_type' ) ) === array( 'elt_internal_link' ), 'All relative paths, queries and fragments must be classified as internal.' );
$check_options['href_scanner_internal_domains'] = array( 'alias.test', 'xn--bcher-kva.example', 'third.test' );
$configured_links                              = href_scanner_extract_links( '<a href="https://WWW.alias.test/a">Alias</a><a href="https://bücher.example/a">IDN</a><a href="//third.test/a">Third</a><a href="https://internal.example/a">Removed</a><a href="https://sub.alias.test/a">Subdomain</a><a href="https://alias.test.evil.test/a">Lookalike</a>', 'https://example.test/post/' );
check( array_column( array_slice( $configured_links, 0, 3 ), 'post_type' ) === array_fill( 0, 3, 'elt_internal_link' ), 'All three saved domains must be internal, including www, IDN and protocol-relative links.' );
check( array_column( array_slice( $configured_links, 3 ), 'post_type' ) === array_fill( 0, 3, 'elt_external_link' ), 'Removed domains, unlisted subdomains and lookalikes must remain external.' );
check( 'elt_external_link' === href_scanner_extract_links( '<a href="https://alias.test/a">Alias</a>', 'https://example.test/post/', array() )[0]['post_type'], 'A scan must use its domain snapshot rather than changed settings.' );
check( href_scanner_sanitize_domains( array( ' https://WWW.Alias.test/path?x=1 ', 'bücher.example', 'third.test.' ) ) === array( 'alias.test', 'xn--bcher-kva.example', 'third.test' ), 'Domain fields must normalize URLs, www, IDN and trailing dots.' );
check( href_scanner_sanitize_domains( array( '*.example.test', 'https://user:pass@example.test', 'javascript:alert(1)' ) ) === $check_options['href_scanner_internal_domains'], 'Invalid domains must retain previous values.' );
check( count( $check_errors ) === 3, 'Invalid domain values must display settings errors.' );
check( href_scanner_sanitize_domains( array( '', '', '' ) ) === array( '', '', '' ), 'All domain fields may be cleared.' );
$check_options['elt_last_scan'] = array(
	'scan_id' => 'completed',
	'phase'   => 'done',
);
check( 'done' === href_scanner_scan_batch( 'completed' )['phase'], 'A late request for a completed scan must not start a new scan.' );
check( href_scanner_scan_batch( 'unknown' ) instanceof WP_Error, 'A stale scan identifier must be rejected.' );
$check_options['elt_scan_state'] = array(
	'scan_id' => 'new-scan',
	'phase'   => 'scan',
);
check( 'done' === href_scanner_scan_batch( 'completed' )['phase'], 'An old browser tab must not advance a newer scan.' );
check( has_action( 'wp_ajax_href_scanner_scan', 'href_scanner_ajax_scan' ) !== false, 'The authenticated scan action must be registered.' );
check( has_action( 'wp_ajax_nopriv_href_scanner_scan' ) === false, 'Anonymous scan requests must not be registered.' );

$check_screen                      = (object) array(
	'base'      => 'edit',
	'post_type' => 'elt_external_link',
);
$_GET                              = array(
	'elt_anchor' => 'Tea',
	'elt_follow' => 'No Follow',
	'elt_source' => 'Example Post',
);
$_GET['href_scanner_filter_nonce'] = wp_create_nonce( 'href_scanner_filter' );
foreach ( array( 'elt_internal_link', 'elt_external_link' ) as $fixture_post_type ) {
	$filter_query = new class() extends WP_Query {
		/**
		 * Treat this fixture as the native listing query.
		 *
		 * @return bool
		 */
		public function is_main_query() {
			return true;
		}
	};
	$filter_query->set( 'post_type', $fixture_post_type );
	$filter_query->set(
		'meta_query',
		array(
			'relation' => 'OR',
			array(
				'key'   => '_existing',
				'value' => 'kept',
			),
		)
	);
	href_scanner_filter_list( $filter_query );
	$filters = $filter_query->get( 'meta_query' );
	check( 'AND' === $filters['relation'] && count( $filters ) === 5, 'All listing filters must combine with existing query conditions.' );
	check(
		array(
			'key'     => '_elt_anchor',
			'value'   => 'Tea',
			'compare' => 'LIKE',
		) === $filters[0],
		'Anchor Text must support partial matches.'
	);
	check(
		array(
			'key'     => '_elt_follow',
			'value'   => 'No Follow',
			'compare' => '=',
		) === $filters[1],
		'Follow Type must use an exact match.'
	);
	check(
		array(
			'key'     => '_elt_source_title',
			'value'   => 'Example Post',
			'compare' => 'LIKE',
		) === $filters[2],
		'Source Post must filter the displayed title.'
	);
	check( 'OR' === $filters[3]['relation'], 'Existing query groups must retain their relation.' );
}
$filter_query->set( 'meta_query', array() );
$_GET                              = array(
	'elt_anchor' => array(),
	'elt_follow' => 'invalid',
	'elt_source' => '',
);
$_GET['href_scanner_filter_nonce'] = wp_create_nonce( 'href_scanner_filter' );
href_scanner_filter_list( $filter_query );
check( $filter_query->get( 'meta_query' ) === array(), 'Invalid and empty filters must not restrict the listing.' );
$_GET                              = array( 'elt_anchor' => 'Tea' );
$_GET['href_scanner_filter_nonce'] = wp_create_nonce( 'href_scanner_filter' );
$secondary_query                   = new WP_Query();
$secondary_query->set( 'post_type', 'elt_internal_link' );
href_scanner_filter_list( $secondary_query );
check( $secondary_query->get( 'meta_query' ) === '', 'Secondary queries such as scans must not receive listing filters.' );
$filter_query->set( 'post_type', 'post' );
href_scanner_filter_list( $filter_query );
check( $filter_query->get( 'meta_query' ) === array(), 'Other WordPress content types must not receive link filters.' );
$_GET                              = array(
	'elt_anchor' => '"<script>bad</script>',
	'elt_follow' => 'No Follow',
	'elt_source' => 'Example Post',
);
$_GET['href_scanner_filter_nonce'] = wp_create_nonce( 'href_scanner_filter' );
ob_start();
href_scanner_list_filters( 'elt_internal_link', 'top' );
$filter_html = ob_get_clean();
check( strpos( $filter_html, 'name="elt_anchor"' ) !== false && strpos( $filter_html, 'name="elt_source"' ) !== false, 'Both text filters must be rendered.' );
check( strpos( $filter_html, 'value="No Follow" selected=' ) !== false, 'The selected Follow Type must survive filtering.' );
check( strpos( $filter_html, '<script>' ) === false && strpos( $filter_html, '&quot;' ) !== false, 'Filter values must be sanitized and escaped.' );
check( substr_count( $filter_html, 'class="href-scanner-filter"' ) === 4 && strpos( $filter_html, '<label for="elt_anchor">Anchor Text</label>' ) !== false, 'All four filters must have visible labels and separate layout groups.' );
$_GET = array();

$_GET                              = array( 'elt_content_type' => 'fixture_custom' );
$_GET['href_scanner_filter_nonce'] = wp_create_nonce( 'href_scanner_filter' );
$filter_query->set( 'post_type', 'elt_external_link' );
href_scanner_filter_list( $filter_query );
check( $filter_query->get( 'href_scanner_source_type' ) === 'fixture_custom', 'Content Type must filter the main listing query.' );
$export_query = new WP_Query();
$export_query->set( 'post_type', 'elt_internal_link' );
$export_query->set( 'href_scanner_export', true );
$check_screen->base = 'admin-post';
href_scanner_filter_list( $export_query );
check( $export_query->get( 'href_scanner_source_type' ) === 'fixture_custom', 'CSV exports must apply the same content-type filter.' );
check( has_action( 'admin_post_href_scanner_export', 'href_scanner_export_csv' ) !== false && has_action( 'admin_post_nopriv_href_scanner_export' ) === false, 'CSV exports must have an authenticated handler only.' );
check( has_action( 'manage_posts_extra_tablenav', 'href_scanner_export_button' ) !== false, 'Export All must be outside the filter actions container.' );
foreach ( array( 'elt_external_link', 'elt_internal_link' ) as $fixture_post_type ) {
	$check_screen->post_type = $fixture_post_type;
	ob_start();
	href_scanner_export_button( 'top' );
	$export_html = ob_get_clean();
	check( strpos( $export_html, 'class="button button-primary alignright href-scanner-export"' ) !== false && strpos( $export_html, 'dashicons-download" aria-hidden="true"' ) !== false && strpos( $export_html, 'post_type=' . $fixture_post_type ) !== false, 'Both listings must have a distinct right-aligned export with a decorative download icon.' );
}
$check_screen->post_type = 'post';
ob_start();
href_scanner_export_button( 'top' );
$other_export_html = ob_get_clean();
check( '' === $other_export_html, 'Other content listings must not show the export button.' );
$check_screen->post_type = 'elt_external_link';
ob_start();
href_scanner_export_button( 'bottom' );
$bottom_export_html = ob_get_clean();
check( '' === $bottom_export_html, 'Export All must appear only in the top toolbar.' );
$_GET = array();

$_GET                              = array( 'elt_group' => 'example.org' );
$_GET['href_scanner_filter_nonce'] = wp_create_nonce( 'href_scanner_filter' );
$check_screen->base                = 'edit';
$filter_query->set( 'post_type', 'elt_external_link' );
$filter_query->set( 'meta_query', array() );
href_scanner_filter_list( $filter_query );
check(
	array(
		'key'     => '_elt_group',
		'value'   => 'example.org',
		'compare' => '=',
	) === $filter_query->get( 'meta_query' )[0],
	'Report counts must filter listings by the exact target group.'
);
$export_query->set( 'meta_query', array() );
href_scanner_filter_list( $export_query );
check( '=' === $export_query->get( 'meta_query' )[0]['compare'], 'CSV exports must preserve exact report target filtering.' );
ob_start();
href_scanner_list_filters( 'elt_external_link', 'top' );
$group_html = ob_get_clean();
check( strpos( $group_html, 'name="elt_group" value="example.org"' ) !== false && strpos( $group_html, 'Clear target filter' ) !== false, 'Target filters must survive form submissions and have a clear action.' );
ob_start();
href_scanner_export_button( 'top' );
$group_export_html = ob_get_clean();
check( strpos( $group_export_html, 'elt_group=example.org' ) !== false, 'Export links must retain the report target.' );
$_GET = array();

$fixture_target = 'https://example.test/a%20page/?a=1&b=2';
$_GET           = array( 'elt_group' => $fixture_target );
check( array() === href_scanner_filter_values(), 'Filters without a nonce must be ignored.' );
$_GET['href_scanner_filter_nonce'] = 'invalid';
check( array() === href_scanner_filter_values(), 'Filters with an invalid nonce must be ignored.' );
$_GET['href_scanner_filter_nonce'] = array( 'invalid' );
check( array() === href_scanner_filter_values(), 'Nonce arrays must be rejected without type errors.' );
$_GET['href_scanner_filter_nonce'] = wp_create_nonce( 'href_scanner_filter' );
$_GET['elt_anchor']                = array( 'invalid' );
check( href_scanner_group_filter() === $fixture_target && ! isset( href_scanner_filter_values()['elt_anchor'] ), 'Verified targets must retain encoded paths and reject array filters.' );
$_GET         = array();
$nested_links = href_scanner_extract_links( '<a href="/nested">One <strong>two &amp; three</strong><!-- hidden --> four</a>', 'https://example.test/source/' );
check( 'One two & three four' === $nested_links[0]['anchor'], 'XPath must preserve descendant anchor text and decoded entities without comments.' );

echo "href Scanner checks passed.\n";
