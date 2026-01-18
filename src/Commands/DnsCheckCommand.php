<?php

namespace Alyakin\DnsChecker\Commands;

use Alyakin\DnsChecker\DnsLookupService;
use Illuminate\Console\Command;

class DnsCheckCommand extends Command
{
    protected $signature = 'dns:check {domain} {type=A}';

    protected $description = 'Check DNS records for a given domain';

    public function handle(DnsLookupService $dns)
    {
        $domain = $this->argument('domain');
        $type = strtoupper($this->argument('type'));

        $this->info("Querying DNS type [$type] for domain: $domain");

        $records = $dns->getRecords($domain, $type);

        if (empty($records)) {
            $this->warn('No records found.');

            return 1;
        }

        foreach ($records as $record) {
            $this->line("- $record");
        }

        return 0;
    }
}
