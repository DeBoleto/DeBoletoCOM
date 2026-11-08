<template>
    <BoxOfficeLayout title="Aforo por localidad">
        <label class="mb-6 block max-w-md text-sm font-medium">
            <span class="mb-1.5 block">Evento</span>
            <select v-model="eventId" class="block w-full rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green" @change="load">
                <option :value="null" disabled>Elige un evento</option>
                <option v-for="event in events" :key="event.id" :value="event.id">{{ event.name }}</option>
            </select>
        </label>

        <p v-if="error" class="text-sm text-bo-sold" role="alert">{{ error }}</p>
        <div v-if="loading" class="h-64 animate-pulse rounded-xl bg-bo-card" aria-busy="true" />

        <div v-else-if="data" class="grid gap-6 xl:grid-cols-[1fr_420px]">
            <section v-if="coloredSections.length" class="rounded-xl border border-bo-line bg-bo-card p-4" aria-label="Mapa de ocupación">
                <ZoneCanvas :sections="coloredSections" :canvas-size="data.stage.canvas" />
                <ul class="mt-3 flex flex-wrap gap-4 text-sm text-bo-slate">
                    <li v-for="item in scale" :key="item.label" class="flex items-center gap-2">
                        <span class="size-3 rounded-sm" :style="{ background: item.color }" />
                        {{ item.label }}
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-bo-line bg-bo-card p-5" aria-label="Ocupación por localidad">
                <ul class="space-y-4">
                    <li v-for="locality in data.localities" :key="locality.id">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium">{{ locality.name }}</span>
                            <span class="tabular text-bo-slate">
                                {{ locality.sold }}<template v-if="locality.capacity"> / {{ locality.capacity }}</template>
                                <strong v-if="locality.percentage !== null" class="ml-2 text-bo-ink">{{ locality.percentage }} %</strong>
                            </span>
                        </div>
                        <div v-if="locality.percentage !== null" class="mt-1.5 h-2 overflow-hidden rounded-full bg-bo-paper">
                            <div class="h-full rounded-full" :style="{ width: `${Math.min(100, locality.percentage)}%`, background: colorFor(locality.percentage) }" />
                        </div>
                        <p v-else class="mt-1 text-xs text-bo-muted">Sin aforo configurado.</p>
                    </li>
                </ul>
            </section>
        </div>
    </BoxOfficeLayout>
</template>

<script setup>
import { computed, ref } from "vue";
import BoxOfficeLayout from "@/Layouts/BoxOfficeLayout.vue";
import ZoneCanvas from "@/Components/seat-map/ZoneCanvas.vue";

defineProps({ events: { type: Array, required: true } });

const scale = [
    { label: "Menos de 50 %", color: "#86c9b1" },
    { label: "50 a 80 %", color: "#1f8f6b" },
    { label: "80 a 95 %", color: "#d9822b" },
    { label: "Más de 95 %", color: "#c9423f" },
];

const eventId = ref(null);
const data = ref(null);
const loading = ref(false);
const error = ref("");

function colorFor(percentage) {
    if (percentage >= 95) return scale[3].color;
    if (percentage >= 80) return scale[2].color;
    if (percentage >= 50) return scale[1].color;
    return scale[0].color;
}

const coloredSections = computed(() => {
    if (!data.value?.stage) return [];
    const byLocality = Object.fromEntries(data.value.localities.map((locality) => [String(locality.id), locality.percentage]));

    return data.value.stage.sections.map((section) => {
        const percentage = byLocality[String(section.localityId)];
        return percentage === null || percentage === undefined ? section : { ...section, areaColor: colorFor(percentage), color: colorFor(percentage) };
    });
});

async function load() {
    loading.value = true;
    error.value = "";
    try {
        const response = await fetch(route("boxoffice.capacity.data", eventId.value), { headers: { Accept: "application/json" }, credentials: "same-origin" });
        if (!response.ok) throw new Error(String(response.status));
        data.value = await response.json();
    } catch {
        error.value = "No pudimos cargar el aforo del evento.";
    } finally {
        loading.value = false;
    }
}
</script>
