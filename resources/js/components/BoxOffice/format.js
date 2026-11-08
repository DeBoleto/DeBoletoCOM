const currency = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    minimumFractionDigits: 2,
});

export function money(value) {
    return currency.format(Number(value) || 0);
}

export function iconForMenu(faClass) {
    const value = String(faClass || "").toLowerCase();

    if (value.includes("ticket")) return "ticket";
    if (/chart|bar|line|pie/.test(value)) return "chart";
    if (/money|dollar|usd|cash|credit/.test(value)) return "cash";
    if (value.includes("user")) return "user";
    if (value.includes("calendar")) return "calendar";
    if (/file|report|list/.test(value)) return "file";

    return "grid";
}
