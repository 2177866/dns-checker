# Raw DNS responses

Use `queryRaw(string $domain, string $type = 'A'): ?object` when you need the original resolver response rather than an array of extracted values:

```php
use Alyakin\DnsChecker\DnsLookupService;

$dns = new DnsLookupService(config('dns-checker'));
$response = $dns->queryRaw('www.example.com', 'A');

if ($response !== null) {
    foreach ($response->answer as $record) {
        // The native record representation includes owner, type, TTL and data.
        echo (string) $record, PHP_EOL;
    }

    // Other native response sections are preserved too.
    $authority = $response->authority;
    $additional = $response->additional;
}
```

The returned object is the **unmodified NetDNS2 response**, not a package-defined DTO or an array. It retains the header, question, answer, authority and additional sections, including record owner names, types, TTLs and record-specific data. A query for `A` may contain both CNAME and A records; raw mode does not filter them, follow additional CNAME links itself or decide whether a domain points to your application.

This package targets NetDNS2 v2. Native object shapes belong to that library. Names, record types and record-specific fields are not normalized: for example, NetDNS2 v2 CNAME records expose `cname` as a domain object, not a universal `target` string. Inspect the installed resolver's types before accessing record-specific properties. Casting a record to string, as above, does not assume that every record shares the same data properties.

Return and error behavior:

- A valid response with an empty `answer` is still a response object, not `null`.
- A domain rejected by the configured validator returns `null`, including when `throw_exceptions=true`.
- With `throw_exceptions=false`, a resolver failure returns `null` if no valid response is available; reporting follows `log_nxdomain` just as for `getRecords()`.
- With `throw_exceptions=true`, resolver failures throw `DnsRecordNotFoundException`, `DnsTimeoutException` or `DnsQueryFailedException`. An exception from a configured server is propagated immediately, without a system fallback.
- When custom `servers` are configured, a response with a nonempty answer is returned directly. Otherwise, `fallback_to_system=true` allows a system lookup. Its valid response is returned even if its answer is empty; if that lookup fails with exceptions disabled, an earlier valid empty response is retained. With fallback disabled, the custom lookup's response (or `null`) is returned as-is.

Raw queries intentionally **bypass the package's Laravel response cache**: native resolver objects are not serialized into the extracted-value cache, and raw calls neither read nor populate it. Upstream DNS resolvers may still cache responses. Existing `getRecords()` / `query()` array results and their cache behavior are unchanged.

## Facade, fluent client and factory

```php
use Alyakin\DnsChecker\DnsCheckerFactory;
use Alyakin\DnsChecker\Facades\DnsChecker;

$response = DnsChecker::queryRaw('www.example.com');
$response = DnsChecker::usingServer('8.8.8.8')
    ->fallbackToSystem(false)
    ->withTimeout(5)
    ->queryRaw('www.example.com', 'CNAME');

// Works without Laravel when passed an explicit configuration array.
$factory = new DnsCheckerFactory(['timeout' => 2]);
$response = $factory->queryRaw('www.example.com', 'A');
$response = $factory->make()->queryRaw('www.example.com', 'CNAME');
```

## Optional raw lookup contract

`Contracts\RawDnsLookup` extends the existing `Contracts\DnsLookup` with `queryRaw()`. The original `DnsLookup` interface is unchanged, so existing custom implementations remain compatible with value-only queries.

The Laravel provider makes `RawDnsLookup` resolve to the same `DnsLookupService` singleton as the concrete service. Request the optional contract explicitly when your consumer requires raw access:

```php
use Alyakin\DnsChecker\Contracts\RawDnsLookup;

final class InspectDomain
{
    public function handle(RawDnsLookup $dns): ?object
    {
        return $dns->queryRaw('www.example.com', 'A');
    }
}
```

If a custom `DnsCheckerFactory` lookup callback returns an implementation of only `DnsLookup`, the client's `query()` / `getRecords()` methods still work. Calling `queryRaw()` through that client or factory throws `LogicException`; the package does not fabricate a response from extracted values. This capability error is independent of `throw_exceptions`.
