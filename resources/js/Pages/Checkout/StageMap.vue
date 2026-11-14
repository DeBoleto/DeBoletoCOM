<template>
    <Head :title="`Compra · ${event.name}`" />

    <div class="page-layout">
        <SiteHeader @open-login="auth?.open('phone')" @open-register="auth?.open('register')" />

        <main id="main-content">
            <div class="process-bar">
                <div class="container process-bar-inner">
                    <Link :href="route('event.detail', event.slug)" class="process-event" :aria-label="`Volver a ${event.name}`">
                        <img v-if="event.image" :src="event.image" alt="" class="process-thumb" />
                        <span class="process-event-text">
                            <span class="process-event-name">{{ event.name }}</span>
                            <span class="process-event-meta">{{ eventMeta }}</span>
                        </span>
                    </Link>
                    <CheckoutSteps :current="0" class="process-steps" />
                    <Transition name="fade">
                        <HoldTimer v-if="deadline" :deadline="deadline" label="Tus lugares se apartan" @expired="expire" />
                    </Transition>
                </div>
            </div>

            <TransitionGroup tag="div" name="toast" class="toasts" aria-live="polite">
                <p v-for="toast in toasts" :key="toast.id" class="toast" :class="`toast--${toast.tone}`" role="status">
                    {{ toast.text }}
                </p>
            </TransitionGroup>

            <div v-if="!props.function" class="container">
                <FunctionPicker :functions="functions" @choose="chooseFunction" />
            </div>

            <div v-else class="container venue">
                <div class="venue-grid">
                    <aside class="venue-rail">
                        <section class="event-card" aria-label="Datos del evento">
                            <h1 class="event-card-name">{{ event.name }}</h1>
                            <p class="event-card-line">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                                <span>{{ props.function.date }} · {{ props.function.time }}</span>
                                <button v-if="functions.length > 1" type="button" class="event-card-change" @click="changeDate">Cambiar</button>
                            </p>
                            <p v-if="venueLine" class="event-card-line">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg>
                                <span>{{ venueLine }}</span>
                            </p>
                        </section>

                        <VenueZoneList :zones="zoneList" :active-key="activeKey" @select="focusZone" @show-all="showAllZones" />
                    </aside>

                    <section ref="mapPanel" class="map-panel" aria-label="Mapa del recinto">
                        <div class="map-toolbar">
                            <nav class="crumbs" aria-label="Ubicación en el mapa">
                                <button type="button" class="crumb" :class="{ 'is-current': !activeSection }" @click="showAllZones">
                                    Mapa completo
                                </button>
                                <template v-if="activeSection">
                                    <span class="crumb-sep" aria-hidden="true">›</span>
                                    <span class="crumb is-current">
                                        <span class="crumb-swatch" :style="{ background: zoneColor(activeSection) }" />
                                        {{ activeSection.name }}
                                    </span>
                                </template>
                            </nav>
                            <p v-if="activeSection" class="map-price">
                                <strong>{{ money(activeInfo?.price ?? sectionPrice(activeSection)) }}</strong> por boleto
                            </p>
                            <p v-else class="map-hint">Toca una zona para ver sus asientos</p>
                        </div>

                        <div v-if="activeInfo?.promotion || activeInfo?.volumeDiscount" class="promo-strip">
                            <span v-if="activeInfo.promotion" class="promo-chip">🎉 Promoción {{ activeInfo.promotion.label }} en esta zona</span>
                            <span v-if="activeInfo.volumeDiscount" class="promo-chip">
                                Desde {{ activeInfo.volumeDiscount.from }} boletos, {{ money(activeInfo.volumeDiscount.amount) }} menos c/u
                            </span>
                        </div>

                        <div class="map-stage">
                            <Transition name="swap" mode="out-in">
                                <div v-if="activeIsGeneral" key="ga" class="ga">
                                    <div class="ga-card">
                                        <span class="ga-icon" aria-hidden="true">
                                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                                        </span>
                                        <p class="ga-title">{{ activeSection.name }} · Acceso general</p>
                                        <template v-if="activeInfo">
                                            <p class="ga-text">
                                                <template v-if="activeInfo.remaining > 0">Sin asientos numerados. ¿Cuántos boletos quieres?</template>
                                                <template v-else>Ya no quedan boletos en esta zona.</template>
                                            </p>
                                            <Stepper
                                                v-if="activeInfo.remaining > 0"
                                                :model-value="readPick(activeKey).quantity"
                                                :max="gaMax(activeKey)"
                                                label="boletos"
                                                @update:model-value="(value) => setQuantity(activeKey, value)"
                                            />
                                            <p v-if="activeInfo.remaining > 0 && activeInfo.remaining <= 20" class="ga-left">¡Solo quedan {{ activeInfo.remaining }}!</p>
                                        </template>
                                        <p v-else class="ga-text">Consultando disponibilidad…</p>
                                    </div>
                                </div>

                                <div v-else key="canvas" class="viewport">
                                    <VenueCanvas
                                        ref="canvas"
                                        :seats="seats"
                                        :zones="canvasZones"
                                        :active-key="activeKey"
                                        :selected-keys="selectedKeys"
                                        @zone="focusZone"
                                        @toggle="toggleSeat"
                                        @hover="hoverSeat = $event"
                                    />

                                    <Transition name="fade">
                                        <div v-if="!venueLoaded" class="map-loading" aria-label="Cargando disponibilidad">
                                            <span class="spinner" aria-hidden="true" />
                                            <span>Cargando el mapa…</span>
                                        </div>
                                    </Transition>

                                    <div class="map-controls" role="group" aria-label="Controles del mapa">
                                        <button type="button" aria-label="Acercar" @click="canvas?.zoomIn()">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                                        </button>
                                        <button type="button" aria-label="Alejar" @click="canvas?.zoomOut()">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14" /></svg>
                                        </button>
                                        <button type="button" aria-label="Centrar mapa" @click="canvas?.reset()">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9V5a2 2 0 0 1 2-2h4M21 9V5a2 2 0 0 0-2-2h-4M3 15v4a2 2 0 0 0 2 2h4M21 15v4a2 2 0 0 1-2 2h-4" /></svg>
                                        </button>
                                    </div>

                                    <Transition name="fade">
                                        <button v-if="activeSection" type="button" class="map-back" @click="showAllZones">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                                            Mapa completo
                                        </button>
                                    </Transition>

                                    <p class="map-gesture" aria-hidden="true">Arrastra para moverte · pellizca para acercar</p>
                                </div>
                            </Transition>
                        </div>

                        <ul v-if="!activeIsGeneral" class="legend">
                            <li><span class="swatch" :style="{ background: legendColors.available }" /> Disponible</li>
                            <li>
                                <span class="swatch swatch--check" :style="{ background: legendColors.selected }">✓</span>
                                Tu selección
                            </li>
                            <li><span class="swatch swatch--proceso" /> En proceso</li>
                            <li><span class="swatch swatch--vendido" /> Vendido</li>
                        </ul>
                    </section>

                    <aside class="venue-cart" aria-labelledby="cart-title">
                        <CartPanel
                            :groups="groups"
                            :count="totalTickets"
                            :total="estimate"
                            :max-tickets="limits.maxTickets"
                            :parking="parking"
                            :parking-selected="wantsParking"
                            :submitting="submitting"
                            empty-hint="Toca una zona del mapa para elegir tus lugares. Puedes combinar varias zonas."
                            @remove="removeTicket"
                            @special="setSpecial"
                            @extra="setExtraChair"
                            @parking="wantsParking = $event"
                            @continue="submit"
                            @cancel="cancel"
                            @clear="clearAll"
                            @focus="focusGroup"
                        />
                    </aside>
                </div>
            </div>

            <Transition name="slide-up">
                <div v-if="props.function && totalTickets > 0" class="mobile-bar">
                    <button type="button" class="mobile-cart" :aria-label="`Ver resumen: ${totalTickets} boletos`" @click="sheetOpen = true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a3 3 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a3 3 0 0 0 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1z" /></svg>
                        <span :key="totalTickets" class="mobile-count">{{ totalTickets }}</span>
                    </button>
                    <button type="button" class="mobile-sum" @click="sheetOpen = true">
                        <span>Subtotal</span>
                        <strong>{{ money(estimate) }}</strong>
                    </button>
                    <button type="button" class="mobile-go" :disabled="submitting" @click="submit">
                        {{ submitting ? "Apartando…" : "Continuar" }}
                    </button>
                </div>
            </Transition>

            <Transition name="sheet">
                <div v-if="sheetOpen" class="sheet-wrap" @click.self="sheetOpen = false">
                    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-cart-title">
                        <span class="sheet-handle" aria-hidden="true" />
                        <button type="button" class="sheet-close" aria-label="Cerrar resumen" @click="sheetOpen = false">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                        </button>
                        <CartPanel
                            title-id="sheet-cart-title"
                            :groups="groups"
                            :count="totalTickets"
                            :total="estimate"
                            :max-tickets="limits.maxTickets"
                            :parking="parking"
                            :parking-selected="wantsParking"
                            :submitting="submitting"
                            @remove="removeTicket"
                            @special="setSpecial"
                            @extra="setExtraChair"
                            @parking="wantsParking = $event"
                            @continue="submit"
                            @cancel="cancel"
                            @clear="clearAll"
                            @focus="focusGroup"
                        />
                    </div>
                </div>
            </Transition>

            <div
                v-if="tooltip"
                class="seat-tooltip"
                :style="{ left: `${hoverSeat.clientX}px`, top: `${hoverSeat.clientY}px` }"
                aria-hidden="true"
            >
                <strong :style="{ color: tooltip.color }">{{ tooltip.title }}</strong>
                <span>{{ tooltip.detail }}</span>
            </div>
        </main>

        <SiteFooter />
        <MobileBottomNav v-if="totalTickets === 0" @open-login="auth?.open('phone')" />

        <DialogModal :show="rules !== null" max-width="md" @close="rules = null">
            <template #title>Reglas de {{ rules?.zone }}</template>
            <template #content>
                <p class="rules-text">{{ rules?.text }}</p>
            </template>
            <template #footer>
                <button type="button" class="ghost-btn ghost-btn--solid" @click="rules = null">Entendido</button>
            </template>
        </DialogModal>

        <AuthModals ref="auth" @authenticated="afterLogin" />
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import VenueCanvas from "@/components/seat-map/VenueCanvas.vue";
import { zoneSeatColors } from "@/components/seat-map/seatColors.js";
import SiteHeader from "@/components/SiteHeader.vue";
import SiteFooter from "@/components/SiteFooter.vue";
import MobileBottomNav from "@/components/MobileBottomNav.vue";
import DialogModal from "@/components/DialogModal.vue";
import CheckoutSteps from "@/components/checkout/CheckoutSteps.vue";
import HoldTimer from "@/components/checkout/HoldTimer.vue";
import AuthModals from "@/components/checkout/AuthModals.vue";
import Stepper from "@/components/checkout/Stepper.vue";
import FunctionPicker from "@/components/checkout/process/FunctionPicker.vue";
import CartPanel from "@/components/checkout/process/CartPanel.vue";
import VenueZoneList from "@/components/checkout/stage-map/VenueZoneList.vue";
import { getJson, money, postJson } from "@/components/checkout/api.js";

