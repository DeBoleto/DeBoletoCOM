<template>
    <Head :title="`Boletos · ${event.name}`" />

    <div class="page-layout">
        <SiteHeader @open-login="auth?.open('phone')" @open-register="auth?.open('register')" />

        <main id="main-content">
            <div class="timer-bar">
                <div class="container timer-bar-inner">
                    <div class="timer-event">
                        <p class="timer-event-name">{{ event.name }}</p>
                        <p class="timer-event-meta">{{ eventMeta }}</p>
                    </div>
                    <CheckoutSteps :current="0" class="timer-steps" />
                    <HoldTimer :deadline="deadline" label="Tiempo para elegir" @expired="expire" />
                </div>
            </div>

            <div class="container seat-page">
                <p v-if="notice" class="notice" :class="`notice--${notice.tone}`" role="status">{{ notice.text }}</p>

                <div class="seat-grid">
                    <div class="seat-main">
                        <template v-if="mode === 'map'">
                            <section class="map-card" aria-labelledby="zones-title">
                                <h2 id="zones-title">Elige una zona</h2>
                                <div v-if="stage" class="map-frame">
                                    <div class="viewport viewport--zones">
                                        <ZoneCanvas
                                            :sections="stage.sections"
                                            :active-key="activeKey"
                                            :zoom="zoneZoom"
                                            :canvas-size="stage.canvas"
                                            @select="selectSection"
                                        />
                                    </div>
                                    <ZoomControls v-model="zoneZoom" label="mapa de zonas" />
                                </div>
                                <p v-else class="empty-zone">El mapa de este recinto no está disponible.</p>
                                <ul v-if="zoneList.length" class="zone-chips" aria-label="Zonas">
                                    <li v-for="section in zoneList" :key="section.key">
                                        <button
                                            type="button"
                                            class="zone-chip"
                                            :class="{ 'is-active': section.key === activeKey }"
                                            :aria-pressed="section.key === activeKey"
                                            @click="selectSection(section.key)"
                                        >
                                            <span class="zone-chip-swatch" :style="{ background: section.areaColor || section.color }" />
                                            <span>{{ section.name }}</span>
                                            <span class="zone-chip-price">{{ money(sectionPrice(section)) }}</span>
                                        </button>
                                    </li>
                                </ul>
                            </section>

                            <section ref="seatPanel" class="map-card" aria-labelledby="seats-title">
                                <div class="seat-head">
                                    <h2 id="seats-title">{{ activeSection ? activeSection.name : "Asientos" }}</h2>
                                    <p v-if="info" class="seat-head-price">{{ money(info.price) }} <span>por boleto</span></p>
                                </div>

                                <p v-if="!activeSection" class="empty-zone">Toca una zona del mapa para ver sus lugares.</p>
                                <p v-else-if="loadError" class="empty-zone">{{ loadError }}</p>
                                <p v-else-if="!info" class="empty-zone">Cargando disponibilidad…</p>

                                <template v-else>
                                    <p v-if="info.promotion" class="promo-badge">
                                        Promoción {{ info.promotion.label }}: los boletos de cortesía se aplican en el pago.
                                    </p>
                                    <p v-if="info.volumeDiscount" class="promo-badge">
                                        Desde {{ info.volumeDiscount.from }} boletos, {{ money(info.volumeDiscount.amount) }} menos por boleto.
                                    </p>

                                    <div v-if="isGeneralAdmission" class="ga">
                                        <p class="ga-text">
                                            Zona sin asientos numerados.
                                            <template v-if="info.remaining > 0">Elige cuántos boletos quieres.</template>
                                            <template v-else>Ya no quedan boletos en esta zona.</template>
                                        </p>
                                        <Stepper
                                            v-if="info.remaining > 0"
                                            v-model="quantity"
                                            :max="Math.min(info.remaining, limits.maxTickets)"
                                            label="boletos"
                                        />
                                    </div>

                                    <div v-else class="map-frame">
                                        <div class="viewport viewport--seats">
                                            <SeatCanvas
                                                :section="sectionWithStatus"
                                                :zoom="seatZoom"
                                                :selected-keys="selectedKeys"
                                                @toggle="toggleSeat"
                                            />
                                        </div>
                                        <ZoomControls v-model="seatZoom" label="mapa de asientos" />
                                    </div>

                                    <ul v-if="!isGeneralAdmission" class="legend">
                                        <li><span class="swatch swatch--disponible" /> Disponible</li>
                                        <li><span class="swatch swatch--seleccionado" /> Tu selección</li>
                                        <li><span class="swatch swatch--proceso" /> En proceso de compra</li>
                                        <li><span class="swatch swatch--vendido" /> Vendido</li>
                                        <li><span class="swatch swatch--no-disponible" /> No disponible</li>
                                    </ul>
                                </template>
                            </section>
                        </template>

                        <section v-else class="list-card" aria-labelledby="localities-title">
                            <h2 id="localities-title">Elige tus boletos</h2>
                            <ul class="locality-list">
                                <li v-for="locality in localities" :key="locality.id" class="locality-row">
                                    <div class="locality-info">
                                        <p class="locality-name">{{ locality.name }}</p>
                                        <p class="locality-price">{{ money(locality.price) }}</p>
                                        <p v-if="locality.promotion" class="locality-promo">Promoción {{ locality.promotion.label }}</p>
                                        <button v-if="locality.rules" type="button" class="link-btn" @click="showRules(locality.name, locality.rules)">
                                            Ver reglas de la zona
                                        </button>
                                    </div>
                                    <Stepper
                                        v-if="locality.remaining > 0"
                                        :model-value="quantities[locality.id] ?? 0"
                                        :max="Math.min(locality.remaining, limits.maxTickets - totalTickets + (quantities[locality.id] ?? 0))"
                                        :label="`boletos de ${locality.name}`"
                                        @update:model-value="(value) => setQuantity(locality.id, value)"
                                    />
                                    <span v-else class="sold-out">Agotado</span>
                                </li>
                            </ul>
                        </section>
                    </div>

                    <aside class="summary" aria-labelledby="summary-title">
                        <h2 id="summary-title" class="summary-title">Tu selección</h2>

                        <p v-if="totalTickets === 0" class="summary-empty">
                            {{ mode === "map" ? "Aún no eliges asientos." : "Aún no eliges boletos." }}
                        </p>

                        <ul v-else class="summary-list">
                            <li v-for="ticket in tickets" :key="ticket.key" class="summary-item">
                                <div class="summary-item-head">
                                    <span class="summary-seat">{{ ticket.label }}</span>
                                    <span class="summary-zone">{{ ticket.zone }}</span>
                                </div>
                                <label v-if="ticket.specialsAllowed" class="summary-select">
                                    <span class="visually-hidden">Tipo de boleto para {{ ticket.label }}</span>
                                    <select v-model="specials[ticket.specialKey]">
                                        <option :value="null">Regular · {{ money(info?.price) }}</option>
                                        <option v-for="special in info.specials" :key="special.id" :value="special.id">
                                            {{ special.name }} · {{ money(special.price) }}
                                        </option>
                                    </select>
                                </label>
                                <label v-if="info?.extraChair && !ticket.generalAdmission" class="summary-check">
                                    <input v-model="extraChairs" type="checkbox" :value="ticket.seat" />
                                    Silla adicional (+{{ money(info.extraChair.price) }})
                                </label>
                            </li>
                        </ul>

                        <label v-if="parking && totalTickets > 0" class="addon">
                            <input v-model="wantsParking" type="checkbox" />
                            <span>
                                <strong>Agregar {{ parking.label.toLowerCase() }}</strong>
                                <span>{{ money(parking.price) }} más cargos</span>
                            </span>
                        </label>

                        <div v-if="totalTickets > 0" class="summary-total">
                            <span>{{ totalTickets }} {{ totalTickets === 1 ? "boleto" : "boletos" }}</span>
                            <strong>{{ money(estimate) }}</strong>
                        </div>
                        <p v-if="totalTickets > 0" class="summary-hint">Los cargos y promociones se calculan en el pago.</p>

                        <div class="summary-actions">
                            <button
                                type="button"
                                class="btn btn--primary"
                                :disabled="totalTickets === 0 || submitting"
                                @click="submit"
                            >
                                {{ submitting ? "Apartando…" : "Continuar al pago" }}
                            </button>
                            <button type="button" class="btn btn--ghost" :disabled="submitting" @click="cancel">
                                Cancelar compra
                            </button>
                        </div>
                    </aside>
                </div>
            </div>

            <div v-if="totalTickets > 0" class="mobile-cta">
                <div>
                    <p class="mobile-cta-count">{{ totalTickets }} {{ totalTickets === 1 ? "boleto" : "boletos" }}</p>
                    <p class="mobile-cta-total">{{ money(estimate) }}</p>
                </div>
                <button type="button" class="btn btn--primary" :disabled="submitting" @click="submit">
                    {{ submitting ? "Apartando…" : "Continuar" }}
                </button>
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
                <button type="button" class="btn btn--primary" @click="rules = null">Entendido</button>
            </template>
        </DialogModal>

        <AuthModals ref="auth" @authenticated="afterLogin" />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
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
import { getJson, money, postJson } from "@/components/checkout/api.js";

