<template>
    <SeoHead />

    <div class="page-layout">
        <SiteHeader @open-login="openLogin" @open-register="openRegister" />

        <main id="main-content">
            <div v-if="$page.props.flash?.error" class="container">
                <p class="flash-error" role="alert">{{ $page.props.flash.error }}</p>
            </div>
            <section class="hero-section">
                <div class="container">
                    <div class="hero-frame">
                        <picture class="hero-picture">
                            <source
                                :srcset="heroImage.replace('.png', '.webp')"
                                type="image/webp"
                            />
                            <source
                                :srcset="heroImage.replace('.png', '.avif')"
                                type="image/avif"
                            />
                            <img
                                :src="heroImage"
                                :alt="event.name"
                                class="hero-image"
                            />
                        </picture>
                    </div>
                </div>
            </section>

            <section class="info-bar">
                <div class="container">
                    <div class="info-bar-inner">
                        <div class="info-text">
                            <h1 class="event-title">{{ event.name }}</h1>
                            <p
                                v-if="formattedMainDate || event.venue"
                                class="event-meta"
                            >
                                <template v-if="formattedMainDate">
                                    <svg
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <rect
                                            x="3"
                                            y="4"
                                            width="18"
                                            height="18"
                                            rx="2"
                                            ry="2"
                                        />
                                        <line x1="16" y1="2" x2="16" y2="6" />
                                        <line x1="8" y1="2" x2="8" y2="6" />
                                        <line x1="3" y1="10" x2="21" y2="10" />
                                    </svg>
                                    {{ formattedMainDate }}
                                </template>
                            </p>
                        </div>
                        <button
                            v-if="canBuy"
                            type="button"
                            class="btn-buy"
                            @click="buy()"
                        >
                            COMPRAR BOLETO
                        </button>
                    </div>
                </div>
            </section>

            <section class="body-section">
                <div class="container">
                    <div class="body-grid">
                        <div class="main-column">
                            <div class="detail-block">
                                <h2 class="block-title">Acerca del evento</h2>
                                <p>{{ event.desc }}</p>
                                &nbsp;
                                <hr />
                                &nbsp;
                                <h2 class="block-title">
                                    Información importante
                                </h2>
                                <p class="event-comments">
                                    {{ formattedComments }}
                                </p>
                            </div>
                            <div v-if="kit" class="detail-block">
                                <h2 class="block-title">
                                    <svg
                                        width="20"
                                        height="20"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"
                                        />
                                    </svg>
                                    Tu kit incluye
                                </h2>
                                <ul v-if="kit.items.length" class="kit-items">
                                    <li
                                        v-for="item in kit.items"
                                        :key="item.name"
                                        class="kit-item"
                                    >
                                        <KitIcon
                                            :name="item.icon"
                                            :size="item.icon ? 20 : 16"
                                        />
                                        <span>{{ item.name }}</span>
                                    </li>
                                </ul>
                                <dl
                                    v-if="kit.deliveryDate || kit.deliveryPlace"
                                    class="kit-delivery"
                                >
                                    <div v-if="kit.deliveryDate">
                                        <dt>Entrega del kit</dt>
                                        <dd>
                                            {{ formatDate(kit.deliveryDate) }}
                                            <template
                                                v-if="hasTime(kit.deliveryDate)"
                                            >
                                                ·
                                                {{ formatTime(kit.deliveryDate) }}
                                            </template>
                                        </dd>
                                    </div>
                                    <div v-if="kit.deliveryPlace">
                                        <dt>Lugar de entrega</dt>
                                        <dd>{{ kit.deliveryPlace }}</dd>
                                    </div>
                                </dl>
                            </div>
                            <div v-if="convocatoria" class="detail-block">
                                <h2 class="block-title">
                                    <svg
                                        width="20"
                                        height="20"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"
                                        />
                                        <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                                        <path d="M10 9H8" />
                                        <path d="M16 13H8" />
                                        <path d="M16 17H8" />
                                    </svg>
                                    Convocatoria
                                </h2>
                                <p class="convocatoria-text">
                                    Consulta las bases, rutas, categorías y
                                    premiación de la carrera.
                                </p>
                                <a
                                    :href="convocatoria"
                                    class="convocatoria-link"
                                    target="_blank"
                                    rel="noopener"
                                    download
                                >
                                    <svg
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d="M12 15V3" />
                                        <path
                                            d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"
                                        />
                                        <path d="m7 10 5 5 5-5" />
                                    </svg>
                                    Descargar convocatoria (PDF)
                                </a>
                            </div>
                            <div
                                v-if="event.functions.length"
                                id="fechas"
                                class="detail-block"
                            >
                                <h2 class="block-title">
                                    <svg
                                        width="20"
                                        height="20"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <rect
                                            x="3"
                                            y="4"
                                            width="18"
                                            height="18"
                                            rx="2"
                                            ry="2"
                                        />
                                        <line x1="16" y1="2" x2="16" y2="6" />
                                        <line x1="8" y1="2" x2="8" y2="6" />
                                        <line x1="3" y1="10" x2="21" y2="10" />
                                    </svg>
                                    Fechas del evento
                                </h2>
                                <p
                                    v-if="needsDate && !chosenFunction"
                                    class="date-hint"
                                    role="status"
                                >
                                    Elige la fecha a la que quieres asistir.
                                </p>
                                <ul class="function-list">
                                    <li
                                        v-for="fn in event.functions"
                                        :key="fn.id"
                                        class="function-item"
                                        :class="{
                                            'function-item--active':
                                                selectedFunctionId ===
                                                String(fn.id),
                                            'function-item--clickable': canBuy,
                                        }"
                                    >
                                        <component
                                            :is="canBuy ? 'a' : 'div'"
                                            :href="
                                                canBuy
                                                    ? route('checkout.map', {
                                                          evento: event.id,
                                                          function: fn.id,
                                                      })
                                                    : undefined
                                            "
                                            class="function-link"
                                            @click="onFunctionClick($event, fn)"
                                        >
                                            <div class="function-date-badge">
                                                <span class="d">{{
                                                    getDay(fn.date)
                                                }}</span>
                                                <span class="m">{{
                                                    getMonth(fn.date)
                                                }}</span>
                                            </div>
                                            <div class="fn-info">
                                                <span class="fn-event-name">{{
                                                    event.name
                                                }}</span>
                                                <span class="fn-details"
                                                    >{{ formatTime(fn.date) }},
                                                    {{ event.venueName }} -
                                                    Villahermosa, Tabasco</span
                                                >
                                            </div>
                                            <span
                                                v-if="canBuy"
                                                class="function-cta"
                                            >
                                                Elegir asientos
                                                <svg
                                                    width="16"
                                                    height="16"
                                                    viewBox="0 0 16 16"
                                                    fill="none"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        d="M6 4L10 8L6 12"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                            </span>
                                        </component>
                                    </li>
                                </ul>
                            </div>

                            <div class="detail-block">
                                <template v-if="canBuy">
                                    <button
                                        type="button"
                                        class="btn-buy"
                                        @click="buy()"
                                    >
                                        COMPRAR BOLETO
                                    </button>
                                    <div class="event-info-label">
                                        <span
                                            >Tabasco -
                                            {{ event.venueName }}</span
                                        >
                                        <span>1 evento</span>
                                    </div>
                                </template>
                            </div>

                            <section
                                v-if="sponsors.length"
                                class="sponsors-section"
                            >
                                <h2 class="sponsors-title block-title">
                                    Patrocinadores
                                </h2>
                                <div class="sponsor-wrapper">
                                    <a
                                        href="javascript:void(0)"
                                        class="sponsor-arrow sponsor-arrow-prev"
                                        aria-label="Anterior"
                                        @click.prevent="scrollSponsorsPrev"
                                    >
                                        <svg
                                            width="16"
                                            height="16"
                                            viewBox="0 0 16 16"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M10 12L6 8L10 4"
                                                stroke="white"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </a>
                                    <div
                                        ref="sponsorScrollRef"
                                        class="sponsor-scroll"
                                        @mousedown="onSponsorMouseDown"
                                        @mousemove="onSponsorMouseMove"
                                        @mouseup="onSponsorMouseUp"
                                        @mouseleave="onSponsorMouseUp"
                                        @click.capture="onSponsorClick"
                                    >
                                        <ul class="sponsor-track">
                                            <li
                                                v-for="s in sponsors"
                                                :key="s.image"
                                            >
                                                <component
                                                    :is="s.url ? 'a' : 'div'"
                                                    :href="s.url || undefined"
                                                    :target="s.url ? '_blank' : undefined"
                                                    :rel="s.url ? 'noopener sponsored' : undefined"
                                                    :title="s.name"
                                                    class="sponsor-card"
                                                    draggable="false"
                                                >
                                                    <img
                                                        :src="s.image"
                                                        :alt="s.name"
                                                        draggable="false"
                                                    />
                                                    <h3>{{ s.name }}</h3>
                                                </component>
                                            </li>
                                        </ul>
                                    </div>
                                    <a
                                        href="javascript:void(0)"
                                        class="sponsor-arrow sponsor-arrow-next"
                                        aria-label="Siguiente"
                                        @click.prevent="scrollSponsorsNext"
                                    >
                                        <svg
                                            width="16"
                                            height="16"
                                            viewBox="0 0 16 16"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M6 4L10 8L6 12"
                                                stroke="white"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </a>
                                </div>
                            </section>
                        </div>

                        <aside class="sidebar">
                            <div
                                v-if="
                                    event.hasPromotion ||
                                    event.promotions.length
                                "
                                class="sidebar-block promotions-block"
                            >
                                <h2 class="block-title">
                                    <svg
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <polygon
                                            points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
                                        />
                                    </svg>
                                    Promociones disponibles
                                </h2>
                                <div
                                    v-if="event.promotions.length"
                                    class="promo-list"
                                >
                                    <div
                                        v-for="(promo, i) in event.promotions"
                                        :key="i"
                                        class="promo-badge"
                                    >
                                        {{ promo }}
                                    </div>
                                </div>
                                <p v-else class="promo-empty">
                                    No hay promociones disponibles
                                </p>
                            </div>

                            <div class="sidebar-block zones-block">
                                <h2 class="block-title">
                                    <svg
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"
                                        />
                                    </svg>
                                    Precios de zonas
                                </h2>
                                <div
                                    class="zone-list"
                                    :class="{
                                        'zone-list--cols-2':
                                            sortedZones.length > 10,
                                    }"
                                >
                                    <div
                                        v-for="zone in sortedZones"
                                        :key="zone.id"
                                        class="zone-row"
                                    >
                                        <span class="zone-name">{{
                                            zone.name
                                        }}</span>
                                        <span class="zone-price">
                                            <span
                                                :class="{
                                                    'old-price':
                                                        hasDiscount(zone),
                                                }"
                                                >${{
                                                    formatPrice(
                                                        zone.originalPrice,
                                                    )
                                                }}</span
                                            >
                                            <span
                                                v-if="hasDiscount(zone)"
                                                class="current-price"
                                                >${{
                                                    formatPrice(
                                                        zone.discountPrice,
                                                    )
                                                }}</span
                                            >
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="sidebar-block location-block">
                                <h2 class="block-title">
                                    <svg
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"
                                        />
                                        <circle cx="12" cy="10" r="3" />
                                    </svg>
                                    Ubicaci&oacute;n
                                </h2>
                                <EventMap
                                    :latitude="event.latitude"
                                    :longitude="event.longitude"
                                    :venue-name="event.venueName"
                                />
                                <p v-if="event.venueName" class="venue-name">
                                    {{ event.venueName }}
                                </p>
                            </div>
                        </aside>
                    </div>
                </div>
            </section>
        </main>

        <NearbyEventsStrip :events="nearbyEvents" />

        <SiteFooter />
        <MobileBottomNav v-if="!canBuy" @open-login="openLogin" />
        <div v-else class="mobile-buy-bar">
            <div class="mobile-buy-price">
                <template v-if="fromPrice !== null">
                    <span class="mobile-buy-from">Desde</span>
                    <strong>${{ formatPrice(fromPrice) }}</strong>
                </template>
                <strong v-else class="mobile-buy-name">{{ event.name }}</strong>
            </div>
            <button type="button" class="mobile-buy-btn" @click="buy()">
                COMPRAR BOLETO
            </button>
        </div>

        <PhoneLoginModal
            :show="showPhoneLoginModal"
            @close="closePhoneLogin"
            @switch-to-login="switchPhoneToLogin"
            @switch-to-register="switchPhoneToRegister"
            @code-sent="handleCodeSent"
        />
        <VerifyCodeModal
            :show="showVerifyCodeModal"
            :lada="verifyLada"
            :telefono="verifyTelefono"
            :canal="verifyCanal"
            @close="closeVerifyCode"
            @verified="handleVerified"
            @resend="handleResend"
            @switch-to-login="switchVerifyToLogin"
            @switch-to-register="switchVerifyToRegister"
        />
        <LoginModal
            :show="showLoginModal"
            @close="closeLogin"
            @switch-to-register="switchLoginToRegister"
        />
        <RegisterModal
            :show="showRegisterModal"
            @close="closeRegister"
            @switch-to-login="switchRegisterToLogin"
        />
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import SeoHead from "@/components/SeoHead.vue";
import SiteHeader from "@/components/SiteHeader.vue";
import SiteFooter from "@/components/SiteFooter.vue";
import MobileBottomNav from "@/components/MobileBottomNav.vue";
import PhoneLoginModal from "@/components/PhoneLoginModal.vue";
import VerifyCodeModal from "@/components/VerifyCodeModal.vue";
import LoginModal from "@/components/LoginModal.vue";
import RegisterModal from "@/components/RegisterModal.vue";
import EventMap from "@/components/EventMap.vue";
import NearbyEventsStrip from "@/components/NearbyEventsStrip.vue";
import KitIcon from "@/components/KitIcon.vue";

