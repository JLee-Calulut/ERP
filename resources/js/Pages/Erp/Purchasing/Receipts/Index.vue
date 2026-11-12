<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    receipts: { type: Array, default: () => [] },
});
</script>

<template>
    <ErpLayout>
        <Head title="Purchase receipts" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Purchase receipts</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Stock received against your purchase orders ({{ receipts.length }} shown).
                </p>
            </div>
            <Link
                href="/erp/purchasing/orders"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >View purchase orders</Link>
        </header>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Receipt</th>
                            <th class="px-4 py-3">Purchase order</th>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Received by</th>
                            <th class="px-4 py-3">Received at</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="receipt in receipts" :key="receipt.id">
                            <td class="px-4 py-3 font-medium text-slate-900">#{{ receipt.id }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                <Link
                                    :href="`/erp/purchasing/orders/${receipt.purchase_order_id}`"
                                    class="text-brand-600 hover:text-brand-700"
                                >#{{ receipt.purchase_order_id }}</Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ receipt.supplier ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">${{ Number(receipt.total).toFixed(2) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ receipt.received_by ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ receipt.received_at }}
                                <span class="block text-xs text-slate-400">{{ receipt.received_at_readable }}</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Link
                                    :href="`/erp/purchasing/receipts/${receipt.id}`"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50"
                                >View</Link>
                            </td>
                        </tr>
                        <tr v-if="receipts.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                No receipts yet. Receive stock against a confirmed purchase order.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </ErpLayout>
</template>
