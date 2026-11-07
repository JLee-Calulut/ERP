<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\SalesService;
use Illuminate\Database\Seeder;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $sales = app(SalesService::class);
        $admin = User::where('email', 'admin@erp.test')->first();

        // ------------------------------------------------------------------
        // Customers
        // ------------------------------------------------------------------
        $customers = collect([
            [
                'name' => 'Northwind Traders',
                'email' => 'hello@northwind.example',
                'phone' => '+1 555-0100',
                'address_line1' => '1200 Pike Street',
                'address_city' => 'Seattle',
                'address_state' => 'WA',
                'address_postal_code' => '98101',
                'address_country' => 'United States',
            ],
            [
                'name' => 'Blue Oak Coffee Co.',
                'email' => 'orders@blueoak.example',
                'phone' => '+1 555-0142',
                'address_line1' => '88 Harbor Avenue',
                'address_city' => 'Portland',
                'address_state' => 'OR',
                'address_postal_code' => '97205',
                'address_country' => 'United States',
            ],
            [
                'name' => 'Paper & Co. Supplies',
                'email' => 'sales@paperco.example',
                'phone' => '+1 555-0167',
                'address_line1' => '411 Market Street',
                'address_city' => 'San Francisco',
                'address_state' => 'CA',
                'address_postal_code' => '94103',
                'address_country' => 'United States',
            ],
            [
                'name' => 'Metro Office Solutions',
                'email' => 'hello@metrooffice.example',
                'phone' => '+1 555-0138',
                'address_line1' => '77 King Street',
                'address_city' => 'Toronto',
                'address_state' => 'ON',
                'address_postal_code' => 'M5V 1M5',
                'address_country' => 'Canada',
            ],
        ])->map(fn (array $data) => Customer::create($data));

        // ------------------------------------------------------------------
        // Catalog lookup by SKU (stock is set by InventorySeeder).
        // ------------------------------------------------------------------
        $products = Product::whereIn('sku', [
            'MOUSE-WL-001', 'KB-MECH-002', 'MON-27-003', 'PAPER-A4-004',
            'PENS-GEL-005', 'DESK-STD-006', 'CHAIR-ERG-007', 'COFFEE-1KG-008',
            'TEA-GRN-009', 'BOX-SHIP-010', 'BUBBLE-RL-012',
        ])->get()->keyBy('sku');

        $line = fn (string $sku, int $quantity) => [
            'product_id' => $products[$sku]->id,
            'quantity' => $quantity,
        ];

        // Completed orders spread across the last 6 months (incl. current),
        // sized so the seeded catalog never runs out of stock.
        $completed = [
            // 5 months ago — Northwind
            ['customer' => 0, 'months_ago' => 5, 'items' => [
                $line('MOUSE-WL-001', 2), $line('KB-MECH-002', 1), $line('COFFEE-1KG-008', 1),
            ]],
            // 4 months ago — Blue Oak
            ['customer' => 1, 'months_ago' => 4, 'items' => [
                $line('PENS-GEL-005', 3), $line('PAPER-A4-004', 1), $line('BOX-SHIP-010', 5), $line('BUBBLE-RL-012', 1),
            ]],
            // 3 months ago — Metro
            ['customer' => 3, 'months_ago' => 3, 'items' => [
                $line('CHAIR-ERG-007', 2), $line('MOUSE-WL-001', 1), $line('TEA-GRN-009', 4),
            ]],
            // 2 months ago — Paper & Co.
            ['customer' => 2, 'months_ago' => 2, 'items' => [
                $line('MON-27-003', 1), $line('KB-MECH-002', 1), $line('DESK-STD-006', 1),
            ]],
            // 1 month ago — Blue Oak
            ['customer' => 1, 'months_ago' => 1, 'items' => [
                $line('MOUSE-WL-001', 2), $line('COFFEE-1KG-008', 1), $line('TEA-GRN-009', 2), $line('BUBBLE-RL-012', 1),
            ]],
            // this month — Northwind
            ['customer' => 0, 'months_ago' => 0, 'items' => [
                $line('KB-MECH-002', 1), $line('CHAIR-ERG-007', 1), $line('BOX-SHIP-010', 3), $line('PENS-GEL-005', 2),
            ]],
        ];

        foreach ($completed as $index => $order) {
            $date = now()->subMonths($order['months_ago'])->startOfMonth()->addDays(6);

            $created = $sales->createOrder([
                'customer_id' => $customers[$order['customer']]->id,
                'items' => $order['items'],
            ], $admin);

            $created->update(['ordered_at' => $date]);
            $sales->transitionStatus($created, SalesOrder::STATUS_CONFIRMED);
            $sales->transitionStatus($created, SalesOrder::STATUS_COMPLETED);

            $invoice = $sales->generateInvoice($created);
            $invoice->update(['issued_at' => $date]);
        }

        // One still-uncompleted (confirmed) order with an item in flight,
        // so there is an actionable order on the sales desk.
        $pendingCustomer = $customers[2];
        $pending = $sales->createOrder([
            'customer_id' => $pendingCustomer->id,
            'items' => [
                $line('MOUSE-WL-001', 1),
                $line('TEA-GRN-009', 1),
            ],
        ], $admin);

        $pending->update(['ordered_at' => now()->subDays(1)]);
        $sales->transitionStatus($pending, SalesOrder::STATUS_CONFIRMED);
    }
}
