<template>
    <ProfileShell title="Mis compras">
        <div v-if="purchases.length" class="filters" role="tablist" aria-label="Filtrar compras">
            <button
                v-for="option in filters"
                :key="option.value"
                type="button"
                role="tab"
                class="filter"
                :class="{ 'is-active': filter === option.value }"
                :aria-selected="filter === option.value"
                @click="filter = option.value"
            >
                {{ option.label }} <span class="filter-count">{{ count(option.value) }}</span>
            </button>
        </div>

        <ul v-if="visible.length" class="purchases" role="list">
            <li v-for="purchase in visible" :key="purchase.code">
                <article class="purchase" :class="{ 'is-muted': purchase.action.type !== 'ticket' && purchase.action.type !== 'pdf' && purchase.action.type !== 'bond' }">
                    <div class="purchase-media">
                        <img v-if="purchase.event.image" :src="purchase.event.image" :alt="''" loading="lazy" decoding="async" />
                        <div class="purchase-date" aria-hidden="true">
                            <span class="purchase-day">{{ day(purchase.event.iso) }}</span>
                            <span class="purchase-month">{{ month(purchase.event.iso) }}</span>
                        </div>
                    </div>

                    <div class="purchase-body">
                        <p class="purchase-folio">Folio {{ purchase.folio }}</p>
                        <h2 class="purchase-name">{{ purchase.event.name }}</h2>
                        <p class="purchase-meta">
                            {{ [purchase.event.date, purchase.event.time && `${purchase.event.time} h`].filter(Boolean).join(" · ") }}
                        </p>
                        <p class="purchase-meta">{{ [purchase.event.venue, purchase.event.city].filter(Boolean).join(", ") }}</p>

                        <dl class="purchase-facts">
                            <div>
                                <dt>Boletos</dt>
                                <dd>{{ purchase.tickets }}</dd>
                            </div>
                            <div v-if="purchase.localities.length">
                                <dt>Zona</dt>
                                <dd>{{ purchase.localities.join(", ") }}</dd>
                            </div>
                            <div v-if="purchase.seats.length">
                                <dt>Asientos</dt>
                                <dd class="purchase-seats">{{ purchase.seats.join(", ") }}</dd>
                            </div>
                            <div>
                                <dt>Pagado</dt>
                                <dd>{{ money(purchase.total) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <footer class="purchase-foot">
                        <span class="purchase-bought">Comprado el {{ longDate(purchase.purchasedAt) }}</span>
                        <a v-if="purchase.action.type === 'ticket'" :href="purchase.action.url" class="purchase-cta" target="_blank" rel="noopener">
                            {{ purchase.action.label }}
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9" /></svg>
                            <span class="visually-hidden">(se abre en otra pestaña)</span>
                        </a>
                        <a v-else-if="purchase.action.url" :href="purchase.action.url" class="purchase-cta" :target="purchase.action.type === 'bond' ? '_blank' : undefined" rel="noopener">
                            {{ purchase.action.label }}
                        </a>
                        <span v-else class="purchase-status">{{ purchase.action.label }}</span>
                    </footer>
                </article>
            </li>
        </ul>

        <div v-else class="empty">
            <p class="empty-title">{{ purchases.length ? "No hay compras en esta lista" : "Aún no tienes compras" }}</p>
            <p class="empty-text">Cuando compres boletos los verás aquí con su boleto digital.</p>
            <Link href="/" class="purchase-cta">Ver eventos</Link>
        </div>
    </ProfileShell>
</template>

<script setup>
import { computed, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import ProfileShell from "@/components/profile/ProfileShell.vue";
import { money } from "@/components/checkout/api.js";

const props = defineProps({
    purchases: { type: Array, default: () => [] },
});

const MONTHS = ["ENE", "FEB", "MAR", "ABR", "MAY", "JUN", "JUL", "AGO", "SEP", "OCT", "NOV", "DIC"];
const ACTIVE = ["ticket", "pdf", "bond"];

const filters = [
    { value: "upcoming", label: "Próximos" },
    { value: "past", label: "Anteriores" },
    { value: "all", label: "Todas" },
];

const filter = ref(props.purchases.some((purchase) => ACTIVE.includes(purchase.action.type)) ? "upcoming" : "all");

function matches(purchase, value) {
    if (value === "upcoming") return ACTIVE.includes(purchase.action.type);
    if (value === "past") return !ACTIVE.includes(purchase.action.type);
    return true;
}

const visible = computed(() => props.purchases.filter((purchase) => matches(purchase, filter.value)));

function count(value) {
    return props.purchases.filter((purchase) => matches(purchase, value)).length;
}

function parse(iso) {
    const date = iso ? new Date(String(iso).replace(" ", "T")) : null;
    return date && !Number.isNaN(date.getTime()) ? date : null;
}

function day(iso) {
    return parse(iso)?.getDate() ?? "--";
}

function month(iso) {
    const date = parse(iso);
    return date ? MONTHS[date.getMonth()] : "";
}

function longDate(iso) {
    const date = parse(iso);
    return date ? date.toLocaleDateString("es-MX", { day: "numeric", month: "long", year: "numeric" }) : "—";
}
</script>

<style scoped>
.filters {
    display: flex;
    gap: 8px;
    margin-bottom: var(--space-5);
    overflow-x: auto;
    scrollbar-width: none;
}

.filter {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    padding: 8px 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-full);
    background: transparent;
    color: var(--color-text-secondary);
    font: inherit;
    font-size: var(--text-sm);
    font-weight: 700;
    cursor: pointer;
}

.filter.is-active {
    border-color: var(--color-brand);
    color: var(--color-text-primary);
}

.filter-count {
    min-width: 22px;
    padding: 1px 7px;
    border-radius: var(--radius-full);
    background: var(--color-surface-2);
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}

.filter:focus-visible,
.purchase-cta:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.purchases {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 340px), 1fr));
    gap: var(--space-5);
    list-style: none;
}

