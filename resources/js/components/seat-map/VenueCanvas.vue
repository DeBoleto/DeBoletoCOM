<template>
    <div ref="host" class="venue-canvas" :class="{ 'is-grabbing': grabbing }">
        <div class="visually-hidden">
            <template v-if="activeKey === null">
                <button v-for="zone in zoneButtons" :key="zone.key" type="button" @click="emit('zone', zone.key)">
                    {{ zone.name }}
                </button>
            </template>
            <template v-else>
                <button
                    v-for="seat in activeSeats"
                    :key="seat.id"
                    type="button"
                    :disabled="seat.status !== 'disponible'"
                    :aria-pressed="seat.status === 'disponible' ? isSelected(seat) : undefined"
                    :aria-label="`Fila ${seat.fila}, asiento ${seat.id}, ${statusLabel(seat.status)}`"
                    @click="emit('toggle', seat)"
                >
                    {{ seat.id }}
                </button>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import Konva from "konva";
import { SEAT_STATUS, zoneSeatColors } from "./seatColors.js";

const GRID = 20;
const RADIUS_MAP = 6;
const RADIUS_ZONE = 8;
const MAX_FIT = { map: 1, zone: 2 };
const ZOOM_STEP = 1.25;

const STATUS_LABEL = {
    disponible: "Disponible",
    no_disponible: "No disponible",
    vendido: "Vendido",
    en_proceso: "En proceso de compra",
};

const props = defineProps({
    seats: { type: Array, required: true },
    zones: { type: Object, required: true },
    activeKey: { type: String, default: null },
    selectedKeys: { type: Array, default: () => [] },
});

const emit = defineEmits(["zone", "toggle", "hover", "scale"]);

const host = ref(null);
const grabbing = ref(false);

let stage = null;
let layer = null;
let observer = null;
let entrance = null;
let fitScale = 1;
let lastTap = { id: null, at: 0 };
let pinch = null;
const nodes = new Map();

const reduceMotion = typeof window !== "undefined" && window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

const activeSeats = computed(() => props.seats.filter((seat) => seat.key === props.activeKey));
const visibleSeats = computed(() => (props.activeKey === null ? props.seats : activeSeats.value));
const selected = computed(() => new Set(props.selectedKeys));
const index = computed(() => new Map(props.seats.map((seat) => [`${seat.key}:${seat.id}`, seat])));
const zoneButtons = computed(() =>
    Object.entries(props.zones)
        .filter(([, zone]) => zone.available && zone.hasSeats)
        .map(([key, zone]) => ({ key, name: zone.name })),
);

function statusLabel(status) {
    return STATUS_LABEL[status] ?? status;
}

function isSelected(seat) {
    return selected.value.has(`${seat.key}:${seat.id}`);
}

function colorsFor(seat) {
    return zoneSeatColors(props.zones[seat.key]?.color);
}

function position(seat) {
    return props.activeKey === null ? { x: seat.x * GRID, y: seat.y * GRID } : { x: seat.zx * GRID, y: seat.zy * GRID };
}

function fill(seat) {
    if (isSelected(seat)) return colorsFor(seat).selected;
    if (seat.status === "disponible") return colorsFor(seat).available;
    return (SEAT_STATUS[seat.status] ?? SEAT_STATUS.no_disponible).fill;
}

function build() {
    if (!stage) return;

    entrance?.stop();
    layer.destroyChildren();
    nodes.clear();

    const zoneMode = props.activeKey !== null;
    const radius = zoneMode ? RADIUS_ZONE : RADIUS_MAP;

    const areas = new Konva.Group({ listening: false });
    const dots = new Konva.Group();

    for (const seat of visibleSeats.value) {
        const { x, y } = position(seat);
        const color = props.zones[seat.key]?.color ?? "#94a3b8";

        areas.add(
            new Konva.Rect({
                x: x - GRID / 2,
                y: y - GRID / 2,
                width: GRID,
                height: GRID,
                fill: color,
                opacity: zoneMode ? 0.08 : 0.2,
                perfectDrawEnabled: false,
            }),
        );

        const circle = new Konva.Circle({
            x,
            y,
            radius,
            fill: fill(seat),
            stroke: seat.status === "en_proceso" ? SEAT_STATUS.en_proceso.stroke : "#ffffff",
            strokeWidth: seat.status === "en_proceso" ? 1.5 : zoneMode ? 2 : 1.5,
            dash: seat.status === "en_proceso" ? [3, 2] : undefined,
            perfectDrawEnabled: false,
            shadowForStrokeEnabled: false,
            name: "seat",
            hitFunc(context, shape) {
                context.beginPath();
                context.arc(0, 0, GRID / 2, 0, Math.PI * 2);
                context.closePath();
                context.fillStrokeShape(shape);
            },
        });

        const mark = new Konva.Text({
            x: x - radius,
            y: y - radius * 0.62,
            width: radius * 2,
            align: "center",
            text: "✓",
            fontSize: radius * 1.3,
            fontStyle: "bold",
            fill: "#ffffff",
            listening: false,
            visible: zoneMode && isSelected(seat),
        });

        circle.on("click tap", (event) => onSeat(event, seat));
        circle.on("mouseenter", (event) => enter(event, seat, circle));
        circle.on("mouseleave", () => leave(seat, circle));

        dots.add(circle, mark);
        nodes.set(`${seat.key}:${seat.id}`, { circle, mark, seat });
    }

    layer.add(areas, dots);
    fit();
    animateEntrance();
}

