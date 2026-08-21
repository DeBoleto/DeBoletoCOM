<template>
    <section class="nearby-section" aria-labelledby="nearby-heading">
        <div class="container">
            <div class="nearby-header">
                <div class="nearby-title-group">
                    <span class="nearby-icon" aria-hidden="true">📍</span>
                    <h2 id="nearby-heading" class="nearby-title">
                        Cerca de ti
                    </h2>
                </div>

                <p
                    v-if="locationFailed"
                    class="nearby-location-warning"
                    role="status"
                >
                    No fue posible obtener tu localizacion, pero tenemos estas
                    opciones que podrian encantarte.
                </p>
            </div>

            <div class="nearby-grid">
                <div class="nearby-col nearby-col--cards">
                    <a
                        v-for="event in filteredSidebar"
                        :key="`sidebar-${event.id}`"
                        :href="`/evento/${event.slug}`"
                        class="nearby-card"
                        :aria-label="`Ver evento: ${event.title}`"
                    >
                        <img
                            :src="event.image"
                            :alt="`Imagen del evento: ${event.title}`"
                            class="nearby-card-img"
                            loading="lazy"
                            decoding="async"
                        />
                        <div class="nearby-card-overlay">
                            <div class="nearby-card-info">
                                <span class="nearby-card-title">{{
                                    event.title
                                }}</span>
                                <span class="nearby-card-location">
                                    <svg
                                        width="14"
                                        height="14"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"
                                        />
                                        <circle cx="12" cy="10" r="3" />
                                    </svg>
                                    {{ event.city }}
                                </span>
                            </div>
                            <span class="nearby-card-cta">Comprar Ahora</span>
                        </div>
                    </a>
                    <p v-if="filteredSidebar.length === 0" class="nearby-empty">
                        No hay eventos en este estado.
                    </p>
                </div>

                <ul
                    class="nearby-col nearby-col--tickets"
                    role="list"
                    aria-label="Eventos cerca de ti"
                >
                    <li
                        v-for="event in filteredActivos"
                        :key="`activos-${event.id}`"
                        class="ticket"
                    >
                        <a
                            :href="`/evento/${event.slug}`"
                            class="ticket-thumb"
                            :aria-label="`Ver evento: ${event.title}`"
                        >
                            <img
                                :src="event.image"
                                :alt="`Imagen del evento: ${event.title}`"
                                class="ticket-thumb-img"
                                loading="lazy"
                                decoding="async"
                            />
                        </a>

                        <div class="ticket-body">
                            <a
                                :href="`/evento/${event.slug}`"
                                class="ticket-title"
                                >{{ event.title }}</a
                            >
                            <span class="ticket-meta">
                                <svg
                                    width="12"
                                    height="12"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"
                                    />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                {{ event.city }}, {{ event.estado }}
                            </span>
                            <span class="ticket-meta ticket-meta--distance">
                                <svg
                                    width="12"
                                    height="12"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg>
                                {{ event.distance }}
                            </span>
                            <span class="ticket-meta ticket-meta--price">
                                <svg
                                    width="12"
                                    height="12"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M20.59 13.41 11 3.82A2 2 0 0 0 9.58 3H5a2 2 0 0 0-2 2v4.59a2 2 0 0 0 .59 1.41l9.59 9.59a2 2 0 0 0 2.82 0l4.59-4.59a2 2 0 0 0 0-2.82z"
                                    />
                                    <path d="M7 7h.01" />
                                </svg>
                                Desde {{ event.price }}
                            </span>
                        </div>

                        <div class="ticket-stub" aria-label="Fecha del evento">
                            <span class="ticket-date-day">{{
                                dateParts(event.date).day
                            }}</span>

                            <span class="ticket-date-month">{{
                                dateParts(event.date).month
                            }}</span>
                        </div>
                    </li>
                    <li
                        v-if="filteredActivos.length === 0"
                        class="ticket ticket--empty"
                    >
                        <p class="nearby-empty">
                            No hay eventos en este estado.
                        </p>
                    </li>
                </ul>
            </div>

            <div class="nearby-footer">
                <div class="nearby-select-wrap">
                    <label for="nearby-estado" class="visually-hidden">
                        Filtrar eventos por estado
                    </label>
                    <select
                        id="nearby-estado"
                        v-model="selectedEstado"
                        class="nearby-select"
                    >
                        <option
                            v-for="estado in estadoOptions"
                            :key="estado"
                            :value="estado"
                        >
                            {{ estado }}
                        </option>
                    </select>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";

