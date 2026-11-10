<template>
    <ol class="steps" aria-label="Pasos de la compra">
        <li
            v-for="(step, index) in steps"
            :key="step"
            class="step"
            :class="{ 'is-done': index < current, 'is-current': index === current }"
            :aria-current="index === current ? 'step' : undefined"
        >
            <span class="step-dot">{{ index < current ? "✓" : index + 1 }}</span>
            <span class="step-label">{{ step }}</span>
        </li>
    </ol>
</template>

<script setup>
defineProps({
    current: { type: Number, required: true },
});

const steps = ["Boletos", "Pago", "Listo"];
</script>

<style scoped>
.steps {
    display: flex;
    align-items: center;
    gap: 6px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.step {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--color-text-muted);
    font-size: var(--text-xs);
    font-weight: 600;
}

.step + .step::before {
    content: "";
    width: 18px;
    height: 1px;
    background: var(--color-border-strong);
    margin-right: 2px;
}

.step-dot {
    display: inline-grid;
    place-items: center;
    width: 22px;
    height: 22px;
    border-radius: var(--radius-full);
    border: 1px solid var(--color-border-strong);
    font-variant-numeric: tabular-nums;
}

.step.is-done {
    color: var(--color-text-secondary);
}

.step.is-done .step-dot {
    background: var(--color-brand-dim);
    border-color: transparent;
    color: var(--color-brand);
}

.step.is-current {
    color: var(--color-text-primary);
}

.step.is-current .step-dot {
    background: var(--color-brand);
    border-color: var(--color-brand);
    color: #06291d;
}

@media (max-width: 520px) {
    .step-label {
        display: none;
    }

    .step.is-current .step-label {
        display: inline;
    }
}
</style>
