# Draft release: href Scanner 1.0.0

Maintainers: review [the release checklist](WORDPRESS_ORG_RELEASE_CHECKLIST.md) and the recorded validation evidence before publishing. Remove this draft heading and maintainer note from the published release description.

First public release of href Scanner, a WordPress admin tool for auditing stored internal and external links.

- Scan selected Posts, Pages and editor-supported custom types.
- Run and resume complete or quick scans with progress and time estimates.
- Review grouped domain/page reports and filtered individual link occurrences.
- Export matching records as CSV, with spreadsheet-formula protection.
- Classify site URLs and up to three configured domain aliases as internal.

Requires WordPress 6.2+, PHP 7.4+ and DOM/libxml. Reports include selected private and unpublished content. No destinations are fetched or data transmitted to external services. Deactivation and plugin deletion retain records and settings.

Install the reviewed plugin ZIP through the WordPress admin. For a GitHub source download, extract it and build the plugin ZIP first. When upgrading a private development build, follow the README's domain-setting upgrade instructions. Read the [usage, privacy and known limitations](../README.md), including complete-scan timing and incomplete CSVs after database errors.

Source and contributions: [nadeem-khan/href-scanner](https://github.com/nadeem-khan/href-scanner). Licensed GPL-2.0-or-later.
