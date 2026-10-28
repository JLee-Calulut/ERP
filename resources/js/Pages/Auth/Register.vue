<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/GuestLayout.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import Input from '@/Components/Forms/Input.vue';
import InputError from '@/Components/Forms/InputError.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />

    <GuestLayout>
        <h2 class="text-lg font-semibold text-slate-900">Create your account</h2>
        <p class="mt-1 text-sm text-slate-500">
            New accounts start with the <b>Staff</b> role.
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel value="Name" />
                <Input v-model="form.name" autocomplete="name" autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div>
                <InputLabel value="Email" />
                <Input v-model="form.email" type="email" autocomplete="username" />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <InputLabel value="Password" />
                <Input v-model="form.password" type="password" autocomplete="new-password" />
                <InputError :message="form.errors.password" />
            </div>

            <div>
                <InputLabel value="Confirm password" />
                <Input v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <PrimaryButton :disabled="form.processing">Create account</PrimaryButton>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Already registered?
            <a href="/login" class="font-semibold text-brand-600 hover:text-brand-700">Sign in</a>
        </p>
    </GuestLayout>
</template>