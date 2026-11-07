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
    customers: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const form = useForm({
    customer_id: '',
    items: [],
});

// Line items keep client-side catalog metadata for the live total; only
// product_id + quantity are sent to the server on submit.
const lines = ref([]);
const selectedProductId = ref('');

const addLine = () => {
    const product = props.products.find((p) => p.id === Number(selectedProductId.value));
    if (!product) return;
    if (lines.value.some((line) => line.product_id === product.id)) return;

    lines.value.push({
        product_id: product.id,
        name: product.name,
        sku: product.sku,
        price: Number(product.selling_price),
        stock: Number(product.stock_quantity),
        quantity: 1,
    });
    selectedProductId.value = '';
};

const removeLine = (index) => {
    lines.value.splice(index, 1);
};

const runningTotal = computed(() =>
    lines.value.reduce((sum, line) => sum + line.price * line.quantity, 0),
);

const submit = () => {
    form.items = lines.value.map((line) => ({
        product_id: line.product_id,
        quantity: line.quantity,
    }));
    form.post('/erp/sales/orders');
};

const lineErrors = computed(() => {
    const errors = [];
    Object.entries(form.errors).forEach(([key, value]) => {
        if (key === 'customer_id') return;
        errors.push(value);
    });
    return errors;
});
</script>

<template>
    <ErpLayout>
        <Head title="New sales order" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">New sales order</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Pick a customer, add line items and review the running total before saving.
                </p>
            </div>
            <Link
                href="/erp/sales/orders"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to orders</Link>
        </header>

        <form @submit.prevent="submit" class="space-y-6">
            <Card title="Order">
                <div class="max-w-md">
                    <InputLabel value="Customer" />
                    <select
                        v-model="form.customer_id"
                        required
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                    >
                        <option value="">Select a customer…</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }}{{ customer.email ? ` (${customer.email})` : '' }}
                        </option>
                    </select>
                    <InputError :message="form.errors.customer_id" />
                </div>
            </Card>

            <Card title="Line items">
                <div class="mb-4 flex flex-wrap items-end gap-3">
                    <div class="min-w-64 flex-1">
                        <InputLabel value="Add product" />
                        <select
                            v-model="selectedProductId"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                        >
                            <option value="">Select a product…</option>
                            <option v-for="product in products" :key="product.id" :value="product.id">
                                {{ product.name }} ({{ product.sku }}) — ${{ Number(product.selling_price).toFixed(2) }} · Stock {{ product.stock_quantity }}
                            </option>
                        </select>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                        @click="addLine"
                    >Add line</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-2">Product</th>
                                <th class="px-4 py-2">Unit price</th>
                                <th class="px-4 py-2">Qty</th>
                                <th class="px-4 py-2 text-right">Line total</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(line, index) in lines" :key="line.product_id">
                                <td class="px-4 py-2">
                                    <p class="font-medium text-slate-900">{{ line.name }}</p>
                                    <p class="text-xs text-slate-500">{{ line.sku }} · {{ line.stock }} in stock</p>
                                </td>
                                <td class="px-4 py-2 text-slate-600">${{ line.price.toFixed(2) }}</td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model.number="line.quantity"
                                        type="number"
                                        min="1"
                                        class="w-24 rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                                    />
                                </td>
                                <td class="px-4 py-2 text-right font-medium text-slate-900">
                                    ${{ (line.price * line.quantity).toFixed(2) }}
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
                                    No items yet — add at least one product.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="lineErrors.length" class="mt-3 rounded-lg bg-red-50 px-4 py-3">
                    <p v-for="(error, index) in lineErrors" :key="index" class="text-sm text-red-700">
                        {{ error }}
                    </p>
                </div>

                <div class="mt-4 flex justify-end border-t border-slate-100 pt-4">
                    <div class="text-right">
                        <p class="text-sm text-slate-500">Running total</p>
                        <p class="text-2xl font-bold text-slate-900">${{ runningTotal.toFixed(2) }}</p>
                    </div>
                </div>
            </Card>

            <div class="flex items-center justify-end gap-2">
                <SecondaryButton type="button" @click="router.visit('/erp/sales/orders')">Cancel</SecondaryButton>
                <PrimaryButton :disabled="form.processing || lines.length === 0">
                    Create order
                </PrimaryButton>
            </div>
        </form>
    </ErpLayout>
</template>
