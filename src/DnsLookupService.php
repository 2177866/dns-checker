<?php

namespace Alyakin\DnsChecker;

use Alyakin\DnsChecker\Exceptions\DnsQueryFailedException;
use Alyakin\DnsChecker\Exceptions\DnsRecordNotFoundException;
use Alyakin\DnsChecker\Exceptions\DnsTimeoutException;

class DnsLookupService
{
    protected array $dnsServers;

    protected int $timeout;

    protected int $retryCount;

    protected bool $fallbackToSystem;

    protected bool $logNxdomain;

    protected ?string $domainValidator;

    protected bool $throwExceptions;

    public function __construct(array $config)
    {
        $this->dnsServers = $config['servers'] ?? [];
        $this->timeout = $config['timeout'] ?? 2;
        $this->retryCount = $config['retry_count'] ?? 1;
        $this->fallbackToSystem = $config['fallback_to_system'] ?? true;
        $this->logNxdomain = $config['log_nxdomain'] ?? false;
        $this->throwExceptions = $config['throw_exceptions'] ?? false;
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

            return array_values($records);

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
        if ($e instanceof \NetDNS2\Exception) {
            return $e->getCode() === \NetDNS2\ENUM\Error::DNS_NXDOMAIN->value;
        }

        return stripos($e->getMessage(), 'NXDOMAIN') !== false;
    }

    protected function isTimeoutException(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return stripos($message, 'timed out') !== false
            || stripos($message, 'timeout') !== false;
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
