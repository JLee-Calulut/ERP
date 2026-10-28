<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    permissionCount: { type: Number, default: 0 },
});

const confirmDelete = ref(null);

const requestDelete = () => {
    router.delete(`/erp/roles/${confirmDelete.value.id}`, {
        onFinish: () => (confirmDelete.value = null),
    });
};
</script>

<template>
    <ErpLayout>
        <Head title="Roles & Permissions" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Roles & Permissions</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Control what each role can see and do across the ERP ({{ permissionCount }} permissions available).
                </p>
            </div>
            <Link
                href="/erp/roles/create"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
            >
                + New role
            </Link>
        </header>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Users</th>
                            <th class="px-4 py-3">Permissions</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="role in roles" :key="role.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">
                                    {{ role.name }}
                                    <span
                                        v-if="role.is_core"
                                        class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500"
                                    >CORE</span>
                                </p>
                                <p class="text-xs text-slate-500">{{ role.slug }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ role.users_count }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ role.permissions_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/erp/roles/${role.id}/edit`"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50"
                                >Edit</Link>
                                <button
                                    v-if="!role.is_core"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 transition hover:bg-red-50"
                                    @click="confirmDelete = role"
                                >Delete</button>
                            </td>
                        </tr>
                        <tr v-if="roles.length === 0">
                            <td colspan="4" class="px-4 py-10 text-center text-slate-400">
                                No roles yet — create your first role.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="confirmDelete !== null" max-width="sm" @close="confirmDelete = null">
            <h3 class="text-lg font-semibold text-slate-900">Delete role?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ confirmDelete?.name }}" will be removed and can no longer be assigned. This cannot be undone.
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
