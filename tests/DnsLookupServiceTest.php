<?php

use Alyakin\DnsChecker\CacheSpy;
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

it('caches successful DNS responses via Laravel cache when enabled', function () {
    CacheSpy::reset();

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

    expect($service->getRecords('example.com', 'A'))->toBe(['1.2.3.4']);
    expect($service->resolverCalls)->toBe(1);

    expect($service->getRecords('example.com', 'A'))->toBe(['1.2.3.4']);
    expect($service->resolverCalls)->toBe(1);
});

it('does not call report() on NXDOMAIN (Net_DNS2_Exception code=3) by default', function () {
    $service = new class([]) extends DnsLookupService
    {
        protected function createResolver(array $nameservers)
        {
            return new class
            {
                public function query(string $domain, string $type): object
                {
                    throw new Net_DNS2_Exception('no such domain', 3);
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

it('supports fluent config mutation and shortcut query methods on DnsCheckerClient', function () {
    $receivedConfigs = [];
    $receivedQueries = [];

    $client = new \Alyakin\DnsChecker\DnsCheckerClient(
        ['timeout' => 2],
        function (array $config) use (&$receivedConfigs, &$receivedQueries): DnsLookup {
            $receivedConfigs[] = $config;

            return new class(function (string $domain, string $type) use (&$receivedQueries): void {
                $receivedQueries[] = [$domain, $type];
            }) implements DnsLookup
            {
                public function __construct(private \Closure $recordQuery) {}

                public function getRecords(string $domain, string $type = 'A'): array
                {
                    ($this->recordQuery)($domain, $type);

                    return ["$type:$domain"];
                }
            };
        },
        ['timeout' => 2],
    );

    expect($client->getConfig())->toBe(['timeout' => 2]);

    $client
        ->usingServers(['8.8.8.8', '1.1.1.1'])
        ->addServer('9.9.9.9')
        ->withTimeout(5)
        ->withRetries(3)
        ->fallbackToSystem(false)
        ->logNxdomain()
        ->throwExceptions()
        ->validateDomain(\Alyakin\DnsChecker\DomainValidator::class.'@validate');

    expect($client->getConfig())->toMatchArray([
        'servers' => ['8.8.8.8', '1.1.1.1', '9.9.9.9'],
        'timeout' => 5,
        'retry_count' => 3,
        'fallback_to_system' => false,
        'log_nxdomain' => true,
        'throw_exceptions' => true,
        'domain_validator' => \Alyakin\DnsChecker\DomainValidator::class.'@validate',
    ]);

    expect($client->clearServers()->getConfig()['servers'])->toBe([]);
    $client->withoutDomainValidation();

    expect($client->getRecords('example.com', 'A'))->toBe(['A:example.com']);
    expect($client->a('example.com'))->toBe(['A:example.com']);
    expect($client->aaaa('example.com'))->toBe(['AAAA:example.com']);
    expect($client->mx('example.com'))->toBe(['MX:example.com']);
    expect($client->ns('example.com'))->toBe(['NS:example.com']);
    expect($client->txt('example.com'))->toBe(['TXT:example.com']);
    expect($client->cname('example.com'))->toBe(['CNAME:example.com']);

    expect($receivedConfigs)->toHaveCount(7);
    expect($receivedQueries)->toHaveCount(7);

    $client->setConfig(['servers' => ['8.8.4.4']]);
    expect($client->getConfig())->toBe(['servers' => ['8.8.4.4']]);

    $client->resetConfig();
    expect($client->getConfig())->toBe(['timeout' => 2]);
});

it('exposes the same fluent API on DnsCheckerFactory', function () {
    $receivedConfigs = [];

    $factory = new DnsCheckerFactory(
        ['timeout' => 2],
        function (array $config) use (&$receivedConfigs): DnsLookup {
            $receivedConfigs[] = $config;

            return new class implements DnsLookup
            {
                public function getRecords(string $domain, string $type = 'A'): array
                {
                    return ["$type:$domain"];
                }
            };
        }
    );

    expect($factory->make())->toBeInstanceOf(\Alyakin\DnsChecker\DnsCheckerClient::class);
    expect($factory->usingServer('8.8.8.8'))->toBeInstanceOf(\Alyakin\DnsChecker\DnsCheckerClient::class);
    expect($factory->usingServers(['1.1.1.1']))->toBeInstanceOf(\Alyakin\DnsChecker\DnsCheckerClient::class);
    expect($factory->withTimeout(5))->toBeInstanceOf(\Alyakin\DnsChecker\DnsCheckerClient::class);
    expect($factory->withRetries(3))->toBeInstanceOf(\Alyakin\DnsChecker\DnsCheckerClient::class);
    expect($factory->setRetries(4))->toBeInstanceOf(\Alyakin\DnsChecker\DnsCheckerClient::class);
    expect($factory->fallbackToSystem(false))->toBeInstanceOf(\Alyakin\DnsChecker\DnsCheckerClient::class);
    expect($factory->logNxdomain())->toBeInstanceOf(\Alyakin\DnsChecker\DnsCheckerClient::class);
    expect($factory->throwExceptions())->toBeInstanceOf(\Alyakin\DnsChecker\DnsCheckerClient::class);
    expect($factory->validateDomain(\Alyakin\DnsChecker\DomainValidator::class.'@validate'))->toBeInstanceOf(\Alyakin\DnsChecker\DnsCheckerClient::class);
    expect($factory->withoutDomainValidation())->toBeInstanceOf(\Alyakin\DnsChecker\DnsCheckerClient::class);

    expect($factory->query('example.com', 'TXT'))->toBe(['TXT:example.com']);
    expect($factory->getRecords('example.com', 'A'))->toBe(['A:example.com']);
    expect($receivedConfigs)->toHaveCount(2);
});

it('extracts record values for common types and normalizes domains', function () {
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

                public function __construct(private \Closure $recordQuery) {}

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

    expect($service->getRecords(' example.com. ', 'A'))->toBe(['1.2.3.4']);
    expect($service->getRecords('example.com', 'MX'))->toBe(['mx.example.com']);
    expect($service->getRecords('example.com', 'NS'))->toBe(['ns1.example.com']);
    expect($service->getRecords('example.com', 'TXT'))->toBe(['hello']);
    expect($service->getRecords('example.com', 'CAA'))->toBe(['raw']);

    expect($service->queries[0])->toBe(['example.com', 'A']);
});

it('can cache empty responses when cache_empty=true', function () {
    CacheSpy::reset();

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

    expect($service->getRecords('example.com', 'A'))->toBe([]);
    expect(array_values(CacheSpy::$store))->toBe([[]]);
});

it('reports DNS failures except NXDOMAIN by default (or when log_nxdomain=true)', function () {
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

    expect($service->getRecords('example.com', 'A'))->toBe([]);
    expect(ReportSpy::$calls)->toHaveCount(1);

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

    expect($service->getRecords('does-not-exist.example', 'A'))->toBe([]);
    expect(ReportSpy::$calls)->toHaveCount(1);
});

it('does not query resolver when domain validator config is invalid', function () {
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

    expect($service->getRecords('example.com', 'A'))->toBe([]);
    expect($service->resolverCalls)->toBe(0);
});

it('maps unknown errors to DnsQueryFailedException when throw_exceptions=true', function () {
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

    $service->getRecords('example.com', 'A');
})->throws(\Alyakin\DnsChecker\Exceptions\DnsQueryFailedException::class);
