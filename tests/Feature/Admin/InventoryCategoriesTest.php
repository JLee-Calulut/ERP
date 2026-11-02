<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        return $user;
    }

    public function test_categories_require_authentication(): void
    {
        $this->get('/erp/inventory/categories')->assertRedirect(route('erp.login'));
    }

    public function test_categories_require_the_inventory_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/inventory/categories')
            ->assertForbidden();
    }

    public function test_staff_can_view_the_categories_index(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/inventory/categories')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Inventory/Categories/Index')
                ->has('categories', 5));
    }

    public function test_admin_can_create_a_category_with_auto_slug(): void
    {
        $this->actingAs($this->admin())
            ->post('/erp/inventory/categories', [
                'name' => 'Networking',
                'description' => 'Cables, routers and switches.',
            ])
            ->assertRedirect(route('erp.inventory.categories.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Networking', 'slug' => 'networking']);
    }

    public function test_category_name_is_required_and_unique(): void
    {
        $this->actingAs($this->admin())
            ->post('/erp/inventory/categories', ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin())
            ->post('/erp/inventory/categories', ['name' => 'Electronics'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_edit_a_category(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Old Category', 'slug' => 'old-category', 'description' => null]);

        $this->actingAs($admin)
            ->put("/erp/inventory/categories/{$category->id}", [
                'name' => 'New Category',
            ])
            ->assertRedirect(route('erp.inventory.categories.index'));

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Category']);
    }

    public function test_categories_in_use_by_products_cannot_be_deleted(): void
    {
        $category = Category::create(['name' => 'Used Category', 'slug' => 'used-category', 'description' => null]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Uses The Category',
            'sku' => 'USED-CAT-001',
            'cost_price' => 1,
            'selling_price' => 2,
        ]);

        $this->actingAs($this->admin())
            ->delete("/erp/inventory/categories/{$category->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_admin_can_delete_an_unused_category(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Empty Category', 'slug' => 'empty-category', 'description' => null]);

        $this->actingAs($admin)
            ->delete("/erp/inventory/categories/{$category->id}")
            ->assertRedirect(route('erp.inventory.categories.index'));

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_admin_can_view_the_category_edit_form(): void
    {
        $category = Category::first();

        $this->actingAs($this->admin())
            ->get("/erp/inventory/categories/{$category->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Inventory/Categories/Form')
                ->where('category.id', $category->id));
    }
}
