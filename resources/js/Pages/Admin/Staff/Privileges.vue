<template>
    <BackOfficeLayout :title="`Privilegios de ${user.name}`">
        <Link :href="route('admin.staff.index', type.section)" class="mb-5 inline-flex items-center gap-1 text-sm font-medium text-bo-slate hover:text-bo-ink">
            <BoIcon name="chevronLeft" :size="16" />
            {{ type.plural }}
        </Link>

        <p v-if="!canEdit" class="mb-5 rounded-lg border border-bo-line bg-bo-card px-4 py-3 text-sm text-bo-slate">
            Solo un administrador puede cambiar privilegios, y nadie puede cambiar los propios.
        </p>

        <form class="space-y-4" @submit.prevent="save">
            <fieldset v-for="category in categories" :key="category.name" class="rounded-xl border border-bo-line bg-bo-card p-5" :disabled="!canEdit">
                <legend class="px-1 font-semibold">{{ category.name }}</legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <label v-for="privilege in category.privileges" :key="privilege.id" class="flex items-start gap-2.5 text-sm">
                        <input v-model="form.privilegios" type="checkbox" :value="privilege.id" class="mt-0.5 rounded border-bo-line text-bo-green focus:ring-bo-green" />
                        <span>
                            {{ privilege.label }}
                            <span v-if="privilege.menu" class="ml-1 text-xs text-bo-muted">(menú)</span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <div v-if="canEdit" class="sticky bottom-0 flex items-center gap-4 border-t border-bo-line bg-bo-paper/95 py-4 backdrop-blur">
                <button type="submit" class="rounded-lg bg-bo-green px-5 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50" :disabled="form.processing || !form.isDirty">
                    Guardar privilegios
                </button>
                <span class="tabular text-sm text-bo-slate">{{ form.privilegios.length }} seleccionados</span>
            </div>
        </form>
    </BackOfficeLayout>
</template>

<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";

const props = defineProps({
    type: { type: Object, required: true },
    user: { type: Object, required: true },
    categories: { type: Array, required: true },
    granted: { type: Array, required: true },
    canEdit: { type: Boolean, default: false },
});

const form = useForm({ privilegios: [...props.granted] });

function save() {
    form.put(route("admin.staff.privileges.update", { tipo: props.type.section, usuario: props.user.id }), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}
</script>
