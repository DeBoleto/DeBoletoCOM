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

            <div v-if="mode === 'function'" class="container">
                <FunctionPicker :functions="functions" @choose="chooseFunction" />
            </div>

            <div v-else class="container process">
                <div class="process-grid" :class="{ 'process-grid--list': mode === 'quantities' }">
                    <aside v-if="mode === 'map'" class="process-rail">
                        <button v-if="functions.length > 1" type="button" class="change-date" @click="changeDate">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                            <span>{{ selectedFunction.date }} · {{ selectedFunction.time }}</span>
                            <span class="change-date-link">Cambiar</span>
                        </button>
                        <ZoneRail
                            :zones="zones"
                            :active-key="activeKey"
                            :hover-key="hoverKey"
                            @select="focusZone"
                            @hover="hoverKey = $event"
                            @show-all="showAllZones"
                        />
                    </aside>

                    <section v-if="mode === 'map'" ref="mapPanel" class="map-panel" aria-label="Mapa del recinto">
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
                            <p v-if="activeInfo" class="map-price">
                                <strong>{{ money(activeInfo.price) }}</strong> por boleto
                            </p>
                            <p v-else-if="!activeSection" class="map-hint">Toca una zona para ver sus lugares</p>
                        </div>

                        <Transition name="swap" mode="out-in">
                            <div v-if="!activeSection" key="zones" class="map-stage">
                                <div v-if="stage" class="viewport viewport--zones">
                                    <ZoneCanvas
                                        :sections="stage.sections"
                                        :active-key="activeKey"
                                        :hover-key="hoverKey"
                                        :disabled-keys="disabledKeys"
                                        :badges="badges"
                                        :zoom="zoneZoom"
                                        :canvas-size="stage.canvas"
                                        :auto-height="false"
                                        @select="focusZone"
                                        @hover="hoverKey = $event"
                                    />
                                </div>
                                <p v-else class="map-empty">El mapa de este recinto no está disponible.</p>
                                <ZoomControls v-if="stage" v-model="zoneZoom" label="mapa de zonas" class="map-zoom" />
                            </div>

                            <div v-else :key="`seats-${activeKey}`" class="map-stage">
                                <div v-if="loadError" class="map-empty">
                                    <p>{{ loadError }}</p>
                                    <button type="button" class="ghost-btn" @click="loadSection(activeKey)">Reintentar</button>
                                </div>
                                <div v-else-if="!activeInfo" class="map-loading" aria-label="Cargando disponibilidad">
                                    <span v-for="index in 18" :key="index" class="skeleton-seat" :style="{ animationDelay: `${index * 40}ms` }" />
                                </div>

                                <template v-else>
                                    <div v-if="activeInfo.promotion || activeInfo.volumeDiscount" class="promo-strip">
                                        <span v-if="activeInfo.promotion" class="promo-chip">🎉 Promoción {{ activeInfo.promotion.label }} en esta zona</span>
                                        <span v-if="activeInfo.volumeDiscount" class="promo-chip">
                                            Desde {{ activeInfo.volumeDiscount.from }} boletos, {{ money(activeInfo.volumeDiscount.amount) }} menos c/u
                                        </span>
                                    </div>

                                    <div v-if="activeIsGeneral" class="ga">
                                        <div class="ga-card">
                                            <span class="ga-icon" aria-hidden="true">
                                                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                                            </span>
                                            <p class="ga-title">{{ activeSection.name }} · Acceso general</p>
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
                                            <p v-if="activeInfo.remaining > 0 && activeInfo.remaining <= 20" class="ga-left">
                                                ¡Solo quedan {{ activeInfo.remaining }}!
                                            </p>
                                        </div>
                                    </div>

                                    <div v-else class="viewport viewport--seats">
                                        <SeatCanvas
                                            :section="sectionWithStatus"
                                            :zoom="seatZoom"
                                            :selected-keys="selectedKeys"
                                            selected-color="#4ecba0"
                                            @toggle="toggleSeat"
                                            @hover="hoverSeat = $event"
                                        />
                                    </div>
                                    <ZoomControls v-if="!activeIsGeneral" v-model="seatZoom" label="mapa de asientos" class="map-zoom" />

                                    <button
                                        v-if="stage && !activeIsGeneral"
                                        type="button"
                                        class="minimap"
                                        aria-label="Volver al mapa completo"
                                        @click="showAllZones"
                                    >
                                        <span class="viewport minimap-viewport">
                                            <ZoneCanvas
                                                :sections="stage.sections"
                                                :active-key="activeKey"
                                                :disabled-keys="disabledKeys"
                                                :canvas-size="stage.canvas"
                                                :auto-height="false"
                                            />
                                        </span>
                                        <span class="minimap-label">Mapa completo</span>
                                    </button>
                                </template>
                            </div>
                        </Transition>

                        <ul v-if="activeInfo && !activeIsGeneral" class="legend">
                            <li><span class="swatch swatch--disponible" /> Disponible</li>
                            <li><span class="swatch swatch--seleccionado" /> Tu selección</li>
                            <li><span class="swatch swatch--proceso" /> En proceso</li>
                            <li><span class="swatch swatch--vendido" /> Vendido</li>
                            <li><span class="swatch swatch--no-disponible" /> No disponible</li>
                        </ul>
                    </section>

                    <section v-else class="list-panel" aria-labelledby="localities-title">
                        <div class="list-head">
                            <h2 id="localities-title">Elige tus boletos</h2>
                            <button v-if="functions.length > 1" type="button" class="change-date" @click="changeDate">
                                <span>{{ selectedFunction.date }} · {{ selectedFunction.time }}</span>
                                <span class="change-date-link">Cambiar</span>
                            </button>
                        </div>
                        <ul class="locality-list">
                            <li
                                v-for="(locality, index) in localities"
                                :key="locality.id"
                                class="locality"
                                :class="{ 'is-picked': (quantities[locality.id] ?? 0) > 0, 'is-out': locality.remaining === 0 }"
                                :style="{ '--accent': palette[index % palette.length], '--delay': `${index * 50}ms` }"
                            >
                                <span class="locality-accent" aria-hidden="true" />
                                <div class="locality-info">
                                    <p class="locality-name">{{ locality.name }}</p>
                                    <p class="locality-price">{{ money(locality.price) }}</p>
                                    <p v-if="locality.promotion" class="locality-promo">Promoción {{ locality.promotion.label }}</p>
                                    <p v-if="locality.remaining > 0 && locality.remaining <= 20" class="locality-left">Quedan {{ locality.remaining }}</p>
                                    <button v-if="locality.rules" type="button" class="link-btn" @click="rules = { zone: locality.name, text: locality.rules }">
                                        Ver reglas de la zona
                                    </button>
                                </div>
                                <Stepper
                                    v-if="locality.remaining > 0"
                                    :model-value="quantities[locality.id] ?? 0"
                                    :max="Math.min(locality.remaining, limits.maxTickets - totalTickets + (quantities[locality.id] ?? 0))"
                                    :label="`boletos de ${locality.name}`"
                                    @update:model-value="(value) => (quantities[locality.id] = value)"
                                />
                                <span v-else class="sold-out">Agotado</span>
                            </li>
                        </ul>
                    </section>

                    <aside class="process-cart" aria-labelledby="cart-title">
                        <CartPanel
                            :groups="groups"
                            :count="totalTickets"
                            :total="estimate"
                            :max-tickets="limits.maxTickets"
                            :parking="parking"
                            :parking-selected="wantsParking"
                            :submitting="submitting"
                            :empty-hint="mode === 'map' ? 'Toca una zona del mapa para elegir tus lugares. Puedes combinar varias zonas.' : 'Elige cuántos boletos quieres de cada zona.'"
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
                <div v-if="mode !== 'function' && totalTickets > 0" class="mobile-bar">
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
                <div v-if="sheetOpen" class="sheet-wrap" @click.self="sheetOpen = false" @keydown.esc="sheetOpen = false">
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
                v-if="hoverSeat && tooltip"
                class="seat-tooltip"
                :style="{ left: `${hoverSeat.clientX}px`, top: `${hoverSeat.clientY}px` }"
                aria-hidden="true"
            >
                <strong>{{ tooltip.title }}</strong>
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
import ZoneCanvas from "@/components/seat-map/ZoneCanvas.vue";
import SeatCanvas from "@/components/seat-map/SeatCanvas.vue";
import SiteHeader from "@/components/SiteHeader.vue";
import SiteFooter from "@/components/SiteFooter.vue";
import MobileBottomNav from "@/components/MobileBottomNav.vue";
import DialogModal from "@/components/DialogModal.vue";
import CheckoutSteps from "@/components/checkout/CheckoutSteps.vue";
import HoldTimer from "@/components/checkout/HoldTimer.vue";
import AuthModals from "@/components/checkout/AuthModals.vue";
import Stepper from "@/components/checkout/Stepper.vue";
import ZoomControls from "@/components/checkout/ZoomControls.vue";
import FunctionPicker from "@/components/checkout/process/FunctionPicker.vue";
import ZoneRail from "@/components/checkout/process/ZoneRail.vue";
import CartPanel from "@/components/checkout/process/CartPanel.vue";
import { getJson, money, postJson } from "@/components/checkout/api.js";