const props = defineProps({
    event: { type: Object, required: true },
    kit: { type: Object, default: null },
    // URL del PDF de la convocatoria (carreras) o null.
    convocatoria: { type: String, default: null },
    sponsors: { type: Array, default: () => [] },
    nearbyEvents: { type: Array, default: () => [] },
});

const formattedComments = computed(() => {
    const raw = props.event.comments || "";
    return String(raw)
        .replace(/\\r\\n/g, "\n")
        .replace(/\\r/g, "\n")
        .replace(/\\n/g, "\n");
});

const page = usePage();

const heroImage = computed(
    () => props.event.sliderImage || props.event.image,
);

const selectedFunctionId = computed(() => {
    const url = new URL(page.url, window.location.origin);
    return url.searchParams.get("function") || null;
});

const selectedFunction = computed(() => {
    const fn = props.event.functions.find(
        (f) => String(f.id) === selectedFunctionId.value,
    );
    return fn || props.event.functions[0] || null;
});

const hasMultipleFunctions = computed(() => {
    return props.event.functions.length > 1;
});

const canBuy = computed(
    () => Boolean(props.event.ventaWeb) && props.event.functions.length > 0,
);

const chosenFunction = computed(() => {
    const fromUrl = props.event.functions.find(
        (f) => String(f.id) === selectedFunctionId.value,
    );
    if (fromUrl) return fromUrl;
    return hasMultipleFunctions.value ? null : props.event.functions[0] || null;
});

