<template>
    <Head :title="title" />

    <div class="page-layout">
        <SiteHeader />

        <main id="main-content" class="container done-page">
            <CheckoutSteps :current="order.status === 'paid' ? 3 : 2" class="done-steps" />

            <div class="done-grid">
                <section class="done-main" aria-live="polite">
                    <template v-if="order.status === 'paid'">
                        <p class="done-kicker is-ok">Compra confirmada</p>
                        <h1>¡Listo! Tus boletos son tuyos.</h1>
                        <p class="done-lead">
                            Enviamos los boletos a <strong>{{ order.email }}</strong>. Para entrar al evento muestra el
                            boleto digital con su código QR dinámico; no se aceptan capturas ni impresiones.
                        </p>
                        <div class="done-actions">
                            <a v-if="order.bondUrl" :href="order.bondUrl" class="btn btn--primary" target="_blank" rel="noopener">Registrar mis bonos</a>
                            <a v-if="order.digitalUrl" :href="order.digitalUrl" class="btn" :class="order.bondUrl ? 'btn--ghost' : 'btn--primary'" target="_blank" rel="noopener">Ver boleto digital</a>
                            <a v-if="order.downloadUrl" :href="order.downloadUrl" class="btn btn--ghost">Descargar PDF</a>
                        </div>
                        <p v-if="order.bondUrl" class="done-hint">Completa el registro con tus datos para generar tu bono personalizado.</p>
                    </template>

                    <template v-else-if="order.status === 'awaiting_transfer'">
                        <p class="done-kicker">Esperando tu transferencia</p>
                        <h1>Transfiere para confirmar tu compra</h1>
                        <p class="done-lead">Tus lugares están apartados hasta la fecha límite. En cuanto recibamos el pago te enviaremos los boletos por correo.</p>

                        <div v-if="order.transfer" class="spei">
                            <div class="spei-row">
                                <span>CLABE</span>
                                <strong class="spei-clabe">{{ groupedClabe }}</strong>
                                <button type="button" class="copy-btn" @click="copy(order.transfer.clabe)">{{ copied ? "Copiada" : "Copiar" }}</button>
                            </div>
                            <div class="spei-row"><span>Banco</span><strong>{{ order.transfer.bank }}</strong></div>
                            <div class="spei-row"><span>Beneficiario</span><strong>{{ order.transfer.beneficiary }}</strong></div>
                            <div class="spei-row"><span>Monto exacto</span><strong>{{ money(order.transfer.amount) }}</strong></div>
                            <div v-if="dueDate" class="spei-row"><span>Fecha límite</span><strong>{{ dueDate }}</strong></div>
                        </div>
                        <p v-else class="done-hint">No pudimos consultar los datos de transferencia. Recarga la página en unos minutos.</p>

                        <ol class="spei-steps">
                            <li>Da de alta la CLABE en la app o portal de tu banco.</li>
                            <li>Transfiere el monto exacto antes de la fecha límite.</li>
                            <li>Te avisaremos por correo cuando el pago se acredite.</li>
                        </ol>
                    </template>

                    <template v-else-if="order.status === 'processing'">
                        <p class="done-kicker">Pago en proceso</p>
                        <h1>Estamos confirmando tu pago</h1>
                        <p class="done-lead">
                            Tu banco todavía no responde. Esta página se actualiza sola; también puedes cerrarla: si el pago
                            se aprueba, te enviaremos los boletos por correo.
                        </p>
                        <span class="spinner" aria-hidden="true" />
                    </template>

                    <template v-else>
                        <p class="done-kicker is-bad">Compra no completada</p>
                        <h1>No se realizó el cargo</h1>
                        <p class="done-lead">El pago fue rechazado o el tiempo para pagar terminó, así que liberamos los lugares. Puedes intentarlo de nuevo.</p>
                        <div class="done-actions">
                            <Link :href="route('event.detail', event.slug)" class="btn btn--primary">Volver al evento</Link>
                        </div>
                    </template>

                    <p class="done-ref">Compra #{{ order.number }}</p>
                </section>

                <OrderStub v-if="order.quote" :event="event" :func="func" :quote="order.quote" class="done-stub" />
            </div>
        </main>

        <SiteFooter />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import SiteHeader from "@/components/SiteHeader.vue";
import SiteFooter from "@/components/SiteFooter.vue";
import CheckoutSteps from "@/components/checkout/CheckoutSteps.vue";
import OrderStub from "@/components/checkout/OrderStub.vue";
import { money } from "@/components/checkout/api.js";

const props = defineProps({
    event: { type: Object, required: true },
    function: { type: Object, default: null },
    order: { type: Object, required: true },
});

const POLL_MS = 4000;
const MAX_POLLS = 45;

