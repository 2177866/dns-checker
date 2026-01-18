<?php

namespace Alyakin\DnsChecker;

use Alyakin\DnsChecker\Contracts\DnsLookup;

final class DnsCheckerClient
{
    /**
     * @param  array<string, mixed>  $config
     * @param  callable(array<string, mixed>): DnsLookup  $dnsLookupFactory
     */
    public function __construct(
        private array $config,
        private $dnsLookupFactory,
        private readonly array $baseConfig = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function resetConfig(): self
    {
        $this->config = $this->baseConfig;

        return $this;
    }

    public function usingServer(string $server): self
    {
        $this->config['servers'] = [$server];

        return $this;
    }

    /**
     * @param  array<int, string>  $servers
     */
    public function usingServers(array $servers): self
    {
        $this->config['servers'] = array_values($servers);

        return $this;
    }

    public function addServer(string $server): self
    {
        $this->config['servers'] = array_values(array_merge($this->config['servers'] ?? [], [$server]));

        return $this;
    }

    public function clearServers(): self
    {
        $this->config['servers'] = [];

        return $this;
    }

    public function withTimeout(int|float $seconds): self
    {
        $this->config['timeout'] = $seconds;

        return $this;
    }

    public function withRetries(int $count): self
    {
        return $this->setRetries($count);
    }

    public function setRetries(int $count): self
    {
        $this->config['retry_count'] = $count;

        return $this;
    }

    public function fallbackToSystem(bool $enabled = true): self
    {
        $this->config['fallback_to_system'] = $enabled;

        return $this;
    }

    public function logNxdomain(bool $enabled = true): self
    {
        $this->config['log_nxdomain'] = $enabled;

        return $this;
    }

    public function throwExceptions(bool $enabled = true): self
    {
        $this->config['throw_exceptions'] = $enabled;

        return $this;
    }

    public function validateDomain(string $classAtMethod): self
    {
        $this->config['domain_validator'] = $classAtMethod;

        return $this;
    }

    public function withoutDomainValidation(): self
    {
        $this->config['domain_validator'] = null;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function query(string $domain, string $type = 'A'): array
    {
        /** @var DnsLookup $dns */
        $dns = ($this->dnsLookupFactory)($this->config);

        return $dns->getRecords($domain, $type);
    }

    /**
     * @return array<int, string>
     */
    public function getRecords(string $domain, string $type = 'A'): array
    {
        return $this->query($domain, $type);
    }

    /**
     * @return array<int, string>
     */
    public function a(string $domain): array
    {
        return $this->query($domain, 'A');
    }

    /**
     * @return array<int, string>
     */
    public function aaaa(string $domain): array
    {
        return $this->query($domain, 'AAAA');
    }

    /**
     * @return array<int, string>
     */
    public function mx(string $domain): array
    {
        return $this->query($domain, 'MX');
    }

    /**
     * @return array<int, string>
     */
    public function ns(string $domain): array
    {
        return $this->query($domain, 'NS');
    }

    /**
     * @return array<int, string>
     */
    public function txt(string $domain): array
    {
        return $this->query($domain, 'TXT');
    }

    /**
     * @return array<int, string>
     */
    public function cname(string $domain): array
    {
        return $this->query($domain, 'CNAME');
    }
}
