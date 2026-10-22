<?php

namespace Alyakin\DnsChecker\Tests\Unit;

use Alyakin\DnsChecker\Contracts\DnsLookup;
use Alyakin\DnsChecker\Contracts\RawDnsLookup;
use Alyakin\DnsChecker\DnsCheckerFactory;
use Alyakin\DnsChecker\DnsCheckerServiceProvider;
use Alyakin\DnsChecker\DnsLookupService;
use Alyakin\DnsChecker\Exceptions\DnsQueryFailedException;
use Alyakin\DnsChecker\Exceptions\DnsRecordNotFoundException;
use Alyakin\DnsChecker\Exceptions\DnsTimeoutException;
use Alyakin\DnsChecker\Facades\DnsChecker;
use PHPUnit\Framework\TestCase;

/** Deterministic resolver doubles never perform network queries or access a database. */
final class RawDnsLookupTest extends TestCase
{
    public function test_preserves_the_entire_response_and_mixed_answer_chain(): void
    {
        $response = (object) [
            'header' => (object) ['rcode' => 0, 'aa' => true],
            'question' => [(object) ['qname' => 'client.example', 'qtype' => 'A']],
            'answer' => [
                (object) ['name' => 'client.example', 'type' => 'CNAME', 'ttl' => 60, 'cname' => 'public.example'],
                (object) ['name' => 'public.example', 'type' => 'A', 'ttl' => 120, 'address' => '192.0.2.1'],
            ],
            'authority' => [(object) ['name' => 'example', 'type' => 'NS', 'ttl' => 300]],
            'additional' => [(object) ['name' => 'ns.example', 'type' => 'A', 'ttl' => 300]],
        ];
        $service = new ScriptedRawLookup([], [$response]);

        self::assertSame($response, $service->queryRaw('client.example'));
        self::assertSame('client.example', $response->answer[0]->name);
        self::assertSame('CNAME', $response->answer[0]->type);
        self::assertSame(60, $response->answer[0]->ttl);
        self::assertSame([['client.example', 'A', []]], $service->queries);
        self::assertSame(0, $service->cacheReads);
    }

    public function test_preserves_native_net_dns2_record_types(): void
    {
        $record = \NetDNS2\RR::fromString('client.example. 60 IN CNAME public.example.');
        self::assertInstanceOf(\NetDNS2\RR\CNAME::class, $record);
        $response = (new \ReflectionClass(\NetDNS2\Packet\Response::class))->newInstanceWithoutConstructor();
        $response->answer = [$record];
        $service = new ScriptedRawLookup([], [$response]);
        $owner = $record->name;
        $target = $record->cname;
        $type = $record->type;

        self::assertSame($response, $service->queryRaw('client.example', 'CNAME'));
        self::assertSame($record, $response->answer[0]);
        self::assertSame($owner, $record->name);
        self::assertSame($target, $record->cname);
        self::assertSame($type, $record->type);
        self::assertSame(60, $record->ttl);
    }

    public function test_invalid_domain_does_not_create_resolver(): void
    {
        $service = new ScriptedRawLookup(['throw_exceptions' => true], []);
        foreach (['', ' ', 'https://client.example/path', 'invalid domain'] as $domain) {
            self::assertNull($service->queryRaw($domain));
        }
        self::assertSame([], $service->queries);
    }

    public function test_normalization_and_record_type_follow_existing_contract(): void
    {
        $response = (object) ['answer' => []];
        $service = new ScriptedRawLookup([], [$response]);
        self::assertSame($response, $service->queryRaw(' Client.Example. ', 'AAAA'));
        self::assertSame([['Client.Example', 'AAAA', []]], $service->queries);
    }

    public function test_custom_positive_answer_does_not_fall_back(): void
    {
        $response = (object) ['answer' => [(object) ['address' => '192.0.2.1']]];
        $service = new ScriptedRawLookup(['servers' => ['192.0.2.53']], [$response]);
        self::assertSame($response, $service->queryRaw('client.example'));
        self::assertSame([['client.example', 'A', ['192.0.2.53']]], $service->queries);
    }

    public function test_empty_response_is_not_confused_with_failure(): void
    {
        $response = (object) ['answer' => [], 'authority' => [(object) ['type' => 'SOA']]];
        $service = new ScriptedRawLookup(['servers' => ['192.0.2.53'], 'fallback_to_system' => false], [$response]);
        self::assertSame($response, $service->queryRaw('client.example'));
        self::assertCount(1, $service->queries);
    }

    public function test_custom_empty_answer_falls_back_to_system_including_empty_response(): void
    {
        $custom = (object) ['answer' => []];
        $system = (object) ['answer' => [], 'authority' => [(object) ['type' => 'SOA']]];
        $service = new ScriptedRawLookup(['servers' => ['192.0.2.53']], [$custom, $system]);
        self::assertSame($system, $service->queryRaw('client.example'));
        self::assertSame([['client.example', 'A', ['192.0.2.53']], ['client.example', 'A', []]], $service->queries);
    }