const props = defineProps({
    event: { type: Object, required: true },
    functions: { type: Array, default: () => [] },
    function: { type: Object, default: null },
    stage: { type: Object, default: null },
    limits: { type: Object, required: true },
    parking: { type: Object, default: null },
    cart: { type: Object, default: null },
});

const POLL_MS = 15000;
const STATUS_LABEL = { disponible: "Disponible", vendido: "Vendido", en_proceso: "En proceso de compra", no_disponible: "No disponible" };
const VENUE_STATUS = { vendido: "vendido", no_disponible: "no_disponible", proceso: "en_proceso", mio: "disponible" };

const page = usePage();
const auth = ref(null);
const canvas = ref(null);
const mapPanel = ref(null);

const activeKey = ref(null);
const hoverSeat = ref(null);
const picks = reactive({});
const infos = reactive({});
const venue = reactive({});
const venueLoaded = ref(false);
const wantsParking = ref(Boolean(props.cart?.parking));
const submitting = ref(false);
const pendingSubmit = ref(false);
const rules = ref(null);
const sheetOpen = ref(false);
const deadline = ref(null);
const toasts = ref([]);
const rulesShown = new Set();
const requestIds = {};
let venueRequest = 0;
let pollId;
let toastId = 0;

const eventMeta = computed(() =>
    [props.event.venue, props.function ? [props.function.date, props.function.time].filter(Boolean).join(" · ") : null]
        .filter(Boolean)
        .join(" · "),
);

