<?php

namespace Alyakin\DnsChecker\Exceptions;

class DnsLookupException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $domain,
        public readonly string $type,
        public readonly string $resolver,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
