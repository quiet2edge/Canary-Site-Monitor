=== WP Canary ===
Contributors: snarkles683
Tags: database, monitoring, diagnostics, debug, site-health
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitors your WordPress site for database errors, plugin changes, 404 floods, and PHP errors, and explains what it finds in plain English.

== Description ==

WP Canary checks a WordPress site for common failure conditions and reports what it finds in plain language, so problems are noticed before they affect visitors.

Most things that go wrong on a WordPress site go wrong quietly. A plugin deactivates after an update. Bots generate hundreds of 404 responses a day. Database tables drift out of sync with the plugins that own them. PHP notices pile up in the debug log. None of it announces itself, and by the time it becomes visible it has usually been happening for a while.

WP Canary runs its checks on WP-Cron schedules, not on front-end page loads, and presents the results on a dashboard in the WordPress admin.

= Included in the free version =

* **Database scanner** - compares your live table structure against the expected schema for WooCommerce, Yoast SEO, Rank Math SEO, Redirection, and Flamingo. Detects missing tables, missing columns, column type mismatches, collation mismatches, and orphaned or MyISAM tables.
* **Plugin watchdog** - records plugin activations and deactivations with timestamp, user, and IP address, and raises an alert when three or more plugins are deactivated at once.
* **404 flood monitor** - logs 404 requests, identifies known bots and crawlers, and raises an alert when a single IP address exceeds a threshold you configure (50 per hour by default).
* **Site-wide table audit** - lists every database table with its row count, storage engine, size, and status.
* **Admin dashboard** - a health score, quick-glance metric cards, and a list of recent alerts.
* **CSV export** - download the 404 log, plugin change history, or database issue log.

= Included with a Pro licence =

The following modules are bundled with the plugin but remain inactive unless a Pro licence is activated. No additional code is downloaded from a remote server to enable them.

* **Database repair** - adds missing columns and converts MyISAM tables to InnoDB, with confirmation prompts.
* **Daily email digest** - an HTML summary of all findings, sent each morning to an address you choose. The digest is generated and sent from your own site using the standard WordPress mail function.
* **Debug log analyzer** - parses the debug log, filters harmless deprecation notices, groups duplicate errors, and attributes them to the responsible plugin.
* **Ad interference monitor** - detects ad-bot traffic hitting 404 pages and checks for plugin conflicts affecting ad scripts.
* **PHP resource monitor** - reports PHP limits, current and peak memory use, and memory-exhaustion events found in the debug log.
* **Cron health checker** - audits scheduled cron events and detects late or missed runs.
* **Plugin health** - update gap detection, licence health checks, and risk assessment.

WP Canary is not a security plugin. It does not scan for malware and does not provide a firewall. It reports on database integrity, PHP health, cron reliability, plugin changes, and traffic patterns.

== External services ==

This plugin uses one external service: **Freemius**, which provides licence management, plugin updates, and optional usage tracking.

What is sent: the site URL, WordPress version, PHP version, the list of active plugins and the active theme, and the administrator email address.

When it is sent: on plugin activation, when the plugin checks for an update, and when a licence key is validated.

Opting in to usage tracking is optional and can be skipped during activation. The plugin remains fully functional if you skip it.

Freemius terms of service: https://freemius.com/terms/
Freemius privacy policy: https://freemius.com/privacy/

No other data leaves your site. Alert and digest emails are generated on your own server and sent with the standard WordPress `wp_mail()` function. Their contents are not transmitted to the plugin author.

== Installation ==

1. Upload the `wp-canary` folder to `/wp-content/plugins/`, or install the plugin through **Plugins - Add New**.
2. Activate the plugin through the **Plugins** screen.
3. Go to **WP Canary** in the admin sidebar.
4. Click **Run Full Scan Now** on the dashboard for an immediate report.

= Multisite =

WP Canary is network-aware. When network-activated it creates its tables for each site in the network and adds its menu to both the network admin and the individual site admin screens.

== Frequently Asked Questions ==

= Will the scanner slow down my site? =

No. The database scanner runs on a cron schedule (daily by default) or when you trigger it manually. It does not run on front-end page loads.

= Does WP Canary change anything on my site? =

The free version only reads and reports. The database repair module, which requires a Pro licence, makes changes only when you explicitly confirm them. It performs `ALTER TABLE ADD COLUMN` and storage-engine conversions against known plugin schemas, and never deletes data. Take a database backup before running any repair.

= Does the debug log analyzer need anything enabled? =

Yes. It requires `WP_DEBUG_LOG` to be enabled in `wp-config.php`. The other modules work regardless of your debug settings.

= What happens to my data if I uninstall the plugin? =

By default, all custom tables and options are removed on uninstall. To keep them, enable **Keep Data on Uninstall** in **WP Canary - Settings** before deleting the plugin.

= Does this work with WP-CLI? =

Not yet. WP-CLI support is planned for a future release.

== Screenshots ==

1. The WP Canary dashboard, showing the health score and recent alerts.
2. The database scan results screen.
3. The settings screen.

== Changelog ==

= 1.1.2 =
* Security: database repair now validates the target table and column against the known-schema registry, and takes the column type from the registry rather than from the request.
* Security: collation repair now validates the target collation against the collations the server actually supports.
* Security: on multisite, database repairs now require a super administrator.
* Security: the debug log path is confined to wp-content and must be a .log file.
* Security: CSV exports neutralise leading =, +, - and @ so that logged request data cannot execute as a spreadsheet formula.
* Free trial is now 4 days.

= 1.1.1 =
* The plugin watchdog and the 404 flood monitor are now part of the free version.

= 1.1.0 =
* Free: database collation mismatch detection for plugin-owned tables.
* Free: Rank Math SEO tables added to the known schema registry.
* Pro: collation repair via ALTER TABLE CONVERT TO CHARACTER SET, with validation.
* Pro: call stack tracing in the plugin watchdog (cron, AJAX, WP-CLI, admin user, originating plugin).
* Pro: new plugin health module covering update gaps, licence health, and risk assessment.
* Pro: plugin health admin screen.
* Pro: the daily digest now includes plugin health warnings.

= 1.0.0 =
* Initial release.
* Free: database scanner for WooCommerce, Yoast SEO, Redirection, and Flamingo; dashboard with health score.
* Pro: database repair, 404 flood monitor, plugin watchdog, ad interference monitor, PHP resource monitor, debug log analyzer, cron health checker, daily email digest, CSV export.
* Multisite support.

== Upgrade Notice ==

= 1.1.2 =
Security hardening for database repair, the debug log reader, and CSV export. Recommended for all users.