const fromPrice = computed(() => {
    const prices = (props.event.zones ?? [])
        .map((zone) =>
            Number(zone.discountPrice) > 0
                ? Number(zone.discountPrice)
                : Number(zone.originalPrice),
        )
        .filter((price) => Number.isFinite(price) && price > 0);
    return prices.length ? Math.min(...prices) : null;
});

const needsDate = ref(false);

function buy(fn = chosenFunction.value) {
    if (!fn) {
        needsDate.value = true;
        document
            .getElementById("fechas")
            ?.scrollIntoView({ behavior: "smooth", block: "start" });
        return;
    }

    router.get(route("checkout.map", { evento: props.event.id }), {
        function: fn.id,
    });
}

function onFunctionClick(e, fn) {
    if (!canBuy.value) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
    e.preventDefault();
    buy(fn);
}

const showPhoneLoginModal = ref(false);
const showVerifyCodeModal = ref(false);
const showLoginModal = ref(false);
const showRegisterModal = ref(false);

const verifyLada = ref("52");
const verifyTelefono = ref("");
const verifyCanal = ref("whatsapp");

function openLogin() {
    showRegisterModal.value = false;
    showLoginModal.value = false;
    showVerifyCodeModal.value = false;
    showPhoneLoginModal.value = true;
}
function openRegister() {
    showPhoneLoginModal.value = false;
    showLoginModal.value = false;
    showVerifyCodeModal.value = false;
    showRegisterModal.value = true;
}
function closePhoneLogin() {
    showPhoneLoginModal.value = false;
}
function closeVerifyCode() {
    showVerifyCodeModal.value = false;
}
function closeLogin() {
    showLoginModal.value = false;
}
function closeRegister() {
    showRegisterModal.value = false;
}
function switchPhoneToLogin() {
    showPhoneLoginModal.value = false;
    showLoginModal.value = true;
}
function switchPhoneToRegister() {
    showPhoneLoginModal.value = false;
    showRegisterModal.value = true;
}
function handleCodeSent(data) {
    verifyLada.value = data.lada;
    verifyTelefono.value = data.telefono;
    verifyCanal.value = data.canal;
    showPhoneLoginModal.value = false;
    showVerifyCodeModal.value = true;
}
function handleVerified() {
    showVerifyCodeModal.value = false;
}
function handleResend() {
    showVerifyCodeModal.value = false;
    showPhoneLoginModal.value = true;
}
function switchVerifyToLogin() {
    showVerifyCodeModal.value = false;
    showLoginModal.value = true;
}
function switchVerifyToRegister() {
    showVerifyCodeModal.value = false;
    showRegisterModal.value = true;
}
function switchLoginToRegister() {
    showLoginModal.value = false;
    showRegisterModal.value = true;
}
function switchRegisterToLogin() {
    showRegisterModal.value = false;
    showLoginModal.value = true;
}

