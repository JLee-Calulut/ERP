<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    purchaseOrder: { type: Object, required: true },
});

const page = usePage();
const canManage = (page.props.auth?.user?.permissions ?? []).includes('purchasing.manage');
const canReceive = computed(() => canManage && props.purchaseOrder.can_receive);

const statusBadge = (status) => ({
    draft: 'bg-slate-100 text-slate-600',
    confirmed: 'bg-blue-100 text-blue-700',
    received: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
}[status] ?? 'bg-slate-100 text-slate-600');

const transitionLabels = {
    confirmed: 'Confirm order',
    cancelled: 'Cancel order',
};

const changeStatus = (status) => {
    router.patch(`/erp/purchasing/orders/${props.purchaseOrder.id}/status`, { status }, {
        preserveScroll: true,
    });
};

const transitionButtons = computed(() =>
    canManage
        ? (props.purchaseOrder.available_transitions ?? []).map((status) => ({
              status,
              label: transitionLabels[status] ?? status,
          }))
        : [],
);

const confirmDelete = ref(false);

const requestDelete = () => {
    router.delete(`/erp/purchasing/orders/${props.purchaseOrder.id}`);
};

const receiveModal = ref(false);
const submitting = ref(false);

const receiveForm = reactive({
    notes: '',
    items: (props.purchaseOrder.items ?? []).map((item) => ({
        purchase_order_item_id: item.id,
        quantity_received: item.remaining ?? 0,
    })),
});

