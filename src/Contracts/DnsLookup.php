<?php

namespace Alyakin\DnsChecker\Contracts;

interface DnsLookup
{
    /**
     * @return array<int, string>
     */
    public function getRecords(string $domain, string $type = 'A'): array;
}
