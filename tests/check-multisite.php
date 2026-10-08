<?php
/**
 * Check network activation and isolation between disposable sites.
 *
 * @package HrefScanner
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_multisite() || ! get_site( 2 ) || ! is_plugin_active_for_network( 'href-scanner/href-scanner.php' ) ) {
	throw new RuntimeException( 'This test requires a disposable multisite network with a second blog and network-activated href Scanner.' );
}

$fixtures = array();
$scan_id  = wp_generate_uuid4();
try {
	foreach ( array( 1, 2 ) as $fixture_site_id ) {
		switch_to_blog( $fixture_site_id );
		try {
			$saved_domains = get_option( 'href_scanner_internal_domains', false );
			$source_id     = wp_insert_post(
				array(
					'post_type'    => 'post',
					'post_status'  => 'draft',
					'post_title'   => 'Site isolation fixture',
					'post_content' => '<a href="https://site' . $fixture_site_id . '.example.test/">Site link</a>',
				),
				true
			);
			if ( is_wp_error( $source_id ) ) {
				throw new RuntimeException( 'Could not create a site-isolation fixture.' );
			}
			$fixtures[ $fixture_site_id ] = array(
				'source_id' => $source_id,
				'domains'   => $saved_domains,
			);
			update_option( 'href_scanner_internal_domains', array( 'site' . $fixture_site_id . '.example.test', '', '' ) );
			$result = href_scanner_scan_post( get_post( $source_id ), $scan_id, array() );
			if ( is_wp_error( $result ) ) {
				throw new RuntimeException( 'Could not scan a site-isolation fixture.' );
			}
		} finally {
			restore_current_blog();
		}
	}
	foreach ( array( 1, 2 ) as $fixture_site_id ) {
		switch_to_blog( $fixture_site_id );
		try {
			$records = get_posts(
				array(
					'post_type'   => 'elt_external_link',
					'post_parent' => $fixtures[ $fixture_site_id ]['source_id'],
				)
			);
			if ( count( $records ) !== 1 || get_post_meta( $records[0]->ID, '_elt_scan_id', true ) !== $scan_id || get_post_meta( $records[0]->ID, '_elt_url', true ) !== ( 'https://site' . $fixture_site_id . '.example.test/' ) || get_option( 'href_scanner_internal_domains' ) !== array( 'site' . $fixture_site_id . '.example.test', '', '' ) ) {
				throw new RuntimeException( 'Multisite records and settings must remain separate for each blog.' );
			}
		} finally {
			restore_current_blog();
		}
	}
	echo "Network activation, per-site records and option-isolation checks passed.\n";
} finally {
	foreach ( $fixtures as $fixture_site_id => $fixture ) {
		switch_to_blog( $fixture_site_id );
		try {
			foreach ( get_posts(
				array(
					'post_type'      => array( 'elt_external_link', 'elt_internal_link' ),
					'post_parent'    => $fixture['source_id'],
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			) as $record_id ) {
				wp_delete_post( $record_id, true );
			}
			wp_delete_post( $fixture['source_id'], true );
			false === $fixture['domains'] ? delete_option( 'href_scanner_internal_domains' ) : update_option( 'href_scanner_internal_domains', $fixture['domains'] );
		} finally {
			restore_current_blog();
		}
	}
}
