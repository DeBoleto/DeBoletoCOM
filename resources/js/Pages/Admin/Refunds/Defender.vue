<template>
    <BackOfficeLayout title="Ticket Defender">
        <label class="mb-6 block max-w-md text-sm font-medium">
            <span class="mb-1.5 block">Evento con Defender</span>
            <select :value="selected ?? ''" class="block w-full rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green" @change="router.get(route('admin.defender.index'), { evento: $event.target.value }, { preserveState: true, only: ['selected', 'summary'] })">
                <option value="" disabled>Elige un evento</option>
                <option v-for="event in events" :key="event.id" :value="event.id">{{ event.name }} — {{ event.venue }}</option>
            </select>
        </label>

        <template v-if="summary">
            <section class="mb-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-bo-line bg-bo-card p-5"><p class="text-sm text-bo-slate">Total asegurado</p><p class="tabular mt-1 text-2xl font-semibold">{{ money(summary.insured) }}</p></div>
                <div class="rounded-xl border border-bo-line bg-bo-card p-5"><p class="text-sm text-bo-slate">Boletos vendidos</p><p class="tabular mt-1 text-2xl font-semibold">{{ money(summary.sold) }}</p></div>
                <div class="rounded-xl border border-bo-line bg-bo-card p-5"><p class="text-sm text-bo-slate">Diferencia</p><p class="tabular mt-1 text-2xl font-semibold">{{ money(summary.pending) }}</p></div>
            </section>
            <div class="mb-4 flex flex-wrap gap-2">
                <a v-for="report in ['general-td', 'detallado-td']" :key="report" :href="route('admin.defender.report', { tipo: report, evento: selected })" target="_blank" rel="noopener" class="rounded-lg border border-bo-line bg-bo-card px-3 py-2 text-sm font-medium hover:border-bo-slate">
                    {{ report === "general-td" ? "Reporte general Defender" : "Reporte detallado Defender" }}
                </a>
            </div>
            <ul class="divide-y divide-bo-line rounded-xl border border-bo-line bg-bo-card text-sm">
                <li v-for="row in summary.purchases" :key="row.id" class="flex justify-between gap-4 px-5 py-2.5">
                    <span class="tabular">Compra {{ row.id }} <span class="text-bo-slate">{{ row.bank }}</span></span>
                    <span class="tabular">{{ money(row.total) }} <span class="text-bo-slate">(Defender {{ money(row.defender) }})</span></span>
                </li>
                <li v-if="!summary.purchases.length" class="px-5 py-3 text-bo-slate">Sin compras con Defender.</li>
            </ul>
        </template>
    </BackOfficeLayout>
</template>

<script setup>
import { router } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import { money } from "@/components/BoxOffice/format.js";

defineProps({
    events: { type: Array, required: true },
    selected: { type: Number, default: null },
    summary: { type: Object, default: null },
});
</script>
