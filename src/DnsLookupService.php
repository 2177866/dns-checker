<?php

namespace Alyakin\DnsChecker;

use Net_DNS2_Resolver;
use Net_DNS2_Exception;

class DnsLookupService
{
    protected array $dnsServers;
    protected int $timeout;
    protected int $retryCount;
    protected bool $fallbackToSystem;
    protected bool $logNxdomain;

    public function __construct(array $config)
    {
        $this->dnsServers = $config['servers'] ?? [];
        $this->timeout = $config['timeout'] ?? 2;
        $this->retryCount = $config['retry_count'] ?? 1;
        $this->fallbackToSystem = $config['fallback_to_system'] ?? true;
        $this->logNxdomain = $config['log_nxdomain'] ?? false;
    }

    public function getRecords(string $domain, string $type = 'A'): array
    {
        // Пытаемся с кастомными серверами
        if (!empty($this->dnsServers)) {
            $result = $this->resolve($domain, $type, $this->dnsServers);
            if (!empty($result)) {
                return $result;
            }

            if (!$this->fallbackToSystem) {
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

        } catch (Net_DNS2_Exception $e) {
            $isNxdomain = $this->isNxdomainException($e);
            if (!$isNxdomain || $this->logNxdomain) {
                $this->reportFailure(
                    "DNS lookup failed (" . (empty($nameservers) ? 'system' : implode(', ', $nameservers)) . "): " . $e->getMessage()
                );
            }
            return [];
        }
    }

    protected function createResolver(array $nameservers)
    {
        return new Net_DNS2_Resolver([
            'nameservers' => $nameservers,
            'timeout'     => $this->timeout,
            'retry_count' => $this->retryCount,
        ]);
    }

    protected function reportFailure(string $message): void
    {
        if (function_exists(__NAMESPACE__ . '\\report')) {
            report($message);
            return;
        }

        if (function_exists('report')) {
            \report($message);
        }
    }

    protected function isNxdomainException(Net_DNS2_Exception $e): bool
    {
        return stripos($e->getMessage(), 'NXDOMAIN') !== false;
    }

    protected function extractRecordData($record, string $type): ?string
    {
        return match (strtoupper($type)) {
            'A', 'AAAA'     => $record->address ?? null,
            'MX'            => $record->exchange ?? null,
            'NS', 'CNAME'   => $record->target ?? null,
            'TXT'           => $record->text ?? null,
            default         => method_exists($record, '__toString') ? (string)$record : null,
        };
    }
}
