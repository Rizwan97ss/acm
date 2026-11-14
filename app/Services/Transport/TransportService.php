<?php

namespace App\Services\Transport;

use App\Models\Student;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransportService
{
    /**
     * Seats the student on the route (moving them from any other route). The route row
     * is locked so concurrent assignments cannot exceed its capacity.
     */
    public function assign(Student $student, TransportRoute $route, ?int $stopId): TransportAssignment
    {
        return DB::transaction(function () use ($student, $route, $stopId): TransportAssignment {
            $route = TransportRoute::query()->lockForUpdate()->findOrFail($route->id);

            if (! $route->is_active) {
                throw ValidationException::withMessages(['transport_route_id' => __('This route is not running.')]);
            }

            if ($stopId !== null && ! TransportStop::query()->where('transport_route_id', $route->id)->whereKey($stopId)->exists()) {
                throw ValidationException::withMessages(['transport_stop_id' => __('Choose a stop on this route.')]);
            }

            $riders = $route->assignments()->where('student_id', '!=', $student->id)->count();

            if ($riders >= $route->capacity) {
                throw ValidationException::withMessages(['student' => __('This route is full (:count seats).', ['count' => $route->capacity])]);
            }

            return TransportAssignment::query()->updateOrCreate(
                ['student_id' => $student->id],
                ['school_id' => $route->school_id, 'transport_route_id' => $route->id, 'transport_stop_id' => $stopId],
            );
        });
    }

    /**
     * Replaces the route's stops with the given ordered list, keeping ids that still exist
     * so students' chosen stops survive an edit.
     *
     * @param  list<array{id?: int|null, name: string, pickup_time?: string|null, drop_time?: string|null}>  $stops
     */
    public function syncStops(TransportRoute $route, array $stops): void
    {
        DB::transaction(function () use ($route, $stops): void {
            $keep = [];

            foreach ($stops as $position => $stop) {
                $attributes = ['name' => $stop['name'], 'pickup_time' => $stop['pickup_time'] ?? null, 'drop_time' => $stop['drop_time'] ?? null, 'position' => $position];
                $existing = isset($stop['id']) ? $route->stops()->whereKey($stop['id'])->first() : null;

                if ($existing !== null) {
                    $existing->update($attributes);
                    $keep[] = $existing->id;
                } else {
                    $keep[] = $route->stops()->create($attributes)->id;
                }
            }

            $route->stops()->whereNotIn('id', $keep)->delete();
        });
    }
}
