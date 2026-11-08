<template>
    <BackOfficeLayout title="Reportes">
        <form class="mb-6 flex max-w-md gap-3" @submit.prevent="router.get(route('boxoffice.reports.index'), { evento: term }, { preserveState: true })">
            <label class="relative flex-1">
                <span class="sr-only">Buscar evento</span>
                <BoIcon name="search" :size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-bo-muted" />
                <input v-model.trim="term" type="search" placeholder="Buscar evento" class="block w-full rounded-lg border-bo-line bg-bo-card py-2.5 pl-10 focus:border-bo-green focus:ring-bo-green" />
            </label>
            <button type="submit" class="rounded-lg bg-bo-ink px-4 font-semibold text-white hover:bg-bo-ink-2">Buscar</button>
        </form>

        <EmptyState v-if="events.length === 0" title="Sin eventos" :text="search ? `Ningún evento coincide con “${search}”.` : 'Aquí aparecen los eventos que tienes asignados.'" />

        <ul v-else class="divide-y divide-bo-line overflow-hidden rounded-xl border border-bo-line bg-bo-card">
            <li v-for="event in events" :key="event.id" class="flex flex-wrap items-center justify-between gap-4 p-4 sm:px-5">
                <div class="min-w-0">
                    <p class="font-semibold">{{ event.name }}</p>
                    <p class="text-sm text-bo-slate">{{ event.venue }}, {{ event.city }} — <span class="tabular">{{ event.date }}</span></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-for="report in reportsFor(event)"
                        :key="report.type"
                        :href="route('boxoffice.reports.show', { tipo: report.type, evento: event.id })"
                        target="_blank"
                        rel="noopener"
                        class="rounded-lg border px-3 py-1.5 text-sm font-medium"
                        :class="report.primary ? 'border-bo-green bg-bo-green text-white hover:bg-bo-green-dark' : 'border-bo-line hover:border-bo-slate'"
                    >
                        {{ report.label }}
                    </a>
                </div>
            </li>
        </ul>
    </BackOfficeLayout>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import EmptyState from "@/components/BoxOffice/EmptyState.vue";

const props = defineProps({
    search: { type: String, default: "" },
    events: { type: Array, required: true },
    allowed: { type: Array, required: true },
});

const term = ref(props.search);

const REPORTS = [
    { type: "general", label: "General", primary: true },
    { type: "detallado", label: "Detallado" },
    { type: "ventas", label: "Ventas" },
    { type: "pagos", label: "Pagos" },
    { type: "whatsapp", label: "WhatsApp" },
    { type: "estacionamiento", label: "Estacionamiento", parking: true },
    { type: "general-td", label: "General Defender" },
    { type: "detallado-td", label: "Detallado Defender" },
];

function reportsFor(event) {
    return REPORTS.filter((report) => props.allowed.includes(report.type) && (!report.parking || event.parking));
}
</script>
