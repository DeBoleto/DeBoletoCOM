<template>
    <Head :title="`Pago · ${event.name}`" />

    <div class="page-layout">
        <SiteHeader />

        <main id="main-content">
            <div class="timer-bar">
                <div class="container timer-bar-inner">
                    <div class="timer-event">
                        <p class="timer-event-name">{{ event.name }}</p>
                        <p class="timer-event-meta">{{ [func.date, func.time].filter(Boolean).join(" · ") }}</p>
                    </div>
                    <CheckoutSteps :current="1" class="timer-steps" />
                    <HoldTimer :deadline="heldUntil" label="Tiempo para pagar" @expired="expired" />
                </div>
            </div>

            <div class="container pay-page">
                <div class="pay-grid">
                    <form class="pay-form" novalidate @submit.prevent="pay">
                        <div class="pay-heading">
                            <h1>Pago</h1>
                            <Link :href="seatsUrl" class="back-link">Cambiar boletos</Link>
                        </div>

                        <p v-if="blocked" class="notice notice--error" role="alert">{{ blocked }}</p>
                        <p v-else-if="errorMessage" class="notice notice--error" role="alert">{{ errorMessage }}</p>

                        <fieldset class="block">
                            <legend class="block-title">Forma de pago</legend>
                            <div class="methods">
                                <label class="method" :class="{ 'is-active': method === 'card' }">
                                    <input v-model="method" type="radio" value="card" />
                                    <span class="method-name">Tarjeta de crédito o débito</span>
                                    <span class="method-hint">Visa, Mastercard o American Express</span>
                                </label>
                                <label class="method" :class="{ 'is-active': method === 'transfer' }">
                                    <input v-model="method" type="radio" value="transfer" />
                                    <span class="method-name">Transferencia SPEI</span>
                                    <span class="method-hint">Tienes {{ transferHours }} horas para transferir</span>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset v-if="method === 'card'" class="block">
                            <legend class="block-title">Datos de la tarjeta</legend>

                            <div v-if="savedCards.length" class="saved-cards">
                                <label v-for="card in savedCards" :key="card.id" class="saved-card" :class="{ 'is-active': cardChoice === card.id }">
                                    <input v-model="cardChoice" type="radio" :value="card.id" />
                                    <span class="saved-card-brand">{{ brandLabel(card.brand) }}</span>
                                    <span class="saved-card-number">•••• {{ card.last4 }}</span>
                                    <span class="saved-card-exp">{{ card.expiration }}</span>
                                </label>
                                <label class="saved-card" :class="{ 'is-active': cardChoice === 'new' }">
                                    <input v-model="cardChoice" type="radio" value="new" />
                                    <span class="saved-card-brand">Usar otra tarjeta</span>
                                </label>
                            </div>

                            <div v-if="cardChoice === 'new'" class="card-fields">
                                <div class="field field--full">
                                    <label for="card-holder">Nombre como aparece en la tarjeta</label>
                                    <input id="card-holder" v-model.trim="card.holder" autocomplete="cc-name" required />
                                </div>
                                <div class="field field--full">
                                    <label for="card-number">Número de tarjeta</label>
                                    <input
                                        id="card-number"
                                        :value="card.number"
                                        inputmode="numeric"
                                        autocomplete="cc-number"
                                        maxlength="23"
                                        placeholder="0000 0000 0000 0000"
                                        required
                                        @input="formatCardNumber"
                                    />
                                </div>
                                <div class="field">
                                    <label for="card-exp">Vencimiento</label>
                                    <input
                                        id="card-exp"
                                        :value="card.expiration"
                                        inputmode="numeric"
                                        autocomplete="cc-exp"
                                        maxlength="5"
                                        placeholder="MM/AA"
                                        required
                                        @input="formatExpiration"
                                    />
                                </div>
                                <div class="field">
                                    <label for="card-cvv">Código de seguridad</label>
                                    <input id="card-cvv" v-model="card.cvv" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="CVV" required />
                                </div>
                                <label class="check field--full">
                                    <input v-model="saveCard" type="checkbox" />
                                    Guardar esta tarjeta para mis próximas compras
                                </label>
                            </div>

                            <div v-else class="card-fields">
                                <div class="field">
                                    <label for="saved-cvv">Código de seguridad de la tarjeta</label>
                                    <input id="saved-cvv" v-model="card.cvv" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="CVV" required />
                                </div>
                            </div>

                            <div v-if="options.installments.length" class="field field--full installments">
                                <label for="installments">Plazo</label>
                                <select id="installments" v-model="installments">
                                    <option :value="null">Una sola exhibición</option>
                                    <option v-for="plan in options.installments" :key="plan" :value="plan">{{ plan }} meses sin intereses</option>
                                </select>
                            </div>

                            <p class="secure-note">El pago se autentica con tu banco (3D Secure). DeBoleto no guarda el número de tu tarjeta.</p>
                        </fieldset>

                        <div v-else class="block transfer-info">
                            <p>Al confirmar te mostraremos una cuenta CLABE y el monto exacto. Tus lugares quedan apartados {{ transferHours }} horas; si no recibimos la transferencia en ese tiempo, se liberan.</p>
                        </div>

                        <fieldset class="block">
                            <legend class="block-title">Entrega de boletos</legend>
                            <p class="delivery-note">Te enviaremos tus boletos digitales al correo de tu cuenta.</p>
                            <label class="check">
                                <input v-model="sendWhatsapp" type="checkbox" />
                                Recibirlos también por WhatsApp
                            </label>
                            <div v-if="sendWhatsapp" class="field whatsapp-field">
                                <label for="whatsapp">Número de WhatsApp (10 dígitos)</label>
                                <input id="whatsapp" v-model="whatsappPhone" inputmode="numeric" autocomplete="tel-national" maxlength="10" />
                                <p v-if="form.errors.whatsapp" class="field-error">{{ form.errors.whatsapp }}</p>
                            </div>
                        </fieldset>

                        <label class="check terms">
                            <input v-model="accepted" type="checkbox" />
                            <span>
                                Acepto los
                                <a v-if="legal.terms" :href="legal.terms" target="_blank" rel="noopener">términos y condiciones</a><template v-else>términos y condiciones</template>
                                y la
                                <a v-if="legal.privacy" :href="legal.privacy" target="_blank" rel="noopener">política de privacidad</a><template v-else>política de privacidad</template>.
                            </span>
                        </label>
                        <p v-if="form.errors.acepto" class="field-error">{{ form.errors.acepto }}</p>

                        <button type="submit" class="btn btn--primary pay-btn" :disabled="!canPay">
                            <template v-if="paying">Procesando pago…</template>
                            <template v-else>{{ method === "transfer" ? "Generar ficha SPEI" : "Pagar" }} {{ money(quote.total) }}</template>
                        </button>
                    </form>

                    <div class="pay-summary">
                        <OrderStub :event="event" :func="func" :quote="quote">
                            <div class="extras">
                                <p v-if="promotionText" class="promo">Promoción {{ promotionText }} aplicada.</p>

                                <form class="coupon" @submit.prevent="applyCoupon">
                                    <label for="coupon" class="visually-hidden">Código promocional</label>
                                    <input id="coupon" v-model.trim="couponInput" placeholder="Código promocional" maxlength="40" autocomplete="off" />
                                    <button v-if="quote.coupon" type="button" class="coupon-btn" @click="removeCoupon">Quitar</button>
                                    <button v-else type="submit" class="coupon-btn" :disabled="!couponInput || quoting">Aplicar</button>
                                </form>
                                <p v-if="couponError" class="field-error">{{ couponError }}</p>
                                <p v-else-if="quote.coupon" class="coupon-ok">{{ quote.coupon.description }}</p>

                                <label v-if="options.defender" class="defender">
                                    <input v-model="defender" type="checkbox" :disabled="quoting" />
                                    <span>
                                        <strong>Ticket Defender</strong>
                                        Protege tu compra si no puedes asistir por una eventualidad cubierta.
                                    </span>
                                </label>
                            </div>
                        </OrderStub>
                    </div>
                </div>
            </div>
        </main>

        <SiteFooter />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import SiteHeader from "@/components/SiteHeader.vue";
