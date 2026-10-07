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
                            <div
                                class="viewport-scaler"
                                :style="{ width: `${zoneZoom * 100}%` }"
                            >
                                <svg
                                    class="zone-svg"
                                    :viewBox="zoneFrame.viewBox"
                                    role="img"
                                    aria-label="Diagrama del recinto"
                                >
                                    <rect
                                        v-if="zoneFrame.stageBar"
                                        :x="zoneFrame.stageBar.x"
                                        :y="zoneFrame.stageBar.y"
                                        :width="zoneFrame.stageBar.width"
                                        :height="zoneFrame.stageBar.height"
                                        fill="#1d4ed8"
                                        rx="4"
                                    />
                                    <text
                                        v-if="zoneFrame.stageBar"
                                        :x="zoneFrame.stageBar.x + zoneFrame.stageBar.width / 2"
                                        :y="zoneFrame.stageBar.y + zoneFrame.stageBar.height / 2"
                                        text-anchor="middle"
                                        dominant-baseline="central"
                                        fill="#ffffff"
                                        font-weight="700"
                                        :font-size="zoneFrame.stageBar.height * 0.34"
                                    >
                                        ESCENARIO
                                    </text>
                                    <g
                                        v-for="section in stage.sections"
                                        :key="section.key"
                                    >
                                        <polygon
                                            v-if="section.polygon.length >= 3"
                                            :points="polygonPoints(section.polygon)"
                                            :fill="section.areaColor"
                                            :stroke="section.key === activeKey ? '#4ecba0' : '#334155'"
                                            :stroke-width="section.key === activeKey ? 16 : 4"
                                            role="button"
                                            tabindex="0"
                                            :aria-pressed="section.key === activeKey"
                                            :aria-label="zoneAriaLabel(section)"
                                            @click="selectSection(section.key)"
                                            @keydown.enter.prevent="selectSection(section.key)"
                                            @keydown.space.prevent="selectSection(section.key)"
                                        />
                                        <text
                                            v-if="section.polygon.length >= 3"
                                            :x="centroid(section.polygon).x"
                                            :y="centroid(section.polygon).y"
                                            text-anchor="middle"
                                            dominant-baseline="central"
                                            font-weight="700"
                                            :font-size="labelSize(section)"
                                            :fill="fillTextColor(section.areaColor)"
                                        >
                                            {{ section.name }}
                                        </text>
                                    </g>
                                </svg>
                            </div>
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
                            <svg
                                class="seat-svg"
                                :viewBox="seatLayout.viewBox"
                                :width="seatLayout.width * seatZoom"
                                :height="seatLayout.height * seatZoom"
                                role="group"
                                aria-label="Asientos de la zona seleccionada"
                            >
                                <g v-if="activeSection.stage">
                                    <rect
                                        :x="activeSection.stage.x1 * UNIT"
                                        :y="activeSection.stage.y1 * UNIT"
                                        :width="(activeSection.stage.x2 - activeSection.stage.x1) * UNIT"
                                        :height="18"
                                        fill="#f59e0b"
                                        rx="3"
                                    />
                                    <text
                                        :x="((activeSection.stage.x1 + activeSection.stage.x2) / 2) * UNIT"
                                        :y="activeSection.stage.y1 * UNIT + 9"
                                        text-anchor="middle"
                                        dominant-baseline="central"
                                        fill="#ffffff"
                                        font-size="12"
                                        font-weight="700"
                                    >
                                        ESCENARIO
                                    </text>
                                </g>
                                <g
                                    v-for="(label, index) in seatLayout.labels"
                                    :key="`label-${index}`"
                                >
                                    <rect
                                        :x="label.x * UNIT - LABEL_SIZE / 2"
                                        :y="label.y * UNIT - LABEL_SIZE / 2"
                                        :width="LABEL_SIZE"
                                        :height="LABEL_SIZE"
                                        fill="#1f2937"
                                        rx="2"
                                    />
                                    <text
                                        :x="label.x * UNIT"
                                        :y="label.y * UNIT"
                                        text-anchor="middle"
                                        dominant-baseline="central"
                                        fill="#ffffff"
                                        font-size="12"
                                        font-weight="700"
                                    >
                                        {{ label.name }}
                                    </text>
                                </g>
                                <g
                                    v-for="row in activeSection.rows"
                                    :key="row.fila"
                                >
                                    <g
                                        v-for="seat in row.seats"
                                        :key="seat.id"
                                        :class="{ 'is-selectable': seat.status === 'disponible' }"
                                        :role="seat.status === 'disponible' ? 'button' : 'img'"
                                        :tabindex="seat.status === 'disponible' ? 0 : undefined"
                                        :aria-pressed="seat.status === 'disponible' ? isSelected(activeSection, seat) : undefined"
                                        :aria-label="`${seat.id}, ${statusLabel(seat.status)}`"
                                        @click="toggleSeat(activeSection, seat)"
                                        @keydown.enter.prevent="toggleSeat(activeSection, seat)"
                                        @keydown.space.prevent="toggleSeat(activeSection, seat)"
                                    >
                                        <rect
                                            :x="seat.x * UNIT - SEAT_W / 2"
                                            :y="seat.y * UNIT - SEAT_H / 2"
                                            :width="SEAT_W"
                                            :height="SEAT_H"
                                            :fill="isSelected(activeSection, seat) ? '#166534' : statusColor(seat.status)"
                                            :stroke="isSelected(activeSection, seat) ? '#4ecba0' : 'transparent'"
                                            :stroke-width="isSelected(activeSection, seat) ? 3 : 0"
                                            rx="2"
                                        />
                                        <text
                                            :x="seat.x * UNIT"
                                            :y="seat.y * UNIT"
                                            text-anchor="middle"
                                            dominant-baseline="central"
                                            :fill="seatTextColor(seat, activeSection)"
                                            font-size="9"
                                            font-weight="700"
                                        >
                                            {{ seat.id }}
                                        </text>
                                    </g>
                                </g>
                            </svg>
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
import SiteHeader from "@/components/SiteHeader.vue";
import SiteFooter from "@/components/SiteFooter.vue";
import MobileBottomNav from "@/components/MobileBottomNav.vue";
import DialogModal from "@/components/DialogModal.vue";
import PhoneLoginModal from "@/components/PhoneLoginModal.vue";
import VerifyCodeModal from "@/components/VerifyCodeModal.vue";
import LoginModal from "@/components/LoginModal.vue";
import RegisterModal from "@/components/RegisterModal.vue";