const monthNames = [
    "Ene",
    "Feb",
    "Mar",
    "Abr",
    "May",
    "Jun",
    "Jul",
    "Ago",
    "Sep",
    "Oct",
    "Nov",
    "Dic",
];

function formatDate(iso) {
    const d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    return `${d.getDate()} ${monthNames[d.getMonth()]} ${d.getFullYear()}`;
}

function formatTime(iso) {
    const d = new Date(iso);
    if (isNaN(d.getTime())) return "";
    return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")} hrs`;
}

function hasTime(iso) {
    const d = new Date(iso);
    return !isNaN(d.getTime()) && (d.getHours() !== 0 || d.getMinutes() !== 0);
}

function getDay(iso) {
    const d = new Date(iso);
    return isNaN(d.getTime()) ? "" : d.getDate();
}

function getMonth(iso) {
    const d = new Date(iso);
    return isNaN(d.getTime()) ? "" : monthNames[d.getMonth()].toUpperCase();
}

function formatPrice(val) {
    return Number(val).toLocaleString("es-MX");
}

function hasDiscount(zone) {
    return zone.originalPrice !== zone.discountPrice;
}

const sortedZones = computed(() => {
    return [...props.event.zones].sort(
        (a, b) => b.originalPrice - a.originalPrice,
    );
});

