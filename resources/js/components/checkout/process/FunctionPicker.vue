<template>
    <section class="picker" aria-labelledby="picker-title">
        <header class="picker-head">
            <p class="picker-kicker">Paso 1 de 3</p>
            <h2 id="picker-title" class="picker-title">¿Qué fecha quieres vivir?</h2>
            <p class="picker-sub">Elige la función y después tus lugares en el mapa.</p>
        </header>

        <ul class="picker-grid">
            <li v-for="(fn, index) in functions" :key="fn.id" :style="{ '--delay': `${index * 60}ms` }">
                <button type="button" class="date-card" @click="emit('choose', fn)">
                    <span class="date-cal" aria-hidden="true">
                        <span class="date-month">{{ parts(fn).month }}</span>
                        <span class="date-day">{{ parts(fn).day }}</span>
                    </span>
                    <span class="date-info">
                        <span class="date-weekday">{{ parts(fn).weekday }}</span>
                        <span class="date-full">{{ fn.date }}</span>
                        <span v-if="fn.time" class="date-time">{{ fn.time }} h</span>
                    </span>
                    <span class="date-cta" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                    </span>
                </button>
            </li>
        </ul>
    </section>
</template>

<script setup>
defineProps({
    functions: { type: Array, required: true },
});

const emit = defineEmits(["choose"]);

const monthFormat = new Intl.DateTimeFormat("es-MX", { month: "short" });
const weekdayFormat = new Intl.DateTimeFormat("es-MX", { weekday: "long" });

function parts(fn) {
    const date = fn.iso ? new Date(String(fn.iso).replace(" ", "T")) : null;

    if (!date || Number.isNaN(date.getTime())) {
        return { month: "", day: "•", weekday: "" };
    }

    return {
        month: monthFormat.format(date).replace(".", ""),
        day: String(date.getDate()).padStart(2, "0"),
        weekday: weekdayFormat.format(date),
    };
}
</script>

<style scoped>
.picker {
    max-width: 880px;
    margin: 0 auto;
    padding: var(--space-8) 0 var(--space-12);
}

.picker-head {
    text-align: center;
    margin-bottom: var(--space-6);
}

.picker-kicker {
    color: var(--color-brand);
    font-size: var(--text-xs);
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.picker-title {
    font-size: var(--text-3xl);
    font-weight: 800;
    letter-spacing: -0.02em;
    margin-top: 6px;
}

.picker-sub {
    color: var(--color-text-secondary);
    margin-top: 6px;
}

.picker-grid {
    display: grid;
    gap: 12px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.picker-grid li {
    animation: rise 420ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
    animation-delay: var(--delay);
}

.date-card {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 18px;
    width: 100%;
    padding: 14px 18px 14px 14px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-lg);
    background: linear-gradient(135deg, var(--color-surface-2), var(--color-surface-1));
    color: var(--color-text-primary);
    text-align: left;
    cursor: pointer;
    transition: transform var(--transition-base), border-color var(--transition-base), box-shadow var(--transition-base);
}

.date-card:hover,
.date-card:focus-visible {
    transform: translateY(-2px);
    border-color: var(--color-brand);
    box-shadow: 0 10px 30px rgba(78, 203, 160, 0.18);
    outline: none;
}

.date-cal {
    display: grid;
    place-items: center;
    width: 64px;
    height: 68px;
    border-radius: var(--radius-md);
    background: var(--color-brand);
    color: #06291d;
    line-height: 1;
}

.date-month {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.date-day {
    font-size: 1.9rem;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
}

.date-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.date-weekday {
    color: var(--color-brand);
    font-size: var(--text-xs);
    font-weight: 700;
    text-transform: capitalize;
}

.date-full {
    font-size: var(--text-lg);
    font-weight: 800;
}

.date-time {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.date-cta {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: var(--radius-full);
    background: var(--color-surface-3);
    color: var(--color-brand);
    transition: background var(--transition-base), color var(--transition-base), transform var(--transition-base);
}

.date-card:hover .date-cta {
    background: var(--color-brand);
    color: #06291d;
    transform: translateX(3px);
}

@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .picker-grid li {
        animation: none;
    }

    .date-card,
    .date-cta {
        transition: none;
    }
}
</style>
