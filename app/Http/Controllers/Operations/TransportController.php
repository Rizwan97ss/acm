<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Services\People\PortalScope;
use App\Services\Tenancy\CurrentSchool;
use App\Services\Transport\TransportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Routes, stops and who rides where. Students and guardians only see their own seat.
 */
class TransportController extends Controller
{
    public function __construct(
        private TransportService $transport,
        private PortalScope $portalScope,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('transport.view');
        $user = $request->user();
        $isPortal = $this->portalScope->isPortalUser($user);

        return Inertia::render('operations/transport/index', [
            'routes' => $isPortal ? [] : TransportRoute::query()
                ->with('stops')
                ->withCount('assignments')
                ->orderBy('name')
                ->get()
                ->map(fn (TransportRoute $route): array => [
                    ...$route->only(['id', 'name', 'vehicle_no', 'driver_name', 'driver_phone', 'capacity', 'is_active']),
                    'riders' => $route->assignments_count,
                    'stops' => $route->stops->map(fn (TransportStop $stop): array => $this->presentStop($stop))->values(),
                ])->values(),
            'mine' => $isPortal ? TransportAssignment::query()
                ->whereIn('student_id', $this->portalScope->studentIds($user))
                ->with(['student:id,first_name,last_name', 'route', 'stop'])
                ->get()
                ->map(fn (TransportAssignment $assignment): array => [
                    'student' => "{$assignment->student->first_name} {$assignment->student->last_name}",
                    'route' => $assignment->route->name,
                    'vehicle_no' => $assignment->route->vehicle_no,
                    'driver_name' => $assignment->route->driver_name,
                    'driver_phone' => $assignment->route->driver_phone,
                    'stop' => $assignment->stop ? $this->presentStop($assignment->stop) : null,
                ])->values() : [],
            'can' => [
                'create' => $user->can('transport.create'),
                'edit' => $user->can('transport.edit'),
                'delete' => $user->can('transport.delete'),
            ],
        ]);
    }

    public function show(Request $request, TransportRoute $route): Response
    {
        Gate::authorize('transport.view');
        abort_if($this->portalScope->isPortalUser($request->user()), 403);

        $route->load('stops');

        return Inertia::render('operations/transport/show', [
            'route' => [
                ...$route->only(['id', 'name', 'vehicle_no', 'driver_name', 'driver_phone', 'capacity', 'is_active']),
                'stops' => $route->stops->map(fn (TransportStop $stop): array => $this->presentStop($stop))->values(),
            ],
            'riders' => $route->assignments()
                ->with(['student:id,admission_no,first_name,last_name', 'student.currentEnrollment.section.schoolClass', 'stop'])
                ->get()
                ->sortBy(fn (TransportAssignment $assignment): string => "{$assignment->stop?->position}-{$assignment->student->last_name}")
                ->map(fn (TransportAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'student' => "{$assignment->student->first_name} {$assignment->student->last_name}",
                    'admission_no' => $assignment->student->admission_no,
                    'class' => trim("{$assignment->student->currentEnrollment?->section?->schoolClass?->name} {$assignment->student->currentEnrollment?->section?->name}") ?: null,
                    'stop' => $assignment->stop?->name,
                ])->values(),
            'can' => ['edit' => $request->user()->can('transport.edit')],
        ]);
    }

    public function store(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        Gate::authorize('transport.create');

        $validated = $this->validateRoute($request, $currentSchool, null);

        DB::transaction(function () use ($validated): void {
            $route = TransportRoute::query()->create(collect($validated)->except('stops')->all());
            $this->transport->syncStops($route, $validated['stops'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Route added.')]);

        return back();
    }

    public function update(Request $request, TransportRoute $route, CurrentSchool $currentSchool): RedirectResponse
    {
        Gate::authorize('transport.edit');

        $validated = $this->validateRoute($request, $currentSchool, $route);

        if ($validated['capacity'] < $route->assignments()->count()) {
            throw ValidationException::withMessages(['capacity' => __('More students ride this route than the new capacity.')]);
        }

        DB::transaction(function () use ($route, $validated): void {
            $route->update(collect($validated)->except('stops')->all());
            $this->transport->syncStops($route, $validated['stops'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Route updated.')]);

        return back();
    }

    public function destroy(TransportRoute $route): RedirectResponse
    {
        Gate::authorize('transport.delete');

        if ($route->assignments()->exists()) {
            throw ValidationException::withMessages(['route' => __('Move or remove the riders first.')]);
        }

        $route->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Route deleted.')]);

        return to_route('operations.transport.index');
    }

    public function assign(Request $request, TransportRoute $route): RedirectResponse
    {
        Gate::authorize('transport.edit');

        $validated = $request->validate([
            'admission_no' => ['required', 'string', 'max:32'],
            'transport_stop_id' => ['nullable', 'integer'],
        ]);

        $student = Student::query()->where('admission_no', trim($validated['admission_no']))->first()
            ?? throw ValidationException::withMessages(['admission_no' => __('No student has that admission number.')]);

        $this->transport->assign($student, $route, isset($validated['transport_stop_id']) ? (int) $validated['transport_stop_id'] : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added to the route.', ['name' => $student->first_name])]);

        return back();
    }

    public function unassign(TransportAssignment $assignment): RedirectResponse
    {
        Gate::authorize('transport.edit');

        $assignment->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Student removed from the route.')]);

        return back();
    }

    /**
     * @return array{id: int, name: string, pickup_time: string|null, drop_time: string|null}
     */
    private function presentStop(TransportStop $stop): array
    {
        return [
            'id' => $stop->id,
            'name' => $stop->name,
            'pickup_time' => $stop->pickup_time ? substr($stop->pickup_time, 0, 5) : null,
            'drop_time' => $stop->drop_time ? substr($stop->drop_time, 0, 5) : null,
        ];
    }

    /**
     * @return array{name: string, vehicle_no: string|null, driver_name: string|null, driver_phone: string|null, capacity: int, is_active: bool, stops?: list<array{id?: int|null, name: string, pickup_time?: string|null, drop_time?: string|null}>}
     */
    private function validateRoute(Request $request, CurrentSchool $currentSchool, ?TransportRoute $route): array
    {
        /** @var array{name: string, vehicle_no: string|null, driver_name: string|null, driver_phone: string|null, capacity: int, is_active: bool, stops?: list<array{id?: int|null, name: string, pickup_time?: string|null, drop_time?: string|null}>} */
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('transport_routes')->where('school_id', $currentSchool->id())->ignore($route?->id)],
            'vehicle_no' => ['nullable', 'string', 'max:30'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'driver_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'is_active' => ['boolean'],
            'stops' => ['nullable', 'array', 'max:50'],
            'stops.*.id' => ['nullable', 'integer'],
            'stops.*.name' => ['required', 'string', 'max:100'],
            'stops.*.pickup_time' => ['nullable', 'date_format:H:i'],
            'stops.*.drop_time' => ['nullable', 'date_format:H:i'],
        ]);
    }
}
