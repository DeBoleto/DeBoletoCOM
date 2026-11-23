export const FOUR_FOR_TWO = 42;

/**
 * Indica, boleto por boleto, cuáles salen gratis por la promoción, con las
 * mismas reglas que TicketPricing: en el "N+1 x N" el boleto N+1 va de
 * cortesía y en el 4x2 (código 42, solo en mapas) el 3.º y 4.º de cada
 * bloque completo de 4.
 */
export function promotionSequence({ total = 0, fourForTwo = true } = {}) {
    const blockLimit = Math.floor(total / 4) * 4;
    let counter = 0;

    return (promotion) => {
        if (!promotion) return false;

        if (fourForTwo && promotion === FOUR_FOR_TWO) {
            counter++;
            const position = counter % 4;
            return counter <= blockLimit && (position === 3 || position === 0);
        }

        const free = counter >= promotion;
        counter = free ? 0 : counter + 1;
        return free;
    };
}

export function freeTickets(total, promotion) {
    const next = promotionSequence({ total });
    return Array.from({ length: total }, () => next(promotion));
}

export function promotionLabel(promotion) {
    if (!promotion) return "";
    return promotion === FOUR_FOR_TWO ? "4x2" : `${promotion + 1}x${promotion}`;
}
