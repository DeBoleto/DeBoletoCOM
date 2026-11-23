<template>
    <dialog
        ref="dialog"
        class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-5xl overflow-y-auto rounded-2xl border border-bo-line bg-bo-paper p-0 text-bo-ink shadow-2xl backdrop:bg-bo-ink/60 backdrop:backdrop-blur-[2px]"
        aria-labelledby="payment-dialog-title"
        @close="emit('close')"
    >
        <div class="flex items-center justify-between gap-3 border-b border-bo-line bg-bo-card px-6 py-4">
            <div>
                <h2 id="payment-dialog-title" class="text-lg font-semibold">Cobro</h2>
                <p class="text-sm text-bo-slate">Confirma el total con el cliente y registra el pago.</p>
            </div>
            <button type="button" class="rounded-md p-1.5 text-bo-slate hover:bg-bo-paper hover:text-bo-ink" aria-label="Cerrar" @click="close">
                <BoIcon name="close" :size="20" />
            </button>
        </div>

        <div v-if="payment" class="grid gap-6 p-4 sm:p-6 lg:grid-cols-[1fr_360px]">
            <article class="bo-stub border border-bo-line" :style="{ '--stub-cut': 'calc(100% - 196px)' }" aria-label="Detalle de la venta">
                <header class="flex gap-4 p-6">
                    <EventThumb :src="event.image" :alt="event.name" size="sm" />
                    <div class="min-w-0">
                        <h3 class="text-xl leading-tight font-semibold">{{ event.name }}</h3>
                        <p class="mt-1 text-sm text-bo-slate">{{ event.venue }}, {{ event.city }}</p>
                        <p class="tabular text-sm text-bo-slate">{{ payment.function.date }} a las {{ payment.function.time }} h</p>
                    </div>
                </header>

                <div class="border-t border-bo-line px-6 py-4">
                    <div class="mb-2 flex items-center justify-between text-sm text-bo-slate">
                        <span>{{ totals.paid }} {{ totals.paid === 1 ? "boleto pagado" : "boletos pagados" }}</span>
                        <span v-if="totals.courtesies">{{ totals.courtesies }} {{ totals.courtesies === 1 ? "cortesía" : "cortesías" }} {{ payment.promotionText }}</span>
                    </div>
                    <ul class="max-h-72 divide-y divide-bo-line overflow-y-auto text-sm">
                        <li v-for="(line, index) in payment.lines" :key="index" class="flex items-center justify-between gap-4 py-2">
                            <span class="min-w-0">
                                <span class="font-medium">{{ line.label }}</span>
                                <span class="text-bo-slate"> — {{ line.locality }}</span>
                                <span v-if="line.type && line.type !== 'Regular'" class="ml-2 rounded bg-bo-paper px-1.5 py-0.5 text-xs text-bo-slate">{{ line.type }}</span>
                            </span>
                            <span v-if="line.courtesy" class="flex shrink-0 items-center gap-2">
                                <span class="rounded-full bg-fuchsia-100 px-2 py-0.5 text-xs font-bold text-fuchsia-700">{{ payment.promotionText || "Promoción" }}</span>
                                <span class="tabular font-semibold text-fuchsia-700">{{ money(0) }}</span>
                            </span>
                            <span v-else class="tabular shrink-0">{{ money(line.price) }}</span>
                        </li>
                    </ul>
                </div>

                <div class="bo-perforation" />

                <dl class="tabular space-y-1.5 px-6 py-5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-bo-slate">Subtotal</dt>
                        <dd>{{ money(totals.subtotal) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-bo-slate">Cargo por servicio</dt>
                        <dd>{{ money(totals.service) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-bo-slate">Cargo bancario</dt>
                        <dd>{{ money(totals.payment) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between pt-2">
                        <dt class="font-semibold">Total a cobrar</dt>
                        <dd class="text-3xl font-semibold">{{ money(totals.total) }}</dd>
                    </div>
                </dl>
            </article>

            <form class="space-y-5" @submit.prevent="confirm">
                <section class="rounded-xl border border-bo-line bg-bo-card p-5">
                    <p class="text-sm text-bo-slate">Forma de pago</p>
                    <p class="text-lg font-semibold">{{ payment.paymentType.label }}</p>
                    <p v-if="payment.discount" class="mt-1 text-sm text-bo-slate">Descuento aplicado: {{ payment.discount }} %</p>

                    <div v-if="payment.paymentType.value === CASH" class="mt-5 border-t border-bo-line pt-4">
                        <label for="received" class="mb-1.5 block text-sm font-medium">Efectivo recibido</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-bo-muted">$</span>
                            <input
                                id="received"
                                ref="receivedInput"
                                v-model="received"
                                type="number"
                                inputmode="decimal"
                                min="0"
                                step="0.01"
                                class="tabular block w-full rounded-lg border-bo-line py-2.5 pl-7 text-lg focus:border-bo-green focus:ring-bo-green"
                            />
                        </div>
                        <div class="tabular mt-3 flex items-baseline justify-between">
                            <span class="text-sm text-bo-slate">Cambio</span>
                            <span class="text-2xl font-semibold" :class="change < 0 ? 'text-bo-sold' : 'text-bo-green-dark'">
                                {{ received === "" ? "—" : money(change) }}
                            </span>
                        </div>
                        <p v-if="received !== '' && change < 0" class="mt-1 text-sm text-bo-sold">Faltan {{ money(-change) }}.</p>
                    </div>
                </section>

                <label class="flex items-start gap-3 rounded-xl border border-bo-line bg-bo-card p-4 text-sm">
                    <input v-model="form.acepto" type="checkbox" class="mt-0.5 size-5 rounded border-bo-line text-bo-green focus:ring-bo-green" />
                    <span>Revisé los boletos y el total con el cliente.</span>
                </label>
                <p v-if="form.errors.acepto" class="text-sm text-bo-sold">{{ form.errors.acepto }}</p>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-bo-green px-4 py-3.5 text-lg font-semibold text-white hover:bg-bo-green-dark disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!form.acepto || form.processing"
                >
                    {{ form.processing ? "Registrando venta…" : `Cobrar ${money(totals.total)}` }}
                </button>

                <div class="flex justify-between">
                    <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-bo-slate hover:text-bo-ink" @click="close">
                        <BoIcon name="chevronLeft" :size="16" />
                        Cambiar boletos
                    </button>
                    <Link
                        :href="route('boxoffice.tickets.checkout.cancel', event.id)"
                        method="post"
                        as="button"
                        class="text-sm font-medium text-bo-slate hover:text-bo-sold"
                    >
                        Cancelar venta
                    </Link>
                </div>
            </form>
        </div>
    </dialog>
</template>

<script setup>
import { computed, nextTick, ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import BoIcon from "./BoIcon.vue";
import EventThumb from "./EventThumb.vue";
import { money } from "./format.js";

const CASH = 1;

const props = defineProps({
    event: { type: Object, required: true },
    payment: { type: Object, default: null },
});

const emit = defineEmits(["close"]);

const dialog = ref(null);
const receivedInput = ref(null);
const received = ref("");
const form = useForm({ acepto: false, token: "" });

const totals = computed(() => props.payment?.totals ?? {});
const change = computed(() => Math.round((Number(received.value || 0) - (totals.value.total ?? 0)) * 100) / 100);

function open() {
    received.value = "";
    form.reset();
    form.clearErrors();
    form.token = props.payment?.confirmToken ?? "";

    if (!dialog.value?.open) dialog.value?.showModal();
    nextTick(() => receivedInput.value?.focus());
}

function close() {
    dialog.value?.close();
}

function confirm() {
    form.post(route("boxoffice.tickets.checkout.confirm", props.event.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            if (!page.props.payment) close();
        },
    });
}

defineExpose({ open, close });
</script>
