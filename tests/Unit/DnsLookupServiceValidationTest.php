<?php

namespace Alyakin\DnsChecker\Tests\Unit;

use Alyakin\DnsChecker\DnsLookupService;
use Alyakin\DnsChecker\DomainValidator;
use Alyakin\DnsChecker\Tests\TestCase;
use Closure;

final class DnsLookupServiceValidationTest extends TestCase
{
    public function test_it_does_not_query_the_resolver_when_the_domain_is_invalid_by_the_default_validator(): void
    {
        $service = new class([]) extends DnsLookupService
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

        $this->assertSame([], $service->getRecords('bad domain', 'A'));
        $this->assertSame(0, $service->resolverCalls);
    }

    public function test_it_does_not_query_the_resolver_when_the_domain_becomes_empty_after_normalization(): void
    {
        $service = new class([]) extends DnsLookupService
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

        $this->assertSame([], $service->getRecords(' . . . ', 'A'));
        $this->assertSame(0, $service->resolverCalls);
    }

    public function test_it_does_not_query_the_resolver_when_the_domain_validator_config_is_invalid(): void
    {
        $service = new class(['domain_validator' => 'BadFormat']) extends DnsLookupService
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

        $this->assertSame([], $service->getRecords('example.com', 'A'));
        $this->assertSame(0, $service->resolverCalls);
    }

    public function test_it_does_not_query_the_resolver_when_the_validator_method_is_missing_from_the_configured_callback(): void
    {
        $service = new class(['domain_validator' => DomainValidator::class.'@missingMethod']) extends DnsLookupService
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

        $this->assertSame([], $service->getRecords('example.com', 'A'));
        $this->assertSame(0, $service->resolverCalls);
    }

    public function test_it_extracts_record_values_for_common_types_and_normalizes_domains(): void
    {
        $service = new class([]) extends DnsLookupService
        {
            public array $queries = [];

            protected function createResolver(array $nameservers)
            {
                $queries = &$this->queries;

                return new class(function (string $domain, string $type) use (&$queries): void {
                    $queries[] = [$domain, $type];
                })
                {

                    public function __construct(private Closure $recordQuery) {}

                    public function query(string $domain, string $type): object
                    {
                        ($this->recordQuery)($domain, $type);

                        return (object) [
                            'answer' => match ($type) {
                                'A' => [(object) ['address' => '1.2.3.4']],
                                'MX' => [(object) ['exchange' => 'mx.example.com']],
                                'NS' => [(object) ['target' => 'ns1.example.com']],
                                'TXT' => [(object) ['text' => 'hello']],
                                default => [new class
                                {
                                    public function __toString(): string
                                    {
                                        return 'raw';
                                    }
                                }],
                            },
                        ];
                    }
                };
            }
        };

        $this->assertSame(['1.2.3.4'], $service->getRecords(' example.com. ', 'A'));
        $this->assertSame(['mx.example.com'], $service->getRecords('example.com', 'MX'));
        $this->assertSame(['ns1.example.com'], $service->getRecords('example.com', 'NS'));
        $this->assertSame(['hello'], $service->getRecords('example.com', 'TXT'));
        $this->assertSame(['raw'], $service->getRecords('example.com', 'CAA'));
        $this->assertSame(['example.com', 'A'], $service->queries[0]);
    }
}
