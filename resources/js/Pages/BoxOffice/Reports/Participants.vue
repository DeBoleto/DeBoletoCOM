<template>
    <BackOfficeLayout title="Participantes de carreras">
        <form class="flex max-w-3xl flex-wrap items-end gap-4 rounded-xl border border-bo-line bg-bo-card p-5" @submit.prevent="download">
            <label class="min-w-64 flex-1 text-sm font-medium">
                <span class="mb-1.5 block">Carrera</span>
                <select v-model="eventId" required class="block w-full rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green">
                    <option :value="null" disabled>Elige una carrera</option>
                    <option v-for="event in events" :key="event.id" :value="event.id">{{ event.name }} ({{ event.date }})</option>
                </select>
            </label>
            <label class="text-sm font-medium">
                <span class="mb-1.5 block">Registrados desde</span>
                <input v-model="from" type="date" :max="to || undefined" class="rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green" />
            </label>
            <label class="text-sm font-medium">
                <span class="mb-1.5 block">Hasta</span>
                <input v-model="to" type="date" :min="from || undefined" class="rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green" />
            </label>
            <button type="submit" class="rounded-lg bg-bo-green px-5 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50" :disabled="!eventId">
                Descargar Excel
            </button>
        </form>
        <p class="mt-3 max-w-3xl text-sm text-bo-slate">Sin fechas se exportan todos los registros. Los boletos sin registro aparecen al final con su folio.</p>
    </BackOfficeLayout>
</template>

<script setup>
import { ref } from "vue";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";

defineProps({
    events: { type: Array, required: true },
    today: { type: String, required: true },
});

const eventId = ref(null);
const from = ref("");
const to = ref("");

function download() {
    const params = { evento: eventId.value };
    if (from.value) params.desde = from.value;
    if (to.value) params.hasta = to.value;
    window.location.href = route("boxoffice.reports.participants.export", params);
}
</script>
