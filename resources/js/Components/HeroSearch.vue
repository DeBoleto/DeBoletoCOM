<template>
  <section
    class="hero"
    aria-labelledby="hero-heading"
    role="search"
  >
    <div class="hero-bg" aria-hidden="true">
      <picture>
        <source srcset="/hero-bg.webp" type="image/webp" />
        <source srcset="/hero-bg.avif" type="image/avif" />
        <img
          src="/hero-bg.png"
          alt=""
          class="hero-bg-img"
          fetchpriority="high"
          loading="eager"
        />
      </picture>
      <div class="hero-overlay"></div>
    </div>

    <div class="hero-content container">
      <div class="hero-headings">
        <p class="hero-eyebrow">Millones de eventos, un solo destino</p>
        <h1 id="hero-heading" class="hero-title">
          Encuentra tu próximo <br />
          <span class="hero-title-accent">evento favorito</span>
        </h1>
        <p class="hero-subtitle">
          Conciertos, festivales, teatro, conferencias y más.<br />
          Compra boletos al instante, sin complicaciones.
        </p>
      </div>

      <form
        class="search-form"
        action="/buscar"
        method="GET"
        role="search"
        aria-label="Buscar eventos"
        @submit.prevent="handleSearch"
      >
        <div class="search-fields">
          <div class="search-field search-field--main">
            <label for="search-q" class="search-label">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
              </svg>
              Buscar
            </label>
            <input
              id="search-q"
              v-model="query"
              type="search"
              name="q"
              class="search-input"
              placeholder="Artista, evento, equipo…"
              autocomplete="off"
              spellcheck="false"
            />
          </div>

          <div class="search-divider" aria-hidden="true"></div>

          <div class="search-field">
            <label for="search-cat" class="search-label">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16"/>
              </svg>
              Categoría
            </label>
            <select
              id="search-cat"
              v-model="category"
              name="categoria"
              class="search-input search-select"
              aria-label="Seleccionar categoría de evento"
            >
              <option value="">Todos los eventos</option>
              <option value="conciertos">Conciertos</option>
              <option value="festivales">Festivales</option>
              <option value="teatro">Teatro</option>
              <option value="conferencias">Conferencias</option>
              <option value="deportes">Deportes</option>
              <option value="electronica">Electrónica / EDM</option>
            </select>
          </div>

          <div class="search-divider" aria-hidden="true"></div>

          <div class="search-field">
            <label for="search-loc" class="search-label">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
              </svg>
              Ciudad
            </label>
            <input
              id="search-loc"
              v-model="location"
              type="text"
              name="ciudad"
              class="search-input"
              placeholder="Ciudad o estado"
              list="city-list"
              autocomplete="off"
            />
            <datalist id="city-list">
              <option value="Ciudad de México" />
              <option value="Guadalajara" />
              <option value="Monterrey" />
              <option value="Puebla" />
              <option value="Tijuana" />
              <option value="Mérida" />
            </datalist>
          </div>
        </div>

        <button type="submit" class="search-btn" aria-label="Buscar eventos">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <span>Buscar eventos</span>
        </button>
      </form>

      <div class="quick-filters" aria-label="Categorías rápidas">
        <span class="quick-filters-label">Explora:</span>
        <ul role="list" class="chips-list">
          <li v-for="chip in quickFilters" :key="chip.value">
            <a
              :href="`/buscar?categoria=${chip.value}`"
              class="chip"
              :class="`chip--${chip.color}`"
            >
              <span class="chip-icon" aria-hidden="true">{{ chip.icon }}</span>
              {{ chip.label }}
            </a>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue'

const query = ref('')
const category = ref('')
const location = ref('')

function handleSearch() {
  const params = new URLSearchParams()
  if (query.value) params.set('q', query.value)
  if (category.value) params.set('categoria', category.value)
  if (location.value) params.set('ciudad', location.value)
  window.location.href = `/buscar?${params.toString()}`
}

const quickFilters = [
  { label: 'Conciertos', value: 'conciertos', icon: '🎵', color: 'concert' },
  { label: 'Festivales', value: 'festivales', icon: '🎪', color: 'festival' },
  { label: 'Teatro', value: 'teatro', icon: '🎭', color: 'theater' },
  { label: 'Conferencias', value: 'conferencias', icon: '🎤', color: 'conference' },
  { label: 'Deportes', value: 'deportes', icon: '⚽', color: 'sports' },
  { label: 'Electrónica', value: 'electronica', icon: '🎧', color: 'edm' },
]
</script>

<style scoped>
.hero {
  position: relative;
  min-height: min(90vh, 780px);
  display: flex;
  align-items: center;
  overflow: hidden;
}

.hero-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
}

.hero-bg-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center top;
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(
      to bottom,
      rgba(10, 10, 15, 0.6) 0%,
      rgba(10, 10, 15, 0.55) 40%,
      rgba(10, 10, 15, 0.88) 80%,
      var(--color-bg) 100%
    );
}