function latest(seat) {
    return index.value.get(`${seat.key}:${seat.id}`) ?? seat;
}

function paint() {
    const zoneMode = props.activeKey !== null;

    for (const { circle, mark, seat } of nodes.values()) {
        const current = latest(seat);
        circle.fill(fill(current));
        circle.stroke(current.status === "en_proceso" ? SEAT_STATUS.en_proceso.stroke : "#ffffff");
        circle.dash(current.status === "en_proceso" ? [3, 2] : []);
        mark.visible(zoneMode && isSelected(current));
    }

    layer?.batchDraw();
}

function contentBox() {
    return layer.getClientRect({ relativeTo: layer, skipShadow: true, skipStroke: true });
}

function fit() {
    if (!stage) return;

    const box = contentBox();
    if (!box.width || !box.height) return;

    const margin = 0.92;
    const limit = props.activeKey === null ? MAX_FIT.map : MAX_FIT.zone;
    fitScale = Math.min((stage.width() * margin) / box.width, (stage.height() * margin) / box.height, limit);

    stage.scale({ x: fitScale, y: fitScale });
    stage.position({
        x: (stage.width() - box.width * fitScale) / 2 - box.x * fitScale,
        y: (stage.height() - box.height * fitScale) / 2 - box.y * fitScale,
    });
    stage.batchDraw();
    emit("scale", 1);
}

function zoomAt(next, point) {
    if (!stage) return;

    const min = fitScale * 0.8;
    const max = Math.max(fitScale * 6, 3);
    const scale = Math.max(min, Math.min(max, next));
    const old = stage.scaleX();
    const anchor = { x: (point.x - stage.x()) / old, y: (point.y - stage.y()) / old };

    stage.scale({ x: scale, y: scale });
    stage.position({ x: point.x - anchor.x * scale, y: point.y - anchor.y * scale });
    stage.batchDraw();
    emit("scale", scale / fitScale);
}

function center() {
    return { x: stage.width() / 2, y: stage.height() / 2 };
}

function zoomIn() {
    zoomAt(stage.scaleX() * ZOOM_STEP, center());
}

function zoomOut() {
    zoomAt(stage.scaleX() / ZOOM_STEP, center());
}

function onSeat(event, seat) {
    event.cancelBubble = true;
    const id = `${seat.key}:${seat.id}`;
    const now = Date.now();
    if (lastTap.id === id && now - lastTap.at < 400) return;
    lastTap = { id, at: now };

    if (props.activeKey === null) {
        if (props.zones[seat.key]?.available) emit("zone", seat.key);
        return;
    }

    const current = latest(seat);
    if (current.status !== "disponible") return;

    emit("toggle", current);
    if (!isSelected(current)) ripple(nodes.get(id)?.circle, colorsFor(current).glow);
}

function enter(event, stale, circle) {
    const seat = latest(stale);
    const clickable = props.activeKey === null ? props.zones[seat.key]?.available : seat.status === "disponible";
    stage.container().style.cursor = clickable ? "pointer" : "default";

    if (props.activeKey !== null && clickable && !reduceMotion) {
        circle.to({ scaleX: 1.35, scaleY: 1.35, duration: 0.15 });
    }

    emit("hover", { seat, zoneMode: props.activeKey !== null, clientX: event.evt?.clientX ?? 0, clientY: event.evt?.clientY ?? 0 });
}

function leave(seat, circle) {
    stage.container().style.cursor = "default";
    if (props.activeKey !== null && !reduceMotion) circle.to({ scaleX: 1, scaleY: 1, duration: 0.15 });
    emit("hover", null);
}

