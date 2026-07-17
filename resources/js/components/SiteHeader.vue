<template>
  <header class="site-header" role="banner">
    <div class="header-inner container">
      <a href="/" class="brand" aria-label="Boleto — Ir a inicio">
        <picture>
          <source srcset="/deboletoIcono.png" media="(max-width: 767px)">
          <img src="/logo_blanco.png" alt="Boleto" class="brand-logo">
        </picture>
      </a>

      <div class="search-col">
        <SearchAutocomplete />
      </div>

      <div class="header-right">
        <nav class="primary-nav" aria-label="Navegación principal">
          <ul role="list" class="nav-list">
            <li><a href="#events" class="nav-link">Eventos</a></li>
            <li><a href="#categories" class="nav-link">Categorías</a></li>
            <li><a href="#venues" class="nav-link">Recintos</a></li>
            <li><a href="#promoters" class="nav-link">Promotores</a></li>
          </ul>
        </nav>

        <div class="header-actions">
        <template v-if="user">
          <a :href="route('dashboard')" class="btn-ghost">Dashboard</a>
          <span class="user-name">{{ user.name }}</span>
          <a :href="route('logout')" class="btn-ghost" @click.prevent="logout">Salir</a>
        </template>
        <template v-else>
          <a href="#" class="btn-ghost" @click.prevent="emit('open-login')">Iniciar sesión</a>
          <a href="#" class="btn-brand" @click.prevent="emit('open-register')">Crear cuenta</a>
        </template>
        <button
          class="hamburger"
          type="button"
          :aria-expanded="menuOpen"
          aria-controls="mobile-menu"
          aria-label="Abrir menú"
          @click="toggleMenu"
        >
          <span class="bar"></span>
          <span class="bar"></span>
          <span class="bar"></span>
        </button>
      </div>
      </div>
    </div>

    <nav
      id="mobile-menu"
      class="mobile-menu"
      :class="{ 'mobile-menu--open': menuOpen }"
      aria-label="Menú móvil"
    >
      <ul role="list" class="mobile-nav-list">
        <li><a href="#events" class="mobile-nav-link" @click="closeMenu">Eventos</a></li>
        <li><a href="#categories" class="mobile-nav-link" @click="closeMenu">Categorías</a></li>
        <li><a href="#venues" class="mobile-nav-link" @click="closeMenu">Recintos</a></li>
        <li><a href="#promoters" class="mobile-nav-link" @click="closeMenu">Promotores</a></li>
        <li class="mobile-actions">
          <template v-if="user">
            <a :href="route('dashboard')" class="btn-ghost w-full">Dashboard</a>
            <a href="/logout" class="btn-ghost w-full" @click.prevent="logout">Salir</a>
          </template>
          <template v-else>
            <a href="#" class="btn-ghost w-full" @click.prevent="emit('open-login')">Iniciar sesión</a>
            <a href="#" class="btn-brand w-full" @click.prevent="emit('open-register')">Crear cuenta</a>
          </template>
        </li>
      </ul>
    </nav>
  </header>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import SearchAutocomplete from '@/components/SearchAutocomplete.vue'

const emit = defineEmits(['open-login', 'open-register'])

const user = computed(() => usePage().props.auth?.user ?? null)

const menuOpen = ref(false)

function toggleMenu() {
  menuOpen.value = !menuOpen.value
}
function closeMenu() {
  menuOpen.value = false
}

function handleResize() {
  if (window.innerWidth >= 768) closeMenu()
}

function logout() {
  router.post(route('logout'))
}

onMounted(() => window.addEventListener('resize', handleResize))
onBeforeUnmount(() => window.removeEventListener('resize', handleResize))
</script>

<style scoped>
.site-header {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(10, 10, 15, 0.85);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--color-border);
  height: var(--header-height);
}

.header-inner {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  height: 100%;
  gap: var(--space-4);
}

.search-col {
  display: flex;
  justify-content: center;
  min-width: 0;
}

.header-right {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-shrink: 0;
}

.brand {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-shrink: 0;
  text-decoration: none;
}

.brand-logo {
  height: 28px;
  width: auto;
  display: block;
}

.primary-nav { display: none; }

@media (min-width: 768px) {
  .primary-nav { display: flex; }
}




.nav-list {
  display: flex;
  list-style: none;
  gap: var(--space-1);
}

.nav-link {
  display: block;
  padding: var(--space-2) var(--space-4);
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-text-secondary);
  border-radius: var(--radius-sm);
  transition: color var(--transition-fast), background var(--transition-fast);
}

.nav-link:hover {
  color: var(--color-text-primary);
  background: var(--color-surface-2);
}

.header-actions {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-shrink: 0;
}

.user-name {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-text-primary);
  white-space: nowrap;
}

.btn-ghost {
  display: none;
  padding: var(--space-2) var(--space-4);
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-text-secondary);
  border-radius: var(--radius-sm);
  transition: color var(--transition-fast), background var(--transition-fast);
  white-space: nowrap;
}

.btn-ghost:hover {
  color: var(--color-text-primary);
  background: var(--color-surface-2);
}

@media (min-width: 768px) {
  .btn-ghost { display: block; }
}

.btn-brand {
  display: none;
  padding: var(--space-2) var(--space-5);
  font-size: var(--text-sm);
  font-weight: 600;
  color: #fff;
  background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
  border-radius: var(--radius-full);
  transition: opacity var(--transition-fast), transform var(--transition-fast);
  white-space: nowrap;
  box-shadow: var(--shadow-brand);
}

.btn-brand:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

@media (min-width: 768px) {
  .btn-brand { display: block; }
}

.hamburger {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 5px;
  width: 36px;
  height: 36px;
  padding: 6px;
  background: none;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: background var(--transition-fast);
}

.hamburger:hover { background: var(--color-surface-2); }

.bar {
  display: block;
  width: 100%;
  height: 2px;
  background: var(--color-text-secondary);
  border-radius: 2px;
  transition: background var(--transition-fast);
}

@media (min-width: 768px) {
  .hamburger { display: none; }
}

.mobile-menu {
  position: fixed;
  top: var(--header-height);
  left: 0;
  right: 0;
  background: var(--color-surface-1);
  border-bottom: 1px solid var(--color-border);
  transform: translateY(-100%);
  opacity: 0;
  pointer-events: none;
  transition: transform var(--transition-base), opacity var(--transition-base);
  z-index: 99;
}

.mobile-menu--open {
  transform: translateY(0);
  opacity: 1;
  pointer-events: auto;
}

.mobile-nav-list {
  list-style: none;
  padding: var(--space-4) var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.mobile-nav-link {
  display: block;
  padding: var(--space-3) var(--space-4);
  font-size: var(--text-base);
  font-weight: 500;
  color: var(--color-text-secondary);
  border-radius: var(--radius-sm);
  transition: color var(--transition-fast), background var(--transition-fast);
}

.mobile-nav-link:hover {
  color: var(--color-text-primary);
  background: var(--color-surface-2);
}

.mobile-actions {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding-top: var(--space-4);
  border-top: 1px solid var(--color-border);
  margin-top: var(--space-3);
}

.mobile-actions .btn-ghost,
.mobile-actions .btn-brand {
  display: block;
  text-align: center;
}

.w-full { width: 100%; }
</style>
