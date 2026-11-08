<template>
    <BackOfficeLayout title="Ventas del día">
        <form class="flex max-w-2xl flex-wrap items-end gap-4 rounded-xl border border-bo-line bg-bo-card p-5" @submit.prevent="open">
            <label class="min-w-64 flex-1 text-sm font-medium">
                <span class="mb-1.5 block">Evento</span>
                <select v-model="eventId" required class="block w-full rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green">
                    <option :value="null" disabled>Elige un evento</option>
                    <option v-for="event in events" :key="event.id" :value="event.id">{{ event.name }} ({{ event.date }})</option>
                </select>
            </label>
            <label class="text-sm font-medium">
                <span class="mb-1.5 block">Día</span>
                <input v-model="day" type="date" required class="rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green" />
            </label>
            <button type="submit" class="rounded-lg bg-bo-green px-5 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50" :disabled="!eventId || !day">
                Generar PDF
            </button>
        </form>
    </BackOfficeLayout>
</template>

<script setup>
import { ref } from "vue";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";

const props = defineProps({
    events: { type: Array, required: true },
    today: { type: String, required: true },
});

const eventId = ref(null);
const day = ref(props.today);

function open() {
    window.open(route("boxoffice.reports.daily.pdf", { evento: eventId.value, fecha: day.value }), "_blank", "noopener");
}
</script>
