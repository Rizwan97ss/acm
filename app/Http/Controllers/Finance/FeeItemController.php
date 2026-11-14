<?php

namespace App\Http\Controllers\Finance;

use App\Enums\FeeFrequency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FeeItemRequest;
use App\Models\AcademicYear;
use App\Models\FeeItem;
use App\Models\InvoiceLine;
use App\Services\Academics\AcademicOptions;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The fee structure: what each class pays in an academic year.
 */
class FeeItemController extends Controller
{
    public function index(Request $request, AcademicOptions $options): Response
    {
        Gate::authorize('fees.view');

        $years = $options->years();
        $yearId = $request->integer('academic_year_id') ?: ($years->firstWhere('is_current', true)['id'] ?? $years->first()['id'] ?? null);

        return Inertia::render('finance/fee-items/index', [
            'years' => $years,
            'classes' => $options->classes(),
            'frequencies' => FeeFrequency::options(),
            'selectedYearId' => $yearId,
            'items' => FeeItem::query()
                ->with('schoolClass:id,name')
                ->where('academic_year_id', $yearId)
                ->orderByRaw('school_class_id is not null')
                ->orderBy('name')
                ->get()
                ->map(fn (FeeItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'frequency' => $item->frequency->value,
                    'frequency_label' => $item->frequency->label(),
                    'school_class_id' => $item->school_class_id,
                    'school_class' => $item->schoolClass?->name,
                    'is_active' => $item->is_active,
                ]),
            'can' => [
                'create' => $request->user()->can('fees.create'),
                'update' => $request->user()->can('fees.edit'),
                'delete' => $request->user()->can('fees.delete'),
            ],
        ]);
    }

    public function store(FeeItemRequest $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $item = FeeItem::query()->create([...$request->payload(), 'school_id' => $currentSchool->id()]);

        return $this->back($item, __('Fee added.'));
    }

    public function update(FeeItemRequest $request, FeeItem $feeItem): RedirectResponse
    {
        $feeItem->update($request->payload());

        return $this->back($feeItem, __('Fee updated.'));
    }

    /**
     * Fees already invoiced are kept for history; deactivate them instead.
     */
    public function destroy(FeeItem $feeItem): RedirectResponse
    {
        Gate::authorize('fees.delete');

        if (InvoiceLine::query()->where('fee_item_id', $feeItem->id)->exists()) {
            $feeItem->update(['is_active' => false]);

            return $this->back($feeItem, __('This fee has been invoiced, so it was deactivated instead of deleted.'));
        }

        $feeItem->delete();

        return $this->back($feeItem, __('Fee deleted.'));
    }

    private function back(FeeItem $item, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('finance.fee-items.index', ['academic_year_id' => $item->academic_year_id ?: AcademicYear::query()->current()->value('id')]);
    }
}