const props = defineProps({
    event: { type: Object, required: true },
    functions: { type: Array, default: () => [] },
    function: { type: Object, default: null },
    mode: { type: String, required: true },
    stage: { type: Object, default: null },
    localities: { type: Array, default: () => [] },
    limits: { type: Object, required: true },
    parking: { type: Object, default: null },
    cart: { type: Object, default: null },
});

const POLL_MS = 15000;
const STATUS_LABEL = { disponible: "Disponible", vendido: "Vendido", en_proceso: "En proceso de compra", no_disponible: "No disponible" };
const palette = ["#4ecba0", "#3b82f6", "#e535ab", "#f97316", "#a855f7", "#06b6d4", "#eab308"];

const page = usePage();
const auth = ref(null);
const mapPanel = ref(null);

const activeKey = ref(null);
const hoverKey = ref(null);
const hoverSeat = ref(null);
const zoneZoom = ref(1);
const seatZoom = ref(1);
const picks = reactive({});
const infos = reactive({});
const quantities = reactive({});
const loadError = ref("");
const wantsParking = ref(Boolean(props.cart?.parking));
const submitting = ref(false);
const pendingSubmit = ref(false);
const rules = ref(null);
const sheetOpen = ref(false);
const deadline = ref(null);
const toasts = ref([]);
const rulesShown = new Set();
let pollId;
let toastId = 0;
const requestIds = {};

