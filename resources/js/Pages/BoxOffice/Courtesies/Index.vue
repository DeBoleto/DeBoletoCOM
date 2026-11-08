<template>
    <BackOfficeLayout title="Cortesías">
        <template #actions>
            <Link
                :href="route('boxoffice.courtesies.create.index')"
                class="hidden items-center gap-2 rounded-lg bg-bo-green px-3 py-2 text-sm font-semibold text-white hover:bg-bo-green-dark sm:inline-flex"
            >
                <BoIcon name="plus" :size="16" />
                Crear cortesías
            </Link>
        </template>

        <div class="mb-6 flex flex-wrap items-end gap-4">
            <label class="text-sm font-medium">
                <span class="mb-1.5 block">Evento</span>
                <select
                    :value="selectedEvent ?? ''"
                    class="w-80 max-w-full rounded-lg border-bo-line py-2.5 pr-9 focus:border-bo-green focus:ring-bo-green"
                    @change="selectEvent($event.target.value)"
                >
                    <option value="" disabled>Elige un evento</option>
                    <option v-for="event in events" :key="event.id" :value="event.id">{{ event.name }} ({{ event.date }})</option>
                </select>
            </label>
            <a
                v-if="selectedEvent && pending > 0"
                :href="route('boxoffice.courtesies.print-pending', selectedEvent)"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 rounded-lg bg-bo-ink px-4 py-2.5 text-sm font-semibold text-white hover:bg-bo-ink-2"
            >
                <BoIcon name="printer" :size="16" />
                Imprimir {{ pending }} pendientes
            </a>
            <label v-if="tickets.length" class="relative ml-auto block w-full sm:w-64">
                <span class="sr-only">Filtrar cortesías</span>
                <BoIcon name="search" :size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-bo-muted" />
                <input
                    v-model.trim="search"
                    type="search"
                    placeholder="Folio, asiento o zona"
                    class="block w-full rounded-lg border-bo-line bg-bo-card py-2.5 pr-3 pl-10 focus:border-bo-green focus:ring-bo-green"
                />
            </label>
        </div>

        <EmptyState v-if="!selectedEvent" title="Elige un evento" text="Verás las cortesías emitidas y podrás imprimirlas o darlas de baja." />
        <EmptyState v-else-if="tickets.length === 0" title="Sin cortesías" text="Este evento todavía no tiene boletos de cortesía.">
            <Link :href="route('boxoffice.courtesies.create.selection', selectedEvent)" class="font-semibold text-bo-green hover:text-bo-green-dark">Crear cortesías</Link>
        </EmptyState>

        <div v-else class="overflow-x-auto rounded-xl border border-bo-line bg-bo-card">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="border-b border-bo-line text-bo-slate">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Folio</th>
                        <th scope="col" class="px-5 py-3 font-medium">Zona</th>
                        <th scope="col" class="px-5 py-3 font-medium">Asiento</th>
                        <th scope="col" class="px-5 py-3 font-medium">Función</th>
                        <th scope="col" class="px-5 py-3 font-medium">Estado</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-bo-line">
                    <tr v-for="ticket in filtered" :key="ticket.id">
                        <td class="tabular px-5 py-3">{{ ticket.id }}</td>
                        <td class="px-5 py-3">{{ ticket.locality }}</td>
                        <td class="tabular px-5 py-3">{{ ticket.seat ?? "General" }}</td>
                        <td class="px-5 py-3 whitespace-nowrap">{{ ticket.function }}</td>
                        <td class="px-5 py-3">
                            <span
                                class="rounded px-2 py-0.5 text-xs font-semibold"
                                :class="ticket.printed ? 'bg-bo-green-soft text-bo-green-dark' : 'bg-bo-paper text-bo-slate'"
                            >
                                {{ ticket.printed ? "Impresa" : "Sin imprimir" }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex justify-end gap-2">
                                <a
                                    v-if="!ticket.printed"
                                    :href="route('boxoffice.courtesies.print', ticket.id)"
                                    target="_blank"
                                    rel="noopener"
                                    class="rounded-lg border border-bo-line px-3 py-1.5 font-medium hover:border-bo-slate"
                                    @click="markPrinted(ticket)"
                                >
                                    Imprimir
                                </a>
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 font-medium text-bo-sold hover:bg-bo-sold-soft"
                                    @click="remove(ticket)"
                                >
                                    Eliminar
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { computed, ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import EmptyState from "@/components/BoxOffice/EmptyState.vue";

const props = defineProps({
    events: { type: Array, required: true },
    selectedEvent: { type: Number, default: null },
    tickets: { type: Array, default: () => [] },
});

const search = ref("");

const pending = computed(() => props.tickets.filter((ticket) => !ticket.printed).length);

const filtered = computed(() => {
    const term = search.value.toLowerCase();
    if (!term) return props.tickets;

    return props.tickets.filter((ticket) => `${ticket.id} ${ticket.seat ?? ""} ${ticket.locality ?? ""}`.toLowerCase().includes(term));
});

function selectEvent(eventId) {
    router.get(route("boxoffice.courtesies.index"), { evento: eventId }, { preserveState: true, only: ["selectedEvent", "tickets"] });
}

function markPrinted(ticket) {
    ticket.printed = true;
}

function remove(ticket) {
    if (!window.confirm(`¿Eliminar la cortesía ${ticket.id}? El boleto dejará de ser válido.`)) return;

    router.delete(route("boxoffice.courtesies.destroy", ticket.id), { preserveScroll: true });
}
</script>
