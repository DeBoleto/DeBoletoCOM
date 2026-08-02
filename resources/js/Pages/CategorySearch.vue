<template>
  <Head :title="pageTitle">
    <meta name="description" :content="pageDescription" />
    <meta property="og:title" :content="pageTitle" />
    <meta property="og:description" :content="pageDescription" />
    <meta property="og:image" :content="heroImage" />
  </Head>

  <div class="page-layout">
    <SiteHeader @open-login="openLogin" @open-register="openRegister" />

    <main id="main-content">
      <section class="hero">
        <div class="container">
          <div class="hero-frame">
            <picture class="hero-picture">
              <img :src="heroImage" :alt="heroTitle" class="hero-image" />
            </picture>
            <div class="hero-overlay"></div>
            <div class="hero-content">
              <h1 class="hero-title">{{ heroTitle }}</h1>
              <p class="hero-count">{{ events.length }} {{ events.length === 1 ? 'evento' : 'eventos' }} próximos</p>
            </div>
          </div>
        </div>
      </section>

      <section class="body-section">
        <div class="container">
          <div class="body-grid">
            <button
              type="button"
              class="btn-filter-toggle"
              :class="{ 'btn-filter-toggle--active': showFilters }"
              @click="showFilters = !showFilters"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" />
              </svg>
              Filtrar
              <span v-if="activeFilterCount" class="filter-count">{{ activeFilterCount }}</span>
            </button>

            <aside class="sidebar" :class="{ 'sidebar--open': showFilters }">
              <div v-if="showFilters" class="sidebar-scrim" @click="showFilters = false"></div>
              <div class="sidebar-panel">
                <div class="sidebar-head">
                  <h2 class="sidebar-title">Filtrar</h2>
                  <button
                    v-if="activeFilterCount"
                    type="button"
                    class="btn-clear"
                    @click="clearFilters"
                  >Limpiar filtros</button>
                </div>

                <div v-if="facets.cities.length" class="filter-block">
                  <h3 class="filter-title">Ciudad</h3>
                  <ul class="filter-list">
                    <li v-for="city in sortedCities" :key="city">
                      <label class="filter-option">
                        <input v-model="filters.cities" type="checkbox" :value="city" class="filter-check" />
                        <span>{{ city }}</span>
                      </label>
                    </li>
                  </ul>
                </div>

                <div v-if="facets.states.length" class="filter-block">
                  <h3 class="filter-title">Estado</h3>
                  <ul class="filter-list">
                    <li v-for="state in sortedStates" :key="state">
                      <label class="filter-option">
                        <input v-model="filters.states" type="checkbox" :value="state" class="filter-check" />
                        <span>{{ state }}</span>
                      </label>
                    </li>
                  </ul>
                </div>

                <div v-if="facets.months.length" class="filter-block">
                  <h3 class="filter-title">Mes</h3>
                  <ul class="filter-list">
                    <li v-for="month in sortedMonths" :key="month">
                      <label class="filter-option">
                        <input v-model="filters.months" type="checkbox" :value="month" class="filter-check" />
                        <span>{{ monthLabel(month) }}</span>
                      </label>
                    </li>
                  </ul>
                </div>

                <div v-if="priceMax > priceMin" class="filter-block">
                  <h3 class="filter-title">Precio</h3>
                  <div class="price-fields">
                    <label class="price-field">
                      <span class="price-field-label">Mín</span>
                      <input v-model.number="filters.priceMin" type="number" min="0" class="price-input" />
                    </label>
                    <span class="price-sep">—</span>
                    <label class="price-field">
                      <span class="price-field-label">Máx</span>
                      <input v-model.number="filters.priceMax" type="number" min="0" class="price-input" />
                    </label>
                  </div>
                </div>

                <button
                  type="button"
                  class="btn-apply"
                  @click="showFilters = false"
                >Ver {{ filteredEvents.length }} {{ filteredEvents.length === 1 ? 'evento' : 'eventos' }}</button>
              </div>
            </aside>

            <div class="main-column">
              <div class="results-head">
                <p class="results-count" aria-live="polite">
                  {{ filteredEvents.length }} {{ filteredEvents.length === 1 ? 'evento' : 'eventos' }}
                  <span v-if="activeFilterCount" class="results-sub">(filtrados)</span>
                </p>
                <label class="sort-wrap">
                  <span class="sort-label">Ordenar por</span>
                  <select v-model="sortBy" class="sort-select">
                    <option value="date">Fecha (próximos)</option>
                    <option value="price">Menor precio</option>
                    <option value="price-desc">Mayor precio</option>
                    <option value="name">Nombre</option>
                  </select>
                </label>
              </div>

              <p v-if="filteredEvents.length === 0" class="results-empty">
                No hay eventos que coincidan con los filtros seleccionados.
              </p>

              <div v-else class="results-grid">
                <EventCard
                  v-for="event in filteredEvents"
                  :key="event.id"
                  :event="event"
                />
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <SiteFooter />
    <MobileBottomNav @open-login="openLogin" />

    <PhoneLoginModal :show="showPhoneLoginModal" @close="closePhoneLogin" @switch-to-login="switchPhoneToLogin" @switch-to-register="switchPhoneToRegister" @code-sent="handleCodeSent" />
    <VerifyCodeModal :show="showVerifyCodeModal" :lada="verifyLada" :telefono="verifyTelefono" :canal="verifyCanal" @close="closeVerifyCode" @verified="handleVerified" @resend="handleResend" @switch-to-login="switchVerifyToLogin" @switch-to-register="switchVerifyToRegister" />
    <LoginModal :show="showLoginModal" @close="closeLogin" @switch-to-register="switchLoginToRegister" />
    <RegisterModal :show="showRegisterModal" @close="closeRegister" @switch-to-login="switchRegisterToLogin" />
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import SiteHeader from '@/components/SiteHeader.vue'
import SiteFooter from '@/components/SiteFooter.vue'
import MobileBottomNav from '@/components/MobileBottomNav.vue'
import PhoneLoginModal from '@/components/PhoneLoginModal.vue'
import VerifyCodeModal from '@/components/VerifyCodeModal.vue'
import LoginModal from '@/components/LoginModal.vue'
import RegisterModal from '@/components/RegisterModal.vue'
import EventCard from '@/components/EventCard.vue'