const selectedFunction = computed(() => props.function);

const eventMeta = computed(() =>
    [props.event.venue, props.function ? [props.function.date, props.function.time].filter(Boolean).join(" · ") : null]
        .filter(Boolean)
        .join(" · "),
);

const sections = computed(() => props.stage?.sections ?? []);
const activeSection = computed(() => sections.value.find((section) => section.key === activeKey.value) ?? null);
const activeInfo = computed(() => (activeKey.value === null ? null : infos[activeKey.value] ?? null));
const activeIsGeneral = computed(() => isGeneral(activeKey.value));
const disabledKeys = computed(() => sections.value.filter((section) => !section.available).map((section) => section.key));

const zones = computed(() =>
    sections.value
        .map((section) => ({
            key: section.key,
            name: section.name,
            color: zoneColor(section),
            available: section.available,
            price: sectionPrice(section),
            originalPrice: section.price?.originalPrice ?? null,
            count: countFor(section.key),
        }))
        .sort((a, b) => Number(b.available) - Number(a.available) || b.price - a.price),
);

const badges = computed(() => Object.fromEntries(sections.value.map((section) => [section.key, countFor(section.key)])));

const selectedKeys = computed(() => readPick(activeKey.value).seats.map((seat) => `${activeKey.value}:${seat}`));

const seatRows = computed(() => {
    const rows = {};
    for (const row of activeSection.value?.rows ?? []) {
        for (const seat of row.seats) rows[seat.id] = row.fila;
    }
    return rows;
});

