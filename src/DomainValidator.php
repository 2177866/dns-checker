<?php

namespace Alyakin\DnsChecker;

final class DomainValidator
{
    public static function validate(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
