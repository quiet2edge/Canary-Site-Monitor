<?php
/**
 * Plugin Health admin view.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_pro     = wpc_is_pro();
$raw        = $is_pro ? get_option( 'wpc_plugin_health_results', array() ) : array();
$scanned_at = isset( $raw['scanned_at'] ) ? $raw['scanned_at'] : '';
$gaps       = isset( $raw['update_gaps'] ) ? (array) $raw['update_gaps'] : array();
$licenses   = isset( $raw['license_health'] ) ? (array) $raw['license_health'] : array();
$risks      = isset( $raw['risk_assessments'] ) ? (array) $raw['risk_assessments'] : array();
?>
<div class="wrap wpc-wrap">
	<h1><?php esc_html_e( 'Plugin Health', 'wp-canary' ); ?></h1>

	<?php if ( ! $is_pro ) : ?>
	<div class="wpc-upgrade-notice notice notice-info">
		<p>
			<?php esc_html_e( 'Plugin Health is a Pro feature.', 'wp-canary' ); ?>
			<a href="https://wpsiteguardian.com/#pricing" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to WP Canary Pro →', 'wp-canary' ); ?></a>
		</p>
	</div>
	<?php else : ?>

	<div class="wpc-card">
		<div class="wpc-card-header" style="display:flex;align-items:center;gap:16px;">
			<button id="wpc-run-plugin-health-scan" class="button button-primary">
				<?php esc_html_e( 'Run Scan', 'wp-canary' ); ?>
			</button>
			<span id="wpc-plugin-health-status" style="color:#888;font-size:13px;"></span>
			<?php if ( $scanned_at ) : ?>
			<span style="color:#888;font-size:13px;">
				<?php
				printf(
					/* translators: scan timestamp */
					esc_html__( 'Last scan: %s', 'wp-canary' ),
					esc_html( $scanned_at )
				);
				?>
			</span>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $risks ) ) : ?>
	<div class="wpc-card" style="border-left:4px solid #cb2431;">
		<h2 style="margin-top:0;font-size:15px;color:#cb2431;"><?php esc_html_e( 'Risk Assessments', 'wp-canary' ); ?></h2>
		<?php foreach ( $risks as $risk ) : ?>
		<div class="notice notice-error inline" style="margin:0 0 10px;padding:10px 14px;">
			<p style="margin:0;"><?php echo esc_html( $risk['message'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<div class="wpc-card">
		<h2 style="margin-top:0;font-size:15px;"><?php esc_html_e( 'Update Gaps', 'wp-canary' ); ?></h2>
		<?php if ( empty( $gaps ) ) : ?>
		<p style="color:#888;"><?php esc_html_e( 'No update gap issues detected, or no scan has been run yet.', 'wp-canary' ); ?></p>
		<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Plugin Name', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Last Updated', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Days Since Update', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Tested Up To', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'WP Version Gap', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-canary' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $gaps as $gap ) :
				$sev        = isset( $gap['severity'] ) ? $gap['severity'] : 'info';
				$badge_bg   = 'critical' === $sev ? '#cb2431' : ( 'warning' === $sev ? '#e36209' : '#22863a' );
				$badge_lbl  = 'critical' === $sev ? __( 'Critical', 'wp-canary' ) : ( 'warning' === $sev ? __( 'Warning', 'wp-canary' ) : __( 'OK', 'wp-canary' ) );
			?>
			<tr>
				<td><strong><?php echo esc_html( $gap['plugin_name'] ?? '' ); ?></strong><br><small style="color:#888;"><?php echo esc_html( $gap['plugin_file'] ?? '' ); ?></small></td>
				<td><?php echo esc_html( $gap['last_updated'] ?? '—' ); ?></td>
				<td><?php echo null !== ( $gap['days_since_update'] ?? null ) ? (int) $gap['days_since_update'] : '—'; ?></td>
				<td><?php echo esc_html( $gap['tested_up_to'] ?? '—' ); ?></td>
				<td><?php echo null !== ( $gap['wp_version_gap'] ?? null ) ? esc_html( number_format( (float) $gap['wp_version_gap'], 1 ) ) : '—'; ?></td>
				<td><span style="display:inline-block;background:<?php echo esc_attr( $badge_bg ); ?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px;"><?php echo esc_html( $badge_lbl ); ?></span></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<div class="wpc-card">
		<h2 style="margin-top:0;font-size:15px;"><?php esc_html_e( 'License Health', 'wp-canary' ); ?></h2>
		<?php if ( empty( $licenses ) ) : ?>
		<p style="color:#888;"><?php esc_html_e( 'No license issues detected, or no scan has been run yet.', 'wp-canary' ); ?></p>
		<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Plugin Name', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'License Status', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Risk Level', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Action', 'wp-canary' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $licenses as $lic ) :
				$missing = 'missing' === ( $lic['license_status'] ?? '' );
			?>
			<tr>
				<td><strong><?php echo esc_html( $lic['plugin_name'] ?? '' ); ?></strong></td>
				<td>
					<?php if ( $missing ) : ?>
					<span style="color:#cb2431;font-weight:bold;"><?php esc_html_e( 'License Missing', 'wp-canary' ); ?></span>
					<?php else : ?>
					<span style="color:#22863a;font-weight:bold;"><?php esc_html_e( 'License Active', 'wp-canary' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( ucfirst( $lic['severity'] ?? 'info' ) ); ?></td>
				<td><?php echo esc_html( $lic['message'] ?? '' ); ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<script>
	jQuery(function($){
		$('#wpc-run-plugin-health-scan').on('click', function(){
			var $btn    = $(this);
			var $status = $('#wpc-plugin-health-status');
			$btn.prop('disabled', true).text(wpcData.i18n.scanning);
			$status.text('');
			$.post(wpcData.ajaxUrl, {
				action: 'wpc_run_plugin_health_scan',
				nonce:  wpcData.nonce
			}, function(response){
				$btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Run Scan', 'wp-canary' ) ); ?>);
				if ( response.success ) {
					$status.text(wpcData.i18n.scan_complete);
					setTimeout(function(){ location.reload(); }, 800);
				} else {
					$status.text(wpcData.i18n.error);
				}
			}).fail(function(){
				$btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Run Scan', 'wp-canary' ) ); ?>);
				$status.text(wpcData.i18n.error);
			});
		});
	});
	</script>

	<?php endif; ?>
</div>
