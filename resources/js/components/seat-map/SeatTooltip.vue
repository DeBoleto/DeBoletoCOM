<template>
    <Teleport to="body">
        <div class="seat-tip" :class="{ 'seat-tip--below': below }" :style="position" aria-hidden="true">
            <div v-if="hasPlace" class="seat-tip-place">
                <div class="seat-tip-cell">
                    <span class="seat-tip-caption">Fila</span>
                    <strong class="seat-tip-value">{{ row ?? "—" }}</strong>
                </div>
                <div class="seat-tip-cell">
                    <span class="seat-tip-caption">Asiento</span>
                    <strong class="seat-tip-value">{{ seat ?? "—" }}</strong>
                </div>
            </div>
            <div v-if="status || detail || hint" class="seat-tip-info" :class="{ 'seat-tip-info--ruled': hasPlace }">
                <span v-if="status" class="seat-tip-status">{{ status }}</span>
                <strong v-if="detail" class="seat-tip-price">{{ detail }}</strong>
                <span v-if="hint" class="seat-tip-hint">{{ hint }}</span>
            </div>
            <div class="seat-tip-zone" :style="{ background: zoneColor, color: ink }">
                <strong class="seat-tip-name">{{ zone }}</strong>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed } from "vue";

/**
 * Tarjeta flotante de un asiento o zona del mapa: fila, asiento, precio y
 * ayuda sobre blanco y una franja con el color de la zona, con texto blanco u
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
    hint: { type: String, default: "" },
});

const FALLBACK = "#be3455";
const HALF_WIDTH = 150;

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

const hasPlace = computed(() => props.row !== null || props.seat !== null);

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
    min-width: 220px;
    max-width: 300px;
    overflow: hidden;
    border-radius: 10px;
    background: #ffffff;
    box-shadow:
        0 14px 32px -12px rgba(15, 23, 42, 0.45),
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
    gap: 1px;
    padding: 8px 14px 6px;
}

.seat-tip-cell + .seat-tip-cell {
    border-left: 2px solid #e5e7eb;
}

.seat-tip-caption {
    color: #6b7280;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.seat-tip-value {
    color: #111827;
    font-size: 20px;
    font-weight: 800;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.seat-tip-info {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 8px;
    padding: 7px 14px;
}

.seat-tip-info--ruled {
    justify-content: center;
    border-top: 1px solid #eef0f3;
}

.seat-tip-price {
    color: #111827;
    font-size: 13px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.seat-tip-hint {
    color: #6b7280;
    font-size: 11px;
    font-weight: 500;
}

.seat-tip-status {
    padding: 1px 8px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.seat-tip-zone {
    padding: 7px 14px;
}

.seat-tip-name {
    display: block;
    overflow: hidden;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.04em;
    line-height: 1.3;
    text-overflow: ellipsis;
    text-transform: uppercase;
    white-space: nowrap;
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
