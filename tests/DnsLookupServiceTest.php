<?php

use Alyakin\DnsChecker\Contracts\DnsLookup;
use Alyakin\DnsChecker\DnsCheckerFactory;
use Alyakin\DnsChecker\DnsLookupService;
use Alyakin\DnsChecker\Exceptions\DnsRecordNotFoundException;
use Alyakin\DnsChecker\Exceptions\DnsTimeoutException;
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

it('implements DnsLookup contract', function () {
    $service = new DnsLookupService([]);
    expect($service)->toBeInstanceOf(DnsLookup::class);
});

it('supports fluent config overrides via factory client', function () {
    $received = [];

    $factory = new DnsCheckerFactory(
        ['timeout' => 2, 'retry_count' => 1],
        function (array $config) use (&$received): DnsLookup {
            $received[] = $config;

            return new class implements DnsLookup
            {
                public function getRecords(string $domain, string $type = 'A'): array
                {
                    return [];
                }
            };
        }
    );

    $factory
        ->usingServer('8.8.8.8')
        ->withTimeout(5)
        ->setRetries(3)
        ->fallbackToSystem(false)
        ->query('example.com', 'TXT');

    expect($received)->toHaveCount(1);
    expect($received[0])->toMatchArray([
        'servers' => ['8.8.8.8'],
        'timeout' => 5,
        'retry_count' => 3,
        'fallback_to_system' => false,
    ]);
});

it('supports getConfig, setConfig, resetConfig on fluent client', function () {
    $received = [];

    $factory = new DnsCheckerFactory(
        ['timeout' => 2, 'retry_count' => 1],
        function (array $config) use (&$received): DnsLookup {
            $received[] = $config;

            return new class implements DnsLookup
            {
                public function getRecords(string $domain, string $type = 'A'): array
                {
                    return [];
                }
            };
        }
    );

    $client = $factory->make();

    expect($client->getConfig())->toMatchArray(['timeout' => 2, 'retry_count' => 1]);

    $client->setConfig(['servers' => ['8.8.8.8'], 'timeout' => 5]);
    expect($client->getConfig())->toBe(['servers' => ['8.8.8.8'], 'timeout' => 5]);

    $client->query('example.com', 'A');
    expect($received)->toHaveCount(1);
    expect($received[0])->toBe(['servers' => ['8.8.8.8'], 'timeout' => 5]);

    $client
        ->usingServer('1.1.1.1')
        ->withTimeout(10);

    $client->resetConfig()->query('example.com', 'A');
    expect($received)->toHaveCount(2);
    expect($received[1])->toMatchArray(['timeout' => 2, 'retry_count' => 1]);
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
                    throw new RuntimeException('NXDOMAIN');
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

it('throws DnsRecordNotFoundException on NXDOMAIN when throw_exceptions=true', function () {
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

    $service->getRecords('does-not-exist.example', 'A');
})->throws(DnsRecordNotFoundException::class);

it('throws DnsTimeoutException on timeout when throw_exceptions=true', function () {
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

    $service->getRecords('example.com', 'A');
})->throws(DnsTimeoutException::class);
