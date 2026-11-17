<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    receipt: { type: Object, required: true },
});

const page = usePage();
const canPost = (page.props.auth?.user?.permissions ?? []).includes('accounting.manage');

const postToAccounting = () => {
    router.post(`/erp/purchasing/receipts/${props.receipt.id}/post`, {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <ErpLayout>
        <Head :title="`Purchase receipt #${receipt.id}`" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Purchase receipt #{{ receipt.id }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Received {{ receipt.received_at_readable }} · Total ${{ Number(receipt.total).toFixed(2) }}
                </p>
            </div>
            <Link
                href="/erp/purchasing/receipts"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to receipts</Link>
        </header>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                Received
            </span>
            <span
                v-if="receipt.posted"
                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"
            >
                Posted to accounting · {{ receipt.journal_entry_number }}
            </span>
            <button
                v-if="canPost && !receipt.posted"
                class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700"
                @click="postToAccounting"
            >Post to accounting</button>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <Card title="Received items">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <th class="px-4 py-2">Product</th>
                                    <th class="px-4 py-2">SKU</th>
                                    <th class="px-4 py-2">Ordered</th>
                                    <th class="px-4 py-2">Received</th>
                                    <th class="px-4 py-2">Unit cost</th>
                                    <th class="px-4 py-2 text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(item, index) in receipt.items" :key="index">
                                    <td class="px-4 py-2 font-medium text-slate-900">{{ item.product ?? 'Deleted product' }}</td>
                                    <td class="px-4 py-2 text-slate-500">{{ item.sku ?? '' }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ item.ordered_quantity ?? '—' }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ item.quantity_received }}</td>
                                    <td class="px-4 py-2 text-slate-600">${{ Number(item.unit_cost ?? 0).toFixed(2) }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-900">${{ Number(item.line_total ?? 0).toFixed(2) }}</td>
                                </tr>
                                <tr v-if="receipt.items.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-400">No received items.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end border-t border-slate-100 pt-4 text-right">
                        <div>
                            <p class="text-sm text-slate-500">Total</p>
                            <p class="text-lg font-bold text-slate-900">${{ Number(receipt.total).toFixed(2) }}</p>
                        </div>
                    </div>
                </Card>
            </div>

            <div class="space-y-6">
                <Card title="Supplier">
                    <template v-if="receipt.supplier">
                        <p class="font-medium text-slate-900">{{ receipt.supplier.name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ receipt.supplier.email || 'No email' }}</p>
                        <p class="text-sm text-slate-500">{{ receipt.supplier.phone || '' }}</p>
                    </template>
                    <p v-else class="text-sm text-slate-500">—</p>
                </Card>

                <Card title="Details">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Purchase order</dt>
                            <dd v-if="receipt.purchase_order" class="text-slate-700">
                                <Link
                                    :href="`/erp/purchasing/orders/${receipt.purchase_order.id}`"
                                    class="text-brand-600 hover:text-brand-700"
                                >#{{ receipt.purchase_order.id }}</Link>
                                <span class="ml-1 text-xs text-slate-400">({{ receipt.purchase_order.status_label }})</span>
                            </dd>
                            <dd v-else class="text-slate-700">—</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Ordered total</dt>
                            <dd class="text-slate-700" v-if="receipt.purchase_order">${{ Number(receipt.purchase_order.total).toFixed(2) }}</dd>
                            <dd class="text-slate-700" v-else>—</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Received at</dt>
                            <dd class="text-slate-700">{{ receipt.received_at }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Received by</dt>
                            <dd class="text-slate-700">{{ receipt.received_by ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Accounting</dt>
                            <template v-if="receipt.posted">
                                <dd class="font-medium text-emerald-700">
                                    {{ receipt.journal_entry_number }}
                                    <span v-if="receipt.posted_at_readable" class="block text-xs font-normal text-slate-400">
                                        posted {{ receipt.posted_at_readable }}
                                    </span>
                                </dd>
                            </template>
                            <dd v-else class="text-slate-500">Not posted</dd>
                        </div>
                    </dl>
                </Card>

                <Card v-if="receipt.notes" title="Notes">
                    <p class="text-sm text-slate-600">{{ receipt.notes }}</p>
                </Card>
            </div>
        </div>
    </ErpLayout>
</template>
