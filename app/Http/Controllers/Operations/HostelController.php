<?php

namespace App\Http\Controllers\Operations;

use App\Enums\HostelType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Student;
use App\Services\Finance\Money;
use App\Services\Operations\HostelService;
use App\Services\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hostels, rooms, residents and monthly hostel fees.
 */
class HostelController extends Controller
{
    public function __construct(private HostelService $hostels) {}

    public function index(): Response
    {
        Gate::authorize('hostels.view');

        $hostels = Hostel::query()->with(['warden:id,first_name,last_name', 'rooms' => fn ($query) => $query->withCount('residents')])->orderBy('name')->get();

        return Inertia::render('operations/hostels/index', [
            'hostels' => $hostels->map(fn (Hostel $hostel): array => [
                ...$this->present($hostel),
                'rooms' => $hostel->rooms->count(),
                'beds' => (int) $hostel->rooms->sum('capacity'),
                'occupied' => (int) $hostel->rooms->sum('residents_count'),
            ])->values(),
            'types' => HostelType::options(),
            'wardens' => $this->wardens(),
            'can' => ['create' => request()->user()->hasPermission('hostels.create')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('hostels.create');

        $hostel = Hostel::query()->create($this->validatedHostel($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Hostel added. Add its rooms next.')]);

        return to_route('operations.hostels.show', $hostel);
    }

    public function show(Request $request, Hostel $hostel): Response
    {
        Gate::authorize('hostels.view');
        $hostel->load(['warden:id,first_name,last_name,phone', 'rooms.residents.student:id,first_name,last_name,admission_no,gender']);

        return Inertia::render('operations/hostels/show', [
            'hostel' => $this->present($hostel),
            'rooms' => $hostel->rooms->map(fn (HostelRoom $room): array => [
                'id' => $room->id,
                'name' => $room->name,
                'floor' => $room->floor,
                'capacity' => $room->capacity,
                'notes' => $room->notes,
                'residents' => $room->residents->map(fn (HostelAllocation $allocation): array => [
                    'id' => $allocation->id,
                    'student' => $allocation->student->full_name,
                    'admission_no' => $allocation->student->admission_no,
                    'bed_label' => $allocation->bed_label,
                    'starts_on' => $allocation->starts_on->toDateString(),
                ])->values(),
            ])->values(),
            'types' => HostelType::options(),
            'wardens' => $this->wardens(),
            'can' => [
                'edit' => $request->user()->hasPermission('hostels.edit'),
                'delete' => $request->user()->hasPermission('hostels.delete'),
                'bill' => $request->user()->hasPermission('invoices.create'),
            ],
        ]);
    }

    public function update(Request $request, Hostel $hostel): RedirectResponse
    {
        Gate::authorize('hostels.edit');

        $hostel->update($this->validatedHostel($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Hostel saved.')]);

        return back();
    }

    public function destroy(Hostel $hostel): RedirectResponse
    {
        Gate::authorize('hostels.delete');

        if (HostelAllocation::query()->whereIn('hostel_room_id', $hostel->rooms()->select('id'))->whereNull('ends_on')->exists()) {
            throw ValidationException::withMessages(['hostel' => __('Move or vacate every resident before deleting this hostel.')]);
        }

        $hostel->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Hostel deleted.')]);

        return to_route('operations.hostels.index');
    }

    public function storeRoom(Request $request, Hostel $hostel): RedirectResponse
    {
        Gate::authorize('hostels.edit');

        $hostel->rooms()->create([...$this->validatedRoom($request, $hostel), 'school_id' => $hostel->school_id]);

        return back();
    }

    public function updateRoom(Request $request, HostelRoom $room): RedirectResponse
    {
        Gate::authorize('hostels.edit');

        $validated = $this->validatedRoom($request, $room->hostel, $room);

        if ($validated['capacity'] < $room->residents()->count()) {
            throw ValidationException::withMessages(['capacity' => __('The room has more residents than that.')]);
        }

        $room->update($validated);

        return back();
    }

    public function destroyRoom(HostelRoom $room): RedirectResponse
    {
        Gate::authorize('hostels.edit');

        if ($room->residents()->exists()) {
            throw ValidationException::withMessages(['room' => __('Move or vacate the residents first.')]);
        }

        $room->delete();

        return back();
    }

    public function allocate(Request $request, HostelRoom $room): RedirectResponse
    {
        Gate::authorize('hostels.edit');

        $validated = $request->validate([
            'admission_no' => ['required', 'string', 'max:32'],
            'bed_label' => ['nullable', 'string', 'max:10'],
            'starts_on' => ['nullable', 'date'],
        ]);

        $student = Student::query()->where('admission_no', trim($validated['admission_no']))->first()
            ?? throw ValidationException::withMessages(['admission_no' => __('No student has that admission number.')]);

        $this->hostels->allocate($room, $student, $validated['bed_label'] ?? null, $request->user(), isset($validated['starts_on']) ? CarbonImmutable::parse($validated['starts_on']) : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name moved into room :room.', ['name' => $student->full_name, 'room' => $room->name])]);

        return back();
    }

    public function move(Request $request, HostelAllocation $allocation): RedirectResponse
    {
        Gate::authorize('hostels.edit');

        $validated = $request->validate([
            'hostel_room_id' => ['required', 'integer', Rule::exists('hostel_rooms', 'id')->where('school_id', $allocation->school_id)],
            'bed_label' => ['nullable', 'string', 'max:10'],
        ]);

        $this->hostels->move($allocation, HostelRoom::query()->whereKey((int) $validated['hostel_room_id'])->firstOrFail(), $validated['bed_label'] ?? null, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Resident moved.')]);

        return back();
    }

    public function vacate(HostelAllocation $allocation): RedirectResponse
    {
        Gate::authorize('hostels.edit');

        $this->hostels->vacate($allocation);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bed vacated.')]);

        return back();
    }

    public function bill(Request $request, Hostel $hostel): RedirectResponse
    {
        Gate::authorize('invoices.create');

        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];
        $result = $this->hostels->billMonth($hostel, CarbonImmutable::createFromFormat('Y-m-d', "{$month}-01")->startOfDay(), $request->user());

        Log::info('Hostel fees billed.', ['hostel_id' => $hostel->id, 'month' => $month, ...$result]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':created invoices created; :skipped already billed.', $result)]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedHostel(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(HostelType::class)],
            'warden_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('school_id', (int) app(CurrentSchool::class)->id())],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'monthly_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
        ]);

        return [...$validated, 'monthly_fee' => Money::toCents($validated['monthly_fee'])];
    }

    /**
     * @return array{name: string, floor: string|null, capacity: int, notes: string|null}
     */
    private function validatedRoom(Request $request, Hostel $hostel, ?HostelRoom $room = null): array
    {
        /** @var array{name: string, floor: string|null, capacity: int, notes: string|null} */
        return $request->validate([
            'name' => ['required', 'string', 'max:30', Rule::unique('hostel_rooms', 'name')->where('hostel_id', $hostel->id)->ignore($room?->id)],
            'floor' => ['nullable', 'string', 'max:20'],
            'capacity' => ['required', 'integer', 'min:1', 'max:30'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Hostel $hostel): array
    {
        return [
            'id' => $hostel->id,
            'name' => $hostel->name,
            'type' => $hostel->type->value,
            'type_label' => $hostel->type->label(),
            'warden_id' => $hostel->warden_id,
            'warden' => $hostel->warden?->full_name,
            'phone' => $hostel->phone,
            'address' => $hostel->address,
            'monthly_fee' => Money::toMajor($hostel->monthly_fee),
            'is_active' => $hostel->is_active,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function wardens(): array
    {
        return array_values(Employee::query()->where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
            ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->full_name])->all());
    }
}
