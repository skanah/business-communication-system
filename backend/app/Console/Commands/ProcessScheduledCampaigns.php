<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessScheduledCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled campaigns that are ready to execute';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\CampaignScheduler $scheduler)
    {
        $this->info('Processing scheduled campaigns...');

        $results = $scheduler->processScheduledCampaigns();

        $this->info("Processed {$results['processed']} campaigns");
        $this->info("Encountered {$results['errors']} errors");
        $this->info("Found {$results['total_scheduled']} scheduled campaigns");

        $this->info('Campaign processing completed.');

        return 0;
    }
}
