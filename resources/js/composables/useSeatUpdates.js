import { onUnmounted, watch } from "vue";

/**
 * Avisos en tiempo real (Reverb) de cambios de disponibilidad de una
 * función: alguien apartó, liberó o compró asientos. Sin Reverb configurado
 * no hace nada y los mapas siguen consultando periódicamente.
 */
export const realtimeEnabled = Boolean(import.meta.env.VITE_REVERB_APP_KEY);

let echo = null;
let connecting = null;

function connect() {
    if (!realtimeEnabled) return Promise.resolve(null);
    if (echo) return Promise.resolve(echo);

    connecting ??= Promise.all([import("laravel-echo"), import("pusher-js")])
        .then(([{ default: Echo }, { default: Pusher }]) => {
            const scheme = import.meta.env.VITE_REVERB_SCHEME ?? "https";
            const port = Number(import.meta.env.VITE_REVERB_PORT) || (scheme === "https" ? 443 : 80);

            window.Pusher = Pusher;
            echo = new Echo({
                broadcaster: "reverb",
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
                wsPort: port,
                wssPort: port,
                forceTLS: scheme === "https",
                enabledTransports: ["ws", "wss"],
            });

            return echo;
        })
        .catch(() => {
            connecting = null;
            return null;
        });

    return connecting;
}

/**
 * Llama `onChange` cuando cambia la disponibilidad de la función que
 * devuelve `functionId()`. Las ráfagas de avisos se agrupan y cada pantalla
 * espera un poco al azar para no consultar todas al mismo tiempo. Al volver
 * a la pestaña también se actualiza.
 */
export function useSeatUpdates(functionId, onChange) {
    let channel = null;
    let timer = null;
    let stopped = false;

    function schedule() {
        if (timer || stopped) return;
        timer = window.setTimeout(() => {
            timer = null;
            if (!document.hidden) onChange();
        }, 250 + Math.random() * 750);
    }

    function leave() {
        if (channel && echo) echo.leave(channel);
        channel = null;
    }

    async function subscribe(id) {
        leave();
        if (!id) return;

        const client = await connect();
        if (!client || stopped || functionId() !== id) return;

        channel = `funcion.${id}`;
        client.channel(channel).listen(".seats.changed", schedule);
    }

    function onVisible() {
        if (!document.hidden && functionId()) schedule();
    }

    watch(functionId, subscribe, { immediate: true });
    document.addEventListener("visibilitychange", onVisible);

    onUnmounted(() => {
        stopped = true;
        window.clearTimeout(timer);
        document.removeEventListener("visibilitychange", onVisible);
        leave();
    });
}