const receive = () => {
    submitting.value = true;

    router.post(`/erp/purchasing/orders/${props.purchaseOrder.id}/receipts`, {
        notes: receiveForm.notes,
        items: receiveForm.items.filter((item) => Number(item.quantity_received) > 0),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            receiveModal.value = false;
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
};
</script>

<template>
    <ErpLayout>
        <Head :title="`Purchase order #${purchaseOrder.id}`" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Purchase order #{{ purchaseOrder.id }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ purchaseOrder.ordered_at_readable }} · Totals ${{ Number(purchaseOrder.total).toFixed(2) }}
                </p>
            </div>
            <Link
                href="/erp/purchasing/orders"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to purchase orders</Link>
        </header>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadge(purchaseOrder.status)">
                {{ purchaseOrder.status_label }}
            </span>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="button in transitionButtons"
                    :key="button.status"
                    class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-700"
                    @click="changeStatus(button.status)"
                >{{ button.label }}</button>
                <button
                    v-if="canReceive"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                    @click="receiveModal = true"
                >Receive stock</button>
                <button
                    v-if="canManage && purchaseOrder.status !== 'received'"
                    class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-red-700"
                    @click="confirmDelete = true"
                >Delete</button>
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
                                    <th class="px-4 py-2">Unit cost</th>
                                    <th class="px-4 py-2">Qty</th>
                                    <th class="px-4 py-2">Received</th>
                                    <th class="px-4 py-2 text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in purchaseOrder.items" :key="item.id">
                                    <td class="px-4 py-2">
                                        <p class="font-medium text-slate-900">{{ item.product?.name ?? 'Deleted product' }}</p>
                                        <p class="text-xs text-slate-500">{{ item.product?.sku ?? '' }}</p>
                                    </td>
                                    <td class="px-4 py-2 text-slate-600">${{ Number(item.unit_cost).toFixed(2) }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ item.quantity }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ item.received ?? 0 }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-900">${{ Number(item.line_total).toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end gap-8 border-t border-slate-100 pt-4 text-right">
                        <div>
                            <p class="text-sm text-slate-500">Subtotal</p>
                            <p class="font-semibold text-slate-900">${{ Number(purchaseOrder.subtotal).toFixed(2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">Total</p>
                            <p class="text-lg font-bold text-slate-900">${{ Number(purchaseOrder.total).toFixed(2) }}</p>
                        </div>
                    </div>
                </Card>
            </div>

            <div class="space-y-6">
                <Card title="Supplier">
                    <template v-if="purchaseOrder.supplier">
                        <p class="font-medium text-slate-900">{{ purchaseOrder.supplier.name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ purchaseOrder.supplier.email || 'No email' }}</p>
                        <p class="text-sm text-slate-500">{{ purchaseOrder.supplier.phone || '' }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ [purchaseOrder.supplier.city, purchaseOrder.supplier.country].filter(Boolean).join(', ') || '' }}
                        </p>
                        <Link
                            v-if="canManage"
                            :href="`/erp/purchasing/suppliers/${purchaseOrder.supplier.id}/edit`"
                            class="mt-3 inline-block text-sm font-medium text-brand-600 hover:text-brand-700"
                        >View supplier</Link>
                    </template>
                    <p v-else class="text-sm text-slate-500">—</p>
                </Card>

                <Card title="Summary">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Ordered at</dt>
                            <dd class="text-slate-700">{{ purchaseOrder.ordered_at }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Status</dt>
                            <dd class="font-medium text-slate-900">{{ purchaseOrder.status_label }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Created by</dt>
                            <dd class="text-slate-700">{{ purchaseOrder.created_by ?? '—' }}</dd>
                        </div>
                    </dl>
                </Card>

                <Card v-if="purchaseOrder.receipts?.length" title="Receipts">
                    <ul class="space-y-2 text-sm">
                        <li v-for="receipt in purchaseOrder.receipts" :key="receipt.id">
                            <Link
                                :href="`/erp/purchasing/receipts/${receipt.id}`"
                                class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 transition hover:border-brand-300 hover:bg-brand-50"
                            >
                                <span>
                                    <span class="block font-medium text-slate-900">Receipt #{{ receipt.id }}</span>
                                    <span class="text-xs text-slate-400">{{ receipt.received_at_readable }} · {{ receipt.received_by ?? '—' }}</span>
                                </span>
                                <span class="font-semibold text-slate-900">${{ Number(receipt.total).toFixed(2) }}</span>
                            </Link>
                        </li>
                    </ul>
                </Card>

                <Card v-if="purchaseOrder.notes" title="Notes">
                    <p class="text-sm text-slate-600">{{ purchaseOrder.notes }}</p>
                </Card>
            </div>
        </div>

        <Modal :show="confirmDelete" max-width="sm" @close="confirmDelete = false">
            <h3 class="text-lg font-semibold text-slate-900">Delete purchase order?</h3>
            <p class="mt-2 text-sm text-slate-500">
                Purchase order #{{ purchaseOrder.id }} will be removed. This cannot be undone.
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <button
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    @click="confirmDelete = false"
                >Cancel</button>
                <button
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700"
                    @click="requestDelete"
                >Delete</button>
            </div>
        </Modal>

        <Modal :show="receiveModal" max-width="2xl" @close="receiveModal = false">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-slate-900">Receive stock</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Record quantities received against purchase order #{{ purchaseOrder.id }}. Stock is added to inventory on save.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-2">Product</th>
                            <th class="px-4 py-2">Ordered</th>
                            <th class="px-4 py-2">Received</th>
                            <th class="px-4 py-2">Remaining</th>
                            <th class="px-4 py-2">Receive now</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(entry, index) in receiveForm.items" :key="entry.purchase_order_item_id">
                            <td class="px-4 py-2 font-medium text-slate-900">{{ purchaseOrder.items[index]?.product?.name }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ purchaseOrder.items[index]?.quantity }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ purchaseOrder.items[index]?.received ?? 0 }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ purchaseOrder.items[index]?.remaining ?? 0 }}</td>
                            <td class="px-4 py-2">
                                <input
                                    v-model="entry.quantity_received"
                                    type="number"
                                    min="0"
                                    :max="purchaseOrder.items[index]?.remaining ?? 0"
                                    class="w-24 rounded-lg border-slate-300 text-sm"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="receipt-notes">Notes</label>
                <textarea
                    id="receipt-notes"
                    v-model="receiveForm.notes"
                    rows="2"
                    placeholder="Optional note for this receipt"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                ></textarea>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    @click="receiveModal = false"
                >Cancel</button>
                <button
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    :disabled="submitting"
                    @click="receive"
                >{{ submitting ? 'Recording…' : 'Record receipt' }}</button>
            </div>
        </Modal>
    </ErpLayout>
</template>