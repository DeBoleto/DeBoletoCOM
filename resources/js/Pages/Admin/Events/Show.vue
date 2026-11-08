<template>
    <BackOfficeLayout :title="event.name">
        <template #actions>
            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="event.published ? 'bg-bo-green-soft text-bo-green-dark' : 'bg-bo-paper text-bo-slate'">
                {{ event.published ? "Activo" : "Inactivo" }}
            </span>
        </template>

        <div class="grid gap-6 lg:grid-cols-[1fr_300px]">
            <div class="space-y-6">
                <section class="flex gap-5 rounded-xl border border-bo-line bg-bo-card p-5">
                    <EventThumb :src="event.image" :alt="event.name" size="sm" class="size-24" />
                    <div class="min-w-0">
                        <p class="tabular text-sm text-bo-slate">#{{ event.id }}</p>
                        <p class="font-semibold">{{ event.venue }}</p>
                        <ul class="tabular mt-2 text-sm text-bo-slate">
                            <li v-for="item in functions" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                </section>

                <section class="rounded-xl border border-bo-line bg-bo-card" aria-labelledby="localities-title">
                    <h2 id="localities-title" class="border-b border-bo-line px-5 py-3 font-semibold">Localidades</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead class="text-bo-slate">
                                <tr>
                                    <th scope="col" class="px-5 py-2 font-medium">Nombre</th>
                                    <th scope="col" class="px-3 py-2 font-medium">Precio</th>
                                    <th scope="col" class="px-3 py-2 font-medium">Comisión taquilla</th>
                                    <th scope="col" class="px-3 py-2 font-medium">Comisión web</th>
                                    <th scope="col" class="px-3 py-2 font-medium">Aforo</th>
                                    <th scope="col" class="px-5 py-2"><span class="sr-only">Guardar</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-bo-line">
                                <tr v-for="row in rows" :key="row.id">
                                    <td class="px-5 py-2"><input v-model="row.name" type="text" :aria-label="`Nombre de ${row.name}`" :class="cell" /></td>
                                    <td class="px-3 py-2"><input v-model="row.price" type="number" min="0" step="0.01" aria-label="Precio" :class="[cell, 'w-28']" /></td>
                                    <td class="px-3 py-2"><input v-model="row.commission" type="number" min="0" step="0.01" aria-label="Comisión taquilla" :class="[cell, 'w-24']" /></td>
                                    <td class="px-3 py-2"><input v-model="row.webCommission" type="number" min="0" step="0.01" aria-label="Comisión web" :class="[cell, 'w-24']" /></td>
                                    <td class="px-3 py-2"><input v-model="row.capacity" type="number" min="0" aria-label="Aforo" :class="[cell, 'w-24']" /></td>
                                    <td class="px-5 py-2 text-right">
                                        <button type="button" class="rounded-lg border border-bo-line px-3 py-1.5 font-medium hover:border-bo-slate disabled:opacity-40" :disabled="!dirty(row)" @click="save(row)">Guardar</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <aside class="space-y-2" aria-label="Acciones del evento">
                <Link :href="route('admin.tickets.index', event.id)" :class="action"><BoIcon name="ticket" :size="18" /> Boletos emitidos</Link>
                <Link :href="route('admin.events.courtesies.selection', event.id)" :class="action"><BoIcon name="plus" :size="18" /> Crear cortesías</Link>
                <Link :href="route('admin.refunds.index', event.id)" :class="action"><BoIcon name="cash" :size="18" /> Reembolsos</Link>
                <a :href="route('admin.tickets.export', event.id)" :class="action"><BoIcon name="download" :size="18" /> Excel de boletos</a>
                <a :href="route('admin.events.buyers', event.id)" target="_blank" rel="noopener" :class="action"><BoIcon name="mail" :size="18" /> Lista de compradores</a>
            </aside>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { reactive, watch } from "vue";
import { Link, router } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import EventThumb from "@/components/BoxOffice/EventThumb.vue";

const props = defineProps({
    event: { type: Object, required: true },
    functions: { type: Array, required: true },
    localities: { type: Array, required: true },
});

const cell = "block w-full rounded-md border-bo-line py-1.5 text-sm focus:border-bo-green focus:ring-bo-green";
const action = "flex items-center gap-3 rounded-lg border border-bo-line bg-bo-card px-4 py-3 text-sm font-medium hover:border-bo-green";

const rows = reactive(props.localities.map((locality) => ({ ...locality })));

watch(
    () => props.localities,
    (localities) => rows.splice(0, rows.length, ...localities.map((locality) => ({ ...locality }))),
);

function dirty(row) {
    const original = props.localities.find((locality) => locality.id === row.id);
    return ["name", "price", "commission", "webCommission", "capacity"].some((key) => String(original[key] ?? "") !== String(row[key] ?? ""));
}

function save(row) {
    router.put(
        route("admin.events.localities.update", { evento: props.event.id, localidad: row.id }),
        { name: row.name, price: row.price, commission: row.commission, webCommission: row.webCommission, capacity: row.capacity === "" ? null : row.capacity },
        { preserveScroll: true },
    );
}
</script>
