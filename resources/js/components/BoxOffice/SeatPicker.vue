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
            <div class="mt-3 overflow-auto rounded-lg bg-bo-paper p-2">
                <ZoneCanvas
                    :sections="stage.sections"
                    :active-key="sectionKey"
                    :hover-key="hoverKey"
                    :badges="badges"
                    :canvas-size="stage.canvas"
                    @select="selectSection"
                    @hover="hoverKey = $event"
                />
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="section in stage.sections"
                    :key="section.key"
                    type="button"
                    class="relative inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors"
                    :class="sectionKey === section.key ? 'border-bo-ink bg-bo-ink text-white' : 'border-bo-line hover:border-bo-slate'"
                    :aria-pressed="sectionKey === section.key"
                    @click="selectSection(section.key)"
                    @mouseenter="hoverKey = section.key"
                    @mouseleave="hoverKey = null"
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
                <div v-if="info && !isGeneralAdmission" class="flex items-center gap-1">
                    <button type="button" class="rounded-md border border-bo-line p-1.5 hover:border-bo-slate" aria-label="Alejar" @click="zoom(-0.15)">
                        <BoIcon name="zoomOut" :size="18" />
                    </button>
                    <button type="button" class="rounded-md border border-bo-line p-1.5 hover:border-bo-slate" aria-label="Acercar" @click="zoom(0.15)">
                        <BoIcon name="zoomIn" :size="18" />
                    </button>
                    <button type="button" class="rounded-md border border-bo-line p-1.5 hover:border-bo-slate" aria-label="Ajustar a la zona" @click="seatZoom = 1">
                        <BoIcon name="refresh" :size="18" />
                    </button>
                </div>
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
                <div class="mt-4 flex h-[60vh] min-h-80 overflow-auto rounded-lg bg-bo-paper p-3">
                    <SeatCanvas :key="sectionKey" :section="sectionWithStatus" :zoom="seatZoom" :selected-keys="selectedKeys" variant="dots" @toggle="toggleSeat" />
                </div>
                <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-bo-slate">
                    <li v-for="item in legend" :key="item.label" class="flex items-center gap-2">
                        <span class="size-3 rounded-full" :style="{ background: item.color }" />
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
import { computed, reactive, ref, watch } from "vue";
import BoIcon from "./BoIcon.vue";
import QuantityStepper from "./QuantityStepper.vue";
import ZoneCanvas from "@/components/seat-map/ZoneCanvas.vue";
import SeatCanvas from "@/components/seat-map/SeatCanvas.vue";
import { money } from "./format.js";

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

const legend = [
    { label: "Disponible", color: "#22c55e" },
    { label: "Seleccionado", color: "#166534" },
    { label: "En proceso", color: "#f97316" },
    { label: "Vendido", color: "#ef4444" },
    { label: "No disponible", color: "#9ca3af" },
];

const sectionKey = ref(null);
const hoverKey = ref(null);
const infos = reactive({});
const picks = reactive({});
const error = ref("");
const loading = ref(false);
const seatZoom = ref(1);
let requestId = 0;

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

const selectedKeys = computed(() => activePick.value.seats.map((seat) => `${sectionKey.value}:${seat}`));

const quantityMax = computed(() => {
    const remaining = info.value?.remaining ?? 0;
    const room = props.maxTickets - (totalTickets.value - activePick.value.quantity);
    return Math.max(0, Math.min(remaining, room));
});

const sectionWithStatus = computed(() => {
    const statuses = info.value?.statuses ?? {};

    return {
        ...activeSection.value,
        rows: activeSection.value.rows.map((row) => ({
            ...row,
            seats: row.seats.map((seat) => ({
                ...seat,
                status: seat.selectable === false ? "no_disponible" : (statuses[seat.id] ?? "disponible"),
            })),
        })),
    };
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

function resetAll() {
    Object.keys(picks).forEach((key) => delete picks[key]);
}

function clear(key) {
    delete picks[key];
    publish();
}

function selectSection(key) {
    if (!props.multiple) resetAll();
    sectionKey.value = key;
    seatZoom.value = 1;
    load(key);
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
    if (!pick) return;
    const statuses = data.statuses ?? {};
    pick.seats = pick.seats.filter((seat) => (statuses[seat] ?? "disponible") === "disponible");
    if (isGeneral(data)) pick.quantity = Math.min(pick.quantity, data.remaining ?? 0);
}

function toggleSeat(seat) {
    const pick = pickFor(sectionKey.value);
    const index = pick.seats.indexOf(seat.id);
    const limit = props.multiple ? props.maxTickets - totalTickets.value : props.maxTickets - pick.seats.length;

    if (index >= 0) pick.seats.splice(index, 1);
    else if (limit > 0) pick.seats.push(seat.id);

    publish();
}

function setQuantity(value) {
    pickFor(sectionKey.value).quantity = value;
    publish();
}

function zoom(delta) {
    seatZoom.value = Math.min(2.5, Math.max(0.5, Math.round((seatZoom.value + delta) * 100) / 100));
}

watch(
    () => props.functionId,
    () => {
        resetAll();
        Object.keys(infos).forEach((key) => delete infos[key]);
        if (sectionKey.value !== null) load(sectionKey.value, { force: true });
        else publish();
    },
);

defineExpose({ clear, focus: selectSection });
</script>
