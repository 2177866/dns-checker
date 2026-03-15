<?php

namespace Alyakin\DnsChecker\Tests\Unit;

use Alyakin\DnsChecker\DnsLookupService;
use Alyakin\DnsChecker\Exceptions\DnsQueryFailedException;
use Alyakin\DnsChecker\Exceptions\DnsRecordNotFoundException;
use Alyakin\DnsChecker\Exceptions\DnsTimeoutException;
use Alyakin\DnsChecker\ReportSpy;
use Alyakin\DnsChecker\Tests\TestCase;
use NetDNS2\ENUM\Error;
use NetDNS2\Exception as NetDns2Exception;
use RuntimeException;

final class DnsLookupServiceErrorHandlingTest extends TestCase
{
    public function test_it_does_not_query_the_system_resolver_when_custom_servers_are_set_and_fallback_to_system_is_disabled(): void
    {
        $service = new class(['servers' => ['203.0.113.53'], 'fallback_to_system' => false]) extends DnsLookupService
        {
            public array $resolverNameserversCalls = [];

            protected function createResolver(array $nameservers)
            {
                $this->resolverNameserversCalls[] = $nameservers;

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
        $this->assertSame([['203.0.113.53']], $service->resolverNameserversCalls);
    }

    public function test_it_does_not_call_report_on_nxdomain_by_default(): void
    {
        $service = new class([]) extends DnsLookupService
        {
            protected function createResolver(array $nameservers)
            {
                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        throw new RuntimeException('NXDOMAIN');
                    }
                };
            }
        };

        $this->assertSame([], $service->getRecords('does-not-exist.example', 'A'));
        $this->assertSame([], ReportSpy::$calls);
    }

    public function test_it_recognizes_nxdomain_from_the_real_net_dns2_exception_type(): void
    {
        $service = new class([]) extends DnsLookupService
        {
            protected function createResolver(array $nameservers)
            {
                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        throw new NetDns2Exception('no such domain', Error::DNS_NXDOMAIN);
                    }
                };
            }
        };

        $this->assertSame([], $service->getRecords('does-not-exist.example', 'A'));
        $this->assertSame([], ReportSpy::$calls);
    }

    public function test_it_throws_dns_record_not_found_exception_on_nxdomain_when_throw_exceptions_is_enabled(): void
    {
        $service = new class(['throw_exceptions' => true]) extends DnsLookupService
        {
            protected function createResolver(array $nameservers)
            {
                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        throw new RuntimeException('NXDOMAIN');
                    }
                };
            }
        };

        $this->expectException(DnsRecordNotFoundException::class);

        $service->getRecords('does-not-exist.example', 'A');
    }

    public function test_it_throws_dns_timeout_exception_on_timeout_when_throw_exceptions_is_enabled(): void
    {
        $service = new class(['throw_exceptions' => true]) extends DnsLookupService
        {
            protected function createResolver(array $nameservers)
            {
                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        throw new RuntimeException('request timed out');
                    }
                };
            }
        };

        $this->expectException(DnsTimeoutException::class);

        $service->getRecords('example.com', 'A');
    }

    public function test_it_reports_dns_failures_except_nxdomain_by_default_and_can_report_nxdomain_when_enabled(): void
    {
        $service = new class([]) extends DnsLookupService
        {
            protected function createResolver(array $nameservers)
            {
                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        throw new RuntimeException('bad things happened');
                    }
                };
            }
        };

        $this->assertSame([], $service->getRecords('example.com', 'A'));
        $this->assertCount(1, ReportSpy::$calls);

        ReportSpy::reset();

        $service = new class(['log_nxdomain' => true]) extends DnsLookupService
        {
            protected function createResolver(array $nameservers)
            {
                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        throw new RuntimeException('NXDOMAIN');
                    }
                };
            }
        };

        $this->assertSame([], $service->getRecords('does-not-exist.example', 'A'));
        $this->assertCount(1, ReportSpy::$calls);
    }

    public function test_it_maps_unknown_resolver_errors_to_dns_query_failed_exception_when_throw_exceptions_is_enabled(): void
    {
        $service = new class(['throw_exceptions' => true]) extends DnsLookupService
        {
            protected function createResolver(array $nameservers)
            {
                return new class
                {
                    public function query(string $domain, string $type): object
                    {
                        throw new RuntimeException('some other error');
                    }
                };
            }
        };

        $this->expectException(DnsQueryFailedException::class);

        $service->getRecords('example.com', 'A');
    }
}
