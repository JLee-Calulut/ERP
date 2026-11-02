<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', category: null }) },
});

const page = usePage();
const canManage = (page.props.auth?.user?.permissions ?? []).includes('inventory.manage');

const filters = reactive({
    search: props.filters.search ?? '',
    category: props.filters.category ?? '',
});

const applyFilters = () => {
    router.get('/erp/inventory/products', filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const confirmDelete = ref(null);

const requestDelete = () => {
    router.delete(`/erp/inventory/products/${confirmDelete.value.id}`, {
        onFinish: () => (confirmDelete.value = null),
    });
};

const statusBadge = (status) => ({
    out: 'bg-slate-100 text-slate-600',
    low: 'bg-amber-100 text-amber-700',
    in: 'bg-emerald-100 text-emerald-700',
}[status] ?? 'bg-slate-100 text-slate-600');

const statusLabel = (status) => ({
    out: 'Out of stock',
    low: 'Low stock',
    in: 'In stock',
}[status] ?? status);
</script>

<template>
    <ErpLayout>
        <Head title="Products" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Products</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Manage catalog items, pricing and stock levels ({{ products.length }} shown).
                </p>
            </div>
            <Link
                v-if="canManage"
                href="/erp/inventory/products/create"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
            >+ New product</Link>
        </header>

        <form class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_220px_auto]" @submit.prevent="applyFilters">
            <input
                v-model="filters.search"
                type="text"
                placeholder="Search by name or SKU…"
                class="rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
            />
            <select
                v-model="filters.category"
                class="rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
            >
                <option value="">All categories</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                </option>
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
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Cost</th>
                            <th class="px-4 py-3">Sell</th>
                            <th class="px-4 py-3">Stock</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="product in products" :key="product.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ product.name }}</p>
                                <p class="text-xs text-slate-500">{{ product.sku }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ product.category ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">${{ product.cost_price.toFixed(2) }}</td>
                            <td class="px-4 py-3 text-slate-600">${{ product.selling_price.toFixed(2) }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ product.stock_quantity }}
                                <span class="text-xs text-slate-400">/ {{ product.low_stock_threshold }} min</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="statusBadge(product.stock_status)"
                                >{{ statusLabel(product.stock_status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Link
                                    v-if="canManage"
                                    :href="`/erp/inventory/products/${product.id}/edit`"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50"
                                >Edit</Link>
                                <button
                                    v-if="canManage"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 transition hover:bg-red-50"
                                    @click="confirmDelete = product"
                                >Delete</button>
                            </td>
                        </tr>
                        <tr v-if="products.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                No products found{{ filters.search ? ' for your search' : ' yet' }}.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="confirmDelete !== null" max-width="sm" @close="confirmDelete = null">
            <h3 class="text-lg font-semibold text-slate-900">Delete product?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ confirmDelete?.name }}" will be removed from the catalog. This cannot be undone.
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
