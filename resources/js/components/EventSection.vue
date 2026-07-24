<template>
  <section
    class="event-section"
    :id="sectionId"
    :aria-labelledby="`${sectionId}-heading`"
  >
    <div class="container">
      <div class="section-header">
        <div class="section-title-group">
          <span
            class="section-icon"
            :class="`section-icon--${accentColor}`"
            aria-hidden="true"
          >{{ icon }}</span>
          <div>
            <h2 :id="`${sectionId}-heading`" class="section-title">{{ title }}</h2>
            <p v-if="subtitle" class="section-subtitle">{{ subtitle }}</p>
          </div>
        </div>
        <a
          :href="viewAllLink"
          class="section-view-all"
          :aria-label="`Ver todos los eventos de ${title}`"
        >
          Ver todos
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="m9 18 6-6-6-6"/>
          </svg>
        </a>
      </div>

      <ul
        role="list"
        class="events-grid"
        :class="`events-grid--${layout}`"
        :aria-label="`Lista de eventos: ${title}`"
      >
        <li
          v-for="event in events"
          :key="event.id"
          class="grid-item"
        >
          <EventCard
            :event="event"
            :featured="layout === 'featured'"
          />
        </li>
      </ul>

      <div class="scroll-hint" aria-hidden="true">
        <span class="scroll-hint-dot" v-for="n in Math.ceil(events.length / 2)" :key="n"></span>
      </div>
    </div>
  </section>
</template>

<script setup>
import EventCard from './EventCard.vue'

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  sectionId: { type: String, required: true },
  icon: { type: String, default: '🎫' },
  accentColor: { type: String, default: 'brand' },
  viewAllLink: { type: String, default: '/eventos' },
  events: { type: Array, required: true },
  layout: { type: String, default: 'grid' },
})
</script>

<style scoped>
.event-section {
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

.section-icon--brand       { background: var(--color-brand-dim); }
.section-icon--festival    { background: rgba(249, 115, 22, 0.15); }
.section-icon--conference  { background: rgba(59, 130, 246, 0.15); }

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
  transition: background var(--transition-fast), color var(--transition-fast), border-color var(--transition-fast);
  white-space: nowrap;
  flex-shrink: 0;
}

.section-view-all:hover {
  background: var(--color-brand-dim);
  border-color: var(--color-brand);
}

.events-grid {
  list-style: none;
  display: grid;
  gap: var(--space-5);
}

.events-grid--grid {
  grid-template-columns: repeat(3, 1fr);
}

.events-grid--featured {
  grid-template-columns: repeat(3, 1fr);
}

.scroll-hint {
  display: none;
  justify-content: center;
  gap: var(--space-2);
  margin-top: var(--space-5);
}

.scroll-hint-dot {
  width: 6px;
  height: 6px;
  border-radius: var(--radius-full);
  background: var(--color-surface-3);
}

@media (min-width: 1400px) {
  .events-grid--grid,
  .events-grid--featured { grid-template-columns: repeat(4, 1fr); }
}

@media (max-width: 1100px) {
  .events-grid--grid     { grid-template-columns: repeat(3, 1fr); }
  .events-grid--featured { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
  .events-grid--grid,
  .events-grid--featured {
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-4);
  }
  .section-title { font-size: var(--text-xl); }
}

@media (max-width: 540px) {
  .event-section { padding-block: var(--space-12) var(--space-6); }

  .events-grid--grid,
  .events-grid--featured {
    display: flex;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    gap: var(--space-4);
    padding-bottom: var(--space-3);
    scrollbar-width: none;
  }

  .events-grid--grid::-webkit-scrollbar,
  .events-grid--featured::-webkit-scrollbar { display: none; }

  .grid-item {
    flex: 0 0 80vw;
    max-width: 320px;
    scroll-snap-align: start;
  }

  .scroll-hint { display: flex; }
}
</style>
