<template>
  <Head :title="event.name">
    <meta name="description" :content="`Compra boletos para ${event.name}. ${formattedMainDate}`" />
    <meta property="og:title" :content="event.name" />
    <meta property="og:description" :content="`Compra boletos para ${event.name}. ${formattedMainDate}`" />
    <meta property="og:image" :content="event.image" />
    <link rel="canonical" :href="`/evento/${$page.props.slug}`" />
  </Head>

  <div class="page-layout">
    <SiteHeader @open-login="openLogin" @open-register="openRegister" />

    <main id="main-content">
      <section class="hero-section">
        <div class="container">
          <div class="hero-frame">
            <img :src="event.image" :alt="event.name" class="hero-image" />
          </div>
        </div>
      </section>

      <section class="info-bar">
        <div class="container">
          <div class="info-bar-inner">
            <div class="info-text">
              <h1 class="event-title">{{ event.name }}</h1>
              <p v-if="formattedMainDate || event.venue" class="event-meta">
                <template v-if="formattedMainDate">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                  {{ formattedMainDate }}
                </template>
              </p>
            </div>
            <button type="button" class="btn-buy">Comprar boleto</button>
          </div>
        </div>
      </section>

      <section class="body-section">
        <div class="container">
          <div class="body-grid">
            <div class="main-column">
              <div v-if="event.hasPromotion" class="detail-block">
                <h2 class="block-title">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                  </svg>
                  Promociones
                </h2>
                <div v-if="event.promotions.length" class="promo-main-list">
                  <div v-for="(promo, i) in event.promotions" :key="i" class="promo-main-card">{{ promo }}</div>
                </div>
                <p v-else class="promo-empty">Aplican promociones</p>
              </div>
              <div v-if="event.functions.length" class="detail-block">
                <h2 class="block-title">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                  Fechas del evento
                </h2>
                <ul class="function-list">
                  <li v-for="fn in event.functions" :key="fn.id" class="function-item">
                    <span class="fn-date">{{ formatDate(fn.date) }}</span>
                    <span class="fn-time">{{ formatTime(fn.date) }}</span>
                  </li>
                </ul>
              </div>

              <div class="detail-block">
                <h2 class="block-title">Detalles del evento</h2>
                <div class="detail-grid">
                  <div v-if="formattedMainDate" class="detail-item">
                    <span class="detail-label">Fecha</span>
                    <span class="detail-value">{{ formattedMainDate }}</span>
                  </div>
                  <div v-if="event.venueName" class="detail-item">
                    <span class="detail-label">Lugar</span>
                    <span class="detail-value">{{ event.venueName }}</span>
                  </div>
                </div>
              </div>

              <section class="sponsors-section">
                <h2 class="sponsors-title block-title">Sponsors</h2>
                <div class="sponsor-wrapper">
                  <a
                    href="javascript:void(0)"
                    class="sponsor-arrow sponsor-arrow-prev"
                    aria-label="Anterior"
                    @click.prevent="scrollSponsorsPrev"
                  >
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                      <path d="M10 12L6 8L10 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </a>
                  <div
                    ref="sponsorScrollRef"
                    class="sponsor-scroll"
                    @mousedown="onSponsorMouseDown"
                    @mousemove="onSponsorMouseMove"
                    @mouseup="onSponsorMouseUp"
                    @mouseleave="onSponsorMouseUp"
                    @click.prevent="onSponsorClick"
                  >
                    <ul class="sponsor-track">
                      <li v-for="s in sponsors" :key="s.name">
                        <a :href="s.url" :title="s.name" draggable="false">
                          <img :src="s.image" :alt="s.name" draggable="false">
                          <h3>{{ s.name }}</h3>
                        </a>
                      </li>
                    </ul>
                  </div>
                  <a
                    href="javascript:void(0)"
                    class="sponsor-arrow sponsor-arrow-next"
                    aria-label="Siguiente"
                    @click.prevent="scrollSponsorsNext"
                  >
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                      <path d="M6 4L10 8L6 12" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </a>
                </div>
              </section>
            </div>

            <aside class="sidebar">
              <div v-if="event.hasPromotion || event.promotions.length" class="sidebar-block promotions-block">
                <h2 class="block-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                  </svg>
                  Promociones disponibles
                </h2>
                <div v-if="event.promotions.length" class="promo-list">
                  <div v-for="(promo, i) in event.promotions" :key="i" class="promo-badge">{{ promo }}</div>
                </div>
                <p v-else class="promo-empty">No hay promociones disponibles</p>
              </div>

              <div class="sidebar-block zones-block">
                <h2 class="block-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  </svg>
                  Precios de zonas
                </h2>
                <div class="zone-list">
                  <div v-for="zone in sortedZones" :key="zone.id" class="zone-row">
                    <span class="zone-name">{{ zone.name }}</span>
                    <span class="zone-price">
                      <span v-if="hasDiscount(zone)" class="old-price">${{ formatPrice(zone.originalPrice) }}</span>
                      <span class="current-price">${{ formatPrice(zone.discountPrice) }}</span>
                    </span>
                  </div>
                </div>
              </div>
            </aside>
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
import { computed, ref } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import SiteHeader from '@/components/SiteHeader.vue'
import SiteFooter from '@/components/SiteFooter.vue'
import MobileBottomNav from '@/components/MobileBottomNav.vue'
import PhoneLoginModal from '@/components/PhoneLoginModal.vue'
import VerifyCodeModal from '@/components/VerifyCodeModal.vue'
import LoginModal from '@/components/LoginModal.vue'
import RegisterModal from '@/components/RegisterModal.vue'

