<?php
/**
 * Scan saved WordPress content and report internal and external link occurrences.
 *
 * Plugin Name: href Scanner
 * Plugin URI: https://github.com/nadeem-khan/href-scanner
 * Description: Scan internal and external links, review anchor text and follow attributes, and export filtered link reports.
 * Version: 1.0.0
 * Author: Nadeem Khan
 * Author URI: https://profiles.wordpress.org/chillopedia/
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: href-scanner
 * Domain Path: /languages
 *
 * @package HrefScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'href_scanner_register_post_types' );
add_action( 'admin_menu', 'href_scanner_admin_menu' );
add_action( 'admin_init', 'href_scanner_register_settings' );
add_action( 'admin_enqueue_scripts', 'href_scanner_admin_scripts' );
add_action( 'wp_ajax_href_scanner_scan', 'href_scanner_ajax_scan' );
add_action( 'restrict_manage_posts', 'href_scanner_list_filters', 10, 2 );
add_action( 'manage_posts_extra_tablenav', 'href_scanner_export_button' );
add_filter(
	'disable_months_dropdown',
	function ( $disabled, $post_type ) {
		return $disabled || in_array( $post_type, array( 'elt_external_link', 'elt_internal_link' ), true );
	},
	10,
	2
);
add_action( 'pre_get_posts', 'href_scanner_filter_list' );
add_filter( 'posts_where', 'href_scanner_filter_source_type', 10, 2 );
add_filter( 'posts_where', 'href_scanner_scan_where', 10, 2 );
add_filter( 'posts_clauses', 'href_scanner_report_clauses', 10, 2 );
add_action( 'admin_post_href_scanner_export', 'href_scanner_export_csv' );

/**
 * Register private link records and their admin columns.
 *
 * @return void
 */
function href_scanner_register_post_types() {
	foreach ( array( 'external', 'internal' ) as $link_type ) {
		register_post_type(
			'elt_' . $link_type . '_link',
			array(
				'labels'       => array(
					'name'          => 'external' === $link_type ? __( 'External Links', 'href-scanner' ) : __( 'Internal Links', 'href-scanner' ),
					'singular_name' => 'external' === $link_type ? __( 'External Link', 'href-scanner' ) : __( 'Internal Link', 'href-scanner' ),
					'menu_name'     => 'external' === $link_type ? __( 'External Links', 'href-scanner' ) : __( 'Internal Links', 'href-scanner' ),
					'all_items'     => 'external' === $link_type ? __( 'All External Links', 'href-scanner' ) : __( 'All Internal Links', 'href-scanner' ),
					'not_found'     => __( 'No links found. Run a scan from Settings.', 'href-scanner' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'elt-external-report',
				'show_in_rest' => false,
				'rewrite'      => false,
				'query_var'    => false,
				'supports'     => false,
				'menu_icon'    => 'dashicons-admin-links',
				'map_meta_cap' => false,
				'capabilities' => array(
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'read_private_posts' => 'manage_options',
					'read_post'          => 'manage_options',
					'edit_post'          => 'do_not_allow',
					'delete_post'        => 'do_not_allow',
					'delete_posts'       => 'do_not_allow',
					'create_posts'       => 'do_not_allow',
					'publish_posts'      => 'do_not_allow',
				),
			)
		);
		add_filter( 'manage_elt_' . $link_type . '_link_posts_columns', 'href_scanner_link_columns' );
		add_action( 'manage_elt_' . $link_type . '_link_posts_custom_column', 'href_scanner_link_column', 10, 2 );
		add_filter( 'bulk_actions-edit-elt_' . $link_type . '_link', '__return_empty_array' );
	}
}

/**
 * Register the scanner settings and report menus.
 *
 * @return void
 */
function href_scanner_admin_menu() {
	add_menu_page( __( 'href Scanner', 'href-scanner' ), __( 'href Scanner', 'href-scanner' ), 'manage_options', 'elt-external-report', 'href_scanner_report_page', 'dashicons-admin-links' );
	add_submenu_page( 'elt-external-report', __( 'External Link Report', 'href-scanner' ), __( 'External Link Report', 'href-scanner' ), 'manage_options', 'elt-external-report', 'href_scanner_report_page' );
	add_submenu_page( 'elt-external-report', __( 'Internal Link Report', 'href-scanner' ), __( 'Internal Link Report', 'href-scanner' ), 'manage_options', 'elt-internal-report', 'href_scanner_report_page' );
	add_submenu_page( 'elt-external-report', __( 'href Scanner Settings', 'href-scanner' ), __( 'Settings', 'href-scanner' ), 'manage_options', 'href-scanner-settings', 'href_scanner_settings_page' );
}

/**
 * Enqueue assets only on scanner admin screens.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function href_scanner_admin_scripts( $hook_suffix ) {
	$screen = get_current_screen();
	if ( 'edit.php' === $hook_suffix && $screen && in_array( $screen->post_type, array( 'elt_external_link', 'elt_internal_link' ), true ) ) {
		wp_enqueue_style( 'href-scanner-listings', plugins_url( 'listings.css', __FILE__ ), array(), '1.0.0' );
	}
	if ( strpos( $hook_suffix, '_page_href-scanner-settings' ) === false ) {
		return;
	}
	wp_enqueue_script( 'href-scanner-admin', plugins_url( 'admin.js', __FILE__ ), array( 'jquery', 'wp-i18n' ), '1.0.0', true );
	wp_set_script_translations( 'href-scanner-admin', 'href-scanner' );
}

/**
 * Register domain and source-content settings.
 *
 * @return void
 */
function href_scanner_register_settings() {
	register_setting(
		'href_scanner_settings',
		'href_scanner_internal_domains',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'href_scanner_sanitize_domains',
			'default'           => array( '', '', '' ),
		)
	);
	add_settings_section( 'href_scanner_domains', __( 'Internal Domains', 'href-scanner' ), '__return_false', 'href-scanner-settings' );
	foreach ( array( __( 'Internal Domain 1', 'href-scanner' ), __( 'Internal Domain 2', 'href-scanner' ), __( 'Internal Domain 3', 'href-scanner' ) ) as $index => $label ) {
		add_settings_field(
			'href_scanner_domain_' . $index,
			$label,
			'href_scanner_domain_field',
			'href-scanner-settings',
			'href_scanner_domains',
			array(
				'index'     => $index,
				'label_for' => 'href_scanner_domain_' . $index,
			)
		);
	}
	register_setting(
		'href_scanner_settings',
		'href_scanner_content_types',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'href_scanner_sanitize_content_types',
			'default'           => array( 'post', 'page' ),
		)
	);
	add_settings_section( 'href_scanner_content', __( 'Content to Scan', 'href-scanner' ), '__return_false', 'href-scanner-settings' );
	add_settings_field( 'href_scanner_content_types', __( 'Content Types', 'href-scanner' ), 'href_scanner_content_types_field', 'href-scanner-settings', 'href_scanner_content' );
}

