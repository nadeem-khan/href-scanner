<?php
/**
 * Check complete and quick scan selection, resumption and cleanup.
 *
 * @package HrefScanner
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

require __DIR__ . '/bootstrap.php';

/**
 * Require a disposable scan regression condition to hold.
 *
 * @param bool   $condition Required regression condition.
 * @param string $message Regression failure or validation message.
 * @throws RuntimeException If the regression expectation fails.
 * @return void
 */
function href_scanner_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

/**
 * Complete a bounded fixture scan or fail on an error.
 *
 * @param string $mode Complete or quick scan mode.
 * @throws RuntimeException If the regression expectation fails.
 * @return array
 */
function href_scanner_finish_scan( $mode ) {
	$scan_id = '';
	for ( $batch = 0; $batch < 20; $batch++ ) {
		$state = href_scanner_scan_batch( $scan_id, $mode );
		href_scanner_assert( ! is_wp_error( $state ), is_wp_error( $state ) ? $state->get_error_message() : 'Scan failed.' );
		if ( 'done' === $state['phase'] ) {
			return $state;
		}
		$scan_id = $state['scan_id'];
	}
	throw new RuntimeException( 'Scan did not finish.' );
}

/**
 * Create a disposable scan source fixture.
 *
 * @param mixed  $type Type.
 * @param string $content Saved source HTML.
 * @return int
 */
function href_scanner_test_source( $type, $content ) {
	global $href_scanner_source_ids;
	$source_id = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'draft',
			'post_title'   => 'Scan verification',
			'post_content' => $content,
		),
		true
	);
	href_scanner_assert( ! is_wp_error( $source_id ), 'Could not create a test source.' );
	$href_scanner_source_ids[] = $source_id;
	return $source_id;
}

/**
 * Set controlled source dates for cutoff regression checks.
 *
 * @param mixed $source_id Source id.
 * @param mixed $created_gmt Created gmt.
 * @param mixed $modified_gmt Modified gmt.
 * @param mixed $zero_gmt Zero gmt.
 * @return void
 */
function href_scanner_test_dates( $source_id, $created_gmt, $modified_gmt, $zero_gmt = false ) {
	$set_dates = function ( $data, $postarr ) use ( $source_id, $created_gmt, $modified_gmt, $zero_gmt ) {
		if ( (int) ( $postarr['ID'] ?? 0 ) === (int) $source_id ) {
			$data['post_date']         = get_date_from_gmt( $created_gmt );
			$data['post_date_gmt']     = $zero_gmt ? '0000-00-00 00:00:00' : $created_gmt;
			$data['post_modified']     = get_date_from_gmt( $modified_gmt );
			$data['post_modified_gmt'] = $zero_gmt ? '0000-00-00 00:00:00' : $modified_gmt;
		}
		return $data;
	};
	add_filter( 'wp_insert_post_data', $set_dates, 10, 2 );
	try {
		$result = wp_update_post( array( 'ID' => $source_id ), true );
		href_scanner_assert( ! is_wp_error( $result ), 'Could not set fixture cutoff dates.' );
	} finally {
		remove_filter( 'wp_insert_post_data', $set_dates );
	}
}

