<template>
    <BackOfficeLayout :title="user ? `Editar ${type.singular}` : `Nuevo ${type.singular}`">
        <form class="max-w-2xl space-y-6" novalidate @submit.prevent="submit">
            <section class="grid gap-5 rounded-xl border border-bo-line bg-bo-card p-6 sm:grid-cols-2">
                <Field id="nombre" label="Nombre" :error="form.errors.nombre" class="sm:col-span-2">
                    <input id="nombre" v-model="form.nombre" type="text" autocomplete="given-name" required :class="input" />
                </Field>
                <Field id="apellido_paterno" label="Apellido paterno" :error="form.errors.apellido_paterno">
                    <input id="apellido_paterno" v-model="form.apellido_paterno" type="text" required :class="input" />
                </Field>
                <Field id="apellido_materno" label="Apellido materno (opcional)" :error="form.errors.apellido_materno">
                    <input id="apellido_materno" v-model="form.apellido_materno" type="text" :class="input" />
                </Field>
                <Field id="correo" label="Correo electrónico" :error="form.errors.correo">
                    <input id="correo" v-model="form.correo" type="email" autocomplete="off" required :class="input" />
                </Field>
                <Field id="telefono" label="Teléfono (opcional)" :error="form.errors.telefono">
                    <input id="telefono" v-model="form.telefono" type="tel" inputmode="numeric" maxlength="10" :class="input" />
                </Field>
                <Field id="nacimiento_fecha" label="Fecha de nacimiento (opcional)" :error="form.errors.nacimiento_fecha">
                    <input id="nacimiento_fecha" v-model="form.nacimiento_fecha" type="date" :class="input" />
                </Field>
            </section>

            <section class="grid gap-5 rounded-xl border border-bo-line bg-bo-card p-6 sm:grid-cols-2">
                <p class="text-sm text-bo-slate sm:col-span-2">
                    {{ user ? "Deja la contraseña vacía para conservar la actual." : "Mínimo 8 caracteres, con letras y números." }}
                </p>
                <Field id="password" label="Contraseña" :error="form.errors.password">
                    <input id="password" v-model="form.password" type="password" autocomplete="new-password" :class="input" />
                </Field>
                <Field id="password_confirmation" label="Confirmar contraseña">
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" :class="input" />
                </Field>
            </section>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-bo-green px-5 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50" :disabled="form.processing">
                    {{ user ? "Guardar cambios" : `Crear ${type.singular}` }}
                </button>
                <Link :href="route('admin.staff.index', type.section)" class="rounded-lg px-4 py-2.5 font-medium text-bo-slate hover:text-bo-ink">Cancelar</Link>
            </div>
        </form>
    </BackOfficeLayout>
</template>

<script setup>
import { h } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";

const props = defineProps({
    type: { type: Object, required: true },
    user: { type: Object, default: null },
});

const input = "block w-full rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green";

const Field = (fieldProps, { slots }) =>
    h("div", { class: fieldProps.class }, [
        h("label", { for: fieldProps.id, class: "mb-1.5 block text-sm font-medium" }, fieldProps.label),
        slots.default?.(),
        fieldProps.error ? h("p", { class: "mt-1.5 text-sm text-bo-sold" }, fieldProps.error) : null,
    ]);
Field.props = ["id", "label", "error", "class"];

const form = useForm({
    nombre: props.user?.nombre ?? "",
    apellido_paterno: props.user?.apellido_paterno ?? "",
    apellido_materno: props.user?.apellido_materno ?? "",
    correo: props.user?.correo ?? "",
    telefono: props.user?.telefono ?? "",
    nacimiento_fecha: props.user?.nacimiento_fecha ?? "",
    password: "",
    password_confirmation: "",
});

function submit() {
    if (props.user) {
        form.put(route("admin.staff.update", { tipo: props.type.section, usuario: props.user.id }));
    } else {
        form.post(route("admin.staff.store", props.type.section));
    }
}
</script>