/**
 * Keep only registered source types with editor support.
 *
 * @param mixed $input Submitted setting value.
 * @return array
 */
function href_scanner_sanitize_content_types( $input ) {
	$registered_types = array_keys( href_scanner_content_types() );
	return array_values( array_intersect( $registered_types, array_filter( (array) $input, 'is_string' ) ) );
}

/**
 * Return supported source-content type objects.
 *
 * @return array
 */
function href_scanner_content_types() {
	$content_types = array();
	foreach ( get_post_types( array(), 'objects' ) as $content_type ) {
		if ( post_type_supports( $content_type->name, 'editor' ) && ( in_array( $content_type->name, array( 'post', 'page' ), true ) || ( ! $content_type->_builtin && ! in_array( $content_type->name, array( 'elt_external_link', 'elt_internal_link' ), true ) ) ) ) {
			$content_types[ $content_type->name ] = $content_type;
		}
	}
	return $content_types;
}

/**
 * Render source-content selection controls.
 *
 * @return void
 */
function href_scanner_content_types_field() {
	$selected_types = get_option( 'href_scanner_content_types', array( 'post', 'page' ) );
	echo '<input type="hidden" name="href_scanner_content_types[]" value="">';
	foreach ( href_scanner_content_types() as $content_type ) {
		echo '<label><input type="checkbox" name="href_scanner_content_types[]" value="' . esc_attr( $content_type->name ) . '"' . checked( in_array( $content_type->name, $selected_types, true ), true, false ) . '> ' . esc_html( $content_type->labels->name . ' (' . $content_type->name . ')' ) . '</label><br>';
	}
	echo '<p class="description">' . esc_html__( 'Select Posts, Pages or custom content types that support the editor. A complete scan removes records from unselected types. After changing domains or content types, run a complete scan to rebuild your link profile.', 'href-scanner' ) . '</p>';
}

/**
 * Return the latest completed full scan, including legacy state.
 *
 * @return array|false
 */
function href_scanner_last_complete_scan() {
	$last_complete = get_option( 'href_scanner_last_complete_scan' );
	if ( ! $last_complete ) {
		$last_scan = get_option( 'elt_last_scan' );
		if ( $last_scan && ( $last_scan['mode'] ?? 'complete' ) === 'complete' && 'done' === $last_scan['phase'] ) {
			$last_complete = $last_scan;
		}
	}
	return $last_complete && ! empty( $last_complete['completed_at'] ) ? $last_complete : false;
}

/**
 * Normalize domain aliases while retaining invalid existing values.
 *
 * @param mixed $input Submitted setting value.
 * @return array
 */
function href_scanner_sanitize_domains( $input ) {
	$saved_domains = get_option( 'href_scanner_internal_domains', array( '', '', '' ) );
	$domains       = array( '', '', '' );
	foreach ( $domains as $index => $domain ) {
		$value = is_array( $input ) && isset( $input[ $index ] ) && is_string( $input[ $index ] ) ? trim( $input[ $index ] ) : '';
		if ( '' === $value ) {
			continue;
		}
		$parts = wp_parse_url( strpos( $value, '://' ) === false ? 'https://' . ltrim( $value, '/' ) : $value );
		try {
			$host = ! empty( $parts['host'] ) ? strtolower( \WpOrg\Requests\IdnaEncoder::encode( $parts['host'] ) ) : '';
		} catch ( \WpOrg\Requests\Exception $error ) {
			$host = '';
		}
		if ( ! $host || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ! filter_var( 'https://' . $host . '/', FILTER_VALIDATE_URL ) || preg_match( '/[\s*]/', $host ) ) {
			$domains[ $index ] = $saved_domains[ $index ] ?? '';
			add_settings_error( 'href_scanner_internal_domains', 'href_scanner_domain_' . $index, __( 'Enter a valid domain or HTTP(S) URL. The previous value was retained.', 'href-scanner' ) );
			continue;
		}
		$domains[ $index ] = preg_replace( '/^www\./i', '', rtrim( $host, '.' ) );
	}
	return $domains;
}

/**
 * Render a domain-alias setting.
 *
 * @param array $args Setting or query arguments.
 * @return void
 */
function href_scanner_domain_field( $args ) {
	$domains = get_option( 'href_scanner_internal_domains', array( '', '', '' ) );
	echo '<input type="text" class="regular-text" id="' . esc_attr( $args['label_for'] ) . '" name="href_scanner_internal_domains[' . esc_attr( $args['index'] ) . ']" value="' . esc_attr( $domains[ $args['index'] ] ?? '' ) . '" placeholder="example.com">';
}

/**
 * Render authorized scan settings and progress controls.
 *
 * @return void
 */
