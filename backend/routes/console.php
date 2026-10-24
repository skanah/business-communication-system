<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\ProcessScheduledCampaigns;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The command is already registered via the artisan command discovery
// Laravel automatically discovers commands in the app/Console/Commands directory
