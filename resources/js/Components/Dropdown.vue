<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    align: { type: String, default: 'left' },
});

const open = ref(false);
const root = ref(null);

const onClickOutside = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div ref="root" class="relative">
        <div @click="open = !open"><slot name="trigger" /></div>

        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute z-50 mt-2 w-52 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-slate-200"
                :class="align === 'right' ? 'right-0' : 'left-0'"
            >
                <slot />
            </div>
        </transition>
    </div>
</template>