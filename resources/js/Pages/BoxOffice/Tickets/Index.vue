<template>
    <BackOfficeLayout :title="title">
        <template v-if="cashRegister" #actions>
            <Link
                :href="route('boxoffice.cash.show')"
                class="inline-flex items-center gap-2 rounded-lg border border-bo-line bg-bo-card px-3 py-2 text-sm font-semibold hover:border-bo-slate"
            >
                <BoIcon name="cash" :size="16" />
                <span class="hidden sm:inline">Cerrar caja</span>
                <span class="sm:hidden">Caja</span>
            </Link>
        </template>

        <p v-if="cashRegister" class="mb-4 inline-flex items-center gap-2 rounded-full bg-bo-green-soft px-3 py-1 text-xs font-semibold text-bo-green-dark">
            <BoIcon name="lock" :size="14" />
            Caja{{ cashRegister.name ? ` ${cashRegister.name}` : "" }} abierta desde {{ cashRegister.openedAt }}
        </p>

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

        <ul v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <li v-for="event in filtered" :key="event.id">
                <Link
                    :href="route(actionRoute, event.id)"
                    class="group flex h-full flex-col overflow-hidden rounded-2xl border border-bo-line bg-bo-card shadow-[0_1px_2px_rgba(22,32,43,0.05)] transition duration-200 hover:-translate-y-0.5 hover:border-bo-green/60 hover:shadow-[0_14px_32px_-12px_rgba(22,32,43,0.28)] focus-visible:-translate-y-0.5"
                >
                    <div class="relative">
                        <EventThumb :src="event.image" :alt="event.name" class="rounded-none transition-transform duration-500 group-hover:scale-[1.03]" />
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-bo-ink/70 via-bo-ink/5 to-transparent" />
                        <span class="tabular absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-bo-ink shadow-sm backdrop-blur">
                            <BoIcon name="calendar" :size="14" class="text-bo-green" />
                            {{ event.date }}
                        </span>
                        <span
                            v-if="event.promotion"
                            class="absolute top-3 right-3 inline-flex max-w-[55%] items-center gap-1 rounded-full bg-fuchsia-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm"
                            :title="promotionText(event.promotion)"
                        >
                            <BoIcon name="gift" :size="14" class="shrink-0" />
                            <span class="truncate">{{ promotionText(event.promotion) }}</span>
                        </span>
                        <p class="absolute right-3 bottom-3 left-3 line-clamp-2 text-[15px] leading-snug font-semibold text-white drop-shadow">
                            {{ event.name }}
                        </p>
                    </div>
                    <div class="flex flex-1 flex-col gap-3 p-4">
                        <p class="flex items-start gap-2 text-sm text-bo-slate">
                            <BoIcon name="venue" :size="16" class="mt-0.5 shrink-0 text-bo-muted" />
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-bo-ink">{{ event.venue }}</span>
                                <span class="block truncate">{{ [event.city, event.state].filter(Boolean).join(", ") }}</span>
                            </span>
                        </p>
                        <span
                            class="mt-auto inline-flex items-center justify-between rounded-lg bg-bo-green-soft px-3.5 py-2.5 text-sm font-semibold text-bo-green-dark transition-colors group-hover:bg-bo-green group-hover:text-white"
                        >
                            {{ actionLabel }}
                            <BoIcon name="chevronRight" :size="18" class="transition-transform group-hover:translate-x-0.5" />
                        </span>
                    </div>
                </Link>
            </li>
        </ul>
    </BackOfficeLayout>
</template>

<script setup>
import { computed, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import EventThumb from "@/components/BoxOffice/EventThumb.vue";
import EmptyState from "@/components/BoxOffice/EmptyState.vue";
import { promotionText } from "@/components/BoxOffice/format.js";

const props = defineProps({
    events: { type: Array, required: true },
    title: { type: String, default: "Vender boletos" },
    actionLabel: { type: String, default: "Vender boletos" },
    actionRoute: { type: String, default: "boxoffice.tickets.checkout.start" },
    cashRegister: { type: Object, default: null },
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
