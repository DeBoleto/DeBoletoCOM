<template>
    <BackOfficeLayout :title="`Formatos — ${venue.name}`">
        <Link :href="route('admin.venues.index')" class="mb-5 inline-flex items-center gap-1 text-sm font-medium text-bo-slate hover:text-bo-ink">
            <BoIcon name="chevronLeft" :size="16" />
            Recintos
        </Link>

        <div class="grid gap-6 xl:grid-cols-[300px_1fr]">
            <nav aria-label="Formatos del recinto">
                <ul class="space-y-1">
                    <li v-for="format in formats" :key="format.id">
                        <button
                            type="button"
                            class="w-full rounded-lg border px-4 py-2.5 text-left text-sm"
                            :class="current?.id === format.id ? 'border-bo-green bg-bo-green-soft' : 'border-bo-line bg-bo-card hover:border-bo-slate'"
                            @click="edit(format)"
                        >
                            <span class="block font-medium">{{ format.name }}</span>
                            <span class="tabular text-xs text-bo-slate">{{ format.localities }} localidades</span>
                        </button>
                    </li>
                </ul>
                <button type="button" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-bo-green hover:text-bo-green-dark" @click="edit(null)">
                    <BoIcon name="plus" :size="16" />
                    Nuevo formato
                </button>
            </nav>

            <form class="space-y-4 rounded-xl border border-bo-line bg-bo-card p-6" @submit.prevent="save">
                <label class="block text-sm font-medium">
                    <span class="mb-1.5 block">Nombre del formato</span>
                    <input v-model="form.nombre" type="text" required class="block w-full max-w-md rounded-lg border-bo-line py-2.5 text-sm focus:border-bo-green focus:ring-bo-green" />
                    <span v-if="form.errors.nombre" class="mt-1 block text-bo-sold">{{ form.errors.nombre }}</span>
                </label>
                <StageXmlEditor v-model="form.xml" :error="form.errors.xml" />
                <button type="submit" class="rounded-lg bg-bo-green px-5 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50" :disabled="form.processing || !form.nombre || !form.xml">
                    {{ current ? "Guardar formato" : "Crear formato" }}
                </button>
            </form>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import StageXmlEditor from "@/components/BoxOffice/StageXmlEditor.vue";

const props = defineProps({
    venue: { type: Object, required: true },
    formats: { type: Array, required: true },
});

const current = ref(props.formats[0] ?? null);
const form = useForm({ nombre: current.value?.name ?? "", xml: current.value?.xml ?? "" });

function edit(format) {
    current.value = format;
    form.defaults({ nombre: format?.name ?? "", xml: format?.xml ?? "" });
    form.reset();
    form.clearErrors();
}

function save() {
    if (current.value) {
        form.put(route("admin.venues.formats.update", { escenario: props.venue.id, formato: current.value.id }), { preserveScroll: true });
    } else {
        form.post(route("admin.venues.formats.store", props.venue.id), { preserveScroll: true });
    }
}
</script>
