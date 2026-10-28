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
    role: { type: Object, default: null },
    permissionGroups: { type: Array, default: () => [] },
});

const isEditing = computed(() => props.role !== null);

const form = useForm({
    name: props.role?.name ?? '',
    slug: props.role?.slug ?? '',
    description: props.role?.description ?? '',
    permissions: props.role?.permission_ids ?? [],
});

const togglePermission = (id) => {
    const index = form.permissions.indexOf(id);
    if (index === -1) {
        form.permissions.push(id);
    } else {
        form.permissions.splice(index, 1);
    }
};

const toggleGroup = (group) => {
    const ids = group.permissions.map((permission) => permission.id);
    const allSelected = ids.every((id) => form.permissions.includes(id));
    if (allSelected) {
        form.permissions = form.permissions.filter((id) => !ids.includes(id));
    } else {
        form.permissions = [...new Set([...form.permissions, ...ids])];
    }
};

const isGroupSelected = (group) =>
    group.permissions.every((permission) => form.permissions.includes(permission.id));

const submit = () => {
    if (isEditing.value) {
        form.put(`/erp/roles/${props.role.id}`);
    } else {
        form.post('/erp/roles');
    }
};

const confirmDelete = ref(false);
const requestDelete = () => {
    router.delete(`/erp/roles/${props.role.id}`, {
        onFinish: () => (confirmDelete.value = false),
    });
};

const selectedCount = computed(() => form.permissions.length);
const totalPermissions = computed(() =>
    props.permissionGroups.reduce((total, group) => total + group.permissions.length, 0),
);
</script>

<template>
    <ErpLayout>
        <Head :title="isEditing ? 'Edit role' : 'New role'" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ isEditing ? `Edit role — ${role.name}` : 'New role' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ isEditing ? 'Update the role name and its permissions.' : 'Create a role and choose its permissions.' }}
                </p>
            </div>
            <Link
                href="/erp/roles"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to roles</Link>
        </header>

        <form @submit.prevent="submit" class="space-y-6">
            <Card title="Details">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Name" />
                        <Input v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Slug" />
                        <Input v-model="form.slug" placeholder="auto-generated from name" />
                        <InputError :message="form.errors.slug" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel value="Description" />
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                        placeholder="What is this role responsible for?"
                    />
                    <InputError :message="form.errors.description" />
                </div>
            </Card>

            <Card>
                <template #actions>
                    <span class="text-xs font-medium text-slate-500">{{ selectedCount }} / {{ totalPermissions }} selected</span>
                </template>
                <div class="space-y-6">
                    <div v-for="group in permissionGroups" :key="group.key">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-900">{{ group.module }}</h3>
                            <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-brand-600">
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                    :checked="isGroupSelected(group)"
                                    @change="toggleGroup(group)"
                                />
                                Select all
                            </label>
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label
                                v-for="permission in group.permissions"
                                :key="permission.id"
                                class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 transition hover:border-brand-300 hover:bg-brand-50"
                                :class="form.permissions.includes(permission.id) ? 'border-brand-300 bg-brand-50' : ''"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                    :checked="form.permissions.includes(permission.id)"
                                    @change="togglePermission(permission.id)"
                                />
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-800">{{ permission.name }}</span>
                                    <span class="block truncate text-xs text-slate-400">{{ permission.slug }}</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </Card>

            <div class="flex items-center justify-between gap-3">
                <div>
                    <button
                        v-if="isEditing && !role.is_core"
                        type="button"
                        class="inline-flex rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                        @click="confirmDelete = true"
                    >Delete role</button>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="router.visit('/erp/roles')">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ isEditing ? 'Save changes' : 'Create role' }}
                    </PrimaryButton>
                </div>
            </div>
        </form>

        <Modal :show="confirmDelete" max-width="sm" @close="confirmDelete = false">
            <h3 class="text-lg font-semibold text-slate-900">Delete role?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ role?.name }}" will be removed and can no longer be assigned. This cannot be undone.
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
