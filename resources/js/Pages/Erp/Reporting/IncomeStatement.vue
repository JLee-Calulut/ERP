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
const revenue = payload.revenue ?? [];
const expenses = payload.expenses ?? [];
const totals = payload.totals ?? {};

const formatMoney = (value) =>
    `${Number(value) < 0 ? '-' : ''}$${Math.abs(Number(value)).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const formatGeneratedAt = (value) => (value ? new Date(value).toLocaleString() : null);
</script>

<template>
    <ErpLayout>
        <Head title="Income Statement" />

        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Income Statement</h1>
            <p class="mt-1 text-sm text-slate-500">
                Revenue and expenses derived from the journal lines, closed to a net income total
                — snapshot #{{ snapshot.id }} generated {{ formatGeneratedAt(snapshot.generated_at) }}.
            </p>
                    <div class="mt-4">
                        <SnapshotActions :snapshot="snapshot" />
                    </div>
        </header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatCard label="Total revenue" :value="formatMoney(summary.total_revenue)" icon="📈" tone="green" />
            <StatCard label="Total expenses" :value="formatMoney(summary.total_expenses)" icon="📉" tone="amber" />
            <StatCard
                label="Net income"
                :value="formatMoney(summary.net_income)"
                icon="🧮"
                :tone="Number(summary.net_income) < 0 ? 'red' : 'blue'"
            />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <Card title="Revenue">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Account</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="account in revenue" :key="account.account_id">
                                <td class="px-4 py-3 font-mono text-slate-500">{{ account.code }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ account.name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                    {{ formatMoney(account.amount) }}
                                </td>
                            </tr>
                            <tr v-if="revenue.length === 0">
                                <td colspan="3" class="px-4 py-10 text-center text-slate-400">No revenue postings yet.</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="revenue.length > 0" class="border-t border-slate-200 bg-slate-50">
                            <tr class="text-sm font-semibold text-slate-900">
                                <td colspan="2" class="px-4 py-3">Total revenue</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(totals.revenue) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>

            <Card title="Expenses">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Account</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="account in expenses" :key="account.account_id">
                                <td class="px-4 py-3 font-mono text-slate-500">{{ account.code }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ account.name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                    {{ formatMoney(account.amount) }}
                                </td>
                            </tr>
                            <tr v-if="expenses.length === 0">
                                <td colspan="3" class="px-4 py-10 text-center text-slate-400">No expense postings yet.</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="expenses.length > 0" class="border-t border-slate-200 bg-slate-50">
                            <tr class="text-sm font-semibold text-slate-900">
                                <td colspan="2" class="px-4 py-3">Total expenses</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(totals.expenses) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>
        </div>

        <Card class="mt-6">
            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-4">
                <p class="text-sm font-semibold text-slate-700">Net income (revenue − expenses)</p>
                <p
                    class="text-2xl font-bold tabular-nums"
                    :class="Number(summary.net_income) < 0 ? 'text-red-600' : 'text-emerald-600'"
                >{{ formatMoney(summary.net_income) }}</p>
            </div>
        </Card>
    </ErpLayout>
</template>