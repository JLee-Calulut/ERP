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
    supplier: { type: Object, default: null },
});

const isEditing = computed(() => props.supplier !== null);

const form = useForm({
    name: props.supplier?.name ?? '',
    email: props.supplier?.email ?? '',
    phone: props.supplier?.phone ?? '',
    address_line1: props.supplier?.address_line1 ?? '',
    address_city: props.supplier?.address_city ?? '',
    address_state: props.supplier?.address_state ?? '',
    address_postal_code: props.supplier?.address_postal_code ?? '',
    address_country: props.supplier?.address_country ?? '',
});

const submit = () => {
    if (isEditing.value) {
        form.put(`/erp/purchasing/suppliers/${props.supplier.id}`);
    } else {
        form.post('/erp/purchasing/suppliers');
    }
};

const confirmDelete = ref(false);

const requestDelete = () => {
    router.delete(`/erp/purchasing/suppliers/${props.supplier.id}`, {
        onFinish: () => (confirmDelete.value = false),
    });
};

const listUrl = '/erp/purchasing/suppliers';
</script>

<template>
    <ErpLayout>
        <Head :title="isEditing ? 'Edit supplier' : 'New supplier'" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ isEditing ? `Edit supplier — ${supplier.name}` : 'New supplier' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ isEditing ? 'Update contact and address details.' : 'Add a supplier to your purchasing book.' }}
                </p>
            </div>
            <Link
                :href="listUrl"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >← Back to suppliers</Link>
        </header>

        <form @submit.prevent="submit" class="space-y-6">
            <Card title="Contact">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Supplier name" />
                        <Input v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Email" />
                        <Input v-model="form.email" type="email" placeholder="sales@company.com" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div>
                        <InputLabel value="Phone" />
                        <Input v-model="form.phone" placeholder="+1 555-0100" />
                        <InputError :message="form.errors.phone" />
                    </div>
                </div>
            </Card>

            <Card title="Address">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel value="Address line 1" />
                        <Input v-model="form.address_line1" placeholder="Street address" />
                        <InputError :message="form.errors.address_line1" />
                    </div>
                    <div>
                        <InputLabel value="City" />
                        <Input v-model="form.address_city" />
                        <InputError :message="form.errors.address_city" />
                    </div>
                    <div>
                        <InputLabel value="State / Province" />
                        <Input v-model="form.address_state" />
                        <InputError :message="form.errors.address_state" />
                    </div>
                    <div>
                        <InputLabel value="Postal code" />
                        <Input v-model="form.address_postal_code" />
                        <InputError :message="form.errors.address_postal_code" />
                    </div>
                    <div>
                        <InputLabel value="Country" />
                        <Input v-model="form.address_country" />
                        <InputError :message="form.errors.address_country" />
                    </div>
                </div>
            </Card>

            <div class="flex items-center justify-between gap-3">
                <div>
                    <button
                        v-if="isEditing"
                        type="button"
                        class="inline-flex rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                        @click="confirmDelete = true"
                    >Delete supplier</button>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="router.visit(listUrl)">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ isEditing ? 'Save changes' : 'Create supplier' }}
                    </PrimaryButton>
                </div>
            </div>
        </form>

        <Modal :show="confirmDelete" max-width="sm" @close="confirmDelete = false">
            <h3 class="text-lg font-semibold text-slate-900">Delete supplier?</h3>
            <p class="mt-2 text-sm text-slate-500">
                "{{ supplier?.name }}" will be archived. This cannot be undone.
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