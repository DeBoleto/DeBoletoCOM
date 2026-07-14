<template>
  <nav aria-label="Breadcrumb">
    <ol itemscope itemtype="https://schema.org/BreadcrumbList">
      <li v-for="(crumb, i) in crumbs" :key="i" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a :href="crumb.href" itemprop="item">
          <span itemprop="name">{{ crumb.label }}</span>
        </a>
        <meta itemprop="position" :content="String(i + 1)" />
        <span v-if="i < crumbs.length - 1" class="separator" aria-hidden="true">/</span>
      </li>
    </ol>
  </nav>
</template>

<script setup>
defineProps({
  crumbs: {
    type: Array,
    required: true,
  },
})
</script>

<style scoped>
nav[aria-label="Breadcrumb"] {
  padding-block: var(--space-4) 0;
}

ol {
  list-style: none;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-2);
}

li {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

a {
  color: var(--color-text-secondary);
  transition: color var(--transition-fast);
}

a:hover {
  color: var(--color-brand);
}

.separator {
  color: var(--color-text-muted);
  opacity: 0.4;
}
</style>
