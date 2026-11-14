<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Api\V1\Concerns\ScopesPortalStudents;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    use ScopesPortalStudents;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('invoices.view');

        $filters = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $studentIds = $this->portalStudentIds($request->user());

        $invoices = Invoice::query()
            ->with('student:id,first_name,last_name,admission_no')
            ->when($studentIds !== null, fn (Builder $query) => $query->whereIn('student_id', $studentIds))
            ->when($filters['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return InvoiceResource::collection($invoices);
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        Gate::authorize('invoices.view');

        $studentIds = $this->portalStudentIds($request->user());
        abort_if($studentIds !== null && ! in_array($invoice->student_id, $studentIds, true), 404);

        return new InvoiceResource($invoice->load('student:id,first_name,last_name,admission_no'));
    }
}
