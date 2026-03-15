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

        public static function reset(): void
        {
            self::$store = [];
        }
    }
}

namespace {
    use Alyakin\DnsChecker\CacheSpy;

    if (! function_exists('cache')) {
        function cache(): object
        {
            return new class
            {
                public function store(?string $name): object
                {
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
