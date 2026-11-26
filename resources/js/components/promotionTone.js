const TONES = {
    nxm: "#dc2626",
    msi: "#2563eb",
    discount: "#c026d3",
};

/**
 * Tipo de promoción según su texto: "2x1", "3x2"… (rojo), meses sin
 * intereses (azul) o descuento (fucsia). Si trae varias, gana el "NxM".
 */
export function promotionTone(text) {
    const value = String(text ?? "").toLowerCase();

    if (/\d+\s*x\s*\d+/.test(value)) return "nxm";
    if (/\bmsi\b|meses sin intereses/.test(value)) return "msi";
    return "discount";
}

export function promotionStyle(text) {
    return { background: TONES[promotionTone(text)] };
}
