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

                <div class="container summary-toggle-wrap">
                    <button
                        type="button"
                        class="summary-toggle"
                        :aria-expanded="summaryOpen"
                        aria-controls="order-drawer"
                        @click="summaryOpen = !summaryOpen"
                    >
                        <span class="summary-toggle-label">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 9a3 3 0 0 0 0 6v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a3 3 0 0 0 0-6V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z" />
                                <path d="M13 5v2M13 11v2M13 17v2" />
                            </svg>
                            {{ ticketCountLabel }}
                        </span>
                        <span class="summary-toggle-total">
                            {{ money(quote.total) }}
                            <svg class="chevron" :class="{ 'is-open': summaryOpen }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </span>
                    </button>
                </div>

                <div v-show="summaryOpen" id="order-drawer" class="container summary-drawer">
                    <OrderStub :event="event" :func="func" :quote="quote" />
                </div>
            </div>

            <div class="container pay-page">
                <div class="pay-grid">
                    <aside class="pay-summary">
                        <OrderStub :event="event" :func="func" :quote="quote" />
                    </aside>

                    <form class="pay-form" novalidate @submit.prevent="pay" @focusin="onFocusIn" @focusout="onFocusOut">
                        <div class="pay-heading">
                            <div>
                                <h1>Pago</h1>
                                <p class="pay-subtitle">Tres pasos y tus boletos son tuyos.</p>
                            </div>
                            <Link :href="seatsUrl" class="back-link">Cambiar boletos</Link>
                        </div>

                        <p v-if="blocked" class="notice notice--error" role="alert">{{ blocked }}</p>
                        <p v-else-if="errorMessage" class="notice notice--error" role="alert">{{ errorMessage }}</p>
                        <p v-if="promotionText" class="notice notice--promo">Promoción {{ promotionText }} aplicada.</p>

                        <section class="block" aria-labelledby="step-method">
                            <header class="block-head">
                                <span class="step-badge is-done" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                </span>
                                <h2 id="step-method" class="block-title">Forma de pago</h2>
                            </header>

                            <div class="methods" role="radiogroup" aria-labelledby="step-method">
                                <label class="method" :class="{ 'is-active': method === 'card' }">
                                    <input v-model="method" type="radio" name="metodo" value="card" class="visually-hidden" />
                                    <span class="method-icon" aria-hidden="true">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20M6 15h4" /></svg>
                                    </span>
                                    <span class="method-text">
                                        <span class="method-name">Tarjeta</span>
                                        <span class="method-hint">Crédito o débito<template v-if="options.installments.length"> · hasta {{ maxInstallments }} MSI</template></span>
                                    </span>
                                    <span class="method-radio" aria-hidden="true" />
                                </label>
                                <label class="method" :class="{ 'is-active': method === 'transfer' }">
                                    <input v-model="method" type="radio" name="metodo" value="transfer" class="visually-hidden" />
                                    <span class="method-icon" aria-hidden="true">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-6 9 6" /><path d="M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 21h18" /></svg>
                                    </span>
                                    <span class="method-text">
                                        <span class="method-name">Transferencia SPEI</span>
                                        <span class="method-hint">Tienes {{ transferHours }} horas para pagar</span>
                                    </span>
                                    <span class="method-radio" aria-hidden="true" />
                                </label>
                            </div>
                        </section>

                        <section v-if="method === 'card'" class="block" aria-labelledby="step-card">
                            <header class="block-head">
                                <span class="step-badge" :class="{ 'is-done': cardDone }" aria-hidden="true">
                                    <svg v-if="cardDone" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                    <template v-else>2</template>
                                </span>
                                <h2 id="step-card" class="block-title">Datos de la tarjeta</h2>
                            </header>

                            <div v-if="savedCards.length" class="saved-cards" role="radiogroup" aria-label="Tarjetas guardadas">
                                <label v-for="saved in savedCards" :key="saved.id" class="saved-card" :class="{ 'is-active': cardChoice === saved.id }">
                                    <input v-model="cardChoice" type="radio" name="tarjeta" :value="saved.id" class="visually-hidden" />
                                    <span class="brand-pill">{{ brandShort(saved.brand) }}</span>
                                    <span class="saved-card-number">•••• {{ saved.last4 }}</span>
                                    <span class="saved-card-exp">{{ saved.expiration }}</span>
                                    <span class="method-radio" aria-hidden="true" />
                                </label>
                                <label class="saved-card" :class="{ 'is-active': cardChoice === 'new' }">
                                    <input v-model="cardChoice" type="radio" name="tarjeta" value="new" class="visually-hidden" />
                                    <span class="brand-pill brand-pill--ghost">+</span>
                                    <span class="saved-card-number">Usar otra tarjeta</span>
                                    <span />
                                    <span class="method-radio" aria-hidden="true" />
                                </label>
                            </div>

                            <div v-if="cardChoice === 'new'" class="card-fields">
                                <div class="field field--full" :class="{ 'has-error': showError('number') }">
                                    <label for="card-number">Número de tarjeta</label>
                                    <div class="input-wrap">
                                        <input
                                            id="card-number"
                                            :value="card.number"
                                            inputmode="numeric"
                                            autocomplete="cc-number"
                                            maxlength="23"
                                            placeholder="0000 0000 0000 0000"
                                            required
                                            :aria-invalid="showError('number')"
                                            :aria-describedby="showError('number') ? 'card-number-error' : undefined"
                                            @input="formatCardNumber"
                                            @blur="touched.number = true"
                                        />
                                        <Transition name="pop">
                                            <span v-if="cardBrand" class="brand-pill brand-pill--inline">{{ brandShort(cardBrand) }}</span>
                                        </Transition>
                                    </div>
                                    <p v-if="showError('number')" id="card-number-error" class="field-error">{{ fieldErrors.number }}</p>
                                </div>
                                <div class="field field--full" :class="{ 'has-error': showError('holder') }">
                                    <label for="card-holder">Nombre como aparece en la tarjeta</label>
                                    <input
                                        id="card-holder"
                                        v-model.trim="card.holder"
                                        autocomplete="cc-name"
                                        required
                                        :aria-invalid="showError('holder')"
                                        :aria-describedby="showError('holder') ? 'card-holder-error' : undefined"
                                        @blur="touched.holder = true"
                                    />
                                    <p v-if="showError('holder')" id="card-holder-error" class="field-error">{{ fieldErrors.holder }}</p>
                                </div>
                                <div class="field" :class="{ 'has-error': showError('expiration') }">
                                    <label for="card-exp">Vencimiento</label>
                                    <input
                                        id="card-exp"
                                        :value="card.expiration"
                                        inputmode="numeric"
                                        autocomplete="cc-exp"
                                        maxlength="5"
                                        placeholder="MM/AA"
                                        required
                                        :aria-invalid="showError('expiration')"
                                        :aria-describedby="showError('expiration') ? 'card-exp-error' : undefined"
                                        @input="formatExpiration"
                                        @blur="touched.expiration = true"
                                    />
                                    <p v-if="showError('expiration')" id="card-exp-error" class="field-error">{{ fieldErrors.expiration }}</p>
                                </div>
                                <div class="field" :class="{ 'has-error': showError('cvv') }">
                                    <label for="card-cvv">CVV</label>
                                    <input
                                        id="card-cvv"
                                        v-model="card.cvv"
                                        v-digits
                                        inputmode="numeric"
                                        autocomplete="cc-csc"
                                        maxlength="4"
                                        :placeholder="cardBrand === 'american_express' ? '4 dígitos' : '3 dígitos'"
                                        required
                                        :aria-invalid="showError('cvv')"
                                        :aria-describedby="showError('cvv') ? 'card-cvv-error' : undefined"
                                        @blur="touched.cvv = true"
                                    />
                                    <p v-if="showError('cvv')" id="card-cvv-error" class="field-error">{{ fieldErrors.cvv }}</p>
                                </div>
                                <label class="check field--full">
                                    <input v-model="saveCard" type="checkbox" />
                                    Guardar esta tarjeta para mis próximas compras
                                </label>
                            </div>

                            <div v-else class="card-fields">
                                <div class="field" :class="{ 'has-error': showError('cvv') }">
                                    <label for="saved-cvv">CVV de la tarjeta</label>
                                    <input
                                        id="saved-cvv"
                                        v-model="card.cvv"
                                        v-digits
                                        inputmode="numeric"
                                        autocomplete="cc-csc"
                                        maxlength="4"
                                        placeholder="CVV"
                                        required
                                        :aria-invalid="showError('cvv')"
                                        :aria-describedby="showError('cvv') ? 'saved-cvv-error' : undefined"
                                        @blur="touched.cvv = true"
                                    />
                                    <p v-if="showError('cvv')" id="saved-cvv-error" class="field-error">{{ fieldErrors.cvv }}</p>
                                </div>
                            </div>

                            <div v-if="options.installments.length" class="plans-wrap">
                                <p id="plans-label" class="plans-label">¿Cómo quieres pagar?</p>
                                <div class="plans" role="radiogroup" aria-labelledby="plans-label">
                                    <label class="plan" :class="{ 'is-active': installments === null }">
                                        <input v-model="installments" type="radio" name="meses" :value="null" class="visually-hidden" :disabled="quoting" />
                                        <span class="plan-main">Contado</span>
                                        <span class="plan-sub">Un solo pago</span>
                                    </label>
                                    <label v-for="plan in options.installments" :key="plan" class="plan" :class="{ 'is-active': installments === plan }">
                                        <input v-model="installments" type="radio" name="meses" :value="plan" class="visually-hidden" :disabled="quoting" />
                                        <span class="plan-main">{{ plan }} meses</span>
                                        <span class="plan-sub">sin intereses</span>
                                    </label>
                                </div>
                                <p v-if="quote.installments" class="plans-note">{{ quote.installments }} pagos de {{ money(quote.total / quote.installments) }}</p>
                            </div>

                            <p class="secure-note">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                                Tu banco confirma el pago (3D Secure). DeBoleto no guarda el número de tu tarjeta.
                            </p>
                        </section>

                        <section v-else class="block" aria-labelledby="step-transfer">
                            <header class="block-head">
                                <span class="step-badge is-done" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                </span>
                                <h2 id="step-transfer" class="block-title">Cómo funciona SPEI</h2>
                            </header>
                            <ol class="transfer-steps">
                                <li><span>1</span>Confirma y te mostramos una cuenta CLABE con el monto exacto.</li>
                                <li><span>2</span>Transfiere desde la app de tu banco en menos de {{ transferHours }} horas.</li>
                                <li><span>3</span>Al recibir tu pago te enviamos tus boletos. Si no llega a tiempo, los lugares se liberan.</li>
                            </ol>
                        </section>

                        <section class="block" aria-labelledby="step-delivery">
                            <header class="block-head">
                                <span class="step-badge" :class="{ 'is-done': deliveryDone }" aria-hidden="true">
                                    <svg v-if="deliveryDone" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                    <template v-else>3</template>
                                </span>
                                <h2 id="step-delivery" class="block-title">Entrega de boletos</h2>
                            </header>
                            <p class="delivery-note">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="m22 7-10 6L2 7" /></svg>
                                Llegan a tu correo en cuanto se confirme el pago.
                            </p>
                            <label class="switch">
                                <input v-model="sendWhatsapp" type="checkbox" role="switch" />
                                <span class="switch-track" aria-hidden="true"><span class="switch-thumb" /></span>
                                Recibirlos también por WhatsApp
                            </label>
                            <div v-if="sendWhatsapp" class="field whatsapp-field" :class="{ 'has-error': showWhatsappError }">
                                <label for="whatsapp">Número de WhatsApp (10 dígitos)</label>
                                <input
                                    id="whatsapp"
                                    v-model="whatsappPhone"
                                    v-digits
                                    type="tel"
                                    inputmode="numeric"
                                    autocomplete="tel-national"
                                    maxlength="10"
                                    :aria-invalid="showWhatsappError"
                                    @blur="touched.whatsapp = true"
                                />
                                <p v-if="form.errors.whatsapp" class="field-error">{{ form.errors.whatsapp }}</p>
                                <p v-else-if="showWhatsappError" class="field-error">Escribe los 10 dígitos de tu número.</p>
                            </div>
                        </section>

                        <section class="block block--extras">
                            <button
                                type="button"
                                class="extras-toggle"
                                :aria-expanded="extrasOpen"
                                aria-controls="extras-panel"
                                @click="extrasOpen = !extrasOpen"
                            >
                                <span>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" /><circle cx="7" cy="7" r="1.5" /></svg>
                                    {{ extrasTitle }}
                                </span>
                                <span v-if="extrasSummary" class="extras-badge">{{ extrasSummary }}</span>
                                <svg class="chevron" :class="{ 'is-open': extrasOpen }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </button>

                            <div v-show="extrasOpen" id="extras-panel" class="extras">
                                <div>
                                    <div class="coupon">
                                        <label for="coupon" class="visually-hidden">Código promocional</label>
                                        <input
                                            id="coupon"
                                            v-model.trim="couponInput"
                                            placeholder="Código promocional"
                                            maxlength="40"
                                            autocomplete="off"
                                            :disabled="Boolean(quote.coupon)"
                                            @keydown.enter.prevent="quote.coupon || applyCoupon()"
                                        />
                                        <button v-if="quote.coupon" type="button" class="coupon-btn" @click="removeCoupon">Quitar</button>
                                        <button v-else type="button" class="coupon-btn" :disabled="!couponInput || quoting" @click="applyCoupon">Aplicar</button>
                                    </div>
                                    <p v-if="couponError" class="field-error">{{ couponError }}</p>
                                    <p v-else-if="quote.coupon" class="coupon-ok">{{ quote.coupon.description }}</p>
                                </div>

                                <label v-if="options.defender" class="defender" :class="{ 'is-active': defender }">
                                    <input v-model="defender" type="checkbox" :disabled="quoting" />
                                    <span>
                                        <strong>Ticket Defender</strong>
                                        Protege tu compra si no puedes asistir por una eventualidad cubierta.
                                    </span>
                                </label>
                            </div>
                        </section>

                        <div class="action-bar" :class="{ 'is-hidden': typing }">
                            <div class="action-bar-inner">
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

                                <button type="submit" class="btn btn--primary pay-btn" :disabled="!canPay" :aria-describedby="pendingReason ? 'pay-reason' : undefined">
                                    <template v-if="paying">
                                        <span class="spinner" aria-hidden="true" />
                                        Procesando pago…
                                    </template>
                                    <template v-else>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                                        {{ method === "transfer" ? "Generar ficha SPEI" : "Pagar" }} {{ money(quote.total) }}
                                    </template>
                                </button>
                                <p v-if="pendingReason" id="pay-reason" class="pay-reason">{{ pendingReason }}</p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <SiteFooter />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue";
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
const touched = reactive({ holder: false, number: false, expiration: false, cvv: false, whatsapp: false });
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
const summaryOpen = ref(false);
const extrasOpen = ref(Boolean(props.quote.coupon));
const textFocused = ref(false);
const keyboardOpen = ref(false);
const typing = computed(() => textFocused.value && keyboardOpen.value);

