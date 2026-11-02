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
    product: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
});

const isEditing = computed(() => props.product !== null);

const form = useForm({
    category_id: props.product?.category_id ?? '',
    name: props.product?.name ?? '',
    sku: props.product?.sku ?? '',
    description: props.product?.description ?? '',
    cost_price: props.product ? props.product.cost_price.toFixed(2) : '0.00',
    selling_price: props.product ? props.product.selling_price.toFixed(2) : '0.00',
    stock_quantity: props.product?.stock_quantity ?? 0,
    low_stock_threshold: props.product?.low_stock_threshold ?? 5,
    is_active: props.product?.is_active ?? true,
});

const submit = () => {
    if (isEditing.value) {
        form.put(`/erp/inventory/products/${props.product.id}`);
    } else {
        form.post('/erp/inventory/products');
    }
};

const confirmDelete = ref(false);
const requestDelete = () => {
    router.delete(`/erp/inventory/products/${props.product.id}`, {
        onFinish: () => (confirmDelete.value = false),
    });
};

const listUrl = '/erp/inventory/products';
</script>

<template>
    <ErpLayout>
        <Head :title="isEditing ? 'Edit product' : 'New product'" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ isEditing ? `Edit product — ${product.name}` : 'New product' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ isEditing ? 'Update catalog details, pricing and stock settings.' : 'Add a new item to the catalog.' }}
                </p>
            </div>
            <Link
                :href="listUrl"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to products</Link>
        </header>

        <form @submit.prevent="submit" class="space-y-6">
            <Card title="Details">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Product name" />
                        <Input v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="SKU" />
                        <Input v-model="form.sku" required placeholder="e.g. MOUSE-WL-001" />
                        <InputError :message="form.errors.sku" />
                    </div>
                    <div>
                        <InputLabel value="Category" />
                        <select
                            v-model="form.category_id"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                        >
                            <option value="">No category</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.category_id" />
                    </div>
                    <div>
                        <InputLabel value="Active" />
                        <label class="mt-2 flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            />
                            Visible and sellable
                        </label>
                        <InputError :message="form.errors.is_active" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel value="Description" />
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                        placeholder="Optional notes about this product."
                    />
                    <InputError :message="form.errors.description" />
                </div>
            </Card>

            <Card title="Pricing & stock">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <InputLabel value="Cost price ($)" />
                        <Input v-model="form.cost_price" type="number" step="0.01" min="0" required />
                        <InputError :message="form.errors.cost_price" />
                    </div>
                    <div>
                        <InputLabel value="Selling price ($)" />
                        <Input v-model="form.selling_price" type="number" step="0.01" min="0" required />
                        <InputError :message="form.errors.selling_price" />
                    </div>
                    <div>
                        <InputLabel value="Opening stock" />
                        <Input v-model="form.stock_quantity" type="number" step="1" min="0" />
                        <InputError :message="form.errors.stock_quantity" />
                    </div>
                    <div>
                        <InputLabel value="Low stock threshold" />
                        <Input v-model="form.low_stock_threshold" type="number" step="1" min="0" />
                        <InputError :message="form.errors.low_stock_threshold" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-400">
                    Opening stock is only applied on create; use the Stock page to move quantities afterwards.
                </p>
            </Card>

            <div class="flex items-center justify-between gap-3">
                <div>
                    <button
                        v-if="isEditing"
                        type="button"
                        class="inline-flex rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                        @click="confirmDelete = true"
                    >Delete product</button>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="router.visit(listUrl)">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ isEditing ? 'Save changes' : 'Create product' }}
                    </PrimaryButton>
                </div>
            </div>
        </form>

        <Modal :show="confirmDelete" max-width="sm" @close="confirmDelete = false">
            <h3 class="text-lg font-semibold text-slate-900">Delete product?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ product?.name }}" will be removed from the catalog. This cannot be undone.
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
