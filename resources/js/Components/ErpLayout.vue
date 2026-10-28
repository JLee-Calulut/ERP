<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';

const page = usePage();
const sidebarOpen = ref(false);

const user = computed(() => page.props.auth?.user ?? null);
const permissions = computed(() => new Set(user.value?.permissions ?? []));
const currentPath = computed(() => page.url);

const nav = [
    {
        section: 'Overview',
        items: [
            { label: 'Dashboard', href: '/erp/dashboard', permission: 'dashboard.view', icon: '📊', soon: false },
        ],
    },
    {
        section: 'Administration',
        adminOnly: true,
        items: [
            { label: 'Users', href: '/erp/users', permission: 'users.manage', icon: '🪪', soon: false },
            { label: 'Roles & Permissions', href: '/erp/roles', permission: 'roles.manage', icon: '🔐', soon: false },
        ],
    },
    // Modules that arrive on Days 3-7. They stay visible but disabled, so the
    // layout shows the shape of the app without linking to routes that do not
    // exist yet. Flip `soon` to false and set the real href on that day.
    {
        section: 'Inventory',
        items: [
            { label: 'Products', href: '#', permission: 'inventory.view', icon: '📦', soon: true },
            { label: 'Categories', href: '#', permission: 'inventory.view', icon: '🗂️', soon: true },
            { label: 'Stock', href: '#', permission: 'inventory.view', icon: '⚖️', soon: true },
        ],
    },
    {
        section: 'Sales',
        items: [
            { label: 'Customers', href: '#', permission: 'sales.view', icon: '👥', soon: true },
            { label: 'Orders', href: '#', permission: 'sales.view', icon: '🛒', soon: true },
            { label: 'Invoices', href: '#', permission: 'sales.view', icon: '🧾', soon: true },
        ],
    },
    {
        section: 'Purchasing',
        items: [
            { label: 'Suppliers', href: '#', permission: 'purchasing.view', icon: '🏭', soon: true },
            { label: 'Purchase Orders', href: '#', permission: 'purchasing.view', icon: '📋', soon: true },
            { label: 'Receipts', href: '#', permission: 'purchasing.view', icon: '📥', soon: true },
        ],
    },
    {
        section: 'Accounting',
        items: [
            { label: 'Chart of Accounts', href: '#', permission: 'accounting.view', icon: '🧮', soon: true },
            { label: 'Journal', href: '#', permission: 'accounting.view', icon: '📔', soon: true },
            { label: 'Trial Balance', href: '#', permission: 'accounting.view', icon: '📐', soon: true },
            { label: 'General Ledger', href: '#', permission: 'accounting.view', icon: '📒', soon: true },
        ],
    },
    {
        section: 'Insights',
        items: [
            { label: 'Reports', href: '#', permission: 'reports.view', icon: '📈', soon: true },
        ],
    },
];

const visibleNav = computed(() =>
    nav
        .filter((group) => !group.adminOnly || user.value?.roles?.includes('admin'))
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => permissions.value.has(item.permission)),
        }))
        .filter((group) => group.items.length > 0),
);

const logout = () => router.post('/erp/logout');
</script>

<template>
    <div class="min-h-full">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
            @click="sidebarOpen = false"
        />

        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-slate-900 transition-transform duration-200 lg:translate-x-0"
        >
            <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-lg font-black text-white">
                    E
                </div>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-white">ERP Portal</p>
                    <p class="text-xs text-slate-400">Operations console</p>
                </div>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                <div v-for="group in visibleNav" :key="group.section">
                    <p class="mb-2 px-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        {{ group.section }}
                    </p>
                    <ul class="space-y-1">
                        <li v-for="item in group.items" :key="item.label">
                            <Link
                                v-if="!item.soon"
                                :href="item.href"
                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                                :class="currentPath === item.href ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
                            >
                                <span class="text-base">{{ item.icon }}</span>
                                {{ item.label }}
                            </Link>
                            <span
                                v-else
                                class="flex cursor-not-allowed select-none items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-500"
                            >
                                <span class="text-base opacity-70">{{ item.icon }}</span>
                                {{ item.label }}
                                <span class="ml-auto rounded-full bg-white/5 px-2 py-0.5 text-[10px] font-semibold text-slate-400">
                                    Soon
                                </span>
                            </span>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="border-t border-white/10 p-3">
                <div class="flex items-center gap-3 rounded-lg bg-white/5 px-3 py-2">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
                        {{ (user?.name ?? '?').charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-sm font-medium text-white">{{ user?.name }}</p>
                        <p class="truncate text-xs text-slate-400">{{ (user?.roles ?? []).join(', ') || 'Member' }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                <div class="flex items-center gap-3">
                    <button
                        class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 lg:hidden"
                        @click="sidebarOpen = true"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h2 class="hidden text-lg font-semibold text-slate-900 sm:block">ERP Portal</h2>
                </div>

                <Dropdown align="right">
                    <template #trigger>
                        <button class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100">
                            <span class="hidden sm:inline">{{ user?.name }}</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
                                {{ (user?.name ?? '?').charAt(0).toUpperCase() }}
                            </span>
                            <svg class="h-4 w-4 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </template>
                    <template #default>
                        <div class="border-b border-slate-100 px-4 py-2">
                            <p class="text-sm font-medium text-slate-900">{{ user?.name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ user?.email }}</p>
                        </div>
                        <button
                            class="w-full px-4 py-2 text-left text-sm font-medium text-red-600 transition hover:bg-red-50"
                            @click="logout"
                        >
                            Sign out
                        </button>
                    </template>
                </Dropdown>
            </header>

            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <slot />
            </main>
        </div>
    </div>
</template>