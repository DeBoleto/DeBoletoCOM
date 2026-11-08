<template>
    <ol class="flex items-center gap-2 text-sm" aria-label="Pasos de la venta">
        <li
            v-for="(step, index) in steps"
            :key="step"
            class="flex items-center gap-2"
            :aria-current="index + 1 === current ? 'step' : undefined"
        >
            <span
                class="tabular inline-flex size-7 items-center justify-center rounded-full border text-xs font-semibold"
                :class="
                    index + 1 < current
                        ? 'border-bo-green bg-bo-green text-white'
                        : index + 1 === current
                          ? 'border-bo-ink bg-bo-ink text-white'
                          : 'border-bo-line bg-bo-card text-bo-muted'
                "
            >
                <BoIcon v-if="index + 1 < current" name="check" :size="14" />
                <template v-else>{{ index + 1 }}</template>
            </span>
            <span :class="index + 1 === current ? 'font-semibold text-bo-ink' : 'text-bo-slate'" class="hidden sm:inline">
                {{ step }}
            </span>
            <span v-if="index < steps.length - 1" class="mx-1 h-px w-6 bg-bo-line sm:w-10" />
        </li>
    </ol>
</template>

<script setup>
import BoIcon from "./BoIcon.vue";

defineProps({
    current: { type: Number, required: true },
    steps: { type: Array, default: () => ["Boletos", "Cobro", "Entrega"] },
});
</script>
