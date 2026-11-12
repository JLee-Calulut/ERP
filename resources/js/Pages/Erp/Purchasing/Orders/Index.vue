<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    purchaseOrders: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', status: null }) },
});

const page = usePage();
const canManage = (page.props.auth?.user?.permissions ?? []).includes('purchasing.manage');

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get('/erp/purchasing/orders', filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const statusBadge = (status) => ({
    draft: 'bg-slate-100 text-slate-600',
    confirmed: 'bg-blue-100 text-blue-700',
    received: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
}[status] ?? 'bg-slate-100 text-slate-600');
</script>

<template>
    <ErpLayout>
        <Head title="Purchase orders" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Purchase orders</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Orders you place with suppliers ({{ purchaseOrders.length }} shown).
                </p>
            </div>
            <Link
                v-if="canManage"
                href="/erp/purchasing/orders/create"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
            >+ New purchase order</Link>
        </header>

        <form class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_220px_auto]" @submit.prevent="applyFilters">
            <input
                v-model="filters.search"
                type="text"
                placeholder="Search by supplier name or email…"
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
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Ordered</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="purchaseOrder in purchaseOrders" :key="purchaseOrder.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">#{{ purchaseOrder.id }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ purchaseOrder.supplier ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusBadge(purchaseOrder.status)">
                                    {{ purchaseOrder.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ purchaseOrder.items_count }}</td>
                            <td class="px-4 py-3 text-slate-900 font-medium">${{ Number(purchaseOrder.total).toFixed(2) }}</td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ purchaseOrder.ordered_at }}
                                <span class="block text-xs text-slate-400">{{ purchaseOrder.ordered_at_readable }}</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Link
                                    :href="`/erp/purchasing/orders/${purchaseOrder.id}`"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50"
                                >View</Link>
                            </td>
                        </tr>
                        <tr v-if="purchaseOrders.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                No purchase orders found{{ filters.search ? ' for your search' : ' yet' }}.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </ErpLayout>
</template>