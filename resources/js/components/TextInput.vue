<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: String,
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<style scoped>
input {
    border-color: var(--color-border);
    background-color: var(--color-surface-2);
    color: var(--color-text-primary);
}

input:focus {
    border-color: var(--color-brand);
    --tw-ring-color: var(--color-brand);
}
</style>

<template>
    <input
        ref="input"
        class="rounded-md shadow-sm"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
