# Laravel DNS Checker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alyakin/dns-checker.svg)](https://packagist.org/packages/alyakin/dns-checker)
[![PHP Version](https://img.shields.io/packagist/php-v/alyakin/dns-checker)](https://packagist.org/packages/alyakin/dns-checker)
[![License](https://img.shields.io/packagist/l/alyakin/dns-checker.svg)](LICENSE)

**Описание:**  
Это обертка над [`mikepultz/netdns2`](https://github.com/mikepultz/netdns2) для быстрой и управляемой проверки DNS-записей с возможностью fallback на системный резолвер и `gethostbyname`.

## Установка

```bash
composer require alyakin/dns-checker
```

## Публикация конфига

```bash
php artisan vendor:publish --tag=dns-checker-config
```

## Пример использования

```php
use Alyakin\DnsChecker\DnsLookupService;

$dns = new DnsLookupService(config('dns-checker'));

$ips = $dns->getRecords('example.com'); // по умолчанию A-запись
```

## Примеры:

### Проверка MX-записей:

```php
$mx = $dns->getRecords('example.com', 'MX');
```

### Проверка TXT-записей (например, SPF):

```php
$txt = $dns->getRecords('example.com', 'TXT');
```

### Проверка CNAME:

```php
$cname = $dns->getRecords('sub.example.com', 'CNAME');
```

## Лицензия

MIT
