# Contributing

## Local development

Use a separate WordPress installation and disposable database. Install the plugin from the built ZIP for release verification; edit the development checkout for normal work. Requirements and commands are in [README.md](README.md). Set `HREF_SCANNER_WP_ROOT` and add `define('HREF_SCANNER_TEST_SITE', true);` to the disposable site's `wp-config.php`. Run commands from the repository root; keep database dumps, keys and logs outside the web root. Never run database tests on live, staging or valued local content.

## Style and scope

Implement the smallest correct change. Reuse existing WordPress APIs, styles and helpers. Use snake_case for PHP and JavaScript variables and meaningful plugin-prefixed global symbols. Follow WordPress Coding Standards for PHP and JavaScript. Avoid unrelated reformatting; use formatting tools only for intentional style changes. CSS uses tab indentation and the [WordPress CSS conventions](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/). Use the docblocks required by those standards; keep other comments short and meaningful. CSS additions should reuse existing variables. Do not add dependencies, abstraction layers or speculative features for small changes.

Keep `href_scanner_` functions and the established post types, metadata and options compatible. Do not change stored-data or uninstall behavior without documented review. Validate input at boundaries, check capabilities and nonces separately, prepare SQL values, and escape output where rendered. Do not add suppression rules to conceal audit failures.

## Testing

Run the lightweight PHP parser checks, Node assertions, syntax checks, release checks and secret scanner. Run the scan, local-URL, content-type, quick-scan, security and lifecycle scripts on a marked disposable site. Test the generated ZIP, activation/deactivation and data preservation. Add focused regression assertions for meaningful changes rather than tests that repeat trivial implementation details.

Install WP-CLI separately and Plugin Check in the disposable WordPress site as shown in README.md. Run Plugin Check both statically and with runtime checks; retain errors and warnings. Use PHP_CodeSniffer from that installed Plugin Check package to check the project's PHP/JavaScript files:

```sh
php "$HREF_SCANNER_WP_ROOT/wp-content/plugins/plugin-check/vendor/bin/phpcs" --standard=.phpcs.xml.dist
php "$HREF_SCANNER_WP_ROOT/wp-content/plugins/plugin-check/vendor/bin/phpcs" --standard=WordPress --ignore-annotations --extensions=php,js href-scanner.php admin.js tests
```

These commands use a POSIX shell; in PowerShell, substitute `$env:HREF_SCANNER_WP_ROOT`. Test the minimum WordPress/PHP combination and a current WordPress release on maintained PHP versions. CI runs the single-site regression suite; it does not run multisite, browser, keyboard or mobile checks. Run `tests/check-multisite.php` on a separate marked network with blogs 1 and 2 and network-activated href Scanner. Record manual validation separately and mark unavailable checks **NOT RUN**, with a reason.

The ruleset enables the complete WordPress standard and ignores annotations, with no rule exclusions or custom-property exceptions. The additional `--standard=WordPress --ignore-annotations --extensions=php,js` check must also finish with zero errors and zero warnings. Use native WordPress query and screen APIs, verify request nonces, and keep disposable fixtures within those same rules.

The secret scanner requires a Git checkout and examines working files, available Git history/reflogs, commit messages and Git configuration. It skips `.audit`, `dist`, `release`, dependency directories and Python caches. Inspect the exact release ZIP separately and keep excluded local data private; a pattern scan cannot prove absence of secrets.

## Issues and pull requests

Use [GitHub Issues](https://github.com/nadeem-khan/href-scanner/issues) for ordinary bugs and [pull requests](https://github.com/nadeem-khan/href-scanner/pulls) for proposed changes. Include WordPress/PHP/plugin versions, content-type setup, minimal sanitized sample HTML, steps, expected result and actual result. Do not include real URLs with credentials, customer content, CSV reports, database dumps or configuration files. Report security problems privately using [SECURITY.md](SECURITY.md).

A pull request should explain the user-visible problem, focused changes, compatibility/data impact and checks actually executed. Include no credentials or generated test environments. Maintainers review correctness, security, compatibility and GPL-compatible provenance. There is no guaranteed response time or established support SLA.

## Contribution licensing and maintenance

By submitting a contribution, you confirm that you have the right to contribute it under **GPL-2.0-or-later**. Retain required third-party notices and update `THIRD-PARTY-NOTICES.txt` when necessary. Disclose the source and license of copied code or assets.

Update release metadata and the changelog only for an approved release version. Publishing is a maintainer decision; CI checks code and packaging only. See [RELEASING.md](docs/RELEASING.md) for the approval gates and manual SVN procedure.
