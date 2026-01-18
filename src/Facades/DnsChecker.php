<?php

namespace Alyakin\DnsChecker\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Alyakin\DnsChecker\DnsCheckerClient usingServer(string $server)
 * @method static \Alyakin\DnsChecker\DnsCheckerClient usingServers(array $servers)
 * @method static \Alyakin\DnsChecker\DnsCheckerClient withTimeout(int|float $seconds)
 * @method static \Alyakin\DnsChecker\DnsCheckerClient withRetries(int $count)
 * @method static \Alyakin\DnsChecker\DnsCheckerClient setRetries(int $count)
 * @method static \Alyakin\DnsChecker\DnsCheckerClient fallbackToSystem(bool $enabled = true)
 * @method static \Alyakin\DnsChecker\DnsCheckerClient logNxdomain(bool $enabled = true)
 * @method static \Alyakin\DnsChecker\DnsCheckerClient throwExceptions(bool $enabled = true)
 * @method static \Alyakin\DnsChecker\DnsCheckerClient withoutDomainValidation()
 * @method static \Alyakin\DnsChecker\DnsCheckerClient validateDomain(string $classAtMethod)
 * @method static array<int, string> query(string $domain, string $type = 'A')
 * @method static array<int, string> getRecords(string $domain, string $type = 'A')
 *
 * @see \Alyakin\DnsChecker\DnsCheckerFactory
 */
final class DnsChecker extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'dns-checker.factory';
    }
}
