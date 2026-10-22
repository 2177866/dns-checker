# Development

```bash
composer test
composer pint
composer phpstan
```

Local DDEV environment is configured for PHP `8.3` to match the supported/tested range and avoid PHP `8.5` deprecation noise from dev dependencies.

The package requires PHP `8.2` or later. Composer resolves dependencies against PHP `8.2.0`, CI tests PHP `8.2` and `8.3`, and the local DDEV runtime remains PHP `8.3`. PHP `8.1` is no longer supported.

Pre-commit hook (Pint → PHPStan → PHPUnit):

```bash
git config core.hooksPath .githooks
```