const sectionWithStatus = computed(() => {
    const statuses = activeInfo.value?.statuses ?? {};
    const mapStatus = { vendido: "vendido", no_disponible: "no_disponible", proceso: "en_proceso", mio: "disponible" };

    return {
        ...activeSection.value,
        rows: activeSection.value.rows.map((row) => ({
            ...row,
            seats: row.seats.map((seat) => ({
                ...seat,
                status: seat.selectable === false ? "no_disponible" : (mapStatus[statuses[seat.id]] ?? "disponible"),
            })),
        })),
    };
});

const tooltip = computed(() => {
    const seat = hoverSeat.value?.seat;
    if (!seat || !activeInfo.value) return null;
    const row = seatRows.value[seat.id];
    const status = selectedKeys.value.includes(`${activeKey.value}:${seat.id}`) ? "Tu selección" : STATUS_LABEL[seat.status] ?? seat.status;

    return {
        title: `Asiento ${seat.id}${row ? ` · Fila ${row}` : ""}`,
        detail: seat.status === "disponible" ? `${money(activeInfo.value.price)} · ${status}` : status,
    };
});

const totalTickets = computed(() => {
    if (props.mode === "quantities") return Object.values(quantities).reduce((sum, value) => sum + value, 0);
    return sections.value.reduce((sum, section) => sum + countFor(section.key), 0);
});

const groups = computed(() => (props.mode === "quantities" ? quantityGroups() : mapGroups()));
const estimate = computed(() => groups.value.reduce((sum, group) => sum + group.tickets.reduce((acc, ticket) => acc + ticket.price + (ticket.extraChair ? group.extraChairPrice : 0), 0), 0));

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

function isGeneral(key) {
    if (key === null) return false;
    const info = infos[key];
    if (info) return info.capacity !== null && info.capacity !== undefined;
    return sections.value.find((section) => section.key === key)?.capacity != null;
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
    return section.areaColor || section.color;
}

