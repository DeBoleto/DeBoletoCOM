<template>
  <Head title="DeBoleto — Tu plataforma de boletos">
    <meta name="description" content="Compra boletos para conciertos, festivales, teatro, conferencias y más. La plataforma líder de venta de entradas en línea." />
    <meta property="og:title" content="DeBoleto — Tu plataforma de boletos" />
    <meta property="og:description" content="Compra boletos para conciertos, festivales, teatro, conferencias y más eventos." />
    <meta property="og:url" content="{{ route('home') }}" />
    <meta name="twitter:title" content="DeBoleto — Tu plataforma de boletos" />
    <meta name="twitter:description" content="Compra boletos para conciertos, festivales, teatro, conferencias y más." />
    <meta property="og:image" content="/og-image.png" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta name="twitter:image" content="/og-image.png" />
    <link rel="canonical" href="{{ route('home') }}" />
  </Head>

  <div class="page-layout">
    <SiteHeader @open-login="openLogin" @open-register="openRegister" />

    <main id="main-content">

	<HeroSlider :events="featuredEvents" :banners="banners" type="banners" />

	<EventSection
            title="Eventos en tu Zona"
            subtitle="Eventos cerca de ti"
            section-id="featured"
            icon="📍"
            accent-color="conference"
            view-all-link="/eventos?filtro=destacados"
            :events="resolvedZoneEvents"
            layout="featured"
	/>
	<EventSection
            title="Próximos Eventos"
            subtitle="No te pierdas lo que viene esta temporada"
            section-id="events"
            icon="📅"
            accent-color="brand"
            view-all-link="/eventos?filtro=proximos"
            :events="resolvedNextEvents"
            layout="grid"
	/>

      <EventSection
        title="Eventos Populares"
        subtitle="Los más buscados en este momento"
        section-id="popular"
        icon="🔥"
        accent-color="festival"
        view-all-link="/eventos?filtro=populares"
        :events="popularEvents"
        layout="grid"
      />

      <CategoriesStrip />

    </main>

    <SiteFooter />
    <MobileBottomNav />

    <LoginModal :show="showLoginModal" @close="closeLogin" @switch-to-register="switchLoginToRegister" />
    <RegisterModal :show="showRegisterModal" @close="closeRegister" @switch-to-login="switchRegisterToLogin" />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import SiteHeader from '@/components/SiteHeader.vue'
import HeroSlider from '@/components/HeroSlider.vue'
import EventSection from '@/components/EventSection.vue'
import CategoriesStrip from '@/components/CategoriesStrip.vue'
import SiteFooter from '@/components/SiteFooter.vue'
import MobileBottomNav from '@/components/MobileBottomNav.vue'
import LoginModal from '@/components/LoginModal.vue'
import RegisterModal from '@/components/RegisterModal.vue'
import { useEvents } from '@/composables/useEvents.js'
import { useStructuredData } from '@/composables/useStructuredData.js'

const props = defineProps({
  nextEvents: {
    type: Array,
    default: () => [],
  },
  zoneEvents: {
    type: Array,
    default: () => [],
  },
  banners: {
    type: Array,
    default: () => [],
  },
})

const showLoginModal = ref(false)
const showRegisterModal = ref(false)

function openLogin() { showRegisterModal.value = false; showLoginModal.value = true }
function openRegister() { showLoginModal.value = false; showRegisterModal.value = true }
function closeLogin() { showLoginModal.value = false }
function closeRegister() { showRegisterModal.value = false }
function switchLoginToRegister() { showLoginModal.value = false; showRegisterModal.value = true }
function switchRegisterToLogin() { showRegisterModal.value = false; showLoginModal.value = true }

const { organizationSchema, webSiteSchema } = useStructuredData()

const { nextEvents: mockNextEvents, popularEvents, featuredEvents } = useEvents()
const resolvedNextEvents = computed(() =>
  props.nextEvents.length > 0 ? props.nextEvents : mockNextEvents
)
const resolvedZoneEvents = computed(() =>
  props.zoneEvents.length > 0 ? props.zoneEvents : featuredEvents
)
</script>

<style scoped>
.page-layout {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

main { flex: 1; }


.promoter-banner {
  padding-block: var(--space-16);
}

.banner-content {
  background: linear-gradient(135deg, rgba(60,175,135,.22) 0%, rgba(124, 58, 237, 0.12) 100%);
  border: 1px solid rgba(78, 203, 160, 0.6);
  border-radius: var(--radius-xl);
  padding: var(--space-12) var(--space-12);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-10);
  flex-wrap: wrap;
}

.banner-text { flex: 1; min-width: 280px; }

.banner-title {
  font-size: var(--text-3xl);
  font-weight: 900;
  color: var(--color-text-primary);
  letter-spacing: -0.03em;
  margin-bottom: var(--space-4);
  text-wrap: balance;
}

.banner-desc {
  font-size: var(--text-base);
  color: var(--color-text-secondary);
  line-height: 1.7;
  margin-bottom: var(--space-6);
}

.banner-features {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.banner-features li {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-text-secondary);
}

.banner-features svg { color: var(--color-brand); flex-shrink: 0; }

.banner-actions {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  align-items: center;
  flex-shrink: 0;
}

.banner-cta-primary {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-4) var(--space-8);
  background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
  color: #fff;
  font-size: var(--text-base);
  font-weight: 700;
  border-radius: var(--radius-full);
  box-shadow: var(--shadow-brand);
  transition: opacity var(--transition-fast), transform var(--transition-fast);
  white-space: nowrap;
}

.banner-cta-primary:hover { opacity: 0.9; transform: translateY(-2px); }

.banner-cta-secondary {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-text-muted);
  text-decoration: underline;
  text-underline-offset: 3px;
  transition: color var(--transition-fast);
}

.banner-cta-secondary:hover { color: var(--color-text-primary); }

@media (max-width: 768px) {
  .banner-content  { padding: var(--space-8); }
  .banner-title    { font-size: var(--text-2xl); }
}

@media (max-width: 540px) {
  .banner-content  { padding: var(--space-6); }
  .banner-actions  { width: 100%; }
  .banner-cta-primary { justify-content: center; width: 100%; }
}
</style>
