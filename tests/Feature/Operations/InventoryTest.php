<?php

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\School;
use App\Models\User;

function storekeeper(School $school): User
{
    return userWithPermissions(['inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete'], $school);
}

test('an item with opening stock records a movement', function () {
    $school = School::factory()->create();

    $this->actingAs(storekeeper($school))->post(route('operations.inventory.store'), [
        'name' => 'Whiteboard markers', 'unit' => 'box', 'reorder_level' => 5, 'opening_quantity' => 20,
    ])->assertSessionHasNoErrors();

    $item = InventoryItem::query()->sole();
    expect($item->quantity)->toBe(20)
        ->and(InventoryMovement::query()->sole())->type->toBe('in')->balance_after->toBe(20);
});

test('stock moves in and out, cannot go negative, and counts record the difference', function () {
    $school = School::factory()->create();
    $user = storekeeper($school);
    $item = InventoryItem::factory()->create(['school_id' => $school->id]);
    $move = fn (string $type, int $quantity) => $this->actingAs($user)->post(route('operations.inventory.move', $item), ['type' => $type, 'quantity' => $quantity]);

    $move('in', 10)->assertSessionHasNoErrors();
    $move('out', 4)->assertSessionHasNoErrors();
    $move('out', 7)->assertSessionHasErrors('quantity');
    $move('adjust', 5)->assertSessionHasNoErrors();

    expect($item->fresh()->quantity)->toBe(5)
        ->and(InventoryMovement::query()->orderBy('id')->pluck('quantity')->all())->toBe([10, -4, -1]);
});

test('items with stock cannot be deleted and viewers cannot move stock', function () {
    $school = School::factory()->create();
    $item = InventoryItem::factory()->create(['school_id' => $school->id, 'quantity' => 3]);

    $this->actingAs(storekeeper($school))->delete(route('operations.inventory.destroy', $item))->assertSessionHasErrors('item');
    $this->actingAs(userWithPermissions(['inventory.view'], $school))
        ->post(route('operations.inventory.move', $item), ['type' => 'out', 'quantity' => 1])
        ->assertForbidden();
});

test('items from another school are not reachable', function () {
    $school = School::factory()->create();
    $foreign = InventoryItem::factory()->create();

    $this->actingAs(storekeeper($school))
        ->post(route('operations.inventory.move', $foreign), ['type' => 'in', 'quantity' => 1])
        ->assertNotFound();
});
