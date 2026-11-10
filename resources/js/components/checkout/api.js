const currency = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    minimumFractionDigits: 2,
});

export function money(value) {
    return currency.format(Number(value) || 0);
}

function csrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : "";
}

async function request(method, url, body) {
    const response = await fetch(url, {
        method,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...(body ? { "Content-Type": "application/json", "X-XSRF-TOKEN": csrfToken() } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    let data = null;
    try {
        data = await response.json();
    } catch {
        data = null;
    }

    return { ok: response.ok, status: response.status, data };
}

export const getJson = (url) => request("GET", url);
export const postJson = (url, body = {}) => request("POST", url, body);
