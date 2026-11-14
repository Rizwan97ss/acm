<?php

namespace App\Http\Controllers\Operations;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Services\Inventory\InventoryService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock of consumables and equipment, with an append-only movement history.
 */
class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): Response
    {
        Gate::authorize('inventory.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'low' => ['nullable', 'boolean'],
        ]);

        $items = InventoryItem::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$escaped}%")->orWhere('sku', 'like', "%{$escaped}%")->orWhere('category', 'like', "%{$escaped}%"));
            })
            ->when($request->boolean('low'), fn (Builder $query) => $query->whereColumn('quantity', '<=', 'reorder_level'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $user = $request->user();

        return Inertia::render('operations/inventory', [
            'items' => [
                'data' => $items->getCollection()->map(fn (InventoryItem $item): array => [
                    ...$item->only(['id', 'name', 'sku', 'category', 'unit', 'location', 'quantity', 'reorder_level']),
                    'low' => $item->isLow(),
                ])->values(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'from' => $items->firstItem(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'to' => $items->lastItem(),
                    'total' => $items->total(),
                    'links' => $items->linkCollection()->all(),
                ],
            ],
            'filters' => [...$filters, 'low' => $request->boolean('low')],
            'lowCount' => InventoryItem::query()->whereColumn('quantity', '<=', 'reorder_level')->count(),
            'movements' => InventoryMovement::query()
                ->with(['item:id,name,unit', 'user:id,name'])
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn (InventoryMovement $movement): array => [
                    'id' => $movement->id,
                    'item' => $movement->item->name,
                    'unit' => $movement->item->unit,
                    'type' => $movement->type,
                    'type_label' => StockMovementType::from($movement->type)->label(),
                    'quantity' => $movement->quantity,
                    'balance_after' => $movement->balance_after,
                    'note' => $movement->note,
                    'user' => $movement->user?->name,
                    'at' => $movement->created_at?->toIso8601String(),
                ])->values(),
            'movementTypes' => StockMovementType::options(),
            'can' => [
                'create' => $user->can('inventory.create'),
                'edit' => $user->can('inventory.edit'),
                'delete' => $user->can('inventory.delete'),
            ],
        ]);
    }

    public function store(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        Gate::authorize('inventory.create');

        $validated = $this->validateItem($request, $currentSchool, null);
        $item = InventoryItem::query()->create(collect($validated)->except('opening_quantity')->all());

        if (($validated['opening_quantity'] ?? 0) > 0) {
            $this->inventory->record($item, StockMovementType::In, (int) $validated['opening_quantity'], __('Opening stock'), $request->user());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item added.')]);

        return back();
    }

    public function update(Request $request, InventoryItem $item, CurrentSchool $currentSchool): RedirectResponse
    {
        Gate::authorize('inventory.edit');

        $item->update(collect($this->validateItem($request, $currentSchool, $item))->except('opening_quantity')->all());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item updated.')]);

        return back();
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        Gate::authorize('inventory.delete');

        if ($item->quantity !== 0) {
            throw ValidationException::withMessages(['item' => __('Issue or count the remaining stock down to zero before deleting.')]);
        }

        $item->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item deleted.')]);

        return back();
    }

    public function move(Request $request, InventoryItem $item): RedirectResponse
    {
        Gate::authorize('inventory.edit');

        $validated = $request->validate([
            'type' => ['required', Rule::enum(StockMovementType::class)],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->inventory->record($item, StockMovementType::from($validated['type']), (int) $validated['quantity'], $validated['note'] ?? null, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock updated.')]);

        return back();
    }

    /**
     * @return array{name: string, sku: string|null, category: string|null, unit: string, location: string|null, reorder_level: int, opening_quantity?: int|null}
     */
    private function validateItem(Request $request, CurrentSchool $currentSchool, ?InventoryItem $item): array
    {
        /** @var array{name: string, sku: string|null, category: string|null, unit: string, location: string|null, reorder_level: int, opening_quantity?: int|null} */
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:40', Rule::unique('inventory_items')->where('school_id', $currentSchool->id())->ignore($item?->id)],
            'category' => ['nullable', 'string', 'max:60'],
            'unit' => ['required', 'string', 'max:20'],
            'location' => ['nullable', 'string', 'max:100'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'opening_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);
    }
}
