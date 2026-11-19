<script setup>
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import StatCard from '@/Components/Cards/StatCard.vue';
import RevenueTrend from '@/Components/Charts/RevenueTrend.vue';
import SnapshotActions from '@/Components/Reporting/SnapshotActions.vue';

const props = defineProps({
    snapshot: { type: Object, required: true },
});

const payload = props.snapshot.payload ?? {};
const summary = payload.summary ?? {};
const revenueByMonth = payload.revenue_by_month ?? [];
const topProducts = payload.top_products ?? [];
const topCustomers = payload.top_customers ?? [];

const formatMoney = (value) =>
    `$${Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const formatGeneratedAt = (value) => (value ? new Date(value).toLocaleString() : null);
</script>

<template>
    <ErpLayout>
        <Head title="Sales Report" />

        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Sales Report</h1>
            <p class="mt-1 text-sm text-slate-500">
                Revenue by month, top products and customers, invoice count and average order value
                — snapshot #{{ snapshot.id }} generated {{ formatGeneratedAt(snapshot.generated_at) }}.
            </p>
                    <div class="mt-4">
                        <SnapshotActions :snapshot="snapshot" />
                    </div>
        </header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Total revenue" :value="formatMoney(summary.total_revenue)" icon="💰" tone="green" />
            <StatCard label="Completed orders" :value="summary.order_count ?? 0" icon="🛒" tone="blue" />
            <StatCard label="Average order value" :value="formatMoney(summary.average_order_value)" icon="📊" tone="violet" />
            <StatCard label="Invoices" :value="summary.invoice_count ?? 0" icon="🧾" tone="amber" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <Card title="Revenue by month">
                    <RevenueTrend
                        :labels="revenueByMonth.map((entry) => entry.label)"
                        :values="revenueByMonth.map((entry) => entry.revenue)"
                    />
                </Card>
            </div>

            <Card title="Top customers">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-3 py-2">Customer</th>
                            <th class="px-3 py-2 text-right">Orders</th>
                            <th class="px-3 py-2 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="customer in topCustomers" :key="customer.customer_id">
                            <td class="px-3 py-2 font-medium text-slate-900">{{ customer.name }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ customer.order_count }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ formatMoney(customer.revenue) }}</td>
                        </tr>
                        <tr v-if="topCustomers.length === 0">
                            <td colspan="3" class="px-3 py-8 text-center text-slate-400">No customers yet.</td>
                        </tr>
                    </tbody>
                </table>
            </Card>
        </div>

        <Card title="Top products by revenue" class="mt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 text-right">Quantity sold</th>
                            <th class="px-4 py-3 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(product, index) in topProducts" :key="product.product_id">
                            <td class="px-4 py-3 text-slate-400">{{ index + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ product.name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ product.quantity }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ formatMoney(product.revenue) }}</td>
                        </tr>
                        <tr v-if="topProducts.length === 0">
                            <td colspan="4" class="px-4 py-10 text-center text-slate-400">No products sold yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </ErpLayout>
</template>
