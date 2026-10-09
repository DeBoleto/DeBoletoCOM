<template>
    <Head :title="`Asientos · ${event.name}`" />

    <div class="page-layout">
        <SiteHeader @open-login="openLogin" @open-register="openRegister" />

        <main id="main-content">
            <div class="timer-bar">
                <div class="container timer-bar-inner">
                    <div class="timer-event">
                        <p class="timer-event-name">{{ event.name }}</p>
                        <p v-if="eventMeta" class="timer-event-meta">
                            {{ eventMeta }}
                        </p>
                    </div>
                    <div
                        class="timer"
                        :class="{ 'timer--urgent': remaining > 0 && remaining <= 60 }"
                        role="timer"
                        aria-label="Tiempo restante para seleccionar asientos"
                    >
                        <span class="timer-label">Tiempo restante</span>
                        <span class="timer-value">{{ clock }}</span>
                    </div>
                </div>
            </div>

            <div class="container seat-page">
                <section class="map-card" aria-label="Selección de zona">
                    <h2>Seleccione una zona</h2>
                    <div class="map-frame">
                        <div class="viewport viewport--zones">
                            <ZoneCanvas
                                :sections="stage.sections"
                                :active-key="activeKey"
                                :zoom="zoneZoom"
                                :canvas-size="stage.canvas"
                                @select="selectSection"
                            />
                        </div>
                        <div class="zoom-controls" aria-label="Zoom del mapa de zonas">
                            <button
                                type="button"
                                aria-label="Aumentar zoom del mapa de zonas"
                                :disabled="zoneZoom >= MAX_ZOOM"
                                @click="changeZoom('zone', ZOOM_STEP)"
                            >
                                +
                            </button>
                            <button
                                type="button"
                                aria-label="Disminuir zoom del mapa de zonas"
                                :disabled="zoneZoom <= MIN_ZOOM"
                                @click="changeZoom('zone', -ZOOM_STEP)"
                            >
                                −
                            </button>
                            <button
                                type="button"
                                aria-label="Restablecer zoom del mapa de zonas"
                                @click="resetZoom('zone')"
                            >
                                ·
                            </button>
                        </div>
                    </div>
                </section>

                <section
                    ref="seatPanel"
                    class="map-card"
                    aria-label="Selección de asientos"
                >
                    <h2>Seleccione los asientos deseados.</h2>
                    <p v-if="activeSection" class="zone-subtitle">
                        {{ activeSection.name }}
                        <span class="zone-price">{{ zonePriceLabel(activeSection) }}</span>
                    </p>

                    <div v-if="activeSection && activeSection.rows.length" class="map-frame">
                        <div class="viewport viewport--seats">
                            <SeatCanvas
                                :section="activeSection"
                                :zoom="seatZoom"
                                :selected-keys="selectedKeys"
                                @toggle="toggleSeat(activeSection, $event)"
                            />
                        </div>
                        <div class="zoom-controls" aria-label="Zoom del mapa de asientos">
                            <button
                                type="button"
                                aria-label="Aumentar zoom del mapa de asientos"
                                :disabled="seatZoom >= MAX_ZOOM"
                                @click="changeZoom('seat', ZOOM_STEP)"
                            >
                                +
                            </button>
                            <button
                                type="button"
                                aria-label="Disminuir zoom del mapa de asientos"
                                :disabled="seatZoom <= MIN_ZOOM"
                                @click="changeZoom('seat', -ZOOM_STEP)"
                            >
                                −
                            </button>
                            <button
                                type="button"
                                aria-label="Restablecer zoom del mapa de asientos"
                                @click="resetZoom('seat')"
                            >
                                ·
                            </button>
                        </div>
                    </div>
                    <p v-else class="empty-zone">
                        Esta zona no tiene asientos numerados.
                    </p>

                    <p v-if="selectedDetails.length" class="selection-summary">
                        Seleccionados: {{ selectedDetails.map((item) => item.seatId).join(", ") }}
                    </p>

                    <ul class="legend">
                        <li><span class="swatch swatch--disponible" /> Disponible</li>
                        <li><span class="swatch swatch--no-disponible" /> No disponible</li>
                        <li><span class="swatch swatch--vendido" /> Vendido</li>
                        <li><span class="swatch swatch--proceso" /> En proceso de compra</li>
                    </ul>

                    <div class="actions">
                        <button type="button" class="action-btn action-btn--cancel" @click="goHome">
                            Cancelar
                        </button>
                        <button type="button" class="action-btn action-btn--back" @click="goBack">
                            Anterior
                        </button>
                        <button
                            type="button"
                            class="action-btn action-btn--next"
                            :disabled="!selectedDetails.length"
                            @click="openPayment"
                        >
                            Siguiente
                        </button>
                    </div>
                </section>
            </div>
        </main>

        <SiteFooter />
        <MobileBottomNav @open-login="openLogin" />

        <DialogModal :show="showPayment" max-width="lg" @close="closePayment">
            <template #title>Se procederá al pago</template>
            <template #content>
                <p class="pay-lead">
                    Tu selección está lista. El siguiente paso es el pago.
                </p>
                <ul class="pay-list">
                    <li v-for="item in selectedDetails" :key="item.key">
                        <span>{{ item.zone }} · {{ item.seatId }}</span>
                        <span>{{ item.price === null ? "Sin precio" : formatMoney(item.price) }}</span>
                    </li>
                </ul>
                <p class="pay-total">
                    <span>Total</span>
                    <span>{{ totalLabel }}</span>
                </p>
            </template>
            <template #footer>
                <button type="button" class="action-btn action-btn--next" @click="closePayment">
                    Cerrar
                </button>
            </template>
        </DialogModal>

        <PhoneLoginModal
            :show="showPhoneLoginModal"
            @close="closePhoneLogin"
            @switch-to-login="switchPhoneToLogin"
            @switch-to-register="switchPhoneToRegister"
            @code-sent="handleCodeSent"
        />
        <VerifyCodeModal
            :show="showVerifyCodeModal"
            :lada="verifyLada"
            :telefono="verifyTelefono"
            :canal="verifyCanal"
            @close="closeVerifyCode"
            @verified="handleVerified"
            @resend="handleResend"
            @switch-to-login="switchVerifyToLogin"
            @switch-to-register="switchVerifyToRegister"
        />
        <LoginModal
            :show="showLoginModal"
            @close="closeLogin"
            @switch-to-register="switchLoginToRegister"
        />
        <RegisterModal
            :show="showRegisterModal"
            @close="closeRegister"
            @switch-to-login="switchRegisterToLogin"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";