const venueLine = computed(() => [props.event.venue, props.event.city, props.event.state].filter(Boolean).join(", "));

const sections = computed(() => props.stage?.sections ?? []);
const activeSection = computed(() => sections.value.find((section) => section.key === activeKey.value) ?? null);
const activeInfo = computed(() => (activeKey.value === null ? null : infos[activeKey.value] ?? null));
const activeIsGeneral = computed(() => activeSection.value !== null && isGeneral(activeKey.value));
const legendColors = computed(() => zoneSeatColors(activeSection.value ? zoneColor(activeSection.value) : "#4ecba0"));

const seats = computed(() =>
    sections.value
        .filter((section) => section.capacity === null && section.rows.length)
        .flatMap((section) => {
            const statuses = venue[section.key]?.statuses ?? {};
            const offset = section.offset ?? { x: 0, y: 0 };

            return section.rows.flatMap((row) =>
                row.seats.map((seat) => ({
                    key: section.key,
                    id: seat.id,
                    fila: row.fila,
                    x: seat.x + offset.x,
                    y: row.y + offset.y,
                    zx: seat.x,
                    zy: row.y,
                    status: seat.selectable === false || !section.available ? "no_disponible" : VENUE_STATUS[statuses[seat.id]] ?? "disponible",
                })),
            );
        }),
);

const canvasZones = computed(() =>
    Object.fromEntries(
        sections.value.map((section) => [
            section.key,
            {
                name: section.name,
                color: zoneColor(section),
                available: section.available,
                hasSeats: section.capacity === null && section.rows.length > 0,
                price: sectionPrice(section),
            },
        ]),
    ),
);

const zoneList = computed(() =>
    sections.value
        .map((section) => ({
            key: section.key,
            name: section.name,
            color: zoneColor(section),
            available: section.available,
            price: sectionPrice(section),
            originalPrice: section.price?.originalPrice ?? null,
            remaining: venue[section.key]?.remaining ?? null,
            count: countFor(section.key),
        }))
        .sort((a, b) => Number(b.available) - Number(a.available) || b.price - a.price),
);

const selectedKeys = computed(() =>
    Object.entries(picks).flatMap(([key, pick]) => (isGeneral(key) ? [] : pick.seats.map((seat) => `${key}:${seat}`))),
);

const tooltip = computed(() => {
    const hover = hoverSeat.value;
    if (!hover) return null;

    const section = sections.value.find((candidate) => candidate.key === hover.seat.key);
    if (!section) return null;

    if (!hover.zoneMode) {
        return {
            title: section.name,
            color: zoneColor(section),
            detail: section.available ? `${money(sectionPrice(section))} · Toca para ver asientos` : "No disponible",
        };
    }

    const mine = selectedKeys.value.includes(`${hover.seat.key}:${hover.seat.id}`);
    const status = mine ? "Tu selección" : STATUS_LABEL[hover.seat.status] ?? hover.seat.status;

    return {
        title: `Fila ${hover.seat.fila} · Asiento ${hover.seat.id}`,
        color: zoneColor(section),
        detail: hover.seat.status === "disponible" ? `${money(infos[section.key]?.price ?? sectionPrice(section))} · ${status}` : status,
    };
});