const props = defineProps({
  event: { type: Object, required: true },
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
  verifyLada.value = data.lada; verifyTelefono.value = data.telefono; verifyCanal.value = data.canal
  showPhoneLoginModal.value = false; showVerifyCodeModal.value = true
}
function handleVerified() { showVerifyCodeModal.value = false }
function handleResend() { showVerifyCodeModal.value = false; showPhoneLoginModal.value = true }
function switchVerifyToLogin() { showVerifyCodeModal.value = false; showLoginModal.value = true }
function switchVerifyToRegister() { showVerifyCodeModal.value = false; showRegisterModal.value = true }
function switchLoginToRegister() { showLoginModal.value = false; showRegisterModal.value = true }
function switchRegisterToLogin() { showRegisterModal.value = false; showLoginModal.value = true }

const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']

function formatDate(iso) {
  const d = new Date(iso)
  if (isNaN(d.getTime())) return iso
  return `${d.getDate()} ${monthNames[d.getMonth()]} ${d.getFullYear()}`
}

function formatTime(iso) {
  const d = new Date(iso)
  if (isNaN(d.getTime())) return ''
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')} hrs`
}

function formatPrice(val) {
  return Number(val).toLocaleString('es-MX')
}

function hasDiscount(zone) {
  return zone.originalPrice !== zone.discountPrice
}

const sortedZones = computed(() => {
  return [...props.event.zones].sort((a, b) => b.originalPrice - a.originalPrice)
})

const formattedMainDate = computed(() => {
  if (!props.event.functions.length) return ''
  return formatDate(props.event.functions[0].date)
})

const sponsors = [
  { name: 'Coca Cola',   image: 'https://picsum.photos/seed/cocacola/440/280',   url: '#' },
  { name: 'Canon',       image: 'https://picsum.photos/seed/canon/440/280',       url: '#' },
  { name: 'Spotify',     image: 'https://picsum.photos/seed/spotify/440/280',     url: '#' },
  { name: 'FedEx',       image: 'https://picsum.photos/seed/fedex/440/280',       url: '#' },
  { name: 'UPS',         image: 'https://picsum.photos/seed/ups/440/280',         url: '#' },
  { name: 'Citi',        image: 'https://picsum.photos/seed/citi/440/280',        url: '#' },
  { name: 'BBVA',        image: 'https://picsum.photos/seed/bbva/440/280',        url: '#' },
  { name: 'Cerveza Sol', image: 'https://picsum.photos/seed/cervezasol/440/280', url: '#' },
]

const sponsorScrollRef = ref(null)

let sponsorIsDown = false
let sponsorStartX = 0
let sponsorStartScroll = 0
let sponsorMoved = false

function onSponsorMouseDown(e) {
  if (!sponsorScrollRef.value) return
  sponsorIsDown = true
  sponsorMoved = false
  sponsorStartX = e.pageX
  sponsorStartScroll = sponsorScrollRef.value.scrollLeft
  sponsorScrollRef.value.classList.add('dragging')
}

function onSponsorMouseMove(e) {
  if (!sponsorIsDown || !sponsorScrollRef.value) return
  const dx = e.pageX - sponsorStartX
  if (Math.abs(dx) > 3) sponsorMoved = true
  sponsorScrollRef.value.scrollLeft = sponsorStartScroll - dx
}

function onSponsorMouseUp() {
  if (!sponsorIsDown || !sponsorScrollRef.value) return
  sponsorIsDown = false
  sponsorScrollRef.value.classList.remove('dragging')
}

function onSponsorClick(e) {
  if (sponsorMoved) {
    e.preventDefault()
    e.stopPropagation()
  }
}

function getSponsorStep() {
  if (!sponsorScrollRef.value) return 440
  const item = sponsorScrollRef.value.querySelector('.sponsor-track li')
  const w = item ? item.getBoundingClientRect().width : 220
  return (w + 6) * 2
}

function scrollSponsorsPrev() {
  if (!sponsorScrollRef.value) return
  sponsorScrollRef.value.scrollBy({ left: -getSponsorStep(), behavior: 'smooth' })
}

function scrollSponsorsNext() {
  if (!sponsorScrollRef.value) return
  sponsorScrollRef.value.scrollBy({ left: getSponsorStep(), behavior: 'smooth' })
}
</script>

<style scoped>
.page-layout {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

main { flex: 1; }

.hero-section {
  padding-block: var(--space-8) var(--space-4);
}

.hero-frame {
  border: 2px solid var(--color-border-strong);
  border-radius: var(--radius-xl);
  overflow: hidden;
  max-height: 480px;
}

.hero-image {
  width: 100%;
  height: 100%;
  max-height: 480px;
  object-fit: cover;
  display: block;
}

.info-bar {
  padding-block: var(--space-4) var(--space-8);
  border-bottom: 1px solid var(--color-border);
}

.info-bar-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-6);
  flex-wrap: wrap;
}

