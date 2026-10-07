<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Sprint 20: stock in / out / count per branch item, never below zero, with a one-time low-stock alert. */
class InventoryTest extends TestCase
{
    use CreatesClinicData, RefreshDatabase;

    public function test_stock_movements_and_low_stock_alert(): void
    {
        $branch = Branch::factory()->create();
        $reception = $this->userWithRole(Role::Receptionist, $branch);
        $admin = $this->userWithRole(Role::BranchAdmin, $branch);
        $this->actingAs($reception);

        $id = $this->postJson('/api/v1/inventory', [
            'branch_id' => $branch->id, 'name' => 'Picture cards (demo)', 'category' => 'therapy_material', 'unit' => 'pack',
            'reorder_level' => 3, 'unit_cost' => 250, 'opening_stock' => 10,
        ])->assertCreated()->assertJsonPath('data.stock', 10)->json('data.id');
        $this->postJson('/api/v1/inventory', ['branch_id' => $branch->id, 'name' => 'Picture cards (demo)', 'category' => 'toy', 'unit' => 'pcs', 'reorder_level' => 0])
            ->assertJsonValidationErrors('name');

        $this->postJson("/api/v1/inventory/{$id}/movements", ['type' => 'out', 'quantity' => 4, 'reference' => 'Speech room'])->assertCreated()->assertJsonPath('data.item.stock', 6);
        $this->postJson("/api/v1/inventory/{$id}/movements", ['type' => 'out', 'quantity' => 7])->assertJsonValidationErrors('quantity');
        $this->assertSame(0, $admin->notifications()->count());

        // Falling to the reorder level alerts managers once; further use while low does not repeat it.
        $this->postJson("/api/v1/inventory/{$id}/movements", ['type' => 'out', 'quantity' => 3])->assertJsonPath('data.item.low', true);
        $this->postJson("/api/v1/inventory/{$id}/movements", ['type' => 'out', 'quantity' => 1]);
        $this->assertSame(1, $admin->notifications()->where('data->kind', 'inventory.low_stock')->count());

        $this->postJson("/api/v1/inventory/{$id}/movements", ['type' => 'in', 'quantity' => 12, 'unit_cost' => 275])->assertJsonPath('data.item.stock', 14)->assertJsonPath('data.item.unit_cost', 275);
        $this->postJson("/api/v1/inventory/{$id}/movements", ['type' => 'adjust', 'quantity' => 13, 'note' => 'Monthly count'])->assertJsonPath('data.item.stock', 13);

        $card = $this->getJson("/api/v1/inventory/{$id}/movements")->assertOk()->json('data');
        $this->assertSame([-1, 12, -1, -3, -4, 10], array_map(fn ($m) => (int) $m['quantity'], $card));        // newest first
        $this->assertSame(13.0, (float) InventoryItem::find($id)->stock);

        $this->getJson('/api/v1/inventory?low=1')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/inventory')->assertJsonPath('totals.value', 3575)->assertJsonPath('totals.items', 1);

        // Another branch's staff cannot touch it; a trainer cannot see inventory at all.
        $this->actingAs($this->userWithRole(Role::Receptionist, Branch::factory()->create()))->postJson("/api/v1/inventory/{$id}/movements", ['type' => 'out', 'quantity' => 1])->assertForbidden();
        $this->actingAs($this->userWithRole(Role::Trainer, $branch))->getJson('/api/v1/inventory')->assertForbidden();
    }
}
