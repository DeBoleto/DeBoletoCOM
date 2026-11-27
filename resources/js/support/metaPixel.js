const FIRED_KEY = "meta_pixel_fired";
const MAX_REMEMBERED = 200;

const initialized = new Set();
let fired = null;

function firedIds() {
    if (fired === null) {
        fired = new Set();

        try {
            JSON.parse(window.sessionStorage.getItem(FIRED_KEY) || "[]").forEach((id) => fired.add(id));
        } catch {
            return fired;
        }
    }

    return fired;
}

function remember(id) {
    const ids = firedIds();
    ids.add(id);

    try {
        window.sessionStorage.setItem(FIRED_KEY, JSON.stringify([...ids].slice(-MAX_REMEMBERED)));
    } catch {
        return;
    }
}

function loadPixel() {
    if (window.fbq) {
        return window.fbq;
    }

    const fbq = function (...args) {
        fbq.callMethod ? fbq.callMethod(...args) : fbq.queue.push(args);
    };

    if (!window._fbq) {
        window._fbq = fbq;
    }

    fbq.push = fbq;
    fbq.loaded = true;
    fbq.version = "2.0";
    fbq.queue = [];
    window.fbq = fbq;

    const script = document.createElement("script");
    script.async = true;
    script.src = "https://connect.facebook.net/en_US/fbevents.js";
    document.head.appendChild(script);

    return fbq;
}

/**
 * Dispara en el píxel de cada evento los eventos que dejó el servidor
 * (prop metaPixel), con el mismo eventID que la API de conversiones.
 */
export function flushMetaPixel(events) {
    if (typeof window === "undefined" || !Array.isArray(events) || events.length === 0) {
        return;
    }

    events.forEach((event) => {
        const pixels = Array.isArray(event?.pixels) ? event.pixels : [];

        if (pixels.length === 0 || !event.name || !event.eventId || firedIds().has(event.eventId)) {
            return;
        }

        const fbq = loadPixel();

        pixels.forEach((pixel) => {
            if (!initialized.has(pixel)) {
                fbq("init", pixel);
                initialized.add(pixel);
            }

            fbq("trackSingle", pixel, event.name, event.data || {}, { eventID: event.eventId });
        });

        remember(event.eventId);
    });
}
