# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.1.0] - 2026-03-16

### Changed
- migrated the package dependency from `pear/net_dns2` to `mikepultz/netdns2`
- standardised the codebase on NetDNS2 v2 and removed legacy `Net_DNS2_*` compatibility paths
- aligned the local DDEV runtime with the supported/tested PHP range
- simplified CI dependency installation for a fixed NetDNS2 v2 target
- migrated the test suite from Pest to PHPUnit
- updated dev dependencies to a secure PHPUnit release

### Documentation
- reorganized the documentation into focused files under `docs/`
- shortened the README to an entry-point style overview
- added architecture, request flow, error handling, caching, and Laravel integration documentation
- expanded `CHANGELOG.md` with release history

### Testing
- reorganized tests into guide-like scenarios grouped by topic
- added resilient usage examples for validation, caching, resolver selection, and error handling

## [2.0.0] - 2026-01-19

### Added
- resolver fallback support when custom DNS servers return no records
- configurable NXDOMAIN logging
- configurable domain validation
- `throw_exceptions` support with typed DNS exceptions
- Laravel facade support
- DI contract support for DNS lookups
- fluent configuration helpers
- Laravel cache-backed DNS result caching

### Changed
- updated dependency support to work with newer NetDNS2 releases, including v2
- rewrote the README in English

### Development
- added Pest, Pint, PHPStan and a pre-commit workflow for local quality checks
- documented hooks and CI usage
- added coverage collection in CI for tagged releases

## [1.0.0] - 2025-04-03

### Added
- initial package release
- base DNS lookup wrapper for Laravel-oriented usage

[Unreleased]: https://github.com/2177866/dns-checker/compare/v2.1.0...HEAD
[2.1.0]: https://github.com/2177866/dns-checker/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/2177866/dns-checker/compare/1.0.0...v2.0.0
[1.0.0]: https://github.com/2177866/dns-checker/releases/tag/1.0.0
