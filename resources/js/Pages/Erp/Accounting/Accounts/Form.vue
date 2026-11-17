<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import Input from '@/Components/Forms/Input.vue';
import InputError from '@/Components/Forms/InputError.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';

const props = defineProps({
    account: { type: Object, default: null },
});

const isEditing = computed(() => props.account !== null);

const types = [
    { value: 'asset', label: 'Asset' },
    { value: 'liability', label: 'Liability' },
    { value: 'equity', label: 'Equity' },
    { value: 'revenue', label: 'Revenue' },
    { value: 'expense', label: 'Expense' },
];

const form = useForm({
    code: props.account?.code ?? '',
    name: props.account?.name ?? '',
    type: props.account?.type ?? 'asset',
    is_active: props.account?.is_active ?? true,
    description: props.account?.description ?? '',
});

const submit = () => {
    if (isEditing.value) {
        form.put(`/erp/accounting/accounts/${props.account.id}`);
    } else {
        form.post('/erp/accounting/accounts');
    }
};

const confirmDelete = ref(false);

const requestDelete = () => {
    router.delete(`/erp/accounting/accounts/${props.account.id}`, {
        onFinish: () => (confirmDelete.value = false),
    });
};

const listUrl = '/erp/accounting/accounts';
</script>

<template>
    <ErpLayout>
        <Head :title="isEditing ? 'Edit account' : 'New account'" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ isEditing ? `Edit account — ${account.code} ${account.name}` : 'New account' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Add or update a classification on your chart of accounts.
                </p>
            </div>
            <Link
                :href="listUrl"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to chart of accounts</Link>
        </header>

        <form @submit.prevent="submit" class="space-y-6">
            <Card title="Account details">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Code" />
                        <Input v-model="form.code" placeholder="e.g. 1010" required />
                        <InputError :message="form.errors.code" />
                    </div>
                    <div>
                        <InputLabel value="Name" />
                        <Input v-model="form.name" placeholder="e.g. Cash" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Type" />
                        <select
                            v-model="form.type"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                        >
                            <option v-for="type in types" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                    <div>
                        <InputLabel value="Active" />
                        <label class="mt-2 flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            />
                            Accept new journal postings
                        </label>
                        <InputError :message="form.errors.is_active" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Description" />
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                            placeholder="Optional notes about this account."
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>
                </div>
            </Card>

            <div class="flex items-center justify-between gap-3">
                <div>
                    <button
                        v-if="isEditing"
                        type="button"
                        class="inline-flex rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                        @click="confirmDelete = true"
                    >Delete account</button>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="router.visit(listUrl)">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ isEditing ? 'Save changes' : 'Create account' }}
                    </PrimaryButton>
                </div>
            </div>
        </form>

        <Modal :show="confirmDelete" max-width="sm" @close="confirmDelete = false">
            <h3 class="text-lg font-semibold text-slate-900">Delete account?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ account?.code }} — {{ account?.name }}" will be removed. Accounts used by journal lines cannot
                be deleted.
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
    </ErpLayout>
</template>