const totalTickets = computed(() => sections.value.reduce((sum, section) => sum + countFor(section.key), 0));

const groups = computed(() =>
    sections.value
        .filter((section) => countFor(section.key) > 0)
        .map((section) => {
            const key = section.key;
            const pick = readPick(key);
            const info = infos[key];
            const general = isGeneral(key);
            const unitPrice = info?.price ?? sectionPrice(section);
            const specials = info?.specials ?? [];
            const specialPrices = Object.fromEntries(specials.map((special) => [special.id, special.price]));
            const list = general ? Array.from({ length: pick.quantity }, () => null) : pick.seats;
            const rows = Object.fromEntries(section.rows.flatMap((row) => row.seats.map((seat) => [seat.id, row.fila])));

            return {
                key,
                name: section.name,
                color: zoneColor(section),
                unitPrice,
                specials,
                extraChairPrice: info?.extraChair?.price ?? 0,
                tickets: list.map((seat, index) => {
                    const specialKey = seat ?? `Boleto${index + 1}`;
                    const specialsAllowed = specials.length > 0 && (info.specialsOnEveryTicket || index % 2 === 1);
                    const special = specialsAllowed ? pick.specials[specialKey] ?? null : null;

                    return {
                        id: `${key}:${seat ?? `ga-${index}`}`,
                        seat,
                        index,
                        label: seat ? `Asiento ${seat}` : `Boleto ${index + 1}`,
                        sub: seat && rows[seat] ? `Fila ${rows[seat]}` : general ? "Acceso general" : "",
                        specialKey,
                        specialsAllowed,
                        special,
                        price: special ? specialPrices[special] ?? unitPrice : unitPrice,
                        extraChairAvailable: Boolean(info?.extraChair) && seat !== null,
                        extraChair: seat !== null && pick.extraChairs.includes(seat),
                    };
                }),
            };
        }),
);

const estimate = computed(() =>
    groups.value.reduce((sum, group) => sum + group.tickets.reduce((acc, ticket) => acc + ticket.price + (ticket.extraChair ? group.extraChairPrice : 0), 0), 0),
);

watch(totalTickets, (count) => {
    if (count > 0 && deadline.value === null) deadline.value = Math.floor(Date.now() / 1000) + props.limits.holdMinutes * 60;
    if (count === 0) {
        deadline.value = null;
        sheetOpen.value = false;
    }
});

watch(
    () => page.props.flash?.error,
    (message) => message && notify(message),
    { immediate: true },
);

watch(sheetOpen, (open) => {
    document.body.style.overflow = open ? "hidden" : "";
});

const EMPTY_PICK = Object.freeze({ seats: [], quantity: 0, specials: {}, extraChairs: [] });

function readPick(key) {
    return (key !== null && picks[key]) || EMPTY_PICK;
}

function pickFor(key) {
    if (!picks[key]) picks[key] = { seats: [], quantity: 0, specials: {}, extraChairs: [] };
    return picks[key];
}

function sectionByKey(key) {
    return sections.value.find((section) => section.key === key) ?? null;
}

function isGeneral(key) {
    return sectionByKey(key)?.capacity != null;
}

function countFor(key) {
    const pick = picks[key];
    if (!pick) return 0;
    return isGeneral(key) ? pick.quantity : pick.seats.length;
}

function sectionPrice(section) {
    return section.price?.discountPrice ?? section.price?.originalPrice ?? 0;
}

function zoneColor(section) {
    return section.color || section.areaColor;
}

function notify(text, tone = "error") {
    const id = ++toastId;
    toasts.value.push({ id, text, tone });
    window.setTimeout(() => {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }, 5500);
}

function chooseFunction(fn) {
    router.get(route("checkout.map", props.event.id), { function: fn.id });
}

function changeDate() {
    if (totalTickets.value > 0 && !window.confirm("Si cambias de fecha se liberan los lugares que elegiste. ¿Continuar?")) return;
    clearAll();
    router.get(route("checkout.map", props.event.id));
}

