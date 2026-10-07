<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Inventory (Sprint 20): therapy materials and supplies per branch — stock in, stock out, counts, and a
 * low-stock alert when an item falls to its reorder level. Costs stay in Expenses / Vendor bills.
 */
class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::INVENTORY_VIEW);
        $branches = $request->user()->accessibleBranchIds();
        $q = trim((string) $request->input('q'));
        $items = InventoryItem::with('branch:id,name')
            ->when($branches !== null, fn ($b) => $b->whereIn('branch_id', $branches))
            ->when($request->filled('branch_id'), fn ($b) => $b->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('category'), fn ($b) => $b->where('category', $request->string('category')))
            ->when($q !== '', fn ($b) => $b->where('name', 'like', "%{$q}%"))
            ->when(! $request->boolean('inactive'), fn ($b) => $b->where('is_active', true))
            ->when($request->boolean('low'), fn ($b) => $b->where('reorder_level', '>', 0)->whereColumn('stock', '<=', 'reorder_level'))
            ->orderBy('name')->get();

        return response()->json([
            'data' => $items->map(fn (InventoryItem $i) => $this->item($i))->values(),
            'categories' => InventoryItem::CATEGORIES,
            'totals' => ['items' => $items->count(), 'low' => $items->filter->isLow()->count(), 'value' => round($items->sum(fn ($i) => (float) $i->stock * (float) $i->unit_cost), 2)],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(Permission::INVENTORY_MANAGE);
        $data = $request->validate($this->rules($request) + ['opening_stock' => ['nullable', 'numeric', 'min:0']]);
        abort_unless($request->user()->canAccessBranch((int) $data['branch_id']), 403);

        $item = DB::transaction(function () use ($data, $request) {
            $item = InventoryItem::create([...collect($data)->except('opening_stock')->all(), 'stock' => 0]);
            if ((float) ($data['opening_stock'] ?? 0) > 0) {
                $this->record($item, 'in', (float) $data['opening_stock'], ['note' => 'Opening stock', 'unit_cost' => $data['unit_cost'] ?? null], $request->user()->id);
            }

            return $item;
        });

        return response()->json(['data' => $this->item($item->fresh('branch'))], 201);
    }

    public function update(Request $request, InventoryItem $item): JsonResponse
    {
        Gate::authorize(Permission::INVENTORY_MANAGE);
        abort_unless($request->user()->canAccessBranch($item->branch_id), 403);
        $data = $request->validate(collect($this->rules($request, $item))->except('branch_id')->all() + ['is_active' => ['sometimes', 'boolean']]);
        $item->update($data);

        return response()->json(['data' => $this->item($item->fresh('branch'))]);
    }

    /** GET /inventory/{item}/movements — the stock card. */
    public function movements(Request $request, InventoryItem $item): JsonResponse
    {
        Gate::authorize(Permission::INVENTORY_VIEW);
        abort_unless($request->user()->canAccessBranch($item->branch_id), 403);

        return response()->json(['data' => $item->movements()->with('creator:id,name')->latest('date')->latest('id')->limit(200)->get()
            ->map(fn (InventoryMovement $m) => [
                'id' => $m->id, 'date' => $m->date->toDateString(), 'type' => $m->type, 'quantity' => (float) $m->quantity,
                'balance_after' => (float) $m->balance_after, 'unit_cost' => $m->unit_cost !== null ? (float) $m->unit_cost : null,
                'reference' => $m->reference, 'note' => $m->note, 'by' => $m->creator?->name,
            ])]);
    }

    /** POST /inventory/{item}/movements — in (received), out (used / issued) or adjust (physical count). */
    public function move(Request $request, InventoryItem $item): JsonResponse
    {
        Gate::authorize(Permission::INVENTORY_MANAGE);
        abort_unless($request->user()->canAccessBranch($item->branch_id), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(['in', 'out', 'adjust'])],
            'quantity' => ['required', 'numeric', $request->input('type') === 'adjust' ? 'min:0' : 'gt:0'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $movement = $this->record($item, $data['type'], (float) $data['quantity'], $data, $request->user()->id);

        return response()->json(['data' => ['movement_id' => $movement->id, 'item' => $this->item($item->fresh('branch'))]], 201);
    }

    /** Applies one movement under a row lock; "adjust" sets the counted stock. Warns staff when stock falls to the reorder level. */
    private function record(InventoryItem $item, string $type, float $quantity, array $extra, ?int $userId): InventoryMovement
    {
        return DB::transaction(function () use ($item, $type, $quantity, $extra, $userId) {
            $locked = InventoryItem::lockForUpdate()->findOrFail($item->id);
            $before = (float) $locked->stock;
            $change = match ($type) {
                'in' => $quantity, 'out' => -$quantity, default => $quantity - $before
            };
            if ($before + $change < 0) {
                throw ValidationException::withMessages(['quantity' => "Only {$before} {$locked->unit} in stock."]);
            }
            $locked->update(['stock' => $before + $change] + ($type === 'in' && isset($extra['unit_cost']) ? ['unit_cost' => $extra['unit_cost']] : []));
            $movement = InventoryMovement::create([
                'inventory_item_id' => $locked->id, 'date' => $extra['date'] ?? today()->toDateString(), 'type' => $type, 'quantity' => $change,
                'balance_after' => $before + $change, 'unit_cost' => $extra['unit_cost'] ?? null, 'reference' => $extra['reference'] ?? null,
                'note' => $extra['note'] ?? null, 'created_by' => $userId,
            ]);
            $wasLow = (float) $locked->reorder_level > 0 && $before <= (float) $locked->reorder_level;
            if ($change < 0 && ! $wasLow && $locked->isLow()) {
                app(NotificationService::class)->toStaff(Permission::INVENTORY_MANAGE, $locked->branch_id, 'inventory.low_stock', "Low stock: {$locked->name}",
                    "Only {$locked->stock} {$locked->unit} left (reorder level {$locked->reorder_level}).", '/app/inventory?low=1');
            }

            return $movement;
        });
    }

    private function rules(Request $request, ?InventoryItem $item = null): array
    {
        $branchId = $item?->branch_id ?? $request->integer('branch_id');

        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:150', Rule::unique('inventory_items')->where('branch_id', $branchId)->ignore($item?->id)],
            'category' => ['required', Rule::in(array_keys(InventoryItem::CATEGORIES))],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function item(InventoryItem $i): array
    {
        return [
            'id' => $i->id, 'branch_id' => $i->branch_id, 'branch' => $i->branch->name, 'name' => $i->name, 'category' => $i->category,
            'unit' => $i->unit, 'stock' => (float) $i->stock, 'reorder_level' => (float) $i->reorder_level,
            'unit_cost' => $i->unit_cost !== null ? (float) $i->unit_cost : null, 'is_active' => $i->is_active, 'notes' => $i->notes, 'low' => $i->isLow(),
        ];
    }
}