const props = defineProps({
    sidebarEvents: {
        type: Array,
        default: () => [],
    },
    activosEvents: {
        type: Array,
        default: () => [],
    },
});

const ESTADO_COORDS = {
    Aguascalientes: [22.0109, -102.3491],
    "Baja California": [30.5834, -115.4334],
    " B.C.": [25.1445, -110.5885],
    "Baja California Sur": [25.1445, -110.5885],
    Campeche: [19.0, -90.5],
    Chiapas: [16.5, -92.5],
    Chihuahua: [28.6333, -106.0694],
    CDMX: [19.4326, -99.1332],
    "Ciudad de México": [19.4326, -99.1332],
    Coahuila: [27.2, -101.3],
    Colima: [19.2433, -103.7245],
    Durango: [24.934, -104.8683],
    "Estado de México": [19.35, -99.65],
    Guanajuato: [20.9, -101.0],
    Guerrero: [17.44, -99.75],
    Hidalgo: [20.48, -98.8],
    Jalisco: [20.6, -103.5],
    Michoacán: [19.4, -101.9],
    Morelos: [18.7, -99.1],
    Nayarit: [21.8, -104.9],
    "Nuevo León": [25.6, -100.0],
    Oaxaca: [17.05, -96.6],
    Puebla: [18.9, -97.95],
    Querétaro: [20.59, -100.2],
    "Quintana Roo": [19.6, -88.0],
    "San Luis Potosí": [22.15, -100.9],
    Sinaloa: [25.0, -107.5],
    Sonora: [29.0, -110.0],
    Tabasco: [17.9, -92.6],
    Tamaulipas: [24.0, -98.7],
    Tlaxcala: [19.3, -98.2],
    Veracruz: [19.5, -96.8],
    Yucatán: [20.8, -89.1],
    Zacatecas: [22.77, -102.5],
};

const selectedEstado = ref("");
const userLocation = ref(null);
const locationFailed = ref(false);

const estadoOptions = computed(() => {
    const estados = new Set();
    [...props.sidebarEvents, ...props.activosEvents].forEach((event) => {
        if (event.estado) {
            estados.add(event.estado);
        }
    });
    return [...estados].sort((a, b) => a.localeCompare(b, "es"));
});

const filteredSidebar = computed(() =>
    props.sidebarEvents
        .filter((event) => event.estado === selectedEstado.value)
        .slice(0, 2),
);

const filteredActivos = computed(() => {
    const events = props.activosEvents
        .filter((event) => event.estado === selectedEstado.value)
        .slice(0, 6);

    return events.map((event) => ({
        ...event,
        distance: distanceFor(event.estado),
    }));
});

