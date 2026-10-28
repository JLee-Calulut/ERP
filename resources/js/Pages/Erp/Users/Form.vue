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
    user: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
});

const isEditing = computed(() => props.user !== null);

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    roles: props.user?.role_ids ?? [],
});

const toggleRole = (id) => {
    const index = form.roles.indexOf(id);
    if (index === -1) {
        form.roles.push(id);
    } else {
        form.roles.splice(index, 1);
    }
};

const submit = () => {
    if (isEditing.value) {
        form.put(`/erp/users/${props.user.id}`);
    } else {
        form.post('/erp/users');
    }
};

const confirmDelete = ref(false);
const requestDelete = () => {
    router.delete(`/erp/users/${props.user.id}`, {
        onFinish: () => (confirmDelete.value = false),
    });
};
</script>

<template>
    <ErpLayout>
        <Head :title="isEditing ? 'Edit user' : 'New user'" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ isEditing ? `Edit user — ${user.name}` : 'New user' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ isEditing ? 'Update account details and roles.' : 'Create a staff account and choose its roles.' }}
                </p>
            </div>
            <Link
                href="/erp/users"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to users</Link>
        </header>

        <form @submit.prevent="submit" class="space-y-6">
            <Card title="Account details">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Name" />
                        <Input v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Email" />
                        <Input v-model="form.email" type="email" required />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel :value="isEditing ? 'New password (optional)' : 'Password'" />
                        <Input v-model="form.password" type="password" :required="!isEditing" autocomplete="new-password" />
                        <InputError :message="form.errors.password" />
                        <p v-if="isEditing" class="mt-1 text-xs text-slate-400">Leave blank to keep the current password.</p>
                    </div>
                    <div>
                        <InputLabel :value="isEditing ? 'Confirm new password' : 'Confirm password'" />
                        <Input v-model="form.password_confirmation" type="password" :required="!isEditing" autocomplete="new-password" />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>
                </div>
            </Card>

            <Card>
                <template #actions>
                    <span class="text-xs font-medium text-slate-500">{{ form.roles.length }} role(s) selected</span>
                </template>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <label
                        v-for="role in roles"
                        :key="role.id"
                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 transition hover:border-brand-300 hover:bg-brand-50"
                        :class="form.roles.includes(role.id) ? 'border-brand-300 bg-brand-50' : ''"
                    >
                        <input
                            type="checkbox"
                            class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            :checked="form.roles.includes(role.id)"
                            @change="toggleRole(role.id)"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-800">{{ role.name }}</span>
                            <span v-if="role.description" class="block text-xs text-slate-400">{{ role.description }}</span>
                        </span>
                    </label>
                </div>
                <InputError :message="form.errors.roles" />
            </Card>

            <div class="flex items-center justify-between gap-3">
                <div>
                    <button
                        v-if="isEditing && !user.is_self"
                        type="button"
                        class="inline-flex rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                        @click="confirmDelete = true"
                    >Delete user</button>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="router.visit('/erp/users')">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ isEditing ? 'Save changes' : 'Create user' }}
                    </PrimaryButton>
                </div>
            </div>
        </form>

        <Modal :show="confirmDelete" max-width="sm" @close="confirmDelete = false">
            <h3 class="text-lg font-semibold text-slate-900">Delete user?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ user?.name }}" ({{ user?.email }}) will be removed from the ERP. This cannot be undone.
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
