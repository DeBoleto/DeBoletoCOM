/**
 * Coloca todas las secciones numeradas en un solo mapa del recinto. Cada
 * sección usa su desplazamiento del XML (num/numx); los XML anteriores sin
 * desplazamientos se acomodan en cuadrícula para que no se encimen.
 */
export function seatedSections(sections) {
    return (sections ?? []).filter((section) => section.capacity === null && section.rows?.length);
}

export function hasPlacement(sections) {
    return seatedSections(sections).some((section) => section.offset && (section.offset.x !== 0 || section.offset.y !== 0));
}

// Sin desplazamientos las secciones se acomodan solas y el escenario del XML ya no coincidiría.
export function venueMainStage(stage) {
    return hasPlacement(stage?.sections) ? (stage?.mainStage ?? null) : null;
}

export function venueOffsets(sections) {
    const seated = seatedSections(sections);

    if (hasPlacement(sections) || seated.length < 2) {
        return Object.fromEntries(seated.map((section) => [section.key, section.offset ?? { x: 0, y: 0 }]));
    }

    const boxes = seated.map((section) => {
        const xs = section.rows.flatMap((row) => row.seats.map((seat) => seat.x));
        const ys = section.rows.map((row) => row.y);
        const minX = Math.min(...xs);
        const minY = Math.min(...ys);

        return { key: section.key, minX, minY, width: Math.max(...xs) - minX + 1, height: Math.max(...ys) - minY + 1 };
    });

    const gap = 3;
    const area = boxes.reduce((sum, box) => sum + (box.width + gap) * (box.height + gap), 0);
    const rowWidth = Math.max(Math.sqrt(area) * 1.4, ...boxes.map((box) => box.width));
    const result = {};
    let x = 0;
    let y = 0;
    let rowHeight = 0;

    for (const box of boxes) {
        if (x > 0 && x + box.width > rowWidth) {
            x = 0;
            y += rowHeight + gap;
            rowHeight = 0;
        }

        result[box.key] = { x: x - box.minX, y: y - box.minY };
        x += box.width + gap;
        rowHeight = Math.max(rowHeight, box.height);
    }

    return result;
}

/**
 * Asientos listos para VenueCanvas. `statusFor(section, seat)` devuelve el
 * estado del asiento (disponible, vendido, en_proceso o no_disponible).
 */
export function venueSeats(sections, statusFor) {
    const offsets = venueOffsets(sections);

    return seatedSections(sections).flatMap((section) => {
        const offset = offsets[section.key] ?? { x: 0, y: 0 };

        return section.rows.flatMap((row) =>
            row.seats.map((seat) => ({
                key: section.key,
                id: seat.id,
                fila: row.fila,
                x: seat.x + offset.x,
                y: row.y + offset.y,
                zx: seat.x,
                zy: row.y,
                status: statusFor(section, seat),
            })),
        );
    });
}
