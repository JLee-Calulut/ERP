<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    transactions: { type: Array, default: () => [] },
});

const page = usePage();
const canManage = (page.props.auth?.user?.permissions ?? []).includes('inventory.manage');

const statusBadge = (status) => ({
    out: 'bg-slate-100 text-slate-600',
    low: 'bg-amber-100 text-amber-700',
    in: 'bg-emerald-100 text-emerald-700',
}[status] ?? 'bg-slate-100 text-slate-600');

const statusLabel = (status) => ({
    out: 'Out of stock',
    low: 'Low stock',
    in: 'In stock',
}[status] ?? status);

const typeClass = (type) => ({
    in: 'text-emerald-600 bg-emerald-50',
    out: 'text-red-600 bg-red-50',
    adjustment: 'text-blue-600 bg-blue-50',
}[type] ?? 'text-slate-500 bg-slate-100');

const quantityLabel = (transaction) => {
    if (transaction.type === 'in') return `+${Math.abs(transaction.quantity)}`;
    if (transaction.type === 'out') return `-${Math.abs(transaction.quantity)}`;
    return `set to ${transaction.new_quantity}`;
};
</script>

<template>
    <ErpLayout>
        <Head title="Stock" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Stock</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Current stock levels and the recent movement ledger.
                </p>
            </div>
            <Link
                v-if="canManage"
                href="/erp/inventory/stock/create"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
            >+ Record movement</Link>
        </header>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <Card title="Current stock">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">On hand</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="product in products" :key="product.id">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900">{{ product.name }}</p>
                                    <p class="text-xs text-slate-500">{{ product.sku }} · {{ product.category ?? 'Uncategorised' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ product.stock_quantity }}
                                    <span class="text-xs text-slate-400">/ {{ product.low_stock_threshold }} min</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                        :class="statusBadge(product.stock_status)"
                                    >{{ statusLabel(product.stock_status) }}</span>
                                </td>
                            </tr>
                            <tr v-if="products.length === 0">
                                <td colspan="3" class="px-4 py-10 text-center text-slate-400">
                                    No products in the catalog yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card title="Recent movements">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Qty</th>
                                <th class="px-4 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="transaction in transactions" :key="transaction.id">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900">{{ transaction.product }}</p>
                                    <p class="text-xs text-slate-500">{{ transaction.reason ?? transaction.user ?? 'System' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                        :class="typeClass(transaction.type)"
                                    >{{ transaction.type_label }}</span>
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-700">{{ quantityLabel(transaction) }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ transaction.created_at_readable }}</td>
                            </tr>
                            <tr v-if="transactions.length === 0">
                                <td colspan="4" class="px-4 py-10 text-center text-slate-400">
                                    No stock movements recorded yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </ErpLayout>
</template>
