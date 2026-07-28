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
            <picture class="hero-picture">
              <source :srcset="event.image.replace('.png', '.webp')" type="image/webp" />
              <source :srcset="event.image.replace('.png', '.avif')" type="image/avif" />
              <img :src="event.image" :alt="event.name" class="hero-image" />
            </picture>
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
            <button v-if="event.ventaWeb" type="button" class="btn-buy">COMPRAR BOLETO</button>
          </div>
        </div>
      </section>

      <section class="body-section">
        <div class="container">
          <div class="body-grid">
            <div class="main-column">
              <div class="detail-block">
                <h2 class="block-title">Acerca del evento</h2>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
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
                    <div class="function-date-badge">
                      <span class="d">{{ getDay(fn.date) }}</span>
                      <span class="m">{{ getMonth(fn.date) }}</span>
                    </div>
                    <div class="fn-info">
                      <span class="fn-event-name">{{ event.name }}</span>
                      <span class="fn-details">{{ formatTime(fn.date) }}, {{ event.venueName }} - Villahermosa, Tabasco</span>
                    </div>
                  </li>
                </ul>
              </div>

              <div class="detail-block">
                <button v-if="event.ventaWeb" type="button" class="btn-buy">COMPRAR BOLETO</button>
                <div class="event-info-label">
                  <span>Tabasco - {{ event.venueName }}</span>
                  <span>1 evento</span>
                </div>
              </div>

              <section class="sponsors-section">
                <h2 class="sponsors-title block-title">Patrocinadores</h2>
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
                <div class="zone-list" :class="{ 'zone-list--cols-2': sortedZones.length > 10 }">
                  <div v-for="zone in sortedZones" :key="zone.id" class="zone-row">
                    <span class="zone-name">{{ zone.name }}</span>
                    <span class="zone-price">
                      <span :class="{ 'old-price': hasDiscount(zone) }">${{ formatPrice(zone.originalPrice) }}</span>
                      <span v-if="hasDiscount(zone)" class="current-price">${{ formatPrice(zone.discountPrice) }}</span>
                    </span>
                  </div>
                </div>
              </div>

              <div class="sidebar-block social-block">
                <h2 class="block-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                  </svg>
                  Redes sociales
                </h2>
                <div class="sidebar-social-links" aria-label="Redes sociales">
                  <a href="#" aria-label="Facebook" class="sidebar-social-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                  </a>
                  <a href="#" aria-label="Instagram" class="sidebar-social-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                  </a>
                  <a href="#" aria-label="Twitter / X" class="sidebar-social-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.766l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                  </a>
                  <a href="#" aria-label="YouTube" class="sidebar-social-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                  </a>
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

function getDay(iso) {
  const d = new Date(iso)
  return isNaN(d.getTime()) ? '' : d.getDate()
}

function getMonth(iso) {
  const d = new Date(iso)
  return isNaN(d.getTime()) ? '' : monthNames[d.getMonth()].toUpperCase()
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
  border-radius: var(--radius-xl);
  overflow: hidden;
  aspect-ratio: 5 / 2;
}

.hero-picture {
  display: block;
  width: 100%;
  height: 100%;
}

.hero-image {
  width: 100%;
  height: 100%;
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

.event-info-label {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  margin-top: var(--space-3);
  font-size: var(--text-sm);
  color: var(--color-text-secondary);
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
  gap: var(--space-4);
  padding: var(--space-4) var(--space-5);
  background: var(--color-surface-1);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.function-date-badge {
  display: flex;
  flex-direction: column;
  align-items: center;
  line-height: 1;
  padding: var(--space-2) var(--space-3);
  background: rgba(10, 10, 15, 0.85);
  border-radius: var(--radius-lg);
  flex-shrink: 0;
}

.function-date-badge .d {
  font-size: 20px;
  font-weight: 800;
  color: #fff;
}

.function-date-badge .m {
  font-size: 10px;
  font-weight: 700;
  color: var(--color-brand);
  letter-spacing: 0.08em;
  margin-top: 2px;
}

.fn-info {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-1);
}

.fn-event-name {
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--color-text-primary);
}

.fn-details {
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
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-2);
}

.zone-list--cols-2 {
  grid-template-columns: 1fr 1fr;
}

.sidebar-social-links {
  display: flex;
  gap: var(--space-3);
}

.sidebar-social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: var(--radius-sm);
  background: var(--color-surface-2);
  color: var(--color-text-muted);
  transition: background var(--transition-fast), color var(--transition-fast);
}

.sidebar-social-link:hover {
  background: var(--color-brand-dim);
  color: var(--color-brand);
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
  flex: 0 0 150px;
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
  .btn-buy {
    width: 100%;
    justify-content: center;
  }

  .sponsor-track li {
    flex: 0 0 150px;
  }

  .sponsor-arrow {
    display: none;
  }

  .sponsor-wrapper {
    padding: 0;
  }
}
</style>
