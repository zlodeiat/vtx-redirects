=== VTX Redirects ===
Contributors: vortex
Tags: redirects, redirect manager, 301 redirects, seo, migration
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight redirect manager with bulk editing, usage scanning, activity logs, and safe internal or external redirects.

== Description ==

VTX Redirects provides a focused interface for managing exact-path redirects from the WordPress administration area.

Features:

* 301, 302, 307, and 308 redirects.
* Internal and administrator-configured external destinations.
* Exact-path matching with normalized trailing slashes.
* Duplicate-source and redirect-loop protection.
* Optional query-string preservation.
* Bulk editor for migrations.
* Content usage scanner for finding references to old URLs.
* Administrative activity log.
* Responsive and keyboard-accessible administration interface.
* No third-party service, tracking, telemetry, or external dependency.

VTX Redirects stores its settings in normal WordPress options and uses WordPress redirect, nonce, capability, URL validation, and database APIs.

== Installation ==

1. Upload the `vtx-redirects` folder to `/wp-content/plugins/` or install the plugin ZIP from Plugins > Add New > Upload Plugin.
2. Activate VTX Redirects.
3. Open Tools > VTX Redirects.
4. Add a source path such as `/old-page` and a destination such as `/new-page`.

== Frequently Asked Questions ==

= Does it support external redirects? =

Yes. Administrators can configure HTTP or HTTPS destinations. Only hosts present in enabled redirect rules are added to WordPress' safe redirect host list.

= Does it support regex or wildcard redirects? =

No. Version 2.0 intentionally uses exact normalized path matching to keep behavior predictable and safe.

= What happens to query strings? =

By default, an incoming query string is preserved when the configured destination does not already contain one. This can be disabled in the plugin settings.

= Does uninstalling the plugin delete my redirects? =

No. Redirect data is intentionally preserved so an accidental uninstall does not destroy migration data. Delete the plugin options manually if permanent removal is required.

== Screenshots ==

1. Redirect manager overview and statistics.
2. Add and edit redirect rules.
3. Bulk editor and activity log.

== Changelog ==

= 2.0.1 =
* Finalized WordPress coding standards compliance and normalized cross-platform line endings.
* Improved CI by separating PHP syntax checks from coding-standards checks.

= 2.0.0 =
* Rebuilt the plugin around a modular repository, runtime, admin, logger, and scanner architecture.
* Removed project-specific seed redirects and branding data.
* Added duplicate-source validation and internal redirect-loop detection.
* Added safe support for administrator-configured external destinations.
* Added query-string preservation setting.
* Improved security, sanitization, escaping, accessibility, and internationalization.
* Improved bulk editing and user-specific admin notices.
* Preserved compatibility with existing `vtx_redirects_rules` option data.
