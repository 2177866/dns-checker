<?php

namespace Alyakin\DnsChecker;

use Alyakin\DnsChecker\Contracts\DnsLookup;

final class DnsCheckerFactory
{
    /**
     * @var callable(array<string, mixed>): DnsLookup
     */
    private $dnsLookupFactory;

    /**
     * @param  array<string, mixed>  $baseConfig
     * @param  callable(array<string, mixed>): DnsLookup|null  $dnsLookupFactory
     */
    public function __construct(
        private array $baseConfig = [],
        ?callable $dnsLookupFactory = null,
    ) {
        $this->dnsLookupFactory = $dnsLookupFactory ?? static fn (array $config): DnsLookup => new DnsLookupService($config);
    }

    public function make(): DnsCheckerClient
    {
        return new DnsCheckerClient($this->baseConfig, $this->dnsLookupFactory, $this->baseConfig);
    }

    public function usingServer(string $server): DnsCheckerClient
    {
        return $this->make()->usingServer($server);
    }

    /**
     * @param  array<int, string>  $servers
     */
    public function usingServers(array $servers): DnsCheckerClient
    {
        return $this->make()->usingServers($servers);
    }

    public function withTimeout(int|float $seconds): DnsCheckerClient
    {
        return $this->make()->withTimeout($seconds);
    }

    public function withRetries(int $count): DnsCheckerClient
    {
        return $this->make()->withRetries($count);
    }

    public function setRetries(int $count): DnsCheckerClient
    {
        return $this->make()->setRetries($count);
    }

    public function fallbackToSystem(bool $enabled = true): DnsCheckerClient
    {
        return $this->make()->fallbackToSystem($enabled);
    }

    public function logNxdomain(bool $enabled = true): DnsCheckerClient
    {
        return $this->make()->logNxdomain($enabled);
    }

    public function throwExceptions(bool $enabled = true): DnsCheckerClient
    {
        return $this->make()->throwExceptions($enabled);
    }

    public function validateDomain(string $classAtMethod): DnsCheckerClient
    {
        return $this->make()->validateDomain($classAtMethod);
    }

    public function withoutDomainValidation(): DnsCheckerClient
    {
        return $this->make()->withoutDomainValidation();
    }

    /**
     * @return array<int, string>
     */
    public function query(string $domain, string $type = 'A'): array
    {
        return $this->make()->query($domain, $type);
    }

    /**
     * @return array<int, string>
     */
    public function getRecords(string $domain, string $type = 'A'): array
    {
        return $this->query($domain, $type);
    }
}
