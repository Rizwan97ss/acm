<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\HostelType;
use App\Models\Employee;
use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Operations\HostelService;
use App\Services\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * Demo boarding for Greenfield High School: a boys' house, a girls' house and a small
 * mixed guest wing, with rooms, residents (one past stay) and this month's hostel fees.
 *
 * Run on its own with `php artisan db:seed --class=DemoHostelSeeder`, after the
 * students workbook has been imported. Running it again changes nothing.
 */
class DemoHostelSeeder extends Seeder
{
    /**
     * @var list<array{name: string, type: HostelType, fee: int, phone: string, address: string, floors: list<string>, rooms_per_floor: int, capacity: int, residents: int}>
     */
    private const array HOSTELS = [
        ['name' => 'Iqbal House', 'type' => HostelType::Boys, 'fee' => 18000, 'phone' => '+1 555 0141', 'address' => 'North campus, Block B', 'floors' => ['Ground', 'First'], 'rooms_per_floor' => 4, 'capacity' => 4, 'residents' => 18],
        ['name' => 'Fatima House', 'type' => HostelType::Girls, 'fee' => 18000, 'phone' => '+1 555 0142', 'address' => 'South campus, Block D', 'floors' => ['Ground', 'First'], 'rooms_per_floor' => 4, 'capacity' => 4, 'residents' => 16],
        ['name' => 'Guest Wing', 'type' => HostelType::Mixed, 'fee' => 9000, 'phone' => '+1 555 0143', 'address' => 'Main building, second floor', 'floors' => ['Second'], 'rooms_per_floor' => 3, 'capacity' => 2, 'residents' => 0],
    ];

    private const array BEDS = ['A', 'B', 'C', 'D'];

    public function run(CurrentSchool $currentSchool, HostelService $hostels): void
    {
        $school = School::query()->where('code', 'GHS')->first();

        if ($school === null) {
            $this->command?->warn('Demo school GHS not found. Run the DemoSeeder first.');

            return;
        }

        $currentSchool->set($school);

        if (Student::query()->whereHas('enrollments')->doesntExist()) {
            $this->command?->warn('No enrolled students yet. Import 1-students-and-parents.xlsx first.');

            return;
        }

        if (Hostel::query()->exists()) {
            $this->command?->info('Hostels already exist; nothing to do.');

            return;
        }

        $admin = User::query()->where('email', 'school.admin@example.com')->firstOrFail();
        $wardens = Employee::query()->where('status', 'active')->where('is_teaching', false)->orderBy('id')->take(3)->get();
        $today = CarbonImmutable::today();
        $placed = 0;

        foreach (self::HOSTELS as $index => $plan) {
            $hostel = Hostel::query()->create([
                'school_id' => $school->id,
                'name' => $plan['name'],
                'type' => $plan['type'],
                'warden_id' => $wardens->get($index)?->id,
                'phone' => $plan['phone'],
                'address' => $plan['address'],
                'monthly_fee' => $plan['fee'],
                'is_active' => true,
            ]);

            $rooms = collect();

            foreach ($plan['floors'] as $floor) {
                for ($room = 1; $room <= $plan['rooms_per_floor']; $room++) {
                    $rooms->push(HostelRoom::query()->create([
                        'school_id' => $school->id,
                        'hostel_id' => $hostel->id,
                        'name' => sprintf('%s-%02d', $floor[0], $room),
                        'floor' => $floor,
                        'capacity' => $plan['capacity'],
                    ]));
                }
            }

            if ($plan['residents'] === 0) {
                continue;
            }

            $gender = $plan['type'] === HostelType::Boys ? Gender::Male : Gender::Female;
            $students = Student::query()
                ->where('gender', $gender)
                ->where('status', 'active')
                ->whereHas('enrollments')
                ->whereDoesntHave('hostelStay')
                ->orderBy('id')
                ->take($plan['residents'] + 1)
                ->get();

            $slots = $rooms->flatMap(fn (HostelRoom $room) => collect(array_slice(self::BEDS, 0, $room->capacity))
                ->map(fn (string $bed): array => [$room, $bed]))->values();

            foreach ($students as $position => $student) {
                [$room, $bed] = $slots[$position % $slots->count()];

                try {
                    $stay = $hostels->allocate($room, $student, $bed, $admin, $today->startOfMonth()->subMonths(2));
                    $placed++;
                } catch (ValidationException) {
                    continue;
                }

                if ($position === $plan['residents']) {
                    $hostels->vacate($stay, $today->startOfMonth()->subDays(5));
                    $placed--;
                }
            }

            $hostels->billMonth($hostel, $today->startOfMonth(), $admin);
        }

        $this->command?->info("Hostels: 3 houses, {$placed} residents, this month’s hostel fees billed.");
    }
}
