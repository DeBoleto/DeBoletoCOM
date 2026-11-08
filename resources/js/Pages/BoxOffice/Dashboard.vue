<template>
    <BoxOfficeLayout title="Inicio">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold">Hola, {{ firstName }}</h2>
                <p class="mt-1 text-sm text-bo-slate">Ventas y pagos de tus eventos.</p>
            </div>
            <div role="tablist" aria-label="Eventos" class="inline-flex rounded-lg border border-bo-line bg-bo-card p-1 text-sm">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    role="tab"
                    type="button"
                    class="rounded-md px-4 py-1.5 font-medium"
                    :class="activeTab === tab.key ? 'bg-bo-ink text-white' : 'text-bo-slate hover:text-bo-ink'"
                    :aria-selected="activeTab === tab.key"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <section v-if="activeTab === 'active'" aria-label="Eventos activos">
            <EmptyState v-if="events.length === 0" title="No tienes eventos activos" text="Cuando te asignen un evento aparecerá aquí con su resumen de ventas." />

            <ul v-else class="divide-y divide-bo-line overflow-hidden rounded-xl border border-bo-line bg-bo-card">
                <li v-for="event in events" :key="event.id" class="grid gap-4 p-4 sm:grid-cols-[1fr_auto] sm:items-center sm:p-5">
                    <div class="flex min-w-0 gap-4">
                        <EventThumb :src="event.image" :alt="event.name" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ event.name }}</p>
                            <p class="mt-0.5 truncate text-sm text-bo-slate">{{ event.venue }}</p>
                            <p class="text-sm text-bo-slate">
                                {{ event.city }}, {{ event.state }}
                                <span v-if="event.date" class="tabular text-bo-muted">— {{ formatDate(event.date) }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-8 gap-y-3 sm:justify-end">
                        <Deferred data="balances">
                            <template #fallback>
                                <div class="flex gap-8" aria-busy="true">
                                    <span v-for="n in 3" :key="n" class="h-9 w-20 animate-pulse rounded bg-bo-paper" />
                                </div>
                            </template>
                            <dl class="tabular flex gap-8 text-right text-sm">
                                <div>
                                    <dt class="text-bo-muted">Vendido</dt>
                                    <dd class="font-semibold">{{ money(balanceOf(event.id).sold) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-bo-muted">Pagado</dt>
                                    <dd class="font-semibold">{{ money(balanceOf(event.id).paid) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-bo-muted">Disponible</dt>
                                    <dd class="font-semibold text-bo-green-dark">{{ money(balanceOf(event.id).available) }}</dd>
                                </div>
                            </dl>
                        </Deferred>

                        <div class="flex gap-2">
                            <a
                                v-if="hasRoute('boxoffice.reports.show')"
                                :href="route('boxoffice.reports.show', { tipo: 'general', evento: event.id })"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-bo-line px-3 py-2 text-sm font-medium hover:border-bo-slate"
                            >
                                <BoIcon name="download" :size="16" />
                                Reporte general
                            </a>
                            <a
                                v-if="canSeePayments && hasRoute('boxoffice.reports.show')"
                                :href="route('boxoffice.reports.show', { tipo: 'pagos', evento: event.id })"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-bo-line px-3 py-2 text-sm font-medium hover:border-bo-slate"
                            >
                                <BoIcon name="file" :size="16" />
                                Pagos
                            </a>
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <section v-else aria-label="Historial de eventos">
            <Deferred data="history">
                <template #fallback>
                    <div class="h-40 animate-pulse rounded-xl bg-bo-card" aria-busy="true" />
                </template>
                <EmptyState v-if="history.length === 0" title="Sin eventos terminados" text="Los eventos aparecen aquí cuando concluye su última fecha." />
                <ul v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <li v-for="event in history" :key="event.id" class="flex gap-4 rounded-xl border border-bo-line bg-bo-card p-4">
                        <EventThumb :src="event.image" :alt="event.name" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ event.name }}</p>
                            <p class="truncate text-sm text-bo-slate">{{ event.venue }}</p>
                            <p class="tabular text-sm text-bo-muted">{{ event.date ? formatDate(event.date) : "" }}</p>
                        </div>
                    </li>
                </ul>
            </Deferred>
        </section>
    </BoxOfficeLayout>
</template>

<script setup>
import { computed, ref } from "vue";
import { Deferred, usePage } from "@inertiajs/vue3";
import BoxOfficeLayout from "@/Layouts/BoxOfficeLayout.vue";
import BoIcon from "@/Components/BoxOffice/BoIcon.vue";
import EventThumb from "@/Components/BoxOffice/EventThumb.vue";
import EmptyState from "@/Components/BoxOffice/EmptyState.vue";
import { money } from "@/Components/BoxOffice/format.js";

const props = defineProps({
    events: { type: Array, required: true },
    history: { type: Array, default: () => [] },
    balances: { type: Object, default: () => ({}) },
    canSeePayments: { type: Boolean, default: false },
});

const tabs = [
    { key: "active", label: "Activos" },
    { key: "history", label: "Historial" },
];

const activeTab = ref("active");
const page = usePage();

const firstName = computed(() => (page.props.boxoffice?.user?.name ?? "").split(" ")[0]);

const dateFormat = new Intl.DateTimeFormat("es-MX", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" });

function formatDate(value) {
    return dateFormat.format(new Date(`${value}T00:00:00Z`));
}

function balanceOf(eventId) {
    return props.balances?.[eventId] ?? { sold: 0, paid: 0, available: 0 };
}

function hasRoute(name) {
    return route().has(name);
}
</script>
