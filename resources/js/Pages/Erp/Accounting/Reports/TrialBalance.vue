<script setup>
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({ debit: 0, credit: 0, balanced: false }) },
});

const typeLabels = {
    asset: 'Asset',
    liability: 'Liability',
    equity: 'Equity',
    revenue: 'Revenue',
    expense: 'Expense',
};

const typeBadges = {
    asset: 'bg-sky-100 text-sky-700',
    liability: 'bg-orange-100 text-orange-700',
    equity: 'bg-violet-100 text-violet-700',
    revenue: 'bg-emerald-100 text-emerald-700',
    expense: 'bg-rose-100 text-rose-700',
};

const formatMoney = (value) =>
    `${Number(value) < 0 ? '-' : ''}$${Math.abs(Number(value)).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
</script>

<template>
    <ErpLayout>
        <Head title="Trial Balance" />

        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Trial Balance</h1>
            <p class="mt-1 text-sm text-slate-500">
                Every account's debit and credit totals from the journal, with its derived balance
                ({{ accounts.length }} accounts).
            </p>
        </header>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Card>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total debits</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(totals.debit) }}</p>
            </Card>
            <Card>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total credits</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(totals.credit) }}</p>
            </Card>
            <Card>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Ledger status</p>
                <p class="mt-1 text-2xl font-bold" :class="totals.balanced ? 'text-emerald-600' : 'text-red-600'">
                    {{ totals.balanced ? 'Balanced' : 'Out of balance' }}
                </p>
            </Card>
        </div>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Account</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3 text-right">Debit total</th>
                            <th class="px-4 py-3 text-right">Credit total</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="account in accounts" :key="account.id">
                            <td class="px-4 py-3 font-mono text-slate-500">{{ account.code }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ account.name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="typeBadges[account.type]">
                                    {{ account.type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                {{ account.debit_total ? formatMoney(account.debit_total) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                {{ account.credit_total ? formatMoney(account.credit_total) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-slate-900">
                                {{ account.balance ? formatMoney(account.balance) : '—' }}
                            </td>
                        </tr>
                        <tr v-if="accounts.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                                No accounts to show yet.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="accounts.length > 0" class="border-t border-slate-200 bg-slate-50">
                        <tr class="text-sm font-semibold text-slate-900">
                            <td colspan="3" class="px-4 py-3">Totals</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(totals.debit) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(totals.credit) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ totals.debit - totals.credit === 0 ? '—' : formatMoney(totals.debit - totals.credit) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </Card>
    </ErpLayout>
</template>
