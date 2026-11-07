<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Product;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\StockTransaction;
use Illuminate\Support\Collection;

class DashboardService
{
    /** Number of quick actions the dashboard grid can show at once. */
    private const QUICK_ACTION_SLOTS = 4;

    public function stats(): array
    {
        return [
            'users' => User::count(),
            'roles' => Role::count(),
            'permissions' => Permission::count(),
            'administrators' => User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
                ->when(auth()->id(), fn ($query, $id) => $query->whereKeyNot($id))
                ->count(),
            'lowStock' => Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count(),
            'products' => Product::count(),
            'customers' => Customer::count(),
            'revenue' => (float) SalesOrder::where('status', SalesOrder::STATUS_COMPLETED)->sum('total'),
            'orders' => SalesOrder::where('status', '!=', SalesOrder::STATUS_CANCELLED)->count(),
        ];
    }

    public function quickActions(): array
    {
        $user = auth()->user();
        $can = fn (string $permission) => $user?->hasPermission($permission) ?? false;

        $catalog = [
            ['label' => 'Manage users', 'icon' => '🪪', 'permission' => 'users.manage', 'href' => '/erp/users'],
            ['label' => 'Roles & permissions', 'icon' => '🔐', 'permission' => 'roles.manage', 'href' => '/erp/roles'],
            ['label' => 'Create sale', 'icon' => '🛒', 'permission' => 'sales.manage', 'href' => '/erp/sales/orders/create'],
            ['label' => 'Receive stock', 'icon' => '📥', 'permission' => 'inventory.manage', 'href' => '/erp/inventory/stock/create'],
            ['label' => 'Manage products', 'icon' => '📦', 'permission' => 'inventory.manage', 'href' => '/erp/inventory/products'],
        ];

        return collect($catalog)
            ->map(fn (array $action) => [
                'label' => $action['label'],
                'icon' => $action['icon'],
                'href' => $can($action['permission']) ? $action['href'] : null,
                'available' => $can($action['permission']),
            ])
            // Put the actions the user can actually perform first so a manager
            // does not see a disabled "Manage users" card ahead of "Create sale".
            ->sortByDesc('available')
            ->take(self::QUICK_ACTION_SLOTS)
            ->values()
            ->all();
    }

    public function revenueTrend(): array
    {
        $labels = collect(range(5, 0))
            ->map(fn (int $m) => now()->subMonths($m)->format('M'))
            ->values()
            ->all();

        $values = collect(range(5, 0))
            ->map(function (int $m) {
                $start = now()->subMonths($m)->startOfMonth();
                $end = now()->subMonths($m)->endOfMonth();

                return (float) SalesOrder::where('status', SalesOrder::STATUS_COMPLETED)
                    ->whereBetween('ordered_at', [$start, $end])
                    ->sum('total');
            })
            ->values()
            ->all();

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    public function recentActivity(int $limit = 10): array
    {
        // Reserve half of the feed for each source, otherwise a busy stock
        // ledger (or a busy sales day) can push the other source out entirely.
        $share = max(1, intdiv($limit + 1, 2));

        $stockFeed = $this->stockActivity($share);
        $salesFeed = $this->salesActivity($share);

        // If one source is shorter than its share, widen both queries so the
        // feed still fills up.
        if ($stockFeed->count() + $salesFeed->count() < $limit) {
            $stockFeed = $this->stockActivity($limit);
            $salesFeed = $this->salesActivity($limit);
        }

        return $stockFeed
            ->concat($salesFeed)
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values()
            ->map(fn (array $entry) => collect($entry)->except('timestamp')->all())
            ->all();
    }

    private function stockActivity(int $limit): Collection
    {
        return StockTransaction::with(['product', 'user'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (StockTransaction $transaction) {
                $verb = $transaction->type === StockTransaction::TYPE_IN
                    ? 'received into'
                    : ($transaction->type === StockTransaction::TYPE_OUT
                        ? 'issued from'
                        : 'adjusted in');

                $actor = $transaction->user ? "by {$transaction->user->name}" : '';

                $label = match ($transaction->type) {
                    StockTransaction::TYPE_IN => 'Stock received',
                    StockTransaction::TYPE_OUT => 'Stock issued',
                    default => 'Stock adjusted',
                };

                return [
                    'timestamp' => $transaction->created_at,
                    'icon' => $transaction->type === StockTransaction::TYPE_OUT ? '📤' : '📥',
                    'message' => "{$label}: {$transaction->product->name} ({$transaction->new_quantity} in stock) {$actor}",
                    'time' => $transaction->created_at->diffForHumans(),
                    'verb' => $verb,
                ];
            });
    }

    private function salesActivity(int $limit): Collection
    {
        return SalesOrder::with('customer')
            ->latest('ordered_at')
            ->limit($limit)
            ->get()
            ->map(function (SalesOrder $order) {
                $at = $order->ordered_at ?? $order->created_at;

                return [
                    'timestamp' => $at,
                    'icon' => '🛒',
                    'message' => "Sales order #{$order->id} ({$order->statusLabel()}) "
                        .'for '.($order->customer?->name ?? 'Unknown customer')
                        .' — $'.number_format($order->total, 2),
                    'time' => $at->diffForHumans(),
                    'verb' => 'created',
                ];
            });
    }
}