import SiteFooter from "@/components/SiteFooter.vue";
import CheckoutSteps from "@/components/checkout/CheckoutSteps.vue";
import HoldTimer from "@/components/checkout/HoldTimer.vue";
import OrderStub from "@/components/checkout/OrderStub.vue";
import { money, postJson } from "@/components/checkout/api.js";
import { loadOpenPay, tokenizeCard } from "@/components/checkout/openpay.js";

const props = defineProps({
    event: { type: Object, required: true },
    function: { type: Object, required: true },
    quote: { type: Object, required: true },
    promotionText: { type: String, default: "" },
    options: { type: Object, required: true },
    savedCards: { type: Array, default: () => [] },
    openpay: { type: Object, required: true },
    heldUntil: { type: Number, required: true },
    transferHours: { type: Number, default: 6 },
    whatsapp: { type: String, default: "" },
    blocked: { type: String, default: null },
    legal: { type: Object, default: () => ({}) },
    seatsUrl: { type: String, required: true },
});

const page = usePage();
const func = computed(() => props.function);

const quote = ref(props.quote);
const method = ref("card");
const cardChoice = ref(props.savedCards[0]?.id ?? "new");
const card = reactive({ holder: "", number: "", expiration: "", cvv: "" });
const saveCard = ref(true);
const installments = ref(null);
const defender = ref(false);
const couponInput = ref("");
const couponCode = ref(null);
const couponError = ref("");
const quoting = ref(false);
const sendWhatsapp = ref(false);
const whatsappPhone = ref(props.whatsapp ?? "");
const accepted = ref(false);
const paying = ref(false);
const clientError = ref("");
const deviceSessionId = ref("");

