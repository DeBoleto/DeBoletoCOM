<template>
    <Teleport to="body">
        <div class="seat-tip" :class="{ 'seat-tip--below': below }" :style="position" aria-hidden="true">
            <div v-if="row !== null || seat !== null" class="seat-tip-place">
                <div class="seat-tip-cell">
                    <span class="seat-tip-caption">Fila</span>
                    <strong class="seat-tip-value">{{ row ?? "—" }}</strong>
                </div>
                <div class="seat-tip-cell">
                    <span class="seat-tip-caption">Asiento</span>
                    <strong class="seat-tip-value">{{ seat ?? "—" }}</strong>
                </div>
            </div>
            <div class="seat-tip-zone" :style="{ background: zoneColor, color: ink }">
                <span v-if="status" class="seat-tip-status" :style="{ borderColor: ink }">{{ status }}</span>
                <strong class="seat-tip-name">{{ zone }}</strong>
                <span v-if="detail" class="seat-tip-detail">{{ detail }}</span>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed } from "vue";

/**
 * Tarjeta flotante de un asiento o zona del mapa: fila y asiento en grande
 * sobre blanco y una franja con el color de la zona, con texto blanco u
 * oscuro según lo que contraste mejor con ese color.
 */
const props = defineProps({
    x: { type: Number, required: true },
    y: { type: Number, required: true },
    zone: { type: String, required: true },
    color: { type: String, default: "" },
    row: { type: [String, Number], default: null },
    seat: { type: [String, Number], default: null },
    status: { type: String, default: "" },
    detail: { type: String, default: "" },
});

const FALLBACK = "#be3455";
const HALF_WIDTH = 120;

const hexColor = computed(() => {
    const value = String(props.color ?? "").trim();
    return /^#?([0-9a-f]{3}){1,2}$/i.test(value) ? `#${value.replace("#", "")}` : null;
});

const zoneColor = computed(() => hexColor.value ?? (String(props.color ?? "").trim() || FALLBACK));

const ink = computed(() => {
    if (!hexColor.value) return "#ffffff";

    let hex = hexColor.value.slice(1);
    if (hex.length === 3) hex = [...hex].map((char) => char + char).join("");

    const [r, g, b] = [0, 2, 4].map((start) => {
        const channel = parseInt(hex.slice(start, start + 2), 16) / 255;
        return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });
    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;

    return (luminance + 0.05) / 0.05 > 1.05 / (luminance + 0.05) ? "#111827" : "#ffffff";
});

const below = computed(() => props.y < 170);

const position = computed(() => {
    const width = typeof window === "undefined" ? Infinity : window.innerWidth;
    const left = Math.min(Math.max(props.x, HALF_WIDTH + 8), Math.max(width - HALF_WIDTH - 8, HALF_WIDTH + 8));

    return { left: `${left}px`, top: `${props.y}px` };
});
</script>

<style scoped>
.seat-tip {
    position: fixed;
    z-index: 80;
    width: max-content;
    min-width: 190px;
    max-width: 240px;
    overflow: hidden;
    border-radius: 18px;
    background: #ffffff;
    box-shadow:
        0 18px 40px -12px rgba(15, 23, 42, 0.45),
        0 0 0 1px rgba(15, 23, 42, 0.08);
    color: #111827;
    font-family: inherit;
    pointer-events: none;
    transform: translate(-50%, calc(-100% - 16px));
    animation: seat-tip-in 140ms ease-out;
}

.seat-tip--below {
    transform: translate(-50%, 20px);
}

.seat-tip-place {
    display: grid;
    grid-template-columns: 1fr 1fr;
}

.seat-tip-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 10px 14px 8px;
}

.seat-tip-cell + .seat-tip-cell {
    border-left: 3px solid #e5e7eb;
}

.seat-tip-caption {
    color: #6b7280;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.seat-tip-value {
    color: #111827;
    font-size: 28px;
    font-weight: 800;
    line-height: 1.05;
    font-variant-numeric: tabular-nums;
}

.seat-tip-zone {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 10px 16px 12px;
}

.seat-tip-name {
    overflow: hidden;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: 0.02em;
    line-height: 1.2;
    text-overflow: ellipsis;
    text-transform: uppercase;
    white-space: nowrap;
}

.seat-tip-detail {
    font-size: 12px;
    font-weight: 600;
    opacity: 0.9;
}

.seat-tip-status {
    align-self: flex-start;
    margin-bottom: 4px;
    padding: 1px 8px;
    border: 1px solid;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

@keyframes seat-tip-in {
    from {
        opacity: 0;
    }
}

@media (hover: none) {
    .seat-tip {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .seat-tip {
        animation: none;
    }
}
</style>
