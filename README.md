# Laravel DNS Checker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alyakin/dns-checker.svg)](https://packagist.org/packages/alyakin/dns-checker)
[![PHP Version](https://img.shields.io/packagist/php-v/alyakin/dns-checker)](https://packagist.org/packages/alyakin/dns-checker)
[![License](https://img.shields.io/packagist/l/alyakin/dns-checker.svg)](LICENSE)

Обёртка над [`pear/net_dns2`](https://github.com/mikepultz/netdns2) для управляемых DNS‑проверок с поддержкой списка кастомных DNS‑серверов и fallback на системный резолвер.

## Установка

```bash
composer require alyakin/dns-checker
```

## Публикация конфига (Laravel)

```bash
php artisan vendor:publish --tag=dns-checker-config
```

## Использование

```php
use Alyakin\DnsChecker\DnsLookupService;

$dns = new DnsLookupService(config('dns-checker'));

$ips = $dns->getRecords('example.com'); // A-записи
$mx  = $dns->getRecords('example.com', 'MX');
```

CLI:

```bash
php artisan dns:check example.com A
```

## Конфигурация

Файл: `config/dns-checker.php`

- `servers` (array<string>): список DNS‑серверов (ip/host) для запроса через Net_DNS2.
- `timeout` (int): таймаут.
- `retry_count` (int): число повторов.
- `fallback_to_system` (bool, default `true`): если `servers` задан и результат пустой — делать fallback на системный резолвер; если `false` — вернуть пустой результат без системного запроса.
- `log_nxdomain` (bool, default `false`): логировать NXDOMAIN через `report()`; при `false` NXDOMAIN не логируется (другие ошибки продолжают логироваться).
- `domain_validator` (string|null, default `Alyakin\\DnsChecker\\DomainValidator::class.'@validate'`): валидатор домена перед запросом DNS. Можно отключить (`null`) или указать `"Class@method"` (статический метод, чтобы работало с `php artisan config:cache`).

Пример кастомного валидатора:

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

## Разработка

Тесты (Pest):

```bash
composer test
```

Форматирование (Pint):

```bash
composer pint
```

Статический анализ (PHPStan level 5):

```bash
composer phpstan
```

Coverage (Pest + Xdebug):

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage --coverage-text
```

Pre-commit hook (Pint → PHPStan → Pest):

```bash
git config core.hooksPath .githooks
```

### Вклад в проект

- Перед отправкой PR убедись, что коммиты проходят pre-commit hook (он запускает Pint по staged-файлам, затем PHPStan и Pest).
- Настройка хука (один раз на репозиторий): `git config core.hooksPath .githooks`
- CI на GitHub тоже прогоняет форматирование/статанализ/тесты на PR и на теги версий, поэтому сломанные коммиты не пройдут.

## Лицензия

MIT
