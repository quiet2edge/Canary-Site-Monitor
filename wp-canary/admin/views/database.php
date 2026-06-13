<?php
/**
 * Database view.
 *
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$scan_results = (array) get_option( 'wpc_last_db_scan_results', array() );
$scan_time    = get_option( 'wpc_last_db_scan_time', '' );

// Build a per-table summary.
$table_issues = array();
foreach ( $scan_results as $issue ) {
	$table = $issue['table'] ?? 'unknown';
	if ( ! isset( $table_issues[ $table ] ) ) {
		$table_issues[ $table ] = array( 'issues' => array(), 'worst' => 'info' );
	}
	$table_issues[ $table ]['issues'][] = $issue;
	$sev_order = array( 'critical' => 2, 'warning' => 1, 'info' => 0 );
	if ( ( $sev_order[ $issue['severity'] ] ?? 0 ) > ( $sev_order[ $table_issues[ $table ]['worst'] ] ?? 0 ) ) {
		$table_issues[ $table ]['worst'] = $issue['severity'];
	}
}

// Also list all scanned tables.
$all_tables = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
?>
<div class="wrap wpc-wrap">
	<h1 class="wpc-page-title">
		<span class="dashicons dashicons-database-view"></span>
		<?php esc_html_e( 'Database Health', 'wp-canary' ); ?>
	</h1>

	<div class="wpc-toolbar">
		<button id="wpc-run-scan" class="button button-primary">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Rescan Now', 'wp-canary' ); ?>
		</button>
		<?php if ( $scan_time ) : ?>
		<span class="wpc-last-scan"><?php printf( /* translators: datetime */ esc_html__( 'Last scan: %s', 'wp-canary' ), esc_html( $scan_time ) ); ?></span>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $scan_results ) ) : ?>
	<div class="wpc-panel">
		<h2><?php printf( /* translators: count */ esc_html__( 'Issues Found (%d)', 'wp-canary' ), count( $scan_results ) ); ?></h2>
		<table class="widefat striped wpc-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Severity', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Table', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Issue', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-canary' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $scan_results as $issue ) :
				$sev = $issue['severity'] ?? 'info';
				$repairable = in_array( $issue['type'] ?? '', array( 'missing_column', 'myisam_engine' ), true );
			?>
				<tr>
					<td><span class="wpc-badge wpc-badge-<?php echo esc_attr( $sev ); ?>"><?php echo esc_html( ucfirst( $sev ) ); ?></span></td>
					<td><code><?php echo esc_html( $issue['table'] ?? '' ); ?></code></td>
					<td><?php echo wp_kses_post( $issue['description'] ?? '' ); ?></td>
					<td>
					<?php if ( $repairable ) : ?>
						<?php if ( wpc_is_pro() ) : ?>
						<button class="button button-small wpc-repair-btn"
							data-issue="<?php echo esc_attr( wp_json_encode( array(
								'type'          => $issue['type'] ?? '',
								'table'         => $issue['table'] ?? '',
								'column'        => $issue['column'] ?? '',
								'expected_type' => $issue['expected_type'] ?? '',
							) ) ); ?>">
							<?php esc_html_e( 'Repair', 'wp-canary' ); ?>
						</button>
						<?php else : ?>
						<a href="<?php echo esc_url( WPC_License::get_upgrade_url() ); ?>" class="button button-small" target="_blank" rel="noopener">
							<span class="dashicons dashicons-star-filled" style="margin-top:3px;"></span>
							<?php esc_html_e( 'Pro', 'wp-canary' ); ?>
						</a>
						<?php endif; ?>
					<?php else : ?>
						<span class="wpc-na">&mdash;</span>
					<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php else : ?>
	<div class="wpc-panel">
		<p><?php esc_html_e( 'No database issues found, or scan has not been run yet. Click "Rescan Now" to start.', 'wp-canary' ); ?></p>
	</div>
	<?php endif; ?>

	<!-- All Tables -->
	<?php if ( ! empty( $all_tables ) ) : ?>
	<div class="wpc-panel">
		<h2><?php printf( /* translators: count */ esc_html__( 'All Tables (%d)', 'wp-canary' ), count( $all_tables ) ); ?></h2>
		<table class="widefat striped wpc-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Table', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Engine', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Rows', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Size', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-canary' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $all_tables as $tbl ) :
				$tname       = $tbl['Name'] ?? '';
				$engine      = $tbl['Engine'] ?? 'Unknown';
				$rows_est    = isset( $tbl['Rows'] ) ? number_format( (int) $tbl['Rows'] ) : '—';
				$data_length = isset( $tbl['Data_length'] ) ? (int) $tbl['Data_length'] : 0;
				$idx_length  = isset( $tbl['Index_length'] ) ? (int) $tbl['Index_length'] : 0;
				$size_kb     = round( ( $data_length + $idx_length ) / 1024, 1 );
				$has_issues  = isset( $table_issues[ $tname ] );
				$worst       = $has_issues ? $table_issues[ $tname ]['worst'] : 'ok';
				$badge_class = 'ok' === $worst ? 'wpc-badge-ok' : 'wpc-badge-' . $worst;
				$badge_text  = 'ok' === $worst ? __( 'OK', 'wp-canary' ) : ucfirst( $worst );
			?>
				<tr>
					<td><code><?php echo esc_html( $tname ); ?></code></td>
					<td><?php echo esc_html( $engine ); ?></td>
					<td><?php echo esc_html( $rows_est ); ?></td>
					<td><?php echo esc_html( $size_kb . ' KB' ); ?></td>
					<td><span class="wpc-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Repair Confirmation Modal -->
	<div id="wpc-repair-modal" class="wpc-modal" style="display:none;">
		<div class="wpc-modal-content">
			<h3><?php esc_html_e( 'Confirm Repair', 'wp-canary' ); ?></h3>
			<p><?php esc_html_e( 'Are you sure? This will ALTER your database table. Always ensure you have a recent backup before proceeding.', 'wp-canary' ); ?></p>
			<div class="wpc-modal-actions">
				<button id="wpc-repair-confirm" class="button button-primary"><?php esc_html_e( 'Yes, Repair', 'wp-canary' ); ?></button>
				<button id="wpc-repair-cancel" class="button"><?php esc_html_e( 'Cancel', 'wp-canary' ); ?></button>
			</div>
			<div id="wpc-repair-result" style="margin-top:10px;"></div>
		</div>
	</div>

	<div id="wpc-scan-result" class="wpc-notice" style="display:none;"></div>
</div>
