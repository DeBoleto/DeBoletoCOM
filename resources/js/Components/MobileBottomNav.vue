<template>
  <nav class="mobile-bottom-nav" aria-label="Navegación móvil">
    <a
      v-for="tab in tabs"
      :key="tab.href"
      :href="tab.href"
      class="bottom-nav-link"
      :class="{ 'bottom-nav-link--active': isActive(tab.href) }"
      :aria-label="tab.label"
    >
      <span class="bottom-nav-icon" v-html="tab.icon" aria-hidden="true"></span>
      <span class="bottom-nav-label">{{ tab.label }}</span>
    </a>
  </nav>
</template>

<script setup>
const tabs = [
  {
    href: '/',
    label: 'Inicio',
    icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 0 0 1 1h3m10-11l2 2m-2-2v10a1 1 0 0 1-1 1h-3m-6 0a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1m-6 0h6"/></svg>',
  },
  {
    href: '/buscar',
    label: 'Buscar',
    icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>',
  },
  {
    href: '/eventos',
    label: 'Eventos',
    icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M9 12h6"/></svg>',
  },
  {
    href: '/login',
    label: 'Perfil',
    icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
  },
]

function isActive(href) {
  if (href === '/') return window.location.pathname === '/'
  return window.location.pathname.startsWith(href)
}
</script>

<style scoped>
.mobile-bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: var(--bottom-nav-height);
  background: rgba(10, 10, 15, 0.92);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-top: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-around;
  z-index: 100;
  padding-bottom: env(safe-area-inset-bottom, 0);
}

@media (min-width: 768px) {
  .mobile-bottom-nav {
    display: none;
  }
}

.bottom-nav-link {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: var(--space-2) var(--space-3);
  color: var(--color-text-muted);
  text-decoration: none;
  transition: color var(--transition-fast);
  min-width: 0;
}

.bottom-nav-link:hover {
  color: var(--color-text-secondary);
}

.bottom-nav-link--active {
  color: var(--color-brand);
}

.bottom-nav-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
}

.bottom-nav-icon :deep(svg) {
  display: block;
}

.bottom-nav-label {
  font-size: 10px;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
}
</style>
