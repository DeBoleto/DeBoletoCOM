<script setup>
import { computed, onMounted, ref } from 'vue'
import DialogModal from '@/components/DialogModal.vue'

defineProps({
    show: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const venues = ref([])
const query = ref('')
const loading = ref(true)
const error = ref(false)

async function loadVenues() {
    loading.value = true
    error.value = false
    try {
        const response = await fetch('/api/venues')
        if (!response.ok) throw new Error('bad status')
        venues.value = await response.json()
    } catch {
        error.value = true
        venues.value = []
    } finally {
        loading.value = false
    }
}

const filteredVenues = computed(() => {
    const q = query.value.trim().toLowerCase()
    if (!q) return venues.value
    return venues.value.filter(v =>
        `${v.name} ${v.city} ${v.state}`.toLowerCase().includes(q)
    )
})

onMounted(loadVenues)
</script>

<template>
    <DialogModal :show="show" max-width="4xl" @close="emit('close')">
        <template #title>
            <div class="venues-header">
                <h2 class="venues-title">Recintos</h2>
                <button class="close-btn" @click="emit('close')" aria-label="Cerrar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
        </template>

        <template #content>
            <div class="venues-body">
                <div class="venues-search-wrap">
                    <svg class="venues-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
                    </svg>
                    <input
                        v-model="query"
                        type="search"
                        class="venues-search-input"
                        placeholder="Buscar recinto…"
                        autocomplete="off"
                        spellcheck="false"
                        aria-label="Buscar recinto"
                        autofocus
                    />
                </div>

                <p v-if="loading" class="venues-state">Cargando recintos…</p>
                <div v-else-if="error" class="venues-state">
                    <p>No se pudieron cargar los recintos.</p>
                    <button type="button" class="venues-retry" @click="loadVenues">Reintentar</button>
                </div>
                <p v-else-if="filteredVenues.length === 0" class="venues-state">Sin resultados</p>

                <ul v-else class="venues-grid">
                    <li v-for="venue in filteredVenues" :key="venue.slug" class="venues-grid-item">
                        <a
                            :href="`/recinto/${venue.slug}`"
                            class="venue-card"
                            :aria-label="`Ver recinto ${venue.name}`"
                            @click="emit('close')"
                        >
                            <div class="venue-card-image-wrap">
                                <img
                                    :src="venue.image"
                                    :alt="venue.name"
                                    class="venue-card-image"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                            <div class="venue-card-info">
                                <span class="venue-card-name">{{ venue.name }}</span>
                                <span class="venue-card-meta">
                                    {{ venue.city }}<template v-if="venue.city && venue.state">, </template>{{ venue.state }} · {{ venue.eventCount }} {{ venue.eventCount === 1 ? 'evento' : 'eventos' }}
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

.venues-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
}

.venues-title {
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

.venues-body {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
    margin-top: var(--space-4);
}

.venues-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.venues-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    pointer-events: none;
}

.venues-search-input {
    width: 100%;
    height: 44px;
    padding: 0 16px 0 44px;
    background: var(--color-surface-2);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xs);
    font-size: var(--text-base);
    color: var(--color-text-primary);
    caret-color: var(--color-brand);
    outline: none;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}

.venues-search-input::placeholder {
    color: var(--color-text-muted);
}

.venues-search-input:focus {
    border-color: var(--color-brand-dim);
    box-shadow: 0 0 0 3px rgba(78, 203, 160, 0.15);
}

.venues-search-input::-webkit-search-decoration,
.venues-search-input::-webkit-search-cancel-button {
    -webkit-appearance: none;
}

.venues-state {
    text-align: center;
    color: var(--color-text-muted);
    font-size: var(--text-sm);
    padding: var(--space-8) 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-3);
}

.venues-retry {
    padding: var(--space-2) var(--space-5);
    font-size: var(--text-sm);
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
    border: none;
    border-radius: var(--radius-xs);
    cursor: pointer;
}

.venues-grid {
    list-style: none;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: var(--space-4);
    max-height: 60dvh;
    overflow-y: auto;
    padding: 2px;
}

.venues-grid-item {
    min-width: 0;
}

.venue-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--color-surface-1);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: transform var(--transition-base), border-color var(--transition-base), box-shadow var(--transition-base);
}

.venue-card:hover {
    transform: scale(1.03) translateY(-4px);
    border-color: var(--color-border-strong);
    box-shadow: 0 0 0 3px #4ecba0, 0 0 12px 4px rgba(78, 203, 160, 0.45);
}

.venue-card-image-wrap {
    aspect-ratio: 16 / 9;
    overflow: hidden;
}

.venue-card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.venue-card:hover .venue-card-image {
    transform: scale(1.05);
}

.venue-card-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: var(--space-3) var(--space-4);
}

.venue-card-name {
    font-size: var(--text-sm);
    font-weight: 700;
    color: var(--color-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.venue-card-meta {
    font-size: var(--text-xs);
    color: var(--color-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (max-width: 540px) {
    .venues-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        max-height: 55dvh;
    }
}
</style>
