<template>
    <section class="next-section" aria-labelledby="next-events-heading">
        <div class="container">
            <header class="section-header">
                <div class="section-title-group">
                    <span
                        class="section-icon section-icon--brand"
                        aria-hidden="true"
                        >📅</span
                    >
                    <div>
                        <h2 id="next-events-heading" class="section-title">
                            PRÓXIMOS EVENTOS
                        </h2>
                        <p class="section-subtitle">
                            No te pierdas lo que viene esta temporada
                        </p>
                    </div>
                </div>
                <a class="section-view-all" href="/eventos?filtro=proximos"
                    >VER TODOS</a
                >
            </header>

            <div v-if="events.length" class="filter-bar">
                <label class="filter-label" for="category-filter"
                    >Categoría</label
                >
                <select
                    id="category-filter"
                    v-model="filter"
                    class="filter-select"
                    aria-label="Filtrar por categoría"
                >
                    <option value="all">Todas las categorías</option>
                    <option
                        v-for="cat in categoryOptions"
                        :key="cat"
                        :value="cat"
                    >
                        {{ cat }}
                    </option>
                </select>
            </div>

            <ul
                v-if="filteredAll.length"
                role="list"
                class="next-grid"
                :aria-label="`Lista de próximos eventos: ${filter === 'all' ? 'todas las categorías' : filter}`"
            >
                <li
                    v-for="event in displayedEvents"
                    :key="event.id"
                    class="grid-item"
                >
                    <EventCardAlt :event="event" />
                </li>
                <li
                    v-if="hasMore"
                    class="grid-item"
                    :class="{ 'grid-item--flush-end': isMoreCardAloneInRow }"
                >
                    <button
                        type="button"
                        class="more-card"
                        :aria-label="loadMoreLabel"
                        @click="loadMore"
                    >
                        <span class="more-plus" aria-hidden="true">+</span>
                        <span class="more-label">Ver más eventos</span>
                    </button>
                </li>
            </ul>
            <p v-else class="empty-state">
                No hay eventos disponibles para esta categoría.
            </p>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import EventCardAlt from "./EventCardAlt.vue";

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

const filter = ref("all");
const loadedCount = ref(5);
const PAGE_SIZE = 15;

const filteredAll = computed(() => {
    if (filter.value === "all") {
        return props.events;
    }
    return props.events.filter(
        (e) => (e.categoryName || "Sin categoría") === filter.value,
    );
});

const displayedEvents = computed(() =>
    filteredAll.value.slice(0, loadedCount.value),
);

const isMoreCardAloneInRow = computed(
    () => displayedEvents.value.length % 5 === 0,
);

const hasMore = computed(() => loadedCount.value < filteredAll.value.length);

const categoryOptions = computed(() => {
    const names = props.events.map((e) => e.categoryName || "Sin categoría");
    return [...new Set(names)];
});

const loadMoreLabel = computed(() =>
    filter.value === "all"
        ? "Cargar más eventos"
        : `Cargar más eventos de ${filter.value}`,
);

function loadMore() {
    loadedCount.value = Math.min(
        loadedCount.value + PAGE_SIZE,
        filteredAll.value.length,
    );
}

watch(filter, () => {
    loadedCount.value = 5;
});
</script>

<style scoped>
.next-section {
    padding-block: var(--space-16) var(--space-8);
}

.section-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--space-4);
    margin-bottom: var(--space-8);
}

.section-title-group {
    display: flex;
    align-items: center;
    gap: var(--space-4);
}

.section-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: var(--radius-md);
    font-size: 1.4rem;
    flex-shrink: 0;
}

.section-icon--brand {
    background: var(--color-brand-dim);
}

.section-title {
    font-size: var(--text-2xl);
    font-weight: 800;
    color: var(--color-text-primary);
    letter-spacing: -0.02em;
}

.section-subtitle {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    margin-top: var(--space-1);
}

.section-view-all {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--color-brand);
    padding: var(--space-2) var(--space-4);
    border-radius: var(--radius-xs);
    border: 1px solid rgba(78, 203, 160, 0.6);
    transition:
        background var(--transition-fast),
        color var(--transition-fast),
        border-color var(--transition-fast);
    white-space: nowrap;
    flex-shrink: 0;
}

.section-view-all:hover {
    background: var(--color-brand-dim);
    border-color: var(--color-brand);
}

.filter-bar {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-8);
}

.filter-label {
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--color-text-secondary);
}

.filter-select {
    padding: var(--space-2) var(--space-4);
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--color-text-primary);
    background: var(--color-surface-2);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    cursor: pointer;
    font-family: inherit;
    transition:
        border-color var(--transition-fast),
        background var(--transition-fast);
}

.filter-select:hover,
.filter-select:focus {
    border-color: var(--color-brand);
    outline: none;
}

.next-grid {
    list-style: none;
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: var(--space-5);
}

.grid-item {
    min-width: 0;
    width: 90%;
    justify-self: center;
}

@media (min-width: 853px) {
    .grid-item--flush-end {
        grid-column: 5;

    }
}

.more-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: var(--space-3);
    width: 100%;
    aspect-ratio: 3 / 4;
    border: none;
    border-radius: var(--radius-xl);
    background: linear-gradient(135deg, var(--color-accent-hover), #0e4d4a);
    color: #fff;
    cursor: pointer;
    font-family: inherit;
    transition:
        transform var(--transition-base),
        box-shadow var(--transition-base);
}

.more-card:hover {
    transform: scale(1.04) translateY(-6px);
    box-shadow:
        0 0 0 3px #4ecba0,
        0 0 12px 4px rgba(78, 203, 160, 0.6),
        0 0 30px 10px rgba(60, 175, 135, 0.22),
        0 0 60px 18px rgba(43, 161, 124, 0.07),
        0 10px 30px rgba(0, 0, 0, 0.45);
}

.more-card:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.more-plus {
    font-size: 3rem;
    font-weight: 200;
    line-height: 1;
}

.more-label {
    font-size: var(--text-sm);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.empty-state {
    padding: var(--space-12);
    text-align: center;
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    background: var(--color-surface-1);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
}

@media (max-width: 852px) {
    .next-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-4);
    }
}

@media (max-width: 768px) {
    .section-title {
        font-size: var(--text-xl);
    }
}

@media (max-width: 540px) {
    .next-section {
        padding-block: var(--space-12) var(--space-6);
    }
}

@media (max-width: 480px) {
    .next-grid {
        grid-template-columns: 1fr;
    }
}
</style>
