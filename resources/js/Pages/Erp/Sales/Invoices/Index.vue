<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    invoices: { type: Array, default: () => [] },
});

const page = usePage();

const statusBadge = (status) => ({
    unpaid: 'bg-amber-100 text-amber-700',
    paid: 'bg-emerald-100 text-emerald-700',
}[status] ?? 'bg-slate-100 text-slate-600');
</script>

<template>
    <ErpLayout>
        <Head title="Invoices" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Invoices</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Billing documents issued for your sales orders ({{ invoices.length }} shown).
                </p>
            </div>
        </header>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Invoice</th>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Issued</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="invoice in invoices" :key="invoice.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ invoice.invoice_number }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <Link
                                    v-if="invoice.order_id"
                                    :href="`/erp/sales/orders/${invoice.order_id}`"
                                    class="text-brand-600 hover:text-brand-700"
                                >#{{ invoice.order_id }}</Link>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ invoice.customer ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">${{ Number(invoice.total).toFixed(2) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusBadge(invoice.status)">
                                    {{ invoice.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ invoice.issued_at }}
                                <span class="block text-xs text-slate-400">{{ invoice.issued_at_readable }}</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Link
                                    :href="`/erp/sales/invoices/${invoice.id}`"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50"
                                >View</Link>
                            </td>
                        </tr>
                        <tr v-if="invoices.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                No invoices yet. Generate one from a confirmed or completed order.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </ErpLayout>
</template>
