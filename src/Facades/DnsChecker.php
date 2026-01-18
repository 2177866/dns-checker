<?php

namespace Alyakin\DnsChecker\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array<int, string> getRecords(string $domain, string $type = 'A')
 *
 * @see \Alyakin\DnsChecker\DnsLookupService
 */
final class DnsChecker extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'dns-checker';
    }
}
