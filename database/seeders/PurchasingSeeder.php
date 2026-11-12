<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchasingService;
use Illuminate\Database\Seeder;

class PurchasingSeeder extends Seeder
{
    public function run(): void
    {
        $purchasing = app(PurchasingService::class);
        $admin = User::where('email', 'admin@erp.test')->first();

        // ------------------------------------------------------------------
        // Suppliers
        // ------------------------------------------------------------------
        $suppliers = collect([
            [
                'name' => 'TechSource Distribution',
                'email' => 'sales@techsource.example',
                'phone' => '+1 555-0211',
                'address_line1' => '2100 Commerce Drive',
                'address_city' => 'Austin',
                'address_state' => 'TX',
                'address_postal_code' => '78701',
                'address_country' => 'United States',
            ],
            [
                'name' => 'OfficeMart Wholesale',
                'email' => 'orders@officemart.example',
                'phone' => '+1 555-0236',
                'address_line1' => '560 Industrial Parkway',
                'address_city' => 'Columbus',
                'address_state' => 'OH',
                'address_postal_code' => '43215',
                'address_country' => 'United States',
            ],
            [
                'name' => 'Summit Office Furniture',
                'email' => 'hello@summitoffice.example',
                'phone' => '+1 555-0258',
                'address_line1' => '3405 Timber Lane',
                'address_city' => 'Grand Rapids',
                'address_state' => 'MI',
                'address_postal_code' => '49503',
                'address_country' => 'United States',
            ],
            [
                'name' => 'PureBean Roasters',
                'email' => 'wholesale@purebean.example',
                'phone' => '+1 555-0283',
                'address_line1' => '19 Roastery Row',
                'address_city' => 'Portland',
                'address_state' => 'OR',
                'address_postal_code' => '97209',
                'address_country' => 'United States',
            ],
            [
                'name' => 'PackRight Logistics',
                'email' => 'sales@packright.example',
                'phone' => '+1 555-0272',
                'address_line1' => '77 Warehousing Way',
                'address_city' => 'Memphis',
                'address_state' => 'TN',
                'address_postal_code' => '38118',
                'address_country' => 'United States',
            ],
        ])->map(fn (array $data) => Supplier::create($data));

        // ------------------------------------------------------------------
        // Catalog lookup by SKU (cost prices come from InventorySeeder).
        // ------------------------------------------------------------------
        $products = Product::whereIn('sku', [
            'MOUSE-WL-001', 'KB-MECH-002', 'MON-27-003', 'PAPER-A4-004',
            'PENS-GEL-005', 'DESK-STD-006', 'CHAIR-ERG-007', 'COFFEE-1KG-008',
            'TEA-GRN-009', 'BOX-SHIP-010', 'TAPE-PKT-011', 'BUBBLE-RL-012',
        ])->get()->keyBy('sku');

        $line = fn (string $sku, int $quantity) => [
            'product_id' => $products[$sku]->id,
            'quantity' => $quantity,
        ];

        // Confirmed + draft orders so the purchasing desk has a real mix.
        // The two-month-old TechSource order is fully received (books a
        // receipt and closes the PO); the other confirmed orders stay open so
        // the demo has "Receive stock" actions and a pending PO value.
        $orders = [
            // confirmed, 2 months ago — electronics from TechSource
            ['supplier' => 0, 'months_ago' => 2, 'confirmed' => true, 'receive' => true, 'items' => [
                $line('MOUSE-WL-001', 20), $line('KB-MECH-002', 10), $line('MON-27-003', 2),
            ]],
            // confirmed, 1 month ago — consumables from OfficeMart
            ['supplier' => 1, 'months_ago' => 1, 'confirmed' => true, 'items' => [
                $line('PAPER-A4-004', 25), $line('PENS-GEL-005', 40), $line('TAPE-PKT-011', 30),
            ]],
            // confirmed, a few days ago — furniture from Summit
            ['supplier' => 2, 'months_ago' => 0, 'confirmed' => true, 'items' => [
                $line('DESK-STD-006', 2), $line('CHAIR-ERG-007', 3),
            ]],
            // draft, yesterday — coffee & tea restock from PureBean
            ['supplier' => 3, 'months_ago' => 0, 'confirmed' => false, 'items' => [
                $line('COFFEE-1KG-008', 5), $line('TEA-GRN-009', 20),
            ]],
            // draft, today — shipping supplies from PackRight
            ['supplier' => 4, 'months_ago' => 0, 'confirmed' => false, 'items' => [
                $line('BOX-SHIP-010', 30), $line('BUBBLE-RL-012', 10),
            ]],
        ];

        foreach ($orders as $order) {
            $date = now()->subMonths($order['months_ago'])->startOfMonth()->addDays(10);

            $created = $purchasing->createPurchaseOrder([
                'supplier_id' => $suppliers[$order['supplier']]->id,
                'items' => $order['items'],
            ], $admin);

            $created->update(['ordered_at' => $date]);

            if ($order['confirmed']) {
                $purchasing->transitionStatus($created, PurchaseOrder::STATUS_CONFIRMED);
            }

            if ($order['receive'] ?? false) {
                $receipt = $purchasing->receive(
                    $created,
                    $created->items
                        ->map(fn ($item) => [
                            'purchase_order_item_id' => $item->id,
                            'quantity_received' => $item->quantity,
                        ])
                        ->all(),
                    'First shipment received in full.',
                    $admin,
                );

                // Backdate the receipt so the dashboard feed shows history.
                $receipt->update(['received_at' => $date->copy()->addDays(5)]);
            }
        }
    }
}
