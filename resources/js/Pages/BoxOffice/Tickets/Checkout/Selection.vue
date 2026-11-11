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
            <div class="min-w-0 space-y-6">
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
                    ref="picker"
                    :stage="stage"
                    :event-id="event.id"
                    :function-id="functionId"
                    stage-route="boxoffice.tickets.checkout.stage"
                    :max-tickets="maxTickets"
                    multiple
                    @change="onPick"
                >
                    <template #extra>
                        <div v-if="specialZones.length" class="mt-5 border-t border-bo-line pt-4">
                            <h3 class="text-sm font-semibold">Tipo de boleto</h3>
                            <div v-for="zone in specialZones" :key="zone.key" class="mt-3">
                                <p v-if="picks.length > 1" class="mb-1.5 text-xs font-semibold tracking-wide text-bo-muted uppercase">{{ zone.name }}</p>
                                <ul class="grid gap-2 sm:grid-cols-2">
                                    <li v-for="ticket in zone.tickets" :key="ticket.key" class="flex items-center justify-between gap-3 text-sm">
                                        <span class="font-medium">{{ ticket.label }}</span>
                                        <select
                                            v-model="specials[ticket.key]"
                                            class="rounded-lg border-bo-line py-1.5 pr-9 text-sm focus:border-bo-green focus:ring-bo-green"
                                            :aria-label="`Tipo de boleto para ${ticket.label}`"
                                        >
                                            <option :value="null">Regular</option>
                                            <option v-for="special in zone.specials" :key="special.id" :value="special.id">
                                                {{ special.name }} — {{ money(special.price) }}
                                            </option>
                                        </select>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div v-if="chairZones.length" class="mt-5 border-t border-bo-line pt-4">
                            <h3 class="text-sm font-semibold">Silla adicional</h3>
                            <p class="text-sm text-bo-slate">
                                {{ money(extraChair.price) }} más {{ money(extraChair.commission) }} de cargo por servicio, por mesa.
                            </p>
                            <div v-for="zone in chairZones" :key="zone.key" class="mt-2 flex flex-wrap items-center gap-3">
                                <span v-if="chairZones.length > 1" class="text-xs font-semibold tracking-wide text-bo-muted uppercase">{{ zone.name }}</span>
                                <label v-for="seat in zone.seats" :key="seat" class="flex items-center gap-2 text-sm">
                                    <input v-model="extraChairs" type="checkbox" :value="`${zone.key}|${seat}`" class="rounded border-bo-line text-bo-green focus:ring-bo-green" />
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
                        <p class="font-semibold">{{ saleTitle }}</p>

                        <div v-if="previewGroups.length" class="mt-4 max-h-80 space-y-4 overflow-y-auto pr-1 text-sm">
                            <div v-for="group in previewGroups" :key="group.key">
                                <div class="mb-1.5 flex items-center justify-between gap-2">
                                    <button
                                        v-if="group.focusable"
                                        type="button"
                                        class="truncate text-xs font-semibold tracking-wide text-bo-slate uppercase hover:text-bo-green"
                                        @click="picker?.focus(group.key)"
                                    >
                                        {{ group.name }}
                                    </button>
                                    <span v-else class="truncate text-xs font-semibold tracking-wide text-bo-slate uppercase">{{ group.name }}</span>
                                    <button
                                        v-if="group.removable"
                                        type="button"
                                        class="shrink-0 rounded p-0.5 text-bo-muted hover:text-bo-sold"
                                        :aria-label="`Quitar ${group.name}`"
                                        @click="picker?.clear(group.key)"
                                    >
                                        <BoIcon name="close" :size="14" />
                                    </button>
                                </div>
                                <ul class="space-y-1">
                                    <li v-for="line in group.lines" :key="line.key" class="flex justify-between gap-3">
                                        <span class="truncate">{{ line.label }}</span>
                                        <span class="tabular shrink-0">{{ money(line.price) }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
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
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
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

const picker = ref(null);
const functionId = ref(props.previous?.functionId ?? (props.functions.length === 1 ? props.functions[0].id : null));
const paymentType = ref(props.previous?.paymentType ?? props.paymentTypes[0]?.value);
const discount = ref(props.previous?.discount ?? null);
const promotion = ref(false);
const quantities = reactive(Object.fromEntries(props.localities.map((locality) => [locality.id, 0])));
const picks = ref([]);
const specials = reactive({});
const extraChairs = ref([]);

const form = useForm({});

const localityOf = (pick) => props.localities.find((locality) => locality.id === pick.localityId) ?? null;

const zones = computed(() =>
    picks.value.map((pick) => ({
        ...pick,
        locality: localityOf(pick),
        tickets: (pick.generalAdmission ? Array.from({ length: pick.quantity }, (_, index) => `Boleto${index + 1}`) : pick.seats).map((id) => ({
            id,
            key: `${pick.key}|${id}`,
            label: ticketLabel(id),
        })),
    })),
);

const specialZones = computed(() =>
    zones.value
        .filter((zone) => zone.tickets.length && zone.locality?.specials.length)
        .map((zone) => ({ key: zone.key, name: zone.name, tickets: zone.tickets, specials: zone.locality.specials })),
);

const chairZones = computed(() =>
    props.extraChair
        ? zones.value.filter((zone) => !zone.generalAdmission && zone.seats.length && props.extraChair.localities.includes(zone.localityId))
        : [],
);

function onPick(value) {
    picks.value = value;
}

watch(zones, (list) => {
    const keys = list.flatMap((zone) => zone.tickets.map((ticket) => ticket.key));
    const chairKeys = chairZones.value.flatMap((zone) => zone.seats.map((seat) => `${zone.key}|${seat}`));

    Object.keys(specials).forEach((key) => {
        if (!keys.includes(key)) delete specials[key];
    });
    extraChairs.value = extraChairs.value.filter((key) => chairKeys.includes(key));
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

const previewGroups = computed(() => {
    if (props.event.mode === "quantities") {
        return props.localities
            .filter((locality) => quantities[locality.id] > 0)
            .map((locality) => ({
                key: locality.id,
                name: locality.name,
                lines: Array.from({ length: quantities[locality.id] }, (_, index) => ({
                    key: `${locality.id}-${index}`,
                    label: `Boleto ${index + 1}`,
                    price: discounted(locality.price),
                })),
            }));
    }

    return zones.value
        .filter((zone) => zone.locality)
        .map((zone) => {
            const lines = zone.tickets.map((ticket) => {
                const special = zone.locality.specials.find((item) => item.id === specials[ticket.key]);

                return {
                    key: ticket.key,
                    label: special ? `${ticket.label} (${special.name})` : ticket.label,
                    price: special ? special.price : discounted(zone.locality.price),
                };
            });

            extraChairs.value
                .filter((key) => key.startsWith(`${zone.key}|`))
                .forEach((key) => lines.push({ key: `${key}|chair`, label: `Silla adicional ${key.split("|")[1]}`, price: props.extraChair.price }));

            return { key: zone.key, name: zone.name, lines, removable: true, focusable: true };
        });
});

const previewLines = computed(() => previewGroups.value.flatMap((group) => group.lines));
const ticketCount = computed(() => previewLines.value.length);
const previewTotal = computed(() => previewLines.value.reduce((sum, line) => sum + Number(line.price), 0));
const canContinue = computed(() => Boolean(functionId.value && paymentType.value && previewLines.value.length));
const errorList = computed(() => Object.values(form.errors));

const saleTitle = computed(() => {
    if (props.event.mode === "quantities") return previewGroups.value.length ? "Varias localidades" : "Elige las localidades";
    if (!picks.value.length) return "Elige una zona";
    if (picks.value.length === 1) return picks.value[0].name;
    return `${picks.value.length} zonas`;
});

function sectionPayload(zone) {
    return {
        localidad: zone.localityId,
        seccion: zone.sectionId,
        asientos: zone.seats,
        cantidad: zone.generalAdmission ? zone.quantity : null,
        especiales: Object.fromEntries(
            zone.tickets.filter((ticket) => specials[ticket.key]).map((ticket) => [ticket.id, specials[ticket.key]]),
        ),
        sillas_extra: extraChairs.value.filter((key) => key.startsWith(`${zone.key}|`)).map((key) => key.split("|")[1]),
    };
}

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
            : { ...base, secciones: zones.value.map(sectionPayload) };

    form.transform(() => payload).post(route("boxoffice.tickets.checkout.selection.store", props.event.id), {
        preserveScroll: true,
    });
}
</script>
