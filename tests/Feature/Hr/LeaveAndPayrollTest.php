<?php

use App\Enums\LeaveStatus;
use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\School;
use App\Models\User;
use App\Services\Finance\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * @return array{school: School, staff: User, employee: Employee, hr: User}
 */
function hrSchool(): array
{
    $school = School::factory()->create();
    $staff = userWithPermissions(['leaves.view', 'leaves.create'], $school);
    $employee = Employee::factory()->teaching()->create(['school_id' => $school->id, 'user_id' => $staff->id, 'joined_on' => '2020-01-01']);
    $hr = userWithPermissions(['leaves.view', 'leaves.approve', 'payroll.view', 'payroll.create', 'payroll.edit', 'payroll.approve'], $school);

    return compact('school', 'staff', 'employee', 'hr');
}

test('leave counts school days and rejects overlaps and requests beyond the balance', function () {
    $this->travelTo('2030-03-01');
    $setup = hrSchool();
    $type = LeaveType::query()->create(['school_id' => $setup['school']->id, 'name' => 'Casual', 'days_per_year' => 6, 'is_paid' => true]);
    $request = fn (string $from, string $to) => $this->actingAs($setup['staff'])->post(route('hr.leave.store'), [
        'leave_type_id' => $type->id, 'starts_on' => $from, 'ends_on' => $to, 'reason' => 'Family',
    ]);

    $request('2030-03-08', '2030-03-12')->assertSessionHasNoErrors();
    $request('2030-03-11', '2030-03-13')->assertSessionHasErrors('starts_on');
    $request('2030-03-18', '2030-03-22')->assertSessionHasErrors('ends_on');

    expect(LeaveRequest::query()->withoutGlobalScopes()->sole())->days->toBe(3)->status->toBe(LeaveStatus::Pending);
});

test('staff cannot approve their own leave; HR can', function () {
    $this->travelTo('2030-03-01');
    $setup = hrSchool();
    $type = LeaveType::query()->create(['school_id' => $setup['school']->id, 'name' => 'Annual', 'days_per_year' => 14, 'is_paid' => true]);
    $hrEmployee = Employee::factory()->create(['school_id' => $setup['school']->id, 'user_id' => $setup['hr']->id]);
    $own = LeaveRequest::query()->create(['school_id' => $setup['school']->id, 'employee_id' => $hrEmployee->id, 'leave_type_id' => $type->id, 'starts_on' => '2030-03-04', 'ends_on' => '2030-03-04', 'days' => 1, 'reason' => 'x']);
    $theirs = LeaveRequest::query()->create(['school_id' => $setup['school']->id, 'employee_id' => $setup['employee']->id, 'leave_type_id' => $type->id, 'starts_on' => '2030-03-04', 'ends_on' => '2030-03-04', 'days' => 1, 'reason' => 'x']);
    LeaveRequest::query()->withoutGlobalScopes()->update(['status' => 'pending']);

    $this->actingAs($setup['hr'])->post(route('hr.leave.review', $own), ['decision' => 'approved'])->assertSessionHasErrors('decision');
    $this->actingAs($setup['hr'])->post(route('hr.leave.review', $theirs), ['decision' => 'approved'])->assertSessionHasNoErrors();
    $this->actingAs($setup['staff'])->post(route('hr.leave.review', $theirs), ['decision' => 'rejected'])->assertForbidden();

    expect($theirs->fresh()->status)->toBe(LeaveStatus::Approved)
        ->and($own->fresh()->status)->toBe(LeaveStatus::Pending);
});

test('payroll deducts unpaid leave, encrypts payslips and posts a balanced entry when paid', function () {
    $this->travelTo('2030-03-01');
    $setup = hrSchool();
    $this->actingAs($setup['hr'])->put(route('hr.payroll.salaries.update', $setup['employee']), [
        'basic' => '2200',
        'allowances' => [['name' => 'Housing', 'amount' => '300']],
        'deductions' => [['name' => 'Tax', 'amount' => '200']],
    ])->assertSessionHasNoErrors();
    $unpaid = LeaveType::query()->create(['school_id' => $setup['school']->id, 'name' => 'Unpaid', 'days_per_year' => 30, 'is_paid' => false]);
    $leave = LeaveRequest::query()->create(['school_id' => $setup['school']->id, 'employee_id' => $setup['employee']->id, 'leave_type_id' => $unpaid->id, 'starts_on' => '2030-03-04', 'ends_on' => '2030-03-05', 'days' => 2, 'reason' => 'x']);
    $leave->forceFill(['status' => LeaveStatus::Approved])->save();

    $this->actingAs($setup['hr'])->post(route('hr.payroll.store'), ['period' => '2030-03'])->assertSessionHasNoErrors();

    $payslip = Payslip::query()->withoutGlobalScopes()->sole();
    // March 2030 has 21 weekdays: 2 unpaid days = 2200 / 21 * 2 = 209.52
    expect((int) $payslip->gross)->toBe(250000)
        ->and((int) $payslip->deductions)->toBe(20000 + 20952)
        ->and((int) $payslip->net)->toBe(250000 - 40952)
        ->and($payslip->unpaid_leave_days)->toBe(2)
        ->and(DB::table('payslips')->value('net'))->not->toContain('209048');

    $run = PayrollRun::query()->withoutGlobalScopes()->sole();
    $this->actingAs($setup['hr'])->post(route('hr.payroll.pay', $run), ['paid_on' => '2030-03-31'])->assertSessionHasErrors('run');
    $this->actingAs($setup['hr'])->post(route('hr.payroll.approve', $run))->assertSessionHasNoErrors();
    $this->actingAs($setup['hr'])->post(route('hr.payroll.pay', $run), ['paid_on' => '2030-03-31'])->assertSessionHasNoErrors();

    $balances = collect(app(LedgerService::class)->balances($setup['school']->id))->keyBy('code');
    expect($run->fresh()->status)->toBe(PayrollStatus::Paid)
        ->and($balances[LedgerService::SALARIES]['balance'])->toBe(250000 - 20952)
        ->and($balances[LedgerService::BANK]['balance'])->toBe(-(250000 - 40952))
        ->and($balances[LedgerService::PAYROLL_PAYABLE]['balance'])->toBe(20000)
        ->and($balances->sum('debit'))->toBe($balances->sum('credit'));
});

test('employees see their own payslip only after approval, and never someone else\'s', function () {
    $setup = hrSchool();
    $other = Employee::factory()->create(['school_id' => $setup['school']->id]);
    foreach ([$setup['employee'], $other] as $employee) {
        $this->actingAs($setup['hr'])->put(route('hr.payroll.salaries.update', $employee), ['basic' => '1000', 'allowances' => [], 'deductions' => []]);
    }
    $this->actingAs($setup['hr'])->post(route('hr.payroll.store'), ['period' => now()->format('Y-m')]);
    $own = Payslip::query()->withoutGlobalScopes()->where('employee_id', $setup['employee']->id)->sole();
    $theirs = Payslip::query()->withoutGlobalScopes()->where('employee_id', $other->id)->sole();

    $this->actingAs($setup['staff'])->get(route('hr.payroll.payslip', $own))->assertForbidden();

    $this->actingAs($setup['hr'])->post(route('hr.payroll.approve', PayrollRun::query()->withoutGlobalScopes()->sole()));

    $this->actingAs($setup['staff'])->get(route('hr.payroll.payslip', $own))->assertOk();
    $this->actingAs($setup['staff'])->get(route('hr.payroll.payslip', $theirs))->assertForbidden();
});
