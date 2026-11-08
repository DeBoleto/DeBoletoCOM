<template>
    <section class="rounded-xl border border-bo-line bg-bo-card p-5" aria-labelledby="zone-title">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="zone-title" class="font-semibold">Zona</h2>
            <p v-if="!functionId" class="text-sm text-bo-pending">Elige primero la función para ver disponibilidad.</p>
        </div>
        <template v-if="stage">
            <div class="mt-3 overflow-auto rounded-lg bg-bo-paper p-2">
                <ZoneCanvas :sections="stage.sections" :active-key="sectionKey" :canvas-size="stage.canvas" @select="selectSection" />
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="section in stage.sections"
                    :key="section.key"
                    type="button"
                    class="rounded-full border px-3 py-1 text-sm"
                    :class="sectionKey === section.key ? 'border-bo-ink bg-bo-ink text-white' : 'border-bo-line hover:border-bo-slate'"
                    @click="selectSection(section.key)"
                >
                    {{ section.name }}
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
            <div v-if="info && !isGeneralAdmission" class="flex items-center gap-1">
                <button type="button" class="rounded-md border border-bo-line p-1.5 hover:border-bo-slate" aria-label="Alejar" @click="zoom(-0.15)">
                    <BoIcon name="zoomOut" :size="18" />
                </button>
                <button type="button" class="rounded-md border border-bo-line p-1.5 hover:border-bo-slate" aria-label="Acercar" @click="zoom(0.15)">
                    <BoIcon name="zoomIn" :size="18" />
                </button>
            </div>
        </div>

        <div v-if="loading" class="mt-4 h-48 animate-pulse rounded-lg bg-bo-paper" aria-busy="true" />
        <p v-else-if="error" class="mt-4 text-sm text-bo-sold">{{ error }}</p>

        <template v-else-if="info">
            <div v-if="isGeneralAdmission" class="mt-4 flex flex-wrap items-center gap-4">
                <span class="text-sm font-medium">Cantidad de boletos</span>
                <QuantityStepper :model-value="quantity" :max="Math.min(maxTickets, info.remaining ?? 0)" label="boletos" @update:model-value="setQuantity" />
                <span v-if="info.remaining === 0" class="text-sm text-bo-sold">Sección agotada.</span>
            </div>

            <template v-else>
                <div class="mt-4 overflow-auto rounded-lg bg-bo-paper p-3">
                    <SeatCanvas :section="sectionWithStatus" :zoom="seatZoom" :selected-keys="selectedKeys" @toggle="toggleSeat" />
                </div>
                <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-bo-slate">
                    <li v-for="item in legend" :key="item.label" class="flex items-center gap-2">
                        <span class="size-3 rounded-sm" :style="{ background: item.color }" />
                        {{ item.label }}
                    </li>
                </ul>
            </template>

            <slot name="extra" />
        </template>
    </section>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import BoIcon from "./BoIcon.vue";
import QuantityStepper from "./QuantityStepper.vue";
import ZoneCanvas from "@/components/seat-map/ZoneCanvas.vue";
import SeatCanvas from "@/components/seat-map/SeatCanvas.vue";
import { money } from "./format.js";

/**
 * Selector de zona y asientos. Emite la selección como:
 * { localityId, sectionId, generalAdmission, seats, quantity, price }.
 */
const props = defineProps({
    stage: { type: Object, default: null },
    eventId: { type: Number, required: true },
    functionId: { type: Number, default: null },
    stageRoute: { type: String, required: true },
    showPrices: { type: Boolean, default: true },
    maxTickets: { type: Number, default: 50 },
});

const emit = defineEmits(["change"]);

const legend = [
    { label: "Disponible", color: "#22c55e" },
    { label: "Seleccionado", color: "#166534" },
    { label: "Vendido", color: "#ef4444" },
    { label: "No disponible", color: "#9ca3af" },
];

const sectionKey = ref(null);
const info = ref(null);
const error = ref("");
const loading = ref(false);
const selectedKeys = ref([]);
const quantity = ref(0);
const seatZoom = ref(1);
let requestId = 0;

const activeSection = computed(() => props.stage?.sections.find((section) => section.key === sectionKey.value) ?? null);
const isGeneralAdmission = computed(() => info.value?.capacity !== null && info.value?.capacity !== undefined);
const selectedSeats = computed(() => selectedKeys.value.map((key) => key.split(":").slice(1).join(":")));

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

function publish() {
    emit("change", {
        localityId: Number(activeSection.value?.localityId) || null,
        sectionId: activeSection.value?.sectionId ?? null,
        generalAdmission: isGeneralAdmission.value,
        seats: isGeneralAdmission.value ? [] : [...selectedSeats.value],
        quantity: isGeneralAdmission.value ? quantity.value : 0,
        price: info.value?.price ?? 0,
    });
}

function reset() {
    selectedKeys.value = [];
    quantity.value = 0;
}

function selectSection(key) {
    sectionKey.value = key;
    reset();
    load();
}

async function load() {
    info.value = null;
    error.value = "";
    publish();

    if (!activeSection.value) return;
    if (!props.functionId) {
        error.value = "Elige la función para ver los asientos.";
        return;
    }

    const current = ++requestId;
    loading.value = true;

    try {
        const url = route(props.stageRoute, { evento: props.eventId, funcion: props.functionId, seccion: activeSection.value.sectionId });
        const response = await fetch(url, { headers: { Accept: "application/json" }, credentials: "same-origin" });

        if (!response.ok) throw new Error(String(response.status));
        if (current !== requestId) return;

        info.value = await response.json();
        publish();
    } catch {
        if (current === requestId) error.value = "No pudimos cargar la disponibilidad. Intenta de nuevo.";
    } finally {
        if (current === requestId) loading.value = false;
    }
}

function toggleSeat(seat) {
    const key = `${activeSection.value.key}:${seat.id}`;
    const index = selectedKeys.value.indexOf(key);

    if (index >= 0) selectedKeys.value.splice(index, 1);
    else if (selectedKeys.value.length < props.maxTickets) selectedKeys.value.push(key);

    publish();
}

function setQuantity(value) {
    quantity.value = value;
    publish();
}

function zoom(delta) {
    seatZoom.value = Math.min(2.5, Math.max(0.5, Math.round((seatZoom.value + delta) * 100) / 100));
}

watch(
    () => props.functionId,
    () => {
        reset();
        if (sectionKey.value !== null) load();
    },
);
</script>
