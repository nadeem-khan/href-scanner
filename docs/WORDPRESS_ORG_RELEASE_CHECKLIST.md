# WordPress.org release checklist

Complete this checklist for each release candidate. Record the candidate version, tested environments, commands and results in the maintainer's review evidence. Mark unavailable checks **NOT RUN** and resolve release blockers before publication. A passing check does not replace directory review or guarantee absence of defects.

## Identity and licensing

- [ ] Confirm ownership and GPL-compatible rights for all code and assets; update `THIRD-PARTY-NOTICES.txt` for added third-party material.
- [ ] Verify the author, contributor account, proposed or assigned slug, name rights and repository links.
- [ ] Keep the plugin header version, enqueued-asset versions, readme stable tag, changelog and translation template consistent; update version-specific CI and release paths.
- [ ] Confirm the declared WordPress/PHP requirements and required extensions match the tested candidate; set `Tested up to` only after testing that version.

## Security and privacy

- [ ] Scan current source, staged files and the exact release archive for credentials and confidential data. Keep local configurations, databases, keys, logs and audit environments private and outside the web root.
- [ ] Review input validation, prepared SQL, output escaping, capability checks and nonces independently.
- [ ] Verify unauthorized roles and invalid nonces cannot scan, export or access reports; confirm generated records remain excluded from public queries and REST.
- [ ] Verify CSV formula protection, complete pagination and clear failure handling when database reads fail.
- [ ] Verify complete/quick scans, resumption, cleanup and database-error retries, including content created or edited during a complete scan.
- [ ] Review private-content reporting, retained records/settings and the absence of a built-in purge or personal-data exporter/eraser. Confirm documentation accurately discloses storage, exports and any external data transmission.
- [ ] Verify the private vulnerability-reporting channel and review `SECURITY.md`.

## Validation

Use fresh disposable WordPress installations and databases. Add `define('HREF_SCANNER_TEST_SITE', true);` to the test site's `wp-config.php` and set `HREF_SCANNER_WP_ROOT` before database regression tests; never run them against valued content.

- [ ] Test the declared minimum WordPress/PHP combination and current supported combinations; record the actual versions and results.
- [ ] Run the documented PHP regression scripts, including security and lifecycle checks; run `tests/check-multisite.php` on a marked disposable network.
- [ ] Validate every PHP file's syntax; run `node --check admin.js` and `node tests/check_admin.js`.
- [ ] Run `python scripts/check_secrets.py --self-test` and `python scripts/check_secrets.py`; investigate candidates without publishing their values.
- [ ] Run Plugin Check statically and with runtime checks enabled; review all errors and warnings.
- [ ] Run `.phpcs.xml.dist` and the additional `--standard=WordPress --ignore-annotations --extensions=php,js` check; resolve errors and warnings without suppressions or rule exclusions.
- [ ] Review readme/header metadata, installation prerequisites and public links.
- [ ] Check settings, scans, reports and exports manually, including keyboard/mobile access and intended plugin/theme combinations.

## Package review

- [ ] Run `python tests/check_release.py` and build the candidate with `python scripts/build.py`.
- [ ] Inspect the exact ZIP members, root folder, CRC integrity, SHA-256 sidecar and agreement with reviewed source.
- [ ] Confirm only runtime files, translations and license notices are included; exclude Git metadata, documentation, tests, tooling, dependencies and local audit artifacts.
- [ ] Install the exact reviewed ZIP into a disposable site and verify activation, scans, exports, deactivation, uninstall retention and reactivation.
- [ ] Keep the approved ZIP unchanged; source line endings and Python/zlib versions can affect archive bytes.

## Publication and follow-up

- [ ] Obtain maintainer approval for source pushes, releases and artifact uploads; confirm all CI jobs pass for the reviewed release commit.
- [ ] Before WordPress.org submission, review the current guidelines, account/name declarations and logged-in upload limit; upload only the reviewed plugin ZIP after approval.
- [ ] Wait for directory acceptance and the assigned SVN URL/slug. Align metadata, text domain, translations, build script, tests, CI paths and package root if the slug changes.
- [ ] Follow [RELEASING.md](RELEASING.md): stage only distributable files in `trunk`, review status/diff and create a new immutable version tag. Stop if the tag already exists.
- [ ] Verify staged SVN files against the reviewed ZIP and obtain maintainer approval before committing.
- [ ] If WordPress.org Release Confirmation is enabled, review and confirm the pending release after the SVN tag commit and verify receipt of the confirmation email.
- [ ] After publication, verify the downloaded package's runtime files, listing metadata and installation/update behavior; update unreleased/draft status in public docs.
- [ ] Monitor private security reports and support; repair releases with reviewed, incremented versions rather than overwriting existing tags.