function ripple(circle, color) {
    if (!circle || reduceMotion) return;

    circle.scale({ x: 1.7, y: 1.7 });
    circle.to({ scaleX: 1, scaleY: 1, duration: 0.5, easing: Konva.Easings.ElasticEaseOut });

    const wave = new Konva.Circle({
        x: circle.x(),
        y: circle.y(),
        radius: circle.radius(),
        stroke: color,
        strokeWidth: 3,
        listening: false,
    });
    circle.getParent().add(wave);
    wave.to({
        radius: circle.radius() * 3,
        strokeWidth: 0.5,
        opacity: 0,
        duration: 0.55,
        easing: Konva.Easings.EaseOut,
        onFinish: () => wave.destroy(),
    });
}

function easeOutBack(t) {
    const c1 = 1.70158;
    return 1 + (c1 + 1) * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2);
}

function animateEntrance() {
    const circles = layer.find(".seat");
    if (!circles.length || reduceMotion || circles.length > 4000) return;

    let minY = Infinity;
    let maxY = -Infinity;
    circles.forEach((circle) => {
        minY = Math.min(minY, circle.y());
        maxY = Math.max(maxY, circle.y());
    });
    const span = maxY - minY || 1;

    circles.forEach((circle) => {
        circle.setAttr("delay", ((circle.y() - minY) / span) * 500);
        circle.scale({ x: 0, y: 0 });
    });

    entrance = new Konva.Animation((frame) => {
        let done = true;
        circles.forEach((circle) => {
            const progress = Math.min(1, Math.max(0, (frame.time - circle.getAttr("delay")) / 360));
            if (progress < 1) done = false;
            const scale = easeOutBack(progress);
            circle.scale({ x: scale, y: scale });
        });
        if (done) entrance.stop();
    }, layer);
    entrance.start();
}

function distance(a, b) {
    return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
}

function onTouchMove(event) {
    const touches = event.evt.touches;
    if (touches.length !== 2) return;

    event.evt.preventDefault();
    stage.draggable(false);

    const rect = stage.container().getBoundingClientRect();
    const midpoint = {
        x: (touches[0].clientX + touches[1].clientX) / 2 - rect.left,
        y: (touches[0].clientY + touches[1].clientY) / 2 - rect.top,
    };
    const gap = distance(touches[0], touches[1]);

    if (pinch) zoomAt(stage.scaleX() * (gap / pinch), midpoint);
    pinch = gap;
}

function onTouchEnd() {
    pinch = null;
    stage.draggable(true);
}

function onWheel(event) {
    event.evt.preventDefault();
    const pointer = stage.getPointerPosition();
    if (!pointer) return;
    zoomAt(stage.scaleX() * (event.evt.deltaY > 0 ? 1 / 1.1 : 1.1), pointer);
}

function resize() {
    if (!stage || !host.value) return;
    const { clientWidth, clientHeight } = host.value;
    if (!clientWidth || !clientHeight) return;
    if (clientWidth === stage.width() && clientHeight === stage.height()) return;
    stage.size({ width: clientWidth, height: clientHeight });
    fit();
}

watch(() => props.activeKey, build);
watch(() => props.seats.map((seat) => seat.key + seat.id).join("|"), build);
watch([() => props.seats, () => props.selectedKeys], paint);

onMounted(() => {
    stage = new Konva.Stage({
        container: host.value,
        width: host.value.clientWidth || 320,
        height: host.value.clientHeight || 320,
        draggable: true,
    });
    layer = new Konva.Layer();
    stage.add(layer);

    stage.on("dragstart", () => (grabbing.value = true));
    stage.on("dragend", () => (grabbing.value = false));
    stage.on("wheel", onWheel);
    stage.on("touchmove", onTouchMove);
    stage.on("touchend touchcancel", onTouchEnd);

    build();

    if (typeof ResizeObserver !== "undefined") {
        observer = new ResizeObserver(resize);
        observer.observe(host.value);
    }
});

onBeforeUnmount(() => {
    observer?.disconnect();
    entrance?.stop();
    stage?.destroy();
});

defineExpose({ zoomIn, zoomOut, reset: fit });
</script>

<style scoped>
.venue-canvas {
    position: relative;
    width: 100%;
    height: 100%;
    touch-action: none;
    cursor: grab;
    user-select: none;
    -webkit-user-select: none;
}

.venue-canvas.is-grabbing {
    cursor: grabbing;
}
</style>
