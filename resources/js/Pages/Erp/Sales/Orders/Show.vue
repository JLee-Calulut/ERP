<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    order: { type: Object, required: true },
});

const page = usePage();
const canManage = (page.props.auth?.user?.permissions ?? []).includes('sales.manage');

const statusBadge = (status) => ({
    draft: 'bg-slate-100 text-slate-600',
    confirmed: 'bg-blue-100 text-blue-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
}[status] ?? 'bg-slate-100 text-slate-600');

const transitionLabels = {
    confirmed: 'Confirm order',
    completed: 'Mark completed',
    cancelled: 'Cancel order',
};

const changeStatus = (status) => {
    router.patch(`/erp/sales/orders/${props.order.id}/status`, { status }, {
        preserveScroll: true,
    });
};

const generateInvoice = () => {
    router.post(`/erp/sales/orders/${props.order.id}/invoice`);
};

const canInvoice = computed(() =>
    canManage &&
    ['confirmed', 'completed'].includes(props.order.status) &&
    props.order.invoice === null,
);

const transitionButtons = computed(() =>
    canManage
        ? (props.order.available_transitions ?? []).map((status) => ({
              status,
              label: transitionLabels[status] ?? status,
          }))
        : [],
);
</script>

<template>
    <ErpLayout>
        <Head :title="`Sales order #${order.id}`" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Sales order #{{ order.id }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ order.ordered_at_readable }} · Totals ${{ Number(order.total).toFixed(2) }}
                </p>
            </div>
            <Link
                href="/erp/sales/orders"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to orders</Link>
        </header>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadge(order.status)">
                {{ order.status_label }}
            </span>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="button in transitionButtons"
                    :key="button.status"
                    class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-700"
                    @click="changeStatus(button.status)"
                >{{ button.label }}</button>
                <button
                    v-if="canInvoice"
                    class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700"
                    @click="generateInvoice"
                >Generate invoice</button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:col-span-2 xl:grid-cols-1">
                <Card title="Line items">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <th class="px-4 py-2">Product</th>
                                    <th class="px-4 py-2">Unit price</th>
                                    <th class="px-4 py-2">Qty</th>
                                    <th class="px-4 py-2 text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in order.items" :key="item.id">
                                    <td class="px-4 py-2">
                                        <p class="font-medium text-slate-900">{{ item.product?.name ?? 'Deleted product' }}</p>
                                        <p class="text-xs text-slate-500">{{ item.product?.sku ?? '' }}</p>
                                    </td>
                                    <td class="px-4 py-2 text-slate-600">${{ Number(item.unit_price).toFixed(2) }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ item.quantity }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-900">${{ Number(item.line_total).toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end gap-8 border-t border-slate-100 pt-4 text-right">
                        <div>
                            <p class="text-sm text-slate-500">Subtotal</p>
                            <p class="font-semibold text-slate-900">${{ Number(order.subtotal).toFixed(2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">Total</p>
                            <p class="text-lg font-bold text-slate-900">${{ Number(order.total).toFixed(2) }}</p>
                        </div>
                    </div>
                </Card>
            </div>

            <div class="space-y-6">
                <Card title="Customer">
                    <template v-if="order.customer">
                        <p class="font-medium text-slate-900">{{ order.customer.name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ order.customer.email || 'No email' }}</p>
                        <p class="text-sm text-slate-500">{{ order.customer.phone || '' }}</p>
                        <Link
                            :href="`/erp/sales/customers/${order.customer.id}/edit`"
                            class="mt-3 inline-block text-sm font-medium text-brand-600 hover:text-brand-700"
                        >View customer</Link>
                    </template>
                    <p v-else class="text-sm text-slate-500">—</p>
                </Card>

                <Card title="Invoice">
                    <template v-if="order.invoice">
                        <Link
                            :href="`/erp/sales/invoices/${order.invoice.id}`"
                            class="font-medium text-brand-600 hover:text-brand-700"
                        >
                            {{ order.invoice.invoice_number }}
                        </Link>
                        <p class="mt-1 text-sm text-slate-500">{{ order.invoice.status_label }}</p>
                    </template>
                    <p v-else class="text-sm text-slate-500">
                        No invoice yet{{ ['confirmed', 'completed'].includes(order.status) ? ' — the order is ready to be invoiced.' : '.' }}
                    </p>
                </Card>

                <Card title="Summary">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Ordered at</dt>
                            <dd class="text-slate-700">{{ order.ordered_at }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Status</dt>
                            <dd class="font-medium text-slate-900">{{ order.status_label }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
        </div>
    </ErpLayout>
</template>
