# Development

```bash
composer test
composer pint
composer phpstan
```

Local DDEV environment is configured for PHP `8.3` to match the supported/tested range and avoid PHP `8.5` deprecation noise from dev dependencies.

Pre-commit hook (Pint → PHPStan → Pest):

```bash
git config core.hooksPath .githooks
```
