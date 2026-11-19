<?php

namespace App\Services\Operations;

use App\Enums\Gender;
use App\Enums\HostelType;
use App\Models\AcademicYear;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\BillingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Allocating students to hostel rooms and billing hostel fees.
 *
 * A room never holds more residents than its capacity (checked under a row lock), a student
 * has one open stay at a time, boys' and girls' hostels only take matching students, and a
 * monthly fee run never bills the same student twice for the same month.
 */
class HostelService
{
    public function __construct(private BillingService $billing) {}

    public function allocate(HostelRoom $room, Student $student, ?string $bed, User $actor, ?CarbonImmutable $from = null): HostelAllocation
    {
        $room->loadMissing('hostel');
        $hostel = $room->hostel;

        if (! $hostel->is_active) {
            throw ValidationException::withMessages(['hostel_room_id' => __('This hostel is closed.')]);
        }

        if (($hostel->type === HostelType::Boys && $student->gender !== Gender::Male) || ($hostel->type === HostelType::Girls && $student->gender !== Gender::Female)) {
            throw ValidationException::withMessages(['student_id' => __(':name cannot stay in a :type hostel.', ['name' => $student->full_name, 'type' => strtolower($hostel->type->label())])]);
        }

        return DB::transaction(function () use ($room, $student, $bed, $actor, $from): HostelAllocation {
            $room = HostelRoom::query()->lockForUpdate()->findOrFail($room->id);

            if (HostelAllocation::query()->where('student_id', $student->id)->whereNull('ends_on')->exists()) {
                throw ValidationException::withMessages(['student_id' => __(':name already has a hostel bed. Move or vacate it first.', ['name' => $student->full_name])]);
            }

            $occupied = HostelAllocation::query()->where('hostel_room_id', $room->id)->whereNull('ends_on')->lockForUpdate()->get();

            if ($occupied->count() >= $room->capacity) {
                throw ValidationException::withMessages(['hostel_room_id' => __('Room :room is full.', ['room' => $room->name])]);
            }

            if ($bed !== null && $bed !== '' && $occupied->contains('bed_label', $bed)) {
                throw ValidationException::withMessages(['bed_label' => __('Bed :bed is taken.', ['bed' => $bed])]);
            }

            return HostelAllocation::query()->create([
                'school_id' => $room->school_id,
                'hostel_room_id' => $room->id,
                'student_id' => $student->id,
                'bed_label' => $bed ?: null,
                'starts_on' => ($from ?? CarbonImmutable::today())->toDateString(),
                'created_by' => $actor->id,
            ]);
        });
    }

    public function vacate(HostelAllocation $allocation, ?CarbonImmutable $on = null): void
    {
        if ($allocation->ends_on !== null) {
            throw ValidationException::withMessages(['allocation' => __('This stay has already ended.')]);
        }

        $allocation->update(['ends_on' => ($on ?? CarbonImmutable::today())->toDateString()]);
    }

    /**
     * Move a resident to another room: the current stay ends today and a new one starts.
     */
    public function move(HostelAllocation $allocation, HostelRoom $room, ?string $bed, User $actor): HostelAllocation
    {
        return DB::transaction(function () use ($allocation, $room, $bed, $actor): HostelAllocation {
            $this->vacate($allocation);

            return $this->allocate($room, $allocation->student, $bed, $actor);
        });
    }

    /**
     * Bill this month's fee to every current resident who has not been billed for it yet.
     *
     * @return array{created: int, skipped: int}
     */
    public function billMonth(Hostel $hostel, CarbonImmutable $month, User $actor): array
    {
        if ($hostel->monthly_fee <= 0) {
            throw ValidationException::withMessages(['month' => __('Set a monthly fee for this hostel first.')]);
        }

        $year = AcademicYear::query()->current()->first() ?? throw ValidationException::withMessages(['month' => __('Set a current academic year first.')]);
        $title = __('Hostel fee, :hostel, :month', ['hostel' => $hostel->name, 'month' => $month->format('F Y')]);

        $residents = HostelAllocation::query()
            ->whereIn('hostel_room_id', $hostel->rooms()->select('id'))
            ->whereNull('ends_on')
            ->with('room:id,name')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($residents as $allocation) {
            if (Invoice::query()->where('student_id', $allocation->student_id)->where('title', $title)->exists()) {
                $skipped++;

                continue;
            }

            $this->billing->createInvoice($actor, $year, $allocation->student_id, $title, $month->startOfMonth(), $month->startOfMonth()->addDays(14), [[
                'fee_item_id' => null,
                'description' => __(':hostel, room :room', ['hostel' => $hostel->name, 'room' => $allocation->room->name]),
                'amount' => $hostel->monthly_fee,
                'discount' => 0,
            ]]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