function href_scanner_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage these settings.', 'href-scanner' ), '', array( 'response' => 403 ) );
	}
	$state            = get_option( 'elt_scan_state' );
	$last_complete    = href_scanner_last_complete_scan();
	$scan_mode        = $state['mode'] ?? 'complete';
	$progress_message = $state ? sprintf(
		// translators: %d: Number of processed content items.
		__( 'Scan paused or running: %d items processed. Resume the active scan to continue.', 'href-scanner' ),
		$state['processed']
	) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'href Scanner Settings', 'href-scanner' ); ?></h1>
		<?php settings_errors(); ?>
		<p><?php esc_html_e( 'Relative URLs and links to the domains configured under Settings → General as Site Address (URL) and WordPress Address (URL) are always internal. Add up to three additional domains below. A www prefix is ignored; other subdomains must be listed separately. You may enter a domain or a full HTTP(S) URL.', 'href-scanner' ); ?></p>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'href_scanner_settings' );
			do_settings_sections( 'href-scanner-settings' );
			submit_button();
			?>
		</form>
		<h2><?php esc_html_e( 'Scan Content', 'href-scanner' ); ?></h2>
		<p><strong><?php esc_html_e( 'Complete Scan:', 'href-scanner' ); ?></strong> <?php esc_html_e( 'Scans all content in the selected types, refreshes the full link profile, removes outdated records and saves its completion date.', 'href-scanner' ); ?></p>
		<p><strong><?php esc_html_e( 'Quick Scan:', 'href-scanner' ); ?></strong> <?php esc_html_e( 'Refreshes only selected content created or updated since the last complete scan finished. Unchanged records are kept, and the complete-scan date remains the cutoff. Quick Scan is disabled until a complete scan finishes.', 'href-scanner' ); ?></p>
		<p><?php esc_html_e( 'Save domain and content-type changes before starting a scan. An interrupted scan uses its original settings. Run another scan after it finishes to apply changes.', 'href-scanner' ); ?></p>
		<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Keep this page open until it finishes.', 'href-scanner' ); ?></strong></p></div>
		<p>
			<button type="button" class="button button-primary" id="href-scanner-scan" data-mode="complete" data-nonce="<?php echo esc_attr( wp_create_nonce( 'href_scanner_scan' ) ); ?>" <?php disabled( $state && 'complete' !== $scan_mode ); ?>><?php echo $state && 'complete' === $scan_mode ? esc_html__( 'Resume Complete Scan', 'href-scanner' ) : esc_html__( 'Start Complete Scan', 'href-scanner' ); ?></button>
			<button type="button" class="button button-secondary" id="href-scanner-quick-scan" data-mode="quick" data-nonce="<?php echo esc_attr( wp_create_nonce( 'href_scanner_scan' ) ); ?>" <?php disabled( ! $last_complete || ( $state && 'quick' !== $scan_mode ) ); ?>><?php echo $state && 'quick' === $scan_mode ? esc_html__( 'Resume Quick Scan', 'href-scanner' ) : esc_html__( 'Start Quick Scan', 'href-scanner' ); ?></button>
		</p>
		<progress class="regular-text" id="href-scanner-progress-bar" max="100" value="<?php echo esc_attr( $state && $state['total'] ? min( 99, (int) floor( $state['processed'] / $state['total'] * 100 ) ) : 0 ); ?>" data-processed="<?php echo esc_attr( $state['processed'] ?? 0 ); ?>" aria-label="<?php esc_attr_e( 'Scan progress', 'href-scanner' ); ?>" 
		<?php
		if ( ! $state ) {
			?>
			hidden<?php } ?>></progress>
		<p id="href-scanner-progress" role="status" aria-live="polite"><?php echo esc_html( $progress_message ); ?></p>
		<p id="href-scanner-estimate"></p>
		<p><?php esc_html_e( 'Scans saved content in the selected types, including drafts, pending, private, scheduled and inherited items. Trashed items and auto-drafts are excluded. Both reports are updated by each scan.', 'href-scanner' ); ?></p>
		<?php if ( $last_complete ) { ?>
			<p>
			<?php
			echo esc_html(
				sprintf(
				// translators: 1: Completion date, 2: Items, 3: External links, 4: Internal links.
					__( 'Last complete scan finished: %1$s — %2$d items, %3$d external links, %4$d internal links.', 'href-scanner' ),
					$last_complete['completed_at'],
					$last_complete['processed'],
					$last_complete['external'],
					$last_complete['internal']
				)
			);
			?>
				</p>
		<?php } ?>
	</div>
	<?php
}

/**
 * Replace default columns with link occurrence fields.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function href_scanner_link_columns( $columns ) {
	$columns = array(
		'elt_url'          => __( 'Link', 'href-scanner' ),
		'elt_anchor'       => __( 'Anchor Text', 'href-scanner' ),
		'elt_follow'       => __( 'Follow Type', 'href-scanner' ),
		'elt_source'       => __( 'Source Post', 'href-scanner' ),
		'elt_content_type' => __( 'Content Type', 'href-scanner' ),
	);
	return $columns;
}

/**
 * Read sanitized filters only after verifying their form or link nonce.
 *
 * @return array
 */
