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
                    :disabled="readonly || seat.status !== 'disponible'"
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
const DIRECT_ON = 3;
const DIRECT_OFF = 2;
const DOUBLE_ZOOM = 3.5;
const DOUBLE_TAP_MS = 300;

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
    mainStage: { type: Object, default: null },
    areas: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(["zone", "toggle", "hover", "scale", "direct"]);

const host = ref(null);
const grabbing = ref(false);
const direct = ref(false);

let stage = null;
let layer = null;
let observer = null;
let entrance = null;
let fitScale = 1;
let lastTap = { id: null, at: 0 };
let pinch = null;
let pendingZone = null;
let lastMapTap = { at: 0, x: 0, y: 0 };
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

function seatMode() {
    return props.activeKey !== null || direct.value;
}

function updateDirect(ratio) {
    const next = !props.readonly && props.activeKey === null && (ratio >= DIRECT_ON || (direct.value && ratio > DIRECT_OFF));
    if (next === direct.value) return;

    direct.value = next;
    emit("direct", next);
    paint();
}

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

    const areas = new Konva.Group({ listening: false });
    const dots = new Konva.Group();

    if (!zoneMode) {
        for (const area of props.areas) addArea(areas, area);
        if (props.mainStage) addMainStage(areas, props.mainStage);
    }

    for (const seat of visibleSeats.value) {
        const { x, y } = position(seat);
        const color = props.zones[seat.key]?.color ?? "#94a3b8";
        const size = zoneMode ? 1 : (seat.size ?? 1);
        const cell = GRID * size;
        const radius = zoneMode ? RADIUS_ZONE : RADIUS_MAP * size;

        // Con el área dibujada detrás, la celda de cada asiento sobra en el mapa completo.
        if (zoneMode || !seat.inArea) {
            areas.add(
                new Konva.Rect({
                    x: x - cell / 2,
                    y: y - cell / 2,
                    width: cell,
                    height: cell,
                    fill: color,
                    opacity: zoneMode ? 0.08 : 0.2,
                    perfectDrawEnabled: false,
                }),
            );
        }

        const circle = new Konva.Circle({
            x,
            y,
            radius,
            fill: fill(seat),
            stroke: seat.status === "en_proceso" ? SEAT_STATUS.en_proceso.stroke : "#ffffff",
            strokeWidth: (seat.status === "en_proceso" ? 1.5 : zoneMode ? 2 : 1.5) * Math.sqrt(size),
            dash: seat.status === "en_proceso" ? [3, 2] : undefined,
            perfectDrawEnabled: false,
            shadowForStrokeEnabled: false,
            name: "seat",
            hitFunc(context, shape) {
                context.beginPath();
                context.arc(0, 0, cell / 2, 0, Math.PI * 2);
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
            visible: seatMode() && isSelected(seat),
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

// Las áreas vienen en px del mapa general, igual que el escenario.
function addArea(group, area) {
    const color = props.zones[area.key]?.color ?? "#94a3b8";

    group.add(
        new Konva.Line({
            points: area.polygon.flatMap((point) => [point.x, point.y]),
            closed: true,
            fill: color,
            opacity: props.zones[area.key]?.available === false ? 0.08 : 0.16,
            stroke: color,
            strokeWidth: 3,
            lineJoin: "round",
            perfectDrawEnabled: false,
        }),
    );
}

// El escenario viene en px del mapa general, la misma escala que los asientos (1 celda = GRID px).
function addMainStage(group, bar) {
    const label = bar.label || "ESCENARIO";
    const fontSize = Math.min(bar.height * 0.4, (bar.width / Math.max(label.length, 1)) * 1.4);

    group.add(
        new Konva.Rect({
            x: bar.x,
            y: bar.y,
            width: bar.width,
            height: bar.height,
            fill: "#000000",
            cornerRadius: 4,
        }),
        new Konva.Text({
            x: bar.x,
            y: bar.y + bar.height / 2 - fontSize / 2,
            width: bar.width,
            text: label,
            align: "center",
            fontSize,
            fontStyle: "bold",
            fill: "#ffffff",
        }),
    );
}

function latest(seat) {
    return index.value.get(`${seat.key}:${seat.id}`) ?? seat;
}

function paint() {
    const marks = seatMode();

    for (const { circle, mark, seat } of nodes.values()) {
        const current = latest(seat);
        circle.fill(fill(current));
        circle.stroke(current.status === "en_proceso" ? SEAT_STATUS.en_proceso.stroke : "#ffffff");
        circle.dash(current.status === "en_proceso" ? [3, 2] : []);
        mark.visible(marks && isSelected(current));
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
    updateDirect(1);
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
    updateDirect(scale / fitScale);
}

function center() {
    return { x: stage.width() / 2, y: stage.height() / 2 };
}

function zoomToPoint(pointer) {
    const old = stage.scaleX();
    const scale = fitScale * DOUBLE_ZOOM;
    const world = { x: (pointer.x - stage.x()) / old, y: (pointer.y - stage.y()) / old };
    const target = { x: stage.width() / 2 - world.x * scale, y: stage.height() / 2 - world.y * scale };
    const done = () => {
        emit("scale", DOUBLE_ZOOM);
        updateDirect(DOUBLE_ZOOM);
    };

    if (reduceMotion) {
        stage.scale({ x: scale, y: scale });
        stage.position(target);
        stage.batchDraw();
        done();
        return;
    }

    stage.to({ scaleX: scale, scaleY: scale, x: target.x, y: target.y, duration: 0.35, easing: Konva.Easings.EaseInOut, onFinish: done });
}

function zoomIn() {
    zoomAt(stage.scaleX() * ZOOM_STEP, center());
}

function zoomOut() {
    zoomAt(stage.scaleX() / ZOOM_STEP, center());
}

// En el mapa completo un toque abre la zona y dos toques seguidos acercan el
// mapa donde se tocó: se espera a ver si llega el segundo toque antes de abrirla.
function mapTap(key) {
    const pointer = stage.getPointerPosition();
    const now = Date.now();
    const second = pointer && now - lastMapTap.at < DOUBLE_TAP_MS && Math.hypot(pointer.x - lastMapTap.x, pointer.y - lastMapTap.y) < 40;

    window.clearTimeout(pendingZone);
    pendingZone = null;

    if (second) {
        lastMapTap = { at: 0, x: 0, y: 0 };
        zoomToPoint(pointer);
        return;
    }

    lastMapTap = { at: now, x: pointer?.x ?? 0, y: pointer?.y ?? 0 };

    if (key !== null && props.zones[key]?.available) {
        pendingZone = window.setTimeout(() => {
            pendingZone = null;
            emit("zone", key);
        }, DOUBLE_TAP_MS);
    }
}

function onStageTap(event) {
    if (seatMode() || pinch) return;
    if (event.target !== stage && event.target.name() === "seat") return;
    mapTap(null);
}

function onSeat(event, seat) {
    event.cancelBubble = true;

    if (!seatMode()) {
        mapTap(seat.key);
        return;
    }

    const id = `${seat.key}:${seat.id}`;
    const now = Date.now();
    if (lastTap.id === id && now - lastTap.at < 400) return;
    lastTap = { id, at: now };

    const current = latest(seat);
    if (props.readonly || current.status !== "disponible" || !props.zones[seat.key]?.available) return;

    emit("toggle", current);
    if (!isSelected(current)) ripple(nodes.get(id)?.circle, colorsFor(current).glow);
}

function enter(event, stale, circle) {
    const seat = latest(stale);
    const picking = seatMode();
    const available = props.zones[seat.key]?.available;
    const clickable = picking ? !props.readonly && available && seat.status === "disponible" : available;
    stage.container().style.cursor = clickable ? "pointer" : "default";

    if (picking && clickable && !reduceMotion) {
        circle.to({ scaleX: 1.35, scaleY: 1.35, duration: 0.15 });
    }

    emit("hover", { seat, zoneMode: picking, clientX: event.evt?.clientX ?? 0, clientY: event.evt?.clientY ?? 0 });
}

function leave(seat, circle) {
    stage.container().style.cursor = "default";
    if (seatMode() && !reduceMotion) circle.to({ scaleX: 1, scaleY: 1, duration: 0.15 });
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
watch(() => [props.mainStage, props.areas], build);
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
    stage.on("click tap", onStageTap);

    build();

    if (typeof ResizeObserver !== "undefined") {
        observer = new ResizeObserver(resize);
        observer.observe(host.value);
    }
});

onBeforeUnmount(() => {
    window.clearTimeout(pendingZone);
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
