<?php

namespace Alyakin\DnsChecker\Tests;

use Alyakin\DnsChecker\DnsLookupService;
use Alyakin\DnsChecker\ReportSpy;
use Net_DNS2_Exception;
use PHPUnit\Framework\TestCase;

final class DnsLookupServiceTest extends TestCase
{
    public function testCustomServersWithFallbackFalseDoesNotQuerySystemResolver(): void
    {
        $service = new class([
            'servers' => ['203.0.113.53'],
            'fallback_to_system' => false,
        ]) extends DnsLookupService {
            public array $resolverNameserversCalls = [];

            protected function createResolver(array $nameservers)
            {
                $this->resolverNameserversCalls[] = $nameservers;

                return new class {
                    public function query(string $domain, string $type): object
                    {
                        return (object) ['answer' => []];
                    }
                };
            }
        };

        $records = $service->getRecords('example.com', 'A');

        $this->assertSame([], $records);
        $this->assertSame([['203.0.113.53']], $service->resolverNameserversCalls);
    }

    public function testNxdomainDoesNotCallReportByDefault(): void
    {
        ReportSpy::reset();

        $service = new class([]) extends DnsLookupService {
            protected function createResolver(array $nameservers)
            {
                return new class {
                    public function query(string $domain, string $type): object
                    {
                        throw new Net_DNS2_Exception('NXDOMAIN');
                    }
                };
            }
        };

        $records = $service->getRecords('does-not-exist.example', 'A');

        $this->assertSame([], $records);
        $this->assertSame([], ReportSpy::$calls);
    }
}