const formattedMainDate = computed(() => {
    if (!selectedFunction.value) return "";
    return formatDate(selectedFunction.value.date);
});

const sponsors = computed(() => props.sponsors);

const sponsorScrollRef = ref(null);

let sponsorIsDown = false;
let sponsorStartX = 0;
let sponsorStartScroll = 0;
let sponsorMoved = false;

function onSponsorMouseDown(e) {
    if (!sponsorScrollRef.value) return;
    sponsorIsDown = true;
    sponsorMoved = false;
    sponsorStartX = e.pageX;
    sponsorStartScroll = sponsorScrollRef.value.scrollLeft;
    sponsorScrollRef.value.classList.add("dragging");
}

function onSponsorMouseMove(e) {
    if (!sponsorIsDown || !sponsorScrollRef.value) return;
    const dx = e.pageX - sponsorStartX;
    if (Math.abs(dx) > 3) sponsorMoved = true;
    sponsorScrollRef.value.scrollLeft = sponsorStartScroll - dx;
}

function onSponsorMouseUp() {
    if (!sponsorIsDown || !sponsorScrollRef.value) return;
    sponsorIsDown = false;
    sponsorScrollRef.value.classList.remove("dragging");
}

function onSponsorClick(e) {
    if (sponsorMoved) {
        e.preventDefault();
        e.stopPropagation();
    }
}

function getSponsorStep() {
    if (!sponsorScrollRef.value) return 440;
    const item = sponsorScrollRef.value.querySelector(".sponsor-track li");
    const w = item ? item.getBoundingClientRect().width : 220;
    return (w + 6) * 2;
}

function scrollSponsorsPrev() {
    if (!sponsorScrollRef.value) return;
    sponsorScrollRef.value.scrollBy({
        left: -getSponsorStep(),
        behavior: "smooth",
    });
}

function scrollSponsorsNext() {
    if (!sponsorScrollRef.value) return;
    sponsorScrollRef.value.scrollBy({
        left: getSponsorStep(),
        behavior: "smooth",
    });
}
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

