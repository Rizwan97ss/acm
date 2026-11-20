<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\Academics\Exams\OnlineExamService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Console\Command;

/**
 * Runs every minute (see routes/console.php): hands in online exam attempts whose time
 * has run out, so a student closing the browser never leaves an exam open. Each school
 * is processed in its own tenant context.
 */
class CloseOverdueExamAttempts extends Command
{
    protected $signature = 'exams:close-overdue';

    protected $description = 'Hand in online exam attempts whose time has run out';

    public function handle(CurrentSchool $currentSchool, OnlineExamService $exams): int
    {
        $closed = 0;

        foreach (School::query()->where('is_active', true)->get() as $school) {
            $currentSchool->set($school);
            $closed += $exams->closeOverdue();
        }

        $currentSchool->set(null);

        $this->info("Handed in {$closed} overdue exam attempts.");

        return self::SUCCESS;
    }
}
