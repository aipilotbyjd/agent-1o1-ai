<?php

namespace App\Console\Commands\Assistant;

use App\Services\Assistant\Briefings\BriefingScheduler;
use Illuminate\Console\Command;

class RunDueBriefingsCommand extends Command
{
    protected $signature = 'assistant:run-due-briefings';

    protected $description = "Start every personal assistant report that is due in its owner's timezone.";

    public function handle(BriefingScheduler $scheduler): int
    {
        $this->info("Started {$scheduler->startDue()} report(s).");

        return self::SUCCESS;
    }
}
