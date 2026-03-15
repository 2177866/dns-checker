<?php

namespace Alyakin\DnsChecker\Tests\Unit;

use Alyakin\DnsChecker\CacheSpy;
use Alyakin\DnsChecker\DnsLookupService;
use Alyakin\DnsChecker\Tests\TestCase;
use RuntimeException;

final class DnsLookupServiceCachingTest extends TestCase
{
    public function testItCachesSuccessfulDnsResponsesViaLaravelCacheWhenEnabled(): void
    {
        $service = new class(['cache' => ['enabled' => true, 'ttl' => 60, 'prefix' => 'dns-checker-tests']]) extends DnsLookupService
        {
            public int $resolverCalls = 0;

            protected function createResolver(array $nameservers)
            {
                $this->resolverCalls++;

                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        return (object) ['answer' => [(object) ['address' => '1.2.3.4']]];
                    }
                };
            }
        };

        $this->assertSame(['1.2.3.4'], $service->getRecords('example.com', 'A'));
        $this->assertSame(1, $service->resolverCalls);

        $this->assertSame(['1.2.3.4'], $service->getRecords('example.com', 'A'));
        $this->assertSame(1, $service->resolverCalls);
    }

    public function testItCanCacheEmptyResponsesWhenCacheEmptyIsEnabled(): void
    {
        $service = new class(['cache' => ['enabled' => true, 'ttl' => 60, 'prefix' => 'dns-checker-tests', 'cache_empty' => true]]) extends DnsLookupService
        {
            protected function createResolver(array $nameservers)
            {
                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        return (object) ['answer' => []];
                    }
                };
            }
        };

        $this->assertSame([], $service->getRecords('example.com', 'A'));
        $this->assertSame([[]], array_values(CacheSpy::$store));
    }

    public function testItContinuesDnsLookupsEvenWhenTheCacheBackendIsUnavailable(): void
    {
        CacheSpy::$repositoryFactory = static fn (): object => new class
        {
            public function store(?string $name): object
            {
                throw new RuntimeException('cache store is temporarily unavailable');
            }
        };

        $service = new class([
            'cache' => [
                'enabled' => true,
                'store' => 'redis',
                'ttl' => 60,
                'prefix' => 'dns-checker-tests',
            ],
        ]) extends DnsLookupService
        {
            public int $resolverCalls = 0;

            protected function createResolver(array $nameservers)
            {
                $this->resolverCalls++;

                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        return (object) ['answer' => [(object) ['address' => '198.51.100.21']]];
                    }
                };
            }
        };

        $this->assertSame(['198.51.100.21'], $service->getRecords('example.com', 'A'));
        $this->assertSame(1, $service->resolverCalls);
        $this->assertSame([], CacheSpy::$store);
    }
}
