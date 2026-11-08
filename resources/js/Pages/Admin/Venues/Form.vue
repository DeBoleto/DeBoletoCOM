<template>
    <BackOfficeLayout :title="venue ? `Editar ${venue.escenario}` : 'Nuevo recinto'">
        <form class="max-w-5xl space-y-6" novalidate @submit.prevent="submit">
            <section class="grid gap-5 rounded-xl border border-bo-line bg-bo-card p-6 sm:grid-cols-2">
                <label class="text-sm font-medium sm:col-span-2">
                    <span class="mb-1.5 block">Nombre</span>
                    <input v-model="form.escenario" type="text" required :class="input" />
                    <span v-if="form.errors.escenario" class="mt-1 block text-bo-sold">{{ form.errors.escenario }}</span>
                </label>
                <label class="text-sm font-medium sm:col-span-2">
                    <span class="mb-1.5 block">Dirección</span>
                    <input v-model="form.direccion" type="text" required :class="input" />
                    <span v-if="form.errors.direccion" class="mt-1 block text-bo-sold">{{ form.errors.direccion }}</span>
                </label>
                <label class="text-sm font-medium">
                    <span class="mb-1.5 block">Ciudad</span>
                    <select v-model="form.pk_ciudad" required :class="input">
                        <option :value="null" disabled>Elige</option>
                        <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm font-medium"><span class="mb-1.5 block">Latitud</span><input v-model="form.latitud" type="number" step="any" :class="input" /></label>
                    <label class="text-sm font-medium"><span class="mb-1.5 block">Longitud</span><input v-model="form.longitud" type="number" step="any" :class="input" /></label>
                </div>
                <label class="text-sm font-medium sm:col-span-2">
                    <span class="mb-1.5 block">Imagen {{ venue ? "(déjala vacía para conservar la actual)" : "" }}</span>
                    <input type="file" accept=".jpg,.jpeg,.png" class="block w-full text-sm" @input="form.imagen = $event.target.files[0]" />
                    <span v-if="form.errors.imagen" class="mt-1 block text-bo-sold">{{ form.errors.imagen }}</span>
                </label>
            </section>

            <section class="rounded-xl border border-bo-line bg-bo-card p-6">
                <StageXmlEditor v-model="form.xml_escenario" label="XML general del recinto (opcional)" :error="form.errors.xml_escenario" />
            </section>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-bo-green px-5 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50" :disabled="form.processing">
                    {{ venue ? "Guardar cambios" : "Crear recinto" }}
                </button>
                <Link :href="route('admin.venues.index')" class="rounded-lg px-4 py-2.5 font-medium text-bo-slate hover:text-bo-ink">Cancelar</Link>
            </div>
        </form>
    </BackOfficeLayout>
</template>

<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import StageXmlEditor from "@/components/BoxOffice/StageXmlEditor.vue";

const props = defineProps({
    venue: { type: Object, default: null },
    cities: { type: Array, required: true },
});

const input = "block w-full rounded-lg border-bo-line py-2.5 text-sm focus:border-bo-green focus:ring-bo-green";

const form = useForm({
    escenario: props.venue?.escenario ?? "",
    direccion: props.venue?.direccion ?? "",
    pk_ciudad: props.venue?.pk_ciudad ?? null,
    latitud: props.venue?.latitud ?? "",
    longitud: props.venue?.longitud ?? "",
    xml_escenario: props.venue?.xml_escenario ?? "",
    imagen: null,
});

function submit() {
    const url = props.venue ? route("admin.venues.update", props.venue.id) : route("admin.venues.store");
    form.post(url, { forceFormData: true });
}
</script>