function href_scanner_filter_values() {
	if ( ! isset( $_GET['href_scanner_filter_nonce'] ) || ! is_string( $_GET['href_scanner_filter_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['href_scanner_filter_nonce'] ) ), 'href_scanner_filter' ) ) {
		return array();
	}
	$values = array();
	foreach ( array( 'elt_anchor', 'elt_follow', 'elt_source', 'elt_content_type', 'elt_group', 's', 'post_status', 'paged' ) as $filter_name ) {
		if ( ! isset( $_GET[ $filter_name ] ) || ! is_string( $_GET[ $filter_name ] ) ) {
			continue;
		}
		// Exact targets retain percent-encoded paths for metadata comparison.
		if ( 'elt_group' === $filter_name ) {
			$values[ $filter_name ] = trim( wp_strip_all_tags( wp_unslash( $_GET[ $filter_name ] ) ) );
		} else {
			$values[ $filter_name ] = sanitize_text_field( wp_unslash( $_GET[ $filter_name ] ) );
		}
	}
	return $values;
}

/**
 * Read an exact report target without losing encoded paths.
 *
 * @return string
 */
function href_scanner_group_filter() {
	return href_scanner_filter_values()['elt_group'] ?? '';
}

/**
 * Render the link-list filter controls.
 *
 * @param string $post_type Selected content or occurrence type.
 * @param string $which List navigation position.
 * @return void
 */
function href_scanner_list_filters( $post_type, $which ) {
	if ( 'top' !== $which || ! in_array( $post_type, array( 'elt_external_link', 'elt_internal_link' ), true ) ) {
		return;
	}
	$values = href_scanner_filter_values();
	wp_nonce_field( 'href_scanner_filter', 'href_scanner_filter_nonce', false );
	$group = $values['elt_group'] ?? '';
	if ( '' !== $group ) {
		echo '<div class="href-scanner-group-filter"><strong>' . esc_html__( 'Showing links to:', 'href-scanner' ) . '</strong> ' . esc_html( $group ) . ' <a href="' . esc_url( remove_query_arg( array( 'elt_group', 'paged' ) ), array( 'http', 'https' ) ) . '">' . esc_html__( 'Clear target filter', 'href-scanner' ) . '</a><input type="hidden" name="elt_group" value="' . esc_attr( $group ) . '"></div>';
	}
	foreach ( array(
		'elt_anchor' => __( 'Anchor Text', 'href-scanner' ),
		'elt_follow' => __( 'Follow Type', 'href-scanner' ),
		'elt_source' => __( 'Source Post', 'href-scanner' ),
	) as $filter_name => $label ) {
		$value = $values[ $filter_name ] ?? '';
		echo '<div class="href-scanner-filter"><label for="' . esc_attr( $filter_name ) . '">' . esc_html( $label ) . '</label>';
		if ( 'elt_follow' === $filter_name ) {
			echo '<select name="elt_follow" id="elt_follow"><option value="">' . esc_html__( 'All Follow Types', 'href-scanner' ) . '</option>';
			foreach ( array(
				'Do Follow' => __( 'Do Follow', 'href-scanner' ),
				'No Follow' => __( 'No Follow', 'href-scanner' ),
			) as $follow_type => $follow_label ) {
				echo '<option value="' . esc_attr( $follow_type ) . '"' . selected( $value, $follow_type, false ) . '>' . esc_html( $follow_label ) . '</option>';
			}
			echo '</select>';
		} else {
			echo '<input type="text" name="' . esc_attr( $filter_name ) . '" id="' . esc_attr( $filter_name ) . '" placeholder="' . esc_attr( $label ) . '" value="' . esc_attr( $value ) . '"> ';
		}
		echo '</div>';
	}
	$selected_type = sanitize_key( $values['elt_content_type'] ?? '' );
	echo '<div class="href-scanner-filter"><label for="elt_content_type">' . esc_html__( 'Content Type', 'href-scanner' ) . '</label>';
	echo '<select name="elt_content_type" id="elt_content_type"><option value="">' . esc_html__( 'All Content Types', 'href-scanner' ) . '</option>';
	foreach ( href_scanner_content_types() as $content_type ) {
		echo '<option value="' . esc_attr( $content_type->name ) . '"' . selected( $selected_type, $content_type->name, false ) . '>' . esc_html( $content_type->labels->name . ' (' . $content_type->name . ')' ) . '</option>';
	}
	echo '</select></div> ';
}

/**
 * Render an authorized export URL with the current filters.
 *
 * @param string $which List navigation position.
 * @return void
 */
function href_scanner_export_button( $which ) {
	$screen = get_current_screen();
	if ( 'top' !== $which || ! $screen || ! in_array( $screen->post_type, array( 'elt_external_link', 'elt_internal_link' ), true ) ) {
		return;
	}
	$export_args = array(
		'action'                    => 'href_scanner_export',
		'post_type'                 => $screen->post_type,
		'href_scanner_filter_nonce' => wp_create_nonce( 'href_scanner_filter' ),
	);
	$values      = href_scanner_filter_values();
	unset( $values['paged'] );
	$export_args = array_merge( $export_args, $values );
	echo '<a class="button button-primary alignright href-scanner-export" href="' . esc_url( wp_nonce_url( add_query_arg( array_map( 'rawurlencode', $export_args ), admin_url( 'admin-post.php' ) ), 'href_scanner_export' ), array( 'http', 'https' ) ) . '"><span class="dashicons dashicons-download" aria-hidden="true"></span> ' . esc_html__( 'Export All', 'href-scanner' ) . '</a> ';
}

/**
 * Apply validated link filters to listings and exports.
 *
 * @param WP_Query $query Current listing or export query.
 * @return void
 */
function href_scanner_filter_list( $query ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! is_admin() || ! in_array( $query->get( 'post_type' ), array( 'elt_external_link', 'elt_internal_link' ), true ) || ( ! $query->get( 'href_scanner_export' ) && ( ! $screen || 'edit' !== $screen->base || ! $query->is_main_query() ) ) ) {
		return;
	}
	$values  = href_scanner_filter_values();
	$filters = array( 'relation' => 'AND' );
	foreach ( array(
		'elt_anchor' => '_elt_anchor',
		'elt_follow' => '_elt_follow',
		'elt_source' => '_elt_source_title',
		'elt_group'  => '_elt_group',
	) as $filter_name => $meta_key ) {
		$value = $values[ $filter_name ] ?? '';
		if ( '' === $value || ( 'elt_follow' === $filter_name && ! in_array( $value, array( 'Do Follow', 'No Follow' ), true ) ) ) {
			continue;
		}
		$filters[] = array(
			'key'     => $meta_key,
			'value'   => $value,
			'compare' => in_array( $filter_name, array( 'elt_follow', 'elt_group' ), true ) ? '=' : 'LIKE',
		);
	}
	if ( count( $filters ) > 1 ) {
		$existing_query = $query->get( 'meta_query' );
		if ( $existing_query ) {
			$filters[] = $existing_query;
		}
		$query->set( 'meta_query', $filters );
	}
	$content_type = sanitize_key( $values['elt_content_type'] ?? '' );
	if ( in_array( $content_type, href_scanner_sanitize_content_types( array( $content_type ) ), true ) ) {
		$query->set( 'href_scanner_source_type', $content_type );
	}
}

/**
 * Filter occurrences by their current or stored source type.
 *
 * @param string   $where Existing SQL conditions.
 * @param WP_Query $query Current listing or export query.
 * @return string
 */
function href_scanner_filter_source_type( $where, $query ) {
	$content_type = $query->get( 'href_scanner_source_type' );
	if ( ! $content_type || ! in_array( $query->get( 'post_type' ), array( 'elt_external_link', 'elt_internal_link' ), true ) ) {
		return $where;
	}
	global $wpdb;
	return $where . $wpdb->prepare(
		" AND COALESCE((SELECT source.post_type FROM {$wpdb->posts} source WHERE source.ID = {$wpdb->posts}.post_parent LIMIT 1), (SELECT source_type.meta_value FROM {$wpdb->postmeta} source_type WHERE source_type.post_id = {$wpdb->posts}.ID AND source_type.meta_key = '_elt_source_type' LIMIT 1)) = %s",
		$content_type
	);
}

/**
 * Return the current source type with a metadata fallback.
 *
 * @param int $record_id Occurrence record ID.
 * @return string
 */
function href_scanner_source_type( $record_id ) {
	$source_type = get_post_type( wp_get_post_parent_id( $record_id ) );
	return $source_type ? $source_type : get_post_meta( $record_id, '_elt_source_type', true );
}

/**
 * Write every matching occurrence and neutralize spreadsheet formulas.
 *
 * @param resource $stream Writable CSV stream.
 * @param string   $post_type Selected content or occurrence type.
 * @param string   $search Applied listing search.
 * @param string   $post_status Requested record status.
 * @return void
 */
function href_scanner_write_csv( $stream, $post_type, $search = '', $post_status = 'any' ) {
	fputcsv( $stream, array( __( 'Link', 'href-scanner' ), __( 'Anchor Text', 'href-scanner' ), __( 'Follow Type', 'href-scanner' ), __( 'Source Post', 'href-scanner' ), __( 'Content Type', 'href-scanner' ) ), ',', '"', '' );
	$page_number = 1;
	do {
		$query = new WP_Query(
			array(
				'post_type'           => $post_type,
				'post_status'         => $post_status,
				's'                   => $search,
				'posts_per_page'      => 100,
				'paged'               => $page_number,
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'no_found_rows'       => true,
				'href_scanner_export' => true,
			)
		);
		foreach ( $query->posts as $record ) {
			$row = array( get_post_meta( $record->ID, '_elt_url', true ), get_post_meta( $record->ID, '_elt_anchor', true ), get_post_meta( $record->ID, '_elt_follow', true ), get_post_meta( $record->ID, '_elt_source_title', true ), href_scanner_source_type( $record->ID ) );
			foreach ( $row as &$value ) {
				$value = (string) $value;
				if ( preg_match( '/^[\x00-\x20]*[=+\-@]|^[\t\r\n]/', $value ) ) {
					$value = "'" . $value;
				}
			}
			unset( $value );
			fputcsv( $stream, $row, ',', '"', '' );
		}
		$page_count = count( $query->posts );
		++$page_number;
	} while ( 100 === $page_count );
}

/**
 * Authorize and stream a filtered CSV download.
 *
 * @return void
 */
function href_scanner_export_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to export link records.', 'href-scanner' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'href_scanner_export' );
	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	if ( ! in_array( $post_type, array( 'elt_external_link', 'elt_internal_link' ), true ) ) {
		wp_die( esc_html__( 'Invalid link listing.', 'href-scanner' ), '', array( 'response' => 400 ) );
	}
	$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$post_status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : 'any';
	$post_status = in_array( $post_status, get_post_stati(), true ) ? $post_status : 'any';
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="href-scanner-' . ( 'elt_external_link' === $post_type ? 'external' : 'internal' ) . '-links.csv"' );
	header( 'X-Content-Type-Options: nosniff' );
	$stream = fopen( 'php://output', 'w' );
	href_scanner_write_csv( $stream, $post_type, $search, $post_status );
	exit;
}

