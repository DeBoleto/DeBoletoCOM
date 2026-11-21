<template>
    <section class="rounded-xl border border-bo-line bg-bo-card p-5" aria-labelledby="zone-title">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <h2 id="zone-title" class="font-semibold">Zona</h2>
                <p v-if="multiple && functionId" class="text-sm text-bo-slate">Puedes combinar boletos de varias zonas en la misma venta.</p>
            </div>
            <p v-if="!functionId" class="text-sm text-bo-pending">Elige primero la función para ver disponibilidad.</p>
            <p v-else-if="multiple" class="tabular text-sm text-bo-slate">
                <span class="font-semibold text-bo-ink">{{ totalTickets }}</span> / {{ maxTickets }} boletos
            </p>
        </div>
        <template v-if="stage">
            <VenueMap
                class="mt-3"
                :stage="stage"
                :statuses="seatStatuses"
                :selected-keys="selectedKeys"
                :active-key="focusKey"
                @zone="focusZone"
                @toggle="pickSeat"
            />
            <p v-if="notice" class="mt-3 rounded-lg bg-bo-pending/10 px-3 py-2 text-sm font-medium text-bo-pending" role="status">
                {{ notice }}
            </p>
            <p v-if="seatGap" class="mt-3 rounded-lg bg-bo-sold-soft px-3 py-2 text-sm font-medium text-bo-sold" role="alert">
                Selecciona asientos pegados: quedó libre el asiento {{ seatGap.seat }} de la fila {{ seatGap.fila }} en {{ seatGap.zone }}, entre dos de los asientos elegidos.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="section in stage.sections"
                    :key="section.key"
                    type="button"
                    class="relative inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors"
                    :class="sectionKey === section.key ? 'border-bo-ink bg-bo-ink text-white' : 'border-bo-line hover:border-bo-slate'"
                    :aria-pressed="sectionKey === section.key"
                    @click="focusZone(section.key)"
                >
                    {{ section.name }}
                    <span
                        v-if="badges[section.key]"
                        class="tabular inline-grid min-w-5 place-items-center rounded-full px-1.5 text-xs font-bold"
                        :class="sectionKey === section.key ? 'bg-white text-bo-ink' : 'bg-bo-green text-white'"
                    >
                        {{ badges[section.key] }}
                    </span>
                </button>
            </div>
        </template>
        <p v-else class="mt-3 text-sm text-bo-sold">Este evento no tiene un mapa de recinto válido. Pide que lo configuren antes de continuar.</p>
    </section>

    <section v-if="activeSection" class="rounded-xl border border-bo-line bg-bo-card p-5" aria-labelledby="seats-title" aria-live="polite">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 id="seats-title" class="font-semibold">{{ activeSection.name }}</h2>
                <p v-if="info" class="tabular text-sm text-bo-slate">
                    <template v-if="showPrices">{{ money(info.price) }} por boleto</template>
                    <template v-if="info.remaining !== null"> — quedan {{ info.remaining }}</template>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    v-if="activePickCount"
                    type="button"
                    class="rounded-md px-2.5 py-1.5 text-sm font-medium text-bo-slate hover:bg-bo-paper hover:text-bo-sold"
                    @click="clear(sectionKey)"
                >
                    Quitar de esta zona
                </button>
            </div>
        </div>

        <div v-if="loading" class="mt-4 h-48 animate-pulse rounded-lg bg-bo-paper" aria-busy="true" />
        <p v-else-if="error" class="mt-4 text-sm text-bo-sold">{{ error }}</p>

        <template v-else-if="info">
            <div v-if="isGeneralAdmission" class="mt-4 flex flex-wrap items-center gap-4">
                <span class="text-sm font-medium">Cantidad de boletos</span>
                <QuantityStepper :model-value="activePick.quantity" :max="quantityMax" label="boletos" @update:model-value="setQuantity" />
                <span v-if="info.remaining === 0" class="text-sm text-bo-sold">Sección agotada.</span>
            </div>

            <template v-else>
                <p class="mt-3 text-sm text-bo-slate">
                    {{ activePickCount ? `Asientos: ${activePick.seats.join(", ")}` : "Toca los asientos en el mapa para elegirlos." }}
                </p>
                <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-bo-slate">
                    <li v-for="item in legend" :key="item.label" class="flex items-center gap-2">
                        <span
                            class="size-3 rounded-full"
                            :style="{
                                background: item.color,
                                boxShadow: item.ring ? `0 0 0 2px ${item.ring}` : undefined,
                                border: item.border ? `1.5px dashed ${item.border}` : undefined,
                            }"
                        />
                        {{ item.label }}
                    </li>
                </ul>
            </template>

            <p v-if="limitReached" class="mt-3 text-sm text-bo-pending">Llegaste al máximo de {{ maxTickets }} boletos por venta.</p>

            <slot name="extra" />
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import QuantityStepper from "./QuantityStepper.vue";
import VenueMap from "./VenueMap.vue";
import { singleSeatGap } from "@/components/seat-map/seatGap.js";
import { SEAT_STATUS, zoneSeatColors } from "@/components/seat-map/seatColors.js";
import { money } from "./format.js";
import { realtimeEnabled, useSeatUpdates } from "@/composables/useSeatUpdates.js";

