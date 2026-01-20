# Laravel DNS Checker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alyakin/dns-checker.svg)](https://packagist.org/packages/alyakin/dns-checker)
[![Total Downloads](https://img.shields.io/packagist/dt/alyakin/dns-checker.svg)](https://packagist.org/packages/alyakin/dns-checker)
[![PHP Version](https://img.shields.io/packagist/php-v/alyakin/dns-checker)](https://packagist.org/packages/alyakin/dns-checker)
[![Pint](https://github.com/2177866/dns-checker/actions/workflows/pint.yml/badge.svg?branch=main)](https://github.com/2177866/dns-checker/actions/workflows/pint.yml)
[![Tests](https://github.com/2177866/dns-checker/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/2177866/dns-checker/actions/workflows/tests.yml)
[![Coverage](https://github.com/2177866/dns-checker/actions/workflows/coverage.yml/badge.svg?branch=main)](https://github.com/2177866/dns-checker/actions/workflows/coverage.yml)
[![License](https://img.shields.io/packagist/l/alyakin/dns-checker.svg)](LICENSE)

A Laravel-friendly DNS lookup wrapper over [`pear/net_dns2`](https://github.com/mikepultz/netdns2) with:
- Custom DNS servers + optional fallback to the system resolver
- Optional typed exceptions (`throw_exceptions`)
- Optional NXDOMAIN logging control
- Optional Laravel Cache-backed caching (Redis/Memcached/Database/etc), avoiding netdns2 file/shmop cache pitfalls
- Facade, fluent API and DI support

## Installation

```bash
composer require alyakin/dns-checker
```

## Publish config (Laravel)

```bash
php artisan vendor:publish --tag=dns-checker-config
```

## Usage

### Plain usage

```php
use Alyakin\DnsChecker\DnsLookupService;

$dns = new DnsLookupService(config('dns-checker'));

$ips = $dns->getRecords('example.com');        // A records
$txt = $dns->getRecords('example.com', 'TXT'); // TXT records
```

If you are not in Laravel, pass the config array directly:

```php
use Alyakin\DnsChecker\DnsLookupService;

$dns = new DnsLookupService([
    'servers' => ['8.8.8.8'],
    'timeout' => 2,
    'retry_count' => 1,
]);
```

### Error handling

By default (`throw_exceptions=false`) errors result in an empty array:

```php
$records = $dns->getRecords('does-not-exist.example', 'A'); // []
```

With `throw_exceptions=true`, you can use `try/catch`:

```php
use Alyakin\DnsChecker\Exceptions\DnsQueryFailedException;
use Alyakin\DnsChecker\Exceptions\DnsRecordNotFoundException;
use Alyakin\DnsChecker\Exceptions\DnsTimeoutException;

try {
    $records = $dns->getRecords('does-not-exist.example', 'A');
} catch (DnsRecordNotFoundException $e) {
    // NXDOMAIN
} catch (DnsTimeoutException $e) {
    // timeout
} catch (DnsQueryFailedException $e) {
    // other DNS errors
}
```

### Facade (Laravel)

```php
use Alyakin\DnsChecker\Facades\DnsChecker;

$ips = DnsChecker::getRecords('example.com', 'A');
```

### Fluent API (Laravel)

```php
use Alyakin\DnsChecker\Facades\DnsChecker;

$result = DnsChecker::usingServer('8.8.8.8')
    ->withTimeout(5)
    ->setRetries(3)
    ->query('example.com', 'TXT');
```

Notes:
- `usingServer()` overrides `servers` for this call; it will not try other configured servers.
- System fallback may still happen if `fallback_to_system=true`.

### Dependency Injection (Laravel)

```php
use Alyakin\DnsChecker\Contracts\DnsLookup;

final class SomeJob
{
    public function handle(DnsLookup $dns): void
    {
        $ips = $dns->getRecords('example.com', 'A');
    }
}
```

### CLI (Laravel)

```bash
php artisan dns:check example.com A
```

## Configuration

File: `config/dns-checker.php`

- `servers` (array<string>): DNS servers (IP/host) to query first.
- `timeout` (int|float): resolver timeout.
- `retry_count` (int): retry count (netdns2 v1 only; netdns2 v2 does not expose `retry_count` as an option).
- `fallback_to_system` (bool, default `true`): when `servers` are set and the result is empty, try the system resolver; if `false`, return empty result without system lookup.
- `log_nxdomain` (bool, default `false`): whether to call `report()` on NXDOMAIN. Other DNS errors are still reported.
- `throw_exceptions` (bool, default `false`): if `true`, throw typed exceptions instead of returning `[]` and calling `report()`.
- `domain_validator` (string|null): `"Class@method"` validator or `null` to disable validation. Default is `Alyakin\\DnsChecker\\DomainValidator::class.'@validate'`.
- `cache` (array):
  - `enabled` (bool): enable Laravel Cache-backed caching for DNS results.
  - `store` (string|null): cache store name from `config/cache.php` (e.g. `redis`, `file`, `database`, `memcached`). `null` uses the default store.
  - `ttl` (int): TTL in seconds.
  - `prefix` (string): cache key prefix.
  - `cache_empty` (bool): cache empty NOERROR/NODATA responses (exceptions are not cached).

### Laravel Cache example

If Redis is your default Laravel cache driver, just enable caching and keep `store=null`:

```php
// config/dns-checker.php
return [
    // ...
    'cache' => [
        'enabled' => true,
        'store' => null,
        'ttl' => 60,
        'prefix' => 'dns-checker',
        'cache_empty' => false,
    ],
];
```

To pin a specific store:

```php
'cache' => [
    'enabled' => true,
    'store' => 'redis', // or 'file' / 'database' / 'memcached'
    'ttl' => 60,
],
```

### Custom domain validator example

```php
// config/dns-checker.php
return [
    // ...
    'domain_validator' => \App\Support\Dns\DomainValidator::class.'@validate',
];
```

```php
namespace App\Support\Dns;

final class DomainValidator
{
    public static function validate(string $domain): bool
    {
        return str_ends_with($domain, '.example')
            && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
```

## Development

```bash
composer test
composer pint
composer phpstan
```

Pre-commit hook (Pint → PHPStan → Pest):

```bash
git config core.hooksPath .githooks
```

## License

MIT
