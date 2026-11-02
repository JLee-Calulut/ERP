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
    category: { type: Object, default: null },
});

const isEditing = computed(() => props.category !== null);

const form = useForm({
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    description: props.category?.description ?? '',
});

const submit = () => {
    if (isEditing.value) {
        form.put(`/erp/inventory/categories/${props.category.id}`);
    } else {
        form.post('/erp/inventory/categories');
    }
};

const confirmDelete = ref(false);
const requestDelete = () => {
    router.delete(`/erp/inventory/categories/${props.category.id}`, {
        onFinish: () => (confirmDelete.value = false),
    });
};

const listUrl = '/erp/inventory/categories';
</script>

<template>
    <ErpLayout>
        <Head :title="isEditing ? 'Edit category' : 'New category'" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ isEditing ? `Edit category — ${category.name}` : 'New category' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ isEditing ? 'Update the category name and details.' : 'Create a category to organise products.' }}
                </p>
            </div>
            <Link
                :href="listUrl"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to categories</Link>
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
                        placeholder="What belongs in this category?"
                    />
                    <InputError :message="form.errors.description" />
                </div>
            </Card>

            <div class="flex items-center justify-between gap-3">
                <div>
                    <button
                        v-if="isEditing && category.products_count === 0"
                        type="button"
                        class="inline-flex rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                        @click="confirmDelete = true"
                    >Delete category</button>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="router.visit(listUrl)">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ isEditing ? 'Save changes' : 'Create category' }}
                    </PrimaryButton>
                </div>
            </div>
        </form>

        <Modal :show="confirmDelete" max-width="sm" @close="confirmDelete = false">
            <h3 class="text-lg font-semibold text-slate-900">Delete category?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ category?.name }}" will be removed. This cannot be undone.
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
