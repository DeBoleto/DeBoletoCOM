<template>
    <div ref="host" class="konva-host" :class="{ 'konva-host--dots': dots }" :style="{ width: `${stageWidth}px`, height: `${stageHeight}px` }">
        <v-stage :config="stageConfig">
            <v-layer>
                <v-rect v-if="section.stage" :config="stageBarConfig" />
                <v-text v-if="section.stage" :config="stageBarTextConfig" />
                <template v-if="!dots">
                    <v-rect
                        v-for="label in labels"
                        :key="`label-${label.x}-${label.y}-${label.name}`"
                        :config="labelBoxConfig(label)"
                    />
                </template>
                <v-text
                    v-for="label in labels"
                    :key="`label-text-${label.x}-${label.y}-${label.name}`"
                    :config="labelTextConfig(label)"
                />
                <template v-if="dots">
                    <v-circle
                        v-for="seat in seats"
                        :key="seat.id"
                        :config="dotConfig(seat)"
                        @click="onSeat(seat)"
                        @tap="onSeat(seat)"
                        @mouseenter="(event) => enter(event, seat)"
                        @mouseleave="leave"
                    />
                </template>
                <template v-else>
                    <v-rect
                        v-for="seat in seats"
                        :key="seat.id"
                        :config="seatConfig(seat)"
                        @click="onSeat(seat)"
                        @tap="onSeat(seat)"
                        @mouseenter="(event) => enter(event, seat)"
                        @mouseleave="leave"
                    />
                </template>
                <template v-if="showSeatText">
                    <v-text
                        v-for="seat in seats"
                        :key="`${seat.id}-label`"
                        :config="seatTextConfig(seat)"
                    />
                </template>
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
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { registerKonva } from "./registerKonva.js";
import { SEAT_STATUS, zoneSeatColors } from "./seatColors.js";

registerKonva();

const UNIT = 10;
const SEAT_W = 34;
const SEAT_H = 22;
const LABEL_SIZE = 22;
const PITCH = 34;
const MIN_PITCH = 20;
const MAX_PITCH = 46;
const DOT_RATIO = 0.68;

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
    variant: { type: String, default: "blocks" },
});

const emit = defineEmits(["toggle", "hover"]);

const host = ref(null);
const hoveredId = ref(null);
const viewport = ref({ width: 0, height: 0 });
let observer = null;
let centered = false;

const dots = computed(() => props.variant === "dots");
const palette = computed(() => zoneSeatColors(props.section.areaColor || props.section.color));

const labels = computed(() =>
    (props.section.labels ?? []).filter((label) => !/^\d+$/.test(String(label.name))),
);

const seats = computed(() =>
    props.section.rows.flatMap((row) => row.seats),
);

const seatNumbers = computed(() => {
    const numbers = {};
    for (const row of props.section.rows) {
        const fila = String(row.fila ?? "");
        for (const seat of row.seats) {
            const id = String(seat.id);
            if (seat.selectable === false) numbers[seat.id] = "♿";
            else if (fila && id.startsWith(fila) && id.length > fila.length) numbers[seat.id] = id.slice(fila.length);
            else numbers[seat.id] = id;
        }
    }
    return numbers;
});

function median(values) {
    if (!values.length) return null;
    const sorted = [...values].sort((a, b) => a - b);
    return sorted[Math.floor(sorted.length / 2)];
}

const gridPitch = computed(() => {
    const xGaps = [];
    for (const row of props.section.rows) {
        const xs = row.seats.map((seat) => seat.x).sort((a, b) => a - b);
        for (let index = 1; index < xs.length; index++) {
            if (xs[index] > xs[index - 1]) xGaps.push(xs[index] - xs[index - 1]);
        }
    }

    const ys = [...new Set(props.section.rows.map((row) => row.y))].sort((a, b) => a - b);
    const yGaps = [];
    for (let index = 1; index < ys.length; index++) yGaps.push(ys[index] - ys[index - 1]);

    const candidates = [median(xGaps), median(yGaps)].filter((gap) => gap > 0);
    return candidates.length ? Math.min(...candidates) : 1;
});

const bounds = computed(() => {
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    const consider = (x, y) => {
        minX = Math.min(minX, x);
        minY = Math.min(minY, y);
        maxX = Math.max(maxX, x);
        maxY = Math.max(maxY, y);
    };

    if (props.section.stage) {
        consider(props.section.stage.x1, props.section.stage.y1);
        consider(props.section.stage.x2, props.section.stage.y1);
    }

    for (const label of labels.value) consider(label.x, label.y);
    for (const seat of seats.value) consider(seat.x, seat.y);

    if (minX === Infinity) return { minX: 0, minY: 0, maxX: 4, maxY: 4 };
    return { minX, minY, maxX, maxY };
});