function haversineKm(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const toRad = (deg) => (deg * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function distanceFor(estado) {
    if (!userLocation.value) {
        return "—";
    }
    const coords = ESTADO_COORDS[estado];
    if (!coords) {
        return "—";
    }
    const km = haversineKm(
        userLocation.value.latitude,
        userLocation.value.longitude,
        coords[0],
        coords[1],
    );
    return `${Math.round(km)} km`;
}

function dateParts(date) {
    const parts = String(date || "").split(" ");
    return {
        day: parts[0] || "—",
        month: (parts[1] || "").toUpperCase(),
    };
}

function nearestEstado(latitude, longitude) {
    let nearest = null;
    let nearestKm = Infinity;

    estadoOptions.value.forEach((estado) => {
        const coords = ESTADO_COORDS[estado];
        if (!coords) {
            return;
        }
        const km = haversineKm(latitude, longitude, coords[0], coords[1]);
        if (km < nearestKm) {
            nearestKm = km;
            nearest = estado;
        }
    });

    return nearest;
}

onMounted(() => {
    if (estadoOptions.value.length > 0) {
        selectedEstado.value = estadoOptions.value[0];
    }

    if (!navigator.geolocation) {
        locationFailed.value = true;
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const { latitude, longitude } = position.coords;
            userLocation.value = { latitude, longitude };
            const nearest = nearestEstado(latitude, longitude);
            if (nearest) {
                selectedEstado.value = nearest;
            }
        },
        () => {
            locationFailed.value = true;
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
    );
});
</script>

<style scoped>
.nearby-section {
    padding-block: var(--space-16) var(--space-8);
}

.nearby-header {
    margin-bottom: var(--space-8);
}

.nearby-title-group {
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

.nearby-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: var(--radius-md);
    background: var(--color-brand-dim);
    font-size: 1.4rem;
    flex-shrink: 0;
}

.nearby-title {
    font-size: var(--text-2xl);
    font-weight: 800;
    color: var(--color-text-primary);
    letter-spacing: -0.02em;
}

.nearby-footer {
    display: flex;
    justify-content: center;
    margin-top: var(--space-6);
}

.nearby-select-wrap {
    min-width: 220px;
}

.nearby-select {
    appearance: none;
    background-color: var(--color-surface-2);
    background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%235a5a78' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right var(--space-3) center;
    color: var(--color-text-primary);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-3) var(--space-10) var(--space-3) var(--space-4);
    font-size: var(--text-sm);
    font-weight: 600;
    cursor: pointer;
    transition:
        border-color var(--transition-fast),
        box-shadow var(--transition-fast);
}

.nearby-select:hover,
.nearby-select:focus {
    border-color: var(--color-brand);
}

.nearby-select option {
    background-color: var(--color-surface-2);
    color: var(--color-text-primary);
}

.nearby-location-warning {
    margin: var(--space-4) auto 0;
    text-align: center;
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
    background: var(--color-brand-dim);
    border: 1px solid rgba(78, 203, 160, 0.5);
    border-radius: var(--radius-md);
    padding: var(--space-3) var(--space-5);
    margin-top: var(--space-2);
    max-width: 560px;
}

.nearby-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-8);
    align-items: stretch;
}

.nearby-col {
    display: flex;
    flex-direction: column;
    gap: var(--space-5);
    min-width: 0;
}

.nearby-col--cards {
    gap: var(--space-5);
}

.nearby-card {
    position: relative;
    display: block;
    border-radius: var(--radius-lg);
    overflow: hidden;
    aspect-ratio: 16 / 9;
    flex: 1;
    min-height: 0;
    border: 1px solid var(--color-border);
    background: var(--color-surface-1);
    transition:
        transform var(--transition-base),
        border-color var(--transition-base),
        box-shadow var(--transition-base);
}

.nearby-card:hover {
    transform: scale(1.02) translateY(-4px);
    border-color: var(--color-brand);
    box-shadow:
        0 0 0 3px #4ecba0,
        0 0 12px 4px rgba(78, 203, 160, 0.6),
        0 10px 30px rgba(0, 0, 0, 0.45);
}

.nearby-card-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.nearby-card:hover .nearby-card-img {
    transform: scale(1.05);
}

.nearby-card-overlay {
    position: absolute;
    inset: auto 0 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    padding: var(--space-5);
    background: linear-gradient(
        to top,
        rgba(10, 10, 15, 0.92) 0%,
        rgba(10, 10, 15, 0.4) 60%,
        transparent 100%
    );
}

.nearby-card-info {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
    flex: 1;
    min-width: 0;
    align-items: flex-start;
}

