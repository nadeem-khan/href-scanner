<?php
/**
 * Check generated admin URLs, metadata and report pagination.
 *
 * @package HrefScanner
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

require __DIR__ . '/bootstrap.php';
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

$settings_hook = get_plugin_page_hookname( 'href-scanner-settings', 'elt-external-report' );
href_scanner_admin_scripts( $settings_hook );
$script = wp_scripts()->registered['href-scanner-admin'];
if ( ! wp_script_is( 'href-scanner-admin', 'enqueued' ) || wp_parse_url( $script->src, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) || ! in_array( 'wp-i18n', $script->deps, true ) ) {
	throw new RuntimeException( 'The settings scanner must load from the local host with translation support.' );
}
foreach ( array( 'elt_external_link', 'elt_internal_link' ) as $fixture_post_type ) {
	set_current_screen( 'edit-' . $fixture_post_type );
	href_scanner_admin_scripts( 'edit.php' );
	$style = wp_styles()->registered['href-scanner-listings'];
	if ( ! wp_style_is( 'href-scanner-listings', 'enqueued' ) || wp_parse_url( $style->src, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		throw new RuntimeException( 'Both link listings must load their scoped styles from the local host.' );
	}
	$bulk_action_hook = 'bulk_actions-edit-' . $fixture_post_type;
	if ( apply_filters( $bulk_action_hook, array( 'edit' => 'Bulk edit' ) ) !== array() ) {
		throw new RuntimeException( 'Read-only link records must not offer unusable bulk-edit controls.' );
	}
	if ( ! apply_filters( 'disable_months_dropdown', false, $fixture_post_type ) ) {
		throw new RuntimeException( 'Link listings must omit the unrelated scan-record date filter.' );
	}
	wp_dequeue_style( 'href-scanner-listings' );
}
set_current_screen( 'edit-post' );
href_scanner_admin_scripts( 'edit.php' );
if ( wp_style_is( 'href-scanner-listings', 'enqueued' ) ) {
	throw new RuntimeException( 'Other WordPress listings must not load the link-listing stylesheet.' );
}

ob_start();
href_scanner_settings_page();
$settings_html = ob_get_clean();
if ( substr_count( $settings_html, 'name="href_scanner_internal_domains[' ) !== 3 || strpos( $settings_html, 'id="href-scanner-scan"' ) === false || strpos( $settings_html, 'options.php' ) === false ) {
	throw new RuntimeException( 'Settings must display three domain fields, saved local domains, and the scanner.' );
}
if ( strpos( $settings_html, 'id="href-scanner-progress-bar" max="100"' ) === false || strpos( $settings_html, 'id="href-scanner-estimate"' ) === false || strpos( $settings_html, '<strong>Keep this page open until it finishes.</strong>' ) === false || strpos( $settings_html, 'Last quick scan finished:' ) !== false ) {
	throw new RuntimeException( 'Settings must show scan progress controls, a prominent stay-open reminder and no quick-scan timestamp.' );
}
$saved_domains = get_option( 'href_scanner_internal_domains', array( '', '', '' ) );
foreach ( array( 0, 1, 2 ) as $index ) {
	if ( strpos( $settings_html, 'name="href_scanner_internal_domains[' . $index . ']" value="' . esc_attr( $saved_domains[ $index ] ?? '' ) . '"' ) === false ) {
		throw new RuntimeException( 'Domain fields must display the values saved in Settings.' );
	}
}

foreach ( array( 'elt_scan_state', 'href_scanner_last_complete_scan', 'elt_last_scan' ) as $option_name ) {
	add_filter( 'pre_option_' . $option_name, '__return_empty_array' );
}
ob_start();
href_scanner_settings_page();
$no_complete_html = ob_get_clean();
if ( ! preg_match( '/<button[^>]*id="href-scanner-quick-scan"[^>]*disabled=/', $no_complete_html ) || strpos( $no_complete_html, 'Quick Scan is disabled until a complete scan finishes.' ) === false || strpos( $no_complete_html, '<strong>Complete Scan:</strong>' ) === false || strpos( $no_complete_html, '<strong>Quick Scan:</strong>' ) === false ) {
	throw new RuntimeException( 'Quick Scan must be disabled without a completed complete scan, with both scan modes explained.' );
}
remove_filter( 'pre_option_href_scanner_last_complete_scan', '__return_empty_array' );
$completed_scan = function () {
	return array(
		'completed_at' => current_time( 'mysql' ),
		'processed'    => 0,
		'external'     => 0,
		'internal'     => 0,
	);
};
add_filter( 'pre_option_href_scanner_last_complete_scan', $completed_scan );
ob_start();
href_scanner_settings_page();
$with_complete_html = ob_get_clean();
if ( preg_match( '/<button[^>]*id="href-scanner-quick-scan"[^>]*disabled=/', $with_complete_html ) || strpos( $with_complete_html, 'Last complete scan finished:' ) === false ) {
	throw new RuntimeException( 'A finished complete scan must enable Quick Scan and display its completion date.' );
}
remove_filter( 'pre_option_href_scanner_last_complete_scan', $completed_scan );
foreach ( array( 'elt_scan_state', 'elt_last_scan' ) as $option_name ) {
	remove_filter( 'pre_option_' . $option_name, '__return_empty_array' );
}

foreach ( array( 'elt-external-report', 'elt-internal-report' ) as $fixture_page ) {
	set_current_screen( get_plugin_page_hookname( $fixture_page, 'elt-external-report' ) );
	ob_start();
	href_scanner_report_page();
	$report_html = ob_get_clean();
	if ( strpos( $report_html, 'id="href-scanner-scan"' ) !== false || strpos( $report_html, 'page=href-scanner-settings' ) === false ) {
		throw new RuntimeException( 'Reports must link to Settings without their own scanner.' );
	}
	if ( strpos( $report_html, 'class="button button-primary"' ) === false || strpos( $report_html, 'class="displaying-num"' ) === false ) {
		throw new RuntimeException( 'Reports must use a prominent detail button and native WordPress pagination.' );
	}
}

$record_ids     = array();
$created_groups = 0;
$report_queries = array();
$capture_query  = function ( $request, $query ) use ( &$report_queries ) {
	if ( $query->get( 'href_scanner_report' ) ) {
		$report_queries[] = $request;
	}
	return $request;
};
add_filter( 'posts_request', $capture_query, 10, 2 );
set_current_screen( get_plugin_page_hookname( 'elt-internal-report', 'elt-external-report' ) );
try {
	if ( href_scanner_report_query( 'elt_internal_link' )->found_posts !== 0 ) {
		throw new RuntimeException( 'Pagination fixtures require an empty disposable link report.' );
	}
	foreach ( array( 0, 1, 51, 151 ) as $group_count ) {
		for ( ; $created_groups < $group_count; ++$created_groups ) {
			$target = 0 === $created_groups ? 'https://example.test/a%20page/?a=1&b=2' : 'https://example.test/target/' . $created_groups;
			for ( $occurrence = 0; $occurrence < ( 0 === $created_groups ? 7 : 1 ); ++$occurrence ) {
				$record_id = wp_insert_post(
					array(
						'post_type'   => 'elt_internal_link',
						'post_status' => 'publish',
						'post_title'  => 'Report pagination fixture',
						'meta_input'  => array( '_elt_group' => $target ),
					),
					true
				);
				if ( is_wp_error( $record_id ) ) {
					throw new RuntimeException( 'Could not create a report pagination fixture.' );
				}
				$record_ids[] = $record_id;
			}
		}
		foreach ( array( 1, 2, 4, 999 ) as $page_number ) {
			$_GET                   = array(
				'paged'                     => (string) $page_number,
				'href_scanner_filter_nonce' => wp_create_nonce( 'href_scanner_filter' ),
			);
			$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=elt-internal-report&paged=' . $page_number;
			$report_queries         = array();
			ob_start();
			href_scanner_report_page();
			$report_html     = ob_get_clean();
			$expected_page   = min( $page_number, max( 1, (int) ceil( $group_count / 50 ) ) );
			$expected_offset = ( $expected_page - 1 ) * 50;
			$last_query      = end( $report_queries );
			if ( ! preg_match( '/LIMIT\s+' . $expected_offset . '\s*,\s*50\b/', $last_query ) || strpos( $report_html, 'class="displaying-num"' ) === false || ( $group_count > 50 && strpos( $report_html, "class='pagination-links'" ) === false ) ) {
				throw new RuntimeException( 'Report pagination must handle empty, single, middle and out-of-range pages consistently.' );
			}
			if ( $group_count > 0 && 1 === $expected_page && ( strpos( $report_html, 'elt_group=https%3A%2F%2Fexample.test%2Fa%2520page%2F%3Fa%3D1%26b%3D2' ) === false || strpos( $report_html, '>7</a>' ) === false ) ) {
				throw new RuntimeException( 'Report counts must link to the exact URL group with encoded paths and query parameters.' );
			}
			if ( $group_count > 50 && strpos( $report_html, 'href_scanner_filter_nonce=' ) === false ) {
				throw new RuntimeException( 'Report pagination links must carry a valid filter nonce.' );
			}
		}
	}
	update_post_meta( $record_ids[0], '_elt_group', 'https://example.test/new-target' );
	$refreshed_report = href_scanner_report_query( 'elt_internal_link' );
	if ( 152 !== $refreshed_report->found_posts || 6 !== (int) $refreshed_report->posts[0]->link_count || isset( get_post( $record_ids[1] )->target ) ) {
		throw new RuntimeException( 'Reports must refresh metadata counts and keep aggregate fields out of cached post objects.' );
	}
	$fail_report          = function ( $request, $query ) {
		return $query->get( 'href_scanner_report' ) ? 'SELECT href_scanner_missing_column' : $request;
	};
	$previous_suppression = $wpdb->suppress_errors( true );
	add_filter( 'posts_request', $fail_report, 10, 2 );
	try {
		ob_start();
		href_scanner_report_page();
		$error_html = ob_get_clean();
		if ( strpos( $error_html, 'Could not load the link report.' ) === false ) {
			throw new RuntimeException( 'A failed native report query must display the database error notice.' );
		}
	} finally {
		remove_filter( 'posts_request', $fail_report );
		$wpdb->suppress_errors( $previous_suppression );
		$wpdb->last_error = '';
	}
} finally {
	remove_filter( 'posts_request', $capture_query );
	foreach ( $record_ids as $record_id ) {
		wp_delete_post( $record_id, true );
	}
	$_GET = array();
}

$fixture_group                     = 'https://example.test/a%20page/?a=1&b=2';
$_GET['elt_group']                 = $fixture_group;
$_GET['href_scanner_filter_nonce'] = wp_create_nonce( 'href_scanner_filter' );
set_current_screen( 'edit-elt_internal_link' );
ob_start();
href_scanner_export_button( 'top' );
$target_export_html = ob_get_clean();
preg_match( '/href="([^"]+)"/', $target_export_html, $export_match );
parse_str( wp_parse_url( html_entity_decode( $export_match[1] ), PHP_URL_QUERY ), $export_params );
if ( $export_params['elt_group'] !== $fixture_group || href_scanner_group_filter() !== $fixture_group ) {
	throw new RuntimeException( 'Exact target filters must preserve encoded paths and embedded query parameters in export links.' );
}
unset( $_GET['elt_group'] );

$plugin_data = get_plugin_data( dirname( __DIR__ ) . '/href-scanner.php', false );
if ( 'href Scanner' !== $plugin_data['Name'] || 'Nadeem Khan' !== $plugin_data['Author'] || 'href-scanner' !== $plugin_data['TextDomain'] || '1.0.0' !== $plugin_data['Version'] ) {
	throw new RuntimeException( 'Plugin release metadata is incorrect.' );
}

echo "Local settings, reports, script URL and plugin metadata checks passed.\n";
