<template>
    <BackOfficeLayout :title="`Boletos — ${event.name}`">
        <template #actions>
            <a :href="route('admin.tickets.export', event.id)" class="inline-flex items-center gap-2 rounded-lg border border-bo-line bg-bo-card px-3 py-2 text-sm font-medium hover:border-bo-slate">
                <BoIcon name="download" :size="16" />
                Excel
            </a>
            <button v-if="event.olmecas" type="button" class="inline-flex items-center gap-2 rounded-lg bg-bo-ink px-3 py-2 text-sm font-semibold text-white hover:bg-bo-ink-2" @click="olmecas">
                Generar bloqueos de temporada
            </button>
        </template>

        <div class="mb-5 flex flex-wrap items-center gap-3">
            <form class="relative w-full sm:w-80" @submit.prevent="filter({ buscar: search || undefined })">
                <label for="search" class="sr-only">Buscar boleto</label>
                <BoIcon name="search" :size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-bo-muted" />
                <input id="search" v-model.trim="search" type="search" placeholder="Folio, compra, asiento o correo" class="block w-full rounded-lg border-bo-line bg-bo-card py-2.5 pl-10 focus:border-bo-green focus:ring-bo-green" />
            </form>
            <label class="flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" :checked="filters.impresos" class="rounded border-bo-line text-bo-green focus:ring-bo-green" @change="filter({ impresos: $event.target.checked ? 1 : undefined })" />
                Solo impresos
            </label>
        </div>

        <EmptyState v-if="tickets.data.length === 0" title="Sin boletos" text="No hay boletos que coincidan con el filtro." />
        <div v-else class="overflow-x-auto rounded-xl border border-bo-line bg-bo-card">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b border-bo-line text-bo-slate">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Folio</th>
                        <th scope="col" class="px-4 py-3 font-medium">Compra</th>
                        <th scope="col" class="px-4 py-3 font-medium">Zona / asiento</th>
                        <th scope="col" class="px-4 py-3 font-medium">Precio</th>
                        <th scope="col" class="px-4 py-3 font-medium">Pago</th>
                        <th scope="col" class="px-4 py-3 font-medium">Comprador</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-bo-line">
                    <tr v-for="ticket in tickets.data" :key="ticket.id">
                        <td class="tabular px-4 py-2.5">{{ ticket.id }}</td>
                        <td class="tabular px-4 py-2.5"><Link :href="route('admin.purchases.index', { folio: ticket.purchase })" class="hover:text-bo-green-dark">{{ ticket.purchase }}</Link></td>
                        <td class="px-4 py-2.5">{{ ticket.locality }}<span v-if="ticket.seat" class="text-bo-slate"> — {{ ticket.seat }}</span></td>
                        <td class="tabular px-4 py-2.5">{{ ticket.courtesy ? "Cortesía" : money(ticket.price) }}</td>
                        <td class="px-4 py-2.5">{{ ticket.payment }}</td>
                        <td class="px-4 py-2.5"><span class="block max-w-52 truncate">{{ ticket.buyer }}</span><span class="tabular text-xs text-bo-muted">{{ ticket.date }}</span></td>
                        <td class="px-4 py-2.5">
                            <div class="flex justify-end gap-1">
                                <button v-if="ticket.printed" type="button" class="rounded-lg px-2.5 py-1.5 font-medium hover:bg-bo-paper" @click="unlock(ticket)">Desbloquear</button>
                                <button type="button" class="rounded-lg px-2.5 py-1.5 font-medium text-bo-sold hover:bg-bo-sold-soft" @click="remove(ticket)">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :page="tickets" />
    </BackOfficeLayout>
</template>

<script setup>
import { ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import EmptyState from "@/components/BoxOffice/EmptyState.vue";
import Pagination from "@/components/BoxOffice/Pagination.vue";
import { money } from "@/components/BoxOffice/format.js";

const props = defineProps({
    event: { type: Object, required: true },
    tickets: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const search = ref(props.filters.buscar ?? "");

function filter(changes) {
    router.get(route("admin.tickets.index", props.event.id), { buscar: props.filters.buscar || undefined, impresos: props.filters.impresos ? 1 : undefined, ...changes }, { preserveState: true, replace: true });
}

function unlock(ticket) {
    router.post(route("admin.tickets.unlock", { evento: props.event.id, boleto: ticket.id }), {}, { preserveScroll: true });
}

function remove(ticket) {
    if (!window.confirm(`¿Eliminar el boleto ${ticket.id}? Se recalculará la compra ${ticket.purchase}. Esta acción no se puede deshacer.`)) return;
    router.delete(route("admin.tickets.destroy", { evento: props.event.id, boleto: ticket.id }), { preserveScroll: true });
}

function olmecas() {
    if (!window.confirm("Se crearán las cortesías y bloqueos de temporada de este partido. Los asientos que ya existan se omiten. ¿Continuar?")) return;
    router.post(route("admin.events.olmecas", props.event.id), {}, { preserveScroll: true });
}
</script>
