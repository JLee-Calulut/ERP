<script setup>
defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <header v-if="title || $slots.actions" class="mb-4 flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
            <div v-if="$slots.actions" class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </header>
        <slot />
    </section>
</template>