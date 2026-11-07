<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    invoice: { type: Object, required: true },
});

const statusBadge = (status) => ({
    unpaid: 'bg-amber-100 text-amber-700',
    paid: 'bg-emerald-100 text-emerald-700',
}[status] ?? 'bg-slate-100 text-slate-600');

const grandTotal = computed(() =>
    (props.invoice.items ?? []).reduce((sum, item) => sum + Number(item.line_total), 0),
);
</script>

<template>
    <ErpLayout>
        <Head :title="invoice.invoice_number" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ invoice.invoice_number }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Issued {{ invoice.issued_at_readable }} · Total ${{ Number(invoice.total).toFixed(2) }}
                </p>
            </div>
            <Link
                href="/erp/sales/invoices"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to invoices</Link>
        </header>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadge(invoice.status)">
                {{ invoice.status_label }}
            </span>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <Card title="Line items">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <th class="px-4 py-2">Product</th>
                                    <th class="px-4 py-2">SKU</th>
                                    <th class="px-4 py-2">Unit price</th>
                                    <th class="px-4 py-2">Qty</th>
                                    <th class="px-4 py-2 text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(item, index) in invoice.items" :key="index">
                                    <td class="px-4 py-2 font-medium text-slate-900">{{ item.product ?? 'Deleted product' }}</td>
                                    <td class="px-4 py-2 text-slate-500">{{ item.sku ?? '' }}</td>
                                    <td class="px-4 py-2 text-slate-600">${{ Number(item.unit_price).toFixed(2) }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ item.quantity }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-900">${{ Number(item.line_total).toFixed(2) }}</td>
                                </tr>
                                <tr v-if="invoice.items.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400">No line items.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end border-t border-slate-100 pt-4 text-right">
                        <div>
                            <p class="text-sm text-slate-500">Total</p>
                            <p class="text-lg font-bold text-slate-900">${{ Number(invoice.total).toFixed(2) }}</p>
                        </div>
                    </div>
                </Card>
            </div>

            <div class="space-y-6">
                <Card title="Bill to">
                    <template v-if="invoice.customer">
                        <p class="font-medium text-slate-900">{{ invoice.customer.name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ invoice.customer.email || 'No email' }}</p>
                        <p class="text-sm text-slate-500">{{ invoice.customer.phone || '' }}</p>
                    </template>
                    <p v-else class="text-sm text-slate-500">—</p>
                </Card>

                <Card title="Details">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Invoice number</dt>
                            <dd class="font-medium text-slate-900">{{ invoice.invoice_number }}</dd>
                        </div>
                        <div v-if="invoice.order" class="flex justify-between">
                            <dt class="text-slate-500">Sales order</dt>
                            <dd>
                                <Link
                                    :href="`/erp/sales/orders/${invoice.order.id}`"
                                    class="text-brand-600 hover:text-brand-700"
                                >#{{ invoice.order.id }} ({{ invoice.order.status_label }})</Link>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Issued at</dt>
                            <dd class="text-slate-700">{{ invoice.issued_at }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Status</dt>
                            <dd class="font-medium text-slate-900">{{ invoice.status_label }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
        </div>
    </ErpLayout>
</template>
