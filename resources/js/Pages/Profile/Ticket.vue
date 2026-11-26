<template>
    <Head :title="`Boletos · ${event.name}`" />

    <div class="page-layout">
        <main id="main-content" class="container pass-page">
            <div ref="track" class="pass-track" tabindex="0" aria-label="Boletos de la compra" @scroll.passive="onScroll" @keydown="onKey">
                <article
                    v-for="(ticket, index) in tickets"
                    :key="`${ticket.kind}-${ticket.id}-${index}`"
                    class="pass"
                    :class="{ 'is-scanned': scanned.has(ticket.id) && ticket.kind === 'ticket', 'is-courtesy': ticket.courtesy }"
                    :aria-label="`Boleto ${index + 1} de ${tickets.length}`"
                >
                    <header class="pass-hero">
                        <img v-if="event.image" :src="event.image" alt="" />
                        <div class="pass-hero-text">
                            <span v-if="ticket.courtesy" class="pass-chip">Cortesía</span>
                            <h1 class="pass-event">{{ event.name }}</h1>
                        </div>
                    </header>

                    <dl class="pass-when">
                        <div>
                            <dt>Fecha</dt>
                            <dd>{{ event.date || "—" }}</dd>
                        </div>
                        <div>
                            <dt>Hora</dt>
                            <dd>{{ event.time ? `${event.time} h` : "—" }}</dd>
                        </div>
                        <div class="pass-when-wide">
                            <dt>Lugar</dt>
                            <dd>{{ [event.venue, event.city].filter(Boolean).join(", ") || "—" }}</dd>
                        </div>
                    </dl>

                    <div class="pass-zone">
                        <div>
                            <span class="pass-caption">{{ ticket.kind === "parking" ? "Pase" : "Zona" }}</span>
                            <strong class="pass-zone-name">{{ ticket.locality || "General" }}</strong>
                            <span v-if="ticket.special" class="pass-special">{{ ticket.special }}</span>
                        </div>
                        <div v-if="ticket.seat" class="pass-seat">
                            <span class="pass-caption">Asiento</span>
                            <strong>{{ ticket.seat }}</strong>
                        </div>
                    </div>

                    <div v-if="ticket.runner" class="pass-runner">
                        <span class="pass-bib">#{{ ticket.runner.number ?? "—" }}</span>
                        <div>
                            <span class="pass-caption">Corredor</span>
                            <strong>{{ ticket.runner.name }}</strong>
                        </div>
                    </div>

                    <div class="pass-cut" aria-hidden="true" />

                    <div class="pass-code">
                        <template v-if="scanned.has(ticket.id) && ticket.kind === 'ticket'">
                            <div class="pass-done" role="status">
                                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                                <strong>Boleto ya ingresado</strong>
                                <span>Este código ya se usó en la entrada.</span>
                            </div>
                        </template>
                        <template v-else-if="ticket.id">
                            <div class="pass-qr">
                                <img :src="qr[ticket.id]?.src ?? ticket.qr" :alt="`Código de acceso del boleto ${ticket.id}`" width="180" height="180" />
                            </div>
                            <p v-if="qr[ticket.id]?.expiresAt" class="pass-timer">
                                <span class="pass-pulse" aria-hidden="true" />
                                Se renueva en {{ remaining(ticket.id) }}
                            </p>
                            <p class="pass-id">{{ ticket.id }}</p>
                        </template>
                    </div>

                    <aside v-if="sponsors.length" class="pass-sponsors" aria-label="Patrocinadores">
                        <div class="pass-sponsors-logos">
                            <component
                                :is="sponsor.url ? 'a' : 'span'"
                                v-for="sponsor in sponsors"
                                :key="sponsor.name"
                                :href="sponsor.url || undefined"
                                :target="sponsor.url ? '_blank' : undefined"
                                :rel="sponsor.url ? 'noopener sponsored' : undefined"
                                class="pass-sponsor"
                            >
                                <img :src="sponsor.image" :alt="sponsor.name" loading="lazy" decoding="async" />
                            </component>
                        </div>
                    </aside>

                    <footer class="pass-foot">
                        <span>Folio {{ purchase.folio }}</span>
                        <span v-if="purchase.holder">{{ purchase.holder }}</span>
                    </footer>
                </article>
            </div>

            <div v-if="tickets.length > 1" class="pass-dots" role="tablist" aria-label="Elegir boleto">
                <button
                    v-for="(ticket, index) in tickets"
                    :key="index"
                    type="button"
                    role="tab"
                    class="pass-dot"
                    :class="{ 'is-active': index === current }"
                    :aria-selected="index === current"
                    :aria-label="`Ver boleto ${index + 1}`"
                    @click="goTo(index)"
                />
            </div>
        </main>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, reactive, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import { getJson } from "@/components/checkout/api.js";