const props = defineProps({
    slug: { type: String, required: true },
    functionId: { type: String, default: null },
    event: { type: Object, required: true },
    stage: { type: Object, required: true },
});

const UNIT = 10;
const SEAT_W = 34;
const SEAT_H = 22;
const LABEL_SIZE = 22;
const MIN_ZOOM = 0.6;
const MAX_ZOOM = 2.5;
const ZOOM_STEP = 0.25;
const HOLD_SECONDS = 7 * 60;

const STATUS_COLOR = {
    disponible: "#22c55e",
    no_disponible: "#9ca3af",
    vendido: "#ef4444",
    en_proceso: "#f97316",
};

const STATUS_LABEL = {
    disponible: "Disponible",
    no_disponible: "No disponible",
    vendido: "Vendido",
    en_proceso: "En proceso de compra",
};

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

const zoneFrame = computed(() => {
    const sections = props.stage.sections.filter((section) => section.polygon.length >= 3);
    if (!sections.length) {
        const size = props.stage.canvas || 2000;
        return { viewBox: `0 0 ${size} ${size}`, stageBar: null };
    }

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (const section of sections) {
        for (const point of section.polygon) {
            minX = Math.min(minX, point.x);
            minY = Math.min(minY, point.y);
            maxX = Math.max(maxX, point.x);
            maxY = Math.max(maxY, point.y);
        }
    }

    const span = Math.max(maxX - minX, 200);
    const barWidth = Math.min(span * 0.46, 640);
    const barHeight = Math.max(80, span * 0.09);
    const barX = (minX + maxX) / 2 - barWidth / 2;
    const barY = minY - barHeight - span * 0.05;
    const pad = span * 0.045;
    const frameMinX = Math.min(minX, barX) - pad;
    const frameMinY = barY - pad;
    const frameMaxX = Math.max(maxX, barX + barWidth) + pad;
    const frameMaxY = maxY + pad;

    return {
        viewBox: `${frameMinX} ${frameMinY} ${frameMaxX - frameMinX} ${frameMaxY - frameMinY}`,
        stageBar: { x: barX, y: barY, width: barWidth, height: barHeight },
    };
});