async function focusZone(key) {
    const section = sectionByKey(key);

    if (!section?.available) {
        notify("Esa zona no está a la venta.");
        return;
    }

    hoverSeat.value = null;
    activeKey.value = key;

    if (window.matchMedia("(max-width: 1023px)").matches) {
        await nextTick();
        mapPanel.value?.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    await loadSection(key);

    const info = infos[key];
    if (info?.rules && !rulesShown.has(key)) {
        rulesShown.add(key);
        rules.value = { zone: info.name, text: info.rules };
    }
}

function showAllZones() {
    activeKey.value = null;
    hoverSeat.value = null;
}

function focusGroup(group) {
    sheetOpen.value = false;
    focusZone(group.key);
}

async function loadVenue() {
    if (!props.function) return;

    const current = ++venueRequest;
    const { ok, data } = await getJson(route("checkout.venue", { evento: props.event.id, funcion: props.function.id }));

    if (current !== venueRequest) return;

    venueLoaded.value = true;

    if (!ok) {
        notify("No pudimos actualizar la disponibilidad del mapa.");
        return;
    }

    for (const [key, zone] of Object.entries(data.zones ?? {})) {
        venue[key] = zone;
        dropLostSeats(key, zone.statuses ?? {});
        if (isGeneral(key) && picks[key]) picks[key].quantity = Math.min(picks[key].quantity, zone.remaining ?? 0);
    }
}

async function loadSection(key) {
    const section = sectionByKey(key);
    if (!section || !props.function) return;

    const current = (requestIds[key] = (requestIds[key] ?? 0) + 1);

    const { ok, data } = await getJson(
        route("checkout.section", { evento: props.event.id, funcion: props.function.id, seccion: section.sectionId }),
    );

    if (current !== requestIds[key] || !ok) return;

    infos[key] = data;

    if (data.capacity !== null && data.capacity !== undefined) {
        venue[key] = { remaining: data.remaining ?? 0, statuses: {} };
        if (picks[key]) picks[key].quantity = Math.min(picks[key].quantity, data.remaining ?? 0);
        return;
    }

    const statuses = data.statuses ?? {};
    const remaining = section.rows
        .flatMap((row) => row.seats)
        .filter((seat) => seat.selectable !== false && (!statuses[seat.id] || statuses[seat.id] === "mio")).length;

    venue[key] = { remaining, statuses };
    dropLostSeats(key, statuses);
}

function dropLostSeats(key, statuses) {
    const pick = picks[key];
    if (!pick || isGeneral(key)) return;

    const lost = pick.seats.filter((seat) => ["vendido", "proceso", "no_disponible"].includes(statuses[seat]));
    if (!lost.length) return;

    pick.seats = pick.seats.filter((seat) => !lost.includes(seat));
    notify(`Ya no está disponible en ${sectionByKey(key)?.name}: ${lost.join(", ")}.`);
}

function gaMax(key) {
    const current = readPick(key).quantity;
    return Math.max(0, Math.min(infos[key]?.remaining ?? 0, props.limits.maxTickets - totalTickets.value + current));
}

function setQuantity(key, value) {
    const pick = pickFor(key);
    if (value > pick.quantity && totalTickets.value >= props.limits.maxTickets) {
        notify(`Puedes elegir hasta ${props.limits.maxTickets} boletos por compra.`, "info");
        return;
    }
    pick.quantity = value;
    for (const specialKey of Object.keys(pick.specials)) {
        if (Number(specialKey.replace("Boleto", "")) > value) delete pick.specials[specialKey];
    }
}

async function toggleSeat(seat) {
    const key = seat.key;
    const pick = pickFor(key);

    if (pick.seats.includes(seat.id)) {
        releaseSeat(key, seat.id);
        return;
    }

    if (totalTickets.value >= props.limits.maxTickets) {
        notify(`Puedes elegir hasta ${props.limits.maxTickets} boletos por compra.`, "info");
        return;
    }

    pick.seats.push(seat.id);
    if (navigator.vibrate) navigator.vibrate(12);

    const { ok, data } = await hold(key, seat.id, "apartar");

    if (!ok && data?.status !== "local") {
        pick.seats = pick.seats.filter((id) => id !== seat.id);
        notify(data?.message ?? "Uno de los asientos que seleccionaste está en proceso de compra. Elige otro.");
        loadSection(key);
    }
}

function releaseSeat(key, seatId) {
    const pick = pickFor(key);
    pick.seats = pick.seats.filter((id) => id !== seatId);
    pick.extraChairs = pick.extraChairs.filter((id) => id !== seatId);
    delete pick.specials[seatId];
    hold(key, seatId, "liberar");
}

function hold(key, seat, action) {
    return postJson(route("checkout.hold", props.event.id), {
        funcion: props.function.id,
        seccion: sectionByKey(key).sectionId,
        asiento: seat,
        accion: action,
    });
}

function removeTicket(group, ticket) {
    if (ticket.seat === null) {
        setQuantity(group.key, Math.max(0, readPick(group.key).quantity - 1));
        return;
    }

    releaseSeat(group.key, ticket.seat);
}

function setSpecial(group, ticket, value) {
    const pick = pickFor(group.key);
    if (value) pick.specials[ticket.specialKey] = value;
    else delete pick.specials[ticket.specialKey];
}

function setExtraChair(group, ticket, checked) {
    const pick = pickFor(group.key);
    pick.extraChairs = checked ? [...new Set([...pick.extraChairs, ticket.seat])] : pick.extraChairs.filter((seat) => seat !== ticket.seat);
}

function clearAll() {
    for (const [key, pick] of Object.entries(picks)) {
        if (!isGeneral(key)) pick.seats.forEach((seat) => hold(key, seat, "liberar"));
        picks[key] = { seats: [], quantity: 0, specials: {}, extraChairs: [] };
    }
}

function payload() {
    return {
        funcion: props.function.id,
        estacionamiento: wantsParking.value,
        secciones: groups.value.map((group) => {
            const section = sectionByKey(group.key);
            const pick = readPick(group.key);
            const general = isGeneral(group.key);

            return {
                localidad: Number(section.localityId),
                seccion: section.sectionId,
                asientos: general ? [] : [...pick.seats],
                cantidad: general ? pick.quantity : null,
                especiales: Object.fromEntries(group.tickets.filter((ticket) => ticket.special).map((ticket) => [ticket.specialKey, ticket.special])),
                sillas_extra: [...pick.extraChairs],
            };
        }),
    };
}

async function submit() {
    if (submitting.value) return;

    if (totalTickets.value === 0) {
        notify("Es necesario seleccionar los asientos para continuar.", "info");
        return;
    }

    for (const group of groups.value) {
        if (!infos[group.key]) await loadSection(group.key);
        const promotion = infos[group.key]?.promotion;
        if (promotion && group.tickets.length <= promotion.count) {
            notify(`Elige al menos ${promotion.count + 1} boletos en ${group.name} para aprovechar la promoción ${promotion.label}.`, "info");
        }
    }

    submitting.value = true;

    router.post(route("checkout.selection", props.event.id), payload(), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (response) => {
            if (response.props.flash?.auth_required) {
                pendingSubmit.value = true;
                sheetOpen.value = false;
                auth.value?.open("phone");
            }
        },
        onError: (errors) => notify(Object.values(errors)[0] ?? "Revisa tu selección."),
        onFinish: () => {
            submitting.value = false;
        },
    });
}

