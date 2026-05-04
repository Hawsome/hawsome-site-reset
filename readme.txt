=== Site Nuke ===
Contributors: hawesome
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A developer tool to permanently wipe your database and delete all media, themes, and plugins for a clean fresh installation.

== Description ==

**WARNING: THIS PLUGIN IS HIGHLY DESTRUCTIVE. USE WITH EXTREME CAUTION.**

Site Nuke is the ultimate reset tool for developers. It does not just reset your database, it completely wipes your server of accumulated bloat, restoring your site to a true "Day 1" factory state.

When executed, this plugin will:
* Delete all data from standard WordPress database tables.
* Preserve your current Admin user account so you aren't logged out.
* Recursively delete all media in your `wp-content/uploads` folder.
* Permanently delete all other plugins and inactive themes.

== Installation ==

1. Upload the `site-nuke` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to Tools > Site Nuke in your WordPress admin dashboard.

== Changelog ==

= 1.0.0 =
* Initial release.