const form = useForm({});

const errorMessage = computed(() => clientError.value || page.props.flash?.error || Object.values(form.errors)[0] || "");

const ticketCountLabel = computed(() => {
    const count = quote.value.lines?.length ?? 0;
    return `${count} ${count === 1 ? "boleto" : "boletos"}`;
});

const maxInstallments = computed(() => Math.max(...props.options.installments));

const cardDigits = computed(() => card.number.replace(/\D/g, ""));

const cardBrand = computed(() => {
    const digits = cardDigits.value;
    if (/^4/.test(digits)) return "visa";
    if (/^3[47]/.test(digits)) return "american_express";
    if (/^(5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d\d|27[01]\d|2720)/.test(digits)) return "mastercard";
    return null;
});

const expirationValid = computed(() => {
    const match = /^(\d{2})\/(\d{2})$/.exec(card.expiration);
    if (!match) return false;
    const month = Number(match[1]);
    return month >= 1 && month <= 12;
});

const fieldErrors = computed(() => ({
    number: cardDigits.value.length >= 13 ? "" : "Revisa el número de tu tarjeta.",
    holder: card.holder.length > 2 ? "" : "Escribe el nombre tal como aparece en la tarjeta.",
    expiration: expirationValid.value ? "" : "Usa el formato MM/AA.",
    cvv: /^\d{3,4}$/.test(card.cvv) ? "" : "Son los 3 o 4 dígitos al reverso de la tarjeta.",
}));