const props = defineProps({
  category: { type: Object, default: null },
  events: { type: Array, default: () => [] },
  facets: { type: Object, default: () => ({ cities: [], states: [], months: [], priceMin: 0, priceMax: 0 }) },
})

const heroTitle = computed(() => props.category ? props.category.name : 'Todos los eventos')
const heroImage = computed(() => props.category?.image ?? '/events/concert-01.png')
const pageTitle = computed(() => props.category ? `${props.category.name} — Eventos` : 'Todos los eventos')
const pageDescription = computed(() =>
  props.category
    ? `Eventos de ${props.category.name}. ${props.events.length} ${props.events.length === 1 ? 'evento' : 'eventos'} próximos.`
    : 'Explora todos los eventos próximos y filtra por ciudad, estado, mes y precio.'
)

const showFilters = ref(false)

const priceMin = props.facets.priceMin ?? 0
const priceMax = props.facets.priceMax ?? 0

const filters = reactive({
  cities: [],
  states: [],
  months: [],
  priceMin,
  priceMax,
})

const sortBy = ref('date')

const MONTHS = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC']
const MONTH_LABELS = {
  ENE: 'Enero', FEB: 'Febrero', MAR: 'Marzo', ABR: 'Abril',
  MAY: 'Mayo', JUN: 'Junio', JUL: 'Julio', AGO: 'Agosto',
  SEP: 'Septiembre', OCT: 'Octubre', NOV: 'Noviembre', DIC: 'Diciembre',
}

function monthLabel(month) {
  return MONTH_LABELS[String(month).toUpperCase()] || month
}

const sortedCities = computed(() => [...props.facets.cities].sort((a, b) => a.localeCompare(b)))
const sortedStates = computed(() => [...props.facets.states].sort((a, b) => a.localeCompare(b)))
const sortedMonths = computed(() =>
  [...props.facets.months]
    .map(m => String(m).toUpperCase())
    .sort((a, b) => {
      const ia = MONTHS.indexOf(a)
      const ib = MONTHS.indexOf(b)
      return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib)
    })
)