.purchase {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    background: var(--color-surface-1);
}

.purchase.is-muted .purchase-media img {
    filter: grayscale(0.7) brightness(0.7);
}

.purchase-media {
    position: relative;
    aspect-ratio: 16 / 8;
    background: linear-gradient(135deg, #0e4d4a, #12121a);
}

.purchase-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.purchase-media::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(0, 0, 0, 0) 55%, rgba(18, 18, 26, 0.95) 100%);
}

.purchase-date {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 52px;
    padding: 6px 8px;
    border-radius: var(--radius-md);
    background: rgba(10, 10, 15, 0.8);
    backdrop-filter: blur(8px);
    line-height: 1;
}

.purchase-day {
    font-size: 22px;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.purchase-month {
    margin-top: 3px;
    color: var(--color-brand);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
}

.purchase-body {
    flex: 1;
    padding: var(--space-4) var(--space-5) var(--space-3);
}

.purchase-folio {
    color: var(--color-text-muted);
    font-size: var(--text-xs);
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    font-variant-numeric: tabular-nums;
}

.purchase-name {
    margin-top: 4px;
    font-size: var(--text-lg);
    font-weight: 800;
    line-height: 1.25;
}

.purchase-meta {
    margin-top: 2px;
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.purchase-facts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 16px;
    margin-top: var(--space-4);
}

.purchase-facts dt {
    color: var(--color-text-muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.purchase-facts dd {
    font-size: var(--text-sm);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.purchase-seats {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.purchase-foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: var(--space-3) var(--space-5) var(--space-4);
    border-top: 1px dashed var(--color-border-strong);
}

.purchase-bought {
    color: var(--color-text-muted);
    font-size: var(--text-xs);
}

.purchase-cta {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 40px;
    padding: 0 16px;
    border-radius: var(--radius-md);
    background: var(--color-brand);
    color: #06291d;
    font-size: var(--text-sm);
    font-weight: 800;
}

.purchase-status {
    padding: 6px 12px;
    border-radius: var(--radius-full);
    background: var(--color-surface-2);
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
    font-weight: 700;
}

.empty {
    display: grid;
    justify-items: center;
    gap: 8px;
    padding: var(--space-12) var(--space-6);
    border: 1px dashed var(--color-border-strong);
    border-radius: var(--radius-xl);
    text-align: center;
}

.empty-title {
    font-size: var(--text-lg);
    font-weight: 800;
}

.empty-text {
    margin-bottom: 8px;
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}
</style>
