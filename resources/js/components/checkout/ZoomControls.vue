<template>
    <div class="zoom-controls" :aria-label="`Zoom del ${label}`" role="group">
        <button type="button" :aria-label="`Acercar ${label}`" :disabled="modelValue >= max" @click="change(step)">+</button>
        <button type="button" :aria-label="`Alejar ${label}`" :disabled="modelValue <= min" @click="change(-step)">−</button>
        <button type="button" :aria-label="`Restablecer zoom del ${label}`" @click="emit('update:modelValue', 1)">⟲</button>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: { type: Number, default: 1 },
    label: { type: String, required: true },
    min: { type: Number, default: 0.6 },
    max: { type: Number, default: 2.5 },
    step: { type: Number, default: 0.25 },
});

const emit = defineEmits(["update:modelValue"]);

function change(delta) {
    emit("update:modelValue", Math.min(props.max, Math.max(props.min, Math.round((props.modelValue + delta) * 100) / 100)));
}
</script>

<style scoped>
.zoom-controls {
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

.zoom-controls button {
    width: 40px;
    height: 40px;
    border: none;
    border-bottom: 1px solid #e5e7eb;
    background: #ffffff;
    color: #111827;
    font-size: 18px;
    cursor: pointer;
}

.zoom-controls button:last-child {
    border-bottom: none;
}

.zoom-controls button:hover:not(:disabled) {
    background: #f3f4f6;
}

.zoom-controls button:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.zoom-controls button:focus-visible {
    outline: 2px solid #16a34a;
    outline-offset: -2px;
}
</style>
