/**
 * WP Site Guardian — Admin JavaScript
 *
 * @package WP_Site_Guardian
 */
/* global wpsgData, jQuery */
( function ( $ ) {
	'use strict';

	var pendingRepairIssue = null;

	// =========================================================
	// Run Scan button
	// =========================================================
	$( document ).on( 'click', '#wpsg-run-scan', function () {
		var $btn    = $( this );
		var $result = $( '#wpsg-scan-result' );

		$btn.prop( 'disabled', true ).text( wpsgData.i18n.scanning );
		$result.hide().removeClass( 'is-success is-error is-info' );

		$.post(
			wpsgData.ajaxUrl,
			{
				action: 'wpsg_run_db_scan',
				nonce:  wpsgData.nonce
			},
			function ( response ) {
				$btn.prop( 'disabled', false ).html(
					'<span class="dashicons dashicons-update"></span> ' +
					( $btn.data( 'original-text' ) || 'Rescan Now' )
				);

				if ( response.success ) {
					$result
						.addClass( 'wpsg-notice is-success' )
						.html( wpsgData.i18n.scan_complete + ' ' + response.data.count + ' issue(s) found.' )
						.show();
					// Reload to reflect new results.
					setTimeout( function () {
						window.location.reload();
					}, 1500 );
				} else {
					$result
						.addClass( 'wpsg-notice is-error' )
						.text( response.data.message || wpsgData.i18n.error )
						.show();
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false );
			$result.addClass( 'wpsg-notice is-error' ).text( wpsgData.i18n.error ).show();
		} );
	} );

	// Store original button text.
	$( '#wpsg-run-scan' ).each( function () {
		$( this ).data( 'original-text', $( this ).text().trim() );
	} );

	// =========================================================
	// Repair button — open modal
	// =========================================================
	$( document ).on( 'click', '.wpsg-repair-btn', function () {
		var issueJson = $( this ).data( 'issue' );
		try {
			pendingRepairIssue = typeof issueJson === 'string' ? JSON.parse( issueJson ) : issueJson;
		} catch ( e ) {
			alert( wpsgData.i18n.error );
			return;
		}
		$( '#wpsg-repair-result' ).hide().html( '' );
		$( '#wpsg-repair-modal' ).show();
	} );

	// Cancel repair modal.
	$( document ).on( 'click', '#wpsg-repair-cancel', function () {
		$( '#wpsg-repair-modal' ).hide();
		pendingRepairIssue = null;
	} );

	// Close modal on backdrop click.
	$( document ).on( 'click', '#wpsg-repair-modal', function ( e ) {
		if ( $( e.target ).is( '#wpsg-repair-modal' ) ) {
			$( '#wpsg-repair-modal' ).hide();
			pendingRepairIssue = null;
		}
	} );

	// Confirm repair.
	$( document ).on( 'click', '#wpsg-repair-confirm', function () {
		if ( ! pendingRepairIssue ) {
			return;
		}

		var $btn    = $( this );
		var $result = $( '#wpsg-repair-result' );

		$btn.prop( 'disabled', true ).text( wpsgData.i18n.repairing );
		$result.hide().html( '' );

		$.post(
			wpsgData.ajaxUrl,
			{
				action: 'wpsg_repair_table',
				nonce:  wpsgData.nonce,
				issue:  pendingRepairIssue
			},
			function ( response ) {
				$btn.prop( 'disabled', false ).text( 'Yes, Repair' );

				if ( response.success ) {
					$result
						.addClass( 'wpsg-notice is-success' )
						.text( response.data.message )
						.show();
					setTimeout( function () {
						$( '#wpsg-repair-modal' ).hide();
						window.location.reload();
					}, 1500 );
				} else {
					$result
						.addClass( 'wpsg-notice is-error' )
						.text( response.data.message || wpsgData.i18n.error )
						.show();
				}

				pendingRepairIssue = null;
			}
		).fail( function () {
			$btn.prop( 'disabled', false );
			$result.addClass( 'wpsg-notice is-error' ).text( wpsgData.i18n.error ).show();
		} );
	} );

	// =========================================================
	// Dismiss alert
	// =========================================================
	$( document ).on( 'click', '.wpsg-dismiss-alert', function () {
		var $btn      = $( this );
		var alertType = $btn.data( 'alert-type' );
		var alertKey  = $btn.data( 'alert-key' );

		$.post(
			wpsgData.ajaxUrl,
			{
				action:     'wpsg_dismiss_alert',
				nonce:      wpsgData.nonce,
				alert_type: alertType,
				alert_key:  alertKey
			},
			function ( response ) {
				if ( response.success ) {
					$btn.closest( 'tr, .wpsg-flood-alert' ).fadeOut( 300 );
				}
			}
		);
	} );

	// =========================================================
	// Export log CSV
	// =========================================================
	$( document ).on( 'click', '.wpsg-export-log', function () {
		var $btn    = $( this );
		var logType = $btn.data( 'log-type' );

		$btn.prop( 'disabled', true ).text( wpsgData.i18n.scanning );

		$.post(
			wpsgData.ajaxUrl,
			{
				action:   'wpsg_export_log',
				nonce:    wpsgData.nonce,
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
					alert( ( response.data && response.data.message ) || wpsgData.i18n.error );
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false );
			alert( wpsgData.i18n.error );
		} );
	} );

	// Store button labels.
	$( '.wpsg-export-log' ).each( function () {
		$( this ).data( 'label', $( this ).text().trim() );
	} );

	// =========================================================
	// Send Test Digest
	// =========================================================
	$( document ).on( 'click', '#wpsg-send-test-digest', function () {
		var $btn    = $( this );
		var $result = $( '#wpsg-digest-result' );

		$btn.prop( 'disabled', true ).text( wpsgData.i18n.sending );
		$result.hide().removeClass( 'is-success is-error' );

		$.post(
			wpsgData.ajaxUrl,
			{
				action: 'wpsg_send_test_digest',
				nonce:  wpsgData.nonce
			},
			function ( response ) {
				$btn.prop( 'disabled', false ).text( 'Send Test Digest' );

				if ( response.success ) {
					$result
						.addClass( 'wpsg-notice is-success' )
						.text( response.data.message )
						.show();
				} else {
					$result
						.addClass( 'wpsg-notice is-error' )
						.text( ( response.data && response.data.message ) || wpsgData.i18n.error )
						.show();
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false );
			$result.addClass( 'wpsg-notice is-error' ).text( wpsgData.i18n.error ).show();
		} );
	} );

} )( jQuery );
