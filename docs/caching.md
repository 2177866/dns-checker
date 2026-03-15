# Caching

## Cache Flow

```mermaid
flowchart TB
    A[Request: domain + type] --> B{Cache enabled?}
    B -->|no| C[Query resolver]
    B -->|yes| D[Build cache key]
    D --> E[Read cache]
    E --> F{Cache hit?}
    F -->|yes| G[Return cached result]
    F -->|no| C
    C --> H[Collect DNS records]
    H --> I{Cache write allowed?}
    I -->|yes| J[Store result with TTL]
    I -->|no| K[Skip cache write]
    J --> L[Return result]
    K --> L
```

## Laravel Cache Example

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

## Notes

- This is an outer cache for DNS query results.
- It uses Laravel Cache stores such as Redis, file, database, or Memcached.
- It does not rely on NetDNS2 file/shmop cache internals.
- `cache_empty=true` allows caching empty NOERROR/NODATA responses.
- Exceptions are not cached.
