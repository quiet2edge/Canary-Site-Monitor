=== WP Canary ===
Contributors: wpsiteguardian
Tags: database, security, monitoring, diagnostics, maintenance
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Proactive WordPress diagnostic plugin that detects, explains, and fixes site-breaking issues before they cause downtime.

== Description ==

**WP Canary** is a proactive site health tool that continuously monitors your WordPress installation for database issues, PHP errors, plugin changes, and suspicious traffic — then presents the findings in clear, jargon-free language any site owner can act on.

= Free Features =

* **Database Scanner** — Compares your live table structure against expected schemas for WooCommerce, Yoast SEO, Redirection, and Flamingo (CF7). Detects missing tables, missing columns, type mismatches, and orphaned/MyISAM tables.
* **Admin Dashboard** — Health score (0–100), quick-glance metric cards, and a recent-alerts list.
* **Site-wide Table Audit** — Lists all database tables with row count, engine, size, and status.

= Pro Features =

* **Automated Database Repairs** — Fix missing columns and convert MyISAM tables to InnoDB with a single click — safely, with confirmation prompts.
* **404 Flood Monitor** — Logs every 404 request, identifies bots (Google, Meta, Bing, generic crawlers), and fires an alert email when a single IP exceeds your flood threshold (default: 50/hr). Flags DB stress correlations automatically.
* **Plugin Watchdog** — Records every plugin activation and deactivation with timestamp, user, and IP. Detects mass-deactivation events (≥3 plugins at once) and sends an immediate alert.
* **Ad Interference Monitor** — Analyzes ad-bot traffic patterns overnight, detects AdsBot-Google hitting 404 pages, and checks for ad-script plugin conflicts.
* **PHP Resource Monitor** — Reads your PHP limits, current and peak memory usage, and checks debug.log for exhaustion events. Flags memory and timeout issues with actionable recommendations.
* **Debug Log Analyzer** — Reads the last 5,000 lines of debug.log, filters harmless deprecation notices, groups duplicate errors by count, attributes errors to responsible plugins, and surfaces critical patterns.
* **Cron Health Checker** — Audits all scheduled cron events, detects late and missed runs, tracks your custom cron timestamps, and recommends server-side cron when WP-Cron is unreliable.
* **Daily Email Digest** — A beautifully formatted HTML email delivered each morning with a plain-English summary for site owners and a technical detail section for developers.
* **CSV Log Export** — Download your 404 log, plugin change history, or database issue log as a CSV file any time.

= Why WP Canary? =

Most monitoring plugins either flood you with incomprehensible error logs or only run checks when you manually ask them to. WP Canary runs quietly in the background, surfaces only meaningful issues, explains them in plain English, and tells you exactly what to do about them.

== Installation ==

1. Upload the `wp-canary` folder to the `/wp-content/plugins/` directory, or install directly via **Plugins → Add New**.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Site Guardian** in the WordPress admin sidebar.
4. Click **Run Full Scan Now** on the Dashboard to get an immediate health report.

= Multisite =

WP Canary is network-aware. When network-activated it creates tables for each site in the network and adds the menu to both the network admin and individual site admin panels.

== Frequently Asked Questions ==

= Will the scanner slow down my site? =

No. The database scanner runs on a cron schedule (daily by default) or when you manually trigger it. It does not run on front-end page loads.

= Is it safe to use the repair feature? =

The repair feature only performs `ALTER TABLE ADD COLUMN` and engine-conversion operations based on well-known plugin schemas. It never deletes data. That said, always take a database backup before running any repairs.

= What happens to my data if I uninstall the plugin? =

By default, all custom tables and options are removed on uninstall. If you want to preserve your data, check "Keep Data on Uninstall" in **Site Guardian → Settings** before deleting the plugin.

= Does this work with WP-CLI? =

Not yet. WP-CLI support is planned for a future release.

= Does WP Canary replace a full security plugin? =

No. It focuses on diagnostic issues — database integrity, PHP health, cron reliability, and traffic patterns — rather than malware scanning or firewall rules.

== Changelog ==

= 1.0.0 =
* Initial release.
* Free: DB scanner for WooCommerce, Yoast SEO, Redirection, and Flamingo.
* Free: Dashboard with health score and metric cards.
* Pro: Database repair, 404 flood monitor, plugin watchdog.
* Pro: Ad interference monitor, PHP resource monitor, debug log analyzer.
* Pro: Cron health checker, daily email digest, CSV export.
* Multisite support.
