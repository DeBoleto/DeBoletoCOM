<template>
    <BackOfficeLayout title="Nuevo evento">
        <form class="max-w-3xl space-y-6" novalidate @submit.prevent="submit">
            <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-lg border border-bo-sold/30 bg-bo-sold-soft px-4 py-3 text-sm text-bo-sold">
                <ul class="list-disc pl-5"><li v-for="(message, key) in form.errors" :key="key">{{ message }}</li></ul>
            </div>

            <section class="grid gap-5 rounded-xl border border-bo-line bg-bo-card p-6 sm:grid-cols-2">
                <h2 class="font-semibold sm:col-span-2">Datos generales</h2>
                <label class="text-sm font-medium sm:col-span-2">
                    <span class="mb-1.5 block">Nombre del evento</span>
                    <input v-model="form.evento" type="text" required :class="input" />
                </label>
                <label class="text-sm font-medium">
                    <span class="mb-1.5 block">Empresario</span>
                    <select v-model="form.pk_usuario" required :class="input">
                        <option :value="null" disabled>Elige</option>
                        <option v-for="promoter in promoters" :key="promoter.id" :value="promoter.id">{{ promoter.name }}</option>
                    </select>
                </label>
                <label class="text-sm font-medium">
                    <span class="mb-1.5 block">Recinto</span>
                    <select v-model="form.pk_escenario" required :class="input" @change="loadFormats">
                        <option :value="null" disabled>Elige</option>
                        <option v-for="venue in venues" :key="venue.id" :value="venue.id">{{ venue.name }}{{ venue.city ? ` (${venue.city})` : "" }}</option>
                    </select>
                </label>
                <label class="text-sm font-medium sm:col-span-2">
                    <span class="mb-1.5 block">Formato del recinto</span>
                    <select v-model="form.pk_escenario_formato" :disabled="!formats.length" required :class="input" @change="resetPrices">
                        <option :value="null" disabled>{{ form.pk_escenario ? "Elige un formato" : "Elige primero el recinto" }}</option>
                        <option v-for="format in formats" :key="format.id" :value="format.id">{{ format.name }} ({{ format.localities.length }} localidades)</option>
                    </select>
                </label>
                <label class="text-sm font-medium sm:col-span-2">
                    <span class="mb-1.5 block">Descripción</span>
                    <textarea v-model="form.descripcion" rows="4" :class="input" />
                </label>
                <label class="text-sm font-medium sm:col-span-2">
                    <span class="mb-1.5 block">Comentarios para taquilla (opcional)</span>
                    <textarea v-model="form.comentarios" rows="2" :class="input" />
                </label>
                <label class="text-sm font-medium sm:col-span-2">
                    <span class="mb-1.5 block">Imagen (JPG o PNG, máx. 5 MB)</span>
                    <input type="file" accept=".jpg,.jpeg,.png" required class="block w-full text-sm" @input="form.imagen = $event.target.files[0]" />
                </label>
            </section>

            <section v-if="selectedFormat" class="rounded-xl border border-bo-line bg-bo-card p-6">
                <h2 class="font-semibold">Precios por localidad</h2>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                    <li v-for="locality in selectedFormat.localities" :key="locality.id">
                        <label class="text-sm font-medium">
                            <span class="mb-1.5 block">{{ locality.name }}</span>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-bo-muted">$</span>
                                <input v-model="form.precios[locality.id]" type="number" min="0" step="0.01" required :class="[input, 'tabular pl-7']" />
                            </div>
                        </label>
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-bo-line bg-bo-card p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Funciones</h2>
                    <button type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-bo-green hover:text-bo-green-dark" @click="form.funciones.push({ date: '', time: '' })">
                        <BoIcon name="plus" :size="16" />
                        Agregar función
                    </button>
                </div>
                <ul class="mt-3 space-y-2">
                    <li v-for="(item, index) in form.funciones" :key="index" class="flex items-center gap-3">
                        <input v-model="item.date" type="date" required aria-label="Fecha" :class="[input, 'max-w-48']" />
                        <input v-model="item.time" type="time" required aria-label="Hora" :class="[input, 'max-w-36']" />
                        <button v-if="form.funciones.length > 1" type="button" class="rounded p-1.5 text-bo-slate hover:text-bo-sold" aria-label="Quitar función" @click="form.funciones.splice(index, 1)">
                            <BoIcon name="close" :size="18" />
                        </button>
                    </li>
                </ul>
            </section>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-bo-green px-5 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50" :disabled="form.processing || !selectedFormat">
                    {{ form.processing ? "Creando…" : "Crear evento" }}
                </button>
                <Link :href="route('admin.events.index')" class="rounded-lg px-4 py-2.5 font-medium text-bo-slate hover:text-bo-ink">Cancelar</Link>
            </div>
            <p class="text-sm text-bo-slate">El evento se crea inactivo. Revisa localidades y comisiones antes de activarlo.</p>
        </form>
    </BackOfficeLayout>
</template>

<script setup>
import { computed, ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";

defineProps({
    promoters: { type: Array, required: true },
    venues: { type: Array, required: true },
});

const input = "block w-full rounded-lg border-bo-line py-2.5 text-sm focus:border-bo-green focus:ring-bo-green";
const formats = ref([]);

const form = useForm({
    evento: "",
    pk_usuario: null,
    pk_escenario: null,
    pk_escenario_formato: null,
    descripcion: "",
    comentarios: "",
    imagen: null,
    precios: {},
    funciones: [{ date: "", time: "" }],
});

const selectedFormat = computed(() => formats.value.find((format) => format.id === form.pk_escenario_formato) ?? null);

async function loadFormats() {
    form.pk_escenario_formato = null;
    formats.value = [];
    const response = await fetch(route("admin.events.formats", form.pk_escenario), { headers: { Accept: "application/json" }, credentials: "same-origin" });
    if (response.ok) formats.value = await response.json();
}

function resetPrices() {
    form.precios = Object.fromEntries((selectedFormat.value?.localities ?? []).map((locality) => [locality.id, 0]));
}

function submit() {
    form
        .transform((data) => ({ ...data, funciones: data.funciones.filter((item) => item.date && item.time).map((item) => `${item.date} ${item.time}`) }))
        .post(route("admin.events.store"), { forceFormData: true });
}
</script>
