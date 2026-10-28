<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
});

const confirmDelete = ref(null);

const requestDelete = () => {
    router.delete(`/erp/users/${confirmDelete.value.id}`, {
        onFinish: () => (confirmDelete.value = null),
    });
};
</script>

<template>
    <ErpLayout>
        <Head title="Users" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Users</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Manage staff accounts and assign roles ({{ roles.length }} roles defined).
                </p>
            </div>
            <Link
                href="/erp/users/create"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
            >
                + New user
            </Link>
        </header>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Roles</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="user in users" :key="user.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">
                                    {{ user.name }}
                                    <span v-if="user.is_self" class="ml-2 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-600">YOU</span>
                                </p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ user.email }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role"
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                                    >{{ role }}</span>
                                    <span v-if="user.roles.length === 0" class="text-xs text-slate-400">No roles</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/erp/users/${user.id}/edit`"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50"
                                >Edit</Link>
                                <button
                                    v-if="!user.is_self"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 transition hover:bg-red-50"
                                    @click="confirmDelete = user"
                                >Delete</button>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td colspan="4" class="px-4 py-10 text-center text-slate-400">
                                No users yet — create your first account.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="confirmDelete !== null" max-width="sm" @close="confirmDelete = null">
            <h3 class="text-lg font-semibold text-slate-900">Delete user?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ confirmDelete?.name }}" ({{ confirmDelete?.email }}) will be removed from the ERP. Their account is soft-deleted and can be restored later.
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
