<?php

namespace Alyakin\DnsChecker;

use Alyakin\DnsChecker\Contracts\DnsLookup;
use Alyakin\DnsChecker\Exceptions\DnsQueryFailedException;
use Alyakin\DnsChecker\Exceptions\DnsRecordNotFoundException;
use Alyakin\DnsChecker\Exceptions\DnsTimeoutException;

class DnsLookupService implements DnsLookup
{
    protected array $dnsServers;

    protected int $timeout;

    protected int $retryCount;

    protected bool $fallbackToSystem;

    protected bool $logNxdomain;

    protected ?string $domainValidator;

    protected bool $throwExceptions;

    /**
     * @var array<string, mixed>
     */
    protected array $cacheConfig;

    public function __construct(array $config)
    {
        $this->dnsServers = $config['servers'] ?? [];
        $this->timeout = $config['timeout'] ?? 2;
        $this->retryCount = $config['retry_count'] ?? 1;
        $this->fallbackToSystem = $config['fallback_to_system'] ?? true;
        $this->logNxdomain = $config['log_nxdomain'] ?? false;
        $this->throwExceptions = $config['throw_exceptions'] ?? false;
        $this->cacheConfig = $config['cache'] ?? [];
        $this->domainValidator = array_key_exists('domain_validator', $config)
            ? $config['domain_validator']
            : (DomainValidator::class.'@validate');
    }

    public function getRecords(string $domain, string $type = 'A'): array
    {
        $domain = $this->normalizeDomain($domain);
        if (! $this->isValidDomain($domain)) {
            return [];
        }

        // Пытаемся с кастомными серверами
        if (! empty($this->dnsServers)) {
            $result = $this->resolve($domain, $type, $this->dnsServers);
            if (! empty($result)) {
                return $result;
            }

            if (! $this->fallbackToSystem) {
                return [];
            }
        }

        // Пытаемся с системным резолвером
        return $this->resolve($domain, $type);
    }

    protected function resolve(string $domain, string $type, array $nameservers = []): array
    {
        $cache = $this->getCacheRepository();
        $cacheKey = null;
        $cacheTtl = null;
        $cacheEmpty = false;
        if ($cache !== null && ($this->cacheConfig['enabled'] ?? false)) {
            $cacheKey = $this->makeCacheKey($domain, $type, $nameservers);
            $cacheTtl = $this->cacheConfig['ttl'] ?? 60;
            $cacheEmpty = (bool) ($this->cacheConfig['cache_empty'] ?? false);

            $cached = $cache->get($cacheKey);
            if (is_array($cached)) {
                /** @var array<int, string> $cached */
                return $cached;
            }
        }

        try {
            $resolver = $this->createResolver($nameservers);

            $response = $resolver->query($domain, $type);

            $records = [];
            foreach ($response->answer as $record) {
                $value = $this->extractRecordData($record, $type);
                if ($value !== null && $value !== '') {
                    $records[] = $value;
                }
            }

            $records = array_values($records);

            if ($cache !== null && ($this->cacheConfig['enabled'] ?? false) && $cacheKey !== null && $cacheTtl !== null) {
                if (! empty($records) || $cacheEmpty) {
                    $cache->put($cacheKey, $records, $cacheTtl);
                }
            }

            return $records;

        } catch (\Throwable $e) {
            $isNxdomain = $this->isNxdomainException($e);
            if ($this->throwExceptions) {
                throw $this->mapException($e, $domain, $type, $nameservers);
            }

            if (! $isNxdomain || $this->logNxdomain) {
                $this->reportFailure(
                    'DNS lookup failed ('.(empty($nameservers) ? 'system' : implode(', ', $nameservers)).'): '.$e->getMessage()
                );
            }

            return [];
        }
    }

    protected function createResolver(array $nameservers)
    {
        if (class_exists(\NetDNS2\Resolver::class)) {
            $options = [
                'nameservers' => $nameservers,
                'timeout' => $this->timeout,
            ];

            return new \NetDNS2\Resolver($options);
        }

        if (class_exists('Net_DNS2_Resolver')) {
            return new \Net_DNS2_Resolver([
                'nameservers' => $nameservers,
                'timeout' => $this->timeout,
                'retry_count' => $this->retryCount,
            ]);
        }

        throw new \RuntimeException('netdns2 resolver class not found; install pear/net_dns2');
    }

