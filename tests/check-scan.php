<?php
/**
 * Check scan persistence, reclassification and stale-record cleanup.
 *
 * @package HrefScanner
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

require __DIR__ . '/bootstrap.php';

$admin_users = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
wp_set_current_user( $admin_users[0]->ID );
$source_id = wp_insert_post(
	wp_slash(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => 'href Scanner verification',
			'post_content' => '<a href="/target/">Relative</a><a href="https://alias.test/target/" rel="nofollow">Author\'s anchor</a><a href="https://outside.test/path?x=1&amp;y=2">External</a>',
		)
	),
	true
);
if ( is_wp_error( $source_id ) ) {
	throw new RuntimeException( esc_html( $source_id->get_error_message() ) );
}

try {
	$counts = href_scanner_scan_post( get_post( $source_id ), 'href-scanner-test', array( 'alias.test', '', '' ) );
	if ( array(
		'external' => 1,
		'internal' => 2,
	) !== $counts ) {
		throw new RuntimeException( 'Initial scan classifications are incorrect.' );
	}
	$records = get_posts(
		array(
			'post_type'      => array( 'elt_internal_link', 'elt_external_link' ),
			'post_parent'    => $source_id,
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	if ( count( $records ) !== 3 || get_post_meta( $records[1]->ID, '_elt_anchor', true ) !== "Author's anchor" || get_post_meta( $records[1]->ID, '_elt_follow', true ) !== 'No Follow' || get_post_meta( $records[2]->ID, '_elt_url', true ) !== 'https://outside.test/path?x=1&y=2' || get_post_meta( $records[0]->ID, '_elt_source_title', true ) !== 'href Scanner verification' ) {
		throw new RuntimeException( 'Link metadata was not saved accurately.' );
	}
	$counts      = href_scanner_scan_post( get_post( $source_id ), 'href-scanner-test-2', array( '', '', '' ) );
	$new_records = get_posts(
		array(
			'post_type'      => array( 'elt_internal_link', 'elt_external_link' ),
			'post_parent'    => $source_id,
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	if ( array(
		'external' => 2,
		'internal' => 1,
	) !== $counts || array_column( $records, 'ID' ) !== array_column( $new_records, 'ID' ) ) {
		throw new RuntimeException( 'Rescanning must reclassify records without duplicating them.' );
	}
	wp_update_post(
		array(
			'ID'           => $source_id,
			'post_content' => 'No links remain.',
		)
	);
	href_scanner_scan_post( get_post( $source_id ), 'href-scanner-test-3', array() );
	if ( get_posts(
		array(
			'post_type'   => array( 'elt_internal_link', 'elt_external_link' ),
			'post_parent' => $source_id,
		)
	) ) {
		throw new RuntimeException( 'Outdated link records must be removed.' );
	}
	echo "Local scan persistence, reclassification and stale-record cleanup checks passed.\n";
} finally {
	foreach ( get_posts(
		array(
			'post_type'      => array( 'elt_internal_link', 'elt_external_link' ),
			'post_parent'    => $source_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	) as $record_id ) {
		wp_delete_post( $record_id, true );
	}
	wp_delete_post( $source_id, true );
}
