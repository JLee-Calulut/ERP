<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/GuestLayout.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import Input from '@/Components/Forms/Input.vue';
import InputError from '@/Components/Forms/InputError.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/erp/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Admin sign in" />

    <GuestLayout>
        <h2 class="text-lg font-semibold text-slate-900">ERP admin sign in</h2>
        <p class="mt-1 text-sm text-slate-500">Use your ERP credentials to open the app.</p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel value="Email" />
                <Input v-model="form.email" type="email" autocomplete="username" autofocus />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <InputLabel value="Password" />
                <Input v-model="form.password" type="password" autocomplete="current-password" />
                <InputError :message="form.errors.password" />
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                Remember me
            </label>

            <PrimaryButton :disabled="form.processing">Sign in</PrimaryButton>
        </form>
    </GuestLayout>
</template>
