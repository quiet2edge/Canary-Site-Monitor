<?php
/**
 * Getting Started page — shown once after plugin activation.
 *
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_pro = wpc_is_pro();
?>
<div class="wrap wpc-getting-started">

	<div class="wpc-gs-hero">
		<span class="dashicons dashicons-shield wpc-gs-icon"></span>
		<h1><?php esc_html_e( 'Welcome to WP Canary', 'wp-canary' ); ?></h1>
		<p class="wpc-gs-tagline">
			<?php esc_html_e( 'Your site\'s early-warning system is now active. Here\'s how to get the most out of it.', 'wp-canary' ); ?>
		</p>
	</div>

	<div class="wpc-gs-cards">

		<div class="wpc-gs-card">
			<span class="dashicons dashicons-database wpc-gs-card-icon"></span>
			<h3><?php esc_html_e( 'Run Your First DB Scan', 'wp-canary' ); ?></h3>
			<p><?php esc_html_e( 'Check your database tables for missing columns, broken schemas, and engine issues across WooCommerce, Yoast, Redirection, and more.', 'wp-canary' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-database' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Scan Database →', 'wp-canary' ); ?>
			</a>
		</div>

		<div class="wpc-gs-card">
			<span class="dashicons dashicons-email-alt wpc-gs-card-icon"></span>
			<h3><?php esc_html_e( 'Set Up Morning Digest', 'wp-canary' ); ?></h3>
			<p><?php esc_html_e( 'Get a plain-English summary of overnight activity delivered to your inbox every morning. Takes 30 seconds to configure.', 'wp-canary' ); ?></p>
			<?php if ( $is_pro ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-reports' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Configure Digest →', 'wp-canary' ); ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-reports' ) ); ?>" class="button button-secondary wpc-pro-badge">
					<?php esc_html_e( 'Pro Feature — Upgrade →', 'wp-canary' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<div class="wpc-gs-card">
			<span class="dashicons dashicons-admin-plugins wpc-gs-card-icon"></span>
			<h3><?php esc_html_e( 'Enable Plugin Watchdog', 'wp-canary' ); ?></h3>
			<p><?php esc_html_e( 'Get alerted the moment any plugin is deactivated outside of normal admin activity — especially mass deactivation events.', 'wp-canary' ); ?></p>
			<?php if ( $is_pro ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-plugin-watchdog' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'View Watchdog →', 'wp-canary' ); ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-plugin-watchdog' ) ); ?>" class="button button-secondary wpc-pro-badge">
					<?php esc_html_e( 'Pro Feature — Upgrade →', 'wp-canary' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<div class="wpc-gs-card">
			<span class="dashicons dashicons-chart-bar wpc-gs-card-icon"></span>
			<h3><?php esc_html_e( 'Configure Alert Thresholds', 'wp-canary' ); ?></h3>
			<p><?php esc_html_e( 'Set your 404 flood threshold, alert email address, data retention period, and scan schedule to match your site\'s traffic.', 'wp-canary' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-settings' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Open Settings →', 'wp-canary' ); ?>
			</a>
		</div>

	</div>

	<?php if ( ! $is_pro ) : ?>
	<div class="wpc-gs-upgrade">
		<h2><?php esc_html_e( 'Unlock the Full Early-Warning System', 'wp-canary' ); ?></h2>
		<p><?php esc_html_e( 'The free version includes the database scanner. Upgrade to Pro to get all 8 monitoring modules.', 'wp-canary' ); ?></p>
		<ul class="wpc-gs-feature-list">
			<li>✓ <?php esc_html_e( 'Plugin Status Watchdog — alerts on any deactivation', 'wp-canary' ); ?></li>
			<li>✓ <?php esc_html_e( '404 Flood Detector — identify bots destroying your DB', 'wp-canary' ); ?></li>
			<li>✓ <?php esc_html_e( 'Ad Network Interference Monitor — Google, Meta, Bing', 'wp-canary' ); ?></li>
			<li>✓ <?php esc_html_e( 'Debug Log Analyzer — filtered, plain-English errors', 'wp-canary' ); ?></li>
			<li>✓ <?php esc_html_e( 'PHP Resource Monitor — catch memory & timeout issues', 'wp-canary' ); ?></li>
			<li>✓ <?php esc_html_e( 'WP-Cron Health Monitor — find missed scheduled tasks', 'wp-canary' ); ?></li>
			<li>✓ <?php esc_html_e( 'Morning Email Digest — overnight activity summary', 'wp-canary' ); ?></li>
			<li>✓ <?php esc_html_e( 'One-click DB repairs with admin confirmation', 'wp-canary' ); ?></li>
		</ul>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-canary-pricing' ) ); ?>" class="button button-primary button-hero">
			<?php esc_html_e( 'Upgrade to Pro — $39 one-time', 'wp-canary' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<p class="wpc-gs-footer">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-canary' ) ); ?>">
			<?php esc_html_e( '← Go to Dashboard', 'wp-canary' ); ?>
		</a>
	</p>

</div>