import { registerKonva } from "@/components/seat-map/registerKonva.js";
import ZoneCanvas from "@/components/seat-map/ZoneCanvas.vue";
import SeatCanvas from "@/components/seat-map/SeatCanvas.vue";
import SiteHeader from "@/components/SiteHeader.vue";
import SiteFooter from "@/components/SiteFooter.vue";
import MobileBottomNav from "@/components/MobileBottomNav.vue";
import DialogModal from "@/components/DialogModal.vue";
import PhoneLoginModal from "@/components/PhoneLoginModal.vue";
import VerifyCodeModal from "@/components/VerifyCodeModal.vue";
import LoginModal from "@/components/LoginModal.vue";
import RegisterModal from "@/components/RegisterModal.vue";

registerKonva();

const props = defineProps({
    slug: { type: String, required: true },
    functionId: { type: String, default: null },
    event: { type: Object, required: true },
    stage: { type: Object, required: true },
});

const MIN_ZOOM = 0.6;
const MAX_ZOOM = 2.5;
const ZOOM_STEP = 0.25;
const HOLD_SECONDS = 7 * 60;

const zoneZoom = ref(1);
const seatZoom = ref(1);
const remaining = ref(HOLD_SECONDS);
const showPayment = ref(false);
const leaving = ref(false);
const selectedKeys = ref([]);
const seatPanel = ref(null);

const activeKey = ref(
    props.stage.sections.find((section) => section.rows.length > 0)?.key
        ?? props.stage.sections[0]?.key
        ?? null,
);

let timerId;

const activeSection = computed(() => {
    return props.stage.sections.find((section) => section.key === activeKey.value) ?? null;
});