.info-text {
  flex: 1;
  min-width: 280px;
}

.event-title {
  font-size: var(--text-3xl);
  font-weight: 900;
  letter-spacing: -0.02em;
  color: var(--color-text-primary);
  margin-bottom: var(--space-2);
}

.event-meta {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-base);
  color: var(--color-text-secondary);
}

.event-meta svg {
  flex-shrink: 0;
  color: var(--color-brand);
}

.btn-buy {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-8);
  font-size: var(--text-base);
  font-weight: 700;
  color: #fff;
  background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
  border: none;
  border-radius: var(--radius-xs);
  box-shadow: var(--shadow-brand);
  cursor: pointer;
  transition: opacity var(--transition-fast), transform var(--transition-fast);
  white-space: nowrap;
}

.btn-buy:hover {
  opacity: 0.9;
  transform: translateY(-2px);
}

.body-section {
  padding-block: var(--space-10) var(--space-16);
}

.body-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: var(--space-10);
  align-items: start;
}

@media (max-width: 1024px) {
  .body-grid {
    grid-template-columns: 1fr;
  }
}

.detail-block {
  margin-bottom: var(--space-10);
}

.block-title {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  font-size: var(--text-xl);
  font-weight: 800;
  color: var(--color-text-primary);
  margin-bottom: var(--space-5);
}

.block-title svg {
  color: var(--color-brand);
  flex-shrink: 0;
}

