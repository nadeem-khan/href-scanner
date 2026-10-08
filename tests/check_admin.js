/**
 * Check scanner controls with isolated browser and AJAX fixtures.
 *
 * @package HrefScanner
 */

const assert = require( 'node:assert/strict' );
const fs     = require( 'node:fs' );
const vm     = require( 'node:vm' );
const script = fs.readFileSync( __dirname + '/../admin.js', 'utf8' );

function check_buttons(mode, states, quick_disabled = false, processed_before = 0) {
	const complete_button  = { mode: 'complete', disabled: false };
	const quick_button     = { mode: 'quick', disabled: quick_disabled };
	const progress         = {};
	const progress_bar     = { 'data-processed': processed_before, hidden: true };
	const estimate         = {};
	const estimate_history = [];
	const requests         = [];
	let reloaded           = false;
	let clock_ms           = 1000;
	function jquery(value) {
		if (typeof value === 'function') {
			value( jquery ); return; }
		const elements = { '#href-scanner-progress': progress, '#href-scanner-progress-bar': progress_bar, '#href-scanner-estimate': estimate };
		const items    = typeof value !== 'string' ? [value] : value.includes( ',' ) ? [complete_button, quick_button] : [elements[value]];
		return {
			0: items[0],
			each( callback ) { items.forEach( item => callback.call( item ) ); return this; },
			data( key, value ) { if (value === undefined) {
					return items[0][key]; } items.forEach( item => item[key] = value ); return this; },
			prop( key, value ) { return this.data( key, value ); },
			attr( key, value ) { return key === 'data-mode' ? items[0].mode : key === 'data-nonce' ? 'test-nonce' : this.data( key, value ); },
			val( value ) { return this.data( 'value', value ); },
			text( value ) { if (items[0] === estimate && value !== undefined) {
					estimate_history.push( value ); } return this.data( 'text', value ); },
			on( event, callback ) { items.forEach( item => item.click = callback ); }
		};
	}
	jquery.post   = (url, request) => {
		requests.push( request );
		clock_ms += 5000;
		return { done( callback ) { callback( states.shift() ); return this; }, fail() {} };
	};
	vm.runInNewContext( script, { jQuery: jquery, ajaxurl: '/wp-admin/admin-ajax.php', Date: { now: () => clock_ms }, wp: { i18n: { __: text => text, sprintf: (text, ...values) => text.replace( /%(\d+)\$d/g, (match, index) => values[index - 1] ) } }, window: { location: { reload() { reloaded = true; } } } } );
	const active_button = mode === 'quick' ? quick_button : complete_button;
	active_button.click.call( active_button );
	assert.equal( requests[0].action, 'href_scanner_scan' );
	assert.equal( requests[0].mode, mode );
	assert.equal( requests[0].nonce, 'test-nonce' );
	return { complete_button, quick_button, requests, reloaded, progress, progress_bar, estimate, estimate_history };
}

const state   = { scan_id: 'quick-id', phase: 'cleanup', processed: 1, total: 1, external: 1, internal: 0 };
const resumed = check_buttons( 'quick', [{ success: true, data: state }, { success: false, data: { message: 'Retry' } }] );
assert.equal( resumed.requests[1].scan_id, 'quick-id' );
assert.equal( resumed.requests[1].mode, 'quick' );
assert.equal( resumed.complete_button.disabled, true );
assert.equal( resumed.quick_button.disabled, false );
assert.equal( resumed.quick_button.text, 'Resume Quick Scan' );
assert.equal( resumed.progress.text, 'Retry' );
assert.equal( resumed.progress_bar.value, 99 );
assert.equal( resumed.progress_bar.hidden, false );
assert.ok( resumed.estimate_history.includes( 'Finalizing scan and removing outdated records…' ) );
assert.equal( resumed.estimate.text, 'Scan paused. Resume to continue.' );
const failed = check_buttons( 'complete', [{ success: false }], true );
assert.equal( failed.complete_button.disabled, false );
assert.equal( failed.quick_button.disabled, true );
const finished = check_buttons( 'complete', [{ success: true, data: { ...state, phase: 'done' } }] );
assert.equal( finished.reloaded, true );
assert.equal( finished.progress_bar.value, 100 );
assert.equal( finished.estimate.text, 'Scan complete.' );
const partial = check_buttons( 'complete', [{ success: true, data: { ...state, phase: 'scan', processed: 5, total: 20 } }, { success: false }] );
assert.equal( partial.progress_bar.value, 25 );
assert.ok( partial.estimate_history.includes( 'Estimated time remaining: about 0 min 15 sec, plus cleanup.' ) );
const continued = check_buttons( 'quick', [{ success: true, data: { ...state, phase: 'scan', processed: 15, total: 20 } }, { success: false }], false, 10 );
assert.equal( continued.progress_bar.value, 75 );
assert.ok( continued.estimate_history.includes( 'Estimated time remaining: about 0 min 5 sec, plus cleanup.' ) );
const empty = check_buttons( 'quick', [{ success: true, data: { ...state, total: 0, processed: 0 } }, { success: false }] );
assert.equal( empty.progress_bar.value, 99 );
assert.ok( empty.estimate_history.includes( 'Finalizing scan and removing outdated records…' ) );
console.log( 'Scan controls, progress, time estimates, resumed sessions, cleanup and empty-scan checks passed.' );