const fitScale = computed(() => {
    const base = PITCH / gridPitch.value;
    const { width, height } = viewport.value;
    if (!width || !height) return base;

    const spanX = (bounds.value.maxX - bounds.value.minX) * base + PITCH * 2 + 24;
    const spanY = (bounds.value.maxY - bounds.value.minY) * base + PITCH * 2 + 24;
    const fit = Math.min((width - 24) / spanX, (height - 24) / spanY);
    const pitch = Math.min(MAX_PITCH, Math.max(MIN_PITCH, PITCH * fit));
    return pitch / gridPitch.value;
});

const scale = computed(() =>
    dots.value ? fitScale.value * props.zoom : UNIT * props.zoom,
);

const pitchPx = computed(() => gridPitch.value * scale.value);
const dotRadius = computed(() => (pitchPx.value * DOT_RATIO) / 2);

const margin = computed(() =>
    (dots.value ? pitchPx.value : (SEAT_W / 2) * props.zoom) + 12,
);

const stageBarHeight = computed(() =>
    dots.value ? Math.max(18, pitchPx.value * 0.7) : 18 * props.zoom,
);

const stageGap = computed(() => {
    const stage = props.section.stage;
    if (!dots.value || !stage) return 0;
    const below = [...seats.value, ...labels.value].map((item) => item.y).filter((y) => y > stage.y1);
    if (!below.length) return 0;
    const room = (Math.min(...below) - stage.y1) * scale.value - dotRadius.value;
    return Math.max(0, stageBarHeight.value + pitchPx.value * 0.5 - room);
});

const stageWidth = computed(() =>
    Math.max(1, Math.round((bounds.value.maxX - bounds.value.minX) * scale.value + margin.value * 2)),
);
const stageHeight = computed(() =>
    Math.max(1, Math.round((bounds.value.maxY - bounds.value.minY) * scale.value + margin.value * 2 + (props.section.stage ? (dots.value ? stageGap.value : stageBarHeight.value) : 0))),
);

const stageConfig = computed(() => ({
    width: stageWidth.value,
    height: stageHeight.value,
    pixelRatio: window.devicePixelRatio || 1,
}));

const stageBarConfig = computed(() => {
    const stage = props.section.stage;
    if (!stage) return {};
    const base = {
        x: toX(stage.x1),
        y: toY(stage.y1),
        width: (stage.x2 - stage.x1) * scale.value,
        height: stageBarHeight.value,
        listening: false,
    };
    if (!dots.value) return { ...base, fill: "#f59e0b", cornerRadius: 3 };
    return {
        ...base,
        cornerRadius: stageBarHeight.value / 2,
        fillLinearGradientStartPoint: { x: 0, y: 0 },
        fillLinearGradientEndPoint: { x: base.width, y: 0 },
        fillLinearGradientColorStops: [0, "#1e293b", 0.5, "#334155", 1, "#1e293b"],
        shadowColor: "#0f172a",
        shadowBlur: 12,
        shadowOpacity: 0.18,
        shadowOffsetY: 4,
    };
});

const stageBarTextConfig = computed(() => {
    const stage = props.section.stage;
    if (!stage) return {};
    const fontSize = dots.value ? Math.max(10, stageBarHeight.value * 0.48) : 12 * props.zoom;
    return {
        x: toX(stage.x1),
        y: toY(stage.y1) + stageBarHeight.value / 2 - fontSize / 2,
        width: (stage.x2 - stage.x1) * scale.value,
        text: "ESCENARIO",
        align: "center",
        fontSize,
        fontStyle: "bold",
        letterSpacing: dots.value ? fontSize * 0.25 : 0,
        fill: "#ffffff",
        listening: false,
    };
});

const showSeatText = computed(() => !dots.value || dotRadius.value * 2 >= 13);

function toX(x) {
    return (x - bounds.value.minX) * scale.value + margin.value;
}

function toY(y) {
    const shifted = props.section.stage && y > props.section.stage.y1 ? stageGap.value : 0;
    return (y - bounds.value.minY) * scale.value + margin.value + shifted;
}

function isSelected(seat) {
    return props.selectedKeys.includes(`${props.section.key}:${seat.id}`);
}

function statusLabel(status) {
    return STATUS_LABEL[status] || status;
}

function seatFill(seat) {
    if (isSelected(seat)) return palette.value.selected;
    if (seat.status === "disponible") return palette.value.available;
    return (SEAT_STATUS[seat.status] ?? SEAT_STATUS.no_disponible).fill;
}

function seatTextFill(seat) {
    if (isSelected(seat)) return palette.value.selectedText;
    if (seat.status === "disponible") return palette.value.availableText;
    return (SEAT_STATUS[seat.status] ?? SEAT_STATUS.no_disponible).text;
}

function labelBoxConfig(label) {
    const size = LABEL_SIZE * props.zoom;
    return {
        x: toX(label.x) - size / 2,
        y: toY(label.y) - size / 2,
        width: size,
        height: size,
        fill: "#1f2937",
        cornerRadius: 2,
        listening: false,
    };
}

