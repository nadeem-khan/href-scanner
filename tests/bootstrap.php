<?php
/**
 * Load only an explicitly marked disposable WordPress test site.
 *
 * @package HrefScanner
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

$wp_root = getenv( 'HREF_SCANNER_WP_ROOT' );
if ( ! $wp_root || ! is_file( $wp_root . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set HREF_SCANNER_WP_ROOT to a disposable WordPress installation.' );
}

define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:53320';
require rtrim( $wp_root, '/\\' ) . '/wp-load.php';
if ( ! defined( 'HREF_SCANNER_TEST_SITE' ) || HREF_SCANNER_TEST_SITE !== true ) {
	throw new RuntimeException( 'Database tests require HREF_SCANNER_TEST_SITE=true in the disposable wp-config.php.' );
}
if ( ! function_exists( 'href_scanner_scan_batch' ) ) {
	throw new RuntimeException( 'Activate href Scanner in the disposable installation first.' );
}

require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
