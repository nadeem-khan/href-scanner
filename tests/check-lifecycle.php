<?php
/**
 * Check activation and retained data on a disposable installation.
 *
 * @package HrefScanner
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$fixture_plugin     = 'href-scanner/href-scanner.php';
$was_network_active = is_plugin_active_for_network( $fixture_plugin );
$saved_domains      = get_option( 'href_scanner_internal_domains', false );
$source_id          = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'Retention fixture',
		'post_content' => '<a href="https://retention.example.test/">Retained</a>',
	),
	true
);
if ( is_wp_error( $source_id ) ) {
	throw new RuntimeException( 'Could not create a retention fixture.' );
}
$record_id = 0;
try {
	$result = href_scanner_scan_post( get_post( $source_id ), 'retention-test', array() );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( 'Could not scan the retention fixture.' );
	}
	$records   = get_posts(
		array(
			'post_type'   => 'elt_external_link',
			'post_parent' => $source_id,
			'fields'      => 'ids',
		)
	);
	$record_id = $records[0];
	update_option( 'href_scanner_internal_domains', array( 'retention.example.test', '', '' ) );
	deactivate_plugins( $fixture_plugin );
	if ( is_plugin_active( $fixture_plugin ) || ! get_post( $record_id ) || get_option( 'href_scanner_internal_domains' ) !== array( 'retention.example.test', '', '' ) ) {
		throw new RuntimeException( 'Deactivation must retain records and settings.' );
	}
	uninstall_plugin( $fixture_plugin );
	if ( ! get_post( $record_id ) || get_option( 'href_scanner_internal_domains' ) !== array( 'retention.example.test', '', '' ) ) {
		throw new RuntimeException( 'The WordPress uninstall routine must retain records and settings.' );
	}
	$result = activate_plugin( $fixture_plugin, '', $was_network_active );
	if ( is_wp_error( $result ) || ! is_plugin_active( $fixture_plugin ) || is_plugin_active_for_network( $fixture_plugin ) !== $was_network_active || ! get_post( $record_id ) ) {
		throw new RuntimeException( 'Reactivation must succeed and preserve records.' );
	}
	echo "Activation, deactivation, uninstall routine and retained-data checks passed.\n";
} finally {
	if ( ! is_plugin_active( $fixture_plugin ) || is_plugin_active_for_network( $fixture_plugin ) !== $was_network_active ) {
		activate_plugin( $fixture_plugin, '', $was_network_active );
	}
	if ( $record_id ) {
		wp_delete_post( $record_id, true );
	}
	wp_delete_post( $source_id, true );
	false === $saved_domains ? delete_option( 'href_scanner_internal_domains' ) : update_option( 'href_scanner_internal_domains', $saved_domains );
}