/**
 * Selector de zona y asientos. Emite la selección como
 * { localityId, sectionId, generalAdmission, seats, quantity, price } o, con
 * `multiple`, una lista de esas selecciones (una por zona) con `key` y `name`.
 */
const props = defineProps({
    stage: { type: Object, default: null },
    eventId: { type: Number, required: true },
    functionId: { type: Number, default: null },
    stageRoute: { type: String, required: true },
    showPrices: { type: Boolean, default: true },
    maxTickets: { type: Number, default: 50 },
    multiple: { type: Boolean, default: false },
});

const emit = defineEmits(["change"]);

const legend = computed(() => {
    const colors = zoneSeatColors(activeSection.value?.areaColor || activeSection.value?.color);

    return [
        { label: "Disponible", color: colors.available },
        { label: "Seleccionado", color: colors.selected, ring: colors.glow },
        { label: "En proceso", color: SEAT_STATUS.en_proceso.fill, border: SEAT_STATUS.en_proceso.stroke },
        { label: "Vendido", color: SEAT_STATUS.vendido.fill },
        { label: "No disponible", color: SEAT_STATUS.no_disponible.fill },
    ];
});

const sectionKey = ref(null);
const focusKey = ref(null);
const infos = reactive({});
const venue = reactive({});
const picks = reactive({});
const error = ref("");
const notice = ref("");
const loading = ref(false);
const holdQueue = {};
const REFRESH_MS = realtimeEnabled ? 60000 : 20000;
let requestId = 0;
let venueRequest = 0;
let refreshId;
let noticeId;

const activeSection = computed(() => props.stage?.sections.find((section) => section.key === sectionKey.value) ?? null);
const info = computed(() => (sectionKey.value !== null ? (infos[sectionKey.value] ?? null) : null));
const isGeneralAdmission = computed(() => isGeneral(info.value));
const activePick = computed(() => readPick(sectionKey.value));

const countOf = (key) => {
    const pick = readPick(key);
    return isGeneral(infos[key]) ? pick.quantity : pick.seats.length;
};

const totalTickets = computed(() => Object.keys(picks).reduce((sum, key) => sum + countOf(key), 0));
const activePickCount = computed(() => (sectionKey.value !== null ? countOf(sectionKey.value) : 0));
const limitReached = computed(() => props.multiple && totalTickets.value >= props.maxTickets);

const badges = computed(() =>
    Object.fromEntries(Object.keys(picks).map((key) => [key, countOf(key)]).filter(([, count]) => count > 0)),
);

const selectedKeys = computed(() =>
    Object.entries(picks).flatMap(([key, pick]) => (isGeneral(infos[key]) ? [] : pick.seats.map((seat) => `${key}:${seat}`))),
);

const seatStatuses = computed(() =>
    Object.fromEntries(
        (props.stage?.sections ?? []).map((section) => [section.key, infos[section.key]?.statuses ?? venue[section.key]?.statuses ?? {}]),
    ),
);

const seatGap = computed(() => {
    for (const section of props.stage?.sections ?? []) {
        if (isGeneral(infos[section.key])) continue;

        const statuses = seatStatuses.value[section.key] ?? {};
        const gap = singleSeatGap(section, readPick(section.key).seats, (seatId) => (statuses[seatId] ?? "disponible") !== "disponible");
        if (gap) return gap;
    }

    return null;
});

