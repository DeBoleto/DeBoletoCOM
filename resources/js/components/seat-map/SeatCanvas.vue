<template>
    <div ref="host" class="konva-host" :style="{ width: `${stageWidth}px` }">
        <v-stage :config="stageConfig">
            <v-layer>
                <v-rect v-if="section.stage" :config="stageBarConfig" />
                <v-text v-if="section.stage" :config="stageBarTextConfig" />
                <v-rect
                    v-for="label in labels"
                    :key="`label-${label.x}-${label.y}-${label.name}`"
                    :config="labelBoxConfig(label)"
                />
                <v-text
                    v-for="label in labels"
                    :key="`label-text-${label.x}-${label.y}-${label.name}`"
                    :config="labelTextConfig(label)"
                />
                <v-rect
                    v-for="seat in seats"
                    :key="seat.id"
                    :config="seatConfig(seat)"
                    @click="onSeat(seat)"
                    @tap="onSeat(seat)"
                    @mouseenter="(event) => setCursor(event, seat)"
                    @mouseleave="resetCursor"
                />
                <v-text
                    v-for="seat in seats"
                    :key="`${seat.id}-label`"
                    :config="seatTextConfig(seat)"
                />
            </v-layer>
        </v-stage>
        <div class="visually-hidden">
            <button
                v-for="seat in seats"
                :key="seat.id"
                type="button"
                :disabled="seat.status !== 'disponible'"
                :aria-pressed="seat.status === 'disponible' ? isSelected(seat) : undefined"
                :aria-label="`${seat.id}, ${statusLabel(seat.status)}`"
                @click="onSeat(seat)"
            >
                {{ seat.id }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { registerKonva } from "./registerKonva.js";

registerKonva();

const UNIT = 10;
const SEAT_W = 34;
const SEAT_H = 22;
const LABEL_SIZE = 22;

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

const props = defineProps({
    section: { type: Object, required: true },
    zoom: { type: Number, default: 1 },
    selectedKeys: { type: Array, default: () => [] },
});

const emit = defineEmits(["toggle"]);

const host = ref(null);

const labels = computed(() =>
    (props.section.labels ?? []).filter((label) => !/^\d+$/.test(String(label.name))),
);

const seats = computed(() =>
    props.section.rows.flatMap((row) => row.seats),
);

const layout = computed(() => {
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

    if (props.section.stage) {
        consider(props.section.stage.x1 * UNIT, props.section.stage.y1 * UNIT, 0, 0);
        consider(props.section.stage.x2 * UNIT, props.section.stage.y1 * UNIT + 18, 0, 0);
    }

    for (const label of labels.value) {
        consider(label.x * UNIT, label.y * UNIT, LABEL_SIZE / 2, LABEL_SIZE / 2);
    }

    for (const seat of seats.value) {
        consider(seat.x * UNIT, seat.y * UNIT, SEAT_W / 2, SEAT_H / 2);
    }

    const pad = 12;
    return {
        originX: minX - pad,
        originY: minY - pad,
        width: maxX - minX + pad * 2,
        height: maxY - minY + pad * 2,
    };
});

const stageWidth = computed(() =>
    Math.max(1, Math.round(layout.value.width * props.zoom)),
);
const stageHeight = computed(() =>
    Math.max(1, Math.round(layout.value.height * props.zoom)),
);

const stageConfig = computed(() => ({
    width: stageWidth.value,
    height: stageHeight.value,
    pixelRatio: window.devicePixelRatio || 1,
}));

const stageBarConfig = computed(() => {
    const stage = props.section.stage;
    if (!stage) return {};
    return {
        x: toX(stage.x1 * UNIT),
        y: toY(stage.y1 * UNIT),
        width: (stage.x2 - stage.x1) * UNIT * props.zoom,
        height: 18 * props.zoom,
        fill: "#f59e0b",
        cornerRadius: 3,
        listening: false,
    };
});

const stageBarTextConfig = computed(() => {
    const stage = props.section.stage;
    if (!stage) return {};
    const fontSize = 12 * props.zoom;
    return {
        x: toX(stage.x1 * UNIT),
        y: toY(stage.y1 * UNIT + 9) - fontSize / 2,
        width: (stage.x2 - stage.x1) * UNIT * props.zoom,
        text: "ESCENARIO",
        align: "center",
        fontSize,
        fontStyle: "bold",
        fill: "#ffffff",
        listening: false,
    };
});

function toX(x) {
    return (x - layout.value.originX) * props.zoom;
}

function toY(y) {
    return (y - layout.value.originY) * props.zoom;
}

function isSelected(seat) {
    return props.selectedKeys.includes(`${props.section.key}:${seat.id}`);
}

function statusLabel(status) {
    return STATUS_LABEL[status] || status;
}

function seatFill(seat) {
    if (isSelected(seat)) return "#166534";
    return STATUS_COLOR[seat.status] || STATUS_COLOR.no_disponible;
}

function seatTextFill(seat) {
    if (isSelected(seat) || seat.status !== "no_disponible") return "#ffffff";
    return "#1f2937";
}

function labelBoxConfig(label) {
    const size = LABEL_SIZE * props.zoom;
    return {
        x: toX(label.x * UNIT) - size / 2,
        y: toY(label.y * UNIT) - size / 2,
        width: size,
        height: size,
        fill: "#1f2937",
        cornerRadius: 2,
        listening: false,
    };
}

function labelTextConfig(label) {
    const size = LABEL_SIZE * props.zoom;
    const fontSize = 12 * props.zoom;
    return {
        x: toX(label.x * UNIT) - size / 2,
        y: toY(label.y * UNIT) - fontSize / 2,
        width: size,
        text: label.name,
        align: "center",
        fontSize,
        fontStyle: "bold",
        fill: "#ffffff",
        listening: false,
    };
}

function seatConfig(seat) {
    const selected = isSelected(seat);
    return {
        x: toX(seat.x * UNIT) - (SEAT_W * props.zoom) / 2,
        y: toY(seat.y * UNIT) - (SEAT_H * props.zoom) / 2,
        width: SEAT_W * props.zoom,
        height: SEAT_H * props.zoom,
        fill: seatFill(seat),
        stroke: selected ? "#4ecba0" : "transparent",
        strokeWidth: selected ? 3 * props.zoom : 0,
        cornerRadius: 2,
    };
}

function seatTextConfig(seat) {
    const fontSize = 9 * props.zoom;
    return {
        x: toX(seat.x * UNIT) - (SEAT_W * props.zoom) / 2,
        y: toY(seat.y * UNIT) - fontSize / 2,
        width: SEAT_W * props.zoom,
        text: seat.id,
        align: "center",
        fontSize,
        fontStyle: "bold",
        fill: seatTextFill(seat),
        listening: false,
    };
}

function onSeat(seat) {
    if (seat.status !== "disponible") return;
    emit("toggle", seat);
}

function setCursor(event, seat) {
    const stage = event.target.getStage();
    if (!stage) return;
    stage.container().style.cursor = seat.status === "disponible" ? "pointer" : "default";
}

function resetCursor(event) {
    const stage = event.target.getStage();
    if (stage) stage.container().style.cursor = "default";
}
</script>

<style scoped>
.konva-host {
    display: block;
}
</style>
