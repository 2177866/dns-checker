<?php

namespace Alyakin\DnsChecker\Tests\Unit;

use Alyakin\DnsChecker\CacheSpy;
use Alyakin\DnsChecker\DnsLookupService;
use Alyakin\DnsChecker\ExampleDomainValidator;
use Alyakin\DnsChecker\Tests\TestCase;
use Closure;

final class DnsLookupServiceUsageTest extends TestCase
{
    public function testItCanQueryRecordsDirectlyWithDnsLookupServiceConfiguration(): void
    {
        $service = new class([
            'servers' => ['8.8.8.8', '1.1.1.1'],
            'timeout' => 2,
            'fallback_to_system' => true,
        ]) extends DnsLookupService
        {
            public array $queries = [];

            protected function createResolver(array $nameservers)
            {
                $queries = &$this->queries;

                return new class($nameservers, function (array $nameservers, string $domain, string $type) use (&$queries): void {
                    $queries[] = compact('nameservers', 'domain', 'type');
                })
                {
                    public function __construct(
                        private array $nameservers,
                        private Closure $recordQuery,
                    ) {}

                    public function query(string $domain, string $type): object
                    {
                        ($this->recordQuery)($this->nameservers, $domain, $type);

                        return (object) ['answer' => [(object) ['address' => '203.0.113.10']]];
                    }
                };
            }
        };

        $records = $service->getRecords('example.com', 'A');

        $this->assertSame(['203.0.113.10'], $records);
        $this->assertSame([
            'nameservers' => ['8.8.8.8', '1.1.1.1'],
            'domain' => 'example.com',
            'type' => 'A',
        ], $service->queries[0]);
    }

    public function testItCanPreferCustomResolversAndStopAfterTheFirstSuccessfulResponse(): void
    {
        $service = new class(['servers' => ['203.0.113.53', '203.0.113.54']]) extends DnsLookupService
        {
            public array $resolverNameserversCalls = [];

            protected function createResolver(array $nameservers)
            {
                $this->resolverNameserversCalls[] = $nameservers;

                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        return (object) [
                            'answer' => [
                                (object) ['address' => '203.0.113.10'],
                                (object) ['address' => '203.0.113.11'],
                            ],
                        ];
                    }
                };
            }
        };

        $records = $service->getRecords('example.com', 'A');

        $this->assertSame(['203.0.113.10', '203.0.113.11'], $records);
        $this->assertSame([['203.0.113.53', '203.0.113.54']], $service->resolverNameserversCalls);
    }

    public function testItCanUseACustomDomainValidatorCallbackFromConfiguration(): void
    {
        $service = new class([
            'domain_validator' => ExampleDomainValidator::class.'@allowsExampleDomains',
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
                        return (object) ['answer' => [(object) ['address' => '198.51.100.10']]];
                    }
                };
            }
        };

        $allowedRecords = $service->getRecords('mail.example', 'A');
        $blockedRecords = $service->getRecords('mail.invalid', 'A');

        $this->assertSame(['198.51.100.10'], $allowedRecords);
        $this->assertSame([], $blockedRecords);
        $this->assertSame(1, $service->resolverCalls);
    }

    public function testItCanDisableDomainValidationWhenInputIsAlreadyPreparedByTheApplication(): void
    {
        $service = new class(['domain_validator' => null]) extends DnsLookupService
        {
            public int $resolverCalls = 0;

            protected function createResolver(array $nameservers)
            {
                $this->resolverCalls++;

                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        return (object) ['answer' => []];
                    }
                };
            }
        };

        $records = $service->getRecords('bad domain', 'A');

        $this->assertSame([], $records);
        $this->assertSame(1, $service->resolverCalls);
    }

    public function testItCanCacheDnsResultsInANamedLaravelStore(): void
    {
        $service = new class([
            'cache' => [
                'enabled' => true,
                'store' => 'redis',
                'ttl' => 60,
                'prefix' => 'dns-checker-tests',
            ],
        ]) extends DnsLookupService
        {
            protected function createResolver(array $nameservers)
            {
                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        return (object) ['answer' => [(object) ['address' => '198.51.100.20']]];
                    }
                };
            }
        };

        $records = $service->getRecords('example.com', 'A');

        $this->assertSame(['198.51.100.20'], $records);
        $this->assertSame('redis', CacheSpy::$selectedStore);
    }
}
