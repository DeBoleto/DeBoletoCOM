const SCRIPTS = [
    "https://js.openpay.mx/openpay.v1.min.js",
    "https://js.openpay.mx/openpay-data.v1.min.js",
];

const MESSAGES = {
    1001: "Revisa los datos de la tarjeta.",
    2004: "El número de tarjeta no es válido.",
    2005: "La fecha de vencimiento ya pasó.",
    2006: "Falta el código de seguridad.",
    2009: "El código de seguridad no es válido.",
    3001: "Tu banco rechazó la tarjeta.",
    3002: "La tarjeta está vencida.",
};

let loading = null;

function loadScript(src) {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }

        const script = document.createElement("script");
        script.src = src;
        script.async = false;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error("No pudimos cargar el formulario de pago. Revisa tu conexión y recarga la página."));
        document.head.appendChild(script);
    });
}

/**
 * Carga openpay.js, lo configura y devuelve el id de sesión del dispositivo
 * (antifraude). Solo se ejecuta una vez por página.
 */
export function loadOpenPay(config) {
    if (!config?.merchantId || !config?.publicKey) {
        return Promise.reject(new Error("Los pagos con tarjeta no están disponibles en este momento."));
    }

    loading ??= (async () => {
        for (const src of SCRIPTS) {
            await loadScript(src);
        }

        window.OpenPay.setId(config.merchantId);
        window.OpenPay.setApiKey(config.publicKey);
        window.OpenPay.setSandboxMode(Boolean(config.sandbox));

        return window.OpenPay.deviceData.setup();
    })();

    loading.catch(() => {
        loading = null;
    });

    return loading;
}

/**
 * Tokeniza la tarjeta directamente con OpenPay; el número nunca llega a nuestro servidor.
 */
export function tokenizeCard(card) {
    return new Promise((resolve, reject) => {
        window.OpenPay.token.create(
            card,
            (response) => resolve(response.data.id),
            (response) => {
                const code = response?.data?.error_code;
                reject(new Error(MESSAGES[code] ?? "No pudimos validar la tarjeta. Revisa los datos e inténtalo de nuevo."));
            },
        );
    });
}