function mapGroups() {
    return sections.value
        .filter((section) => countFor(section.key) > 0)
        .map((section) => {
            const key = section.key;
            const pick = readPick(key);
            const info = infos[key];
            const general = isGeneral(key);
            const unitPrice = info?.price ?? sectionPrice(section);
            const specials = info?.specials ?? [];
            const specialPrices = Object.fromEntries(specials.map((special) => [special.id, special.price]));
            const seats = general ? Array.from({ length: pick.quantity }, () => null) : pick.seats;
            const rows = Object.fromEntries(section.rows.flatMap((row) => row.seats.map((seat) => [seat.id, row.fila])));

            return {
                key,
                name: section.name,
                color: zoneColor(section),
                unitPrice,
                specials,
                extraChairPrice: info?.extraChair?.price ?? 0,
                tickets: seats.map((seat, index) => {
                    const specialKey = seat ?? `Boleto${index + 1}`;
                    const specialsAllowed = specials.length > 0 && (info.specialsOnEveryTicket || index % 2 === 1);
                    const special = specialsAllowed ? pick.specials[specialKey] ?? null : null;

                    return {
                        id: `${key}:${seat ?? `ga-${index}`}`,
                        seat,
                        index,
                        label: seat ?? `Boleto ${index + 1}`,
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
        });
}

function quantityGroups() {
    return props.localities
        .map((locality, index) => ({ locality, index }))
        .filter(({ locality }) => (quantities[locality.id] ?? 0) > 0)
        .map(({ locality, index }) => ({
            key: String(locality.id),
            name: locality.name,
            color: palette[index % palette.length],
            unitPrice: locality.price,
            specials: [],
            extraChairPrice: 0,
            tickets: Array.from({ length: quantities[locality.id] }, (_, ticket) => ({
                id: `${locality.id}-${ticket}`,
                seat: null,
                index: ticket,
                label: `Boleto ${ticket + 1}`,
                sub: "",
                specialsAllowed: false,
                price: locality.price,
                extraChairAvailable: false,
                extraChair: false,
            })),
        }));
}

function notify(text, tone = "error") {
    const id = ++toastId;
    toasts.value.push({ id, text, tone });
    window.setTimeout(() => {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }, 5500);
}

function chooseFunction(fn) {
    router.get(route("checkout.process", props.event.slug), { function: fn.id });
}

function changeDate() {
    if (totalTickets.value > 0 && !window.confirm("Si cambias de fecha se liberan los lugares que elegiste. ¿Continuar?")) return;
    clearAll();
    router.get(route("checkout.process", props.event.slug));
}

async function focusZone(key) {
    const section = sections.value.find((candidate) => candidate.key === key);

    if (!section?.available) {
        notify("Esa zona no está a la venta.");
        return;
    }

    hoverSeat.value = null;
    loadError.value = "";
    activeKey.value = key;
    seatZoom.value = 1;

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
    if (props.mode === "map") focusZone(group.key);
}

async function loadSection(key) {
    const section = sections.value.find((candidate) => candidate.key === key);
    if (!section || !props.function) return;

    const current = (requestIds[key] = (requestIds[key] ?? 0) + 1);

    const { ok, data } = await getJson(
        route("checkout.section", { evento: props.event.id, funcion: props.function.id, seccion: section.sectionId }),
    );

    if (current !== requestIds[key]) return;

    if (!ok) {
        if (key === activeKey.value) loadError.value = "No pudimos cargar la disponibilidad. Intenta de nuevo.";
        return;
    }

    if (key === activeKey.value) loadError.value = "";
    infos[key] = data;

    const pick = pickFor(key);
    const lost = pick.seats.filter((seat) => ["vendido", "proceso", "no_disponible"].includes(data.statuses?.[seat]));

    if (lost.length) {
        pick.seats = pick.seats.filter((seat) => !lost.includes(seat));
        notify(`Ya no está disponible en ${section.name}: ${lost.join(", ")}.`);
    }

    if (data.capacity !== null && data.capacity !== undefined) {
        pick.quantity = Math.min(pick.quantity, data.remaining ?? 0);
    }
}

function gaMax(key) {
    const info = infos[key];
    const current = readPick(key).quantity;
    return Math.max(0, Math.min(info?.remaining ?? 0, props.limits.maxTickets - totalTickets.value + current));
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
    const key = activeKey.value;
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
        notify(data?.message ?? "No pudimos apartar ese asiento.");
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
    const section = sections.value.find((candidate) => candidate.key === key);

    return postJson(route("checkout.hold", props.event.id), {
        funcion: props.function.id,
        seccion: section.sectionId,
        asiento: seat,
        accion: action,
    });
}

function removeTicket(group, ticket) {
    if (props.mode === "quantities") {
        quantities[group.key] = Math.max(0, (quantities[group.key] ?? 0) - 1);
        return;
    }

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
    Object.keys(quantities).forEach((key) => delete quantities[key]);
}

function payload() {
    if (props.mode === "quantities") {
        return { funcion: props.function.id, cantidades: { ...quantities }, estacionamiento: wantsParking.value };
    }

    return {
        funcion: props.function.id,
        estacionamiento: wantsParking.value,
        secciones: groups.value.map((group) => {
            const section = sections.value.find((candidate) => candidate.key === group.key);
            const pick = readPick(group.key);
            const general = isGeneral(group.key);

            return {
                localidad: Number(section.localityId),
                seccion: section.sectionId,
                asientos: general ? [] : [...pick.seats],
                cantidad: general ? pick.quantity : null,
                especiales: Object.fromEntries(
                    group.tickets.filter((ticket) => ticket.special).map((ticket) => [ticket.specialKey, ticket.special]),
                ),
                sillas_extra: [...pick.extraChairs],
            };
        }),
    };
}

function submit() {
    if (totalTickets.value === 0 || submitting.value) return;

    for (const group of groups.value) {
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
    if (submitting.value || document.hidden || props.mode !== "map") return;

    const keys = new Set(sections.value.filter((section) => countFor(section.key) > 0).map((section) => section.key));
    if (activeKey.value !== null) keys.add(activeKey.value);

    for (const key of keys) await loadSection(key);
}

function onKey(event) {
    if (event.key === "Escape") {
        if (sheetOpen.value) sheetOpen.value = false;
        else if (activeKey.value !== null && rules.value === null) showAllZones();
    }
}

onMounted(async () => {
    window.addEventListener("keydown", onKey);

    if (props.mode === "quantities") {
        Object.assign(quantities, props.cart?.quantities ?? {});
        return;
    }

    if (props.mode !== "map") return;

    const restored = (props.cart?.sections ?? [])
        .map((saved) => ({ saved, section: sections.value.find((candidate) => candidate.sectionId === saved.sectionId) }))
        .filter(({ section }) => section?.available);

    for (const { saved, section } of restored) {
        await loadSection(section.key);
        const statuses = infos[section.key]?.statuses ?? {};
        const pick = pickFor(section.key);
        pick.seats = saved.seats.filter((seat) => statuses[seat] === "mio" || !statuses[seat]);
        pick.quantity = Math.min(saved.quantity ?? 0, infos[section.key]?.remaining ?? saved.quantity ?? 0);
    }

    const available = sections.value.filter((section) => section.available);
    if (restored.length === 1) focusZone(restored[0].section.key);
    else if (available.length === 1) focusZone(available[0].key);

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
    min-height: 68px;
    padding-block: 10px;
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
    width: 48px;
    height: 48px;
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
    top: calc(var(--header-height) + 80px);
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
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border-strong);
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

.process {
    padding-top: var(--space-4);
    padding-bottom: calc(var(--bottom-nav-height) + var(--space-10));
}

.process-grid {
    display: grid;
    gap: var(--space-4);
}

.change-date {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface-1);
    color: var(--color-text-primary);
    font-size: var(--text-sm);
    font-weight: 600;
    text-align: left;
    cursor: pointer;
}

.change-date svg {
    color: var(--color-brand);
    flex-shrink: 0;
}

.change-date-link {
    margin-left: auto;
    color: var(--color-brand);
    font-size: var(--text-xs);
    font-weight: 800;
}

.map-panel {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    background:
        radial-gradient(120% 80% at 50% 0%, rgba(78, 203, 160, 0.1), transparent 60%),
        var(--color-surface-1);
    scroll-margin-top: calc(var(--header-height) + 80px);
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
    padding: 6px 10px;
    border: none;
    border-radius: var(--radius-full);
    background: var(--color-surface-3);
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
    font-weight: 700;
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

.map-stage {
    position: relative;
}

.viewport {
    display: block;
    overflow: auto;
    border-radius: var(--radius-lg);
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
}

.viewport--zones {
    height: clamp(300px, 52vh, 620px);
}

.viewport--seats {
    height: clamp(320px, 56vh, 640px);
    background: #f8fafc;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
}

.map-zoom {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 2;
}

.minimap {
    position: absolute;
    left: 10px;
    bottom: 10px;
    z-index: 2;
    display: flex;
    flex-direction: column;
    padding: 6px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    background: rgba(10, 10, 15, 0.86);
    color: var(--color-text-secondary);
    cursor: pointer;
    transition: transform var(--transition-fast), border-color var(--transition-fast);
}

.minimap:hover {
    transform: scale(1.04);
    border-color: var(--color-brand);
}

.minimap-viewport {
    width: 120px;
    height: 84px;
    overflow: hidden;
    pointer-events: none;
}

.minimap-label {
    margin-top: 4px;
    font-size: 0.65rem;
    font-weight: 700;
    text-align: center;
}

.map-empty {
    display: grid;
    place-items: center;
    gap: 12px;
    min-height: 260px;
    color: var(--color-text-secondary);
    text-align: center;
}

.map-loading {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    padding: 40px 12%;
    min-height: 320px;
    align-content: center;
}

.skeleton-seat {
    aspect-ratio: 3 / 2;
    border-radius: 6px;
    background: var(--color-surface-3);
    animation: pulse 1.2s ease-in-out infinite;
}

.promo-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
}

.promo-chip {
    padding: 6px 12px;
    border-radius: var(--radius-full);
    background: linear-gradient(90deg, rgba(229, 53, 171, 0.25), rgba(249, 115, 22, 0.25));
    border: 1px solid rgba(229, 53, 171, 0.45);
    font-size: var(--text-xs);
    font-weight: 700;
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
    animation: rise 380ms cubic-bezier(0.2, 0.8, 0.2, 1);
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
    font-weight: 800;
    font-size: var(--text-lg);
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
    list-style: none;
    margin: 12px 0 0;
    padding: 0;
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
}

.legend li {
    display: flex;
    align-items: center;
    gap: 6px;
}

.swatch {
    width: 12px;
    height: 12px;
    border-radius: 3px;
}

.swatch--disponible { background: #22c55e; }
.swatch--seleccionado { background: #4ecba0; box-shadow: 0 0 0 2px rgba(78, 203, 160, 0.4); }
.swatch--no-disponible { background: #9ca3af; }
.swatch--vendido { background: #ef4444; }
.swatch--proceso { background: #f97316; }

.list-panel {
    padding: 18px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    background: var(--color-surface-1);
}

.list-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    margin-bottom: 12px;
}

.list-head h2 {
    font-size: var(--text-xl);
    font-weight: 800;
}

.list-head .change-date {
    width: auto;
    margin: 0;
}

.locality-list {
    display: grid;
    gap: 10px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.locality {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 14px 14px 22px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-lg);
    background: var(--color-surface-2);
    overflow: hidden;
    animation: rise 380ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
    animation-delay: var(--delay);
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}

.locality.is-picked {
    border-color: var(--accent);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35), inset 0 0 0 1px var(--accent);
}

.locality.is-out {
    opacity: 0.5;
}

.locality-accent {
    position: absolute;
    inset: 0 auto 0 0;
    width: 6px;
    background: var(--accent);
}

.locality-name {
    font-weight: 800;
}

.locality-price {
    color: var(--color-brand);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.locality-promo {
    color: #f9a8d4;
    font-size: var(--text-xs);
    font-weight: 700;
}

.locality-left {
    color: #fdba74;
    font-size: var(--text-xs);
    font-weight: 700;
}

.sold-out {
    color: #fca5a5;
    font-weight: 800;
}

.link-btn {
    padding: 0;
    border: none;
    background: none;
    color: var(--color-brand);
    font-size: var(--text-xs);
    text-decoration: underline;
    cursor: pointer;
}

.process-cart {
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
    border-radius: var(--radius-sm);
    background: #0a0a0f;
    border: 1px solid var(--color-border-strong);
    box-shadow: var(--shadow-md);
    font-size: var(--text-xs);
    pointer-events: none;
    transform: translate(-50%, calc(-100% - 14px));
    white-space: nowrap;
}

.seat-tooltip strong {
    font-size: var(--text-sm);
}

.seat-tooltip span {
    color: var(--color-brand);
    font-weight: 700;
}

.rules-text {
    white-space: pre-line;
    color: var(--color-text-secondary);
    line-height: 1.6;
}

.swap-enter-active,
.swap-leave-active {
    transition: opacity 220ms ease, transform 260ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.swap-enter-from {
    opacity: 0;
    transform: scale(0.94);
}

.swap-leave-to {
    opacity: 0;
    transform: scale(1.04);
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

@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@keyframes bump {
    from {
        transform: scale(0.4);
    }
}

@media (min-width: 768px) {
    .process {
        padding-bottom: var(--space-10);
    }
}

@media (min-width: 1024px) {
    .process-steps {
        display: flex;
    }

    .process-grid {
        grid-template-columns: 260px minmax(0, 1fr) 360px;
        align-items: start;
    }

    .process-grid--list {
        grid-template-columns: minmax(0, 1fr) 380px;
    }

    .process-rail {
        position: sticky;
        top: calc(var(--header-height) + 88px);
        max-height: calc(100dvh - var(--header-height) - 110px);
        overflow-y: auto;
        padding-right: 4px;
    }

    .viewport--zones,
    .viewport--seats {
        height: calc(100dvh - var(--header-height) - 230px);
        min-height: 420px;
    }

    .process-cart {
        position: sticky;
        top: calc(var(--header-height) + 88px);
        display: block;
        height: calc(100dvh - var(--header-height) - 110px);
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
    .skeleton-seat,
    .ga-card,
    .locality,
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
    .sheet-leave-active .sheet,
    .minimap {
        transition: none;
    }
}
</style>
