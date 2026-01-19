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
    if (! class_exists('Net_DNS2_Exception')) {
        class Net_DNS2_Exception extends \Exception {}
    }

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
                    return \Alyakin\DnsChecker\CacheSpy::$store[$key] ?? null;
                }

                public function put(string $key, mixed $value, mixed $ttl = null): void
                {
                    \Alyakin\DnsChecker\CacheSpy::$store[$key] = $value;
                }
            };
        }
    }
}
