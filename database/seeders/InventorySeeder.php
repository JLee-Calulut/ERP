<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name' => 'Electronics', 'slug' => 'electronics', 'description' => 'Devices, components and accessories.'],
            ['name' => 'Office Supplies', 'slug' => 'office-supplies', 'description' => 'Stationery and everyday office needs.'],
            ['name' => 'Furniture', 'slug' => 'furniture', 'description' => 'Desks, chairs and workspace furniture.'],
            ['name' => 'Beverages', 'slug' => 'beverages', 'description' => 'Coffee, tea and drinkable goods.'],
            ['name' => 'Packaging', 'slug' => 'packaging', 'description' => 'Boxes, tape and shipping materials.'],
        ])->map(fn (array $data) => Category::create($data));

        $products = [
            ['Wireless Mouse', 'MOUSE-WL-001', 'Electronics', 12, 25, 45, 5],
            ['Mechanical Keyboard', 'KB-MECH-002', 'Electronics', 8, 40, 90, 5],
            ['27" Monitor', 'MON-27-003', 'Electronics', 3, 120, 260, 5],
            ['A4 Paper (Rim)', 'PAPER-A4-004', 'Office Supplies', 2, 6, 9, 5],
            ['Gel Pens (Box)', 'PENS-GEL-005', 'Office Supplies', 60, 4, 8, 5],
            ['Standing Desk', 'DESK-STD-006', 'Furniture', 5, 180, 320, 3],
            ['Office Chair', 'CHAIR-ERG-007', 'Furniture', 14, 110, 210, 4],
            ['Coffee Beans (1kg)', 'COFFEE-1KG-008', 'Beverages', 4, 12, 22, 10],
            ['Green Tea (100pk)', 'TEA-GRN-009', 'Beverages', 35, 8, 15, 8],
            ['Shipping Box (10pk)', 'BOX-SHIP-010', 'Packaging', 90, 5, 9, 20],
            ['Packing Tape', 'TAPE-PKT-011', 'Packaging', 0, 2, 4, 10],
            ['Bubble Wrap (Roll)', 'BUBBLE-RL-012', 'Packaging', 18, 7, 13, 6],
        ];

        $user = User::where('email', 'admin@erp.test')->first();

        foreach ($products as [$name, $sku, $categoryName, $stock, $cost, $price, $threshold]) {
            /** @var Product $product */
            $product = Product::create([
                'category_id' => $categories->firstWhere('name', $categoryName)->id,
                'name' => $name,
                'sku' => $sku,
                'description' => "Demo {$categoryName} product seeded for the ERP.",
                'cost_price' => $cost,
                'selling_price' => $price,
                'stock_quantity' => $stock,
                'low_stock_threshold' => $threshold,
                'is_active' => true,
            ]);

            // Seed an initial "received" transaction so the ledger and
            // dashboard activity feed have real history.
            StockTransaction::create([
                'product_id' => $product->id,
                'type' => StockTransaction::TYPE_IN,
                'quantity' => $stock,
                'previous_quantity' => 0,
                'new_quantity' => $stock,
                'reason' => 'Initial stock',
                'user_id' => $user?->id,
            ]);
        }
    }
}
