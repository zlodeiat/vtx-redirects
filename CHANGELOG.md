# Changelog

## 2.1.1

- Icon alignment and button baseline polish across the admin workspace.
- Added the youneed.dev brand logo to the application header.
- Added restrained orange VTX/youneed.dev accent styling while preserving the existing functional hierarchy.

- Redesigned the admin experience as a focused full-width redirect workspace.
- Moved new redirect creation and settings into accessible side panels.
- Added status filtering and refined search, table actions, bulk editing, and activity views.
- Improved responsive composition, focus states, and reduced-motion behavior.

## 2.0.2

- Refreshed the WordPress administration UI with a modern VTX Labs design system.
- Improved responsive behavior, accessibility focus states, table hierarchy and reduced-motion handling.
- Updated WordPress.org contributor metadata to `vtxlabs`.

## 2.0.1

- Finalized WordPress coding standards compliance.
- Normalized repository line endings with `.gitattributes`.
- Split CI syntax and coding-standards checks for reliable PHP 7.4–8.4 coverage.

## 2.0.0

- Refactored the original plugin into focused runtime, repository, admin, logger, normalizer and scanner classes.
- Removed all production/client seed redirect data.
- Added duplicate source protection and internal redirect loop detection.
- Added safe external redirect host handling via WordPress' allowed redirect hosts filter.
- Added optional query-string preservation.
- Added translation-ready UI strings and accessibility improvements.
- Added GitHub/WordPress.org release metadata and development configuration.