/**
 * Render an escaped occurrence field in the admin list.
 *
 * @param string $column Requested column key.
 * @param int    $post_id Occurrence record ID.
 * @return void
 */
function href_scanner_link_column( $column, $post_id ) {
	if ( 'elt_url' === $column ) {
		$url = get_post_meta( $post_id, '_elt_url', true );
		echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a>';
	} elseif ( 'elt_source' === $column ) {
		$source_id    = wp_get_post_parent_id( $post_id );
		$source_title = get_post_meta( $post_id, '_elt_source_title', true );
		$edit_url     = get_edit_post_link( $source_id );
		echo $edit_url ? '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $source_title ) . '</a>' : esc_html( $source_title );
	} elseif ( 'elt_follow' === $column ) {
		echo get_post_meta( $post_id, '_elt_follow', true ) === 'No Follow' ? esc_html__( 'No Follow', 'href-scanner' ) : esc_html__( 'Do Follow', 'href-scanner' );
	} elseif ( 'elt_anchor' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_' . $column, true ) );
	} elseif ( 'elt_content_type' === $column ) {
		$content_type = href_scanner_source_type( $post_id );
		$type_object  = get_post_type_object( $content_type );
		echo esc_html( $type_object ? $type_object->labels->singular_name . ' (' . $content_type . ')' : ( $content_type ? $content_type : __( 'Unknown', 'href-scanner' ) ) );
	}
}

/**
 * Parse saved HTTP links without fetching their destinations.
 *
 * @param string     $content Saved source HTML.
 * @param string     $source_url Source permalink for relative URLs.
 * @param array|null $internal_domains Additional internal hostnames, or saved settings.
 * @return array|WP_Error
 */
function href_scanner_extract_links( $content, $source_url, $internal_domains = null ) {
	$links = array();
	if ( trim( $content ) === '' ) {
		return $links;
	}
	$previous_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous_errors );
	if ( ! $loaded ) {
		return new WP_Error( 'elt_parse', __( 'Could not parse a post. The scan has stopped; existing records were retained.', 'href-scanner' ) );
	}
	$xpath            = new DOMXPath( $document );
	$home_parts       = wp_parse_url( home_url( '/' ) );
	$home_host        = preg_replace( '/^www\./i', '', rtrim( strtolower( \WpOrg\Requests\IdnaEncoder::encode( $home_parts['host'] ) ), '.' ) );
	$site_host        = preg_replace( '/^www\./i', '', rtrim( strtolower( \WpOrg\Requests\IdnaEncoder::encode( (string) wp_parse_url( site_url( '/' ), PHP_URL_HOST ) ) ), '.' ) );
	$internal_domains = $internal_domains ?? get_option( 'href_scanner_internal_domains', array( '', '', '' ) );
	$internal_domains = array_merge( array( $home_host, $site_host ), $internal_domains );
	foreach ( $document->getElementsByTagName( 'a' ) as $anchor ) {
		if ( ! $anchor->hasAttribute( 'href' ) ) {
			continue;
		}
		$href = trim( $anchor->getAttribute( 'href' ) );
		if ( '' === $href || preg_match( '/[\x00-\x1f\x7f]/', $href ) ) {
			continue;
		}
		try {
			$resolved_url = \WpOrg\Requests\Iri::absolutize( $source_url, $href );
			if ( ! $resolved_url ) {
				continue;
			}
			if ( null !== $resolved_url->ihost ) {
				$resolved_url->ihost = \WpOrg\Requests\IdnaEncoder::encode( $resolved_url->ihost );
			}
		} catch ( \WpOrg\Requests\Exception $error ) {
			continue;
		}
		$url   = $resolved_url->uri;
		$parts = wp_parse_url( $url );
		if ( ! $parts || ! filter_var( $url, FILTER_VALIDATE_URL ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) ) {
			continue;
		}
		$url = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( '' === $url ) {
			continue;
		}
		$host       = preg_replace( '/^www\./i', '', rtrim( strtolower( $parts['host'] ), '.' ) );
		$href_parts = wp_parse_url( $href );
		$relative   = empty( $href_parts['scheme'] ) && empty( $href_parts['host'] );
		$internal   = $relative || in_array( $host, $internal_domains, true );
		$group      = $host;
		if ( $internal ) {
			$group = $home_parts['scheme'] . '://' . $home_parts['host'];
			if ( isset( $home_parts['port'] ) ) {
				$group .= ':' . $home_parts['port'];
			}
			$path   = rtrim( $parts['path'] ?? '/', '/' );
			$group .= $path ? $path : '/';
			if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
				$group .= '?' . $parts['query'];
			}
		}
		$rel_tokens  = preg_split( '/\s+/', strtolower( trim( $anchor->getAttribute( 'rel' ) ) ) );
		$anchor_text = trim( preg_replace( '/\s+/u', ' ', $xpath->evaluate( 'string(.)', $anchor ) ) );
		$links[]     = array(
			'post_type' => $internal ? 'elt_internal_link' : 'elt_external_link',
			'url'       => $url,
			'anchor'    => $anchor_text,
			'follow'    => in_array( 'nofollow', $rel_tokens, true ) ? 'No Follow' : 'Do Follow',
			'group'     => $group,
		);
	}
	return $links;
}

