<?php

return [
    'servers' => [
        '8.8.8.8', // Google's Public DNS
        '1.1.1.1', // Cloudflare's DNS
        '9.9.9.9', // Quad9 DNS
    ],
    // When custom `servers` are set and the lookup returns empty, try system resolver as a fallback.
    // Default: true (backward compatible).
    'fallback_to_system' => true,

    // Log NXDOMAIN via report(). Other DNS errors are still reported.
    // Default: false.
    'log_nxdomain' => false,

    // Domain validator. Can be:
    // - null: disable validation (domain is prepared by the app)
    // - "Class@method": static method (Laravel-friendly; works with config:cache)
    'domain_validator' => \Alyakin\DnsChecker\DomainValidator::class.'@validate',

    'timeout' => 2,
    'retry_count' => 1,
];
