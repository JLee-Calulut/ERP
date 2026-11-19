<script setup>
import { useForm } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
import ErpLayout from '@/Components/ErpLayout.vue';
import Card from '@/Components/Cards/Card.vue';

const props = defineProps({
    reports: { type: Array, default: () => [] },
});

const form = useForm({
    type: '',
});

const runReport = (type) => {
    form.type = type;
    form.post('/erp/reports');
};

const statusStyles = {
    pending: 'bg-amber-100 text-amber-700',
    completed: 'bg-emerald-100 text-emerald-700',
    failed: 'bg-red-100 text-red-700',
};

const formatGeneratedAt = (value) => (value ? new Date(value).toLocaleString() : null);
</script>

<template>
    <ErpLayout>
        <Head title="Reports" />

        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Reports</h1>
            <p class="mt-1 text-sm text-slate-500">
                Run operational reports and open their latest snapshots. Each run is queued and the
                computed output is stored on the snapshot.
            </p>
        </header>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Card v-for="report in reports" :key="report.type">
                <div class="flex items-start gap-4">
                    <span
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-2xl"
                    >{{ report.icon }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-base font-semibold text-slate-900">{{ report.label }}</h3>
                            <span
                                v-if="report.latest_snapshot"
                                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="statusStyles[report.latest_snapshot.status] || 'bg-slate-100 text-slate-600'"
                            >{{ report.latest_snapshot.status_label }}</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ report.description }}</p>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-50"
                                @click="runReport(report.type)"
                            >▶ Run report</button>

                            <template v-if="report.latest_snapshot">
                                <Link
                                    v-if="report.latest_snapshot.status === 'completed'"
                                    :href="`/erp/reports/${report.latest_snapshot.id}`"
                                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                >Open latest</Link>
                                <Link
                                    :href="`/erp/reports/history?type=${report.type}`"
                                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                >History</Link>
                                <p
                                    v-if="report.latest_snapshot.generated_at"
                                    class="text-xs text-slate-400"
                                >Last run {{ formatGeneratedAt(report.latest_snapshot.generated_at) }}</p>
                            </template>
                            <p v-else class="text-xs text-slate-400">No snapshot yet — run the report to see history.</p>
                        </div>

                        <p
                            v-if="report.latest_snapshot?.error"
                            class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600"
                        >{{ report.latest_snapshot.error }}</p>
                    </div>
                </div>
            </Card>
        </div>
    </ErpLayout>
</template>