function afterLogin() {
    if (pendingSubmit.value) {
        pendingSubmit.value = false;
        submit();
    }
}

function cancel() {
    router.post(route("checkout.cancel", props.event.id));
}

function expire() {
    if (totalTickets.value === 0) return;
    notify("Se terminó el tiempo para elegir. Liberamos tus lugares.");
    router.post(route("checkout.cancel", props.event.id));
}

async function poll() {
    if (submitting.value || document.hidden) return;
    await loadVenue();
    if (activeKey.value !== null) await loadSection(activeKey.value);
}

function onKey(event) {
    if (event.key !== "Escape") return;
    if (sheetOpen.value) sheetOpen.value = false;
    else if (activeKey.value !== null && rules.value === null) showAllZones();
}

onMounted(async () => {
    window.addEventListener("keydown", onKey);

    if (!props.function) return;

    await loadVenue();

    const restored = (props.cart?.sections ?? [])
        .map((saved) => ({ saved, section: sections.value.find((candidate) => candidate.sectionId === saved.sectionId) }))
        .filter(({ section }) => section?.available);

    for (const { saved, section } of restored) {
        const statuses = venue[section.key]?.statuses ?? {};
        const pick = pickFor(section.key);
        pick.seats = saved.seats.filter((seat) => statuses[seat] === "mio" || !statuses[seat]);
        pick.quantity = Math.min(saved.quantity ?? 0, venue[section.key]?.remaining ?? saved.quantity ?? 0);
        loadSection(section.key);
    }

    const available = sections.value.filter((section) => section.available);
    if (available.length === 1) focusZone(available[0].key);

    pollId = window.setInterval(poll, POLL_MS);
});

onUnmounted(() => {
    window.clearInterval(pollId);
    window.removeEventListener("keydown", onKey);
    document.body.style.overflow = "";
});
</script>

<style scoped>
.page-layout {
    display: flex;
    flex-direction: column;
    min-height: 100dvh;
}

main {
    flex: 1;
}

.process-bar {
    position: sticky;
    top: var(--header-height);
    z-index: 40;
    background: rgba(10, 10, 15, 0.88);
    border-bottom: 1px solid var(--color-border);
    backdrop-filter: blur(14px);
}

.process-bar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    min-height: 64px;
    padding-block: 8px;
}

.process-event {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    color: inherit;
    text-decoration: none;
}

.process-thumb {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: var(--radius-md);
    object-fit: cover;
    box-shadow: 0 0 0 1px var(--color-border-strong);
}