.hero-section {
    padding-block: var(--space-8) var(--space-4);
}

@media (min-width: 769px) {
    .hero-section .container {
        width: 98.5%;
        max-width: 98.5%;
        margin-inline: auto;
        padding-inline: 0;
    }
}

.hero-frame {
    border-radius: var(--radius-sm);
    overflow: hidden;
    aspect-ratio: 5 / 2;
}

.hero-picture {
    display: block;
    width: 100%;
    height: 100%;
}

.hero-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.info-bar {
    padding-block: var(--space-4) var(--space-8);
    border-bottom: 1px solid var(--color-border);
}

.info-bar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-6);
    flex-wrap: wrap;
}

.info-text {
    flex: 1;
    min-width: 280px;
}

.event-title {
    font-size: var(--text-3xl);
    font-weight: 900;
    letter-spacing: -0.02em;
    color: var(--color-text-primary);
    margin-bottom: var(--space-2);
}

.event-meta {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--text-base);
    color: var(--color-text-secondary);
}

.event-meta svg {
    flex-shrink: 0;
    color: var(--color-brand);
}

.btn-buy {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-8);
    font-size: var(--text-base);
    font-weight: 700;
    color: #fff;
    background: linear-gradient(
        135deg,
        var(--color-brand),
        var(--color-accent)
    );
    border: none;
    border-radius: var(--radius-xs);
    box-shadow: var(--shadow-brand);
    cursor: pointer;
    transition:
        opacity var(--transition-fast),
        transform var(--transition-fast);
    white-space: nowrap;
}

.btn-buy:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.event-info-label {
    display: flex;
    flex-direction: column;
    gap: var(--space-1);
    margin-top: var(--space-3);
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
}

.body-section {
    padding-block: var(--space-10) var(--space-16);
}

.body-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(340px, 380px);
    gap: var(--space-10);
    align-items: start;
}

@media (max-width: 768px) {
    .body-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

.main-column {
    display: flex;
    flex-direction: column;
    gap: var(--space-6);
    min-width: 0;
    max-width: 100%;
}

.detail-block {
    margin-bottom: var(--space-10);
}

.block-title {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    font-size: var(--text-xl);
    font-weight: 800;
    color: var(--color-text-primary);
    margin-bottom: var(--space-5);
}

.block-title svg {
    color: var(--color-brand);
    flex-shrink: 0;
}

.event-comments {
    white-space: pre-line;
}

.kit-items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 180px), 1fr));
    gap: var(--space-3);
    list-style: none;
}

.kit-item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    min-width: 0;
    padding: var(--space-3) var(--space-4);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface-1);
    color: var(--color-text-primary);
    font-weight: 600;
}

.kit-item svg {
    flex-shrink: 0;
    color: var(--color-brand);
}

.kit-item span {
    min-width: 0;
    overflow-wrap: anywhere;
}

.convocatoria-text {
    margin-bottom: var(--space-3);
    color: var(--color-text-secondary);
}

.convocatoria-link {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-4);
    border: 1px solid var(--color-brand);
    border-radius: var(--radius-md);
    color: var(--color-brand);
    font-weight: 700;
    text-decoration: none;
    transition: background 0.2s ease, color 0.2s ease;
}

.convocatoria-link:hover {
    background: var(--color-brand);
    color: #fff;
}

.convocatoria-link:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.kit-delivery {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
    gap: var(--space-3);
    margin-top: var(--space-4);
}

.kit-delivery div {
    min-width: 0;
    padding: var(--space-3) var(--space-4);
    border-left: 3px solid var(--color-brand);
    border-radius: var(--radius-sm);
    background: var(--color-surface-1);
}

.kit-delivery dt {
    color: var(--color-text-muted);
    font-size: var(--text-xs);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.kit-delivery dd {
    margin: 2px 0 0;
    color: var(--color-text-primary);
    font-weight: 600;
    overflow-wrap: anywhere;
}

.function-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

.function-item {
    background: var(--color-surface-1);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xs);
}

.function-item--active {
    border-color: var(--color-brand);
    background: var(--color-brand-dim);
}

.function-item--clickable {
    cursor: pointer;
    transition:
        border-color var(--transition-fast),
        background var(--transition-fast);
}

.function-item--clickable:hover {
    border-color: var(--color-brand);
    background: var(--color-brand-dim);
}

