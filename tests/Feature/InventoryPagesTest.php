<?php

namespace Tests\Feature;

use App\Enums\MaterialUnit;
use App\Enums\Role;
use App\Livewire\Inventory\CategoryManagement;
use App\Livewire\Inventory\MenuManagement;
use App\Livewire\Inventory\RawMaterialManagement;
use App\Livewire\Inventory\StockManagement;
use App\Livewire\Inventory\StockPrediction;
use App\Models\Category;
use App\Models\Menu;
use App\Models\RawMaterial;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryPagesTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::create([
            'name' => 'Op',
            'email' => 'op@example.com',
            'password' => 'password',
            'role' => Role::Operator,
        ]);
    }

    private function material(string $name = 'Cabai'): RawMaterial
    {
        return RawMaterial::create([
            'category_id' => Category::create(['name' => 'Bumbu '.$name])->id,
            'name' => $name,
            'unit' => MaterialUnit::Kilogram,
            'minimum_stock' => 2,
        ]);
    }

    public function test_guest_is_redirected_from_inventory(): void
    {
        $this->get(route('inventory.raw-materials'))->assertRedirect(route('login'));
    }

    public function test_operator_can_access_inventory_routes(): void
    {
        $this->actingAs($this->operator());

        $this->get(route('inventory.categories'))->assertOk();
        $this->get(route('inventory.raw-materials'))->assertOk();
        $this->get(route('inventory.stock'))->assertOk();
        $this->get(route('inventory.menus'))->assertOk();
        $this->get(route('inventory.prediction'))->assertOk();
    }

    public function test_category_can_be_created(): void
    {
        $this->actingAs($this->operator());

        Livewire::test(CategoryManagement::class)
            ->set('name', 'Santan & Kelapa')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Santan & Kelapa']);
    }

    public function test_raw_material_requires_category(): void
    {
        $this->actingAs($this->operator());

        Livewire::test(RawMaterialManagement::class)
            ->set('name', 'Santan')
            ->set('unit', 'liter')
            ->set('minimumStock', '5')
            ->call('save')
            ->assertHasErrors('categoryId');
    }

    public function test_raw_material_can_be_created(): void
    {
        $this->actingAs($this->operator());
        $category = Category::create(['name' => 'Protein']);

        Livewire::test(RawMaterialManagement::class)
            ->set('name', 'Daging Sapi')
            ->set('categoryId', (string) $category->id)
            ->set('unit', 'kg')
            ->set('minimumStock', '8')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('raw_materials', ['name' => 'Daging Sapi', 'category_id' => $category->id]);
    }

    public function test_stock_in_creates_batch_via_page(): void
    {
        $this->actingAs($this->operator());
        $material = $this->material();

        Livewire::test(StockManagement::class)
            ->call('openIn')
            ->set('materialId', (string) $material->id)
            ->set('quantity', '10')
            ->set('unitCost', '45000')
            ->set('receivedAt', '2026-07-15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(10.0, $material->currentStock());
    }

    public function test_stock_out_shows_error_when_insufficient(): void
    {
        $this->actingAs($this->operator());
        $material = $this->material();

        Livewire::test(StockManagement::class)
            ->call('openOut')
            ->set('materialId', (string) $material->id)
            ->set('quantity', '5')
            ->call('save')
            ->assertHasErrors('quantity');
    }

    public function test_menu_can_be_created_with_recipe(): void
    {
        $this->actingAs($this->operator());
        $santan = $this->material('Santan');
        $daging = $this->material('Daging');

        Livewire::test(MenuManagement::class)
            ->call('openCreate')
            ->set('name', 'Rendang')
            ->set('price', '28000')
            ->set('recipe', [
                ['raw_material_id' => (string) $santan->id, 'quantity' => '0.3'],
                ['raw_material_id' => (string) $daging->id, 'quantity' => '0.2'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $menu = Menu::first();
        $this->assertSame('Rendang', $menu->name);
        $this->assertCount(2, $menu->materials);
    }

    public function test_menu_sale_deducts_stock_via_page(): void
    {
        $this->actingAs($this->operator());
        $inventory = new InventoryService;
        $santan = $this->material('Santan');
        $inventory->stockIn($santan, 10, 12000);

        $menu = Menu::create(['name' => 'Gulai', 'price' => 20000]);
        $menu->materials()->attach([$santan->id => ['quantity' => 0.5]]);

        Livewire::test(MenuManagement::class)
            ->call('openSell', $menu->id)
            ->set('portions', '4')
            ->call('confirmSell')
            ->assertHasNoErrors();

        $this->assertSame(8.0, $santan->currentStock()); // 10 - 0.5*4
    }

    public function test_prediction_page_renders_recommendations(): void
    {
        $this->actingAs($this->operator());
        $this->material();

        Livewire::test(StockPrediction::class)
            ->assertOk()
            ->assertViewHas('recommendations');
    }
}
