/**
 * Duallistbox voor de tab Afbeeldingsmaten. Met de hand gebouwd (B5), geen
 * JS-library: twee <select multiple> plus verplaatsknoppen.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var active   = document.getElementById( 'pdk-sizes-active' );
		var disabled = document.getElementById( 'pdk-sizes-disabled' );

		if ( ! active || ! disabled ) {
			return;
		}

		function moveSelected( from, to ) {
			Array.prototype.slice.call( from.selectedOptions ).forEach( function ( option ) {
				if ( option.disabled ) {
					return; // Vergrendelde optie (thumbnail) blijft staan.
				}
				option.selected = false;
				to.appendChild( option );
			} );
		}

		function moveAll( from, to ) {
			Array.prototype.slice.call( from.options ).forEach( function ( option ) {
				if ( option.disabled ) {
					return;
				}
				to.appendChild( option );
			} );
		}

		var toDisabled    = document.getElementById( 'pdk-sizes-to-disabled' );
		var toActive      = document.getElementById( 'pdk-sizes-to-active' );
		var allToDisabled = document.getElementById( 'pdk-sizes-all-to-disabled' );
		var allToActive   = document.getElementById( 'pdk-sizes-all-to-active' );

		if ( toDisabled ) {
			toDisabled.addEventListener( 'click', function () { moveSelected( active, disabled ); } );
		}
		if ( toActive ) {
			toActive.addEventListener( 'click', function () { moveSelected( disabled, active ); } );
		}
		if ( allToDisabled ) {
			allToDisabled.addEventListener( 'click', function () { moveAll( active, disabled ); } );
		}
		if ( allToActive ) {
			allToActive.addEventListener( 'click', function () { moveAll( disabled, active ); } );
		}

		// Een <select multiple> post alleen de GESELECTEERDE opties. We willen
		// juist alle huidige leden van "Uitgeschakeld" opslaan, ongeacht wat de
		// gebruiker toevallig geselecteerd had — dus alles selecteren vlak
		// voor het versturen.
		var form = active.closest( 'form' );
		if ( form ) {
			form.addEventListener( 'submit', function () {
				Array.prototype.slice.call( disabled.options ).forEach( function ( option ) {
					option.selected = true;
				} );
			} );
		}
	} );
} )();

/**
 * Hergeneratie-batchrunner (FR-006). Zelfde AJAX-stappenpatroon als IMGX'
 * batch-UI, maar binnen dit modulescript — AC-003 staat maar één eigen
 * scripthandle op deze tab toe.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var config = window.pdkImageSizesBatch || {};
		var running = false;
		var cancelled = false;

		var progress     = document.querySelector( '.pdk-sizes-batch-progress' );
		var bar          = document.querySelector( '.pdk-sizes-batch-bar span' );
		var text         = document.querySelector( '.pdk-sizes-batch-text' );
		var errorList    = document.querySelector( '.pdk-sizes-batch-errors' );
		var cancelButton = document.querySelector( '.pdk-sizes-batch-cancel' );
		var runButtons   = document.querySelectorAll( '[data-pdk-sizes-run]' );

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
			text.textContent = ( state.done ? config.i18n.done + ' ' : config.i18n.running + ' ' ) +
				state.processed + ' / ' + state.total +
				' (' + state.generated + ' gegenereerd, ' + state.skipped + ' overgeslagen)';

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

			request( 'pdk_image_sizes_batch_step', {} ).then( function ( state ) {
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

			cancelled = false;
			errorList.innerHTML = '';
			progress.dataset.keep = '';
			setBusy( true );
			text.textContent = config.i18n.running;

			request( 'pdk_image_sizes_batch_start', { mode: mode } ).then( function ( state ) {
				render( state );
				step();
			} ).catch( function ( error ) {
				text.textContent = error.message;
				setBusy( false );
			} );
		}

		runButtons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				start( button.getAttribute( 'data-pdk-sizes-run' ) );
			} );
		} );

		cancelButton.addEventListener( 'click', function () {
			cancelled = true;

			request( 'pdk_image_sizes_batch_cancel', {} ).then( function () {
				text.textContent = config.i18n.cancelled;
				setBusy( false );
			} ).catch( function () {
				setBusy( false );
			} );
		} );

		// AC-013: tab gesloten en heropend -> hervat vanaf de opgeslagen offset.
		// De server geeft de lopende state al mee bij het laden van de pagina,
		// dus er is geen apart status-endpoint nodig.
		if ( config.state && ! config.state.done ) {
			progress.dataset.keep = '1';
			setBusy( true );
			render( config.state );
			step();
		}

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! running ) {
				return undefined;
			}

			event.preventDefault();
			event.returnValue = '';

			return '';
		} );
	} );
} )();
