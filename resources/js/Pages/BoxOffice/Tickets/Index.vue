<template>
    <BoxOfficeLayout :title="title">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm text-bo-slate">
                <span class="tabular font-semibold text-bo-ink">{{ filtered.length }}</span>
                {{ filtered.length === 1 ? "evento disponible" : "eventos disponibles" }}
            </p>
            <label class="relative block w-full sm:w-80">
                <span class="sr-only">Buscar evento</span>
                <BoIcon name="search" :size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-bo-muted" />
                <input
                    v-model.trim="search"
                    type="search"
                    placeholder="Evento, recinto o ciudad"
                    class="block w-full rounded-lg border-bo-line bg-bo-card py-2.5 pr-3 pl-10 text-[15px] focus:border-bo-green focus:ring-bo-green"
                />
            </label>
        </div>

        <EmptyState
            v-if="events.length === 0"
            title="No tienes eventos disponibles"
            text="Solo aparecen los eventos que te asignaron y que todavía no terminan."
        />
        <EmptyState v-else-if="filtered.length === 0" title="Sin resultados" :text="`Ningún evento coincide con “${search}”.`" />

        <ul v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            <li v-for="event in filtered" :key="event.id">
                <Link
                    :href="route(actionRoute, event.id)"
                    class="group flex h-full flex-col overflow-hidden rounded-xl border border-bo-line bg-bo-card hover:border-bo-green"
                >
                    <EventThumb :src="event.image" :alt="event.name" class="rounded-none" />
                    <div class="flex flex-1 flex-col p-4">
                        <p class="font-semibold leading-snug">{{ event.name }}</p>
                        <p class="mt-2 flex items-start gap-1.5 text-sm text-bo-slate">
                            <BoIcon name="venue" :size="16" class="mt-0.5 shrink-0" />
                            {{ event.venue }}, {{ event.city }}
                        </p>
                        <p class="mt-1 flex items-center gap-1.5 text-sm text-bo-slate">
                            <BoIcon name="calendar" :size="16" class="shrink-0" />
                            <span class="tabular">{{ event.date }}</span>
                        </p>
                        <span class="mt-auto pt-4 text-sm font-semibold text-bo-green group-hover:text-bo-green-dark">{{ actionLabel }}</span>
                    </div>
                </Link>
            </li>
        </ul>
    </BoxOfficeLayout>
</template>

<script setup>
import { computed, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import BoxOfficeLayout from "@/Layouts/BoxOfficeLayout.vue";
import BoIcon from "@/Components/BoxOffice/BoIcon.vue";
import EventThumb from "@/Components/BoxOffice/EventThumb.vue";
import EmptyState from "@/Components/BoxOffice/EmptyState.vue";

const props = defineProps({
    events: { type: Array, required: true },
    title: { type: String, default: "Vender boletos" },
    actionLabel: { type: String, default: "Vender boletos" },
    actionRoute: { type: String, default: "boxoffice.tickets.checkout.start" },
});

const search = ref("");

const normalize = (value) =>
    String(value ?? "")
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "")
        .toLowerCase();

const filtered = computed(() => {
    const term = normalize(search.value);

    if (!term) {
        return props.events;
    }

    return props.events.filter((event) => normalize(`${event.name} ${event.venue} ${event.city} ${event.state}`).includes(term));
});
</script>