.process-event-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.process-event-name {
    font-weight: 800;
    font-size: var(--text-base);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.process-event-meta {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.process-steps {
    display: none;
}

.toasts {
    position: fixed;
    top: calc(var(--header-height) + 76px);
    left: 50%;
    z-index: 70;
    display: grid;
    gap: 8px;
    width: min(92vw, 440px);
    transform: translateX(-50%);
    pointer-events: none;
}

.toast {
    padding: 12px 16px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    background: rgba(26, 26, 38, 0.97);
    box-shadow: var(--shadow-lg);
    font-size: var(--text-sm);
    font-weight: 600;
}

.toast--error {
    border-color: rgba(239, 68, 68, 0.5);
    color: #fecaca;
}

.toast--info,
.toast--success {
    border-color: rgba(78, 203, 160, 0.5);
}

.toast-enter-active {
    transition: all 320ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.toast-leave-active {
    transition: all 200ms ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-12px) scale(0.97);
}

.venue {
    padding-top: var(--space-4);
    padding-bottom: calc(var(--bottom-nav-height) + var(--space-10));
}

.venue-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: var(--space-4);
}

.venue-grid > * {
    min-width: 0;
}

.venue-rail {
    display: grid;
    gap: var(--space-4);
    align-content: start;
}

.event-card {
    display: grid;
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    background:
        radial-gradient(120% 120% at 0% 0%, rgba(78, 203, 160, 0.12), transparent 60%),
        var(--color-surface-1);
}

.event-card-name {
    font-size: var(--text-lg);
    font-weight: 900;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

.event-card-line {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.event-card-line svg {
    flex-shrink: 0;
    color: var(--color-brand);
}

.event-card-line span {
    min-width: 0;
    overflow-wrap: anywhere;
}

.event-card-change {
    margin-left: auto;
    padding: 4px 0 4px 8px;
    border: none;
    background: none;
    color: var(--color-brand);
    font-size: var(--text-xs);
    font-weight: 800;
    cursor: pointer;
}

.map-panel {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    background:
        radial-gradient(120% 80% at 50% 0%, rgba(78, 203, 160, 0.1), transparent 60%),
        var(--color-surface-1);
    scroll-margin-top: calc(var(--header-height) + 72px);
}

.map-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    margin-bottom: 10px;
}

.crumbs {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}

.crumb {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 36px;
    padding: 0 12px;
    border: none;
    border-radius: var(--radius-full);
    background: var(--color-surface-2);
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
    font-weight: 700;
    white-space: nowrap;
}

button.crumb {
    cursor: pointer;
}

button.crumb:hover {
    color: var(--color-text-primary);
}

.crumb.is-current {
    background: var(--color-brand-dim);
    color: var(--color-text-primary);
    cursor: default;
}

.crumb-sep {
    color: var(--color-text-muted);
}

.crumb-swatch {
    width: 8px;
    height: 8px;
    border-radius: 2px;
}

.map-price {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.map-price strong {
    color: var(--color-brand);
    font-size: var(--text-lg);
    font-variant-numeric: tabular-nums;
}

.map-hint {
    color: var(--color-text-muted);
    font-size: var(--text-xs);
    animation: pulse 2.4s ease-in-out infinite;
}

.promo-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
}

.promo-chip {
    padding: 6px 12px;
    border: 1px solid rgba(229, 53, 171, 0.45);
    border-radius: var(--radius-full);
    background: linear-gradient(90deg, rgba(229, 53, 171, 0.25), rgba(249, 115, 22, 0.25));
    font-size: var(--text-xs);
    font-weight: 700;
}

.map-stage {
    position: relative;
}

.viewport {
    position: relative;
    height: clamp(340px, 60dvh, 640px);
    overflow: hidden;
    border-radius: var(--radius-lg);
    background: radial-gradient(circle at 50% 0%, #ffffff 0%, #f1f5f9 70%);
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
}

.map-loading {
    position: absolute;
    inset: 0;
    z-index: 3;
    display: grid;
    place-content: center;
    justify-items: center;
    gap: 12px;
    background: rgba(241, 245, 249, 0.85);
    color: #475569;
    font-size: var(--text-sm);
    font-weight: 700;
}

.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #cbd5e1;
    border-top-color: var(--color-brand);
    border-radius: 50%;
    animation: spin 800ms linear infinite;
}

.map-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 2;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #d1d5db;
    border-radius: var(--radius-md);
    background: #ffffff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
}

.map-controls button {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border: none;
    border-bottom: 1px solid #e5e7eb;
    background: none;
    color: #0f172a;
    cursor: pointer;
}

.map-controls button:last-child {
    border-bottom: none;
}

.map-controls button:hover {
    background: #f1f5f9;
}

.map-back {
    position: absolute;
    left: 10px;
    top: 10px;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 40px;
    padding: 0 14px;
    border: none;
    border-radius: var(--radius-full);
    background: #0f172a;
    color: #ffffff;
    font-size: var(--text-sm);
    font-weight: 800;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.25);
    cursor: pointer;
}

.map-gesture {
    position: absolute;
    left: 50%;
    bottom: 10px;
    z-index: 1;
    padding: 4px 10px;
    border-radius: var(--radius-full);
    background: rgba(15, 23, 42, 0.72);
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 600;
    white-space: nowrap;
    transform: translateX(-50%);
    pointer-events: none;
}

.ga {
    display: grid;
    place-items: center;
    min-height: 320px;
    padding: 20px;
}

.ga-card {
    display: grid;
    justify-items: center;
    gap: 10px;
    width: min(100%, 380px);
    padding: 28px 22px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-xl);
    background: var(--color-surface-2);
    text-align: center;
}

.ga-icon {
    display: grid;
    place-items: center;
    width: 64px;
    height: 64px;
    border-radius: var(--radius-full);
    background: var(--color-brand-dim);
    color: var(--color-brand);
}

.ga-title {
    font-size: var(--text-lg);
    font-weight: 800;
}

.ga-text {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.ga-left {
    color: #fdba74;
    font-size: var(--text-xs);
    font-weight: 800;
}

.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    margin: 12px 0 0;
    padding: 0;
    list-style: none;
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
}

.legend li {
    display: flex;
    align-items: center;
    gap: 6px;
}

.swatch {
    display: grid;
    place-items: center;
    width: 14px;
    height: 14px;
    border-radius: 50%;
}

.swatch--check {
    color: #ffffff;
    font-size: 9px;
    font-weight: 900;
}

.swatch--vendido {
    background: #cbd5e1;
}

.swatch--proceso {
    background: #ffffff;
    border: 1.5px dashed #94a3b8;
}

