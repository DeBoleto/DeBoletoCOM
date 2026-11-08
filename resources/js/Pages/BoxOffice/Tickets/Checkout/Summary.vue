<template>
    <BoxOfficeLayout :title="`Cobro — ${event.name}`">
        <template #header>
            <div class="flex min-w-0 items-center gap-4">
                <h1 class="truncate text-lg font-semibold">{{ event.name }}</h1>
                <StepIndicator :current="2" class="hidden md:flex" />
            </div>
        </template>

        <div class="mx-auto grid max-w-5xl gap-8 lg:grid-cols-[1fr_360px]">
            <article class="bo-stub border border-bo-line" :style="{ '--stub-cut': 'calc(100% - 196px)' }" aria-label="Detalle de la venta">
                <header class="flex gap-4 p-6">
                    <EventThumb :src="event.image" :alt="event.name" size="sm" />
                    <div class="min-w-0">
                        <h2 class="text-xl leading-tight font-semibold">{{ event.name }}</h2>
                        <p class="mt-1 text-sm text-bo-slate">{{ event.venue }}, {{ event.city }}</p>
                        <p class="tabular text-sm text-bo-slate">{{ functionInfo.date }} a las {{ functionInfo.time }} h</p>
                    </div>
                </header>

                <div class="border-t border-bo-line px-6 py-4">
                    <div class="mb-2 flex items-center justify-between text-sm text-bo-slate">
                        <span>{{ totals.paid }} {{ totals.paid === 1 ? "boleto pagado" : "boletos pagados" }}</span>
                        <span v-if="totals.courtesies">{{ totals.courtesies }} {{ totals.courtesies === 1 ? "cortesía" : "cortesías" }} {{ promotionText }}</span>
                    </div>
                    <ul class="max-h-80 divide-y divide-bo-line overflow-y-auto text-sm">
                        <li v-for="(line, index) in lines" :key="index" class="flex items-center justify-between gap-4 py-2">
                            <span class="min-w-0">
                                <span class="font-medium">{{ line.label }}</span>
                                <span class="text-bo-slate"> — {{ line.locality }}</span>
                                <span v-if="line.type && line.type !== 'Regular'" class="ml-2 rounded bg-bo-paper px-1.5 py-0.5 text-xs text-bo-slate">{{ line.type }}</span>
                            </span>
                            <span v-if="line.courtesy" class="shrink-0 rounded bg-bo-green-soft px-2 py-0.5 text-xs font-semibold text-bo-green-dark">Cortesía</span>
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
                    <p class="text-lg font-semibold">{{ paymentType.label }}</p>
                    <p v-if="discount" class="mt-1 text-sm text-bo-slate">Descuento aplicado: {{ discount }} %</p>

                    <div v-if="paymentType.value === CASH" class="mt-5 border-t border-bo-line pt-4">
                        <label for="received" class="mb-1.5 block text-sm font-medium">Efectivo recibido</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-bo-muted">$</span>
                            <input
                                id="received"
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
                    <Link :href="route('boxoffice.tickets.checkout.selection', event.id)" class="inline-flex items-center gap-1 text-sm font-medium text-bo-slate hover:text-bo-ink">
                        <BoIcon name="chevronLeft" :size="16" />
                        Cambiar boletos
                    </Link>
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
    </BoxOfficeLayout>
</template>

<script setup>
import { computed, ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import BoxOfficeLayout from "@/Layouts/BoxOfficeLayout.vue";
import BoIcon from "@/Components/BoxOffice/BoIcon.vue";
import EventThumb from "@/Components/BoxOffice/EventThumb.vue";
import StepIndicator from "@/Components/BoxOffice/StepIndicator.vue";
import { money } from "@/Components/BoxOffice/format.js";

const CASH = 1;

const props = defineProps({
    event: { type: Object, required: true },
    function: { type: Object, required: true },
    paymentType: { type: Object, required: true },
    promotionText: { type: String, default: "" },
    discount: { type: Number, default: null },
    lines: { type: Array, required: true },
    totals: { type: Object, required: true },
    confirmToken: { type: String, required: true },
});

const functionInfo = computed(() => props.function);
const received = ref("");
const change = computed(() => Math.round((Number(received.value || 0) - props.totals.total) * 100) / 100);

const form = useForm({ acepto: false, token: props.confirmToken });

function confirm() {
    form.post(route("boxoffice.tickets.checkout.confirm", props.event.id));
}
</script>
