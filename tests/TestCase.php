<?php

namespace Alyakin\DnsChecker\Tests;

use Alyakin\DnsChecker\CacheSpy;
use Alyakin\DnsChecker\ReportSpy;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ReportSpy::reset();
        CacheSpy::reset();
    }
}
