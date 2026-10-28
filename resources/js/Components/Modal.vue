<script setup>
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: 'lg' },
});

const emit = defineEmits(['close']);

const widths = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

watch(
    () => props.show,
    (visible) => {
        document.removeEventListener('keydown', onKeydown);
        if (visible) {
            document.addEventListener('keydown', onKeydown);
        }
    },
);

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <transition
            appear
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50" @click="emit('close')" />
                <div class="relative w-full rounded-2xl bg-white p-6 shadow-2xl" :class="widths[maxWidth] || widths.lg">
                    <slot />
                </div>
            </div>
        </transition>
    </Teleport>
</template>