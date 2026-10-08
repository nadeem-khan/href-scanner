# Security policy

## Private reporting

Report vulnerabilities privately using [Report a vulnerability](https://github.com/nadeem-khan/href-scanner/security/advisories/new) in the [repository Security tab](https://github.com/nadeem-khan/href-scanner/security). GitHub requires sign-in. Do not disclose an exploitable vulnerability in a public issue, pull request or forum. The maintainer must keep private vulnerability reporting enabled and verify the form before the first release.

For a published WordPress.org plugin, the official [reporting plugin security issues guide](https://developer.wordpress.org/plugins/wordpress-org/reporting-plugin-security-issues/) provides a private escalation route to the Plugin Review Team if the maintainer cannot be reached. Do not send credentials, production database records or full private exports.

Include the affected version and WordPress/PHP versions, required privileges, a minimal sanitized proof of concept, impact, reproduction steps and any proposed fix. Describe affected data without disclosing customer information or secrets. Share sensitive reproduction details only through a verified private channel.

## Disclosure and supported versions

Security fixes target the latest published stable version; users should upgrade to receive fixes. Older versions have no promised backports. Reports against release candidates are assessed individually. Response and repair deadlines are not guaranteed. Please coordinate disclosure with the maintainer while a fix is reviewed and tested.

The maintainer should acknowledge reports privately, assess severity, test a focused repair, obtain release approval, and publish an incremented fixed version. Rotate/revoke exposed credentials where relevant. Describe the affected versions and remediation after the fix is available; never rewrite an existing release tag or conceal security regressions.
