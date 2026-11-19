<?php

use App\Models\OrganizationMember;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('administrators manage the governing body, shown by body and order', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(['organization.view', 'organization.create', 'organization.edit', 'organization.delete'], $school);

    $this->actingAs($admin)->post(route('admin.organization.store'), [
        'name' => 'Ayesha Malik', 'position' => 'Trustee', 'body' => 'trustees', 'sort_order' => 5, 'is_active' => true,
    ])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.organization.store'), [
        'name' => 'Imran Qureshi', 'position' => 'Chairperson', 'body' => 'trustees', 'sort_order' => 0,
        'phone' => '+92 300 1112233', 'term_starts_on' => '2024-01-01', 'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect(DB::table('organization_members')->where('name', 'Imran Qureshi')->value('phone'))->not->toContain('1112233');

    $this->actingAs($admin)->get(route('admin.organization.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/organization')
            ->where('bodies.0.value', 'trustees')
            ->where('bodies.0.members.0.position', 'Chairperson')
            ->where('bodies.0.members.0.phone', '+92 300 1112233')
            ->has('bodies.0.members', 2));

    $member = OrganizationMember::query()->where('name', 'Ayesha Malik')->sole();
    $this->actingAs($admin)->put(route('admin.organization.update', $member), [
        'name' => 'Ayesha Malik', 'position' => 'Secretary', 'body' => 'executive', 'is_active' => true,
        'term_starts_on' => '2026-01-01', 'term_ends_on' => '2025-01-01',
    ])->assertSessionHasErrors('term_ends_on');

    $this->actingAs($admin)->delete(route('admin.organization.destroy', $member))->assertRedirect();
    expect(OrganizationMember::query()->count())->toBe(1);
});

test('only permitted users see or change the governing body', function () {
    $school = School::factory()->create();
    $viewer = userWithPermissions(['organization.view'], $school);

    $this->actingAs($viewer)->get(route('admin.organization.index'))->assertOk();
    $this->actingAs($viewer)->post(route('admin.organization.store'), ['name' => 'X', 'position' => 'Member', 'body' => 'advisory'])->assertForbidden();
    $this->actingAs(userWithPermissions(['students.view'], $school))->get(route('admin.organization.index'))->assertForbidden();
});