    protected function reportFailure(string $message): void
    {
        if (function_exists(__NAMESPACE__.'\\report')) {
            report($message);

            return;
        }

        if (function_exists('report')) {
            \report($message);
        }
    }

    protected function isNxdomainException(\Throwable $e): bool
    {
        if (class_exists('NetDNS2\\Exception') && $e instanceof \NetDNS2\Exception) {
            if (class_exists('NetDNS2\\ENUM\\Error')) {
                return $e->getCode() === \NetDNS2\ENUM\Error::DNS_NXDOMAIN->value;
            }

            return $e->getCode() === 3;
        }

        if (class_exists('Net_DNS2_Exception') && $e instanceof \Net_DNS2_Exception) {
            return $e->getCode() === 3;
        }

        return stripos($e->getMessage(), 'NXDOMAIN') !== false;
    }

    protected function isTimeoutException(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return stripos($message, 'timed out') !== false
            || stripos($message, 'timeout') !== false;
    }

    protected function getCacheRepository(): ?object
    {
        if (! ($this->cacheConfig['enabled'] ?? false)) {
            return null;
        }

        if (! function_exists('cache')) {
            return null;
        }

        try {
            $cache = \cache();
            if (! is_object($cache) || ! method_exists($cache, 'get') || ! method_exists($cache, 'put')) {
                return null;
            }

            $store = $this->cacheConfig['store'] ?? null;
            if ($store !== null && method_exists($cache, 'store')) {
                $cache = $cache->store($store);
            }

            if (! is_object($cache) || ! method_exists($cache, 'get') || ! method_exists($cache, 'put')) {
                return null;
            }

            return $cache;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function makeCacheKey(string $domain, string $type, array $nameservers): string
    {
        $prefix = (string) ($this->cacheConfig['prefix'] ?? 'dns-checker');
        $payload = [
            'domain' => strtolower($domain),
            'type' => strtoupper($type),
            'nameservers' => array_values($nameservers),
            'timeout' => $this->timeout,
            'retry_count' => $this->retryCount,
        ];

        return $prefix.':'.hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    protected function mapException(\Throwable $e, string $domain, string $type, array $nameservers): \RuntimeException
    {
        $resolver = empty($nameservers) ? 'system' : implode(', ', $nameservers);

        if ($this->isNxdomainException($e)) {
            return new DnsRecordNotFoundException(
                'DNS record not found (NXDOMAIN) via '.$resolver.': '.$e->getMessage(),
                $domain,
                $type,
                $resolver,
                $e
            );
        }

        if ($this->isTimeoutException($e)) {
            return new DnsTimeoutException(
                'DNS lookup timed out via '.$resolver.': '.$e->getMessage(),
                $domain,
                $type,
                $resolver,
                $e
            );
        }

        return new DnsQueryFailedException(
            'DNS lookup failed via '.$resolver.': '.$e->getMessage(),
            $domain,
            $type,
            $resolver,
            $e
        );
    }

    protected function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = rtrim($domain, '.');

        return $domain;
    }

    protected function isValidDomain(string $domain): bool
    {
        if ($domain === '') {
            return false;
        }

        if ($this->domainValidator === null) {
            return true;
        }

        if (! str_contains($this->domainValidator, '@')) {
            return false;
        }

        [$class, $method] = explode('@', $this->domainValidator, 2);
        if ($class === '' || $method === '') {
            return false;
        }

        if (! is_callable([$class, $method])) {
            return false;
        }

        return (bool) call_user_func([$class, $method], $domain);
    }

    protected function extractRecordData($record, string $type): ?string
    {
        return match (strtoupper($type)) {
            'A', 'AAAA' => $record->address ?? null,
            'MX' => $record->exchange ?? null,
            'NS', 'CNAME' => $record->target ?? null,
            'TXT' => $record->text ?? null,
            default => method_exists($record, '__toString') ? (string) $record : null,
        };
    }
}