$href_scanner_source_ids = array();
register_shutdown_function(
	function () use ( &$href_scanner_source_ids ) {
		foreach ( $href_scanner_source_ids as $source_id ) {
			foreach ( get_posts(
				array(
					'post_type'      => array( 'elt_external_link', 'elt_internal_link' ),
					'post_parent'    => $source_id,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			) as $record_id ) {
				wp_delete_post( $record_id, true );
			}
			wp_delete_post( $source_id, true );
		}
	}
);

foreach ( get_posts(
	array(
		'post_type'      => array( 'post', 'page', 'elt_external_link', 'elt_internal_link' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
	)
) as $fixture_post ) {
	wp_delete_post( $fixture_post->ID, true );
}
register_post_type( 'href_test_book', array( 'public' => true ) );
register_post_type( 'href_test_other', array( 'public' => true ) );
update_option( 'timezone_string', 'Asia/Karachi' );
update_option( 'href_scanner_content_types', array( 'post', 'page', 'href_test_book' ) );
delete_option( 'href_scanner_last_complete_scan' );
delete_option( 'elt_last_scan' );
delete_option( 'elt_scan_state' );
href_scanner_assert( is_wp_error( href_scanner_scan_batch( '', 'quick' ) ), 'A quick scan must require a completed complete scan.' );
href_scanner_assert( is_wp_error( href_scanner_scan_batch( '', 'invalid' ) ), 'Unknown scan modes must be rejected.' );

$unchanged_id = href_scanner_test_source( 'post', '<a href="https://unchanged.test/">Unchanged</a>' );
$changed_id   = href_scanner_test_source( 'page', '<a href="https://obsolete.test/">Old</a><a href="/old/">Old internal</a>' );
$book_id      = href_scanner_test_source( 'href_test_book', '<a href="https://book.test/">Book</a>' );
$deleted_id   = href_scanner_test_source( 'href_test_book', '<a href="https://deleted.test/">Deleted</a>' );
$trashed_id   = href_scanner_test_source( 'post', '<a href="https://trashed.test/">Trashed</a>' );
$state        = href_scanner_scan_batch();
href_scanner_assert( 5 === $state['processed'] && ! href_scanner_last_complete_scan(), 'An unfinished complete scan must not save its completion date.' );
href_scanner_assert( is_wp_error( href_scanner_scan_batch( '', 'quick' ) ), 'An active complete scan must prevent a competing quick scan.' );
$complete = href_scanner_finish_scan( 'complete' );
href_scanner_assert( 'complete' === $complete['mode'] && ! empty( $complete['completed_at_gmt'] ) && get_option( 'href_scanner_last_complete_scan' ) === $complete, 'Complete scans must save their completion dates after cleanup.' );
$unchanged_records   = get_posts(
	array(
		'post_type'   => array( 'elt_external_link', 'elt_internal_link' ),
		'post_parent' => $unchanged_id,
		'fields'      => 'ids',
	)
);
$unchanged_record_id = $unchanged_records[0];
$cutoff              = strtotime( $complete['completed_at_gmt'] . ' UTC' );
$old_date            = gmdate( 'Y-m-d H:i:s', $cutoff - 60 );
$new_date            = gmdate( 'Y-m-d H:i:s', $cutoff + 10 );
foreach ( array( $unchanged_id, $changed_id, $book_id, $deleted_id, $trashed_id ) as $source_id ) {
	href_scanner_test_dates( $source_id, $old_date, $old_date );
}
wp_update_post(
	array(
		'ID'           => $changed_id,
		'post_content' => '<a href="/updated/">Updated internal</a>',
	)
);
href_scanner_test_dates( $changed_id, $old_date, $new_date );
$new_book_id = href_scanner_test_source( 'href_test_book', '<a href="https://new-book.test/">New book</a>' );
href_scanner_test_dates( $new_book_id, $new_date, $new_date );
$new_draft_id = href_scanner_test_source( 'post', '<a href="/new-draft/">New draft</a>' );
href_scanner_test_dates( $new_draft_id, $new_date, $new_date, true );
$other_id = href_scanner_test_source( 'href_test_other', '<a href="https://excluded.test/">Excluded</a>' );
href_scanner_test_dates( $other_id, $new_date, $new_date );
href_scanner_scan_post( get_post( $other_id ), 'older-selection', array() );
wp_delete_post( $deleted_id, true );
wp_trash_post( $trashed_id );

$state = href_scanner_scan_batch( '', 'quick' );
href_scanner_assert( 3 === $state['total'] && 3 === $state['processed'] && 'quick' === $state['mode'], 'Quick scans must include updated pages, new custom content and drafts with local dates only.' );
href_scanner_assert( is_wp_error( href_scanner_scan_batch( $state['scan_id'], 'complete' ) ), 'An active quick scan must prevent switching its mode.' );
$quick = href_scanner_finish_scan( 'quick' );
href_scanner_assert( get_option( 'href_scanner_last_complete_scan' ) === $complete, 'A quick scan must preserve the complete-scan cutoff.' );
href_scanner_assert( get_post( $unchanged_record_id ) && get_post_meta( $unchanged_record_id, '_elt_scan_id', true ) === $complete['scan_id'], 'Unchanged link records must survive quick-scan cleanup without being rewritten.' );
href_scanner_assert(
	! get_posts(
		array(
			'post_type'       => array( 'elt_external_link', 'elt_internal_link' ),
			'post_parent__in' => array( $deleted_id, $trashed_id ),
		)
	),
	'Quick scans must remove records from deleted and trashed sources.'
);
$changed_records = get_posts(
	array(
		'post_type'   => array( 'elt_external_link', 'elt_internal_link' ),
		'post_parent' => $changed_id,
	)
);
href_scanner_assert( count( $changed_records ) === 1 && wp_parse_url( get_post_meta( $changed_records[0]->ID, '_elt_url', true ), PHP_URL_PATH ) === '/updated/' && 'elt_internal_link' === $changed_records[0]->post_type, 'Quick scans must remove obsolete hrefs and refresh changed links.' );
href_scanner_assert(
	count(
		get_posts(
			array(
				'post_type'   => 'elt_external_link',
				'post_parent' => $other_id,
			)
		)
	) === 1,
	'Quick scans must preserve live records outside the selected types.'
);
$quick_again = href_scanner_finish_scan( 'quick' );
href_scanner_assert( 3 === $quick_again['processed'] && get_option( 'href_scanner_last_complete_scan' ) === $complete, 'Repeated quick scans must keep using the same complete-scan cutoff.' );
$next_complete = href_scanner_finish_scan( 'complete' );
href_scanner_assert(
	$next_complete['scan_id'] !== $complete['scan_id'] && get_option( 'href_scanner_last_complete_scan' ) === $next_complete && ! get_posts(
		array(
			'post_type'   => 'elt_external_link',
			'post_parent' => $other_id,
		)
	),
	'A new complete scan must refresh the baseline and remove records from unselected types.'
);

$legacy_complete = $next_complete;
unset( $legacy_complete['mode'], $legacy_complete['completed_at_gmt'] );
delete_option( 'href_scanner_last_complete_scan' );
update_option( 'elt_last_scan', $legacy_complete, false );
foreach ( array( $unchanged_id, $changed_id, $book_id, $new_book_id, $new_draft_id ) as $source_id ) {
	href_scanner_test_dates( $source_id, $old_date, $old_date );
}
$empty_quick = href_scanner_finish_scan( 'quick' );
href_scanner_assert( 0 === $empty_quick['processed'] && get_option( 'href_scanner_last_complete_scan' ) === $legacy_complete, 'A legacy complete scan must supply a timezone-correct cutoff, survive an empty quick scan and remain available afterward.' );

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
$admin_users = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
wp_set_current_user( $admin_users[0]->ID );
href_scanner_register_settings();
href_scanner_admin_menu();
global $menu;
href_scanner_assert(
	count(
		array_filter(
			$menu,
			function ( $item ) {
				return 'href Scanner' === $item[0] && 'elt-external-report' === $item[2];
			}
		)
	) === 1,
	'The top-level menu must be named href Scanner.'
);
ob_start();
href_scanner_settings_page();
$settings_html = ob_get_clean();
href_scanner_assert( strpos( $settings_html, 'Start Complete Scan' ) !== false && strpos( $settings_html, 'Start Quick Scan' ) !== false && strpos( $settings_html, 'button button-secondary' ) !== false && strpos( $settings_html, $next_complete['completed_at'] ) !== false, 'Settings must display both scan buttons and the saved complete-scan completion date.' );
foreach ( array( 'attachment', 'revision', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_navigation', 'elt_external_link', 'elt_internal_link' ) as $internal_type ) {
	href_scanner_assert( strpos( $settings_html, 'value="' . $internal_type . '"' ) === false, 'Settings must hide core internal types and generated records.' );
}
echo "Complete and quick scans, date cutoffs, draft dates, cleanup, menu and settings checks passed.\n";
