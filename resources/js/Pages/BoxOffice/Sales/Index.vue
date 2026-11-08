<template>
    <BoxOfficeLayout title="Ventas y cortes de caja">
        <template #actions>
            <Link
                :href="route('boxoffice.sales.mail')"
                class="hidden items-center gap-2 rounded-lg border border-bo-line bg-bo-card px-3 py-2 text-sm font-medium hover:border-bo-slate sm:inline-flex"
            >
                <BoIcon name="mail" :size="16" />
                Reenviar boletos
            </Link>
        </template>

        <section class="mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-bo-line bg-bo-card p-5" aria-labelledby="range-title">
            <div class="mr-auto">
                <h2 id="range-title" class="font-semibold">Periodo para “Ventas por fecha”</h2>
                <p class="text-sm text-bo-slate">El corte de caja siempre es del día de hoy.</p>
            </div>
            <label class="text-sm font-medium">
                <span class="mb-1.5 block">Desde</span>
                <input v-model="from" type="date" :max="to" class="rounded-lg border-bo-line py-2 text-sm focus:border-bo-green focus:ring-bo-green" />
            </label>
            <label class="text-sm font-medium">
                <span class="mb-1.5 block">Hasta</span>
                <input v-model="to" type="date" :min="from" class="rounded-lg border-bo-line py-2 text-sm focus:border-bo-green focus:ring-bo-green" />
            </label>
        </section>

        <EmptyState v-if="events.length === 0" title="Sin eventos publicados" text="Aquí aparecen los eventos publicados que tienes asignados." />

        <div v-else class="overflow-x-auto rounded-xl border border-bo-line bg-bo-card">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b border-bo-line text-bo-slate">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Evento</th>
                        <th scope="col" class="px-5 py-3 font-medium">Fecha</th>
                        <th scope="col" class="px-5 py-3 font-medium">Recinto</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Reportes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-bo-line">
                    <tr v-for="event in events" :key="event.id">
                        <td class="px-5 py-3.5">
                            <p class="font-medium">{{ event.name }}</p>
                            <p class="tabular text-xs text-bo-muted">#{{ event.id }}</p>
                        </td>
                        <td class="tabular px-5 py-3.5 whitespace-nowrap">{{ event.date }}</td>
                        <td class="px-5 py-3.5">
                            <p>{{ event.venue }}</p>
                            <p class="text-xs text-bo-slate">{{ event.city }}, {{ event.state }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex justify-end gap-2">
                                <a
                                    :href="route('boxoffice.sales.cash-cut', event.id)"
                                    target="_blank"
                                    rel="noopener"
                                    class="rounded-lg bg-bo-green px-3 py-2 font-medium whitespace-nowrap text-white hover:bg-bo-green-dark"
                                >
                                    Corte de caja
                                </a>
                                <a
                                    :href="route('boxoffice.sales.by-date', { evento: event.id, desde: from, hasta: to })"
                                    target="_blank"
                                    rel="noopener"
                                    class="rounded-lg border border-bo-line px-3 py-2 font-medium whitespace-nowrap hover:border-bo-slate"
                                    :class="!rangeValid ? 'pointer-events-none opacity-50' : ''"
                                    :aria-disabled="!rangeValid"
                                >
                                    Ventas por fecha
                                </a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </BoxOfficeLayout>
</template>

<script setup>
import { computed, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import BoxOfficeLayout from "@/Layouts/BoxOfficeLayout.vue";
import BoIcon from "@/Components/BoxOffice/BoIcon.vue";
import EmptyState from "@/Components/BoxOffice/EmptyState.vue";

const props = defineProps({
    events: { type: Array, required: true },
    today: { type: String, required: true },
});

const from = ref(props.today);
const to = ref(props.today);
const rangeValid = computed(() => Boolean(from.value && to.value && from.value <= to.value));
</script>
