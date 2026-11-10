<template>
    <div
        class="timer"
        :class="{ 'is-urgent': remaining > 0 && remaining <= 60 }"
        role="timer"
        :aria-label="`Tiempo para ${label}: ${clock}`"
    >
        <span class="timer-label">{{ label }}</span>
        <span class="timer-value">{{ clock }}</span>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

const props = defineProps({
    // Marca de tiempo Unix (segundos) en la que termina la retención.
    deadline: { type: Number, required: true },
    label: { type: String, default: "Tiempo restante" },
});

const emit = defineEmits(["expired"]);

const now = ref(Math.floor(Date.now() / 1000));
const remaining = computed(() => Math.max(0, props.deadline - now.value));
const clock = computed(() => {
    const minutes = Math.floor(remaining.value / 60);
    const seconds = remaining.value % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
});

let timerId;
let notified = false;

watch(remaining, (value) => {
    if (value === 0 && !notified) {
        notified = true;
        emit("expired");
    }
});

watch(
    () => props.deadline,
    () => {
        notified = false;
    },
);

onMounted(() => {
    timerId = window.setInterval(() => {
        now.value = Math.floor(Date.now() / 1000);
    }, 1000);
});

onUnmounted(() => window.clearInterval(timerId));
</script>

<style scoped>
.timer {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    flex-shrink: 0;
}

.timer-label {
    font-size: var(--text-xs);
    color: var(--color-text-secondary);
}

.timer-value {
    font-variant-numeric: tabular-nums;
    font-size: var(--text-2xl);
    font-weight: 800;
    color: var(--color-brand);
    line-height: 1.1;
}

.timer.is-urgent .timer-value {
    color: var(--color-danger);
}
</style>