const venueRoute = computed(() => props.stageRoute.replace(/\.stage$/, ".venue"));
const holdRoute = computed(() => props.stageRoute.replace(/\.stage$/, ".hold"));

const quantityMax = computed(() => {
    const remaining = info.value?.remaining ?? 0;
    const room = props.maxTickets - (totalTickets.value - activePick.value.quantity);
    return Math.max(0, Math.min(remaining, room));
});

function isGeneral(value) {
    return value?.capacity !== null && value?.capacity !== undefined;
}

function readPick(key) {
    return picks[key] ?? { seats: [], quantity: 0 };
}

function pickFor(key) {
    if (!picks[key]) picks[key] = { seats: [], quantity: 0 };
    return picks[key];
}

function selectionFor(key) {
    const section = props.stage?.sections.find((candidate) => candidate.key === key);
    const general = isGeneral(infos[key]);
    const pick = readPick(key);

    return {
        key,
        name: section?.name ?? "",
        localityId: Number(section?.localityId) || null,
        sectionId: section?.sectionId ?? null,
        generalAdmission: general,
        seats: general ? [] : [...pick.seats],
        quantity: general ? pick.quantity : 0,
        price: infos[key]?.price ?? 0,
    };
}

function publish() {
    if (props.multiple) {
        emit("change", Object.keys(picks).filter((key) => countOf(key) > 0 && infos[key]).map(selectionFor));
        return;
    }

    emit("change", sectionKey.value !== null ? selectionFor(sectionKey.value) : selectionFor(null));
}

function resetAll(functionId = props.functionId) {
    Object.keys(picks).forEach((key) => {
        releaseSeats(key, picks[key].seats, functionId);
        delete picks[key];
    });
}

function clear(key) {
    releaseSeats(key, readPick(key).seats);
    delete picks[key];
    publish();
}

function showNotice(message) {
    notice.value = message;
    window.clearTimeout(noticeId);
    noticeId = window.setTimeout(() => (notice.value = ""), 6000);
}

function csrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : "";
}

// Aparta o libera el asiento en el servidor. Las solicitudes de un mismo
// asiento van en orden para que tocarlo dos veces rápido no se cruce.
function sendHold(key, seatId, action, functionId = props.functionId) {
    const section = props.stage?.sections.find((candidate) => candidate.key === key);
    if (!section || !functionId || isGeneral(infos[key])) return Promise.resolve(true);

    const id = `${functionId}:${key}:${seatId}`;
    const run = async () => {
        try {
            const response = await fetch(route(holdRoute.value, props.eventId), {
                method: "POST",
                credentials: "same-origin",
                headers: { Accept: "application/json", "Content-Type": "application/json", "X-XSRF-TOKEN": csrfToken() },
                body: JSON.stringify({ funcion: functionId, seccion: section.sectionId, asiento: String(seatId), accion: action }),
            });

            if (response.ok) return true;

            const data = await response.json().catch(() => ({}));
            if (action === "apartar") showNotice(data.message ?? `El asiento ${seatId} ya no está disponible.`);
            return action !== "apartar";
        } catch {
            if (action === "apartar") showNotice("No pudimos apartar el asiento. Revisa tu conexión e intenta de nuevo.");
            return action !== "apartar";
        }
    };

    holdQueue[id] = (holdQueue[id] ?? Promise.resolve()).then(run);
    return holdQueue[id];
}

function releaseSeats(key, seats, functionId = props.functionId) {
    seats.forEach((seatId) => sendHold(key, seatId, "liberar", functionId));
}

function selectSection(key) {
    if (!props.multiple) resetAll();
    sectionKey.value = key;
    load(key);
}

function focusZone(key) {
    if (key === null) {
        focusKey.value = null;
        return;
    }

    const section = props.stage?.sections.find((candidate) => candidate.key === key);
    focusKey.value = section && section.capacity === null && section.rows.length ? key : null;
    if (sectionKey.value !== key) selectSection(key);
}

function pickSeat(seat) {
    if (sectionKey.value !== seat.key) {
        if (!props.multiple) resetAll();
        sectionKey.value = seat.key;
        load(seat.key);
    }

    toggleSeat(seat);
}

