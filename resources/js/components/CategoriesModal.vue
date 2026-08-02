<script setup>
import { onMounted, ref } from 'vue'
import DialogModal from '@/components/DialogModal.vue'

defineProps({
    show: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const categories = ref([])
const loading = ref(true)
const error = ref(false)

async function loadCategories() {
    loading.value = true
    error.value = false
    try {
        const response = await fetch('/api/categories')
        if (!response.ok) throw new Error('bad status')
        categories.value = await response.json()
    } catch {
        error.value = true
        categories.value = []
    } finally {
        loading.value = false
    }
}

onMounted(loadCategories)
</script>

<template>
    <DialogModal :show="show" max-width="5xl" @close="emit('close')">
        <template #title>
            <div class="categories-header">
                <h2 class="categories-title">Categorías</h2>
                <button class="close-btn" @click="emit('close')" aria-label="Cerrar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
        </template>

        <template #content>
            <div class="categories-body">
                <p v-if="loading" class="categories-state">Cargando categorías…</p>
                <div v-else-if="error" class="categories-state">
                    <p>No se pudieron cargar las categorías.</p>
                    <button type="button" class="categories-retry" @click="loadCategories">Reintentar</button>
                </div>
                <p v-else-if="categories.length === 0" class="categories-state">Sin resultados</p>

                <ul v-else class="categories-grid">
                    <li v-for="cat in categories" :key="cat.slug" class="categories-grid-item">
                        <a
                            :href="`/buscar?categoria=${cat.slug}`"
                            class="category-card"
                            :aria-label="`Ver eventos de ${cat.name}`"
                            @click="emit('close')"
                        >
                            <div class="category-card-image-wrap">
                                <img
                                    :src="cat.image"
                                    :alt="cat.name"
                                    class="category-card-image"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <div class="category-card-overlay"></div>
                                <span class="category-card-name">{{ cat.name }}</span>
                            </div>
                            <div class="category-card-info">
                                <span class="category-card-count">
                                    {{ cat.count }} {{ cat.count === 1 ? 'evento' : 'eventos' }}
                                </span>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
        </template>
    </DialogModal>
</template>

<style scoped>
:deep(.dialog-content) {
    position: relative;
    padding: 20px 24px 28px;
}

.categories-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
}

.categories-title {
    font-size: var(--text-xl);
    font-weight: 800;
    color: var(--color-text-primary);
    margin: 0;
}

.close-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    color: var(--color-text-muted);
    background: none;
    border: none;
    cursor: pointer;
    transition: color var(--transition-fast), background var(--transition-fast);
}

.close-btn:hover {
    color: var(--color-text-primary);
    background: var(--color-surface-2);
}

.categories-body {
    margin-top: var(--space-4);
}

.categories-state {
    text-align: center;
    color: var(--color-text-muted);
    font-size: var(--text-sm);
    padding: var(--space-8) 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-3);
}

.categories-retry {
    padding: var(--space-2) var(--space-5);
    font-size: var(--text-sm);
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
    border: none;
    border-radius: var(--radius-xs);
    cursor: pointer;
}

.categories-grid {
    list-style: none;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: var(--space-4);
    max-height: 60dvh;
    overflow-y: auto;
    padding: 2px;
    margin: 0;
}

.categories-grid-item {
    min-width: 0;
}

.category-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--color-surface-1);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    text-decoration: none;
    transition: transform var(--transition-base), border-color var(--transition-base), box-shadow var(--transition-base);
}

.category-card:hover {
    transform: scale(1.03) translateY(-4px);
    border-color: var(--color-border-strong);
    box-shadow: 0 0 0 3px #4ecba0, 0 0 12px 4px rgba(78, 203, 160, 0.45);
}

.category-card-image-wrap {
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
}

.category-card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.category-card:hover .category-card-image {
    transform: scale(1.05);
}

.category-card-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.65), transparent 60%);
    pointer-events: none;
}

.category-card-name {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 8px;
    z-index: 1;
    padding: 0 var(--space-3);
    font-size: var(--text-sm);
    font-weight: 800;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.category-card-info {
    display: flex;
    align-items: center;
    padding: var(--space-3) var(--space-4);
}

.category-card-count {
    font-size: var(--text-xs);
    color: var(--color-text-muted);
}

@media (max-width: 540px) {
    .categories-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        max-height: 55dvh;
    }
}
</style>