.function-link {
    display: flex;
    align-items: center;
    gap: var(--space-4);
    width: 100%;
    padding: var(--space-4) var(--space-5);
    color: inherit;
    text-decoration: none;
    border-radius: inherit;
}

.function-item:not(.function-item--clickable) .function-link {
    cursor: default;
}

.function-link:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.function-cta {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-left: auto;
    flex-shrink: 0;
    padding: 6px 10px;
    border-radius: 999px;
    background: var(--color-brand-dim);
    color: var(--color-brand);
    font-size: var(--text-xs);
    font-weight: 800;
    white-space: nowrap;
    transition:
        background var(--transition-fast),
        color var(--transition-fast);
}

.function-item--clickable:hover .function-cta {
    background: var(--color-brand);
    color: #06291d;
}

.mobile-buy-bar {
    display: none;
}

@media (max-width: 767px) {
    .page-layout:has(.mobile-buy-bar) {
        padding-bottom: calc(var(--bottom-nav-height) + var(--space-6) + env(safe-area-inset-bottom, 0px));
    }

    .mobile-buy-bar {
        position: fixed;
        bottom: calc(var(--space-3) + env(safe-area-inset-bottom, 0px));
        left: var(--space-3);
        right: var(--space-3);
        z-index: 100;
        display: flex;
        align-items: center;
        gap: var(--space-3);
        min-height: var(--bottom-nav-height);
        padding: var(--space-2) var(--space-2) var(--space-2) var(--space-4);
        background: rgba(18, 18, 26, 0.9);
        backdrop-filter: blur(18px) saturate(140%);
        -webkit-backdrop-filter: blur(18px) saturate(140%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: var(--radius-xl);
        box-shadow:
            0 12px 32px -8px rgba(0, 0, 0, 0.7),
            0 2px 8px rgba(0, 0, 0, 0.35);
    }

    .mobile-buy-price {
        display: flex;
        flex-direction: column;
        min-width: 0;
        line-height: 1.15;
    }

    .mobile-buy-from {
        color: var(--color-text-secondary);
        font-size: var(--text-xs);
    }

    .mobile-buy-price strong {
        font-size: var(--text-lg);
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }

    .mobile-buy-name {
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        font-size: var(--text-sm) !important;
    }

    .mobile-buy-btn {
        flex: 1;
        min-height: 48px;
        margin-left: auto;
        padding: 0 var(--space-4);
        border: none;
        border-radius: var(--radius-lg);
        background: linear-gradient(
            135deg,
            var(--color-brand),
            var(--color-accent)
        );
        color: #fff;
        font-weight: 800;
        font-size: var(--text-sm);
        letter-spacing: 0.02em;
        cursor: pointer;
        box-shadow: var(--shadow-brand);
        transition: transform var(--transition-fast);
    }

    .mobile-buy-btn:active {
        transform: scale(0.98);
    }

    .mobile-buy-btn:focus-visible {
        outline: 2px solid var(--color-brand);
        outline-offset: 2px;
    }

    .function-cta {
        padding: 6px;
        font-size: 0;
        gap: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .function-cta,
    .mobile-buy-btn {
        transition: none;
    }
}

.btn-buy:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.date-hint {
    margin-bottom: var(--space-3);
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--color-brand);
}

.function-date-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 1;
    padding: var(--space-2) var(--space-3);
    background: rgba(10, 10, 15, 0.85);
    border-radius: var(--radius-lg);
    flex-shrink: 0;
}

.function-date-badge .d {
    font-size: 20px;
    font-weight: 800;
    color: #fff;
}

.function-date-badge .m {
    font-size: 10px;
    font-weight: 700;
    color: var(--color-brand);
    letter-spacing: 0.08em;
    margin-top: 2px;
}

.fn-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: var(--space-1);
}

.fn-event-name {
    font-size: var(--text-base);
    font-weight: 700;
    color: var(--color-text-primary);
}

.fn-details {
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
}

.detail-grid {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: var(--space-1);
    padding: var(--space-4) var(--space-5);
    background: var(--color-surface-1);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
}

.detail-label {
    font-size: var(--text-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-text-muted);
}

.detail-value {
    font-size: var(--text-base);
    font-weight: 600;
    color: var(--color-text-primary);
}

.sidebar {
    display: flex;
    flex-direction: column;
    gap: var(--space-8);
    min-width: 0;
}

.sidebar-block {
    background: var(--color-surface-1);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
}

