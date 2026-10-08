=== href Scanner ===
Contributors: chillopedia
Tags: links, internal links, external links, link report, link audit
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scan internal and external links, monitor your WordPress website's overall link profile, and review link structure for SEO.

== Description ==

href Scanner scans both internal and external links across selected WordPress content types, giving you a clear view of your website's overall link profile.

Link structure matters for SEO. Internal links help connect content within your website, while external links show which resources it references. Review anchor text, follow attributes, source content and link counts to support SEO audits and identify patterns that need attention.

The readable source, development instructions and contribution workflow are available in the [GitHub repository](https://github.com/nadeem-khan/href-scanner). For ordinary bugs, use its issue tracker; report vulnerabilities privately through the repository's Security tab.

Domain and page reports show where links are concentrated. Filter individual link records and export CSV reports to keep track of your site's linking structure as content changes. Manage settings, scans, reports and link listings under href Scanner in the WordPress admin menu.

* Save each HTTP(S) link occurrence as an External Link or Internal Link record.
* Choose Posts, Pages and registered custom content types that support the editor. Posts and Pages are selected by default.
* Run a complete scan or quickly refresh content created or updated since the last complete scan.
* View the saved completion date of the last complete scan.
* Follow scan progress with a progress bar and an estimated time remaining.
* Review each URL, anchor text, follow type, source post title, and source content type.
* Filter either listing by Anchor Text, Follow Type, Source Post, and Content Type.
* Export all matching link records as CSV, across all listing pages.
* View external link counts by domain and internal link counts by target page.
* Configure up to three additional internal domains in Settings.

Relative URLs and links to the domains configured under Settings > General as Site Address (URL) and WordPress Address (URL) are always internal. Additional domains are matched by hostname, ignoring a www prefix. Other subdomains must be entered separately. Full HTTP(S) URLs are accepted in domain fields and reduced to their hostname.

External domains combine www and non-www variants. Internal target counts use the site's home origin, treating configured domains as aliases for the same pages. Schemes, fragments and trailing slash variants are combined; query strings are preserved. Every occurrence counts, including repeated links in one post.

Only users with the manage_options capability can access the plugin. Scans are manually triggered from Settings. Save domain and content-type changes before scanning, then run a complete scan to apply them throughout the link profile. Keep Settings open until the scan finishes. Interrupted scans can be resumed using their original settings.

The progress bar updates after each batch and reaches 100% when cleanup finishes. The time estimate uses the current scan session's processing speed and remaining items, so it can change as content size varies. Cleanup time is additional; the final stage displays a cleanup message. A prominent reminder beside the scan controls states: Keep this page open until it finishes.

= Privacy and data retention =

The plugin reads saved content and stores link URLs, anchor text, follow type, source title and content type, report groups, settings, and scan progress in your WordPress database. It does not fetch link destinations or send this data to external services. It adds no visitor tracking or cookies. CSV exports are downloaded directly to your computer.

Reports include selected private and unpublished content. Link URLs, query parameters, anchor text and source titles may contain personal information or credentials already present in that content. Restrict administrative access and treat downloaded CSV files as confidential. After removing sensitive information from source content, run a complete scan to remove its outdated link records. Plugin deletion preserves this data; database backups and downloaded exports need separate retention decisions.

Complete scans update existing records and remove outdated records, including links from unselected content types. Quick scans refresh only eligible content and preserve other live records. Both modes remove records from deleted or trashed content. Deactivation and deletion of the plugin preserve its records and settings, so reinstalling does not discard your link profile. Source content is never modified.

== Installation ==

1. Upload the href-scanner folder to wp-content/plugins, or install the ZIP through Plugins > Add New Plugin > Upload Plugin.
2. Activate href Scanner.
3. Open href Scanner > Settings, choose content types, enter any additional internal domains, and save changes.
4. Click Start Complete Scan and leave the page open until completion. Use Start Quick Scan for subsequent updates.
5. Open either report or individual link listing under href Scanner.

PHP's DOM extension is required for scanning. WordPress 6.2 or later and PHP 7.4 or later are required. Use maintained WordPress and PHP releases in production. If installing from GitHub, build the plugin ZIP as described in README.md; GitHub's Download ZIP contains the development repository.

== Frequently Asked Questions ==

= Which content is scanned? =

Stored content in the types selected under Settings. Posts and Pages are selected by default; existing saved selections are preserved. Settings lists only Posts, Pages and registered custom types with editor support, excluding WordPress core internal types (such as attachments, revisions, navigation and templates) and the plugin's generated link-record types. Published, private, draft, pending, scheduled and inherited items are included; trash and auto-drafts are excluded. Select at least one source type before starting a scan. Only HTML anchor links with an href attribute in saved post content are scanned. Excerpts, custom fields, shortcode output, dynamically rendered blocks and links generated by theme navigation, widgets or templates are not scanned.

= How does Start Quick Scan work? =

A complete scan saves and displays its completion date after all records have been refreshed and cleanup finishes. Start Quick Scan checks selected content created or updated at or after that date, refreshes its links and removes obsolete occurrences, while preserving unchanged records. A quick scan does not advance the complete-scan cutoff, so repeated quick scans keep checking changes since the same complete scan. Finish a complete scan first to enable quick scanning. Run a new complete scan after changing internal domains or selected content types.

Content created or edited during a complete scan can be missed by later quick scans because the cutoff is the completion time. Avoid editing during a complete scan, or run another complete scan after edits finish.

= What does No Follow mean? =

The link's rel attribute contains the nofollow token, case-insensitively. Other links are labeled Do Follow. Sponsored and ugc alone do not set nofollow.

= How are relative links handled? =

Paths, query-only links and fragment-only links are internal and resolved against the source post permalink. Protocol-relative links are classified by their hostname. Empty href values, malformed URLs and non-HTTP(S) schemes such as mailto and tel are ignored.

= Will scanning change my posts or create duplicate records? =

Source content is unchanged. Repeated scans refresh link records without duplicating occurrences. Reports may be partially updated during an interrupted scan; resume it to complete the refresh and remove stale records.

= Can I search by part of an anchor or source title? =

Yes. Anchor Text and Source Post filters use partial text matches. Follow Type and Content Type use exact selections. Enter any combination and click Filter; clear the controls to show all records. Existing records resolve their content type from the source without requiring a rescan.

= Can I view the links behind a report count? =

Yes. Click a count in either report to open all matching link records for that domain or target page. The target filter stays active when applying other filters or exporting. Use Clear target filter to remove it.

= What does Export All export? =

Each link listing has an Export All button at the top right. It downloads every record matching the applied filters and search, across all pages. Click Filter first to apply changed controls. The CSV contains Link, Anchor Text, Follow Type, Source Post, and Content Type; content types use their registered slugs. Text that could be interpreted as a spreadsheet formula is prefixed with an apostrophe. Exports during a scan may reflect partially updated records. A database read failure can also produce an incomplete CSV without an error message. Export after scanning finishes, compare its record count with the filtered listing, and retry after resolving database errors.

= Can I remove all stored reports and settings? =

Deactivation and plugin deletion retain them. There is no built-in purge control or integration with WordPress's personal-data exporter/eraser. To remove everything, have a site administrator perform reviewed database maintenance after a backup. A complete scan refreshes reports from selected content; it does not delete the plugin's settings or all stored data.

= How does multisite work? =

Settings, scan state and reports belong to each site. Configure and scan each site separately; network activation does not create a network-wide scan or report.

== Changelog ==

= 1.0.0 =
* First public release with complete and quick scans of selected saved content.
* Add grouped internal/external reports, filtered occurrence listings and CSV exports with spreadsheet-formula protection.
* Support resumable scans, progress estimates and up to three additional internal domains.
* Restrict reports, settings, scans and exports to users with manage_options.
* Retain records and settings on deactivation and deletion; leave source content unchanged.
* Include GPL notices, development instructions, automated checks and reproducible plugin ZIP packaging.
* Use consistent href Scanner identifiers; private development upgrades require resaving additional internal domains.


== Upgrade Notice ==

= 1.0.0 =
First public release. Before upgrading a private development build, note additional internal domains. After upgrading, reload Settings, save those domains again and run a complete scan. Existing link records and content-type selections are retained.