const props = defineProps({
    event: { type: Object, required: true },
    purchase: { type: Object, required: true },
    tickets: { type: Array, default: () => [] },
    sponsors: { type: Array, default: () => [] },
    owner: { type: Boolean, default: false },
    qrSeconds: { type: Number, default: 300 },
    scanPollSeconds: { type: Number, default: 35 },
});

const track = ref(null);
const current = ref(0);
const now = ref(Date.now());
const scanned = ref(new Set(props.tickets.filter((ticket) => ticket.scanned && ticket.kind === "ticket").map((ticket) => ticket.id)));
const qr = reactive({});

let clock;
let scanTimer;

const ticketIds = [...new Set(props.tickets.filter((ticket) => ticket.id && ticket.kind === "ticket").map((ticket) => ticket.id))];

async function refreshQr(id) {
    if (scanned.value.has(id)) return;

    const { ok, data } = await getJson(route("ticket.qr", { codigo: props.purchase.code, boleto: id }));
    if (!ok || !data?.qr) return;

    qr[id] = { src: data.qr, expiresAt: Date.now() + (data.expiresIn ?? props.qrSeconds) * 1000 };
}

function refreshExpired() {
    for (const id of ticketIds) {
        if (!qr[id] || qr[id].expiresAt <= Date.now()) refreshQr(id);
    }
}

async function pollScans() {
    const { ok, data } = await getJson(route("ticket.scans", props.purchase.code));
    if (ok && Array.isArray(data?.scanned)) scanned.value = new Set(data.scanned);
}

function tick() {
    if (document.hidden) return;
    now.value = Date.now();
    refreshExpired();
}

