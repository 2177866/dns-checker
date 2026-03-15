<?php

namespace Alyakin\DnsChecker\Tests\Unit;

use Alyakin\DnsChecker\Contracts\DnsLookup;
use Alyakin\DnsChecker\DnsCheckerClient;
use Alyakin\DnsChecker\DnsCheckerFactory;
use Alyakin\DnsChecker\DnsLookupService;
use Alyakin\DnsChecker\DomainValidator;
use Alyakin\DnsChecker\Tests\TestCase;
use Closure;

final class DnsCheckerClientAndFactoryTest extends TestCase
{
    public function testDnsLookupServiceImplementsTheDnsLookupContract(): void
    {
        $this->assertInstanceOf(DnsLookup::class, new DnsLookupService([]));
    }

    public function testItSupportsFluentConfigOverridesViaTheFactoryClient(): void
    {
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

        $this->assertCount(1, $received);
        $this->assertEquals([
            'servers' => ['8.8.8.8'],
            'timeout' => 5,
            'retry_count' => 3,
            'fallback_to_system' => false,
        ], $received[0]);
    }

    public function testItSupportsGetConfigSetConfigAndResetConfigOnTheFluentClient(): void
    {
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

        $this->assertEquals(['timeout' => 2, 'retry_count' => 1], $client->getConfig());

        $client->setConfig(['servers' => ['8.8.8.8'], 'timeout' => 5]);
        $this->assertSame(['servers' => ['8.8.8.8'], 'timeout' => 5], $client->getConfig());

        $client->query('example.com', 'A');
        $this->assertCount(1, $received);
        $this->assertSame(['servers' => ['8.8.8.8'], 'timeout' => 5], $received[0]);

        $client->usingServer('1.1.1.1')->withTimeout(10);
        $client->resetConfig()->query('example.com', 'A');

        $this->assertCount(2, $received);
        $this->assertEquals(['timeout' => 2, 'retry_count' => 1], $received[1]);
    }

    public function testItSupportsFluentConfigMutationAndShortcutQueryMethodsOnDnsCheckerClient(): void
    {
        $receivedConfigs = [];
        $receivedQueries = [];

        $client = new DnsCheckerClient(
            ['timeout' => 2],
            function (array $config) use (&$receivedConfigs, &$receivedQueries): DnsLookup {
                $receivedConfigs[] = $config;

                return new class(function (string $domain, string $type) use (&$receivedQueries): void {
                    $receivedQueries[] = [$domain, $type];
                }) implements DnsLookup
                {
                    public function __construct(private Closure $recordQuery) {}

                    public function getRecords(string $domain, string $type = 'A'): array
                    {
                        ($this->recordQuery)($domain, $type);

                        return ["$type:$domain"];
                    }
                };
            },
            ['timeout' => 2],
        );

        $client
            ->usingServers(['8.8.8.8', '1.1.1.1'])
            ->addServer('9.9.9.9')
            ->withTimeout(5)
            ->withRetries(3)
            ->fallbackToSystem(false)
            ->logNxdomain()
            ->throwExceptions()
            ->validateDomain(DomainValidator::class.'@validate');

        $this->assertEquals([
            'servers' => ['8.8.8.8', '1.1.1.1', '9.9.9.9'],
            'timeout' => 5,
            'retry_count' => 3,
            'fallback_to_system' => false,
            'log_nxdomain' => true,
            'throw_exceptions' => true,
            'domain_validator' => DomainValidator::class.'@validate',
        ], $client->getConfig());

        $this->assertSame([], $client->clearServers()->getConfig()['servers']);
        $client->withoutDomainValidation();

        $this->assertSame(['A:example.com'], $client->getRecords('example.com', 'A'));
        $this->assertSame(['A:example.com'], $client->a('example.com'));
        $this->assertSame(['AAAA:example.com'], $client->aaaa('example.com'));
        $this->assertSame(['MX:example.com'], $client->mx('example.com'));
        $this->assertSame(['NS:example.com'], $client->ns('example.com'));
        $this->assertSame(['TXT:example.com'], $client->txt('example.com'));
        $this->assertSame(['CNAME:example.com'], $client->cname('example.com'));

        $this->assertCount(7, $receivedConfigs);
        $this->assertCount(7, $receivedQueries);

        $client->setConfig(['servers' => ['8.8.4.4']]);
        $this->assertSame(['servers' => ['8.8.4.4']], $client->getConfig());

        $client->resetConfig();
        $this->assertSame(['timeout' => 2], $client->getConfig());
    }

    public function testItExposesTheSameFluentApiOnDnsCheckerFactory(): void
    {
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

        $this->assertInstanceOf(DnsCheckerClient::class, $factory->make());
        $this->assertInstanceOf(DnsCheckerClient::class, $factory->usingServer('8.8.8.8'));
        $this->assertInstanceOf(DnsCheckerClient::class, $factory->usingServers(['1.1.1.1']));
        $this->assertInstanceOf(DnsCheckerClient::class, $factory->withTimeout(5));
        $this->assertInstanceOf(DnsCheckerClient::class, $factory->withRetries(3));
        $this->assertInstanceOf(DnsCheckerClient::class, $factory->setRetries(4));
        $this->assertInstanceOf(DnsCheckerClient::class, $factory->fallbackToSystem(false));
        $this->assertInstanceOf(DnsCheckerClient::class, $factory->logNxdomain());
        $this->assertInstanceOf(DnsCheckerClient::class, $factory->throwExceptions());
        $this->assertInstanceOf(DnsCheckerClient::class, $factory->validateDomain(DomainValidator::class.'@validate'));
        $this->assertInstanceOf(DnsCheckerClient::class, $factory->withoutDomainValidation());

        $this->assertSame(['TXT:example.com'], $factory->query('example.com', 'TXT'));
        $this->assertSame(['A:example.com'], $factory->getRecords('example.com', 'A'));
        $this->assertCount(2, $receivedConfigs);
    }
}
