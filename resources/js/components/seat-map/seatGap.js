/**
 * Asientos libres que quedaron solos junto a los elegidos de la misma fila:
 * entre dos asientos elegidos, entre uno elegido y uno apartado o vendido, o
 * a la orilla de la fila al lado de uno elegido. `isTaken(seatId)` indica si
 * el asiento ya está ocupado por otra persona.
 */
export function seatGaps(section, chosenIds, isTaken) {
    if (section.capacity !== null || chosenIds.length === 0) return [];

    const chosen = new Set(chosenIds);
    const gaps = [];

    for (const row of section.rows) {
        const byX = new Map(row.seats.map((seat) => [seat.x, seat]));
        const state = (seat) => {
            if (!seat || seat.selectable === false) return "edge";
            if (chosen.has(seat.id)) return "chosen";
            return isTaken(seat.id) ? "taken" : "open";
        };

        for (const seat of row.seats) {
            if (state(seat) !== "open") continue;

            const sides = [state(byX.get(seat.x - 1)), state(byX.get(seat.x + 1))];
            if (!sides.includes("chosen") || sides.includes("open")) continue;

            const kind = sides.every((side) => side === "chosen") ? "between" : sides.includes("taken") ? "taken" : "edge";
            if (kind === "edge" && chosenIds.length < 2) continue;

            gaps.push({ key: section.key, zone: section.name, fila: row.fila, seat: seat.id, kind });
        }
    }

    return gaps;
}

export function seatGapMessage(gap, mine = "tus lugares") {
    const where = `el asiento ${gap.seat} de la fila ${gap.fila}${gap.zone ? ` en ${gap.zone}` : ""}`;

    if (gap.kind === "taken") return `Selecciona asientos pegados: quedó libre ${where}, entre ${mine} y un asiento ocupado.`;
    if (gap.kind === "edge") return `Selecciona asientos pegados: quedó libre ${where}, a la orilla de la fila junto a ${mine}.`;
    return `Selecciona asientos pegados: quedó libre ${where}, entre dos de ${mine}.`;
}
