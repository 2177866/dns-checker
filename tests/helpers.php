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
}

namespace {
    if (! class_exists('Net_DNS2_Exception')) {
        class Net_DNS2_Exception extends \Exception {}
    }
}
