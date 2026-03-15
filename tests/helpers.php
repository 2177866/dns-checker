<?php

namespace Alyakin\DnsChecker {
    final class ReportSpy
    {
        /** @var array<int, mixed> */
        public static array $calls = [];

        public static function reset(): void
        {
            self::$calls = [];
        }
    }

    function report(mixed $message): void
    {
        ReportSpy::$calls[] = $message;
    }

    final class CacheSpy
    {
        /** @var array<string, mixed> */
        public static array $store = [];

        public static ?string $selectedStore = null;

        /** @var null|\Closure */
        public static $repositoryFactory = null;

        public static function reset(): void
        {
            self::$store = [];
            self::$selectedStore = null;
            self::$repositoryFactory = null;
        }
    }

    final class ExampleDomainValidator
    {
        public static function allowsExampleDomains(string $domain): bool
        {
            return str_ends_with($domain, '.example');
        }
    }
}

namespace {
    use Alyakin\DnsChecker\CacheSpy;

    if (! function_exists('cache')) {
        function cache(): object
        {
            if (CacheSpy::$repositoryFactory instanceof Closure) {
                return (CacheSpy::$repositoryFactory)();
            }

            return new class
            {
                public function store(?string $name): object
                {
                    CacheSpy::$selectedStore = $name;

                    return $this;
                }

                public function get(string $key): mixed
                {
                    return CacheSpy::$store[$key] ?? null;
                }

                public function put(string $key, mixed $value, mixed $ttl = null): void
                {
                    CacheSpy::$store[$key] = $value;
                }
            };
        }
    }
}
