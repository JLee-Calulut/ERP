<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    entries: { type: Array, default: () => [] },
});

const page = usePage();
const canManage = (page.props.auth?.user?.permissions ?? []).includes('accounting.manage');

const formatTotal = (value) =>
    `$${Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
</script>

<template>
    <ErpLayout>
        <Head title="Journal" />

        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Journal</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Double-entry journal entries, newest first ({{ entries.length }} posted).
                </p>
            </div>
            <Link
                v-if="canManage"
                href="/erp/accounting/journal/create"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
            >+ Post entry</Link>
        </header>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Entry</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Lines</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Posted by</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="entry in entries" :key="entry.id">
                            <td class="px-4 py-3">
                                <p class="font-mono font-medium text-slate-900">{{ entry.entry_number }}</p>
                                <p class="text-xs text-slate-400">#{{ entry.id }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-slate-700">{{ entry.journal_date }}</p>
                                <p class="text-xs text-slate-400">{{ entry.journal_date_readable }}</p>
                            </td>
                            <td class="max-w-md px-4 py-3 text-slate-700">{{ entry.description }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ entry.lines_count }}</td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900">
                                {{ formatTotal(entry.total) }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ entry.posted_by ?? '—' }}</td>
                        </tr>
                        <tr v-if="entries.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                                No journal entries posted yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </ErpLayout>
</template>