const func = computed(() => props.function);
const copied = ref(false);
let pollId;
let polls = 0;

const title = computed(() => ({
    paid: "Compra confirmada",
    awaiting_transfer: "Esperando transferencia",
    processing: "Confirmando pago",
}[props.order.status] ?? "Compra no completada"));

const groupedClabe = computed(() => (props.order.transfer?.clabe ?? "").replace(/(\d{3})(\d{3})(\d{11})(\d)/, "$1 $2 $3 $4"));

const dueDate = computed(() => {
    const iso = props.order.transfer?.dueDate;
    if (!iso) return "";
    return new Intl.DateTimeFormat("es-MX", { dateStyle: "full", timeStyle: "short" }).format(new Date(iso));
});

async function copy(text) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 2500);
    } catch {
        copied.value = false;
    }
}

onMounted(() => {
    if (props.order.status !== "processing") return;

    pollId = window.setInterval(() => {
        polls++;

        if (props.order.status !== "processing" || polls > MAX_POLLS) {
            window.clearInterval(pollId);
            return;
        }

        router.reload({ only: ["order"] });
    }, POLL_MS);
});

onUnmounted(() => window.clearInterval(pollId));
</script>

<style scoped>
.page-layout {
    display: flex;
    flex-direction: column;
    min-height: 100dvh;
}

.done-page {
    flex: 1;
    padding-block: var(--space-6) var(--space-16);
}

.done-steps {
    margin-bottom: var(--space-6);
}

.done-grid {
    display: grid;
    gap: var(--space-8);
}

.done-main {
    max-width: 620px;
}

.done-kicker {
    color: var(--color-text-secondary);
    font-weight: 700;
    font-size: var(--text-sm);
}

.done-kicker.is-ok {
    color: var(--color-brand);
}

.done-kicker.is-bad {
    color: #fca5a5;
}

.done-main h1 {
    margin-top: 6px;
    font-size: var(--text-4xl);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.02em;
    text-wrap: balance;
}

.done-lead {
    margin-top: var(--space-4);
    color: var(--color-text-secondary);
    font-size: var(--text-base);
    line-height: 1.6;
}

.done-lead strong {
    color: var(--color-text-primary);
}

.done-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: var(--space-6);
}

.done-hint,
.done-ref {
    margin-top: var(--space-4);
    color: var(--color-text-muted);
    font-size: var(--text-sm);
}

.done-ref {
    margin-top: var(--space-8);
    font-variant-numeric: tabular-nums;
}

.btn {
    display: inline-flex;
    align-items: center;
    min-height: 50px;
    padding: 0 22px;
    border-radius: var(--radius-md);
    font-weight: 800;
}

.btn--primary {
    background: var(--color-brand);
    color: #06291d;
}

.btn--ghost {
    border: 1px solid var(--color-border-strong);
    color: var(--color-text-primary);
}

.btn:focus-visible,
.copy-btn:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.spei {
    display: grid;
    margin-top: var(--space-6);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    background: var(--color-surface-1);
}

.spei-row {
    display: grid;
    grid-template-columns: 120px 1fr auto;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
}

.spei-row + .spei-row {
    border-top: 1px solid var(--color-border);
}

.spei-row span {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.spei-row strong {
    grid-column: 2 / -1;
    font-variant-numeric: tabular-nums;
}

.spei-row:first-child strong {
    grid-column: 2;
}

.spei-clabe {
    font-size: var(--text-xl);
    letter-spacing: 0.04em;
}

.copy-btn {
    padding: 8px 14px;
    border: 1px solid var(--color-brand);
    border-radius: var(--radius-full);
    background: transparent;
    color: var(--color-brand);
    font-weight: 700;
    cursor: pointer;
}

.spei-steps {
    margin: var(--space-6) 0 0;
    padding-left: 1.25rem;
    color: var(--color-text-secondary);
    line-height: 1.8;
}

.spinner {
    display: block;
    width: 36px;
    height: 36px;
    margin-top: var(--space-6);
    border: 3px solid var(--color-border-strong);
    border-top-color: var(--color-brand);
    border-radius: 50%;
    animation: spin 0.9s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (min-width: 1024px) {
    .done-grid {
        grid-template-columns: minmax(0, 1fr) 400px;
        align-items: start;
    }
}

@media (max-width: 520px) {
    .spei-row {
        grid-template-columns: 1fr auto;
    }

    .spei-row strong,
    .spei-row:first-child strong {
        grid-column: 1 / -1;
    }

    .spei-row:first-child .copy-btn {
        grid-row: 1;
        grid-column: 2;
    }
}

@media (prefers-reduced-motion: reduce) {
    .spinner {
        animation-duration: 3s;
    }
}
</style>
