<script setup>
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    snapshot: { type: Object, required: true },
});

const page = usePage();
const permissions = page.props.auth?.user?.permissions ?? [];
const canExport = permissions.includes('reports.export');
const isCompleted = props.snapshot.status === 'completed';
const isFinished = isCompleted || props.snapshot.status === 'failed';

const regenerate = () => {
    if (!window.confirm('Regenerate this report? A fresh run starts and the current snapshot stays in history.')) {
        return;
    }
    router.post(`/erp/reports/${props.snapshot.id}/regenerate`, {}, { preserveScroll: true });
};
</script>

<template>
    <div v-if="isFinished" class="flex flex-wrap items-center gap-3">
        <a
            v-if="isCompleted && canExport"
            :href="`/erp/reports/${snapshot.id}/export`"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
        >⬇ Export CSV</a>
        <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="regenerate"
        >↻ Regenerate</button>
    </div>
</template>
