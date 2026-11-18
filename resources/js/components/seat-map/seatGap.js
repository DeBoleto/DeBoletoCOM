/**
 * Primer asiento libre que quedó solo entre dos asientos elegidos de la misma
 * fila, o null. `isTaken(seatId)` indica si el asiento ya está ocupado.
 */
export function singleSeatGap(section, chosenIds, isTaken) {
    if (section.capacity !== null || chosenIds.length < 2) return null;

    const chosen = new Set(chosenIds);

    for (const row of section.rows) {
        const byX = new Map(row.seats.map((seat) => [seat.x, seat]));

        for (const seat of row.seats) {
            if (chosen.has(seat.id) || seat.selectable === false || isTaken(seat.id)) continue;

            const left = byX.get(seat.x - 1);
            const right = byX.get(seat.x + 1);

            if (left && right && chosen.has(left.id) && chosen.has(right.id)) {
                return { key: section.key, zone: section.name, fila: row.fila, seat: seat.id };
            }
        }
    }

    return null;
}