const form = useForm({});

const errorMessage = computed(() => clientError.value || page.props.flash?.error || Object.values(form.errors)[0] || "");

const cardReady = computed(() => {
    if (method.value !== "card") return true;
    if (cardChoice.value !== "new") return /^\d{3,4}$/.test(card.cvv);

    return card.holder.length > 2 && card.number.replace(/\D/g, "").length >= 13 && /^\d{2}\/\d{2}$/.test(card.expiration) && /^\d{3,4}$/.test(card.cvv);
});

const canPay = computed(() => !props.blocked && accepted.value && cardReady.value && !paying.value && !quoting.value);

function brandLabel(brand) {
    return { visa: "Visa", mastercard: "Mastercard", american_express: "American Express" }[brand] ?? "Tarjeta";
}

function formatCardNumber(event) {
    const digits = event.target.value.replace(/\D/g, "").slice(0, 19);
    card.number = digits.replace(/(\d{4})(?=\d)/g, "$1 ");
    event.target.value = card.number;
}

function formatExpiration(event) {
    const digits = event.target.value.replace(/\D/g, "").slice(0, 4);
    card.expiration = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
    event.target.value = card.expiration;
}

async function requote() {
    quoting.value = true;

    const { ok, status, data } = await postJson(route("checkout.quote", props.event.id), {
        meses: method.value === "card" ? installments.value : null,
        defender: defender.value,
        cupon: couponCode.value,
    });

    quoting.value = false;

    if (ok) {
        quote.value = data.quote;
        couponError.value = "";
        return true;
    }

    if (status === 409) {
        router.visit(props.seatsUrl);
        return false;
    }

    couponError.value = data?.errors?.cupon?.[0] ?? data?.message ?? "No pudimos actualizar el total.";
    return false;
}

async function applyCoupon() {
    couponCode.value = couponInput.value;
    if (!(await requote())) {
        couponCode.value = null;
    }
}

async function removeCoupon() {
    couponCode.value = null;
    couponInput.value = "";
    await requote();
}

watch([installments, defender], () => requote());

watch(method, (value) => {
    if (value === "transfer" && installments.value !== null) installments.value = null;
});

async function pay() {
    if (!canPay.value) return;

    clientError.value = "";
    paying.value = true;

    const payload = {
        metodo: method.value,
        meses: method.value === "card" ? installments.value : null,
        defender: defender.value,
        cupon: couponCode.value,
        whatsapp: sendWhatsapp.value ? whatsappPhone.value : null,
        acepto: accepted.value,
    };

    if (method.value === "card") {
        try {
            payload.device_session_id = deviceSessionId.value || (await loadOpenPay(props.openpay));

            if (cardChoice.value === "new") {
                const [month, year] = card.expiration.split("/");
                payload.token_id = await tokenizeCard({
                    card_number: card.number.replace(/\D/g, ""),
                    holder_name: card.holder,
                    expiration_month: month,
                    expiration_year: year,
                    cvv2: card.cvv,
                });
                payload.guardar_tarjeta = saveCard.value;
            } else {
                payload.card_id = cardChoice.value;
                payload.cvv = card.cvv;
            }
        } catch (error) {
            clientError.value = error.message;
            paying.value = false;
            return;
        }
    }

    form.transform(() => payload).post(route("checkout.pay", props.event.id), {
        preserveScroll: true,
        onFinish: () => {
            paying.value = false;
            card.cvv = "";
        },
    });
}

function expired() {
    router.visit(props.seatsUrl);
}

onMounted(async () => {
    try {
        deviceSessionId.value = await loadOpenPay(props.openpay);
    } catch (error) {
        clientError.value = error.message;
    }
});
</script>

<style scoped>
.page-layout {
    display: flex;
    flex-direction: column;
    min-height: 100dvh;
}

main {
    flex: 1;
}

.timer-bar {
    position: sticky;
    top: var(--header-height);
    z-index: 40;
    background: rgba(17, 17, 24, 0.94);
    border-bottom: 1px solid var(--color-border);
    backdrop-filter: blur(12px);
}

.timer-bar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    min-height: 64px;
    padding-block: var(--space-3);
}

.timer-event {
    min-width: 0;
}

