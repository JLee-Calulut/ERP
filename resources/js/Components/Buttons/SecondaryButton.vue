<script setup>
defineProps({
    type: { type: String, default: 'button' },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
    >
        <slot />
    </button>
</template>