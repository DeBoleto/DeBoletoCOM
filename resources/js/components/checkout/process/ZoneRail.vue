<template>
    <nav class="rail" aria-label="Precios por zona">
        <div class="rail-head">
            <h2 class="rail-title">Precios por zona</h2>
            <span class="rail-count">{{ zones.filter((zone) => zone.available).length }} disponibles</span>
        </div>

        <ul class="rail-list">
            <li v-if="activeKey !== null">
                <button type="button" class="zone zone--all" @click="emit('show-all')">
                    <span class="zone-swatch zone-swatch--all" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
                    </span>
                    <span class="zone-body">
                        <span class="zone-name">Todas las zonas</span>
                        <span class="zone-sub">Volver al mapa completo</span>
                    </span>
                </button>
            </li>
            <li v-for="zone in zones" :key="zone.key">
                <button
                    type="button"
                    class="zone"
                    :class="{
                        'is-active': zone.key === activeKey,
                        'is-hover': zone.key === hoverKey,
                        'is-off': !zone.available,
                    }"
                    :aria-pressed="zone.key === activeKey"
                    :disabled="!zone.available"
                    @click="emit('select', zone.key)"
                    @mouseenter="emit('hover', zone.key)"
                    @mouseleave="emit('hover', null)"
                    @focus="emit('hover', zone.key)"
                    @blur="emit('hover', null)"
                >
                    <span class="zone-swatch" :style="{ background: zone.color }" aria-hidden="true" />
                    <span class="zone-body">
                        <span class="zone-name">{{ zone.name }}</span>
                        <span v-if="!zone.available" class="zone-sub">No disponible</span>
                        <span v-else class="zone-sub">
                            <s v-if="zone.originalPrice && zone.originalPrice > zone.price">{{ money(zone.originalPrice) }}</s>
                            {{ money(zone.price) }}
                        </span>
                    </span>
                    <Transition name="pop">
                        <span v-if="zone.count > 0" :key="zone.count" class="zone-badge" :aria-label="`${zone.count} boletos elegidos`">
                            {{ zone.count }}
                        </span>
                    </Transition>
                </button>
            </li>
        </ul>
    </nav>
</template>

<script setup>
import { money } from "@/components/checkout/api.js";

defineProps({
    zones: { type: Array, required: true },
    activeKey: { type: String, default: null },
    hoverKey: { type: String, default: null },
});

const emit = defineEmits(["select", "hover", "show-all"]);
</script>

<style scoped>
.rail-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 10px;
}

.rail-title {
    font-size: var(--text-base);
    font-weight: 800;
}

.rail-count {
    color: var(--color-text-muted);
    font-size: var(--text-xs);
}

.rail-list {
    display: flex;
    gap: 8px;
    list-style: none;
    margin: 0;
    padding: 2px 2px 6px;
    overflow-x: auto;
    scroll-snap-type: x proximity;
    scrollbar-width: none;
}

.rail-list::-webkit-scrollbar {
    display: none;
}

.rail-list li {
    scroll-snap-align: start;
    flex-shrink: 0;
}

.zone {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 56px;
    width: 100%;
    padding: 8px 14px 8px 8px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    background: var(--color-surface-2);
    color: var(--color-text-primary);
    text-align: left;
    cursor: pointer;
    transition: border-color var(--transition-fast), background var(--transition-fast), transform var(--transition-fast);
}

.zone:hover:not(:disabled),
.zone.is-hover:not(:disabled) {
    border-color: rgba(78, 203, 160, 0.6);
    background: var(--color-surface-3);
}

.zone:active:not(:disabled) {
    transform: scale(0.98);
}

.zone.is-active {
    border-color: var(--color-brand);
    background: linear-gradient(135deg, rgba(78, 203, 160, 0.2), var(--color-surface-2));
    box-shadow: inset 0 0 0 1px var(--color-brand);
}

.zone.is-off {
    opacity: 0.45;
    cursor: not-allowed;
}

.zone:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.zone-swatch {
    flex-shrink: 0;
    width: 10px;
    align-self: stretch;
    border-radius: 6px;
}

.zone-swatch--all {
    display: grid;
    place-items: center;
    width: 28px;
    background: var(--color-surface-3);
    color: var(--color-brand);
}

.zone-body {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.zone-name {
    font-weight: 700;
    font-size: var(--text-sm);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.zone-sub {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.zone-sub s {
    color: var(--color-text-muted);
    margin-right: 4px;
}

.zone.is-active .zone-sub {
    color: var(--color-brand);
}

.zone-badge {
    position: absolute;
    top: -7px;
    right: -7px;
    display: grid;
    place-items: center;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    border-radius: var(--radius-full);
    background: var(--color-brand);
    color: #06291d;
    font-size: 0.72rem;
    font-weight: 900;
    box-shadow: 0 0 0 3px var(--color-bg);
}

.pop-enter-active {
    animation: pop 320ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.pop-leave-active {
    display: none;
}

@keyframes pop {
    from {
        transform: scale(0.3);
    }
}

@media (min-width: 1024px) {
    .rail-list {
        flex-direction: column;
        overflow: visible;
        padding: 0;
    }

    .zone {
        min-height: 60px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .zone {
        transition: none;
    }

    .pop-enter-active {
        animation: none;
    }
}
</style>
