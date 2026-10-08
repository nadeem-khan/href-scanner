/**
 * Handle scanner controls, progress and resumable AJAX sessions.
 *
 * @package HrefScanner
 */

jQuery(
	function ($) {
		const { __, sprintf } = wp.i18n;
		const scan_buttons    = $( '#href-scanner-scan, #href-scanner-quick-scan' );
		const progress        = $( '#href-scanner-progress' );
		const progress_bar    = $( '#href-scanner-progress-bar' );
		const estimate        = $( '#href-scanner-estimate' );
		let scan_id           = '';
		let scan_mode         = 'complete';
		let active_button;
		let started_at         = 0;
		let starting_processed = 0;

		scan_buttons.each(
			function () {
				const scan_button = $( this );
				scan_button.data( 'was_disabled', scan_button.prop( 'disabled' ) );
			}
		);

		function scan_batch() {
			$.post(
				ajaxurl,
				{
					action: 'href_scanner_scan',
					nonce: active_button.attr( 'data-nonce' ),
					scan_id: scan_id,
					mode: scan_mode
				}
			).done(
				function (response) {
					if ( ! response.success) {
						scan_failed( response.data && response.data.message );
						return;
					}
					const state             = response.data;
					scan_id                 = state.scan_id;
					const remaining         = Math.max( 0, state.total - state.processed );
					const session_processed = state.processed - starting_processed;
					const percentage        = state.phase === 'done' ? 100 : remaining === 0 ? 99 : Math.min( 99, Math.floor( state.processed / state.total * 100 ) );
					progress_bar.val( percentage ).attr( 'data-processed', state.processed );
					if (state.phase === 'done') {
						estimate.text( __( 'Scan complete.', 'href-scanner' ) );
					} else if (state.phase === 'cleanup' || remaining === 0) {
						estimate.text( __( 'Finalizing scan and removing outdated records…', 'href-scanner' ) );
					} else if (session_processed > 0) {
						const seconds_remaining = Math.max( 1, Math.ceil( (Date.now() - started_at) / 1000 / session_processed * remaining ) );
						// translators: 1: Estimated remaining minutes, 2: Estimated remaining seconds.
						estimate.text( sprintf( __( 'Estimated time remaining: about %1$d min %2$d sec, plus cleanup.', 'href-scanner' ), Math.floor( seconds_remaining / 60 ), seconds_remaining % 60 ) );
					}
					// translators: 1: Processed items, 2: Total items, 3: External links, 4: Internal links.
					progress.text( sprintf( __( 'Scanned %1$d of %2$d items. Found %3$d external links and %4$d internal links.', 'href-scanner' ), state.processed, state.total, state.external, state.internal ) );
					if (state.phase === 'done') {
						window.location.reload();
					} else {
						if (state.phase === 'cleanup') {
							progress.text( progress.text() + ' ' + __( 'Removing outdated records…', 'href-scanner' ) );
						}
						scan_batch();
					}
				}
			).fail(
				function (request) {
					const response = request.responseJSON;
					scan_failed( response && response.data && response.data.message );
				}
			);
		}

		function scan_failed(message) {
			progress.text( message || __( 'The scan stopped. Resume the active scan to retry. If another scan is running, wait for it to finish.', 'href-scanner' ) );
			estimate.text( __( 'Scan paused. Resume to continue.', 'href-scanner' ) );
			scan_buttons.each(
				function () {
					const scan_button = $( this );
					scan_button.prop( 'disabled', scan_id ? scan_button[0] !== active_button[0] : scan_button.data( 'was_disabled' ) );
				}
			);
			active_button.text( scan_mode === 'quick' ? __( 'Resume Quick Scan', 'href-scanner' ) : __( 'Resume Complete Scan', 'href-scanner' ) );
		}

		scan_buttons.on(
			'click',
			function () {
				active_button      = $( this );
				scan_mode          = active_button.attr( 'data-mode' );
				started_at         = Date.now();
				starting_processed = Number( progress_bar.attr( 'data-processed' ) ) || 0;
				progress_bar.prop( 'hidden', false );
				estimate.text( __( 'Estimating time remaining…', 'href-scanner' ) );
				scan_buttons.prop( 'disabled', true );
				active_button.text( __( 'Scanning…', 'href-scanner' ) );
				progress.text( __( 'Starting scan…', 'href-scanner' ) );
				scan_batch();
			}
		);
	}
);