.hero-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-8);
  padding-block: var(--space-24) var(--space-16);
  width: 100%;
}

.hero-headings {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  max-width: 640px;
}

.hero-eyebrow {
  font-size: var(--text-sm);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-brand);
}

.hero-title {
  font-size: clamp(var(--text-3xl), 5vw, var(--text-5xl));
  font-weight: 900;
  line-height: 1.1;
  letter-spacing: -0.03em;
  color: var(--color-text-primary);
  text-wrap: balance;
}

.hero-title-accent {
  background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-subtitle {
  font-size: var(--text-lg);
  color: var(--color-text-secondary);
  line-height: 1.65;
}

.search-form {
  width: 100%;
  max-width: 860px;
  background: rgba(17, 17, 24, 0.92);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-xl);
  padding: var(--space-4);
  display: flex;
  align-items: stretch;
  gap: var(--space-3);
  box-shadow: var(--shadow-lg), 0 0 60px rgba(229, 53, 171, 0.12);
}

.search-fields {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 0;
  min-width: 0;
}

.search-field {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  padding: var(--space-2) var(--space-5);
  min-width: 0;
}

.search-field--main { flex: 1.4; }

.search-label {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-xs);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-muted);
  white-space: nowrap;
}

.search-input {
  background: none;
  border: none;
  outline: none;
  font-size: var(--text-base);
  font-weight: 500;
  color: var(--color-text-primary);
  width: 100%;
  padding: 0;
  caret-color: var(--color-brand);
}

.search-input::placeholder { color: var(--color-text-muted); font-weight: 400; }

.search-select {
  appearance: none;
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235a5a78' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 4px center;
  padding-right: var(--space-6);
}

.search-select option { background: var(--color-surface-2); color: var(--color-text-primary); }

.search-divider {
  width: 1px;
  align-self: stretch;
  background: var(--color-border);
  flex-shrink: 0;
  margin-block: var(--space-1);
}

.search-btn {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-4) var(--space-6);
  background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
  color: #fff;
  font-size: var(--text-sm);
  font-weight: 700;
  border: none;
  border-radius: var(--radius-lg);
  cursor: pointer;
  white-space: nowrap;
  transition: opacity var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
  box-shadow: var(--shadow-brand);
  flex-shrink: 0;
}

.search-btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
  box-shadow: 0 0 32px rgba(229, 53, 171, 0.4);
}

.search-btn:active { transform: translateY(0); }

.quick-filters {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.quick-filters-label {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-text-muted);
  flex-shrink: 0;
}

.chips-list {
  list-style: none;
  display: flex;
  gap: var(--space-2);
  flex-wrap: wrap;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-full);
  font-size: var(--text-sm);
  font-weight: 500;
  border: 1px solid var(--color-border-strong);
  background: rgba(255, 255, 255, 0.04);
  color: var(--color-text-secondary);
  transition: background var(--transition-fast), color var(--transition-fast), border-color var(--transition-fast), transform var(--transition-fast);
}

.chip:hover {
  background: rgba(255, 255, 255, 0.08);
  color: var(--color-text-primary);
  transform: translateY(-1px);
}

.chip--concert:hover   { border-color: var(--color-concert);    color: var(--color-concert); }
.chip--festival:hover  { border-color: var(--color-festival);   color: var(--color-festival); }
.chip--theater:hover   { border-color: var(--color-theater);    color: var(--color-theater); }
.chip--conference:hover { border-color: var(--color-conference); color: var(--color-conference); }
.chip--sports:hover    { border-color: var(--color-sports);     color: var(--color-sports); }
.chip--edm:hover       { border-color: var(--color-edm);        color: var(--color-edm); }

.chip-icon { font-size: 1em; }

@media (min-width: 1400px) {
  .hero-headings { max-width: 720px; }
  .search-form   { max-width: 960px; }
}

@media (min-width: 1600px) {
  .hero-headings { max-width: 800px; }
  .search-form   { max-width: 1040px; }
}

@media (min-width: 1920px) {
  .hero-headings { max-width: 880px; }
  .search-form   { max-width: 1120px; }
}

@media (max-width: 768px) {
  .search-form {
    flex-direction: column;
    border-radius: var(--radius-lg);
    gap: 0;
    padding: 0;
    overflow: hidden;
  }

  .search-fields {
    flex-direction: column;
    align-items: stretch;
  }

  .search-field {
    padding: var(--space-4) var(--space-5);
  }

  .search-divider {
    width: auto;
    height: 1px;
    margin-block: 0;
    margin-inline: var(--space-5);
  }

  .search-btn {
    margin: var(--space-4);
    justify-content: center;
    padding: var(--space-4);
    border-radius: var(--radius-md);
  }
}

@media (max-width: 480px) {
  .chips-list { gap: var(--space-2); }
}
</style>
