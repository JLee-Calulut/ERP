<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';
import StatCard from '@/Components/Cards/StatCard.vue';

const props = defineProps({
    user: { type: Object, required: true },
    stats: { type: Object, required: true },
    quickActions: { type: Array, default: () => [] },
});

const page = usePage();
const today = new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
});

const availableActions = computed(() => props.quickActions.filter((action) => action.available));
const unavailableActions = computed(() => props.quickActions.filter((action) => !action.available));
</script>

<template>
    <ErpLayout>
        <Head title="Dashboard" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Welcome back, {{ user.name }} — you are signed in as {{ user.roles.join(', ') || 'Member' }}.
                </p>
            </div>
            <p class="text-sm text-slate-500">{{ today }}</p>
        </header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Users" :value="stats.users" icon="🪪" tone="blue" hint="Accounts in the workspace" />
            <StatCard label="Roles" :value="stats.roles" icon="🔐" tone="violet" hint="Admin, Manager, Staff" />
            <StatCard label="Permissions" :value="stats.permissions" icon="🔑" tone="amber" hint="Attached to roles" />
            <StatCard label="Administrators" :value="stats.administrators" icon="👑" tone="green" hint="Users with the admin role" />
        </div>

        <Card title="Quick actions" class="mt-6">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Link
                    v-for="action in availableActions"
                    :key="action.label"
                    :href="action.href"
                    class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-left text-sm font-medium text-slate-700 transition hover:border-brand-500 hover:bg-brand-50"
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-lg">
                        {{ action.icon }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate">{{ action.label }}</span>
                    </span>
                </Link>
                <button
                    v-for="action in unavailableActions"
                    :key="action.label"
                    :disabled="true"
                    class="flex cursor-not-allowed items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-left text-sm font-medium text-slate-400"
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-lg">
                        {{ action.icon }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate">{{ action.label }}</span>
                        <span class="block text-[10px] text-slate-400">No access</span>
                    </span>
                </button>
            </div>
        </Card>

        <Card title="Module activity" class="mt-6">
            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center">
                <p class="text-2xl">🕓</p>
                <p class="mt-2 text-sm font-medium text-slate-600">No activity yet</p>
                <p class="mt-1 text-xs text-slate-400">
                    Inventory, sales, purchasing and accounting activity appears here once those modules arrive (Days 3-7).
                </p>
            </div>
        </Card>

        <p v-if="page.props.flash?.success" class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </p>
    </ErpLayout>
</template>
