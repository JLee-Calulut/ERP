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
const spendBySupplier = payload.spend_by_supplier ?? [];
const poValueByStatus = payload.po_value_by_status ?? [];
const receivedVsPending = payload.received_vs_pending ?? {};

const statusTones = {
    received: 'bg-emerald-100 text-emerald-700',
    confirmed: 'bg-blue-100 text-blue-700',
    draft: 'bg-slate-100 text-slate-600',
    cancelled: 'bg-red-100 text-red-700',
};

const formatMoney = (value) =>
    `$${Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const formatGeneratedAt = (value) => (value ? new Date(value).toLocaleString() : null);
</script>

<template>
    <ErpLayout>
        <Head title="Purchasing Report" />

        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Purchasing Report</h1>
            <p class="mt-1 text-sm text-slate-500">
                Spend by supplier, PO value by status and received-vs-pending from purchase orders and
                receipts — snapshot #{{ snapshot.id }} generated {{ formatGeneratedAt(snapshot.generated_at) }}.
            </p>
                    <div class="mt-4">
                        <SnapshotActions :snapshot="snapshot" />
                    </div>
        </header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Total spend" :value="formatMoney(summary.total_spend)" icon="💸" tone="green" />
            <StatCard label="Pending PO value" :value="formatMoney(summary.pending_value)" icon="⏳" tone="amber" />
            <StatCard label="Received value" :value="formatMoney(summary.received_value)" icon="📥" tone="blue" />
            <StatCard label="Purchase orders" :value="summary.order_count ?? 0" icon="🚚" tone="violet" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <Card title="Spend by supplier">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <th class="px-4 py-3">Supplier</th>
                                    <th class="px-4 py-3 text-right">Orders</th>
                                    <th class="px-4 py-3 text-right">PO value</th>
                                    <th class="px-4 py-3 text-right">Received</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="supplier in spendBySupplier" :key="supplier.supplier_id">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ supplier.supplier }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-500">{{ supplier.order_count }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                        {{ formatMoney(supplier.po_value) }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                        {{ supplier.received_value ? formatMoney(supplier.received_value) : '—' }}
                                    </td>
                                </tr>
                                <tr v-if="spendBySupplier.length === 0">
                                    <td colspan="4" class="px-4 py-10 text-center text-slate-400">No purchase orders yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            <Card title="PO value by status">
                <div class="space-y-3">
                    <div
                        v-for="row in poValueByStatus"
                        :key="row.status"
                        class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3"
                    >
                        <div>
                            <p class="text-sm font-medium text-slate-700">
                                {{ row.status_label }}
                                <span
                                    class="ml-2 rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="statusTones[row.status] || 'bg-slate-100 text-slate-600'"
                                >{{ row.count }}</span>
                            </p>
                        </div>
                        <p class="text-lg font-bold tabular-nums text-slate-900">{{ formatMoney(row.value) }}</p>
                    </div>
                    <p v-if="poValueByStatus.length === 0" class="py-6 text-center text-sm text-slate-400">
                        No orders yet.
                    </p>
                </div>
            </Card>
        </div>

        <Card class="mt-6">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-emerald-50 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Received</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700">
                        {{ formatMoney(receivedVsPending.received) }}
                    </p>
                </div>
                <div class="rounded-xl bg-amber-50 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Pending (confirmed, not received)</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-amber-700">
                        {{ formatMoney(receivedVsPending.pending) }}
                    </p>
                </div>
            </div>
        </Card>
    </ErpLayout>
</template>