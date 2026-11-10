<template>
    <div class="stepper">
        <button type="button" :disabled="modelValue <= min" :aria-label="`Quitar ${label}`" @click="update(modelValue - 1)">−</button>
        <output :aria-label="`Cantidad de ${label}`" aria-live="polite">{{ modelValue }}</output>
        <button type="button" :disabled="modelValue >= max" :aria-label="`Agregar ${label}`" @click="update(modelValue + 1)">+</button>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: { type: Number, default: 0 },
    min: { type: Number, default: 0 },
    max: { type: Number, default: 10 },
    label: { type: String, default: "boletos" },
});

const emit = defineEmits(["update:modelValue"]);

function update(value) {
    emit("update:modelValue", Math.min(props.max, Math.max(props.min, value)));
}
</script>

<style scoped>
.stepper {
    display: inline-flex;
    align-items: center;
    border: 1px solid #d1d5db;
    border-radius: 999px;
    background: #ffffff;
    color: #111827;
}

.stepper button {
    width: 44px;
    height: 44px;
    border: none;
    background: none;
    font-size: 1.25rem;
    color: inherit;
    cursor: pointer;
}

.stepper button:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.stepper button:focus-visible {
    outline: 2px solid #16a34a;
    outline-offset: -2px;
    border-radius: 999px;
}

.stepper output {
    min-width: 32px;
    text-align: center;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
</style>
