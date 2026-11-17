<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    postings: { type: Array, default: () => [] },
    selectedAccount: { type: Object, default: null },
    filters: { type: Object, default: () => ({ account: null }) },
});

const filters = reactive({
    account: props.filters.account ?? '',
});

const applyFilters = () => {
    router.get('/erp/accounting/reports/ledger', filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const formatMoney = (value) =>
    `${Number(value) < 0 ? '-' : ''}$${Math.abs(Number(value)).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const showAccount = (posting) => !props.selectedAccount;
</script>

<template>
    <ErpLayout>
        <Head title="General Ledger" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">General Ledger</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Per-account transaction history with a running balance ({{ postings.length }} postings
                    {{ selectedAccount ? `for ${selectedAccount.code} — ${selectedAccount.name}` : 'across all accounts' }}).
                </p>
            </div>
            <form class="flex items-end gap-2" @submit.prevent="applyFilters">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Account
                    </label>
                    <select
                        v-model="filters.account"
                        class="rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                    >
                        <option value="">All accounts</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">
                            {{ account.code }} — {{ account.name }}
                        </option>
                    </select>
                </div>
                <button
                    type="submit"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                >Filter</button>
            </form>
        </header>

        <div v-if="selectedAccount" class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Card>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Account</p>
                <p class="mt-1 text-xl font-bold text-slate-900">
                    {{ selectedAccount.code }} — {{ selectedAccount.name }}
                </p>
            </Card>
            <Card>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Type</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ selectedAccount.type_label }}</p>
            </Card>
            <Card>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Final balance</p>
                <p class="mt-1 text-xl font-bold" :class="selectedAccount.balance >= 0 ? 'text-emerald-600' : 'text-red-600'">
                    {{ formatMoney(selectedAccount.balance) }}
                </p>
            </Card>
        </div>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Entry</th>
                            <th class="px-4 py-3">Description</th>
                            <th v-if="showAccount" class="px-4 py-3">Account</th>
                            <th class="px-4 py-3 text-right">Debit</th>
                            <th class="px-4 py-3 text-right">Credit</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="posting in postings" :key="posting.id">
                            <td class="px-4 py-3 text-slate-700">{{ posting.journal_date }}</td>
                            <td class="px-4 py-3">
                                <p class="font-mono font-medium text-slate-900">{{ posting.entry_number }}</p>
                                <p v-if="posting.memo" class="text-xs text-slate-400">{{ posting.memo }}</p>
                            </td>
                            <td class="max-w-md px-4 py-3 text-slate-700">{{ posting.description }}</td>
                            <td v-if="showAccount" class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ posting.account_name }}</p>
                                <p class="font-mono text-xs text-slate-400">{{ posting.account_code }}</p>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                {{ posting.debit ? formatMoney(posting.debit) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                {{ posting.credit ? formatMoney(posting.credit) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-slate-900">
                                {{ formatMoney(posting.balance) }}
                            </td>
                        </tr>
                        <tr v-if="postings.length === 0">
                            <td :colspan="showAccount ? 7 : 6" class="px-4 py-10 text-center text-slate-400">
                                No postings found{{ selectedAccount ? ' for this account' : ' yet' }}.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </ErpLayout>
</template>