function showError(field) {
    return Boolean(touched[field] && fieldErrors.value[field]);
}

const cardReady = computed(() => {
    if (method.value !== "card") return true;
    if (cardChoice.value !== "new") return !fieldErrors.value.cvv;

    return !fieldErrors.value.number && !fieldErrors.value.holder && !fieldErrors.value.expiration && !fieldErrors.value.cvv;
});

const cardDone = computed(() => cardReady.value);
const deliveryDone = computed(() => !sendWhatsapp.value || whatsappPhone.value.length === 10);
const showWhatsappError = computed(() => touched.whatsapp && !deliveryDone.value);

const canPay = computed(() => !props.blocked && accepted.value && cardReady.value && deliveryDone.value && !paying.value && !quoting.value);

const missingReason = computed(() => {
    if (method.value === "card") {
        if (cardChoice.value === "new") {
            if (fieldErrors.value.number) return "Falta el número de tarjeta";
            if (fieldErrors.value.holder) return "Falta el nombre del titular";
            if (fieldErrors.value.expiration) return "Falta el vencimiento";
        }
        if (fieldErrors.value.cvv) return "Falta el CVV";
    }
    if (!deliveryDone.value) return "Falta tu número de WhatsApp";
    if (!accepted.value) return "Acepta los términos para continuar";
    return "";
});

