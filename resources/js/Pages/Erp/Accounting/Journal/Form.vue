<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import InputError from '@/Components/Forms/InputError.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
});

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    journal_date: today,
    description: '',
    lines: [],
});

const lines = ref([]);

const newLine = () => ({
    account_id: '',
    debit: '',
    credit: '',
    memo: '',
});

const addLine = () => {
    lines.value.push(newLine());
};

const removeLine = (index) => {
    lines.value.splice(index, 1);
};

const lineTotal = (line, side) => {
    const value = Number(line[side]);
    return Number.isFinite(value) && value > 0 ? value : 0;
};

const totalDebit = computed(() =>
    lines.value.reduce((sum, line) => sum + lineTotal(line, 'debit'), 0),
);

const totalCredit = computed(() =>
    lines.value.reduce((sum, line) => sum + lineTotal(line, 'credit'), 0),
);

const difference = computed(() => totalDebit.value - totalCredit.value);

const hasCompleteAccount = computed(() =>
    lines.value.length > 0 && lines.value.every((line) => line.account_id !== ''),
);

const isBalanced = computed(() => {
    const rounded = Number(difference.value.toFixed(2)) === 0;
    return totalDebit.value > 0 && rounded && hasCompleteAccount.value && lines.value.length >= 2;
});

const submit = () => {
    form.lines = lines.value.map((line) => ({
        account_id: Number(line.account_id),
        debit: Number(line.debit) || 0,
        credit: Number(line.credit) || 0,
        memo: line.memo.trim() || null,
    }));
    form.post('/erp/accounting/journal');
};

const lineErrors = computed(() => {
    const errors = [];
    Object.entries(form.errors).forEach(([key, value]) => {
        if (key === 'journal_date' || key === 'description') return;
        errors.push(value);
    });
    return errors;
});
</script>

<template>
    <ErpLayout>
        <Head title="Post journal entry" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Post journal entry</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Record a double-entry journal entry — debits must equal credits.
                </p>
            </div>
            <Link
                href="/erp/accounting/journal"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to journal</Link>
        </header>

        <form @submit.prevent="submit" class="space-y-6">
            <Card title="Entry">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Journal date" />
                        <input
                            v-model="form.journal_date"
                            type="date"
                            required
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                        />
                        <InputError :message="form.errors.journal_date" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Description" />
                        <input
                            v-model="form.description"
                            type="text"
                            required
                            placeholder="What does this entry record? e.g. Owner capital contribution"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                </div>
            </Card>

            <Card title="Lines">
                <div class="mb-4 flex items-center gap-3">
                    <p class="text-sm text-slate-500">Add at least two lines, one per account leg.</p>
                    <button
                        type="button"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                        @click="addLine"
                    >+ Add line</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-2">Account</th>
                                <th class="px-4 py-2 text-right">Debit</th>
                                <th class="px-4 py-2 text-right">Credit</th>
                                <th class="px-4 py-2">Memo</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(line, index) in lines" :key="index">
                                <td class="px-4 py-2">
                                    <select
                                        v-model="line.account_id"
                                        required
                                        class="w-64 rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                                    >
                                        <option value="">Select an account…</option>
                                        <option v-for="account in accounts" :key="account.id" :value="account.id">
                                            {{ account.code }} — {{ account.name }} ({{ account.type_label }})
                                        </option>
                                    </select>
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="line.debit"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                        class="w-28 rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="line.credit"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                        class="w-28 rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="line.memo"
                                        type="text"
                                        placeholder="Optional note"
                                        class="w-44 rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                                    />
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <button
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-sm font-medium text-red-600 transition hover:bg-red-50"
                                        @click="removeLine(index)"
                                    >✕</button>
                                </td>
                            </tr>
                            <tr v-if="lines.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                    No lines yet — add at least two.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 sm:grid-cols-3">
                    <div class="text-sm">
                        <p class="text-slate-500">Total debits</p>
                        <p class="text-lg font-bold text-slate-900">${{ totalDebit.toFixed(2) }}</p>
                    </div>
                    <div class="text-sm">
                        <p class="text-slate-500">Total credits</p>
                        <p class="text-lg font-bold text-slate-900">${{ totalCredit.toFixed(2) }}</p>
                    </div>
                    <div class="text-sm">
                        <p class="text-slate-500">Difference</p>
                        <p
                            class="text-lg font-bold"
                            :class="difference ? 'text-red-600' : 'text-emerald-600'"
                        >${{ difference.toFixed(2) }}</p>
                    </div>
                </div>

                <div v-if="!isBalanced && lines.length > 0 && hasCompleteAccount" class="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    This entry is not balanced yet — total debits must equal total credits.
                </div>

                <div v-if="lineErrors.length" class="mt-3 rounded-lg bg-red-50 px-4 py-3">
                    <p v-for="(error, index) in lineErrors" :key="index" class="text-sm text-red-700">
                        {{ error }}
                    </p>
                </div>
            </Card>

            <div class="flex items-center justify-end gap-2">
                <SecondaryButton type="button" @click="router.visit('/erp/accounting/journal')">Cancel</SecondaryButton>
                <PrimaryButton :disabled="form.processing || !isBalanced">
                    {{ form.processing ? 'Posting…' : 'Post entry' }}
                </PrimaryButton>
            </div>
        </form>
    </ErpLayout>
</template>
