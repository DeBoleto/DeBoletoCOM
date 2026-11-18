<template>
    <div class="relative overflow-hidden rounded-lg border border-bo-line bg-bo-paper" :class="heightClass">
        <VenueCanvas
            ref="canvas"
            :seats="seats"
            :zones="zones"
            :active-key="activeKey"
            :selected-keys="selectedKeys"
            :main-stage="mainStage"
            :areas="areas"
            :readonly="readonly"
            @zone="emit('zone', $event)"
            @toggle="emit('toggle', $event)"
            @direct="direct = $event"
        />

        <p v-if="!seats.length" class="absolute inset-0 grid place-items-center p-6 text-center text-sm text-bo-slate">
            {{ emptyText }}
        </p>

        <div class="absolute top-2 right-2 flex flex-col gap-1" role="group" aria-label="Controles del mapa">
            <button type="button" class="grid size-9 place-items-center rounded-md border border-bo-line bg-bo-card shadow-sm hover:border-bo-slate" aria-label="Acercar" @click="canvas?.zoomIn()">
                <BoIcon name="zoomIn" :size="18" />
            </button>
            <button type="button" class="grid size-9 place-items-center rounded-md border border-bo-line bg-bo-card shadow-sm hover:border-bo-slate" aria-label="Alejar" @click="canvas?.zoomOut()">
                <BoIcon name="zoomOut" :size="18" />
            </button>
            <button type="button" class="grid size-9 place-items-center rounded-md border border-bo-line bg-bo-card shadow-sm hover:border-bo-slate" aria-label="Centrar mapa" @click="canvas?.reset()">
                <BoIcon name="refresh" :size="18" />
            </button>
        </div>

        <button
            v-if="activeKey !== null"
            type="button"
            class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-bo-ink px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-bo-slate"
            @click="emit('zone', null)"
        >
            <BoIcon name="chevronLeft" :size="16" />
            Mapa completo
        </button>

        <p
            v-if="seats.length && activeKey === null"
            class="pointer-events-none absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full px-3 py-1 text-xs font-semibold whitespace-nowrap shadow-sm"
            :class="direct ? 'bg-bo-green text-white' : 'bg-bo-ink/80 text-white'"
            aria-hidden="true"
        >
            {{ hint }}
        </p>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import BoIcon from "./BoIcon.vue";
import VenueCanvas from "@/components/seat-map/VenueCanvas.vue";
import { venueAreas, venueMainStage, venueSeats } from "@/components/seat-map/venueLayout.js";

/**
 * Mapa completo del recinto para taquilla y administración: todas las zonas
 * en un lienzo, tocar una zona la acerca y, salvo en solo lectura, a partir
 * de x3 los asientos se eligen directo en el mapa completo.
 */
const props = defineProps({
    stage: { type: Object, default: null },
    statuses: { type: Object, default: () => ({}) },
    selectedKeys: { type: Array, default: () => [] },
    activeKey: { type: String, default: null },
    readonly: { type: Boolean, default: false },
    heightClass: { type: String, default: "h-[60vh] min-h-80" },
    emptyText: { type: String, default: "Este mapa no tiene asientos numerados." },
});

const emit = defineEmits(["zone", "toggle"]);

const canvas = ref(null);
const direct = ref(false);

const sections = computed(() => props.stage?.sections ?? []);
const mainStage = computed(() => venueMainStage(props.stage));
const areas = computed(() => venueAreas(sections.value));

const seats = computed(() =>
    venueSeats(sections.value, (section, seat) =>
        seat.selectable === false || section.available === false ? "no_disponible" : (props.statuses[section.key]?.[seat.id] ?? "disponible"),
    ),
);

const zones = computed(() =>
    Object.fromEntries(
        sections.value.map((section) => [
            section.key,
            {
                name: section.name,
                color: section.areaColor || section.color,
                available: section.available !== false,
                hasSeats: section.capacity === null && section.rows.length > 0,
            },
        ]),
    ),
);

const hint = computed(() => {
    if (props.readonly) return "Toca una zona para verla · doble toque para acercar";
    return direct.value ? "Selección de asientos activa · aleja para ver zonas" : "Toca una zona o dale doble toque para elegir asientos";
});
</script>
