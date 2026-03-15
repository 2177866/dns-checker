# Laravel Integration

## Installation

```bash
composer require alyakin/dns-checker
```

## Publish Config

```bash
php artisan vendor:publish --tag=dns-checker-config
```

## Facade

```php
use Alyakin\DnsChecker\Facades\DnsChecker;

$records = DnsChecker::getRecords('example.com', 'A');
```

## Dependency Injection

```php
use Alyakin\DnsChecker\Contracts\DnsLookup;

final class CheckDnsJob
{
    public function handle(DnsLookup $dns): void
    {
        $records = $dns->getRecords('example.com', 'MX');
    }
}
```

## Factory / Fluent Client

```php
use Alyakin\DnsChecker\DnsCheckerFactory;

final class DnsProbe
{
    public function __construct(
        private readonly DnsCheckerFactory $dns
    ) {}

    public function check(): array
    {
        return $this->dns
            ->usingServer('1.1.1.1')
            ->withTimeout(3)
            ->query('example.com', 'TXT');
    }
}
```

## Artisan Command

```bash
php artisan dns:check example.com A
```

## Summary

- service provider auto-discovery
- facade access through `DnsChecker`
- dependency injection via `Alyakin\DnsChecker\Contracts\DnsLookup`
- fluent client creation through `DnsCheckerFactory`
- artisan command: `dns:check`
