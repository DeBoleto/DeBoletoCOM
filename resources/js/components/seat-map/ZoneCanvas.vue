<template>
    <div
        ref="host"
        class="konva-host"
        :style="{ width: `${stageWidth}px`, height: `${stageHeight}px` }"
    >
        <v-stage :config="stageConfig">
            <v-layer>
                <v-rect v-if="frame.stageBar" :config="stageBarConfig" />
                <v-text v-if="frame.stageBar" :config="stageBarTextConfig" />
                <v-line
                    v-for="section in drawable"
                    :key="section.key"
                    :config="polygonConfig(section)"
                    @click="emit('select', section.key)"
                    @tap="emit('select', section.key)"
                    @mouseenter="setCursor"
                    @mouseleave="resetCursor"
                />
                <v-text
                    v-for="section in drawable"
                    :key="`${section.key}-label`"
                    :config="labelConfig(section)"
                />
            </v-layer>
        </v-stage>
        <div class="visually-hidden">
            <button
                v-for="section in drawable"
                :key="section.key"
                type="button"
                :aria-pressed="section.key === activeKey"
                :aria-label="zoneAriaLabel(section)"
                @click="emit('select', section.key)"
            >
                {{ section.name }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { registerKonva } from "./registerKonva.js";

registerKonva();

const props = defineProps({
    sections: { type: Array, required: true },
    activeKey: { type: String, default: null },
    zoom: { type: Number, default: 1 },
    canvasSize: { type: Number, default: 2000 },
});

const emit = defineEmits(["select"]);

const host = ref(null);
const fitWidth = ref(640);
const fitHeight = ref(280);
let observer;

const drawable = computed(() =>
    props.sections.filter((section) => section.polygon.length >= 3),
);

const frame = computed(() => {
    if (!drawable.value.length) {
        const size = props.canvasSize || 2000;
        return {
            minX: 0,
            minY: 0,
            width: size,
            height: size,
            stageBar: null,
        };
    }

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (const section of drawable.value) {
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
        minX: frameMinX,
        minY: frameMinY,
        width: frameMaxX - frameMinX,
        height: frameMaxY - frameMinY,
        stageBar: { x: barX, y: barY, width: barWidth, height: barHeight },
    };
});

const scale = computed(() => {
    if (!frame.value.width || !frame.value.height) return 1;
    const contain = Math.min(
        fitWidth.value / frame.value.width,
        fitHeight.value / frame.value.height,
    );
    return contain * props.zoom;
});

const fittedWidth = computed(() => frame.value.width * scale.value);
const fittedHeight = computed(() => frame.value.height * scale.value);

const stageWidth = computed(() => {
    const drawn = Math.max(1, Math.floor(fittedWidth.value));
    if (props.zoom > 1) return drawn;
    return Math.max(drawn, Math.floor(fitWidth.value));
});
const stageHeight = computed(() => {
    const drawn = Math.max(1, Math.floor(fittedHeight.value));
    if (props.zoom > 1) return drawn;
    return Math.max(drawn, Math.floor(fitHeight.value));
});

const offsetX = computed(() =>
    props.zoom > 1 ? 0 : (stageWidth.value - fittedWidth.value) / 2,
);
const offsetY = computed(() =>
    props.zoom > 1 ? 0 : (stageHeight.value - fittedHeight.value) / 2,
);

const stageConfig = computed(() => ({
    width: stageWidth.value,
    height: stageHeight.value,
    pixelRatio: window.devicePixelRatio || 1,
}));

const stageBarConfig = computed(() => {
    const bar = frame.value.stageBar;
    if (!bar) return {};
    return {
        x: px(bar.x),
        y: py(bar.y),
        width: bar.width * scale.value,
        height: bar.height * scale.value,
        fill: "#1d4ed8",
        cornerRadius: 4,
        listening: false,
    };
});

const stageBarTextConfig = computed(() => {
    const bar = frame.value.stageBar;
    if (!bar) return {};
    const fontSize = bar.height * scale.value * 0.34;
    return {
        x: px(bar.x),
        y: py(bar.y + bar.height / 2) - fontSize / 2,
        width: bar.width * scale.value,
        text: "ESCENARIO",
        align: "center",
        fontSize,
        fontStyle: "bold",
        fill: "#ffffff",
        listening: false,
    };
});

function px(x) {
    return (x - frame.value.minX) * scale.value + offsetX.value;
}

function py(y) {
    return (y - frame.value.minY) * scale.value + offsetY.value;
}

function polygonConfig(section) {
    const active = section.key === props.activeKey;
    return {
        points: section.polygon.flatMap((point) => [px(point.x), py(point.y)]),
        closed: true,
        fill: section.areaColor,
        stroke: active ? "#4ecba0" : "#334155",
        strokeWidth: (active ? 16 : 4) * scale.value,
        lineJoin: "round",
    };
}

function labelConfig(section) {
    const xs = section.polygon.map((point) => point.x);
    const ys = section.polygon.map((point) => point.y);
    const width = Math.max(...xs) - Math.min(...xs);
    const height = Math.max(...ys) - Math.min(...ys);
    const byWidth = (width / Math.max(section.name.length, 1)) * 1.35;
    const byHeight = height * 0.18;
    const fontSize = Math.max(18, Math.min(64, byWidth, byHeight)) * scale.value;
    const center = centroid(section.polygon);

    return {
        x: px(Math.min(...xs)),
        y: py(center.y) - fontSize / 2,
        width: width * scale.value,
        text: section.name,
        align: "center",
        fontSize,
        fontStyle: "bold",
        fill: fillTextColor(section.areaColor),
        listening: false,
    };
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

function fillTextColor(hex) {
    const value = String(hex || "").replace("#", "");
    if (value.length !== 6) return "#ffffff";
    const red = parseInt(value.slice(0, 2), 16);
    const green = parseInt(value.slice(2, 4), 16);
    const blue = parseInt(value.slice(4, 6), 16);
    const luminance = (0.299 * red + 0.587 * green + 0.114 * blue) / 255;
    return luminance > 0.62 ? "#111827" : "#ffffff";
}

function zonePriceLabel(section) {
    if (!section?.price) return "Precio no disponible";
    const current = section.price.discountPrice ?? section.price.originalPrice;
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(current);
}

function zoneAriaLabel(section) {
    return `Zona ${section.name}, ${zonePriceLabel(section)}`;
}

function setCursor(event) {
    const stage = event.target.getStage();
    if (stage) stage.container().style.cursor = "pointer";
}

function resetCursor(event) {
    const stage = event.target.getStage();
    if (stage) stage.container().style.cursor = "default";
}

let measuring = false;

function reservedBottom() {
    const nav = document.querySelector(".mobile-bottom-nav");
    if (!nav || getComputedStyle(nav).display === "none") return 36;
    return Math.ceil(nav.getBoundingClientRect().height) + 20;
}

function measure() {
    const viewport = host.value?.closest(".viewport");
    if (!viewport || measuring) return;
    measuring = true;
    try {
        if (window.scrollY <= 0) {
            const top = viewport.getBoundingClientRect().top;
            const desired = Math.max(240, Math.floor(window.innerHeight - top - reservedBottom()));
            const current = Math.round(viewport.getBoundingClientRect().height);
            if (Math.abs(current - desired) > 1) {
                viewport.style.height = `${desired}px`;
            }
        }
        fitWidth.value = Math.max(1, viewport.clientWidth);
        fitHeight.value = Math.max(1, viewport.clientHeight);
    } finally {
        measuring = false;
    }
}

onMounted(() => {
    measure();
    const viewport = host.value?.closest(".viewport");
    if (viewport) {
        observer = new ResizeObserver(measure);
        observer.observe(viewport);
    }
    window.addEventListener("resize", measure);
});

onUnmounted(() => {
    observer?.disconnect();
    window.removeEventListener("resize", measure);
});
</script>

<style scoped>
.konva-host {
    display: block;
}
</style>
