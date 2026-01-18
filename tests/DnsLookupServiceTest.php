<?php

use Alyakin\DnsChecker\DnsLookupService;
use Alyakin\DnsChecker\ReportSpy;

it('does not query system resolver when custom servers are set and fallback_to_system=false', function () {
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

    $records = $service->getRecords('example.com', 'A');

    expect($records)->toBe([]);
    expect($service->resolverNameserversCalls)->toBe([['203.0.113.53']]);
});

it('does not call report() on NXDOMAIN by default', function () {
    $service = new class([]) extends DnsLookupService
    {
        protected function createResolver(array $nameservers)
        {
            return new class
            {
                public function query(string $domain, string $type): object
                {
                    throw new \Net_DNS2_Exception('NXDOMAIN');
                }
            };
        }
    };

    $records = $service->getRecords('does-not-exist.example', 'A');

    expect($records)->toBe([]);
    expect(ReportSpy::$calls)->toBe([]);
});

it('does not query resolver when domain is invalid (default validator)', function () {
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

    $records = $service->getRecords('bad domain', 'A');

    expect($records)->toBe([]);
    expect($service->resolverCalls)->toBe(0);
});

it('allows disabling domain validation via config', function () {
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

    expect($records)->toBe([]);
    expect($service->resolverCalls)->toBe(1);
});
