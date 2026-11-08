<template>
    <BoxOfficeLayout :title="`${title} — ${event.name}`">
        <template #header>
            <div class="min-w-0">
                <p class="text-xs text-bo-slate">{{ title }}</p>
                <h1 class="truncate text-lg font-semibold">{{ event.name }}</h1>
            </div>
        </template>

        <div v-if="note" class="mb-6 rounded-lg border border-bo-line bg-bo-card px-4 py-3 text-sm">{{ note }}</div>

        <div v-if="errorList.length" role="alert" class="mb-6 rounded-lg border border-bo-sold/30 bg-bo-sold-soft px-4 py-3 text-sm text-bo-sold">
            <ul class="list-disc pl-5">
                <li v-for="message in errorList" :key="message">{{ message }}</li>
            </ul>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_320px] xl:items-start">
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
                            @click="functionId = item.id"
                        >
                            <span class="block font-medium">{{ item.date }}</span>
                            <span class="tabular block text-sm text-bo-slate">{{ item.time }} h</span>
                        </button>
                    </div>
                </section>

                <section v-if="paymentTypes.length" class="rounded-xl border border-bo-line bg-bo-card p-5">
                    <h2 id="payment-title" class="font-semibold">Forma de pago de la diferencia</h2>
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
                </section>

                <SeatPicker
                    :stage="stage"
                    :event-id="event.id"
                    :function-id="functionId"
                    :stage-route="stageRoute"
                    :show-prices="showPrices"
                    @change="pick = $event"
                />
            </div>

            <aside class="xl:sticky xl:top-24">
                <div class="rounded-xl border border-bo-line bg-bo-card p-5">
                    <p class="text-sm text-bo-slate">Selección</p>
                    <p class="font-semibold">{{ localityName ?? "Elige una zona" }}</p>
                    <p class="tabular mt-3 text-3xl font-semibold">{{ count }}</p>
                    <p class="text-sm text-bo-slate">{{ count === 1 ? "boleto" : "boletos" }}</p>
                    <p v-if="showPrices && count" class="tabular mt-2 text-sm text-bo-slate">Valor: {{ money(count * pick.price) }}</p>

                    <button
                        type="button"
                        class="mt-5 w-full rounded-lg bg-bo-green px-4 py-3 font-semibold text-white hover:bg-bo-green-dark disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!functionId || !count || form.processing"
                        @click="submit"
                    >
                        {{ form.processing ? "Validando…" : submitLabel }}
                    </button>
                    <Link :href="route(cancelRoute)" class="mt-2 block w-full rounded-lg px-4 py-2 text-center text-sm font-medium text-bo-slate hover:text-bo-ink">
                        Cancelar
                    </Link>
                </div>
            </aside>
        </div>
    </BoxOfficeLayout>
</template>

<script setup>
import { computed, ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import BoxOfficeLayout from "@/Layouts/BoxOfficeLayout.vue";
import SeatPicker from "@/Components/BoxOffice/SeatPicker.vue";
import { money } from "@/Components/BoxOffice/format.js";

const props = defineProps({
    title: { type: String, required: true },
    event: { type: Object, required: true },
    functions: { type: Array, required: true },
    localities: { type: Array, required: true },
    stage: { type: Object, default: null },
    submitRoute: { type: String, required: true },
    stageRoute: { type: String, required: true },
    cancelRoute: { type: String, required: true },
    submitLabel: { type: String, default: "Continuar" },
    showPrices: { type: Boolean, default: true },
    paymentTypes: { type: Array, default: () => [] },
    note: { type: String, default: "" },
});

const functionId = ref(props.functions.length === 1 ? props.functions[0].id : null);
const paymentType = ref(props.paymentTypes[0]?.value ?? null);
const pick = ref({ localityId: null, sectionId: null, generalAdmission: false, seats: [], quantity: 0, price: 0 });
const form = useForm({});

const count = computed(() => (pick.value.generalAdmission ? pick.value.quantity : pick.value.seats.length));
const localityName = computed(() => props.localities.find((locality) => locality.id === pick.value.localityId)?.name ?? null);
const errorList = computed(() => Object.values(form.errors));

function submit() {
    form
        .transform(() => ({
            funcion: functionId.value,
            tipo_pago: paymentType.value,
            localidad: pick.value.localityId,
            seccion: pick.value.sectionId,
            asientos: pick.value.seats,
            cantidad: pick.value.generalAdmission ? pick.value.quantity : null,
        }))
        .post(route(props.submitRoute, props.event.id), { preserveScroll: true });
}
</script>
