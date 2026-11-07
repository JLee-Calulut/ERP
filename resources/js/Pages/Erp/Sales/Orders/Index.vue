<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    orders: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', status: null }) },
});

const page = usePage();
const canManage = (page.props.auth?.user?.permissions ?? []).includes('sales.manage');

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get('/erp/sales/orders', filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const statusBadge = (status) => ({
    draft: 'bg-slate-100 text-slate-600',
    confirmed: 'bg-blue-100 text-blue-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
}[status] ?? 'bg-slate-100 text-slate-600');
</script>

<template>
    <ErpLayout>
        <Head title="Sales orders" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Sales orders</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Orders placed against your catalog ({{ orders.length }} shown).
                </p>
            </div>
            <Link
                v-if="canManage"
                href="/erp/sales/orders/create"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
            >+ New order</Link>
        </header>

        <form class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_220px_auto]" @submit.prevent="applyFilters">
            <input
                v-model="filters.search"
                type="text"
                placeholder="Search by customer name or email…"
                class="rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
            />
            <select
                v-model="filters.status"
                class="rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
            >
                <option value="">All statuses</option>
                <option v-for="status in statuses" :key="status" :value="status">
                    {{ status.charAt(0).toUpperCase() + status.slice(1) }}
                </option>
            </select>
            <button
                type="submit"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
            >Filter</button>
        </form>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Ordered</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="order in orders" :key="order.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">#{{ order.id }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ order.customer ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusBadge(order.status)">
                                    {{ order.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ order.items_count }}</td>
                            <td class="px-4 py-3 text-slate-900 font-medium">${{ Number(order.total).toFixed(2) }}</td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ order.ordered_at }}
                                <span class="block text-xs text-slate-400">{{ order.ordered_at_readable }}</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Link
                                    :href="`/erp/sales/orders/${order.id}`"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50"
                                >View</Link>
                            </td>
                        </tr>
                        <tr v-if="orders.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                No orders found{{ filters.search ? ' for your search' : ' yet' }}.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </ErpLayout>
</template>
