/**
 * Asientos libres que quedaron solos junto a los elegidos de la misma fila:
 * entre dos asientos elegidos o a la orilla de la fila, al lado de uno
 * elegido. `isTaken(seatId)` indica si el asiento ya está ocupado.
 */
export function seatGaps(section, chosenIds, isTaken) {
    if (section.capacity !== null || chosenIds.length < 2) return [];

    const chosen = new Set(chosenIds);
    const gaps = [];

    for (const row of section.rows) {
        const byX = new Map(row.seats.map((seat) => [seat.x, seat]));
        const open = (seat) => seat && seat.selectable !== false;

        for (const seat of row.seats) {
            if (chosen.has(seat.id) || seat.selectable === false || isTaken(seat.id)) continue;

            const left = byX.get(seat.x - 1);
            const right = byX.get(seat.x + 1);
            const between = left && right && chosen.has(left.id) && chosen.has(right.id);
            const edge = (!open(left) && right && chosen.has(right.id)) || (!open(right) && left && chosen.has(left.id));

            if (between || edge) {
                gaps.push({ key: section.key, zone: section.name, fila: row.fila, seat: seat.id, edge: !between });
            }
        }
    }

    return gaps;
}

export function seatGapMessage(gap, mine = "tus lugares") {
    const where = `el asiento ${gap.seat} de la fila ${gap.fila}${gap.zone ? ` en ${gap.zone}` : ""}`;

    return gap.edge
        ? `Selecciona asientos pegados: quedó libre ${where}, a la orilla de la fila junto a ${mine}.`
        : `Selecciona asientos pegados: quedó libre ${where}, entre dos de ${mine}.`;
}
