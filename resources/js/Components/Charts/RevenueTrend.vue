<script setup>
import { computed } from 'vue';

const props = defineProps({
    labels: { type: Array, default: () => [] },
    values: { type: Array, default: () => [] },
});

const max = computed(() =>
    Math.max(1, ...props.values.map((value) => Number(value) || 0)),
);

const isEmpty = computed(() => props.values.every((value) => !Number(value)));
</script>

<template>
    <div>
        <div class="flex h-48 items-stretch gap-3">
            <div v-for="(value, index) in values" :key="index" class="flex flex-1 flex-col items-center justify-end gap-1">
                <span class="text-xs font-semibold text-slate-600">{{ Number(value).toLocaleString() }}</span>
                <div
                    class="w-full rounded-t-lg bg-brand-600 transition hover:bg-brand-700"
                    :style="{ height: `${Math.max(2, Math.round((Number(value) / max) * 100))}%` }"
                />
                <span class="mt-2 text-xs text-slate-500">{{ labels[index] }}</span>
            </div>
        </div>
        <p v-if="isEmpty" class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-center text-xs text-slate-500">
            No sales revenue recorded yet — the trend fills in once the Sales module goes live.
        </p>
    </div>
</template>