const props = defineProps({
    event: { type: Object, required: true },
    function: { type: Object, required: true },
    mode: { type: String, required: true },
    stage: { type: Object, default: null },
    localities: { type: Array, default: () => [] },
    limits: { type: Object, required: true },
    parking: { type: Object, default: null },
    cart: { type: Object, default: null },
});

const POLL_MS = 15000;
const page = usePage();
const auth = ref(null);
const seatPanel = ref(null);

const deadline = ref(Math.floor(Date.now() / 1000) + props.limits.holdMinutes * 60);
const zoneZoom = ref(1);
const seatZoom = ref(1);
const activeKey = ref(null);
const info = ref(null);
const loadError = ref("");
const selected = ref([]);
const quantity = ref(0);
const quantities = reactive({});
const specials = reactive({});
const extraChairs = ref([]);
const wantsParking = ref(Boolean(props.cart?.parking));
const submitting = ref(false);
const pendingSubmit = ref(false);
const rules = ref(null);
const localNotice = ref(null);
let pollId;
let requestId = 0;

const eventMeta = computed(() =>
    [props.event.venue, [props.function.date, props.function.time].filter(Boolean).join(" · ")].filter(Boolean).join(" · "),
);

const notice = computed(() => {
    if (localNotice.value) return localNotice.value;
    if (page.props.flash?.error) return { tone: "error", text: page.props.flash.error };
    if (page.props.flash?.success) return { tone: "success", text: page.props.flash.success };
    return null;
});