.promo-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.promo-badge {
    display: inline-flex;
    align-items: center;
    padding: var(--space-2) var(--space-4);
    background: var(--color-brand-dim);
    border: 1px solid rgba(78, 203, 160, 0.4);
    border-radius: var(--radius-sm);
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--color-brand);
}

.promo-empty {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
}

.zone-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-2);
}

.zone-list--cols-2 {
    grid-template-columns: 1fr 1fr;
}

.venue-name {
    margin-top: var(--space-3);
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--color-text-secondary);
}

.zone-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-3) var(--space-4);
    border-radius: var(--radius-sm);
    background: var(--color-surface-2);
    gap: var(--space-3);
}

.zone-name {
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--color-text-primary);
    flex: 1;
}

.zone-price {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    flex-shrink: 0;
}

.old-price {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    text-decoration: line-through;
}

.current-price {
    font-size: var(--text-base);
    font-weight: 700;
    color: var(--color-text-primary);
}

.sponsors-title {
    margin-bottom: var(--space-5);
}

.sponsor-wrapper {
    position: relative;
    padding: 0;
}

.sponsor-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x proximity;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
    cursor: grab;
}

.sponsor-scroll::-webkit-scrollbar {
    display: none;
}

.sponsor-scroll.dragging {
    cursor: grabbing;
    scroll-behavior: auto;
}

.sponsor-track {
    display: flex;
    gap: 6px;
    margin: 0;
    padding: 4px 0;
    list-style: none;
}

.sponsor-track li {
    flex: 0 0 150px;
    scroll-snap-align: start;
    border-radius: 12px;
    position: relative;
}

.sponsor-track li .sponsor-card {
    position: relative;
    display: block;
    transition:
        transform 0.35s ease,
        box-shadow 0.35s ease;
    transform-style: preserve-3d;
    outline: none;
    -webkit-tap-highlight-color: transparent;
}

.sponsor-track li .sponsor-card:focus,
.sponsor-track li .sponsor-card:active {
    outline: none;
}

.sponsor-track li .sponsor-card::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: 12px;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.65), transparent 60%);
    pointer-events: none;
}

.sponsor-track li .sponsor-card img {
    border-radius: 12px;
    display: block;
    width: 100%;
    aspect-ratio: 11 / 7;
    object-fit: contain;
    padding: 12px 12px 32px;
    background: #fff;
    pointer-events: none;
}

.sponsor-track li .sponsor-card:hover {
    transform: perspective(700px) rotateY(7deg) scale(1.06) translateZ(10px);
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.55);
}

.sponsor-track li .sponsor-card h3 {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 10px;
    z-index: 2;
    color: #fff;
    font-weight: 800;
    margin: 0;
    text-align: center;
    font-size: 1rem;
}

.sponsor-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 5;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.45);
    border-radius: 50%;
    transition: background 0.2s ease;
}

.sponsor-arrow:hover {
    background: rgba(0, 0, 0, 0.8);
}

.sponsor-arrow-prev {
    left: 0;
}

.sponsor-arrow-next {
    right: 0;
}

.promo-main-list {
    display: grid;
    gap: var(--space-3);
}

.promo-main-card {
    padding: var(--space-4) var(--space-5);
    background: var(--color-surface-1);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: var(--text-base);
    font-weight: 600;
    color: var(--color-text-primary);
}

@media (max-width: 852px) {
    .body-grid {
        gap: var(--space-6);
    }
    .sidebar-block {
        padding: var(--space-4);
    }
}

@media (max-width: 768px) {
    .btn-buy {
        width: 100%;
        justify-content: center;
    }

    .sponsor-track li {
        flex: 0 0 150px;
    }

    .sponsor-arrow {
        display: none;
    }

    .sponsor-wrapper {
        padding: 0;
    }
}

@media (max-width: 393px) {
    .event-title {
        font-size: var(--text-2xl);
    }
    .info-bar-inner {
        flex-direction: column;
        align-items: stretch;
    }
    .sponsor-track li {
        flex: 0 0 130px;
    }
}
.flash-error {
    margin-top: var(--space-4);
    padding: 12px 16px;
    border: 1px solid rgba(239, 68, 68, 0.4);
    border-radius: var(--radius-md);
    background: rgba(239, 68, 68, 0.12);
    color: #fecaca;
    font-size: var(--text-sm);
}
</style>