const activeFilterCount = computed(() =>
  filters.cities.length + filters.states.length + filters.months.length +
  (filters.priceMin > priceMin || filters.priceMax < priceMax ? 1 : 0)
)

function clearFilters() {
  filters.cities = []
  filters.states = []
  filters.months = []
  filters.priceMin = priceMin
  filters.priceMax = priceMax
}

const filteredEvents = computed(() => {
  const list = props.events.filter(event => {
    if (filters.cities.length && !filters.cities.includes(event.city)) return false
    if (filters.states.length && !filters.states.includes(event.state)) return false
    if (filters.months.length && !filters.months.includes(String(event.month).toUpperCase())) return false
    if (event.price < filters.priceMin || event.price > filters.priceMax) return false
    return true
  })

  return list.sort((a, b) => {
    switch (sortBy.value) {
      case 'price':
        return (a.price || 0) - (b.price || 0)
      case 'price-desc':
        return (b.price || 0) - (a.price || 0)
      case 'name':
        return (a.title || '').localeCompare(b.title || '')
      case 'date':
      default:
        return (a.dateISO || '').localeCompare(b.dateISO || '')
    }
  })
})

const showPhoneLoginModal = ref(false)
const showVerifyCodeModal = ref(false)
const showLoginModal = ref(false)
const showRegisterModal = ref(false)

const verifyLada = ref('52')
const verifyTelefono = ref('')
const verifyCanal = ref('whatsapp')

function openLogin() { showRegisterModal.value = false; showLoginModal.value = false; showVerifyCodeModal.value = false; showPhoneLoginModal.value = true }
function openRegister() { showPhoneLoginModal.value = false; showLoginModal.value = false; showVerifyCodeModal.value = false; showRegisterModal.value = true }
function closePhoneLogin() { showPhoneLoginModal.value = false }
function closeVerifyCode() { showVerifyCodeModal.value = false }
function closeLogin() { showLoginModal.value = false }
function closeRegister() { showRegisterModal.value = false }
function switchPhoneToLogin() { showPhoneLoginModal.value = false; showLoginModal.value = true }
function switchPhoneToRegister() { showPhoneLoginModal.value = false; showRegisterModal.value = true }
function handleCodeSent(data) {
  verifyLada.value = data.lada
  verifyTelefono.value = data.telefono
  verifyCanal.value = data.canal
  showPhoneLoginModal.value = false
  showVerifyCodeModal.value = true
}
function handleVerified() { showVerifyCodeModal.value = false }
function handleResend() { showVerifyCodeModal.value = false; showPhoneLoginModal.value = true }
function switchVerifyToLogin() { showVerifyCodeModal.value = false; showLoginModal.value = true }
function switchVerifyToRegister() { showVerifyCodeModal.value = false; showRegisterModal.value = true }
function switchLoginToRegister() { showLoginModal.value = false; showRegisterModal.value = true }
function switchRegisterToLogin() { showRegisterModal.value = false; showLoginModal.value = true }
</script>

<style scoped>
.page-layout {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

main { flex: 1; }

.hero {
  padding-block: var(--space-8) var(--space-4);
}

.hero-frame {
  position: relative;
  border-radius: var(--radius-sm);
  overflow: hidden;
  aspect-ratio: 5 / 2;
  display: flex;
  align-items: flex-end;
}

.hero-picture {
  position: absolute;
  inset: 0;
}

.hero-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(10, 10, 15, 0.92) 0%, rgba(10, 10, 15, 0.35) 55%, rgba(10, 10, 15, 0.15) 100%);
}

.hero-content {
  position: relative;
  z-index: 1;
  padding: var(--space-8) var(--space-8) var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.hero-title {
  font-size: var(--text-4xl);
  font-weight: 900;
  letter-spacing: -0.02em;
  color: #fff;
  margin: 0;
}

.hero-count {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-brand);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.body-section {
  padding-block: var(--space-8) var(--space-16);
}

.body-grid {
  display: grid;
  grid-template-columns: minmax(240px, 280px) 1fr;
  gap: var(--space-10);
  align-items: start;
}

.btn-filter-toggle {
  display: none;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-5);
  font-size: var(--text-sm);
  font-weight: 700;
  color: var(--color-text-primary);
  background: var(--color-surface-1);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-xs);
  cursor: pointer;
  transition: border-color var(--transition-fast), background var(--transition-fast);
}