    public function test_custom_failure_falls_back_to_successful_system_response(): void
    {
        $system = (object) ['answer' => [(object) ['address' => '192.0.2.1']]];
        $service = new ScriptedRawLookup(['servers' => ['192.0.2.53']], [new \RuntimeException('timeout'), $system]);
        self::assertSame($system, $service->queryRaw('client.example'));
        self::assertCount(2, $service->queries);
        self::assertCount(1, $service->failures);
    }

    public function test_failure_without_fallback_returns_null_and_reports_failure(): void
    {
        $service = new ScriptedRawLookup(['servers' => ['192.0.2.53'], 'fallback_to_system' => false], [new \RuntimeException('connection failed')]);
        self::assertNull($service->queryRaw('client.example'));
        self::assertCount(1, $service->queries);
        self::assertCount(1, $service->failures);
    }

    public function test_valid_empty_custom_response_survives_system_failure(): void
    {
        $custom = (object) ['answer' => []];
        $service = new ScriptedRawLookup(['servers' => ['192.0.2.53']], [$custom, new \RuntimeException('timeout')]);
        self::assertSame($custom, $service->queryRaw('client.example'));
        self::assertCount(2, $service->queries);
    }

    public function test_both_resolver_failures_return_null_without_fabricating_a_response(): void
    {
        $service = new ScriptedRawLookup(
            ['servers' => ['192.0.2.53']],
            [new \RuntimeException('custom failed'), new \RuntimeException('system failed')]
        );

        self::assertNull($service->queryRaw('client.example'));
        self::assertCount(2, $service->queries);
        self::assertCount(2, $service->failures);
    }

    public function test_disabled_validation_and_explicit_validator_match_value_lookup_configuration(): void
    {
        $response = (object) ['answer' => []];
        $unvalidated = new ScriptedRawLookup(['domain_validator' => null], [$response]);
        self::assertSame($response, $unvalidated->queryRaw('invalid domain'));

        $validated = new ScriptedRawLookup([
            'domain_validator' => \Alyakin\DnsChecker\ExampleDomainValidator::class.'@allowsExampleDomains',
        ], [$response]);
        self::assertNull($validated->queryRaw('client.invalid'));
        self::assertSame($response, $validated->queryRaw('client.example'));
        self::assertCount(1, $validated->queries);
    }

    public function test_silent_nxdomain_and_configured_reporting(): void
    {
        foreach ([false, true] as $log) {
            $service = new ScriptedRawLookup(['log_nxdomain' => $log], [new \RuntimeException('NXDOMAIN')]);
            self::assertNull($service->queryRaw('client.example'));
            self::assertCount($log ? 1 : 0, $service->failures);
        }
    }

    public function test_typed_exceptions_keep_context_and_do_not_fall_back(): void
    {
        foreach (['NXDOMAIN' => DnsRecordNotFoundException::class, 'timeout' => DnsTimeoutException::class, 'failed' => DnsQueryFailedException::class] as $message => $exceptionClass) {
            $original = new \RuntimeException($message);
            $service = new ScriptedRawLookup(['servers' => ['192.0.2.53'], 'throw_exceptions' => true], [$original]);
            try {
                $service->queryRaw('client.example', 'TXT');
                self::fail('Expected mapped DNS exception.');
            } catch (\Alyakin\DnsChecker\Exceptions\DnsLookupException $exception) {
                self::assertInstanceOf($exceptionClass, $exception);
                self::assertSame($original, $exception->getPrevious());
                self::assertSame('client.example', $exception->domain);
                self::assertSame('TXT', $exception->type);
                self::assertSame('192.0.2.53', $exception->resolver);
                self::assertCount(1, $service->queries);
            }
        }
    }

    public function test_raw_queries_bypass_legacy_cache_and_legacy_extraction_stays_unchanged(): void
    {
        $response = (object) ['answer' => [(object) ['address' => '192.0.2.1']]];
        $service = new ScriptedRawLookup(['cache' => ['enabled' => true]], [$response, $response, $response, $response]);
        self::assertSame($response, $service->queryRaw('client.example'));
        self::assertSame($response, $service->queryRaw('client.example'));
        self::assertSame(0, $service->cacheReads);
        self::assertSame(['192.0.2.1'], $service->getRecords('client.example'));
        self::assertSame(1, $service->cacheReads);
        self::assertSame(['192.0.2.1'], $service->getRecords('client.example'));
        self::assertSame(2, $service->cacheReads);
        self::assertCount(3, $service->queries);
        self::assertSame($response, $service->queryRaw('client.example'));
        self::assertSame(2, $service->cacheReads);
        self::assertCount(4, $service->queries);
    }