function labelTextConfig(label) {
    const size = dots.value ? pitchPx.value : LABEL_SIZE * props.zoom;
    const fontSize = dots.value ? Math.max(10, pitchPx.value * 0.4) : 12 * props.zoom;
    return {
        x: toX(label.x) - size / 2,
        y: toY(label.y) - fontSize / 2,
        width: size,
        text: label.name,
        align: "center",
        fontSize,
        fontStyle: "bold",
        fill: dots.value ? "#64748b" : "#ffffff",
        listening: false,
    };
}

function seatConfig(seat) {
    const selected = isSelected(seat);
    return {
        x: toX(seat.x) - (SEAT_W * props.zoom) / 2,
        y: toY(seat.y) - (SEAT_H * props.zoom) / 2,
        width: SEAT_W * props.zoom,
        height: SEAT_H * props.zoom,
        fill: seatFill(seat),
        stroke: selected ? palette.value.glow : "transparent",
        strokeWidth: selected ? 3 * props.zoom : 0,
        cornerRadius: 2,
    };
}

function dotConfig(seat) {
    const selected = isSelected(seat);
    const available = seat.status === "disponible";
    const hovered = hoveredId.value === seat.id && available;
    const grow = selected ? 1.12 : hovered ? 1.18 : 1;
    const outline = !selected && !available ? SEAT_STATUS[seat.status]?.stroke : null;

    return {
        x: toX(seat.x),
        y: toY(seat.y),
        radius: dotRadius.value * grow,
        fill: seatFill(seat),
        stroke: selected ? palette.value.glow : hovered ? palette.value.hover : (outline ?? undefined),
        strokeWidth: selected ? Math.max(2, dotRadius.value * 0.2) : hovered ? 2 : outline ? 1.5 : 0,
        dash: outline ? [3, 2] : undefined,
        shadowColor: selected ? palette.value.glow : "#0f172a",
        shadowBlur: selected ? dotRadius.value * 1.4 : hovered ? 8 : 0,
        shadowOpacity: selected ? 0.75 : 0.25,
        shadowOffsetY: hovered && !selected ? 2 : 0,
    };
}

function seatTextConfig(seat) {
    if (!dots.value) {
        const fontSize = 9 * props.zoom;
        return {
            x: toX(seat.x) - (SEAT_W * props.zoom) / 2,
            y: toY(seat.y) - fontSize / 2,
            width: SEAT_W * props.zoom,
            text: seat.id,
            align: "center",
            fontSize,
            fontStyle: "bold",
            fill: seatTextFill(seat),
            listening: false,
        };
    }

    const text = seatNumbers.value[seat.id] ?? seat.id;
    const diameter = dotRadius.value * 2;
    const fontSize = Math.min(diameter * 0.46, (diameter * 1.25) / Math.max(2, text.length));
    return {
        x: toX(seat.x) - diameter / 2,
        y: toY(seat.y) - fontSize / 2 + 0.5,
        width: diameter,
        text,
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

function enter(event, seat) {
    setCursor(event, seat);
    hoveredId.value = seat.id;
    emit("hover", { seat, clientX: event.evt?.clientX ?? 0, clientY: event.evt?.clientY ?? 0 });
}

function leave(event) {
    resetCursor(event);
    hoveredId.value = null;
    emit("hover", null);
}

function scroller() {
    return host.value?.parentElement ?? null;
}

function center() {
    const element = scroller();
    if (!element) return;
    centered = true;
    element.scrollLeft = (element.scrollWidth - element.clientWidth) / 2;
    element.scrollTop = (element.scrollHeight - element.clientHeight) / 2;
}

function measure() {
    const element = scroller();
    if (!element) return;
    viewport.value = { width: element.clientWidth, height: element.clientHeight };
}

watch(scale, async (next, previous) => {
    const element = scroller();
    if (!element || !previous || !centered) return;
    const ratioX = (element.scrollLeft + element.clientWidth / 2) / Math.max(1, element.scrollWidth);
    const ratioY = (element.scrollTop + element.clientHeight / 2) / Math.max(1, element.scrollHeight);
    await nextTick();
    element.scrollLeft = ratioX * element.scrollWidth - element.clientWidth / 2;
    element.scrollTop = ratioY * element.scrollHeight - element.clientHeight / 2;
}, { flush: "pre" });

onMounted(async () => {
    if (!dots.value) return;
    measure();
    await nextTick();
    requestAnimationFrame(center);
    const element = scroller();
    if (element && typeof ResizeObserver !== "undefined") {
        observer = new ResizeObserver(measure);
        observer.observe(element);
    }
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<style scoped>
.konva-host {
    display: block;
}

.konva-host--dots {
    flex-shrink: 0;
    margin: auto;
}
</style>
