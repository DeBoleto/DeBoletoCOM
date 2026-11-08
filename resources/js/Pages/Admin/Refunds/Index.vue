<template>
    <BackOfficeLayout :title="`Reembolsos — ${event.name}`">
        <template #actions>
            <a :href="route('admin.refunds.export', event.id)" class="inline-flex items-center gap-2 rounded-lg border border-bo-line bg-bo-card px-3 py-2 text-sm font-medium hover:border-bo-slate">
                <BoIcon name="download" :size="16" />
                Excel
            </a>
        </template>

        <section class="mb-6 grid gap-4 sm:grid-cols-3" aria-label="Totales">
            <div v-for="item in summary" :key="item.label" class="rounded-xl border border-bo-line bg-bo-card p-5">
                <p class="text-sm text-bo-slate">{{ item.label }}</p>
                <p class="tabular mt-1 text-2xl font-semibold">{{ money(item.value) }}</p>
            </div>
        </section>

        <EmptyState v-if="purchases.length === 0" title="Sin compras" text="El evento no tiene compras registradas." />
        <div v-else class="overflow-x-auto rounded-xl border border-bo-line bg-bo-card">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="border-b border-bo-line text-bo-slate">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Compra</th>
                        <th scope="col" class="px-4 py-3 font-medium">Estatus</th>
                        <th scope="col" class="px-4 py-3 font-medium">Pago</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">Boletos</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">Total</th>
                        <th scope="col" class="px-4 py-3 font-medium">Correo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-bo-line">
                    <tr v-for="row in purchases" :key="row.id">
                        <td class="tabular px-4 py-2.5">{{ row.id }}</td>
                        <td class="px-4 py-2.5">
                            <span class="rounded px-2 py-0.5 text-xs font-semibold" :class="row.refunded ? 'bg-bo-green-soft text-bo-green-dark' : row.requested ? 'bg-bo-paper text-bo-pending' : 'text-bo-muted'">{{ row.status }}</span>
                        </td>
                        <td class="px-4 py-2.5">{{ row.payment }}</td>
                        <td class="tabular px-4 py-2.5 text-right">{{ row.tickets }}</td>
                        <td class="tabular px-4 py-2.5 text-right">{{ money(row.subtotal + row.commission) }}</td>
                        <td class="px-4 py-2.5">{{ row.email }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { computed } from "vue";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import EmptyState from "@/components/BoxOffice/EmptyState.vue";
import { money } from "@/components/BoxOffice/format.js";

const props = defineProps({
    event: { type: Object, required: true },
    totals: { type: Object, required: true },
    purchases: { type: Array, required: true },
});

const summary = computed(() => [
    { label: "Total vendido", value: props.totals.sold },
    { label: "Con solicitud de reembolso", value: props.totals.requested },
    { label: "Reembolsado", value: props.totals.refunded },
]);
</script>