const clock = computed(() => {
    const minutes = Math.floor(remaining.value / 60);
    const seconds = remaining.value % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
});

const activeFunction = computed(() => {
    const match = props.event.functions.find(
        (fn) => String(fn.id) === String(props.functionId),
    );
    return match || props.event.functions[0] || null;
});

const eventMeta = computed(() => {
    const parts = [];
    if (props.event.venueName) parts.push(props.event.venueName);
    const dateLabel = formatFunctionDate(activeFunction.value?.date);
    if (dateLabel) parts.push(dateLabel);
    return parts.join(" · ");
});

const selectedDetails = computed(() => {
    const items = [];

    for (const section of props.stage.sections) {
        const price = section.price?.discountPrice ?? section.price?.originalPrice ?? null;
        for (const row of section.rows) {
            for (const seat of row.seats) {
                const key = `${section.key}:${seat.id}`;
                if (!selectedKeys.value.includes(key)) continue;
                items.push({
                    key,
                    seatId: seat.id,
                    zone: section.name,
                    price,
                });
            }
        }
    }

    return items;
});

const totalLabel = computed(() => {
    const prices = selectedDetails.value
        .map((item) => item.price)
        .filter((price) => price !== null);
    if (!prices.length) return "Sin precio";
    return formatMoney(prices.reduce((sum, price) => sum + price, 0));
});

function zonePriceLabel(section) {
    if (!section?.price) return "Precio no disponible";
    const current = section.price.discountPrice ?? section.price.originalPrice;
    return formatMoney(current);
}

function formatMoney(value) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(value);
}

function formatFunctionDate(iso) {
    if (!iso) return "";
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return "";
    return new Intl.DateTimeFormat("es-MX", {
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(date);
}

function selectSection(key) {
    activeKey.value = key;
}

function toggleSeat(section, seat) {
    if (seat.status !== "disponible") return;
    const key = `${section.key}:${seat.id}`;
    const index = selectedKeys.value.indexOf(key);
    if (index >= 0) {
        selectedKeys.value.splice(index, 1);
    } else {
        selectedKeys.value.push(key);
    }
}

function changeZoom(target, delta) {
    const zoom = target === "zone" ? zoneZoom : seatZoom;
    zoom.value = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Math.round((zoom.value + delta) * 100) / 100));
}

function resetZoom(target) {
    if (target === "zone") zoneZoom.value = 1;
    else seatZoom.value = 1;
}

function goHome() {
    if (leaving.value) return;
    leaving.value = true;
    router.visit(route("home"));
}

function goBack() {
    if (leaving.value) return;
    leaving.value = true;
    const query = props.functionId ? { function: props.functionId } : {};
    router.get(route("event.detail", { slug: props.slug }), query);
}

function openPayment() {
    if (!selectedDetails.value.length) return;
    showPayment.value = true;
}

function closePayment() {
    showPayment.value = false;
    if (remaining.value <= 0) goHome();
}

onMounted(() => {
    timerId = window.setInterval(() => {
        if (remaining.value <= 0) return;
        remaining.value -= 1;
        if (remaining.value <= 0) {
            window.clearInterval(timerId);
            if (!showPayment.value) goHome();
        }
    }, 1000);
});

onUnmounted(() => {
    window.clearInterval(timerId);
});

const showPhoneLoginModal = ref(false);
const showVerifyCodeModal = ref(false);
const showLoginModal = ref(false);
const showRegisterModal = ref(false);
const verifyLada = ref("52");
const verifyTelefono = ref("");
const verifyCanal = ref("whatsapp");