.function-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.function-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-4) var(--space-5);
  background: var(--color-surface-1);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.fn-date {
  font-size: var(--text-base);
  font-weight: 600;
  color: var(--color-text-primary);
}

.fn-time {
  font-size: var(--text-sm);
  color: var(--color-text-secondary);
}

.detail-grid {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  padding: var(--space-4) var(--space-5);
  background: var(--color-surface-1);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.detail-label {
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-muted);
}

.detail-value {
  font-size: var(--text-base);
  font-weight: 600;
  color: var(--color-text-primary);
}

.sidebar {
  display: flex;
  flex-direction: column;
  gap: var(--space-8);
}

.sidebar-block {
  background: var(--color-surface-1);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-6);
}

.promo-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.promo-badge {
  display: inline-flex;
  align-items: center;
  padding: var(--space-2) var(--space-4);
  background: var(--color-brand-dim);
  border: 1px solid rgba(78, 203, 160, 0.4);
  border-radius: var(--radius-sm);
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-brand);
}

.promo-empty {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.zone-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.zone-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-sm);
  background: var(--color-surface-2);
  gap: var(--space-3);
}

.zone-name {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-text-primary);
  flex: 1;
}

.zone-price {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-shrink: 0;
}

.old-price {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  text-decoration: line-through;
}

.current-price {
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--color-text-primary);
}

.sponsors-section {
  padding: var(--space-10) 0 0;
}

.sponsors-title {
  margin-bottom: var(--space-5);
}

.sponsor-wrapper {
  position: relative;
  padding: 0;
}

.sponsor-scroll {
  overflow-x: auto;
  overflow-y: hidden;
  scroll-snap-type: x proximity;
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  -ms-overflow-style: none;
  cursor: grab;
}

.sponsor-scroll::-webkit-scrollbar {
  display: none;
}

.sponsor-scroll.dragging {
  cursor: grabbing;
  scroll-behavior: auto;
}

.sponsor-track {
  display: flex;
  gap: 6px;
  margin: 0;
  padding: 4px 0;
  list-style: none;
}

.sponsor-track li {
  flex: 0 0 calc(25% - 4.5px);
  scroll-snap-align: start;
  border-radius: 12px;
  position: relative;
}

.sponsor-track li a {
  position: relative;
  display: block;
  transition: transform .35s ease, box-shadow .35s ease;
  transform-style: preserve-3d;
  outline: none;
  -webkit-tap-highlight-color: transparent;
}

.sponsor-track li a:focus,
.sponsor-track li a:active {
  outline: none;
}

.sponsor-track li a::after {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 12px;
  background: linear-gradient(to top, rgba(0,0,0,.65), transparent 60%);
  pointer-events: none;
}

.sponsor-track li a img {
  border-radius: 12px;
  display: block;
  width: 100%;
  pointer-events: none;
}

.sponsor-track li a:hover {
  transform: perspective(700px) rotateY(7deg) scale(1.06) translateZ(10px);
  box-shadow: 0 14px 30px rgba(0,0,0,.55);
}

.sponsor-track li a h3 {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 10px;
  z-index: 2;
  color: #fff;
  font-weight: 800;
  margin: 0;
  text-align: center;
  font-size: 1rem;
}

.sponsor-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 5;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0,0,0,.45);
  border-radius: 50%;
  transition: background .2s ease;
}

.sponsor-arrow:hover {
  background: rgba(0,0,0,.8);
}

.sponsor-arrow-prev {
  left: 0;
}

.sponsor-arrow-next {
  right: 0;
}

.promo-main-list {
  display: grid;
  gap: var(--space-3);
}

.promo-main-card {
  padding: var(--space-4) var(--space-5);
  background: var(--color-surface-1);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: var(--text-base);
  font-weight: 600;
  color: var(--color-text-primary);
}

@media (max-width: 767px) {
  .sponsor-track li {
    flex: 0 0 calc(50% - 3px);
  }

  .sponsor-arrow {
    display: none;
  }

  .sponsor-wrapper {
    padding: 0;
  }
}
</style>