const pendingReason = computed(() => {
    if (paying.value) return "";
    if (props.blocked) return props.blocked;
    if (quoting.value) return "Actualizando el total…";
    return missingReason.value;
});

const extrasTitle = computed(() => (props.options.defender ? "Código promocional y protección" : "¿Tienes un código promocional?"));

const extrasSummary = computed(() => {
    const parts = [];
    if (quote.value.coupon) parts.push(quote.value.coupon.code);
    if (defender.value) parts.push("Defender");
    return parts.join(" · ");
});

function brandShort(brand) {
    return { visa: "VISA", mastercard: "Mastercard", american_express: "AMEX" }[brand] ?? "Tarjeta";
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

function isTextInput(target) {
    return target instanceof HTMLInputElement && !["checkbox", "radio"].includes(target.type);
}

function onFocusIn(event) {
    textFocused.value = isTextInput(event.target);
}

function onFocusOut(event) {
    if (isTextInput(event.target)) textFocused.value = false;
}

function syncViewport() {
    const viewport = window.visualViewport;
    keyboardOpen.value = Boolean(viewport) && viewport.height < window.innerHeight * 0.8;
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
    if (!couponInput.value || quoting.value) return;
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
    if (!canPay.value) {
        if (!paying.value) {
            Object.keys(touched).forEach((field) => (touched[field] = true));
            document.activeElement?.blur();
        }
        return;
    }

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
                    card_number: cardDigits.value,
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
            touched.cvv = false;
        },
    });
}