.btn-filter-toggle--active {
  border-color: var(--color-brand);
}

.filter-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding-inline: 6px;
  font-size: 11px;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
  border-radius: var(--radius-full);
}

.sidebar-panel {
  position: sticky;
  top: calc(var(--header-height) + var(--space-4));
  display: flex;
  flex-direction: column;
  gap: var(--space-6);
  padding: var(--space-5);
  background: var(--color-surface-1);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.sidebar-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.sidebar-title {
  font-size: var(--text-lg);
  font-weight: 800;
  color: var(--color-text-primary);
  margin: 0;
}

.btn-clear {
  padding: 0;
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--color-brand);
  background: none;
  border: none;
  cursor: pointer;
}

.btn-clear:hover {
  text-decoration: underline;
}

.filter-block {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.filter-title {
  font-size: var(--text-sm);
  font-weight: 700;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin: 0;
}

.filter-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.filter-option {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  font-size: var(--text-sm);
  color: var(--color-text-primary);
  cursor: pointer;
}

.filter-check {
  width: 16px;
  height: 16px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border-strong);
  background: var(--color-surface-2);
  accent-color: var(--color-brand);
}

.price-fields {
  display: flex;
  align-items: flex-end;
  gap: var(--space-2);
}

.price-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 0;
}

.price-field-label {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.price-input {
  width: 100%;
  height: 36px;
  padding-inline: var(--space-2);
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xs);
  font-size: var(--text-sm);
  color: var(--color-text-primary);
  outline: none;
}

.price-input:focus {
  border-color: var(--color-brand-dim);
}

.price-sep {
  color: var(--color-text-muted);
  padding-bottom: 8px;
}

.btn-apply {
  display: none;
  width: 100%;
  padding: var(--space-3);
  font-size: var(--text-sm);
  font-weight: 700;
  color: #fff;
  background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
  border: none;
  border-radius: var(--radius-xs);
  cursor: pointer;
}

.main-column {
  display: flex;
  flex-direction: column;
  gap: var(--space-6);
  min-width: 0;
}

.results-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
  flex-wrap: wrap;
}

.results-count {
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--color-text-primary);
}

.results-sub {
  font-weight: 500;
  color: var(--color-text-muted);
}

.sort-wrap {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.sort-label {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.sort-select {
  height: 36px;
  padding-inline: var(--space-3);
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xs);
  font-size: var(--text-sm);
  color: var(--color-text-primary);
  outline: none;
  cursor: pointer;
}

.results-empty {
  padding: var(--space-16) var(--space-4);
  text-align: center;
  font-size: var(--text-base);
  color: var(--color-text-muted);
}

.results-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: var(--space-5);
}

@media (max-width: 932px) {
  .btn-filter-toggle {
    display: inline-flex;
    justify-self: start;
  }

  .body-grid {
    grid-template-columns: 1fr;
  }

  .sidebar {
    display: none;
  }

  .sidebar--open {
    display: block;
    position: fixed;
    inset: 0;
    z-index: 150;
  }

  .sidebar-scrim {
    position: absolute;
    inset: 0;
    background: rgba(10, 10, 15, 0.7);
  }

  .sidebar-panel {
    position: relative;
    top: auto;
    left: 0;
    width: min(320px, 88vw);
    height: 100dvh;
    overflow-y: auto;
    border-radius: 0 var(--radius-lg) var(--radius-lg) 0;
    border: none;
  }

  .btn-apply {
    display: block;
  }
}

@media (max-width: 640px) {
  .hero {
    padding-top: var(--space-4);
  }

  .hero-content {
    padding: var(--space-6) var(--space-4) var(--space-4);
  }

  .hero-title {
    font-size: var(--text-2xl);
  }

  .results-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: var(--space-4);
  }
}
</style>
