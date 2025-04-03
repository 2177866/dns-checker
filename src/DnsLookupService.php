<?php

namespace Alyakin\DnsChecker;

use Net_DNS2_Resolver;
use Net_DNS2_Exception;

class DnsLookupService
{
    protected array $dnsServers;
    protected int $timeout;
    protected int $retryCount;

    public function __construct(array $config)
    {
        $this->dnsServers = $config['servers'] ?? [];
        $this->timeout = $config['timeout'] ?? 2;
        $this->retryCount = $config['retry_count'] ?? 1;
    }

    public function getRecords(string $domain, string $type = 'A'): array
    {
        // Пытаемся с кастомными серверами
        if (!empty($this->dnsServers)) {
            $result = $this->resolve($domain, $type, $this->dnsServers);
            if (!empty($result)) {
                return $result;
            }
        }

        // Пытаемся с системным резолвером
        return $this->resolve($domain, $type);
    }

    protected function resolve(string $domain, string $type, array $nameservers = []): array
    {
        try {
            $resolver = new Net_DNS2_Resolver([
                'nameservers' => $nameservers,
                'timeout'     => $this->timeout,
                'retry_count' => $this->retryCount,
            ]);

            $response = $resolver->query($domain, $type);

            return collect($response->answer)
                ->map(fn($record) => $this->extractRecordData($record, $type))
                ->filter()
                ->values()
                ->all();

        } catch (Net_DNS2_Exception $e) {
            report("DNS lookup failed (" . (empty($nameservers) ? 'system' : implode(', ', $nameservers)) . "): " . $e->getMessage());
            return [];
        }
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
