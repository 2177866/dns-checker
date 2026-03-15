# Configuration

File: `config/dns-checker.php`

| Option | Type | Default | Description |
|---|---|---|---|
| `servers` | `array<string>` | predefined public resolvers | DNS servers queried first |
| `timeout` | `int\|float` | `2` | Resolver timeout |
| `fallback_to_system` | `bool` | `true` | Use system resolver when custom resolvers return no records |
| `log_nxdomain` | `bool` | `false` | Call `report()` for NXDOMAIN errors |
| `throw_exceptions` | `bool` | `false` | Throw typed exceptions instead of returning `[]` |
| `domain_validator` | `string\|null` | `DomainValidator::class.'@validate'` | Static validator callback or `null` to disable validation |
| `cache.enabled` | `bool` | `false` | Enable Laravel Cache-backed DNS result caching |
| `cache.store` | `string\|null` | `null` | Specific Laravel cache store |
| `cache.ttl` | `int` | `60` | Cache TTL in seconds |
| `cache.prefix` | `string` | `dns-checker` | Cache key prefix |
| `cache.cache_empty` | `bool` | `false` | Cache empty NOERROR/NODATA responses |

## Custom Domain Validator Example

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
