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
const totals = payload.totals ?? {};
const assets = payload.assets ?? [];
const liabilities = payload.liabilities ?? [];
const equity = payload.equity ?? [];
const retainedEarnings = payload.retained_earnings ?? 0;
const ledger = payload.ledger ?? {};

const formatMoney = (value) =>
    `${Number(value) < 0 ? '-' : ''}$${Math.abs(Number(value)).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const formatGeneratedAt = (value) => (value ? new Date(value).toLocaleString() : null);
</script>

<template>
    <ErpLayout>
        <Head title="Balance Sheet" />

        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Balance Sheet</h1>
            <p class="mt-1 text-sm text-slate-500">
                Assets, liabilities and equity derived from the journal lines, with retained earnings
                rolled up from net income — snapshot #{{ snapshot.id }} generated
                {{ formatGeneratedAt(snapshot.generated_at) }}.
            </p>
                    <div class="mt-4">
                        <SnapshotActions :snapshot="snapshot" />
                    </div>
        </header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Total assets" :value="formatMoney(summary.total_assets)" icon="💼" tone="blue" />
            <StatCard label="Total liabilities" :value="formatMoney(summary.total_liabilities)" icon="🧾" tone="amber" />
            <StatCard label="Total equity" :value="formatMoney(summary.total_equity)" icon="📊" tone="violet" />
            <StatCard
                label="Ledger status"
                :value="totals.balanced ? 'Balanced' : 'Out of balance'"
                icon="⚖️"
                :tone="totals.balanced ? 'green' : 'red'"
            />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <Card title="Assets">
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
                            <tr v-for="account in assets" :key="account.account_id">
                                <td class="px-4 py-3 font-mono text-slate-500">{{ account.code }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ account.name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                    {{ formatMoney(account.amount) }}
                                </td>
                            </tr>
                            <tr v-if="assets.length === 0">
                                <td colspan="3" class="px-4 py-10 text-center text-slate-400">No asset postings yet.</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="assets.length > 0" class="border-t border-slate-200 bg-slate-50">
                            <tr class="text-sm font-semibold text-slate-900">
                                <td colspan="2" class="px-4 py-3">Total assets</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(totals.assets) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>

            <Card title="Liabilities">
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
                            <tr v-for="account in liabilities" :key="account.account_id">
                                <td class="px-4 py-3 font-mono text-slate-500">{{ account.code }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ account.name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                    {{ formatMoney(account.amount) }}
                                </td>
                            </tr>
                            <tr v-if="liabilities.length === 0">
                                <td colspan="3" class="px-4 py-10 text-center text-slate-400">No liability postings yet.</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="liabilities.length > 0" class="border-t border-slate-200 bg-slate-50">
                            <tr class="text-sm font-semibold text-slate-900">
                                <td colspan="2" class="px-4 py-3">Total liabilities</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(totals.liabilities) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>

            <Card title="Equity">
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
                            <tr v-for="account in equity" :key="account.account_id">
                                <td class="px-4 py-3 font-mono text-slate-500">{{ account.code }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ account.name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                    {{ formatMoney(account.amount) }}
                                </td>
                            </tr>
                            <tr class="bg-slate-50">
                                <td class="px-4 py-3 font-mono text-slate-500">—</td>
                                <td class="px-4 py-3 font-medium text-slate-700">Retained earnings (net income)</td>
                                <td
                                    class="px-4 py-3 text-right tabular-nums font-medium"
                                    :class="Number(retainedEarnings) < 0 ? 'text-red-600' : 'text-slate-900'"
                                >{{ formatMoney(retainedEarnings) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-slate-200 bg-slate-50">
                            <tr class="text-sm font-semibold text-slate-900">
                                <td colspan="2" class="px-4 py-3">Total equity</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(totals.equity) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>
        </div>

        <Card class="mt-6">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total assets</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">{{ formatMoney(totals.assets) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total liabilities + equity</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">
                        {{ formatMoney(totals.liabilities_and_equity) }}
                    </p>
                </div>
                <div class="rounded-xl bg-slate-50 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Ledger (trial balance)</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">
                        {{ formatMoney(ledger.total_debits) }} = {{ formatMoney(ledger.total_credits) }}
                    </p>
                </div>
            </div>
            <p
                v-if="totals.balanced"
                class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
            >The sheet balances: assets equal liabilities plus equity.</p>
            <p
                v-else
                class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
            >The sheet does not balance — assets do not equal liabilities plus equity.</p>
        </Card>
    </ErpLayout>
</template>