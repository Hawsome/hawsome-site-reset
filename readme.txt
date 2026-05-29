=== Hawsome Site Reset ===
Contributors: hawesome
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.5.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A developer tool to permanently wipe your database and delete all media, themes, and plugins for a clean fresh installation.

== Description ==

**WARNING: THIS PLUGIN IS HIGHLY DESTRUCTIVE. USE WITH EXTREME CAUTION.**

Hawsome Site Reset is an advanced reset tool for developers. It completely wipes your server of accumulated bloat, restoring your site to a clean factory state.

When executed, this plugin will:
* Delete all data from standard WordPress database tables.
* Preserve your current Admin user account so you aren't logged out.
* Recursively delete all media in your `wp-content/uploads` folder.
* Permanently delete all other plugins and inactive themes.

== Installation ==

1. Upload the `hawsome-site-reset` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to Tools > Hawsome Reset in your WordPress admin dashboard.

== Frequently Asked Questions ==

= Will I be logged out after the reset? =
No. Your active session token and Administrator account are strictly preserved. You will remain logged in seamlessly.

= What happens to my active theme? =
Your currently active theme is completely shielded from the filesystem wipe and remains 100% active. All other inactive themes and default WordPress themes are permanently deleted from the server.

= Will this delete caching drop-ins? =
Yes. The plugin aggressively scans the `wp-content` root and permanently removes files like `advanced-cache.php` and `objectcache.php` to prevent fatal errors upon reboot.

== Changelog ==
= 1.5.1 =
* UX: Added a dismissible admin notice prompting users to leave a review after 7 days, helping other developers discover the plugin.

= 1.5.0 =
* Major Update: Comprehensive Database Engine Rewrite.
* Architecture: Implemented a dual-pass database scrub to permanently eradicate residual plugin data and delayed background writes during PHP shutdown.
* Architecture: Expanded the chunked filesystem wiper to aggressively scan the entire `wp-content` directory, removing orphaned cache directories and drop-ins (`advanced-cache.php`, etc.) while protecting the active theme.
* Database: Added `AUTO_INCREMENT` normalization so database IDs sequence perfectly, mirroring a pristine WordPress installation.
* Security: Implemented zero-footprint execution; the plugin now instantly deletes its own temporary security transients upon completion.
* Security: Strict WPCS compliance updates, superglobal sanitization, and verified nonce protection.
* UI/UX: Added a dependency-free SVG password visibility toggle to the Final Verification screen.
* UI/UX: Refined admin dashboard copywriting to clearly and accurately reflect a professional factory reset.
* i18n: 100% translation ready with complete text domain initialization.

= 1.0.0 =
* Initial release.

== Screenshots ==

1. The Pre-Reset Analysis dashboard showing the Impact Report.
2. The Final Verification step with the secure password prompt.
3. The Active Mission Control terminal wiping the site in real-time.
4. The success screen confirming the database and filesystem have been completely purged.