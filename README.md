# VTX Redirects

VTX Redirects is a small, production-oriented WordPress redirect manager focused on predictable exact-path redirects and maintainable code.

## Highlights

- 301, 302, 307 and 308 redirects
- Exact normalized path matching
- Safe internal and administrator-configured external redirects
- Duplicate source protection
- Redirect loop detection
- Optional incoming query-string preservation
- Bulk migration editor
- Content usage scanner
- Activity log
- Capability checks, nonces, sanitization and escaping
- Internationalization-ready admin UI
- No telemetry or third-party runtime services

## Architecture

```text
Incoming request
      │
      ▼
Normalizer ──► Repository ──► enabled rule map
      │                         │
      └──────────────► Runtime redirect resolver

Admin UI ──► Repository
   │             │
   ├──► Logger   └──► validation / loop detection
   └──► Usage Scanner
```

The public release contains no client-specific redirect data, production endpoints, secrets or proprietary business logic.

## Development

The runtime plugin has no Composer dependency. Composer is used only for development tooling.

```bash
composer install
composer lint
composer phpcs
```

## WordPress.org

The plugin includes a WordPress.org-compatible `readme.txt`, GPL-2.0-or-later licensing metadata, translation-ready strings and human-readable source code.

Before submitting a release, test it against the current stable WordPress version and run the official **Plugin Check** plugin. Passing automated checks helps with review but does not guarantee acceptance; WordPress.org performs a manual review as well.

## License

GPL-2.0-or-later.
