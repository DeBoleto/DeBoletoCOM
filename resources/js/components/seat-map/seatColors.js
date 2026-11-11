const FALLBACK = "#22c55e";

function parse(hex) {
    let value = String(hex || "").trim().replace("#", "");
    if (value.length === 3) value = [...value].map((char) => char + char).join("");
    if (!/^[0-9a-f]{6}$/i.test(value)) return null;
    return [0, 2, 4].map((start) => parseInt(value.slice(start, start + 2), 16));
}

function toHex(channels) {
    return `#${channels.map((channel) => Math.round(channel).toString(16).padStart(2, "0")).join("")}`;
}

function mix(channels, target, amount) {
    return channels.map((channel) => channel + (target - channel) * amount);
}

function luminance([red, green, blue]) {
    return (0.299 * red + 0.587 * green + 0.114 * blue) / 255;
}

export function zoneSeatColors(color) {
    const base = parse(color) ?? parse(FALLBACK);
    const light = luminance(base) > 0.62;
    const selected = mix(base, 0, light ? 0.55 : 0.45);

    return {
        available: toHex(base),
        hover: toHex(mix(base, 0, 0.25)),
        selected: toHex(selected),
        availableText: light ? "#111827" : "#ffffff",
        selectedText: "#ffffff",
        glow: toHex(base),
    };
}

export const SEAT_STATUS = {
    vendido: { fill: "#cbd5e1", text: "#64748b" },
    en_proceso: { fill: "#ffffff", text: "#64748b", stroke: "#94a3b8" },
    no_disponible: { fill: "#eef2f6", text: "#b6c0cc" },
};