.timer-event-name {
    font-weight: 800;
    font-size: var(--text-lg);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.timer-event-meta {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.timer-steps {
    display: none;
}

.pay-page {
    padding-block: var(--space-6) var(--space-12);
}

.pay-grid {
    display: grid;
    gap: var(--space-6);
}

.pay-form {
    display: grid;
    gap: var(--space-5);
    max-width: 640px;
}

.pay-heading {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 12px;
}

.pay-heading h1 {
    font-size: var(--text-3xl);
    font-weight: 800;
}

.back-link {
    color: var(--color-brand);
    font-size: var(--text-sm);
}

.notice {
    padding: 12px 16px;
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
}

.notice--error {
    border: 1px solid rgba(239, 68, 68, 0.4);
    background: rgba(239, 68, 68, 0.12);
    color: #fecaca;
}

.block {
    margin: 0;
    padding: 20px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    background: var(--color-surface-1);
    min-width: 0;
}

.block-title {
    float: left;
    width: 100%;
    margin-bottom: 14px;
    font-weight: 800;
    font-size: var(--text-lg);
}

.methods,
.saved-cards {
    clear: both;
    display: grid;
    gap: 10px;
}

.method,
.saved-card {
    position: relative;
    display: grid;
    gap: 2px;
    padding: 14px 16px 14px 44px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: border-color var(--transition-fast);
}

.method input,
.saved-card input {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    accent-color: var(--color-brand);
}

.method.is-active,
.saved-card.is-active {
    border-color: var(--color-brand);
    background: var(--color-brand-dim);
}

.method-name {
    font-weight: 700;
}

.method-hint {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.saved-card {
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 12px;
}

.saved-card-number,
.saved-card-exp {
    font-variant-numeric: tabular-nums;
    color: var(--color-text-secondary);
}

.card-fields {
    clear: both;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 14px;
}

.field {
    display: grid;
    gap: 6px;
}

.field--full {
    grid-column: 1 / -1;
}

.field label {
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
}

.field input,
.field select,
.coupon input {
    min-height: 48px;
    padding: 0 14px;
    border: 1.5px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    background: var(--color-surface-2);
    color: var(--color-text-primary);
    font-size: 16px;
    font-variant-numeric: tabular-nums;
}

.field input:focus,
.field select:focus,
.coupon input:focus {
    outline: none;
    border-color: var(--color-brand);
}

.installments {
    margin-top: 14px;
}

.check {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
    cursor: pointer;
}

.check input,
.defender input {
    margin-top: 3px;
    accent-color: var(--color-brand);
}

.check a {
    color: var(--color-brand);
    text-decoration: underline;
}

.secure-note,
.delivery-note,
.transfer-info p {
    clear: both;
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
    line-height: 1.6;
}

.secure-note {
    margin-top: 14px;
}

.delivery-note {
    margin-bottom: 10px;
}

.whatsapp-field {
    margin-top: 12px;
    max-width: 280px;
}

.field-error {
    color: #fca5a5;
    font-size: var(--text-sm);
}

.terms {
    font-size: var(--text-sm);
}

.btn {
    min-height: 52px;
    padding: 0 22px;
    border-radius: var(--radius-md);
    font-weight: 800;
    font-size: var(--text-base);
    cursor: pointer;
}

.btn--primary {
    border: none;
    background: var(--color-brand);
    color: #06291d;
    font-variant-numeric: tabular-nums;
}

.btn--primary:disabled {
    background: var(--color-surface-3);
    color: var(--color-text-muted);
    cursor: not-allowed;
}

.btn:focus-visible,
.coupon-btn:focus-visible,
.back-link:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.extras {
    display: grid;
    gap: 12px;
    margin-top: 16px;
}

.promo,
.coupon-ok {
    color: var(--color-brand);
    font-size: var(--text-sm);
}

.coupon {
    display: flex;
    gap: 8px;
}

.coupon input {
    flex: 1;
    min-width: 0;
    text-transform: uppercase;
}

.coupon-btn {
    padding: 0 16px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    background: transparent;
    color: var(--color-text-primary);
    font-weight: 700;
    cursor: pointer;
}

.coupon-btn:disabled {
    color: var(--color-text-muted);
    cursor: not-allowed;
}

.defender {
    display: flex;
    gap: 10px;
    padding: 12px;
    border: 1px dashed var(--color-border-strong);
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
    cursor: pointer;
}

.defender strong {
    display: block;
    color: var(--color-text-primary);
}

@media (min-width: 1024px) {
    .timer-steps {
        display: flex;
    }

    .pay-grid {
        grid-template-columns: minmax(0, 1fr) 400px;
        align-items: start;
    }

    .pay-summary {
        position: sticky;
        top: calc(var(--header-height) + 88px);
    }
}

@media (max-width: 520px) {
    .card-fields {
        grid-template-columns: 1fr;
    }
}

@media (prefers-reduced-motion: reduce) {
    .method,
    .saved-card {
        transition: none;
    }
}
</style>
