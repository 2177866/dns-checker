# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- standardised the package on NetDNS2 v2 only and removed legacy Net_DNS2 v1 compatibility code paths
- aligned the local DDEV PHP version with the supported/tested range
- simplified CI dependency installation for the fixed NetDNS2 v2 target

### Deprecated
- `retry_count` remains available for backward compatibility, but is ignored by NetDNS2 v2

### Documentation
- refreshed README wording around NetDNS2 v2 / `mikepultz/netdns2`
- added an architecture diagram and compatibility notes
