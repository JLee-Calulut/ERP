<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const page = usePage();
const canManage = (page.props.auth?.user?.permissions ?? []).includes('inventory.manage');

const confirmDelete = ref(null);

const requestDelete = () => {
    router.delete(`/erp/inventory/categories/${confirmDelete.value.id}`, {
        onFinish: () => (confirmDelete.value = null),
    });
};
</script>

<template>
    <ErpLayout>
        <Head title="Categories" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Categories</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Group products so the catalog stays organised ({{ categories.length }} categories).
                </p>
            </div>
            <Link
                v-if="canManage"
                href="/erp/inventory/categories/create"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
            >+ New category</Link>
        </header>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Products</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="category in categories" :key="category.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ category.name }}</p>
                                <p class="text-xs text-slate-500">{{ category.slug }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ category.products_count }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ category.description ?? '—' }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Link
                                    v-if="canManage"
                                    :href="`/erp/inventory/categories/${category.id}/edit`"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50"
                                >Edit</Link>
                                <button
                                    v-if="canManage && category.products_count === 0"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 transition hover:bg-red-50"
                                    @click="confirmDelete = category"
                                >Delete</button>
                            </td>
                        </tr>
                        <tr v-if="categories.length === 0">
                            <td colspan="4" class="px-4 py-10 text-center text-slate-400">
                                No categories yet — create your first one.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="confirmDelete !== null" max-width="sm" @close="confirmDelete = null">
            <h3 class="text-lg font-semibold text-slate-900">Delete category?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ confirmDelete?.name }}" will be removed. Categories in use by products cannot be deleted.
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
