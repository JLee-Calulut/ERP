<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', type: '' }) },
});

const page = usePage();
const canManage = (page.props.auth?.user?.permissions ?? []).includes('accounting.manage');

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

const filters = reactive({
    search: props.filters.search ?? '',
    type: props.filters.type ?? '',
});

const applyFilters = () => {
    router.get('/erp/accounting/accounts', filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const formatBalance = (value) =>
    `${Number(value) < 0 ? '-' : ''}$${Math.abs(Number(value)).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const confirmDelete = ref(null);

const requestDelete = () => {
    router.delete(`/erp/accounting/accounts/${confirmDelete.value.id}`, {
        onFinish: () => (confirmDelete.value = null),
    });
};
</script>

<template>
    <ErpLayout>
        <Head title="Chart of Accounts" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Chart of Accounts</h1>
                <p class="mt-1 text-sm text-slate-500">
                    The account classifications your journal entries post to ({{ accounts.length }} shown).
                </p>
            </div>
            <Link
                v-if="canManage"
                href="/erp/accounting/accounts/create"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
            >+ New account</Link>
        </header>

        <form class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto]" @submit.prevent="applyFilters">
            <input
                v-model="filters.search"
                type="text"
                placeholder="Search by code or name…"
                class="rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
            />
            <select
                v-model="filters.type"
                class="rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
            >
                <option value="">All types</option>
                <option v-for="type in types" :key="type" :value="type">{{ typeLabels[type] }}</option>
            </select>
            <button
                type="submit"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
            >Filter</button>
        </form>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Account</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3 text-right">Lines</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="account in accounts" :key="account.id">
                            <td class="px-4 py-3 font-mono text-slate-500">{{ account.code }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">
                                    {{ account.name }}
                                    <span v-if="!account.is_active" class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                                        Inactive
                                    </span>
                                </p>
                                <p v-if="account.description" class="max-w-md truncate text-xs text-slate-400">
                                    {{ account.description }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="typeBadges[account.type]">
                                    {{ account.type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900">
                                {{ formatBalance(account.balance) }}
                            </td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ account.lines_count }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Link
                                    v-if="canManage"
                                    :href="`/erp/accounting/accounts/${account.id}/edit`"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50"
                                >Edit</Link>
                                <button
                                    v-if="canManage"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 transition hover:bg-red-50"
                                    @click="confirmDelete = account"
                                >Delete</button>
                            </td>
                        </tr>
                        <tr v-if="accounts.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                                No accounts found{{ filters.search ? ' for your search' : ' yet' }}.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="confirmDelete !== null" max-width="sm" @close="confirmDelete = null">
            <h3 class="text-lg font-semibold text-slate-900">Delete account?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ confirmDelete?.code }} — {{ confirmDelete?.name }}" will be removed. Accounts used by journal
                lines cannot be deleted.
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <button
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    @click="confirmDelete = null"
                >Cancel</button>
                <button
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700"
                    @click="requestDelete"
                >Delete</button>
            </div>
        </Modal>
    </ErpLayout>
</template>