const seatLayout = computed(() => {
    const section = activeSection.value;
    const labels = (section?.labels ?? []).filter((label) => !/^\d+$/.test(String(label.name)));
    let minX = 0;
    let minY = 0;
    let maxX = 40;
    let maxY = 40;

    const consider = (x, y, halfW, halfH) => {
        minX = Math.min(minX, x - halfW);
        minY = Math.min(minY, y - halfH);
        maxX = Math.max(maxX, x + halfW);
        maxY = Math.max(maxY, y + halfH);
    };

    if (section?.stage) {
        consider(section.stage.x1 * UNIT, section.stage.y1 * UNIT, 0, 0);
        consider(
            section.stage.x2 * UNIT,
            section.stage.y1 * UNIT + 18,
            0,
            0,
        );
    }

    for (const label of labels) {
        consider(label.x * UNIT, label.y * UNIT, LABEL_SIZE / 2, LABEL_SIZE / 2);
    }

    for (const row of section?.rows ?? []) {
        for (const seat of row.seats) {
            consider(seat.x * UNIT, seat.y * UNIT, SEAT_W / 2, SEAT_H / 2);
        }
    }

    const pad = 12;
    const width = maxX - minX + pad * 2;
    const height = maxY - minY + pad * 2;

    return {
        labels,
        width,
        height,
        viewBox: `${minX - pad} ${minY - pad} ${width} ${height}`,
    };
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

function polygonPoints(polygon) {
    return polygon.map((point) => `${point.x},${point.y}`).join(" ");
}

function centroid(polygon) {
    const total = polygon.reduce(
        (sum, point) => ({ x: sum.x + point.x, y: sum.y + point.y }),
        { x: 0, y: 0 },
    );
    return {
        x: total.x / polygon.length,
        y: total.y / polygon.length,
    };
}

function labelSize(section) {
    const xs = section.polygon.map((point) => point.x);
    const ys = section.polygon.map((point) => point.y);
    const width = Math.max(...xs) - Math.min(...xs);
    const height = Math.max(...ys) - Math.min(...ys);
    const byWidth = (width / Math.max(section.name.length, 1)) * 1.35;
    const byHeight = height * 0.18;
    return Math.max(18, Math.min(64, byWidth, byHeight));
}

function fillTextColor(hex) {
    const value = String(hex || "").replace("#", "");
    if (value.length !== 6) return "#ffffff";
    const red = parseInt(value.slice(0, 2), 16);
    const green = parseInt(value.slice(2, 4), 16);
    const blue = parseInt(value.slice(4, 6), 16);
    const luminance = (0.299 * red + 0.587 * green + 0.114 * blue) / 255;
    return luminance > 0.62 ? "#111827" : "#ffffff";
}

function statusColor(status) {
    return STATUS_COLOR[status] || STATUS_COLOR.no_disponible;
}

function statusLabel(status) {
    return STATUS_LABEL[status] || status;
}

function seatTextColor(seat, section) {
    if (isSelected(section, seat) || seat.status !== "no_disponible") {
        return "#ffffff";
    }
    return "#1f2937";
}

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

function zoneAriaLabel(section) {
    return `Zona ${section.name}, ${zonePriceLabel(section)}`;
}

function selectSection(key) {
    activeKey.value = key;
}

function isSelected(section, seat) {
    return selectedKeys.value.includes(`${section.key}:${seat.id}`);
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
    max-height: 280px;
}

.viewport--seats {
    max-height: 360px;
}

.viewport-scaler {
    min-width: 100%;
}

.zone-svg {
    display: block;
    width: 100%;
    height: auto;
}

.seat-svg {
    display: block;
}

.seat-svg text,
.zone-svg text {
    pointer-events: none;
}

.zone-svg polygon {
    cursor: pointer;
}

.is-selectable {
    cursor: pointer;
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
        max-height: 520px;
    }

    .viewport--seats {
        max-height: 440px;
    }

    .seat-page {
        padding-bottom: var(--space-10);
    }
}
</style>
