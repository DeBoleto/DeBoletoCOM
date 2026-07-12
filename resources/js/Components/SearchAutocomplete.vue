<template>
  <div
    ref="containerRef"
    class="search-autocomplete"
    role="combobox"
    aria-haspopup="listbox"
    aria-expanded="open"
    :aria-owns="results.length ? 'search-results-listbox' : undefined"
  >
    <div class="search-input-wrap">
      <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
      </svg>
      <input
        ref="inputRef"
        v-model="query"
        type="search"
        class="search-input"
        placeholder="Buscar eventos…"
        autocomplete="off"
        spellcheck="false"
        aria-label="Buscar eventos"
        aria-autocomplete="list"
        aria-controls="search-results-listbox"
        aria-activedescendant=""
        @input="onInput"
        @keydown.down.prevent="onArrowDown"
        @keydown.up.prevent="onArrowUp"
        @keydown.enter.prevent="onEnter"
        @keydown.escape.prevent="close"
        @focus="onFocus"
      />
    </div>

    <ul
      v-if="open && results.length"
      id="search-results-listbox"
      ref="listboxRef"
      class="search-dropdown"
      role="listbox"
    >
      <li
        v-for="(item, index) in results"
        :key="item.id"
        :id="`result-${index}`"
        class="search-result"
        :class="{ 'search-result--active': index === activeIndex }"
        role="option"
        :aria-selected="index === activeIndex"
        @mouseenter="activeIndex = index"
        @mousedown.prevent="goToEvent(item)"
      >
        <a :href="`/evento/${item.slug}`" class="result-link" tabindex="-1" aria-hidden="true"></a>
        <picture class="result-thumb-wrap">
          <source :srcset="item.image.replace('.png', '.webp')" type="image/webp" />
          <source :srcset="item.image.replace('.png', '.avif')" type="image/avif" />
          <img
            :src="item.image"
            :alt="item.title"
            class="result-thumb"
            loading="lazy"
            decoding="async"
            width="48"
            height="48"
          />
        </picture>
        <div class="result-info">
          <span class="result-title">{{ item.title }}</span>
          <span class="result-meta">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
            </svg>
            {{ item.venue }}<span v-if="item.city">, {{ item.city }}</span>
          </span>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useSearch } from '@/composables/useSearch.js'

const { search } = useSearch()

const containerRef = ref(null)
const inputRef = ref(null)
const listboxRef = ref(null)
const query = ref('')
const results = ref([])
const open = ref(false)
const activeIndex = ref(-1)

let debounceTimer = null

function onInput() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    performSearch()
  }, 300)
}

function performSearch() {
  if (query.value.trim().length < 2) {
    results.value = []
    open.value = false
    activeIndex.value = -1
    return
  }
  results.value = search(query.value)
  open.value = true
  activeIndex.value = -1
}

function onArrowDown() {
  if (!open.value || !results.value.length) return
  activeIndex.value = (activeIndex.value + 1) % results.value.length
  scrollIntoView()
}

function onArrowUp() {
  if (!open.value || !results.value.length) return
  activeIndex.value = activeIndex.value <= 0 ? results.value.length - 1 : activeIndex.value - 1
  scrollIntoView()
}

function scrollIntoView() {
  const el = listboxRef.value?.children[activeIndex.value]
  el?.scrollIntoView({ block: 'nearest' })
}

function onEnter() {
  if (activeIndex.value >= 0 && results.value[activeIndex.value]) {
    goToEvent(results.value[activeIndex.value])
  } else if (query.value.trim().length >= 2) {
    close()
  }
}

function goToEvent(event) {
  close()
  window.location.href = `/evento/${event.slug}`
}

function close() {
  open.value = false
  activeIndex.value = -1
}

function onFocus() {
  if (results.value.length) {
    open.value = true
  }
}

function onClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('click', onClickOutside, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside, true)
  clearTimeout(debounceTimer)
})
</script>

<style scoped>
.search-autocomplete {
  position: relative;
  width: 100%;
  max-width: 400px;
}

.search-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-text-muted);
  pointer-events: none;
  flex-shrink: 0;
}

.search-input {
  width: 100%;
  height: 40px;
  padding: 0 14px 0 40px;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  font-size: var(--text-sm);
  color: var(--color-text-primary);
  caret-color: var(--color-brand);
  outline: none;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}

.search-input::placeholder {
  color: var(--color-text-muted);
}

.search-input:focus {
  border-color: var(--color-brand-dim);
  box-shadow: 0 0 0 3px rgba(78, 203, 160, 0.15);
}

.search-input::-webkit-search-decoration,
.search-input::-webkit-search-cancel-button,
.search-input::-webkit-search-results-button,
.search-input::-webkit-search-results-decoration {
  -webkit-appearance: none;
}

.search-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  min-width: 380px;
  max-width: 480px;
  max-height: 420px;
  overflow-y: auto;
  background: var(--color-surface-1);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  z-index: 200;
  list-style: none;
  padding: var(--space-2);
}

.search-result {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: background var(--transition-fast);
}

.search-result:hover,
.search-result--active {
  background: var(--color-surface-2);
}

.result-link {
  position: absolute;
  inset: 0;
  z-index: 0;
}

.result-thumb-wrap {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-md);
  overflow: hidden;
  flex-shrink: 0;
}

.result-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.result-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  position: relative;
  z-index: 1;
}

.result-title {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.result-meta {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.result-meta svg {
  flex-shrink: 0;
}

@media (max-width: 768px) {
  .search-autocomplete {
    display: none;
  }
}
</style>
