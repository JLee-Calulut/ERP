<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: '📊' },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'blue' },
});

const tones = {
    blue: 'bg-brand-50 text-brand-600',
    green: 'bg-emerald-50 text-emerald-600',
    red: 'bg-red-50 text-red-600',
    amber: 'bg-amber-50 text-amber-600',
    violet: 'bg-violet-50 text-violet-600',
};
</script>

<template>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-500">{{ label }}</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ value }}</p>
                <p v-if="hint" class="mt-1 truncate text-xs text-slate-400">{{ hint }}</p>
            </div>
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg"
                :class="tones[tone] || tones.blue"
            >
                {{ icon }}
            </span>
        </div>
    </div>
</template>