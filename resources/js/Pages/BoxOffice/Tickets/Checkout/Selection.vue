<template>
    <BackOfficeLayout :title="`Vender — ${event.name}`">
        <template #header>
            <div class="flex min-w-0 items-center gap-4">
                <h1 class="truncate text-lg font-semibold">{{ event.name }}</h1>
                <StepIndicator :current="1" class="hidden md:flex" />
            </div>
        </template>

        <div
            v-if="errorList.length"
            role="alert"
            class="mb-6 rounded-lg border border-bo-sold/30 bg-bo-sold-soft px-4 py-3 text-sm text-bo-sold"
        >
            <p class="font-semibold">Revisa la venta antes de continuar:</p>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="message in errorList" :key="message">{{ message }}</li>
            </ul>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_340px] xl:items-start">
            <div class="space-y-6">
                <section class="rounded-xl border border-bo-line bg-bo-card p-5" aria-labelledby="function-title">
                    <h2 id="function-title" class="font-semibold">Función</h2>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3" role="radiogroup" aria-labelledby="function-title">
                        <button
                            v-for="item in functions"
                            :key="item.id"
                            type="button"
                            role="radio"
                            :aria-checked="functionId === item.id"
                            class="rounded-lg border px-4 py-3 text-left"
                            :class="functionId === item.id ? 'border-bo-green bg-bo-green-soft ring-1 ring-bo-green' : 'border-bo-line hover:border-bo-slate'"
                            @click="selectFunction(item.id)"
                        >
                            <span class="block font-medium">{{ item.date }}</span>
                            <span class="tabular block text-sm text-bo-slate">{{ item.time }} h</span>
                        </button>
                    </div>
                </section>

                <section class="grid gap-6 rounded-xl border border-bo-line bg-bo-card p-5 md:grid-cols-2">
                    <div>
                        <h2 id="payment-title" class="font-semibold">Forma de pago</h2>
                        <div class="mt-3 inline-flex flex-wrap rounded-lg border border-bo-line p-1" role="radiogroup" aria-labelledby="payment-title">
                            <button
                                v-for="type in paymentTypes"
                                :key="type.value"
                                type="button"
                                role="radio"
                                :aria-checked="paymentType === type.value"
                                class="rounded-md px-4 py-2 text-sm font-medium"
                                :class="paymentType === type.value ? 'bg-bo-ink text-white' : 'text-bo-slate hover:text-bo-ink'"
                                @click="paymentType = type.value"
                            >
                                {{ type.label }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-end gap-4">
                        <label class="flex items-center gap-2.5 py-2 text-sm font-medium">
                            <input v-model="promotion" type="checkbox" class="size-5 rounded border-bo-line text-bo-green focus:ring-bo-green" />
                            Aplicar 2x1
                        </label>
                        <label class="text-sm font-medium">
                            <span class="mb-1.5 block">Descuento</span>
                            <select
                                v-model="discount"
                                class="rounded-lg border-bo-line py-2 pr-9 text-sm focus:border-bo-green focus:ring-bo-green"
                            >
                                <option :value="null">Sin descuento</option>
                                <option v-for="value in discounts" :key="value" :value="value">{{ value }} %</option>
                            </select>
                        </label>
                    </div>
                </section>

                <section v-if="event.mode === 'quantities'" class="rounded-xl border border-bo-line bg-bo-card p-5" aria-labelledby="localities-title">
                    <h2 id="localities-title" class="font-semibold">Boletos por localidad</h2>
                    <ul class="mt-3 divide-y divide-bo-line">
                        <li v-for="locality in localities" :key="locality.id" class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p class="font-medium">{{ locality.name }}</p>
                                <p class="tabular text-sm text-bo-slate">{{ money(locality.price) }}</p>
                            </div>
                            <QuantityStepper v-model="quantities[locality.id]" :max="maxTickets" :label="locality.name" />
                        </li>
                    </ul>
                </section>

                <SeatPicker
                    v-else
                    :stage="stage"
                    :event-id="event.id"
                    :function-id="functionId"
                    stage-route="boxoffice.tickets.checkout.stage"
                    :max-tickets="maxTickets"
                    @change="onPick"
                >
                    <template #extra>
                        <div v-if="ticketKeys.length && selectedLocality?.specials.length" class="mt-5 border-t border-bo-line pt-4">
                            <h3 class="text-sm font-semibold">Tipo de boleto</h3>
                            <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                                <li v-for="key in ticketKeys" :key="key" class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium">{{ ticketLabel(key) }}</span>
                                    <select
                                        v-model="specials[key]"
                                        class="rounded-lg border-bo-line py-1.5 pr-9 text-sm focus:border-bo-green focus:ring-bo-green"
                                        :aria-label="`Tipo de boleto para ${ticketLabel(key)}`"
                                    >
                                        <option :value="null">Regular</option>
                                        <option v-for="special in selectedLocality.specials" :key="special.id" :value="special.id">
                                            {{ special.name }} — {{ money(special.price) }}
                                        </option>
                                    </select>
                                </li>
                            </ul>
                        </div>

                        <div v-if="extraChairAvailable && pick.seats.length" class="mt-5 border-t border-bo-line pt-4">
                            <h3 class="text-sm font-semibold">Silla adicional</h3>
                            <p class="text-sm text-bo-slate">
                                {{ money(extraChair.price) }} más {{ money(extraChair.commission) }} de cargo por servicio, por mesa.
                            </p>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <label v-for="seat in pick.seats" :key="seat" class="flex items-center gap-2 text-sm">
                                    <input v-model="extraChairs" type="checkbox" :value="seat" class="rounded border-bo-line text-bo-green focus:ring-bo-green" />
                                    {{ seat }}
                                </label>
                            </div>
                        </div>
                    </template>
                </SeatPicker>
            </div>

            <aside class="xl:sticky xl:top-24" aria-label="Resumen de la venta">
                <div class="bo-stub border border-bo-line shadow-[0_1px_0_rgba(22,32,43,0.04)]" :style="{ '--stub-cut': 'calc(100% - 152px)' }">
                    <div class="p-5">
                        <p class="text-sm text-bo-slate">Tu venta</p>
                        <p class="font-semibold">{{ selectedLocality?.name ?? (event.mode === "quantities" ? "Varias localidades" : "Elige una zona") }}</p>

                        <ul v-if="previewLines.length" class="mt-4 max-h-64 space-y-1.5 overflow-y-auto text-sm">
                            <li v-for="line in previewLines" :key="line.key" class="flex justify-between gap-3">
                                <span class="truncate">{{ line.label }}</span>
                                <span class="tabular shrink-0">{{ money(line.price) }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-4 text-sm text-bo-muted">Aún no hay boletos.</p>

                        <p v-if="promotion" class="mt-3 text-xs text-bo-slate">El 2x1 y las promociones del evento se aplican al calcular el cobro.</p>
                    </div>

                    <div class="bo-perforation" />

                    <div class="p-5">
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm text-bo-slate">
                                <span class="tabular">{{ ticketCount }}</span> {{ ticketCount === 1 ? "boleto" : "boletos" }}
                            </span>
                            <span class="tabular text-2xl font-semibold">{{ money(previewTotal) }}</span>
                        </div>
                        <p class="text-xs text-bo-muted">Antes de cargos por servicio.</p>

                        <button
                            type="button"
                            class="mt-4 w-full rounded-lg bg-bo-green px-4 py-3 font-semibold text-white hover:bg-bo-green-dark disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canContinue || form.processing"
                            @click="submit"
                        >
                            {{ form.processing ? "Calculando…" : "Continuar al cobro" }}
                        </button>
                        <Link
                            :href="route('boxoffice.tickets.checkout.cancel', event.id)"
                            method="post"
                            as="button"
                            class="mt-2 w-full rounded-lg px-4 py-2 text-sm font-medium text-bo-slate hover:text-bo-ink"
                        >
                            Cancelar venta
                        </Link>
                    </div>
                </div>
            </aside>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import StepIndicator from "@/components/BoxOffice/StepIndicator.vue";
import QuantityStepper from "@/components/BoxOffice/QuantityStepper.vue";
import SeatPicker from "@/components/BoxOffice/SeatPicker.vue";
import { money } from "@/components/BoxOffice/format.js";

const props = defineProps({
    event: { type: Object, required: true },
    functions: { type: Array, required: true },
    localities: { type: Array, required: true },
    stage: { type: Object, default: null },
    paymentTypes: { type: Array, required: true },
    discounts: { type: Array, required: true },
    extraChair: { type: Object, default: null },
    previous: { type: Object, default: null },
});

const maxTickets = 50;

const functionId = ref(props.previous?.functionId ?? (props.functions.length === 1 ? props.functions[0].id : null));
const paymentType = ref(props.previous?.paymentType ?? props.paymentTypes[0]?.value);
const discount = ref(props.previous?.discount ?? null);
const promotion = ref(false);
const quantities = reactive(Object.fromEntries(props.localities.map((locality) => [locality.id, 0])));
const pick = ref({ localityId: null, sectionId: null, generalAdmission: false, seats: [], quantity: 0 });
const specials = reactive({});
const extraChairs = ref([]);

const form = useForm({});

const selectedLocality = computed(() => props.localities.find((locality) => locality.id === pick.value.localityId) ?? null);

const ticketKeys = computed(() =>
    pick.value.generalAdmission ? Array.from({ length: pick.value.quantity }, (_, index) => `Boleto${index + 1}`) : pick.value.seats,
);

const extraChairAvailable = computed(
    () => props.extraChair && !pick.value.generalAdmission && props.extraChair.localities.includes(selectedLocality.value?.id),
);

function onPick(value) {
    pick.value = value;
}

watch(ticketKeys, (keys) => {
    Object.keys(specials).forEach((key) => {
        if (!keys.includes(key)) delete specials[key];
    });
    extraChairs.value = extraChairs.value.filter((seat) => keys.includes(seat));
});

function selectFunction(id) {
    functionId.value = id;
}

function ticketLabel(key) {
    return key.startsWith("Boleto") ? `Boleto ${key.slice(6)}` : `Asiento ${key}`;
}

function discounted(price) {
    return discount.value ? price - price * (discount.value / 100) : price;
}

const previewLines = computed(() => {
    if (props.event.mode === "quantities") {
        return props.localities.flatMap((locality) =>
            Array.from({ length: quantities[locality.id] || 0 }, (_, index) => ({
                key: `${locality.id}-${index}`,
                label: `${locality.name} ${index + 1}`,
                price: discounted(locality.price),
            })),
        );
    }

    if (!selectedLocality.value) return [];

    return ticketKeys.value.map((key) => {
        const special = selectedLocality.value.specials.find((item) => item.id === specials[key]);

        return {
            key,
            label: special ? `${ticketLabel(key)} (${special.name})` : ticketLabel(key),
            price: special ? special.price : discounted(selectedLocality.value.price),
        };
    });
});

const ticketCount = computed(() => previewLines.value.length + extraChairs.value.length);
const previewTotal = computed(
    () => previewLines.value.reduce((sum, line) => sum + Number(line.price), 0) + extraChairs.value.length * (props.extraChair?.price ?? 0),
);
const canContinue = computed(() => Boolean(functionId.value && paymentType.value && previewLines.value.length));
const errorList = computed(() => Object.values(form.errors));

function submit() {
    const base = {
        funcion: functionId.value,
        tipo_pago: paymentType.value,
        promocion: promotion.value,
        descuento: discount.value,
    };

    const payload =
        props.event.mode === "quantities"
            ? { ...base, cantidades: { ...quantities } }
            : {
                  ...base,
                  localidad: pick.value.localityId,
                  seccion: pick.value.sectionId,
                  asientos: pick.value.seats,
                  cantidad: pick.value.generalAdmission ? pick.value.quantity : null,
                  especiales: Object.fromEntries(Object.entries(specials).filter(([, value]) => value)),
                  sillas_extra: extraChairs.value,
              };

    form.transform(() => payload).post(route("boxoffice.tickets.checkout.selection.store", props.event.id), {
        preserveScroll: true,
    });
}
</script>
