<?php

namespace Alyakin\DnsChecker\Contracts;

interface RawDnsLookup extends DnsLookup
{
    /**
     * Return the original resolver response without conversion or caching.
     *
     * A valid response may have an empty answer. Null means an invalid domain
     * or no response when exceptions are disabled.
     *
     * @return object|null Original native NetDNS2 v2 packet response.
     */
    public function queryRaw(string $domain, string $type = 'A'): ?object;
}
