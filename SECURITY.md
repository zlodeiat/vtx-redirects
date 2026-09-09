# Security Policy

Please do not disclose suspected vulnerabilities in a public issue before a fix is available. Report security issues privately to the maintainer through the contact method listed on the maintainer's profile/site.

The plugin requires the `manage_options` capability for all redirect changes, protects state-changing requests with WordPress nonces, validates external URLs, uses `wp_safe_redirect()`, and prepares database values used by the usage scanner.