function expired() {
    router.visit(props.seatsUrl);
}

onMounted(async () => {
    window.visualViewport?.addEventListener("resize", syncViewport);

    try {
        deviceSessionId.value = await loadOpenPay(props.openpay);
    } catch (error) {
        clientError.value = error.message;
    }
});

onUnmounted(() => {
    window.visualViewport?.removeEventListener("resize", syncViewport);
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

.summary-toggle-wrap {
    padding-bottom: var(--space-3);
}

.summary-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    min-height: 44px;
    padding: 0 14px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    background: var(--color-surface-2);
    color: var(--color-text-primary);
    font-size: var(--text-sm);
    font-weight: 700;
    cursor: pointer;
}

.summary-toggle-label,
.summary-toggle-total {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.summary-toggle-label svg {
    color: var(--color-brand);
}

.summary-toggle-total {
    font-size: var(--text-base);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.chevron {
    transition: transform var(--transition-fast);
}

.chevron.is-open {
    transform: rotate(180deg);
}

.summary-drawer {
    max-height: calc(100dvh - var(--header-height) - 140px);
    overflow-y: auto;
    padding-bottom: var(--space-4);
    overscroll-behavior: contain;
}

.page-layout {
    padding-bottom: calc(200px + env(safe-area-inset-bottom));
}

.pay-page {
    padding-block: var(--space-6) var(--space-12);
}

.pay-grid {
    display: grid;
    gap: var(--space-6);
}

.pay-summary {
    display: none;
}

.pay-form {
    display: grid;
    gap: var(--space-4);
    max-width: 640px;
    width: 100%;
}

.pay-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.pay-heading h1 {
    font-size: var(--text-3xl);
    font-weight: 800;
    line-height: 1.1;
}

.pay-subtitle {
    margin-top: 4px;
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.back-link {
    flex-shrink: 0;
    margin-top: 6px;
    color: var(--color-brand);
    font-size: var(--text-sm);
    font-weight: 600;
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

.notice--promo {
    border: 1px solid var(--color-brand);
    background: var(--color-brand-dim);
    color: var(--color-brand);
    font-weight: 700;
}

.block {
    padding: 20px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    background: var(--color-surface-1);
    min-width: 0;
}

.block-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.block-title {
    font-weight: 800;
    font-size: var(--text-lg);
}

.step-badge {
    display: inline-grid;
    place-items: center;
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border: 2px solid var(--color-border-strong);
    border-radius: 999px;
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
    font-weight: 800;
    transition:
        background var(--transition-fast),
        border-color var(--transition-fast),
        color var(--transition-fast);
}

.step-badge.is-done {
    border-color: var(--color-brand);
    background: var(--color-brand);
    color: #06291d;
}

.methods {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.method {
    position: relative;
    display: grid;
    gap: 10px;
    padding: 16px;
    border: 1.5px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    background: var(--color-surface-2);
    cursor: pointer;
    transition:
        border-color var(--transition-fast),
        background var(--transition-fast),
        transform var(--transition-fast);
}

.method:hover {
    border-color: var(--color-text-muted);
}

.method:active {
    transform: scale(0.98);
}

.method.is-active {
    border-color: var(--color-brand);
    background: var(--color-brand-dim);
    box-shadow: 0 0 0 3px rgba(78, 203, 160, 0.15);
}

.method:has(input:focus-visible),
.saved-card:has(input:focus-visible),
.plan:has(input:focus-visible) {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.method-icon {
    display: inline-grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    background: var(--color-surface-3);
    color: var(--color-text-secondary);
    transition:
        background var(--transition-fast),
        color var(--transition-fast);
}

.method.is-active .method-icon {
    background: var(--color-brand);
    color: #06291d;
}

.method-text {
    display: grid;
    gap: 2px;
}

.method-name {
    font-weight: 800;
}

.method-hint {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
    line-height: 1.4;
}

.method-radio {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 20px;
    height: 20px;
    border: 2px solid var(--color-border-strong);
    border-radius: 999px;
    transition: border var(--transition-fast);
}

.is-active > .method-radio {
    border: 6px solid var(--color-brand);
}

.saved-cards {
    display: grid;
    gap: 8px;
    margin-bottom: 16px;
}

.saved-card {
    position: relative;
    display: grid;
    grid-template-columns: auto 1fr auto 24px;
    align-items: center;
    gap: 12px;
    min-height: 56px;
    padding: 0 14px;
    border: 1.5px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition:
        border-color var(--transition-fast),
        background var(--transition-fast);
}

.saved-card .method-radio {
    position: static;
}

.saved-card.is-active {
    border-color: var(--color-brand);
    background: var(--color-brand-dim);
}

.saved-card-number,
.saved-card-exp {
    font-variant-numeric: tabular-nums;
}

.saved-card-number {
    font-weight: 700;
}

.saved-card-exp {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.brand-pill {
    display: inline-grid;
    place-items: center;
    min-width: 52px;
    height: 26px;
    padding: 0 8px;
    border-radius: 6px;
    background: var(--color-text-primary);
    color: var(--color-bg);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.04em;
}

.brand-pill--ghost {
    border: 1.5px dashed var(--color-border-strong);
    background: transparent;
    color: var(--color-text-secondary);
    font-size: var(--text-base);
}

.brand-pill--inline {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
}

.card-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.field {
    display: grid;
    gap: 6px;
    align-content: start;
}

.field--full {
    grid-column: 1 / -1;
}

.field label {
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--color-text-secondary);
}

.input-wrap {
    position: relative;
}

.input-wrap input {
    width: 100%;
    padding-right: 84px;
}

.field input,
.coupon input {
    min-height: 50px;
    padding-inline: 14px;
    border: 1.5px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    background: var(--color-surface-2);
    color: var(--color-text-primary);
    font-size: 16px;
    font-variant-numeric: tabular-nums;
    transition:
        border-color var(--transition-fast),
        box-shadow var(--transition-fast);
}

.field input:focus,
.coupon input:focus {
    outline: none;
    border-color: var(--color-brand);
    box-shadow: 0 0 0 3px rgba(78, 203, 160, 0.18);
}

.field.has-error input {
    border-color: var(--color-danger);
}

.field-error {
    color: #fca5a5;
    font-size: var(--text-sm);
}

.plans-wrap {
    margin-top: 20px;
}

.plans-label {
    margin-bottom: 8px;
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--color-text-secondary);
}

.plans {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(96px, 1fr));
    gap: 8px;
}

.plan {
    display: grid;
    justify-items: center;
    gap: 2px;
    padding: 12px 8px;
    border: 1.5px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    text-align: center;
    cursor: pointer;
    transition:
        border-color var(--transition-fast),
        background var(--transition-fast);
}

.plan.is-active {
    border-color: var(--color-brand);
    background: var(--color-brand-dim);
}

.plan-main {
    font-weight: 800;
}

.plan-sub {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
}

.plan.is-active .plan-sub {
    color: var(--color-brand);
}

.plans-note {
    margin-top: 8px;
    color: var(--color-brand);
    font-size: var(--text-sm);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.secure-note,
.delivery-note {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
    line-height: 1.5;
}

.secure-note {
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border);
}

.secure-note svg,
.delivery-note svg {
    flex-shrink: 0;
    margin-top: 2px;
    color: var(--color-brand);
}

.delivery-note {
    margin-bottom: 14px;
}

.transfer-steps {
    display: grid;
    gap: 12px;
    list-style: none;
    margin: 0;
    padding: 0;
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
    line-height: 1.5;
}

.transfer-steps li {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.transfer-steps span {
    display: inline-grid;
    place-items: center;
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border-radius: 999px;
    background: var(--color-brand-dim);
    color: var(--color-brand);
    font-size: var(--text-xs);
    font-weight: 800;
}

.switch {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    font-size: var(--text-sm);
    font-weight: 600;
    cursor: pointer;
}

.switch input {
    position: absolute;
    opacity: 0;
    width: 1px;
    height: 1px;
}

.switch-track {
    position: relative;
    flex-shrink: 0;
    width: 44px;
    height: 26px;
    border-radius: 999px;
    background: var(--color-surface-3);
    transition: background var(--transition-fast);
}

.switch-thumb {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    border-radius: 999px;
    background: var(--color-text-primary);
    transition: transform var(--transition-fast);
}

.switch input:checked + .switch-track {
    background: var(--color-brand);
}

.switch input:checked + .switch-track .switch-thumb {
    transform: translateX(18px);
}

.switch input:focus-visible + .switch-track {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.whatsapp-field {
    margin-top: 14px;
    max-width: 280px;
}

.block--extras {
    padding: 0;
}

.extras-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 56px;
    padding: 0 20px;
    border: none;
    background: transparent;
    color: var(--color-text-primary);
    font-size: var(--text-sm);
    font-weight: 700;
    text-align: left;
    cursor: pointer;
}

.extras-toggle > span:first-child {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.extras-toggle > span:first-child svg {
    color: var(--color-brand);
}

.extras-badge {
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--color-brand-dim);
    color: var(--color-brand);
    font-size: var(--text-xs);
    font-weight: 800;
}

.extras {
    display: grid;
    gap: 12px;
    padding: 0 20px 20px;
}

.coupon-ok {
    margin-top: 6px;
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

.coupon input:disabled {
    color: var(--color-brand);
    font-weight: 700;
}

.coupon-btn {
    padding: 0 18px;
    border: 1.5px solid var(--color-border-strong);
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
    padding: 14px;
    border: 1.5px dashed var(--color-border-strong);
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
    cursor: pointer;
    transition:
        border-color var(--transition-fast),
        background var(--transition-fast);
}

.defender.is-active {
    border-style: solid;
    border-color: var(--color-brand);
    background: var(--color-brand-dim);
}

.defender strong {
    display: block;
    color: var(--color-text-primary);
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
    flex-shrink: 0;
    width: 18px;
    height: 18px;
    margin-top: 1px;
    accent-color: var(--color-brand);
}

.check a {
    color: var(--color-brand);
    text-decoration: underline;
}

.action-bar {
    position: fixed;
    inset: auto 0 0;
    z-index: 45;
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
    border-top: 1px solid var(--color-border);
    background: rgba(17, 17, 24, 0.96);
    backdrop-filter: blur(12px);
    box-shadow: 0 -12px 32px rgba(0, 0, 0, 0.35);
    transition: transform var(--transition-base);
}

.action-bar.is-hidden {
    transform: translateY(110%);
}

.action-bar-inner {
    display: grid;
    gap: 10px;
    max-width: 640px;
    margin-inline: auto;
}

.terms {
    font-size: var(--text-xs);
    line-height: 1.5;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 54px;
    padding: 0 22px;
    border-radius: var(--radius-md);
    font-weight: 800;
    font-size: var(--text-lg);
    cursor: pointer;
    transition:
        background var(--transition-fast),
        transform var(--transition-fast),
        box-shadow var(--transition-fast);
}

.btn--primary {
    border: none;
    background: var(--color-brand);
    color: #06291d;
    font-variant-numeric: tabular-nums;
    box-shadow: 0 8px 24px rgba(78, 203, 160, 0.3);
}

.btn--primary:not(:disabled):hover {
    background: var(--color-brand-hover);
}

.btn--primary:not(:disabled):active {
    transform: scale(0.98);
}

.btn--primary:disabled {
    background: var(--color-surface-3);
    color: var(--color-text-muted);
    box-shadow: none;
    cursor: not-allowed;
}

.pay-reason {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
    text-align: center;
}

.spinner {
    width: 18px;
    height: 18px;
    border: 2.5px solid currentColor;
    border-right-color: transparent;
    border-radius: 999px;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.pop-enter-active,
.pop-leave-active {
    transition:
        opacity var(--transition-fast),
        transform var(--transition-fast);
}

.pop-enter-from,
.pop-leave-to {
    opacity: 0;
    transform: translateY(-50%) scale(0.8);
}

.btn:focus-visible,
.coupon-btn:focus-visible,
.back-link:focus-visible,
.summary-toggle:focus-visible,
.extras-toggle:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

@media (min-width: 1024px) {
    .timer-steps {
        display: flex;
    }

    .page-layout {
        padding-bottom: 0;
    }

    .summary-toggle-wrap,
    .summary-drawer {
        display: none !important;
    }

    .pay-page {
        padding-block: var(--space-8) var(--space-12);
    }

    .pay-grid {
        grid-template-columns: 400px minmax(0, 1fr);
        align-items: start;
    }

    .pay-summary {
        display: block;
        position: sticky;
        top: calc(var(--header-height) + 88px);
    }

    .action-bar {
        position: static;
        padding: 0;
        border: none;
        background: none;
        backdrop-filter: none;
        box-shadow: none;
        transform: none !important;
    }

    .action-bar-inner {
        max-width: none;
        margin: 0;
    }

    .terms {
        font-size: var(--text-sm);
    }
}

@media (max-width: 520px) {
    .methods {
        grid-template-columns: 1fr;
    }

    .method {
        grid-template-columns: auto 1fr;
        align-items: center;
        padding: 14px 48px 14px 14px;
    }

    .method-radio {
        top: 50%;
        transform: translateY(-50%);
    }

    .card-fields {
        grid-template-columns: 1fr 1fr;
    }

    .whatsapp-field {
        max-width: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .method,
    .saved-card,
    .plan,
    .step-badge,
    .chevron,
    .action-bar,
    .btn,
    .switch-track,
    .switch-thumb,
    .defender,
    .pop-enter-active,
    .pop-leave-active {
        transition: none;
    }

    .spinner {
        animation-duration: 2s;
    }
}
</style>
