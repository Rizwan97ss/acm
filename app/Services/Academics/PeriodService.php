<?php

namespace App\Services\Academics;

use App\Models\Period;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the school's bell schedule as a whole; order in the list is the period order.
 */
class PeriodService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  list<array{id: int|null, name: string, starts_at: string, ends_at: string, is_break: bool}>  $periods
     */
    public function sync(int $schoolId, array $periods): void
    {
        DB::transaction(function () use ($schoolId, $periods): void {
            $keptIds = array_values(array_filter(array_column($periods, 'id')));
            $removed = Period::query()->whereNotIn('id', $keptIds);

            if ((clone $removed)->whereHas('timetableEntries')->exists()) {
                throw ValidationException::withMessages(['periods' => __('A period you removed is used in a timetable. Clear those lessons first.')]);
            }

            $removed->delete();

            foreach ($periods as $index => $period) {
                $model = $period['id'] !== null
                    ? Period::query()->findOrFail($period['id'])
                    : new Period(['school_id' => $schoolId]);

                $model->fill([
                    'name' => $period['name'],
                    'starts_at' => $period['starts_at'],
                    'ends_at' => $period['ends_at'],
                    'is_break' => $period['is_break'],
                    'position' => $index + 1,
                ])->save();
            }
        });

        $this->audit->log('periods.synced', null, [], ['periods' => count($periods)]);
    }
}
