/**
 * IMGX batch runner.
 *
 * Drives the AJAX endpoints one step at a time and renders progress. No image work
 * happens here; the browser only paces the server.
 */
( function () {
	'use strict';

	var config = window.imgxAdmin || {};
	var running = false;
	var cancelled = false;

	var progress = document.querySelector( '.imgx-progress' );
	var bar = document.querySelector( '.imgx-progress-bar span' );
	var text = document.querySelector( '.imgx-progress-text' );
	var errorList = document.querySelector( '.imgx-progress-errors' );
	var cancelButton = document.querySelector( '.imgx-cancel' );
	var runButtons = document.querySelectorAll( '[data-imgx-run]' );

	if ( ! progress || ! runButtons.length ) {
		return;
	}

	function request( action, body ) {
		var data = new FormData();
		data.append( 'action', action );
		data.append( 'nonce', config.nonce );

		Object.keys( body || {} ).forEach( function ( key ) {
			data.append( key, body[ key ] );
		} );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		} ).then( function ( response ) {
			return response.json().then( function ( payload ) {
				if ( ! response.ok || ! payload || ! payload.success ) {
					var message = payload && payload.data && payload.data.message
						? payload.data.message
						: config.i18n.failed;
					throw new Error( message );
				}

				return payload.data;
			} );
		} );
	}

	function setBusy( busy ) {
		running = busy;
		progress.hidden = ! busy && ! progress.dataset.keep;
		cancelButton.hidden = ! busy;

		runButtons.forEach( function ( button ) {
			button.disabled = busy;
		} );
	}

	function render( state ) {
		bar.style.width = state.percent + '%';

		var parts = [];
		parts.push( state.processed + ' / ' + state.total );

		if ( state.mode === 'delete' ) {
			parts.push( state.deleted + ' deleted' );
		} else {
			parts.push( state.generated + ' generated' );
			parts.push( state.skipped + ' skipped' );
		}

		text.textContent = ( state.done ? config.i18n.done + ' ' : config.i18n.running + ' ' ) + parts.join( ' · ' );

		errorList.innerHTML = '';

		( state.errors || [] ).slice( -10 ).forEach( function ( error ) {
			var item = document.createElement( 'li' );
			item.textContent = error;
			errorList.appendChild( item );
		} );
	}

	function step() {
		if ( cancelled ) {
			return;
		}

		request( 'imgx_batch_step', {} ).then( function ( state ) {
			render( state );

			if ( state.done ) {
				progress.dataset.keep = '1';
				setBusy( false );
				return;
			}

			step();
		} ).catch( function ( error ) {
			text.textContent = error.message;
			setBusy( false );
		} );
	}

	function start( mode ) {
		if ( running ) {
			return;
		}

		if ( mode === 'all' && ! window.confirm( config.i18n.confirmAll ) ) {
			return;
		}

		if ( mode === 'delete' && ! window.confirm( config.i18n.confirmDelete ) ) {
			return;
		}

		cancelled = false;
		errorList.innerHTML = '';
		progress.dataset.keep = '';
		setBusy( true );
		text.textContent = config.i18n.running;

		request( 'imgx_batch_start', { mode: mode } ).then( function ( state ) {
			render( state );
			step();
		} ).catch( function ( error ) {
			text.textContent = error.message;
			setBusy( false );
		} );
	}

	runButtons.forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			start( button.getAttribute( 'data-imgx-run' ) );
		} );
	} );

	cancelButton.addEventListener( 'click', function () {
		cancelled = true;

		request( 'imgx_batch_cancel', {} ).then( function () {
			text.textContent = config.i18n.cancelled;
			setBusy( false );
		} ).catch( function () {
			setBusy( false );
		} );
	} );

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( ! running ) {
			return undefined;
		}

		event.preventDefault();
		event.returnValue = '';

		return '';
	} );
}() );
