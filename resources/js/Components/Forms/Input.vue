<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    type: { type: String, default: 'text' },
    modelValue: { type: [String, Number], default: '' },
    required: { type: Boolean, default: false },
    autocomplete: { type: String, default: undefined },
    placeholder: { type: String, default: '' },
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value.focus();
    }
});
</script>

<template>
    <input
        ref="input"
        :type="type"
        :value="modelValue"
        :required="required"
        :autocomplete="autocomplete"
        :placeholder="placeholder"
        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
        @input="$emit('update:modelValue', $event.target.value)"
    />
</template>