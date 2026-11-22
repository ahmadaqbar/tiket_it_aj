<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Subcategory;
use Tests\TestCase;

class CategoryAndSlaTest extends TestCase
{
    public function test_initial_categories_are_seeded(): void
    {
        $this->assertSame(8, Category::count());
        $this->assertSame(39, Subcategory::count());
        $this->assertTrue($this->category('Network & Internet')->subcategories->pluck('name')->contains('WiFi'));
    }

    public function test_only_admin_can_access_category_and_sla_pages(): void
    {
        foreach (['/admin/categories', '/admin/sla'] as $uri) {
            $this->actingAs($this->makeUser())->get($uri)->assertForbidden();
            $this->actingAs($this->makeIt())->get($uri)->assertForbidden();
            $this->actingAs($this->makeAdmin())->get($uri)->assertOk();
        }
    }

    public function test_admin_can_create_update_and_deactivate_category(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/categories', ['name' => 'Telepon & PABX', 'is_active' => 1, 'sort_order' => 9])
            ->assertSessionHasNoErrors();
        $category = Category::where('name', 'Telepon & PABX')->firstOrFail();

        $this->actingAs($admin)->put("/admin/categories/{$category->id}", ['name' => 'Telepon', 'is_active' => 0, 'sort_order' => 9])
            ->assertSessionHasNoErrors();
        $this->assertFalse($category->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.updated', 'entity_id' => $category->id]);
    }

    public function test_category_name_must_be_unique(): void
    {
        $this->actingAs($this->makeAdmin())->post('/admin/categories', ['name' => 'Hardware'])->assertSessionHasErrors('name');
    }

    public function test_used_category_and_subcategory_cannot_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $sub = $this->subcategory('Hardware', 'Printer');
        $this->makeTicket($this->makeUser(), ['subcategory_id' => $sub->id]);

        $this->actingAs($admin)->delete("/admin/categories/{$this->category()->id}")->assertSessionHasErrors('category');
        $this->actingAs($admin)->delete("/admin/subcategories/{$sub->id}")->assertSessionHasErrors('subcategory');
        $this->assertNotNull($sub->fresh());
    }

    public function test_unused_category_can_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $category = $this->category('Request & Development');

        $this->actingAs($admin)->delete("/admin/categories/{$category->id}")->assertSessionHasNoErrors();
        $this->assertNull($category->fresh());
        $this->assertSame(0, Subcategory::where('category_id', $category->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.deleted']);
    }

    public function test_admin_can_manage_subcategories_with_unique_name_per_category(): void
    {
        $admin = $this->makeAdmin();
        $category = $this->category();

        $this->actingAs($admin)->post("/admin/categories/{$category->id}/subcategories", ['name' => 'Scanner', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/admin/categories/{$category->id}/subcategories", ['name' => 'Scanner'])
            ->assertSessionHasErrors('name');
        // Nama sama di kategori lain diperbolehkan.
        $this->actingAs($admin)->post("/admin/categories/{$this->category('Maintenance')->id}/subcategories", ['name' => 'Scanner', 'is_active' => 1])
            ->assertSessionHasNoErrors();

        $sub = $category->subcategories()->where('name', 'Scanner')->firstOrFail();
        $this->actingAs($admin)->put("/admin/subcategories/{$sub->id}", ['name' => 'Scanner Dokumen', 'is_active' => 0])
            ->assertSessionHasNoErrors();
        $this->assertFalse($sub->fresh()->is_active);
    }

    public function test_inactive_subcategory_is_hidden_from_ticket_form(): void
    {
        $this->subcategory('Hardware', 'Monitor')->update(['is_active' => false]);

        $categories = $this->actingAs($this->makeUser())->get('/user/tiket/buat')->assertOk()->viewData('categories');
        $names = $categories->firstWhere('name', 'Hardware')->subcategories->pluck('name');

        $this->assertFalse($names->contains('Monitor'));
        $this->assertTrue($names->contains('Printer'));
    }

    public function test_admin_can_update_sla_and_it_applies_to_new_tickets_only(): void
    {
        $user = $this->makeUser();
        $old = $this->makeTicket($user, ['priority' => 'HIGH']);
        $oldDue = $old->resolution_due_at;
        $policy = SlaPolicy::where('priority', 'HIGH')->firstOrFail();

        $this->actingAs($this->makeAdmin())->put("/admin/sla/{$policy->id}", [
            'response_minutes' => 15, 'resolution_minutes' => 120, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertEquals($oldDue, $old->fresh()->resolution_due_at);
        $new = $this->makeTicket($user, ['priority' => 'HIGH']);
        $this->assertEqualsWithDelta(120, $new->created_at->diffInMinutes($new->resolution_due_at), 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sla.updated', 'entity_id' => $policy->id]);
    }

    public function test_sla_resolution_cannot_be_shorter_than_response(): void
    {
        $policy = SlaPolicy::firstOrFail();

        $this->actingAs($this->makeAdmin())->put("/admin/sla/{$policy->id}", [
            'response_minutes' => 120, 'resolution_minutes' => 60,
        ])->assertSessionHasErrors('resolution_minutes');
    }

    public function test_inactive_sla_leaves_due_dates_empty(): void
    {
        SlaPolicy::where('priority', 'LOW')->update(['is_active' => false]);

        $ticket = $this->makeTicket($this->makeUser(), ['priority' => 'LOW']);

        $this->assertNull($ticket->sla_policy_id);
        $this->assertNull($ticket->resolution_due_at);
    }
}