.nearby-card-title {
    font-size: var(--text-base);
    font-weight: 700;
    line-height: 1.25;
    color: #fff;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.nearby-card-cta {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-5);
    background: linear-gradient(
        135deg,
        var(--color-brand),
        var(--color-accent)
    );
    color: #fff;
    font-size: var(--text-sm);
    font-weight: 700;
    border-radius: var(--radius-xs);
    box-shadow: var(--shadow-brand);
    white-space: nowrap;
    flex-shrink: 0;
    transition:
        opacity var(--transition-fast),
        transform var(--transition-fast);
}

.nearby-card:hover .nearby-card-cta {
    opacity: 0.92;
    transform: translateY(-2px);
}

.nearby-card-location {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--text-xs);
    font-weight: 600;
    color: #fff;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}

.nearby-card-location svg {
    color: var(--color-brand);
    flex-shrink: 0;
}

.nearby-empty {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    text-align: center;
    padding: var(--space-8);
}

.nearby-col--tickets {
    list-style: none;
}

.ticket {
    display: flex;
    align-items: stretch;
    background: var(--color-surface-1);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition:
        border-color var(--transition-base),
        box-shadow var(--transition-base),
        transform var(--transition-base);
}

.ticket:hover {
    border-color: var(--color-brand);
    transform: scale(1.01) translateY(-2px);
    box-shadow:
        0 0 0 2px rgba(78, 203, 160, 0.5),
        0 10px 30px rgba(0, 0, 0, 0.4);
}

.ticket--empty {
    flex: 1;
    align-items: center;
    justify-content: center;
}

.ticket-thumb {
    display: block;
    width: 96px;
    min-width: 96px;
    height: 96px;
    align-self: center;
    margin-left: var(--space-4);
    border-radius: var(--radius-sm);
    overflow: hidden;
    background: var(--color-surface-2);
}

.ticket-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ticket-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
    justify-content: center;
    padding: var(--space-4) var(--space-4) var(--space-4) var(--space-4);
    min-width: 0;
}

.ticket-title {
    font-size: var(--text-sm);
    font-weight: 700;
    color: var(--color-text-primary);
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color var(--transition-fast);
}

.ticket-title:hover {
    color: var(--color-brand);
}

.ticket-meta {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--text-xs);
    color: var(--color-text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ticket-meta svg {
    color: var(--color-brand);
    flex-shrink: 0;
}

.ticket-meta--distance {
    font-weight: 600;
}

.ticket-meta--price {
    font-weight: 600;
    color: var(--color-brand);
}

.ticket-stub {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: var(--space-4) var(--space-5);
    border-left: 2px dashed var(--color-border-strong);
    flex-shrink: 0;
    min-width: 64px;
    background: var(--color-surface-2);
}

.ticket-stub::before,
.ticket-stub::after {
    content: "";
    position: absolute;
    left: -11px;
    width: 20px;
    height: 20px;
    border-radius: var(--radius-full);
    background: var(--color-bg);
    border: 1px solid var(--color-border);
}

.ticket-stub::before {
    top: -11px;
}

.ticket-stub::after {
    bottom: -11px;
}

.ticket-date-day {
    font-size: var(--text-xl);
    font-weight: 800;
    color: var(--color-text-primary);
    line-height: 1;
}

.ticket-date-month {
    font-size: 10px;
    font-weight: 700;
    color: var(--color-brand);
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

@media (max-width: 1100px) {
    .nearby-grid {
        gap: var(--space-6);
    }
}

@media (max-width: 768px) {
    .nearby-grid {
        grid-template-columns: 1fr;
    }

    .nearby-select-wrap {
        width: 100%;
        min-width: 0;
        max-width: 320px;
    }

    .nearby-select {
        width: 100%;
    }

    .nearby-title {
        font-size: var(--text-xl);
    }
}

@media (max-width: 540px) {
    .nearby-section {
        padding-block: var(--space-12) var(--space-6);
    }

    .ticket-thumb {
        width: 72px;
        min-width: 72px;
        height: 72px;
        margin-left: var(--space-3);
    }

    .ticket-stub {
        padding: var(--space-3) var(--space-4);
        min-width: 52px;
    }

    .ticket-body {
        padding: var(--space-3);
    }
}
</style>
