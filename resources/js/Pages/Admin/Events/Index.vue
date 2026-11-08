<template>
    <BackOfficeLayout title="Eventos">
        <template #actions>
            <Link v-if="canCreate" :href="route('admin.events.create')" class="inline-flex items-center gap-2 rounded-lg bg-bo-green px-3 py-2 text-sm font-semibold text-white hover:bg-bo-green-dark">
                <BoIcon name="plus" :size="16" />
                Nuevo evento
            </Link>
        </template>

        <form class="relative mb-5 w-full sm:w-96" @submit.prevent="router.get(route('admin.events.index'), { buscar: search || undefined }, { preserveState: true, replace: true })">
            <label for="search" class="sr-only">Buscar evento</label>
            <BoIcon name="search" :size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-bo-muted" />
            <input id="search" v-model.trim="search" type="search" placeholder="Nombre o id del evento" class="block w-full rounded-lg border-bo-line bg-bo-card py-2.5 pl-10 focus:border-bo-green focus:ring-bo-green" />
        </form>

        <EmptyState v-if="events.data.length === 0" title="Sin eventos" text="Ningún evento coincide con la búsqueda." />

        <div v-else class="overflow-x-auto rounded-xl border border-bo-line bg-bo-card">
            <table class="w-full min-w-[960px] text-left text-sm">
                <thead class="border-b border-bo-line text-bo-slate">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Evento</th>
                        <th scope="col" class="px-4 py-3 font-medium">Fecha</th>
                        <th scope="col" class="px-4 py-3 font-medium">Estado</th>
                        <th scope="col" class="px-4 py-3 font-medium">Venta web</th>
                        <th scope="col" class="px-4 py-3 font-medium">2x1</th>
                        <th scope="col" class="px-4 py-3 font-medium">MSI</th>
                        <th scope="col" class="px-4 py-3 font-medium">Transferencia</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-bo-line">
                    <tr v-for="event in events.data" :key="event.id">
                        <td class="px-4 py-3">
                            <Link :href="route('admin.events.show', event.id)" class="font-medium hover:text-bo-green-dark">{{ event.name }}</Link>
                            <p class="text-xs text-bo-slate"><span class="tabular">#{{ event.id }}</span> — {{ event.venue }}</p>
                        </td>
                        <td class="tabular px-4 py-3 whitespace-nowrap">{{ event.date }}</td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="event.published ? 'bg-bo-green-soft text-bo-green-dark' : 'bg-bo-paper text-bo-slate'"
                                :title="event.published ? 'Finalizar evento' : 'Activar evento'"
                                @click="toggle(event, 'estado', event.published ? 3 : 2, event.published ? '¿Finalizar este evento? Dejará de venderse.' : '¿Activar este evento?')"
                            >
                                {{ event.published ? "Activo" : "Inactivo" }}
                            </button>
                        </td>
                        <td v-for="flag in flagNames" :key="flag" class="px-4 py-3">
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="event.flags[flag]"
                                :aria-label="`${labels[flag]} de ${event.name}`"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="event.flags[flag] ? 'bg-bo-green' : 'bg-bo-line'"
                                @click="toggle(event, flag, event.flags[flag] ? 0 : 1)"
                            >
                                <span class="inline-block size-5 rounded-full bg-white shadow transition-transform" :class="event.flags[flag] ? 'translate-x-5' : 'translate-x-0.5'" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :page="events" />
    </BackOfficeLayout>
</template>

<script setup>
import { ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import EmptyState from "@/components/BoxOffice/EmptyState.vue";
import Pagination from "@/components/BoxOffice/Pagination.vue";

const props = defineProps({
    events: { type: Object, required: true },
    filters: { type: Object, required: true },
    canCreate: { type: Boolean, default: false },
});

const search = ref(props.filters.buscar ?? "");
const flagNames = ["venta_web", "promocion", "msi", "transfer"];
const labels = { venta_web: "Venta web", promocion: "2x1", msi: "Meses sin intereses", transfer: "Transferencia" };

function toggle(event, flag, value, question = null) {
    if (question && !window.confirm(question)) return;
    router.patch(route("admin.events.flags", event.id), { flag, value }, { preserveScroll: true, preserveState: true });
}
</script>
