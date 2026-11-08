<template>
    <BackOfficeLayout :title="`Venta registrada — ${event.name}`">
        <template #header>
            <div class="flex min-w-0 items-center gap-4">
                <h1 class="truncate text-lg font-semibold">{{ event.name }}</h1>
                <StepIndicator :current="3" class="hidden md:flex" />
            </div>
        </template>

        <div class="mx-auto max-w-3xl">
            <div class="bo-stub border border-bo-line" :style="{ '--stub-cut': 'calc(100% - 88px)' }">
                <div class="p-8 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-bo-green-soft text-bo-green-dark">
                        <BoIcon name="check" :size="26" />
                    </span>
                    <h2 class="mt-4 text-2xl font-semibold">Venta registrada</h2>
                    <p class="mt-1 text-bo-slate">
                        {{ tickets }} {{ tickets === 1 ? "boleto" : "boletos" }} por <span class="tabular font-semibold text-bo-ink">{{ money(total) }}</span>
                    </p>
                    <p v-if="isBond" class="mx-auto mt-3 max-w-md text-sm text-bo-slate">
                        Este evento vende bonos: envía el enlace por WhatsApp para que el cliente registre sus datos.
                    </p>
                </div>
                <div class="bo-perforation" />
                <div class="flex items-center justify-between px-8 py-5">
                    <span class="text-sm text-bo-slate">Folio de compra</span>
                    <span class="tabular text-2xl font-semibold tracking-wide">{{ purchaseId }}</span>
                </div>
            </div>

            <section class="mt-6 grid gap-3 sm:grid-cols-3" aria-label="Entregar boletos">
                <a
                    v-if="!isBond"
                    :href="route('boxoffice.tickets.purchase.print', purchaseId)"
                    target="_blank"
                    rel="noopener"
                    class="flex flex-col items-start gap-2 rounded-xl border border-bo-line bg-bo-card p-4 hover:border-bo-green"
                    @click="markDelivered('print')"
                >
                    <BoIcon name="printer" />
                    <span class="font-semibold">Imprimir boletos</span>
                    <span class="text-sm text-bo-slate">Abre la impresión en otra pestaña.</span>
                </a>
                <a
                    v-if="!isBond"
                    :href="route('boxoffice.tickets.purchase.digital', purchaseId)"
                    class="flex flex-col items-start gap-2 rounded-xl border border-bo-line bg-bo-card p-4 hover:border-bo-green"
                    @click="markDelivered('digital')"
                >
                    <BoIcon name="download" />
                    <span class="font-semibold">Descargar PDF</span>
                    <span class="text-sm text-bo-slate">Boletos digitales para el cliente.</span>
                </a>
                <form class="flex flex-col gap-2 rounded-xl border border-bo-line bg-bo-card p-4" :class="isBond ? 'sm:col-span-3' : ''" @submit.prevent="sendWhatsApp">
                    <label for="phone" class="flex items-center gap-2 font-semibold">
                        <BoIcon name="phone" />
                        Enviar por WhatsApp
                    </label>
                    <input
                        id="phone"
                        v-model="phone"
                        type="tel"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="14"
                        placeholder="10 dígitos"
                        class="tabular rounded-lg border-bo-line py-2 text-sm focus:border-bo-green focus:ring-bo-green"
                        :aria-invalid="!!whatsapp.error"
                    />
                    <p class="text-xs text-bo-slate">Confirma el número con el cliente antes de enviar.</p>
                    <p v-if="whatsapp.error" class="text-sm text-bo-sold" role="alert">{{ whatsapp.error }}</p>
                    <p v-if="whatsapp.sent" class="text-sm text-bo-green-dark" role="status">Enviado a {{ whatsapp.sent }}.</p>
                    <button
                        type="submit"
                        class="rounded-lg bg-bo-ink px-3 py-2 text-sm font-semibold text-white hover:bg-bo-ink-2 disabled:opacity-50"
                        :disabled="whatsapp.sending || phoneDigits.length !== 10"
                    >
                        {{ whatsapp.sending ? "Enviando…" : "Enviar" }}
                    </button>
                </form>
            </section>

            <div class="mt-8 flex flex-col-reverse items-stretch justify-between gap-3 border-t border-bo-line pt-6 sm:flex-row sm:items-center">
                <p class="text-sm" :class="delivered.size ? 'text-bo-slate' : 'text-bo-pending'">
                    {{ delivered.size ? "Boletos entregados." : "Entrega los boletos antes de finalizar." }}
                </p>
                <div class="flex gap-3">
                    <button
                        type="button"
                        class="rounded-lg border border-bo-line bg-bo-card px-4 py-2.5 font-semibold hover:border-bo-slate disabled:opacity-50"
                        :disabled="!delivered.size || finishing"
                        @click="finish(true)"
                    >
                        Nueva venta
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-bo-green px-5 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50"
                        :disabled="!delivered.size || finishing"
                        @click="finish(false)"
                    >
                        Finalizar
                    </button>
                </div>
            </div>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { computed, reactive, ref } from "vue";
import { router } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import StepIndicator from "@/components/BoxOffice/StepIndicator.vue";
import { money } from "@/components/BoxOffice/format.js";

const props = defineProps({
    event: { type: Object, required: true },
    purchaseId: { type: Number, required: true },
    isBond: { type: Boolean, default: false },
    total: { type: Number, required: true },
    tickets: { type: Number, required: true },
});

const delivered = reactive(new Set());
const phone = ref("");
const finishing = ref(false);
const whatsapp = reactive({ sending: false, error: "", sent: "" });

const phoneDigits = computed(() => phone.value.replace(/\D/g, ""));

function markDelivered(channel) {
    delivered.add(channel);
}

function csrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : "";
}

async function sendWhatsApp() {
    whatsapp.sending = true;
    whatsapp.error = "";
    whatsapp.sent = "";

    try {
        const response = await fetch(route("boxoffice.tickets.purchase.whatsapp", props.purchaseId), {
            method: "POST",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-XSRF-TOKEN": csrfToken(),
            },
            body: JSON.stringify({ telefono: phoneDigits.value }),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            whatsapp.error = data.errors?.telefono?.[0] ?? data.message ?? "No se pudo enviar el mensaje.";
            return;
        }

        whatsapp.sent = phoneDigits.value;
        markDelivered("whatsapp");
    } catch {
        whatsapp.error = "Sin conexión con el servidor. Intenta de nuevo.";
    } finally {
        whatsapp.sending = false;
    }
}

function finish(another) {
    finishing.value = true;
    router.post(route("boxoffice.tickets.checkout.finish", props.event.id), { otra_venta: another }, { onFinish: () => (finishing.value = false) });
}
</script>
