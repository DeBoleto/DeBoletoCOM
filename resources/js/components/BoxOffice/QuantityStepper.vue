<template>
    <div class="inline-flex items-center rounded-lg border border-bo-line bg-bo-card">
        <button
            type="button"
            class="p-2.5 text-bo-slate hover:text-bo-ink disabled:opacity-40"
            :disabled="modelValue <= min"
            :aria-label="`Quitar ${label}`"
            @click="update(modelValue - 1)"
        >
            <BoIcon name="minus" :size="16" />
        </button>
        <input
            :value="modelValue"
            type="number"
            inputmode="numeric"
            :min="min"
            :max="max"
            class="tabular w-14 border-0 p-0 text-center font-semibold focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
            :aria-label="`Cantidad de ${label}`"
            @change="update(Number($event.target.value))"
        />
        <button
            type="button"
            class="p-2.5 text-bo-slate hover:text-bo-ink disabled:opacity-40"
            :disabled="modelValue >= max"
            :aria-label="`Agregar ${label}`"
            @click="update(modelValue + 1)"
        >
            <BoIcon name="plus" :size="16" />
        </button>
    </div>
</template>

<script setup>
import BoIcon from "./BoIcon.vue";

const props = defineProps({
    modelValue: { type: Number, default: 0 },
    min: { type: Number, default: 0 },
    max: { type: Number, default: 50 },
    label: { type: String, default: "boletos" },
});

const emit = defineEmits(["update:modelValue"]);

function update(value) {
    const safe = Number.isFinite(value) ? Math.round(value) : props.min;
    emit("update:modelValue", Math.min(props.max, Math.max(props.min, safe)));
}
</script>