/**
 * Refresh one source and remove its outdated occurrences.
 *
 * @param WP_Post    $source_post Source content to scan.
 * @param string     $scan_id Scan identifier for resumption.
 * @param array|null $internal_domains Additional internal hostnames, or saved settings.
 * @return array|WP_Error
 */
function href_scanner_scan_post( $source_post, $scan_id, $internal_domains ) {
	$links = href_scanner_extract_links( $source_post->post_content, get_permalink( $source_post ), $internal_domains );
	if ( is_wp_error( $links ) ) {
		return $links;
	}
	$existing_ids = get_posts(
		array(
			'post_type'      => array( 'elt_external_link', 'elt_internal_link' ),
			'post_status'    => 'any',
			'post_parent'    => $source_post->ID,
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$counts       = array(
		'external' => 0,
		'internal' => 0,
	);
	foreach ( $links as $index => $link ) {
		$record_id = wp_insert_post(
			wp_slash(
				array(
					'ID'          => $existing_ids[ $index ] ?? 0,
					'post_type'   => $link['post_type'],
					'post_status' => 'publish',
					'post_title'  => '' !== $link['anchor'] ? $link['anchor'] : $link['url'],
					'post_parent' => $source_post->ID,
					'meta_input'  => array(
						'_elt_url'          => $link['url'],
						'_elt_anchor'       => $link['anchor'],
						'_elt_follow'       => $link['follow'],
						'_elt_source_title' => $source_post->post_title,
						'_elt_source_type'  => $source_post->post_type,
						'_elt_group'        => $link['group'],
						'_elt_scan_id'      => $scan_id,
					),
				)
			),
			true
		);
		if ( is_wp_error( $record_id ) ) {
			return $record_id;
		}
		++$counts[ 'elt_external_link' === $link['post_type'] ? 'external' : 'internal' ];
	}
	foreach ( array_slice( $existing_ids, count( $links ) ) as $record_id ) {
		if ( ! wp_delete_post( $record_id, true ) ) {
			return new WP_Error( 'elt_delete', __( 'Could not remove an outdated link record. Resume the scan to retry.', 'href-scanner' ) );
		}
	}
	return $counts;
}

/**
 * Add snapshot, quick-cutoff or orphan conditions to scanner queries.
 *
 * @param string   $where Existing SQL conditions.
 * @param WP_Query $query Current scanner query.
 * @return string
 */
function href_scanner_scan_where( $where, $query ) {
	global $wpdb;
	$scan = $query->get( 'href_scanner_scan' );
	if ( $scan ) {
		if ( isset( $scan['max_id'] ) ) {
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d AND {$wpdb->posts}.ID <= %d", $scan['last_id'], $scan['max_id'] );
		}
		if ( 'quick' === $scan['mode'] ) {
			$where .= $wpdb->prepare(
				" AND ({$wpdb->posts}.post_date_gmt >= %s OR {$wpdb->posts}.post_modified_gmt >= %s OR ({$wpdb->posts}.post_date_gmt = '0000-00-00 00:00:00' AND {$wpdb->posts}.post_date >= %s) OR ({$wpdb->posts}.post_modified_gmt = '0000-00-00 00:00:00' AND {$wpdb->posts}.post_modified >= %s))",
				$scan['cutoff_gmt'],
				$scan['cutoff_gmt'],
				$scan['cutoff_local'],
				$scan['cutoff_local']
			);
		}
	}
	$scan_id = $query->get( 'href_scanner_stale' );
	if ( $scan_id ) {
		$where .= $wpdb->prepare(
			" AND (NOT EXISTS (SELECT marker.post_id FROM {$wpdb->postmeta} marker WHERE marker.post_id = {$wpdb->posts}.ID AND marker.meta_key = '_elt_scan_id') OR EXISTS (SELECT marker.post_id FROM {$wpdb->postmeta} marker WHERE marker.post_id = {$wpdb->posts}.ID AND marker.meta_key = '_elt_scan_id' AND marker.meta_value <> %s))",
			$scan_id
		);
	}
	if ( $query->get( 'href_scanner_orphans' ) ) {
		$where .= " AND NOT EXISTS (SELECT source.ID FROM {$wpdb->posts} source WHERE source.ID = {$wpdb->posts}.post_parent AND source.post_status NOT IN ('trash', 'auto-draft'))";
	}
	return $where;
}

/**
 * Process or resume a bounded scan and stale-record cleanup.
 *
 * @param string $scan_id Scan identifier for resumption.
 * @param string $mode Complete or quick scan mode.
 * @return array|WP_Error
 */
function href_scanner_scan_batch( $scan_id = '', $mode = 'complete' ) {
	global $wpdb;
	if ( ! in_array( $mode, array( 'complete', 'quick' ), true ) ) {
		return new WP_Error( 'href_scanner_mode', __( 'Invalid scan type.', 'href-scanner' ) );
	}
	$state = get_option( 'elt_scan_state' );
	if ( '' !== $scan_id && ( ! $state || $state['scan_id'] !== $scan_id ) ) {
		$last_scan = get_option( 'elt_last_scan' );
		if ( $last_scan && $last_scan['scan_id'] === $scan_id ) {
			return $last_scan;
		}
		return new WP_Error( 'elt_scan_changed', __( 'Another scan has started. Refresh Settings to resume it.', 'href-scanner' ) );
	}
	if ( ! $state ) {
		$content_types = href_scanner_sanitize_content_types( get_option( 'href_scanner_content_types', array( 'post', 'page' ) ) );
		if ( ! $content_types ) {
			return new WP_Error( 'href_scanner_content_types', __( 'Select and save at least one content type in Settings before scanning.', 'href-scanner' ) );
		}
		$last_complete = href_scanner_last_complete_scan();
		if ( 'quick' === $mode && ! $last_complete ) {
			return new WP_Error( 'href_scanner_complete_required', __( 'Finish a complete scan before starting a quick scan.', 'href-scanner' ) );
		}
		$cutoff_gmt   = 'quick' === $mode ? ( $last_complete['completed_at_gmt'] ?? get_gmt_from_date( $last_complete['completed_at'] ) ) : '1970-01-01 00:00:00';
		$cutoff_local = get_date_from_gmt( $cutoff_gmt );
		if ( 'quick' === $mode && ! get_option( 'href_scanner_last_complete_scan' ) ) {
			update_option( 'href_scanner_last_complete_scan', $last_complete, false );
		}
		$source_query = new WP_Query(
			array(
				'post_type'           => $content_types,
				'post_status'         => array_values( array_diff( get_post_stati(), array( 'trash', 'auto-draft' ) ) ),
				'posts_per_page'      => 1,
				'fields'              => 'ids',
				'cache_results'       => false,
				'orderby'             => 'ID',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'href_scanner_scan'   => array(
					'mode'         => $mode,
					'cutoff_gmt'   => $cutoff_gmt,
					'cutoff_local' => $cutoff_local,
				),
			)
		);
		if ( $wpdb->last_error ) {
			return new WP_Error( 'elt_database', __( 'Could not read the posts. Please try again.', 'href-scanner' ) );
		}
		$state = array(
			'scan_id'          => wp_generate_uuid4(),
			'last_id'          => 0,
			'max_id'           => (int) ( $source_query->posts[0] ?? 0 ),
			'total'            => (int) $source_query->found_posts,
			'processed'        => 0,
			'external'         => 0,
			'internal'         => 0,
			'phase'            => 'scan',
			'internal_domains' => get_option( 'href_scanner_internal_domains', array( '', '', '' ) ),
			'content_types'    => $content_types,
			'mode'             => $mode,
			'cutoff_gmt'       => $cutoff_gmt,
			'cutoff_local'     => $cutoff_local,
		);
		update_option( 'elt_scan_state', $state, false );
	}
	$state['mode'] = $state['mode'] ?? 'complete';
	if ( $state['mode'] !== $mode ) {
		return new WP_Error( 'href_scanner_active_scan', __( 'A scan is already in progress. Resume it before starting another scan.', 'href-scanner' ) );
	}
	if ( 'scan' === $state['phase'] ) {
		$state['internal_domains'] = $state['internal_domains'] ?? get_option( 'href_scanner_internal_domains', array( '', '', '' ) );
		$state['content_types']    = $state['content_types'] ?? array( 'post' );
		$source_query              = new WP_Query(
			array(
				'post_type'           => $state['content_types'],
				'post_status'         => array_values( array_diff( get_post_stati(), array( 'trash', 'auto-draft' ) ) ),
				'posts_per_page'      => 5,
				'fields'              => 'ids',
				'cache_results'       => false,
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'href_scanner_scan'   => array(
					'mode'         => $state['mode'],
					'last_id'      => $state['last_id'],
					'max_id'       => $state['max_id'],
					'cutoff_gmt'   => $state['cutoff_gmt'] ?? '1970-01-01 00:00:00',
					'cutoff_local' => $state['cutoff_local'] ?? '1970-01-01 00:00:00',
				),
			)
		);
		$source_ids                = $source_query->posts;
		if ( $wpdb->last_error ) {
			return new WP_Error( 'elt_database', __( 'Could not read the next batch of posts. Resume the scan to retry.', 'href-scanner' ) );
		}
		foreach ( $source_ids as $source_id ) {
			$source_post = get_post( $source_id );
			$counts      = array(
				'external' => 0,
				'internal' => 0,
			);
			if ( $source_post && in_array( $source_post->post_type, $state['content_types'], true ) && ! in_array( $source_post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				$counts = href_scanner_scan_post( $source_post, $state['scan_id'], $state['internal_domains'] );
			}
			if ( is_wp_error( $counts ) ) {
				return $counts;
			}
			$state['last_id'] = (int) $source_id;
			++$state['processed'];
			$state['external'] += $counts['external'];
			$state['internal'] += $counts['internal'];
			update_option( 'elt_scan_state', $state, false );
		}
		if ( count( $source_ids ) < 5 ) {
			$state['phase'] = 'cleanup';
			update_option( 'elt_scan_state', $state, false );
		}
	} else {
		$cleanup_args = array(
			'post_type'      => array( 'elt_external_link', 'elt_internal_link' ),
			'post_status'    => array_values( get_post_stati() ),
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'cache_results'  => false,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( 'quick' === $state['mode'] ) {
			$cleanup_args['href_scanner_orphans'] = true;
		} else {
			$cleanup_args['href_scanner_stale'] = $state['scan_id'];
		}
		$cleanup_query = new WP_Query( $cleanup_args );
		$stale_ids     = $cleanup_query->posts;
		if ( $wpdb->last_error ) {
			return new WP_Error( 'elt_database', __( 'Could not read outdated records. Resume the scan to retry.', 'href-scanner' ) );
		}
		foreach ( $stale_ids as $record_id ) {
			if ( ! wp_delete_post( $record_id, true ) ) {
				return new WP_Error( 'elt_delete', __( 'Could not remove an outdated record. Resume the scan to retry.', 'href-scanner' ) );
			}
		}
		if ( count( $stale_ids ) < 100 ) {
			$state['phase']            = 'done';
			$state['completed_at']     = current_time( 'mysql' );
			$state['completed_at_gmt'] = current_time( 'mysql', true );
			update_option( 'elt_last_scan', $state, false );
			if ( 'complete' === $state['mode'] ) {
				update_option( 'href_scanner_last_complete_scan', $state, false );
			}
			delete_option( 'elt_scan_state' );
		}
	}
	return $state;
}

/**
 * Authorize an AJAX scan and release its lock after processing.
 *
 * @return void
 */
function href_scanner_ajax_scan() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to scan posts.', 'href-scanner' ) ), 403 );
	}
	check_ajax_referer( 'href_scanner_scan', 'nonce' );
	if ( ! class_exists( 'DOMDocument' ) ) {
		wp_send_json_error( array( 'message' => __( 'The PHP DOM extension is required to scan links.', 'href-scanner' ) ), 500 );
	}
	// ponytail: one request lock; use a worker queue if scans outgrow admin requests.
	$lock_time = (int) get_option( 'elt_scan_lock', 0 );
	if ( $lock_time && $lock_time < time() - 300 ) {
		delete_option( 'elt_scan_lock' );
	}
	if ( ! add_option( 'elt_scan_lock', time(), '', false ) ) {
		wp_send_json_error( array( 'message' => __( 'A scan request is already running. Wait for it to finish, then resume.', 'href-scanner' ) ), 409 );
	}
	try {
		$scan_id = isset( $_POST['scan_id'] ) ? sanitize_text_field( wp_unslash( $_POST['scan_id'] ) ) : '';
		$mode    = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'complete';
		$result  = href_scanner_scan_batch( $scan_id, $mode );
	} finally {
		delete_option( 'elt_scan_lock' );
	}
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
	}
	wp_send_json_success( $result );
}

/**
 * Join one representative post per group without caching aggregate post objects.
 *
 * @param array    $clauses Native query SQL clauses.
 * @param WP_Query $query Current report query.
 * @return array
 */
function href_scanner_report_clauses( $clauses, $query ) {
	if ( ! $query->get( 'href_scanner_report' ) ) {
		return $clauses;
	}
	global $wpdb;
	$clauses['join']   .= $wpdb->prepare(
		" INNER JOIN (SELECT MIN(grouped.ID) AS record_id, m.meta_value AS target, COUNT(*) AS link_count FROM {$wpdb->posts} grouped INNER JOIN {$wpdb->postmeta} m ON m.post_id = grouped.ID AND m.meta_key = '_elt_group' WHERE grouped.post_type = %s AND grouped.post_status = 'publish' GROUP BY m.meta_value) href_scanner_groups ON href_scanner_groups.record_id = {$wpdb->posts}.ID",
		$query->get( 'post_type' )
	);
	$clauses['fields']  = "{$wpdb->posts}.*, href_scanner_groups.target, href_scanner_groups.link_count";
	$clauses['orderby'] = 'href_scanner_groups.link_count DESC, href_scanner_groups.target ASC';
	return $clauses;
}

/**
 * Query a bounded report page through the native WordPress query API.
 *
 * @param string $post_type Occurrence type.
 * @param int    $page_number Requested page.
 * @return WP_Query
 */
function href_scanner_report_query( $post_type, $page_number = 1 ) {
	return new WP_Query(
		array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => 50,
			'paged'                  => $page_number,
			'cache_results'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'href_scanner_report'    => true,
		)
	);
}