.venue-cart {
    display: none;
}

.ghost-btn {
    min-height: 44px;
    padding: 0 18px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    background: transparent;
    color: var(--color-text-primary);
    font-weight: 700;
    cursor: pointer;
}

.ghost-btn--solid {
    border: none;
    background: var(--color-brand);
    color: #06291d;
    font-weight: 800;
}

.mobile-bar {
    position: fixed;
    inset: auto 0 0;
    z-index: 55;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px calc(10px + env(safe-area-inset-bottom));
    border-top: 1px solid var(--color-border-strong);
    background: rgba(17, 17, 24, 0.97);
    backdrop-filter: blur(12px);
}

.mobile-cart {
    position: relative;
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    background: var(--color-surface-2);
    color: var(--color-brand);
    cursor: pointer;
}

.mobile-count {
    position: absolute;
    top: -6px;
    right: -6px;
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
    animation: bump 360ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.mobile-sum {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    border: none;
    background: none;
    color: inherit;
    text-align: left;
    cursor: pointer;
}

.mobile-sum span {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
}

.mobile-sum strong {
    font-size: var(--text-xl);
    font-weight: 900;
    font-variant-numeric: tabular-nums;
}

.mobile-go {
    min-height: 48px;
    padding: 0 22px;
    border: none;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
    color: #06291d;
    font-weight: 900;
    cursor: pointer;
}

.mobile-go:disabled {
    opacity: 0.6;
}

.sheet-wrap {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: flex-end;
    background: rgba(0, 0, 0, 0.6);
}

.sheet {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    max-height: 86dvh;
    padding: 22px 16px calc(16px + env(safe-area-inset-bottom));
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    background: var(--color-surface-1);
    box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.5);
}

.sheet-handle {
    position: absolute;
    top: 8px;
    left: 50%;
    width: 44px;
    height: 4px;
    border-radius: var(--radius-full);
    background: var(--color-border-strong);
    transform: translateX(-50%);
}

.sheet-close {
    position: absolute;
    top: 14px;
    right: 12px;
    z-index: 1;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: var(--radius-full);
    background: var(--color-surface-3);
    color: var(--color-text-secondary);
    cursor: pointer;
}

.sheet :deep(.cart-head) {
    padding-right: 44px;
}

.sheet-enter-active,
.sheet-leave-active {
    transition: background 260ms ease;
}

.sheet-enter-active .sheet,
.sheet-leave-active .sheet {
    transition: transform 320ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.sheet-enter-from,
.sheet-leave-to {
    background: rgba(0, 0, 0, 0);
}

.sheet-enter-from .sheet,
.sheet-leave-to .sheet {
    transform: translateY(100%);
}

.seat-tooltip {
    position: fixed;
    z-index: 75;
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 12px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-sm);
    background: #0a0a0f;
    box-shadow: var(--shadow-md);
    font-size: var(--text-xs);
    white-space: nowrap;
    pointer-events: none;
    transform: translate(-50%, calc(-100% - 14px));
}

.seat-tooltip strong {
    font-size: var(--text-sm);
}

.seat-tooltip span {
    color: var(--color-text-secondary);
    font-weight: 700;
}

.rules-text {
    color: var(--color-text-secondary);
    line-height: 1.6;
    white-space: pre-line;
}

.swap-enter-active,
.swap-leave-active {
    transition: opacity 220ms ease, transform 260ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.swap-enter-from {
    opacity: 0;
    transform: scale(0.96);
}

.swap-leave-to {
    opacity: 0;
    transform: scale(1.03);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 200ms ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 300ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(110%);
}

@keyframes pulse {
    50% {
        opacity: 0.45;
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes bump {
    from {
        transform: scale(0.4);
    }
}

@media (hover: none) {
    .seat-tooltip {
        display: none;
    }
}

@media (min-width: 768px) {
    .venue {
        padding-bottom: var(--space-10);
    }

    .map-panel {
        padding: 14px;
    }

    .map-gesture {
        display: none;
    }
}

@media (min-width: 1024px) {
    .process-steps {
        display: flex;
    }

    .venue-grid {
        grid-template-columns: 280px minmax(0, 1fr) 360px;
        align-items: start;
    }

    .venue-rail {
        position: sticky;
        top: calc(var(--header-height) + 84px);
        max-height: calc(100dvh - var(--header-height) - 104px);
        overflow-y: auto;
        padding-right: 4px;
    }

    .viewport {
        height: calc(100dvh - var(--header-height) - 230px);
        min-height: 440px;
    }

    .venue-cart {
        position: sticky;
        top: calc(var(--header-height) + 84px);
        display: block;
        height: calc(100dvh - var(--header-height) - 104px);
        max-height: 760px;
        padding: 18px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-xl);
        background: var(--color-surface-1);
    }

    .mobile-bar,
    .sheet-wrap {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .map-hint,
    .spinner,
    .mobile-count {
        animation: none;
    }

    .swap-enter-active,
    .swap-leave-active,
    .toast-enter-active,
    .toast-leave-active,
    .slide-up-enter-active,
    .slide-up-leave-active,
    .sheet-enter-active .sheet,
    .sheet-leave-active .sheet {
        transition: none;
    }
}
</style>