    public function test_legacy_custom_lookup_still_works_but_rejects_only_raw_queries(): void
    {
        $lookup = new class implements DnsLookup
        {
            public function getRecords(string $domain, string $type = 'A'): array
            {
                return ['192.0.2.1'];
            }
        };
        $factory = new DnsCheckerFactory([], static fn (array $config): DnsLookup => $lookup);
        self::assertSame(['192.0.2.1'], $factory->getRecords('client.example'));
        self::assertSame(['192.0.2.1'], $factory->make()->query('client.example'));
        $this->expectException(\LogicException::class);
        $factory->queryRaw('client.example');
    }

    public function test_fluent_configuration_and_facade_delegate_raw_queries(): void
    {
        $response = (object) ['answer' => []];
        $lookup = new ScriptedRawLookup([], [$response, $response]);
        $captured = [];
        $factory = new DnsCheckerFactory([], static function (array $config) use ($lookup, &$captured): RawDnsLookup {
            $captured = $config;

            return $lookup;
        });
        $client = $factory->usingServer('192.0.2.53')->withTimeout(5)->withRetries(3)->fallbackToSystem(false)->throwExceptions()->logNxdomain();
        self::assertSame($response, $client->queryRaw('client.example'));
        self::assertSame(['servers' => ['192.0.2.53'], 'timeout' => 5, 'retry_count' => 3, 'fallback_to_system' => false, 'throw_exceptions' => true, 'log_nxdomain' => true], $captured);

        $previousRoot = DnsChecker::getFacadeRoot();
        try {
            DnsChecker::swap($factory);
            self::assertSame($response, DnsChecker::queryRaw('client.example', 'MX'));
        } finally {
            DnsChecker::clearResolvedInstance('dns-checker.factory');
            if ($previousRoot !== null) {
                DnsChecker::swap($previousRoot);
            }
        }
        self::assertSame([['client.example', 'A', []], ['client.example', 'MX', []]], $lookup->queries);
    }

    public function test_provider_registers_optional_raw_contract_as_concrete_service_alias(): void
    {
        $app = $this->createMock(\Illuminate\Contracts\Foundation\Application::class);
        $config = $this->createMock(\Illuminate\Contracts\Config\Repository::class);
        $config->method('get')->with('dns-checker', [])->willReturn([]);
        $service = new DnsLookupService([]);
        $app->method('make')->willReturnCallback(static function (string $abstract) use ($config, $service): object {
            return match ($abstract) {
                'config' => $config,
                DnsLookupService::class => $service,
                default => throw new \LogicException('Unexpected container resolution: '.$abstract),
            };
        });
        $bindings = [];
        $app->method('singleton')->willReturnCallback(static function (string $abstract, \Closure $factory) use (&$bindings): void {
            $bindings[$abstract] = $factory;
        });
        $aliases = [];
        $app->method('alias')->willReturnCallback(static function (string $abstract, string $alias) use (&$aliases): void {
            $aliases[$alias] = $abstract;
        });

        (new DnsCheckerServiceProvider($app))->register();

        self::assertSame(DnsLookupService::class, $aliases[RawDnsLookup::class]);
        self::assertSame(DnsLookupService::class, $aliases['dns-checker']);
        self::assertSame(DnsCheckerFactory::class, $aliases['dns-checker.factory']);
        self::assertArrayHasKey(DnsLookupService::class, $bindings);
        self::assertSame($service, $bindings[DnsLookup::class]($app));
        self::assertSame($service, $app->make($aliases[RawDnsLookup::class]));
    }
}

/** Scripted responses are consumed once; exhausting the script is a test failure. */
final class ScriptedRawLookup extends DnsLookupService
{
    public array $queries = [];

    public array $failures = [];

    public int $cacheReads = 0;

    private object $testCache;

    public function __construct(array $config, public array $queryResults)
    {
        parent::__construct($config);
        $this->testCache = new class
        {
            private array $values = [];

            public function get(string $key): ?array
            {
                return $this->values[$key] ?? null;
            }

            public function put(string $key, array $value, int $ttl): void
            {
                $this->values[$key] = $value;
            }
        };
    }

    protected function createResolver(array $nameservers)
    {
        return new class($this, $nameservers)
        {
            public function __construct(private ScriptedRawLookup $service, private array $nameservers) {}

            public function query(string $domain, string $type): object
            {
                $this->service->queries[] = [$domain, $type, $this->nameservers];
                if ($this->service->queryResults === []) {
                    throw new \LogicException('Unexpected resolver call: test script exhausted.');
                }
                $result = array_shift($this->service->queryResults);
                if ($result instanceof \Throwable) {
                    throw $result;
                }

                return $result;
            }
        };
    }

    protected function reportFailure(string $message): void
    {
        $this->failures[] = $message;
    }

    protected function getCacheRepository(): ?object
    {
        $this->cacheReads++;

        return ($this->cacheConfig['enabled'] ?? false) ? $this->testCache : null;
    }
}
