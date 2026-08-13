<template>
    <section
        v-if="events.length"
        class="nearby-strip"
        aria-labelledby="nearby-events-heading"
    >
        <div class="container">
            <div class="nearby-strip-header">
                <h2 id="nearby-events-heading" class="nearby-strip-title">
                    Eventos Cercanos
                </h2>
                <div class="nearby-strip-controls">
                    <a
                        href="javascript:void(0)"
                        class="nearby-strip-arrow nearby-strip-arrow-prev"
                        aria-label="Anterior"
                        @click.prevent="scrollPrev"
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 16 16"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M10 12L6 8L10 4"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </a>
                    <a
                        href="javascript:void(0)"
                        class="nearby-strip-arrow nearby-strip-arrow-next"
                        aria-label="Siguiente"
                        @click.prevent="scrollNext"
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 16 16"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M6 4L10 8L6 12"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </a>
                </div>
            </div>

            <div ref="scrollRef" class="nearby-strip-scroll">
                <ul class="nearby-strip-track" role="list">
                    <li
                        v-for="event in events"
                        :key="event.slug"
                        class="nearby-strip-item"
                    >
                        <a
                            :href="`/evento/${event.slug}`"
                            class="nearby-strip-card"
                            :aria-label="`Ver evento: ${event.title}`"
                        >
                            <img
                                :src="event.image"
                                :alt="`Imagen del evento: ${event.title}`"
                                class="nearby-strip-thumb"
                                loading="lazy"
                                decoding="async"
                            />
                            <div class="nearby-strip-info">
                                <span class="nearby-strip-name">{{
                                    event.title
                                }}</span>
                                <span class="nearby-strip-row">{{
                                    event.date
                                }}</span>
                                <span class="nearby-strip-row">{{
                                    event.city
                                }}</span>
                                <span class="nearby-strip-row">{{
                                    event.estado
                                }}</span>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from "vue";

defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

const scrollRef = ref(null);

function getStep() {
    if (!scrollRef.value) return 300;
    const item = scrollRef.value.querySelector(".nearby-strip-item");
    const w = item ? item.getBoundingClientRect().width : 300;
    return w + 8;
}

function scrollPrev() {
    if (!scrollRef.value) return;
    scrollRef.value.scrollBy({ left: -getStep(), behavior: "smooth" });
}

function scrollNext() {
    if (!scrollRef.value) return;
    scrollRef.value.scrollBy({ left: getStep(), behavior: "smooth" });
}
</script>

<style scoped>
.nearby-strip {
    padding-block: var(--space-2) var(--space-12);
}

.nearby-strip-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}

.nearby-strip-title {
    font-size: var(--text-2xl);
    font-weight: 800;
    color: var(--color-text-primary);
    letter-spacing: -0.02em;
}

.nearby-strip-controls {
    display: flex;
    gap: var(--space-3);
}

.nearby-strip-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: var(--radius-full);
    background: var(--color-surface-1);
    border: 1px solid var(--color-border);
    color: var(--color-text-primary);
    transition:
        border-color var(--transition-fast),
        background var(--transition-fast),
        color var(--transition-fast);
}

.nearby-strip-arrow:hover {
    border-color: var(--color-brand);
    background: var(--color-brand-dim);
    color: var(--color-brand);
}

.nearby-strip-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x proximity;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.nearby-strip-scroll::-webkit-scrollbar {
    display: none;
}

.nearby-strip-track {
    display: flex;
    gap: var(--space-4);
    margin: 0;
    padding: 4px 0;
    list-style: none;
}

.nearby-strip-item {
    flex: 0 0 auto;
    scroll-snap-align: start;
}

.nearby-strip-card {
    display: flex;
    align-items: stretch;
    gap: var(--space-4);
    padding: var(--space-4);
    background: var(--color-surface-1);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    text-decoration: none;
    transition:
        border-color var(--transition-base),
        box-shadow var(--transition-base),
        transform var(--transition-base);
}

.nearby-strip-card:hover {
    border-color: var(--color-brand);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.nearby-strip-thumb {
    width: 120px;
    height: 120px;
    flex-shrink: 0;
    border-radius: var(--radius-sm);
    object-fit: cover;
    display: block;
    background: var(--color-surface-2);
}

.nearby-strip-info {
    display: flex;
    flex-direction: column;
    gap: var(--space-1);
    justify-content: center;
    min-width: 0;
    max-width: 220px;
}

.nearby-strip-name {
    font-size: var(--text-sm);
    font-weight: 700;
    line-height: 1.3;
    color: var(--color-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: var(--space-1);
}

.nearby-strip-name:hover {
    color: var(--color-brand);
}

.nearby-strip-row {
    font-size: var(--text-xs);
    color: var(--color-text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (max-width: 540px) {
    .nearby-strip {
        padding-block: var(--space-2) var(--space-10);
    }

    .nearby-strip-title {
        font-size: var(--text-xl);
    }

    .nearby-strip-thumb {
        width: 96px;
        height: 96px;
    }
}
</style>
