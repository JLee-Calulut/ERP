<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    customers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const page = usePage();
const canManage = (page.props.auth?.user?.permissions ?? []).includes('sales.manage');

const filters = reactive({ search: props.filters.search ?? '' });

const applyFilters = () => {
    router.get('/erp/sales/customers', filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const confirmDelete = ref(null);

const requestDelete = () => {
    router.delete(`/erp/sales/customers/${confirmDelete.value.id}`, {
        onFinish: () => (confirmDelete.value = null),
    });
};
</script>

<template>
    <ErpLayout>
        <Head title="Customers" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Customers</h1>
                <p class="mt-1 text-sm text-slate-500">
                    People and companies you sell to ({{ customers.length }} shown).
                </p>
            </div>
            <Link
                v-if="canManage"
                href="/erp/sales/customers/create"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
            >+ New customer</Link>
        </header>

        <form class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="applyFilters">
            <input
                v-model="filters.search"
                type="text"
                placeholder="Search by name, email or phone…"
                class="rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
            />
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
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Orders</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="customer in customers" :key="customer.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ customer.name }}</p>
                                <p class="text-xs text-slate-500">#{{ customer.id }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-slate-600">{{ customer.email ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ customer.phone ?? '' }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ [customer.city, customer.country].filter(Boolean).join(', ') || '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ customer.orders_count }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Link
                                    v-if="canManage"
                                    :href="`/erp/sales/customers/${customer.id}/edit`"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50"
                                >Edit</Link>
                                <button
                                    v-if="canManage"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 transition hover:bg-red-50"
                                    @click="confirmDelete = customer"
                                >Delete</button>
                            </td>
                        </tr>
                        <tr v-if="customers.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400">
                                No customers found{{ filters.search ? ' for your search' : ' yet' }}.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="confirmDelete !== null" max-width="sm" @close="confirmDelete = null">
            <h3 class="text-lg font-semibold text-slate-900">Delete customer?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ confirmDelete?.name }}" will be archived. This cannot be undone.
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