const zoneList = computed(() => (props.stage?.sections ?? []).filter((section) => section.available));
const activeSection = computed(() => props.stage?.sections.find((section) => section.key === activeKey.value) ?? null);
const isGeneralAdmission = computed(() => info.value?.capacity !== null && info.value?.capacity !== undefined);
const selectedKeys = computed(() => selected.value.map((seat) => `${activeKey.value}:${seat}`));

const sectionWithStatus = computed(() => {
    const statuses = info.value?.statuses ?? {};
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

const totalTickets = computed(() => {
    if (props.mode === "quantities") return Object.values(quantities).reduce((sum, value) => sum + value, 0);
    return isGeneralAdmission.value ? quantity.value : selected.value.length;
});

const tickets = computed(() => {
    if (props.mode === "quantities") {
        return props.localities.flatMap((locality) =>
            Array.from({ length: quantities[locality.id] ?? 0 }, (_, index) => ({
                key: `${locality.id}-${index}`,
                label: `Boleto ${index + 1}`,
                zone: locality.name,
                generalAdmission: true,
                specialsAllowed: false,
            })),
        );
    }

    const seats = isGeneralAdmission.value ? Array.from({ length: quantity.value }, () => null) : selected.value;

    return seats.map((seat, index) => ({
        key: seat ?? `ga-${index}`,
        seat,
        label: seat ?? `Boleto ${index + 1}`,
        specialKey: seat ?? `Boleto${index + 1}`,
        zone: activeSection.value?.name,
        generalAdmission: seat === null,
        specialsAllowed: Boolean(info.value?.specials?.length) && (info.value.specialsOnEveryTicket || index % 2 === 1),
    }));
});

const estimate = computed(() => {
    if (props.mode === "quantities") {
        return props.localities.reduce((sum, locality) => sum + (quantities[locality.id] ?? 0) * locality.price, 0);
    }

    const specialPrices = Object.fromEntries((info.value?.specials ?? []).map((special) => [special.id, special.price]));
    const base = tickets.value.reduce((sum, ticket) => {
        const specialId = ticket.specialsAllowed ? specials[ticket.specialKey] : null;
        return sum + (specialId ? specialPrices[specialId] ?? 0 : info.value?.price ?? 0);
    }, 0);

    return base + extraChairs.value.length * (info.value?.extraChair?.price ?? 0);
});

function sectionPrice(section) {
    return section.price?.discountPrice ?? section.price?.originalPrice ?? 0;
}

function flashNotice(text, tone = "error") {
    localNotice.value = { text, tone };
    window.setTimeout(() => {
        if (localNotice.value?.text === text) localNotice.value = null;
    }, 6000);
}

async function selectSection(key, { keepSelection = false } = {}) {
    const section = props.stage?.sections.find((candidate) => candidate.key === key);

    if (!section?.available) {
        flashNotice("Esa zona no está a la venta.");
        return;
    }

    if (key === activeKey.value && info.value) return;

    if (!keepSelection) await releaseSelection();

    activeKey.value = key;
    info.value = null;
    seatZoom.value = 1;
    await loadSection({ showRules: true });

    seatPanel.value?.scrollIntoView({ behavior: "smooth", block: "start" });
}

async function loadSection({ showRules = false } = {}) {
    if (!activeSection.value) return;

    const current = ++requestId;
    loadError.value = "";

    const { ok, data } = await getJson(
        route("checkout.section", { evento: props.event.id, funcion: props.function.id, seccion: activeSection.value.sectionId }),
    );

    if (current !== requestId) return;

    if (!ok) {
        loadError.value = "No pudimos cargar la disponibilidad. Intenta de nuevo.";
        return;
    }

    info.value = data;

    // Asientos elegidos que alguien más compró o apartó mientras tanto.
    const lost = selected.value.filter((seat) => ["vendido", "proceso", "no_disponible"].includes(data.statuses?.[seat]));
    if (lost.length) {
        selected.value = selected.value.filter((seat) => !lost.includes(seat));
        flashNotice(`Ya no está disponible: ${lost.join(", ")}.`);
    }

    if (showRules && data.rules) rules.value = { zone: data.name, text: data.rules };
}

async function toggleSeat(seat) {
    const index = selected.value.indexOf(seat.id);

    if (index >= 0) {
        selected.value.splice(index, 1);
        delete specials[seat.id];
        extraChairs.value = extraChairs.value.filter((id) => id !== seat.id);
        hold(seat.id, "liberar");
        return;
    }

    if (selected.value.length >= props.limits.maxTickets) {
        flashNotice(`Puedes elegir hasta ${props.limits.maxTickets} boletos por compra.`);
        return;
    }

    selected.value.push(seat.id);
    const { ok, data } = await hold(seat.id, "apartar");

    if (!ok && data?.status !== "local") {
        selected.value = selected.value.filter((id) => id !== seat.id);
        flashNotice(data?.message ?? "No pudimos apartar ese asiento.");
        loadSection();
    }
}

function hold(seat, action) {
    return postJson(route("checkout.hold", props.event.id), {
        funcion: props.function.id,
        seccion: activeSection.value.sectionId,
        asiento: seat,
        accion: action,
    });
}

async function releaseSelection() {
    const seats = [...selected.value];
    selected.value = [];
    quantity.value = 0;
    extraChairs.value = [];
    Object.keys(specials).forEach((key) => delete specials[key]);

    if (activeSection.value && !isGeneralAdmission.value) {
        await Promise.all(seats.map((seat) => hold(seat, "liberar")));
    }
}

function setQuantity(localityId, value) {
    quantities[localityId] = value;
}

function showRules(zone, text) {
    rules.value = { zone, text };
}

function payload() {
    if (props.mode === "quantities") {
        return { funcion: props.function.id, cantidades: { ...quantities }, estacionamiento: wantsParking.value };
    }

    const chosenSpecials = Object.fromEntries(
        tickets.value
            .filter((ticket) => ticket.specialsAllowed && specials[ticket.specialKey])
            .map((ticket) => [ticket.specialKey, specials[ticket.specialKey]]),
    );

    return {
        funcion: props.function.id,
        localidad: Number(activeSection.value?.localityId),
        seccion: activeSection.value?.sectionId,
        asientos: isGeneralAdmission.value ? [] : [...selected.value],
        cantidad: isGeneralAdmission.value ? quantity.value : null,
        especiales: chosenSpecials,
        sillas_extra: [...extraChairs.value],
        estacionamiento: wantsParking.value,
    };
}

function submit() {
    if (totalTickets.value === 0 || submitting.value) return;

    if (info.value?.promotion && totalTickets.value <= info.value.promotion.count) {
        flashNotice(`Elige al menos ${info.value.promotion.count + 1} boletos para aprovechar la promoción ${info.value.promotion.label}.`, "info");
    }

    submitting.value = true;

    router.post(route("checkout.selection", props.event.id), payload(), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (response) => {
            if (response.props.flash?.auth_required) {
                pendingSubmit.value = true;
                auth.value?.open("phone");
            }
        },
        onError: (errors) => flashNotice(Object.values(errors)[0] ?? "Revisa tu selección."),
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
    flashNotice("Se terminó el tiempo para elegir. Liberamos tus lugares.");
    router.post(route("checkout.cancel", props.event.id));
}

onMounted(async () => {
    const previous = props.cart;

    if (props.mode === "quantities") {
        Object.assign(quantities, previous?.quantities ?? {});
        return;
    }

    const section = previous?.sectionId ? props.stage?.sections.find((candidate) => candidate.sectionId === previous.sectionId) : null;

    if (section) {
        await selectSection(section.key, { keepSelection: true });
        selected.value = previous.seats.filter((seat) => info.value?.statuses?.[seat] === "mio" || !info.value?.statuses?.[seat]);
        quantity.value = previous.quantity ?? 0;
    } else if (zoneList.value.length === 1) {
        await selectSection(zoneList.value[0].key);
    }

    pollId = window.setInterval(() => {
        if (!submitting.value && activeSection.value && !document.hidden) loadSection();
    }, POLL_MS);
});

onUnmounted(() => window.clearInterval(pollId));
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

.timer-bar {
    position: sticky;
    top: var(--header-height);
    z-index: 40;
    background: rgba(17, 17, 24, 0.94);
    border-bottom: 1px solid var(--color-border);
    backdrop-filter: blur(12px);
}

.timer-bar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    min-height: 64px;
    padding-block: var(--space-3);
}

.timer-event {
    min-width: 0;
}

.timer-event-name {
    font-weight: 800;
    font-size: var(--text-lg);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.timer-event-meta {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.timer-steps {
    display: none;
}

.seat-page {
    padding-top: var(--space-5);
    padding-bottom: calc(var(--bottom-nav-height) + var(--space-10));
}

.notice {
    margin-bottom: var(--space-4);
    padding: 12px 16px;
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
    border: 1px solid var(--color-border-strong);
    background: var(--color-surface-2);
}

.notice--error {
    border-color: rgba(239, 68, 68, 0.4);
    background: rgba(239, 68, 68, 0.12);
    color: #fecaca;
}

.notice--success,
.notice--info {
    border-color: rgba(78, 203, 160, 0.4);
    background: var(--color-brand-dim);
}

.seat-grid {
    display: grid;
    gap: var(--space-5);
}

.map-card,
.list-card {
    background: #ffffff;
    color: #111827;
    border-radius: 12px;
    padding: 16px 16px 20px;
    margin-bottom: var(--space-5);
}

.map-card h2,
.list-card h2 {
    color: #111827;
    font-size: 1rem;
    font-weight: 800;
    margin-bottom: 10px;
}

.seat-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 12px;
}

.seat-head-price {
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.seat-head-price span {
    font-weight: 500;
    color: #6b7280;
    font-size: 0.85rem;
}

.map-frame {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: start;
}

.viewport {
    overflow: auto;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    -webkit-overflow-scrolling: touch;
}

.viewport--zones {
    height: 280px;
}

.viewport--seats {
    max-height: 380px;
}

.zone-chips {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    list-style: none;
    margin: 12px 0 0;
    padding: 0 0 4px;
}

.zone-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 40px;
    padding: 0 12px;
    border: 1px solid #d1d5db;
    border-radius: var(--radius-full);
    background: #ffffff;
    color: #111827;
    font-size: 0.88rem;
    white-space: nowrap;
    cursor: pointer;
}

.zone-chip.is-active {
    border-color: #16a34a;
    box-shadow: inset 0 0 0 1px #16a34a;
    font-weight: 700;
}

.zone-chip-swatch {
    width: 10px;
    height: 10px;
    border-radius: 2px;
}

.zone-chip-price {
    color: #4b5563;
    font-variant-numeric: tabular-nums;
}

.promo-badge {
    margin: 4px 0 12px;
    padding: 8px 12px;
    border-radius: 8px;
    background: #ecfdf5;
    color: #065f46;
    font-size: 0.88rem;
}

.empty-zone,
.ga-text {
    margin: 12px 0 16px;
    color: #4b5563;
}

.ga {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 18px;
    list-style: none;
    margin: 16px 0 0;
    padding: 0;
    color: #374151;
    font-size: 0.9rem;
}

.legend li {
    display: flex;
    align-items: center;
    gap: 8px;
}

.swatch {
    width: 14px;
    height: 14px;
    border-radius: 2px;
}

.swatch--disponible { background: #22c55e; }
.swatch--seleccionado { background: #166534; }
.swatch--no-disponible { background: #9ca3af; }
.swatch--vendido { background: #ef4444; }
.swatch--proceso { background: #f97316; }

.locality-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.locality-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 0;
    border-top: 1px solid #e5e7eb;
}

.locality-name {
    font-weight: 700;
}

.locality-price {
    font-variant-numeric: tabular-nums;
    color: #374151;
}

.locality-promo {
    color: #047857;
    font-size: 0.85rem;
}

.sold-out {
    color: #b91c1c;
    font-weight: 700;
}

.link-btn {
    padding: 0;
    border: none;
    background: none;
    color: #047857;
    font-size: 0.85rem;
    text-decoration: underline;
    cursor: pointer;
}

.summary {
    align-self: start;
    padding: 20px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    background: var(--color-surface-1);
}

.summary-title {
    font-size: var(--text-lg);
    font-weight: 800;
    margin-bottom: 12px;
}

.summary-empty,
.summary-hint {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.summary-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 10px;
    max-height: 340px;
    overflow-y: auto;
}

.summary-item {
    padding: 10px 12px;
    border-radius: var(--radius-md);
    background: var(--color-surface-2);
}

.summary-item-head {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.summary-seat {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.summary-zone {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.summary-select select {
    width: 100%;
    margin-top: 8px;
    min-height: 40px;
    padding: 0 10px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-sm);
    background: var(--color-surface-3);
    color: var(--color-text-primary);
}

.summary-check,
.addon {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    margin-top: 8px;
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
    cursor: pointer;
}

.addon {
    margin-top: 14px;
    padding: 12px;
    border: 1px dashed var(--color-border-strong);
    border-radius: var(--radius-md);
}

.addon span {
    display: flex;
    flex-direction: column;
}

.addon strong {
    color: var(--color-text-primary);
}

.summary-check input,
.addon input {
    margin-top: 3px;
    accent-color: var(--color-brand);
}

.summary-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--color-border);
}

.summary-total strong {
    font-size: var(--text-2xl);
    font-variant-numeric: tabular-nums;
}

.summary-actions {
    display: grid;
    gap: 8px;
    margin-top: 16px;
}

.btn {
    min-height: 48px;
    padding: 0 18px;
    border-radius: var(--radius-md);
    font-weight: 800;
    cursor: pointer;
    transition: filter var(--transition-fast);
}

.btn--primary {
    border: none;
    background: var(--color-brand);
    color: #06291d;
}

.btn--primary:disabled {
    background: var(--color-surface-3);
    color: var(--color-text-muted);
    cursor: not-allowed;
}

.btn--ghost {
    border: 1px solid var(--color-border-strong);
    background: transparent;
    color: var(--color-text-secondary);
}

.btn:hover:not(:disabled) {
    filter: brightness(1.08);
}

.btn:focus-visible,
.zone-chip:focus-visible,
.link-btn:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.mobile-cta {
    position: fixed;
    inset: auto 0 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
    border-top: 1px solid var(--color-border);
    background: rgba(17, 17, 24, 0.97);
}

.mobile-cta-count {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
}

.mobile-cta-total {
    font-size: var(--text-xl);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.rules-text {
    white-space: pre-line;
    color: var(--color-text-secondary);
    line-height: 1.6;
}

@media (min-width: 768px) {
    .viewport--zones {
        height: calc(100dvh - 22rem);
        min-height: 320px;
    }

    .viewport--seats {
        max-height: 460px;
    }

    .seat-page {
        padding-bottom: var(--space-10);
    }

    .mobile-cta {
        display: none;
    }
}

@media (min-width: 1024px) {
    .timer-steps {
        display: flex;
    }

    .seat-grid {
        grid-template-columns: minmax(0, 1fr) 360px;
    }

    .summary {
        position: sticky;
        top: calc(var(--header-height) + 88px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .btn {
        transition: none;
    }
}
</style>
