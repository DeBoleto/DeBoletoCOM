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

const GRID = 20;
const AREA_MARGIN = 0.1;
const MIN_FIT = 0.5;
const MAX_FIT = 4;

function polygonOf(section) {
    return section.polygon?.length >= 3 ? section.polygon : null;
}

/**
 * Áreas de las secciones para dibujarlas detrás de los asientos. Solo
 * coinciden con los asientos cuando el XML trae desplazamientos.
 */
export function venueAreas(sections) {
    if (!hasPlacement(sections)) return [];

    return (sections ?? [])
        .filter(polygonOf)
        .map((section) => ({ key: section.key, polygon: section.polygon, general: section.capacity !== null }));
}

function rotate(point, center, radians) {
    if (!radians) return point;
    const cos = Math.cos(radians);
    const sin = Math.sin(radians);
    const dx = point.x - center.x;
    const dy = point.y - center.y;

    return { x: center.x + dx * cos - dy * sin, y: center.y + dx * sin + dy * cos };
}

function radiansOf(section) {
    return ((Number(section.rotation) || 0) * Math.PI) / 180;
}

/**
 * Escala y centra los asientos de la sección dentro de su área (en celdas),
 * para que llenen el área sin importar el tamaño de celda del XML. Con
 * `rotation` el área se mide ya girada de vuelta, para que los asientos
 * sigan la inclinación del polígono.
 */
function areaFit(section, offset) {
    const polygon = polygonOf(section);
    if (!polygon) return null;

    const radians = radiansOf(section);
    const xs = section.rows.flatMap((row) => row.seats.map((seat) => seat.x + offset.x));
    const ys = section.rows.map((row) => row.y + offset.y);
    const seatBox = {
        minX: Math.min(...xs) - 0.5,
        minY: Math.min(...ys) - 0.5,
        maxX: Math.max(...xs) + 0.5,
        maxY: Math.max(...ys) + 0.5,
    };
    const cells = polygon.map((point) => ({ x: point.x / GRID, y: point.y / GRID }));
    const pivot = {
        x: (Math.min(...cells.map((point) => point.x)) + Math.max(...cells.map((point) => point.x))) / 2,
        y: (Math.min(...cells.map((point) => point.y)) + Math.max(...cells.map((point) => point.y))) / 2,
    };
    const upright = cells.map((point) => rotate(point, pivot, -radians));
    const areaBox = {
        minX: Math.min(...upright.map((point) => point.x)),
        minY: Math.min(...upright.map((point) => point.y)),
        maxX: Math.max(...upright.map((point) => point.x)),
        maxY: Math.max(...upright.map((point) => point.y)),
    };
    const areaWidth = (areaBox.maxX - areaBox.minX) * (1 - AREA_MARGIN * 2);
    const areaHeight = (areaBox.maxY - areaBox.minY) * (1 - AREA_MARGIN * 2);
    const size = Math.max(
        MIN_FIT,
        Math.min(MAX_FIT, areaWidth / (seatBox.maxX - seatBox.minX), areaHeight / (seatBox.maxY - seatBox.minY)),
    );

    return {
        size,
        radians,
        pivot,
        fromX: (seatBox.minX + seatBox.maxX) / 2,
        fromY: (seatBox.minY + seatBox.maxY) / 2,
        toX: (areaBox.minX + areaBox.maxX) / 2,
        toY: (areaBox.minY + areaBox.maxY) / 2,
    };
}

/**
 * Asientos listos para VenueCanvas. `statusFor(section, seat)` devuelve el
 * estado del asiento (disponible, vendido, en_proceso o no_disponible).
 * `x`/`y` son celdas del mapa completo, `size` la escala del asiento ahí y
 * `angle` los grados que gira su sección; `zx`/`zy` son las celdas dentro de
 * la zona (sin girar).
 */
export function venueSeats(sections, statusFor) {
    const offsets = venueOffsets(sections);
    const placed = hasPlacement(sections);

    return seatedSections(sections).flatMap((section) => {
        const offset = offsets[section.key] ?? { x: 0, y: 0 };
        const fit = placed ? areaFit(section, offset) : null;
        const radians = placed ? radiansOf(section) : 0;
        // Sin área se gira alrededor del centro de los asientos, igual que el editor.
        const xs = section.rows.flatMap((row) => row.seats.map((seat) => seat.x + offset.x));
        const ys = section.rows.map((row) => row.y + offset.y);
        const center = { x: (Math.min(...xs) + Math.max(...xs)) / 2, y: (Math.min(...ys) + Math.max(...ys)) / 2 };

        return section.rows.flatMap((row) =>
            row.seats.map((seat) => {
                const x = seat.x + offset.x;
                const y = row.y + offset.y;
                const point = fit
                    ? rotate({ x: fit.toX + (x - fit.fromX) * fit.size, y: fit.toY + (y - fit.fromY) * fit.size }, fit.pivot, fit.radians)
                    : rotate({ x, y }, center, radians);

                return {
                    key: section.key,
                    id: seat.id,
                    fila: row.fila,
                    x: point.x,
                    y: point.y,
                    size: fit ? fit.size : 1,
                    angle: (radians * 180) / Math.PI,
                    inArea: fit !== null,
                    zx: seat.x,
                    zy: row.y,
                    status: statusFor(section, seat),
                };
            }),
        );
    });
}