function openLogin() {
    showRegisterModal.value = false;
    showLoginModal.value = false;
    showVerifyCodeModal.value = false;
    showPhoneLoginModal.value = true;
}
function openRegister() {
    showPhoneLoginModal.value = false;
    showLoginModal.value = false;
    showVerifyCodeModal.value = false;
    showRegisterModal.value = true;
}
function closePhoneLogin() {
    showPhoneLoginModal.value = false;
}
function closeVerifyCode() {
    showVerifyCodeModal.value = false;
}
function closeLogin() {
    showLoginModal.value = false;
}
function closeRegister() {
    showRegisterModal.value = false;
}
function switchPhoneToLogin() {
    showPhoneLoginModal.value = false;
    showLoginModal.value = true;
}
function switchPhoneToRegister() {
    showPhoneLoginModal.value = false;
    showRegisterModal.value = true;
}
function handleCodeSent(data) {
    verifyLada.value = data.lada;
    verifyTelefono.value = data.telefono;
    verifyCanal.value = data.canal;
    showPhoneLoginModal.value = false;
    showVerifyCodeModal.value = true;
}
function handleVerified() {
    showVerifyCodeModal.value = false;
}
function handleResend() {
    showVerifyCodeModal.value = false;
    showPhoneLoginModal.value = true;
}
function switchVerifyToLogin() {
    showVerifyCodeModal.value = false;
    showLoginModal.value = true;
}
function switchVerifyToRegister() {
    showVerifyCodeModal.value = false;
    showRegisterModal.value = true;
}
function switchLoginToRegister() {
    showLoginModal.value = false;
    showRegisterModal.value = true;
}
function switchRegisterToLogin() {
    showRegisterModal.value = false;
    showLoginModal.value = true;
}
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

.timer-event-name {
    font-weight: 800;
    font-size: var(--text-lg);
}

.timer-event-meta {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.timer {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    flex-shrink: 0;
}

.timer-label {
    font-size: var(--text-xs);
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-text-secondary);
}

.timer-value {
    font-variant-numeric: tabular-nums;
    font-size: var(--text-2xl);
    font-weight: 800;
    color: var(--color-brand);
    line-height: 1.1;
}

.timer--urgent .timer-value {
    color: var(--color-danger);
}

.seat-page {
    padding-top: var(--space-5);
    padding-bottom: calc(var(--bottom-nav-height) + var(--space-8));
}

.map-card {
    background: #ffffff;
    color: #111827;
    border-radius: 12px;
    padding: 16px 16px 20px;
    margin-bottom: var(--space-5);
}

.map-card h2 {
    color: #111827;
    font-size: 0.95rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.zone-subtitle {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    color: #4b5563;
    font-size: 0.95rem;
}

.zone-price {
    font-weight: 700;
    color: #111827;
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
    max-height: 360px;
}

.zoom-controls {
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

.zoom-controls button {
    width: 36px;
    height: 36px;
    border: none;
    border-bottom: 1px solid #e5e7eb;
    background: #ffffff;
    color: #111827;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
}

.zoom-controls button:last-child {
    border-bottom: none;
}

.zoom-controls button:hover:not(:disabled) {
    background: #f3f4f6;
}

.zoom-controls button:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.empty-zone {
    margin: 12px 0 20px;
    color: #4b5563;
}

.selection-summary {
    margin-top: 12px;
    color: #111827;
    font-size: 0.92rem;
}

.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 18px;
    list-style: none;
    margin: 16px 0;
    color: #374151;
    font-size: 0.92rem;
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
    flex-shrink: 0;
}

.swatch--disponible {
    background: #22c55e;
}

.swatch--no-disponible {
    background: #9ca3af;
}

.swatch--vendido {
    background: #ef4444;
}

.swatch--proceso {
    background: #f97316;
}

.actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
}

.action-btn {
    min-width: 120px;
    min-height: 44px;
    padding: 0 18px;
    border: none;
    border-radius: 4px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    cursor: pointer;
}

.action-btn--cancel {
    background: #1a1a1a;
    color: #ffffff;
}

.action-btn--back,
.action-btn--next {
    background: #16a34a;
    color: #ffffff;
}

.action-btn--next:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.action-btn:hover:not(:disabled) {
    filter: brightness(1.08);
}

.pay-lead {
    margin-bottom: 16px;
    color: var(--color-text-secondary);
}

.pay-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 16px;
}

.pay-list li,
.pay-total {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.pay-total {
    font-weight: 800;
    padding-top: 12px;
    border-top: 1px solid var(--color-border);
}

@media (min-width: 768px) {
    .viewport--zones {
        height: calc(100dvh - 15rem);
    }

    .viewport--seats {
        max-height: 440px;
    }

    .seat-page {
        padding-bottom: var(--space-10);
    }
}
</style>
