<template>
  <article
    class="event-card"
    :class="[`event-card--${variant}`, { 'event-card--featured': featured }]"
    :aria-label="`Evento: ${event.title}`"
  >
    <a :href="`/evento/${event.slug}`" class="card-link" tabindex="-1" aria-hidden="true"></a>

    <div class="card-image-wrap">
      <picture>
        <source :srcset="event.image.replace('.png', '.webp')" type="image/webp" />
        <source :srcset="event.image.replace('.png', '.avif')" type="image/avif" />
        <img
          :src="event.image"
          :alt="`Imagen del evento: ${event.title}`"
          class="card-image"
          loading="lazy"
          decoding="async"
          width="400"
          height="220"
        />
      </picture>
      <div class="date-badge">
        <span class="d">{{ dateBadge.day }}</span>
        <span class="m">{{ dateBadge.month }}</span>
      </div>
      <span
        v-if="event.availability === 'sold-out'"
        class="card-badge card-badge--sold"
        aria-label="Agotado"
      >Agotado</span>
      <span
        v-else-if="event.availability === 'low'"
        class="card-badge card-badge--low"
        aria-label="Pocos boletos disponibles"
      >Últimos boletos</span>
      <button
        type="button"
        class="card-wishlist"
        :class="{ 'card-wishlist--active': isWishlisted }"
        :aria-label="isWishlisted ? 'Quitar de favoritos' : 'Agregar a favoritos'"
        @click.prevent="toggleWishlist"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
      </button>
    </div>

    <div class="card-body">
      <div class="card-meta">

        <span class="card-location">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
          </svg>
          {{ event.venue }}, {{ event.city }}
        </span>
      </div>

      <h3 class="card-title">
        <a :href="`/evento/${event.slug}`" class="card-title-link">{{ event.title }}</a>
      </h3>

      <p v-if="event.artist" class="card-artist">{{ event.artist }}</p>

      <div class="card-footer">
        <div class="card-price" aria-label="Precio desde">
          <span class="price-from">Desde</span>
          <strong class="price-amount">{{ event.priceFormatted }}</strong>
        </div>

      </div>
    </div>
  </article>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  event: {
    type: Object,
    required: true,
  },
  variant: {
    type: String,
    default: 'default',
  },
  featured: {
    type: Boolean,
    default: false,
  },
})

const isWishlisted = ref(false)
function toggleWishlist() {
  isWishlisted.value = !isWishlisted.value
}

const dateBadge = computed(() => {
  const parts = props.event.date.split(' ')
  const day = parts[0].split(/[–-]/)[0]
  const month = (parts[1] || '').toUpperCase()
  return { day, month }
})

const origin = window.location.origin

const availabilityMap = {
  available: 'https://schema.org/InStock',
  low: 'https://schema.org/LimitedAvailability',
  'sold-out': 'https://schema.org/SoldOut',
}

const eventSchema = computed(() => {
  const e = props.event
  const price = parseFloat(e.priceFormatted.replace(/[^0-9.]/g, ''))

  const schema = {
    '@context': 'https://schema.org',
    '@type': 'Event',
    name: e.title,
    description: `${e.title}. ${e.date} en ${e.venue}, ${e.city}.`,
    startDate: e.dateISO,
    eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
    eventStatus: 'https://schema.org/EventScheduled',
    location: {
      '@type': 'Place',
      name: e.venue,
      address: { '@type': 'PostalAddress', addressLocality: e.city },
    },
    image: `${origin}${e.image}`,
    offers: {
      '@type': 'Offer',
      price,
      priceCurrency: 'MXN',
      availability: availabilityMap[e.availability] ?? 'https://schema.org/InStock',
      url: `${origin}/evento/${e.slug}`,
    },
  }

  if (e.artist) {
    schema.performer = { '@type': 'PerformingGroup', name: e.artist }
  }

  return schema
})
</script>

