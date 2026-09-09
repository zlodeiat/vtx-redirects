# Changelog

## 2.0.0

- Refactored the original plugin into focused runtime, repository, admin, logger, normalizer and scanner classes.
- Removed all production/client seed redirect data.
- Added duplicate source protection and internal redirect loop detection.
- Added safe external redirect host handling via WordPress' allowed redirect hosts filter.
- Added optional query-string preservation.
- Added translation-ready UI strings and accessibility improvements.
- Added GitHub/WordPress.org release metadata and development configuration.