/**
 * Render an authorized grouped link report and pagination.
 *
 * @return void
 */
function href_scanner_report_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to view this report.', 'href-scanner' ), '', array( 'response' => 403 ) );
	}
	global $wpdb;
	$screen       = get_current_screen();
	$internal     = $screen && 'elt-internal-report' === substr( $screen->id, -19 );
	$post_type    = $internal ? 'elt_internal_link' : 'elt_external_link';
	$heading      = $internal ? __( 'Internal Link Report', 'href-scanner' ) : __( 'External Link Report', 'href-scanner' );
	$values       = href_scanner_filter_values();
	$page_number  = max( 1, absint( $values['paged'] ?? 1 ) );
	$report_query = href_scanner_report_query( $post_type );
	$group_count  = (int) $report_query->found_posts;
	$report_error = '' !== $wpdb->last_error;
	$page_number  = min( $page_number, max( 1, (int) ceil( $group_count / 50 ) ) );
	if ( $page_number > 1 && ! $report_error ) {
		$report_query = href_scanner_report_query( $post_type, $page_number );
		$report_error = '' !== $wpdb->last_error;
	}
	$rows = $report_query->posts;
	?>
	<div class="wrap">
		<h1><?php echo esc_html( $heading ); ?></h1>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=href-scanner-settings' ) ); ?>"><?php esc_html_e( 'Manage internal domains and scan content in Settings', 'href-scanner' ); ?></a></p>
		<p><?php esc_html_e( 'Counts include every link occurrence, including repeated links in the same post. Results may be incomplete while a scan is in progress.', 'href-scanner' ); ?></p>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $post_type ) ); ?>"><?php esc_html_e( 'View individual link records', 'href-scanner' ); ?></a></p>
		<?php
		if ( $report_error ) {
			?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Could not load the link report. Check that the WordPress posts and postmeta tables are available.', 'href-scanner' ); ?></p></div><?php } ?>
		<table class="widefat fixed striped">
			<thead><tr><th scope="col"><?php echo $internal ? esc_html__( 'Target Page', 'href-scanner' ) : esc_html__( 'Domain', 'href-scanner' ); ?></th><th scope="col"><?php esc_html_e( 'Total Links', 'href-scanner' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $rows as $row ) { ?>
					<tr>
						<td>
						<?php
						if ( $internal ) {
							?>
							<a href="<?php echo esc_url( $row->target ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row->target ); ?></a>
							<?php
						} else {
							echo esc_html( $row->target ); }
						?>
						</td>
						<td><a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'post_type'   => $post_type,
									'post_status' => 'publish',
									'elt_group'   => rawurlencode( $row->target ),
									'href_scanner_filter_nonce' => wp_create_nonce( 'href_scanner_filter' ),
								),
								admin_url( 'edit.php' )
							)
						);
						?>
										"><?php echo esc_html( number_format_i18n( $row->link_count ) ); ?></a></td>
					</tr>
				<?php } ?>
				<?php
				if ( ! $rows ) {
					?>
					<tr><td colspan="2"><?php esc_html_e( 'No links found. Start a scan from Settings.', 'href-scanner' ); ?></td></tr><?php } ?>
			</tbody>
		</table>
		<?php
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		$report_table = new class() extends WP_List_Table {
			/**
			 * Page number.
			 *
			 * @var int
			 */
			private $page_number = 1;
			/**
			 * Return the clamped report page number.
			 *
			 * @return int
			 */
			public function get_pagenum() {
				return $this->page_number; }
			/**
			 * Render native WordPress pagination for grouped reports.
			 *
			 * @param int $group_count Total report groups.
			 * @param int $page_number Clamped report page.
			 * @return void
			 */
			public function render_pagination( $group_count, $page_number ) {
				$this->page_number = $page_number;
				$this->set_pagination_args(
					array(
						'total_items' => $group_count,
						'per_page'    => 50,
					)
				);
				$add_nonce = function ( $url ) {
					return add_query_arg( array( 'href_scanner_filter_nonce' => wp_create_nonce( 'href_scanner_filter' ) ), $url );
				};
				add_filter( 'set_url_scheme', $add_nonce );
				try {
					$this->pagination( 'bottom' );
				} finally {
					remove_filter( 'set_url_scheme', $add_nonce );
				}
			}
		};
		echo '<div class="tablenav bottom">';
		$report_table->render_pagination( $group_count, $page_number );
		echo '<br class="clear"></div>';
	?>
	</div>
	<?php
}