<style scoped>
.event-card {
  position: relative;
  background: var(--color-surface-1);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  height: 100%;
  transition: transform var(--transition-base), border-color var(--transition-base), box-shadow var(--transition-base);
}

.event-card:hover {
  transform: scale(1.04) translateY(-6px);
  border-color: var(--color-border-strong);
  box-shadow: 0 0 0 3px #4ecba0, 0 0 12px 4px rgba(78,203,160,.60), 0 0 30px 10px rgba(60,175,135,.22), 0 0 60px 18px rgba(43,161,124,.07), 0 10px 30px rgba(0,0,0,.45);
  text-decoration: none;
  color: inherit;
}

.card-link {
  position: absolute;
  inset: 0;
  z-index: 0;
}

.card-image-wrap {
  position: relative;
  overflow: hidden;
  aspect-ratio: 4 / 3;
}

.card-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}

.event-card:hover .card-image { transform: scale(1.05); }

.date-badge {
  position: absolute;
  top: var(--space-3);
  left: var(--space-3);
  display: flex;
  flex-direction: column;
  align-items: center;
  line-height: 1;
  padding: var(--space-2) var(--space-3);
  background: rgba(10, 10, 15, 0.85);
  border-radius: var(--radius-lg);
  z-index: 1;
}

.date-badge .d {
  font-size: 20px;
  font-weight: 800;
  color: #fff;
}

.date-badge .m {
  font-size: 10px;
  font-weight: 700;
  color: var(--color-brand);
  letter-spacing: 0.08em;
  margin-top: 2px;
}

.card-badge {
  position: absolute;
  top: var(--space-3);
  right: var(--space-3);
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-full);
  font-size: 11px;
  font-weight: 700;
  z-index: 1;
}

.card-badge--sold { background: rgba(239, 68, 68, 0.9); color: #fff; }
.card-badge--low  { background: rgba(245, 158, 11, 0.9); color: #fff; }

.card-wishlist {
  position: absolute;
  bottom: var(--space-3);
  right: var(--space-3);
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(10, 10, 15, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: var(--radius-full);
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background var(--transition-fast), color var(--transition-fast), transform var(--transition-fast);
  z-index: 2;
}

.card-wishlist:hover { background: rgba(229, 53, 171, 0.2); color: var(--color-brand); }
.card-wishlist--active { color: var(--color-brand); }
.card-wishlist--active svg { fill: var(--color-brand); stroke: var(--color-brand); }
.card-wishlist:hover { transform: scale(1.1); }

.card-body {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-5);
  flex: 1;
}

.card-meta {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  flex-wrap: wrap;
}

.card-location {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-xs);
  color: var(--color-text-primary);
  font-weight: 500;
}

.card-title {
  font-size: var(--text-base);
  font-weight: 700;
  line-height: 1.3;
  color: var(--color-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card-title-link {
  color: inherit;
  transition: color var(--transition-fast);
  position: relative;
  z-index: 1;
}

.card-title-link:hover { color: var(--color-brand); }

.card-artist {
  font-size: var(--text-sm);
  color: var(--color-text-primary);
  font-weight: 500;
}

.card-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-3);
  margin-top: auto;
  padding-top: var(--space-4);
  border-top: 1px solid var(--color-border);
}

.card-price {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.price-from {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-muted);
  font-weight: 600;
}

.price-amount {
  font-size: var(--text-lg);
  font-weight: 800;
  color: var(--color-text-primary);
  letter-spacing: -0.02em;
}

.event-card--featured {
  border-color: rgba(78, 203, 160, 0.6);
  box-shadow: 0 0 0 1px rgba(60,175,135,.22), var(--shadow-md);
}

.event-card--featured:hover {
  border-color: rgba(78, 203, 160, 0.6);
  box-shadow: 0 0 0 3px #4ecba0, 0 0 12px 4px rgba(78,203,160,.60), 0 0 30px 10px rgba(60,175,135,.22), 0 0 60px 18px rgba(43,161,124,.07), 0 10px 30px rgba(0,0,0,.45);
}
</style>
