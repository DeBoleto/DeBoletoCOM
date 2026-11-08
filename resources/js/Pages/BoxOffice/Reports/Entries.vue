<template>
    <BackOfficeLayout title="Contador de entradas">
        <div class="flex max-w-3xl flex-wrap items-end gap-4 rounded-xl border border-bo-line bg-bo-card p-5">
            <label class="min-w-64 flex-1 text-sm font-medium">
                <span class="mb-1.5 block">Evento</span>
                <select v-model="eventId" class="block w-full rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green" @change="loadFunctions">
                    <option :value="null" disabled>Elige un evento</option>
                    <option v-for="event in events" :key="event.id" :value="event.id">{{ event.name }}</option>
                </select>
            </label>
            <label class="text-sm font-medium">
                <span class="mb-1.5 block">Función</span>
                <select v-model="functionId" :disabled="!functions.length" class="rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green" @change="loadTotals">
                    <option :value="null" disabled>Elige</option>
                    <option v-for="item in functions" :key="item.id" :value="item.id">{{ item.label }}</option>
                </select>
            </label>
            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-bo-line px-4 py-2.5 font-medium hover:border-bo-slate disabled:opacity-50" :disabled="!functionId" @click="loadTotals">
                <BoIcon name="refresh" :size="16" />
                Actualizar
            </button>
        </div>

        <p v-if="error" class="mt-6 text-sm text-bo-sold" role="alert">{{ error }}</p>

        <section v-if="totals" class="mt-6 max-w-3xl" aria-live="polite">
            <div class="mb-4 flex items-baseline gap-3">
                <span class="tabular text-5xl font-semibold">{{ totals.total }}</span>
                <span class="text-bo-slate">personas han entrado</span>
            </div>
            <ul class="divide-y divide-bo-line rounded-xl border border-bo-line bg-bo-card">
                <li v-for="row in totals.localities" :key="row.locality" class="flex justify-between px-5 py-3">
                    <span>{{ row.locality }}</span>
                    <span class="tabular font-semibold">{{ row.total }}</span>
                </li>
                <li v-if="!totals.localities.length" class="px-5 py-3 text-sm text-bo-slate">Aún no hay accesos registrados.</li>
            </ul>
        </section>
    </BackOfficeLayout>
</template>

<script setup>
import { ref } from "vue";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";

defineProps({ events: { type: Array, required: true } });

const eventId = ref(null);
const functionId = ref(null);
const functions = ref([]);
const totals = ref(null);
const error = ref("");

async function getJson(url) {
    const response = await fetch(url, { headers: { Accept: "application/json" }, credentials: "same-origin" });
    if (!response.ok) throw new Error(String(response.status));
    return response.json();
}

async function loadFunctions() {
    functionId.value = null;
    totals.value = null;
    error.value = "";
    try {
        functions.value = await getJson(route("boxoffice.reports.entries.functions", eventId.value));
        if (functions.value.length === 1) {
            functionId.value = functions.value[0].id;
            loadTotals();
        }
    } catch {
        error.value = "No pudimos cargar las funciones.";
    }
}

async function loadTotals() {
    error.value = "";
    try {
        totals.value = await getJson(route("boxoffice.reports.entries.totals", { evento: eventId.value, funcion: functionId.value }));
    } catch {
        error.value = "No pudimos cargar los accesos.";
    }
}
</script>