async function loadVenue({ keep = false } = {}) {
    if (!keep) Object.keys(venue).forEach((key) => delete venue[key]);
    if (!props.functionId || !props.stage) return;

    const current = ++venueRequest;

    try {
        const url = route(venueRoute.value, { evento: props.eventId, funcion: props.functionId });
        const response = await fetch(url, { headers: { Accept: "application/json" }, credentials: "same-origin" });
        if (!response.ok) return;

        const data = await response.json();
        if (current !== venueRequest) return;
        Object.assign(venue, data.zones ?? {});
        return true;
    } catch {
        // El mapa se queda con los estados por sección que ya se hayan cargado.
    }
}

async function load(key, { force = false } = {}) {
    error.value = "";
    if (force || !props.multiple) delete infos[key];
    publish();

    const section = props.stage?.sections.find((candidate) => candidate.key === key);
    if (!section) return;
    if (!props.functionId) {
        error.value = "Elige la función para ver los asientos.";
        return;
    }

    const current = ++requestId;
    loading.value = !infos[key];

    try {
        const url = route(props.stageRoute, { evento: props.eventId, funcion: props.functionId, seccion: section.sectionId });
        const response = await fetch(url, { headers: { Accept: "application/json" }, credentials: "same-origin" });

        if (!response.ok) throw new Error(String(response.status));

        const data = await response.json();
        infos[key] = data;
        dropTaken(key, data);
        if (current === requestId) publish();
    } catch {
        if (current === requestId && !infos[key]) error.value = "No pudimos cargar la disponibilidad. Intenta de nuevo.";
    } finally {
        if (current === requestId) loading.value = false;
    }
}

function dropTaken(key, data) {
    const pick = picks[key];
    if (!pick) return [];
    const statuses = data.statuses ?? {};
    const lost = pick.seats.filter((seat) => (statuses[seat] ?? "disponible") !== "disponible");
    pick.seats = pick.seats.filter((seat) => !lost.includes(seat));
    if (isGeneral(data)) pick.quantity = Math.min(pick.quantity, data.remaining ?? 0);
    return lost;
}

// Actualiza la disponibilidad con una sola consulta del mapa completo: sirve
// tanto para el mapa como para las zonas ya abiertas.
async function refresh() {
    if (!props.functionId || !props.stage || document.hidden) return;
    if (!(await loadVenue({ keep: true }))) return;

    const lost = [];

    for (const key of Object.keys(infos)) {
        const zone = venue[key];
        if (!zone || !infos[key]) continue;

        infos[key] = isGeneral(infos[key])
            ? { ...infos[key], remaining: zone.remaining ?? infos[key].remaining }
            : { ...infos[key], statuses: zone.statuses ?? {} };

        const name = props.stage.sections.find((section) => section.key === key)?.name ?? "";
        lost.push(...dropTaken(key, infos[key]).map((seat) => `${seat} (${name})`));
    }

    if (lost.length) showNotice(`Alguien más tomó: ${lost.join(", ")}. Elige otros asientos.`);
    publish();
}

async function toggleSeat(seat) {
    const pick = pickFor(seat.key);
    const index = pick.seats.indexOf(seat.id);
    const limit = props.multiple ? props.maxTickets - totalTickets.value : props.maxTickets - pick.seats.length;

    if (index >= 0) {
        pick.seats.splice(index, 1);
        publish();
        sendHold(seat.key, seat.id, "liberar");
        return;
    }

    if (limit <= 0) return;

    const functionId = props.functionId;
    pick.seats.push(seat.id);
    publish();

    if (await sendHold(seat.key, seat.id, "apartar", functionId)) return;
    if (functionId !== props.functionId) return;

    const current = picks[seat.key];
    if (current) current.seats = current.seats.filter((id) => id !== seat.id);
    publish();
    refresh();
}

function setQuantity(value) {
    pickFor(sectionKey.value).quantity = value;
    publish();
}

watch(
    () => props.functionId,
    (functionId, previous) => {
        resetAll(previous);
        Object.keys(infos).forEach((key) => delete infos[key]);
        loadVenue();
        if (sectionKey.value !== null) load(sectionKey.value, { force: true });
        else publish();
    },
);

useSeatUpdates(() => props.functionId, refresh);

onMounted(() => {
    loadVenue();
    refreshId = window.setInterval(refresh, REFRESH_MS);
});

onUnmounted(() => {
    window.clearInterval(refreshId);
    window.clearTimeout(noticeId);
});

defineExpose({ clear, focus: focusZone, seatGap });
</script>
