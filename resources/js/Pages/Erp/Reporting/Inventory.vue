<script setup>
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import SnapshotActions from '@/Components/Reporting/SnapshotActions.vue';
import StatCard from '@/Components/Cards/StatCard.vue';

const props = defineProps({
    snapshot: { type: Object, required: true },
});

const payload = props.snapshot.payload ?? {};
const summary = payload.summary ?? {};
const valuationByCategory = payload.valuation_by_category ?? [];
const movements = payload.movements ?? {};
const lowStock = payload.low_stock ?? [];

const formatMoney = (value) =>
    `$${Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const formatGeneratedAt = (value) => (value ? new Date(value).toLocaleString() : null);

const movementRows = [
    { label: 'Stock received (in)', key: 'in', icon: '📥', tone: 'green' },
    { label: 'Stock issued (out)', key: 'out', icon: '📤', tone: 'blue' },
    { label: 'Adjustments', key: 'adjustment', icon: '⚖️', tone: 'amber' },
];
</script>

<template>
    <ErpLayout>
        <Head title="Inventory Report" />

        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Inventory Report</h1>
            <p class="mt-1 text-sm text-slate-500">
                Stock valuation by category, low-stock items and ledger movement counts
                — snapshot #{{ snapshot.id }} generated {{ formatGeneratedAt(snapshot.generated_at) }}.
            </p>
                    <div class="mt-4">
                        <SnapshotActions :snapshot="snapshot" />
                    </div>
        </header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Inventory valuation" :value="formatMoney(summary.total_valuation)" icon="💰" tone="green" />
            <StatCard label="Products" :value="summary.product_count ?? 0" icon="📦" tone="blue" />
            <StatCard label="Low stock" :value="summary.low_stock_count ?? 0" icon="⚠️" tone="red" />
            <StatCard label="Ledger movements" :value="summary.movement_count ?? 0" icon="🔁" tone="violet" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <Card title="Valuation by category">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <th class="px-4 py-3">Category</th>
                                    <th class="px-4 py-3 text-right">Products</th>
                                    <th class="px-4 py-3 text-right">Units in stock</th>
                                    <th class="px-4 py-3 text-right">Valuation</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="category in valuationByCategory" :key="category.category_id">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ category.category }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-500">{{ category.product_count }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ category.units }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ formatMoney(category.valuation) }}</td>
                                </tr>
                                <tr v-if="valuationByCategory.length === 0">
                                    <td colspan="4" class="px-4 py-10 text-center text-slate-400">No inventory yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            <Card title="Movement counts">
                <div class="space-y-3">
                    <div
                        v-for="row in movementRows"
                        :key="row.key"
                        class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3"
                    >
                        <p class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <span>{{ row.icon }}</span>
                            {{ row.label }}
                        </p>
                        <p class="text-xl font-bold text-slate-900">{{ movements[row.key] ?? 0 }}</p>
                    </div>
                </div>
            </Card>
        </div>

        <Card title="Low stock items" class="mt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3 text-right">In stock</th>
                            <th class="px-4 py-3 text-right">Threshold</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in lowStock" :key="item.product_id">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ item.name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ item.sku }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ item.category }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium" :class="item.stock_quantity === 0 ? 'text-red-600' : 'text-amber-600'">
                                {{ item.stock_quantity }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-500">{{ item.low_stock_threshold }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ item.status === 'out' ? 'Out of stock' : 'Low' }}</td>
                        </tr>
                        <tr v-if="lowStock.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">No low-stock items — everything is above threshold.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </ErpLayout>
</template>
