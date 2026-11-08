<template>
    <BackOfficeLayout title="Recintos">
        <template #actions>
            <Link :href="route('admin.venues.create')" class="inline-flex items-center gap-2 rounded-lg bg-bo-green px-3 py-2 text-sm font-semibold text-white hover:bg-bo-green-dark">
                <BoIcon name="plus" :size="16" />
                Nuevo recinto
            </Link>
        </template>
        <form class="relative mb-5 w-full sm:w-80" @submit.prevent="router.get(route('admin.venues.index'), { buscar: search || undefined }, { preserveState: true, replace: true })">
            <label for="search" class="sr-only">Buscar recinto</label>
            <BoIcon name="search" :size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-bo-muted" />
            <input id="search" v-model.trim="search" type="search" placeholder="Nombre del recinto" class="block w-full rounded-lg border-bo-line bg-bo-card py-2.5 pl-10 focus:border-bo-green focus:ring-bo-green" />
        </form>
        <EmptyState v-if="venues.data.length === 0" title="Sin recintos" text="Ningún recinto coincide con la búsqueda." />
        <ul v-else class="divide-y divide-bo-line rounded-xl border border-bo-line bg-bo-card">
            <li v-for="venue in venues.data" :key="venue.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                <div class="min-w-0">
                    <p class="font-medium">{{ venue.name }}</p>
                    <p class="text-sm text-bo-slate">{{ venue.address }} — {{ venue.city }}</p>
                </div>
                <div class="flex gap-1 text-sm">
                    <Link :href="route('admin.venues.formats', venue.id)" class="rounded-lg px-2.5 py-1.5 font-medium hover:bg-bo-paper">Formatos</Link>
                    <Link :href="route('admin.venues.edit', venue.id)" class="rounded-lg px-2.5 py-1.5 font-medium hover:bg-bo-paper">Editar</Link>
                </div>
            </li>
        </ul>
        <Pagination :page="venues" />
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
    venues: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const search = ref(props.filters.buscar ?? "");
</script>
