/**
 * WP Canary — Admin JavaScript
 *
 * @package WP_Canary
 */
/* global wpcData, jQuery */
( function ( $ ) {
	'use strict';

	var pendingRepairIssue = null;

	// =========================================================
	// Run Scan button
	// =========================================================
	$( document ).on( 'click', '#wpc-run-scan', function () {
		var $btn    = $( this );
		var $result = $( '#wpc-scan-result' );

		$btn.prop( 'disabled', true ).text( wpcData.i18n.scanning );
		$result.hide().removeClass( 'is-success is-error is-info' );

		$.post(
			wpcData.ajaxUrl,
			{
				action: 'wpc_run_db_scan',
				nonce:  wpcData.nonce
			},
			function ( response ) {
				$btn.prop( 'disabled', false ).html(
					'<span class="dashicons dashicons-update"></span> ' +
					( $btn.data( 'original-text' ) || 'Rescan Now' )
				);

				if ( response.success ) {
					$result
						.addClass( 'wpc-notice is-success' )
						.html( wpcData.i18n.scan_complete + ' ' + response.data.count + ' issue(s) found.' )
						.show();
					// Reload to reflect new results.
					setTimeout( function () {
						window.location.reload();
					}, 1500 );
				} else {
					$result
						.addClass( 'wpc-notice is-error' )
						.text( response.data.message || wpcData.i18n.error )
						.show();
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false );
			$result.addClass( 'wpc-notice is-error' ).text( wpcData.i18n.error ).show();
		} );
	} );

	// Store original button text.
	$( '#wpc-run-scan' ).each( function () {
		$( this ).data( 'original-text', $( this ).text().trim() );
	} );

	// =========================================================
	// Repair button — open modal
	// =========================================================
	$( document ).on( 'click', '.wpc-repair-btn', function () {
		var issueJson = $( this ).data( 'issue' );
		try {
			pendingRepairIssue = typeof issueJson === 'string' ? JSON.parse( issueJson ) : issueJson;
		} catch ( e ) {
			alert( wpcData.i18n.error );
			return;
		}
		$( '#wpc-repair-result' ).hide().html( '' );
		$( '#wpc-repair-modal' ).show();
	} );

	// Cancel repair modal.
	$( document ).on( 'click', '#wpc-repair-cancel', function () {
		$( '#wpc-repair-modal' ).hide();
		pendingRepairIssue = null;
	} );

	// Close modal on backdrop click.
	$( document ).on( 'click', '#wpc-repair-modal', function ( e ) {
		if ( $( e.target ).is( '#wpc-repair-modal' ) ) {
			$( '#wpc-repair-modal' ).hide();
			pendingRepairIssue = null;
		}
	} );

	// Confirm repair.
	$( document ).on( 'click', '#wpc-repair-confirm', function () {
		if ( ! pendingRepairIssue ) {
			return;
		}

		var $btn    = $( this );
		var $result = $( '#wpc-repair-result' );

		$btn.prop( 'disabled', true ).text( wpcData.i18n.repairing );
		$result.hide().html( '' );

		$.post(
			wpcData.ajaxUrl,
			{
				action: 'wpc_repair_table',
				nonce:  wpcData.nonce,
				issue:  pendingRepairIssue
			},
			function ( response ) {
				$btn.prop( 'disabled', false ).text( 'Yes, Repair' );

				if ( response.success ) {
					$result
						.addClass( 'wpc-notice is-success' )
						.text( response.data.message )
						.show();
					setTimeout( function () {
						$( '#wpc-repair-modal' ).hide();
						window.location.reload();
					}, 1500 );
				} else {
					$result
						.addClass( 'wpc-notice is-error' )
						.text( response.data.message || wpcData.i18n.error )
						.show();
				}

				pendingRepairIssue = null;
			}
		).fail( function () {
			$btn.prop( 'disabled', false );
			$result.addClass( 'wpc-notice is-error' ).text( wpcData.i18n.error ).show();
		} );
	} );

	// =========================================================
	// Dismiss alert
	// =========================================================
	$( document ).on( 'click', '.wpc-dismiss-alert', function () {
		var $btn      = $( this );
		var alertType = $btn.data( 'alert-type' );
		var alertKey  = $btn.data( 'alert-key' );

		$.post(
			wpcData.ajaxUrl,
			{
				action:     'wpc_dismiss_alert',
				nonce:      wpcData.nonce,
				alert_type: alertType,
				alert_key:  alertKey
			},
			function ( response ) {
				if ( response.success ) {
					$btn.closest( 'tr, .wpc-flood-alert' ).fadeOut( 300 );
				}
			}
		);
	} );

	// =========================================================
	// Export log CSV
	// =========================================================
	$( document ).on( 'click', '.wpc-export-log', function () {
		var $btn    = $( this );
		var logType = $btn.data( 'log-type' );

		$btn.prop( 'disabled', true ).text( wpcData.i18n.scanning );

		$.post(
			wpcData.ajaxUrl,
			{
				action:   'wpc_export_log',
				nonce:    wpcData.nonce,
				log_type: logType
			},
			function ( response ) {
				$btn.prop( 'disabled', false ).text( $btn.data( 'label' ) || 'Export as CSV' );

				if ( response.success ) {
					var blob     = new Blob( [ response.data.csv ], { type: 'text/csv;charset=utf-8;' } );
					var url      = URL.createObjectURL( blob );
					var a        = document.createElement( 'a' );
					a.href       = url;
					a.download   = response.data.filename;
					a.style.display = 'none';
					document.body.appendChild( a );
					a.click();
					document.body.removeChild( a );
					URL.revokeObjectURL( url );
				} else {
					alert( ( response.data && response.data.message ) || wpcData.i18n.error );
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false );
			alert( wpcData.i18n.error );
		} );
	} );

	// Store button labels.
	$( '.wpc-export-log' ).each( function () {
		$( this ).data( 'label', $( this ).text().trim() );
	} );

	// =========================================================
	// Send Test Digest
	// =========================================================
	$( document ).on( 'click', '#wpc-send-test-digest', function () {
		var $btn    = $( this );
		var $result = $( '#wpc-digest-result' );

		$btn.prop( 'disabled', true ).text( wpcData.i18n.sending );
		$result.hide().removeClass( 'is-success is-error' );

		$.post(
			wpcData.ajaxUrl,
			{
				action: 'wpc_send_test_digest',
				nonce:  wpcData.nonce
			},
			function ( response ) {
				$btn.prop( 'disabled', false ).text( 'Send Test Digest' );

				if ( response.success ) {
					$result
						.addClass( 'wpc-notice is-success' )
						.text( response.data.message )
						.show();
				} else {
					$result
						.addClass( 'wpc-notice is-error' )
						.text( ( response.data && response.data.message ) || wpcData.i18n.error )
						.show();
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false );
			$result.addClass( 'wpc-notice is-error' ).text( wpcData.i18n.error ).show();
		} );
	} );

} )( jQuery );
