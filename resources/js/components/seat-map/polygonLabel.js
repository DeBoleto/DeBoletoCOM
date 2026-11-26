/**
 * Punto donde va el nombre de un área. En aros o formas en C el centro cae
 * fuera (en el hueco), así que se elige, entre el centro y los puntos medios
 * de las líneas vertical y horizontal que pasan por él, el más alejado del
 * borde; con empate gana el de más arriba. Igual que labelPoint() del editor
 * de mapas del admin. `size` es el espacio libre alrededor del punto.
 */
export function labelPoint(polygon) {
    const center = {
        x: polygon.reduce((sum, point) => sum + point.x, 0) / polygon.length,
        y: polygon.reduce((sum, point) => sum + point.y, 0) / polygon.length,
    };
    const candidates = inside(center, polygon) ? [center] : [];

    for (const [fixed, free] of [["x", "y"], ["y", "x"]]) {
        const value = center[fixed];
        const hits = [];
        polygon.forEach((a, index) => {
            const b = polygon[(index + 1) % polygon.length];
            if (a[fixed] > value !== b[fixed] > value) {
                hits.push(a[free] + ((value - a[fixed]) * (b[free] - a[free])) / (b[fixed] - a[fixed]));
            }
        });
        hits.sort((p, q) => p - q);
        for (let index = 0; index + 1 < hits.length; index += 2) {
            candidates.push({ [fixed]: value, [free]: (hits[index] + hits[index + 1]) / 2 });
        }
    }

    let best = null;
    let bestDistance = -1;
    for (const point of candidates) {
        const distance = edgeDistance(point, polygon);
        if (distance > bestDistance * 1.15 + 0.5) {
            best = point;
            bestDistance = distance;
        }
    }

    return best ? { x: best.x, y: best.y, size: bestDistance * 2 } : { ...center, size: 40 };
}

function inside(point, polygon) {
    let result = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const a = polygon[i];
        const b = polygon[j];
        if (a.y > point.y !== b.y > point.y && point.x < ((b.x - a.x) * (point.y - a.y)) / (b.y - a.y) + a.x) result = !result;
    }
    return result;
}

function edgeDistance(point, polygon) {
    let best = Infinity;
    polygon.forEach((a, index) => {
        const b = polygon[(index + 1) % polygon.length];
        const dx = b.x - a.x;
        const dy = b.y - a.y;
        const t = Math.max(0, Math.min(1, ((point.x - a.x) * dx + (point.y - a.y) * dy) / (dx * dx + dy * dy || 1)));
        best = Math.min(best, Math.hypot(point.x - (a.x + t * dx), point.y - (a.y + t * dy)));
    });
    return best;
}
