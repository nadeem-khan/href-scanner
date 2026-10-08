# Release procedure

Maintainers approve source pushes, repository visibility changes, artifact uploads, WordPress.org submissions and SVN releases. No deployment workflow is configured.

## Verify the candidate

1. Review [the release checklist](WORDPRESS_ORG_RELEASE_CHECKLIST.md) and resolve release blockers. Confirm ownership, name rights, the assigned/intended slug, contributor identity and a working private vulnerability-reporting channel.
2. Run the documented regression, syntax, secret, readme and Plugin Check checks. Test the minimum WordPress/PHP combination and current versions on a fresh database. Run `.phpcs.xml.dist` and the additional `--standard=WordPress --ignore-annotations --extensions=php,js` report; both must have zero errors and zero warnings without rule exclusions.
3. Run `python tests/check_release.py`, then `python scripts/build.py`. Inspect every archive member, verify the SHA-256 file and install the resulting ZIP into a disposable site. Validate activation, scans, exports and data retention.
4. Ensure header version, enqueued-asset versions, translation template version, readme stable tag and changelog agree. Update version-specific ZIP paths in CI and release examples when bumping the version. Set `Tested up to` only to a WordPress version actually tested; configuring a CI job does not establish a passing result. Regenerate the POT after changing translatable strings with `wp i18n make-pot . languages/href-scanner.pot --domain=href-scanner --exclude=.audit,dist,tests,scripts`.

Python/zlib versions and source line endings can affect archive bytes across toolchains. Use the reviewed archive and checksum for a release, and do not replace it silently after approval. Store reviewed submission ZIPs and checksum files in the Git-ignored `release/` folder. Update the allowlist when introducing runtime files. Build outputs and local audit installations are ignored by Git.

## GitHub releases

Maintain source in the [GitHub repository](https://github.com/nadeem-khan/href-scanner). Before an approved push, review the exact staged files for credentials, private data and generated artifacts. Inspect remote changes and do not overwrite other work. Verify [private reporting](https://github.com/nadeem-khan/href-scanner/security/advisories/new) is enabled. Protect the default branch with required review, passing WordPress/PHP jobs from `Quality checks`, restrictions on force pushes and deletion, and maintainer-controlled releases. Use GitHub Issues for ordinary reports and private advisories for vulnerabilities. Keep Actions permissions read-only, credentials unavailable to pull requests, and automatic deployments disabled. Dependabot updates Actions only.

Confirm all CI jobs pass for the reviewed release commit. Review [the release draft](FIRST_RELEASE.md) and the exact plugin ZIP before an approved release or artifact upload. Pin updates require review. The WP-CLI download is checksum-locked and will fail if the upstream moving URL changes; verify and update the pin when upgrading tooling.

## Initial WordPress.org submission

Review the current [submission page](https://wordpress.org/plugins/developers/add/), directory guidelines and logged-in upload size limit immediately before uploading. Review every required declaration, particularly name distinctiveness, owner/account details and differentiation from existing plugins; see [the release checklist](WORDPRESS_ORG_RELEASE_CHECKLIST.md). Do not infer that a slug is reserved from a missing directory page; `href-scanner` remains proposed until assigned.

After separate submission approval, verify the intended `chillopedia` contributor account, then sign in to manually upload the reviewed installable plugin ZIP. WordPress.org must approve the submission before providing its SVN repository. Address reviewer feedback locally, rebuild and retest; get approval before uploading a revised package. If the assigned slug changes, update the entry filename, text domain, translations, build-script slug, tests, CI installation paths and package root consistently before the first public release.

## SVN staging after approval

Use the actual assigned repository URL. SVN is the distribution repository; development stays in Git. Code and `readme.txt` belong directly under `trunk`, immutable version snapshots under `tags/VERSION`, and listing artwork under `assets`. No artwork is included or invented in this candidate.

The following commands are examples for a fresh local staging checkout; they have not been run against WordPress.org:

```sh
svn checkout https://plugins.svn.wordpress.org/ASSIGNED-SLUG release-svn
cd release-svn
svn status
svn list tags
```

Stop if the intended version tag already exists. Extract the **reviewed plugin ZIP** elsewhere and copy the contents of its root folder directly into `trunk`, with no extra nested plugin folder. Keep the ZIP's license notices and translations. Do not copy development documentation, ZIPs, tests, tooling or Git metadata. Review obsolete trunk files individually using `svn remove`; never overwrite tags.

```sh
svn add --force trunk
svn status
svn diff
svn copy trunk tags/1.0.0
svn status
svn diff --summarize
```

Before any commit, compare all staged runtime files with the reviewed ZIP, confirm `trunk/href-scanner.php` and `trunk/readme.txt` exist, verify version/stable tag 1.0.0 and check no confidential/development files are included. Obtain explicit SVN publication approval. Only then may the maintainer run:

```sh
svn commit -m "Release 1.0.0"
```

Use the account's securely managed SVN-specific password; do not put credentials in source, command examples, logs or Actions. If [Release Confirmation](https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/) is enabled, review and confirm the pending release through WordPress.org's Release Management dashboard after the tag commit. Verify the committer can receive the confirmation email; an SVN commit alone does not complete that release. After publishing, download the WordPress.org ZIP and compare it to the reviewed runtime files, verify the listing/stable tag and run an installation/update smoke test. Update the changelog's unreleased status, README release status, draft release text and security policy as needed to match the published version. Do not claim SVN parity before this has happened.

## Future updates, rollback and security response

Increment the public plugin version for each approved update. Keep metadata, changelog, translation source and stable tag consistent. Rebuild, test and review the exact archive before staging trunk and copying to a new tag. Never reuse or silently alter an existing tag.

If a release fails, preserve database backups and diagnose before changing data. A corrected forward release with a higher version is the normal updater recovery path; site owners can manually reinstall a previously reviewed archive when appropriate. No automatic schema downgrade or destructive uninstall is implemented. Deactivation preserves link records and settings. Address security reports privately, rotate leaked credentials if any, coordinate disclosure and publish a tested incremented repair after approval.
