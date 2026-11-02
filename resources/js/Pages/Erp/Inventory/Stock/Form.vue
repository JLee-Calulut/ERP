<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import Input from '@/Components/Forms/Input.vue';
import InputError from '@/Components/Forms/InputError.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});

const form = useForm({
    product_id: '',
    type: 'in',
    quantity: '',
    reason: '',
});

const selectedProduct = computed(() =>
    props.products.find((product) => product.id === Number(form.product_id)),
);

const isAdjustment = computed(() => form.type === 'adjustment');
const quantityHint = computed(() => {
    const product = selectedProduct.value;
    if (!product) return null;
    if (form.type === 'in') return `Current stock: ${product.stock_quantity} — this quantity will be added.`;
    if (form.type === 'out') return `Current stock: ${product.stock_quantity} — cannot exceed this amount.`;
    return `Current stock: ${product.stock_quantity} — this is the new stock level.`;
});

const submit = () => {
    form.post('/erp/inventory/stock');
};
</script>

<template>
    <ErpLayout>
        <Head title="Record stock movement" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Record stock movement</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Receive stock, issue it out, or adjust the recorded level.
                </p>
            </div>
            <Link
                href="/erp/inventory/stock"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to stock</Link>
        </header>

        <form @submit.prevent="submit" class="space-y-6">
            <Card title="Movement">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel value="Product" />
                        <select
                            v-model="form.product_id"
                            required
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                        >
                            <option value="" disabled>Choose a product…</option>
                            <option v-for="product in products" :key="product.id" :value="product.id">
                                {{ product.name }} ({{ product.sku }} — {{ product.stock_quantity }} on hand)
                            </option>
                        </select>
                        <InputError :message="form.errors.product_id" />
                    </div>

                    <div>
                        <InputLabel value="Movement type" />
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
                        <InputLabel :value="isAdjustment ? 'New stock level' : 'Quantity'" />
                        <Input
                            v-model="form.quantity"
                            type="number"
                            :step="isAdjustment ? 1 : 1"
                            :min="isAdjustment ? 0 : 1"
                            required
                        />
                        <InputError :message="form.errors.quantity" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Reason (optional)" />
                        <Input v-model="form.reason" placeholder="e.g. Delivery from supplier" />
                        <InputError :message="form.errors.reason" />
                    </div>
                </div>

                <p v-if="quantityHint" class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    {{ quantityHint }}
                </p>
            </Card>

            <div class="flex items-center gap-2">
                <SecondaryButton type="button" @click="router.visit('/erp/inventory/stock')">Cancel</SecondaryButton>
                <PrimaryButton :disabled="form.processing">Record movement</PrimaryButton>
            </div>
        </form>
    </ErpLayout>
</template>
