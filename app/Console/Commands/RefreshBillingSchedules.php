<?php

namespace App\Console\Commands;

use App\Services\BillingSchedules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshBillingSchedules extends Command
{
    protected $signature = 'billing:refresh-schedules';

    protected $description = 'Generate explicit customer billing expectations through next month';

    public function handle(BillingSchedules $schedules): int
    {
        DB::table('organizations')->where('is_active', true)->orderBy('id')->eachById(function ($organization) use ($schedules) {
            $schedules->refreshOrganization($organization->id);
        });
        $this->info('Configured billing expectations refreshed. Unscheduled accounts were not changed.');

        return self::SUCCESS;
    }
}
