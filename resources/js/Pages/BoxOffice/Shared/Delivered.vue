<template>
    <BoxOfficeLayout :title="`${title} — ${event.name}`">
        <div class="mx-auto max-w-2xl">
            <div class="bo-stub border border-bo-line" :style="{ '--stub-cut': 'calc(100% - 88px)' }">
                <div class="p-8 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-bo-green-soft text-bo-green-dark">
                        <BoIcon name="check" :size="26" />
                    </span>
                    <h2 class="mt-4 text-2xl font-semibold">{{ title }}</h2>
                    <p class="mt-1 text-bo-slate">{{ event.name }}: {{ tickets }} {{ tickets === 1 ? "boleto" : "boletos" }}</p>
                </div>
                <div class="bo-perforation" />
                <div class="flex items-center justify-between px-8 py-5">
                    <span class="text-sm text-bo-slate">Folio de compra</span>
                    <span class="tabular text-2xl font-semibold tracking-wide">{{ purchaseId }}</span>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-end gap-3 rounded-xl border border-bo-line bg-bo-card p-5">
                <label v-if="Object.keys(formats).length" class="mr-auto text-sm font-medium">
                    <span class="mb-1.5 block">Formato de impresión</span>
                    <select v-model="format" class="rounded-lg border-bo-line py-2 pr-9 text-sm focus:border-bo-green focus:ring-bo-green">
                        <option v-for="(label, id) in formats" :key="id" :value="Number(id)">{{ label }}</option>
                    </select>
                </label>
                <a
                    :href="printUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2 rounded-lg bg-bo-ink px-4 py-2.5 font-semibold text-white hover:bg-bo-ink-2"
                    @click="printed = true"
                >
                    <BoIcon name="printer" :size="18" />
                    Imprimir boletos
                </a>
            </div>

            <div class="mt-8 flex items-center justify-between border-t border-bo-line pt-6">
                <p class="text-sm" :class="printed ? 'text-bo-slate' : 'text-bo-pending'">
                    {{ printed ? "Boletos enviados a impresión." : "Imprime los boletos antes de finalizar." }}
                </p>
                <Link
                    :href="route(finishRoute, event.id)"
                    method="post"
                    as="button"
                    class="rounded-lg bg-bo-green px-5 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50"
                    :disabled="!printed"
                >
                    Finalizar
                </Link>
            </div>
        </div>
    </BoxOfficeLayout>
</template>

<script setup>
import { computed, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import BoxOfficeLayout from "@/Layouts/BoxOfficeLayout.vue";
import BoIcon from "@/Components/BoxOffice/BoIcon.vue";

const props = defineProps({
    title: { type: String, required: true },
    event: { type: Object, required: true },
    purchaseId: { type: Number, required: true },
    tickets: { type: Number, required: true },
    printRoute: { type: String, required: true },
    finishRoute: { type: String, required: true },
    formats: { type: Object, default: () => ({}) },
    defaultFormat: { type: Number, default: null },
});

const printed = ref(false);
const format = ref(props.defaultFormat && props.formats[props.defaultFormat] ? props.defaultFormat : Number(Object.keys(props.formats)[0]) || null);

const printUrl = computed(() => route(props.printRoute, format.value ? { evento: props.event.id, formato: format.value } : { evento: props.event.id }));
</script>