function remaining(id) {
    const seconds = Math.max(0, Math.round((qr[id].expiresAt - now.value) / 1000));
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`;
}

function onScroll() {
    const el = track.value;
    if (!el?.firstElementChild) return;
    const width = el.firstElementChild.getBoundingClientRect().width + 16;
    current.value = Math.min(props.tickets.length - 1, Math.max(0, Math.round(el.scrollLeft / width)));
}

function goTo(index) {
    const card = track.value?.children[index];
    card?.scrollIntoView({ behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", inline: "center", block: "nearest" });
}

function onKey(event) {
    if (event.key === "ArrowRight") goTo(Math.min(current.value + 1, props.tickets.length - 1));
    if (event.key === "ArrowLeft") goTo(Math.max(current.value - 1, 0));
}

function onVisible() {
    if (!document.hidden) {
        tick();
        pollScans();
    }
}

onMounted(() => {
    refreshExpired();
    clock = window.setInterval(tick, 1000);
    scanTimer = window.setInterval(() => !document.hidden && pollScans(), props.scanPollSeconds * 1000);
    document.addEventListener("visibilitychange", onVisible);
});

onUnmounted(() => {
    window.clearInterval(clock);
    window.clearInterval(scanTimer);
    document.removeEventListener("visibilitychange", onVisible);
});
</script>

<style scoped>
.page-layout {
    display: flex;
    flex-direction: column;
    min-height: 100dvh;
}

.pass-page {
    flex: 1;
    width: 100%;
    padding-block: var(--space-6);
}

.pass-track {
    display: flex;
    gap: 16px;
    padding: 8px max(0px, calc(50% - 190px)) 16px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
}

.pass-track::-webkit-scrollbar {
    display: none;
}

.pass-track:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 4px;
    border-radius: var(--radius-xl);
}

.pass {
    --cut: 14px;
    position: relative;
    flex: 0 0 min(380px, calc(100vw - 48px));
    overflow: hidden;
    border-radius: var(--radius-xl);
    background: #ffffff;
    box-shadow: 0 24px 48px -16px rgba(0, 0, 0, 0.7);
    color: #111827;
    scroll-snap-align: center;
}

.pass-hero {
    position: relative;
    aspect-ratio: 16 / 9;
    background: linear-gradient(135deg, #0e4d4a, #12121a);
}

.pass-hero img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pass-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(0, 0, 0, 0) 40%, rgba(0, 0, 0, 0.85) 100%);
}

.pass-hero-text {
    position: absolute;
    inset-inline: 0;
    bottom: 0;
    z-index: 1;
    padding: 14px 18px;
}

.pass-event {
    color: #ffffff;
    font-size: var(--text-xl);
    font-weight: 800;
    line-height: 1.2;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
}

.pass-chip {
    display: inline-block;
    margin-bottom: 6px;
    padding: 2px 10px;
    border-radius: var(--radius-full);
    background: #c026d3;
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.pass-when {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 16px;
    padding: 16px 18px 12px;
}

.pass-when-wide {
    grid-column: 1 / -1;
}

.pass-when dt,
.pass-caption {
    display: block;
    color: #6b7280;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.pass-when dd {
    font-size: var(--text-sm);
    font-weight: 700;
}

.pass-zone {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    margin: 0 18px;
    padding: 12px 14px;
    border-radius: var(--radius-md);
    background: #ecfdf5;
}

.pass-zone-name {
    display: block;
    color: #065f46;
    font-size: var(--text-lg);
    font-weight: 800;
    line-height: 1.2;
}

.pass-special {
    display: block;
    color: #047857;
    font-size: var(--text-xs);
    font-weight: 700;
}

.pass-seat {
    text-align: right;
}

.pass-seat strong {
    font-size: var(--text-2xl);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.pass-runner {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 10px 18px 0;
}

.pass-bib {
    padding: 6px 12px;
    border: 2px solid #111827;
    border-radius: var(--radius-sm);
    font-size: var(--text-xl);
    font-weight: 900;
    font-variant-numeric: tabular-nums;
}

.pass-cut {
    position: relative;
    height: var(--cut);
    margin: 14px 0 4px;
}

.pass-cut::before {
    content: "";
    position: absolute;
    top: 50%;
    left: calc(var(--cut) + 6px);
    right: calc(var(--cut) + 6px);
    border-top: 2px dashed #d1d5db;
}

.pass-cut::after {
    content: "";
    position: absolute;
    inset: 0 calc(var(--cut) * -1);
    background:
        radial-gradient(circle at left center, var(--color-bg) var(--cut), transparent calc(var(--cut) + 0.5px)),
        radial-gradient(circle at right center, var(--color-bg) var(--cut), transparent calc(var(--cut) + 0.5px));
}

.pass-code {
    display: grid;
    justify-items: center;
    gap: 6px;
    padding: 6px 18px 14px;
}

.pass-qr {
    padding: 10px;
    border: 1px solid #e5e7eb;
    border-radius: var(--radius-md);
}

.pass-qr img {
    display: block;
    /* Más chico en pantallas bajas para que el boleto completo quepa sin scroll. */
    width: clamp(140px, 24dvh, 180px);
    height: clamp(140px, 24dvh, 180px);
    image-rendering: pixelated;
}

.pass-timer {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #374151;
    font-size: var(--text-xs);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pass-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    animation: pass-pulse 1.6s ease-in-out infinite;
}

.pass-id {
    color: #9ca3af;
    font-size: var(--text-xs);
    font-weight: 700;
    letter-spacing: 0.1em;
    font-variant-numeric: tabular-nums;
}

.pass-done {
    display: grid;
    justify-items: center;
    gap: 4px;
    padding: 30px 0;
    color: #059669;
    text-align: center;
}

.pass-done span {
    color: #6b7280;
    font-size: var(--text-sm);
}

.pass.is-scanned .pass-hero img {
    filter: grayscale(1);
}

.pass-sponsors {
    display: flex;
    justify-content: center;
    margin: 0 18px;
    padding: 10px 0 12px;
    border-top: 1px solid #f1f5f9;
}

.pass-sponsors-logos {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 8px 18px;
}

.pass-sponsor {
    display: flex;
    align-items: center;
    border-radius: var(--radius-sm);
}

.pass-sponsor img {
    display: block;
    width: auto;
    max-width: 140px;
    height: 40px;
    object-fit: contain;
}

.pass-sponsor:focus-visible {
    outline: 2px solid #10b981;
    outline-offset: 3px;
}

.pass-foot {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 18px;
    background: #f9fafb;
    color: #6b7280;
    font-size: var(--text-xs);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pass-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 4px;
}

.pass-dot {
    width: 8px;
    height: 8px;
    padding: 0;
    border: none;
    border-radius: var(--radius-full);
    background: var(--color-border-strong);
    cursor: pointer;
    transition:
        width var(--transition-fast),
        background var(--transition-fast);
}

.pass-dot.is-active {
    width: 24px;
    background: var(--color-brand);
}

.pass-dot:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

@keyframes pass-pulse {
    50% {
        opacity: 0.3;
    }
}

@media (prefers-reduced-motion: reduce) {
    .pass-pulse {
        animation: none;
    }
}

@media (max-width: 767px) {
    .pass-page {
        padding-bottom: var(--space-10);
    }
}
</style>
