<template>
    <BackOfficeLayout :title="`Eventos de ${user.name}`">
        <Link :href="route('admin.staff.index', type.section)" class="mb-5 inline-flex items-center gap-1 text-sm font-medium text-bo-slate hover:text-bo-ink">
            <BoIcon name="chevronLeft" :size="16" />
            {{ type.plural }}
        </Link>

        <form class="mb-6 flex max-w-2xl flex-wrap items-end gap-3 rounded-xl border border-bo-line bg-bo-card p-5" @submit.prevent="assign">
            <label class="min-w-64 flex-1 text-sm font-medium">
                <span class="mb-1.5 block">Asignar evento publicado</span>
                <select v-model="form.evento" class="block w-full rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green">
                    <option :value="null" disabled>Elige un evento</option>
                    <option v-for="event in available" :key="event.id" :value="event.id">{{ event.name }}</option>
                </select>
            </label>
            <button type="submit" class="rounded-lg bg-bo-green px-4 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50" :disabled="!form.evento || form.processing">Asignar</button>
            <p v-if="form.errors.evento" class="w-full text-sm text-bo-sold">{{ form.errors.evento }}</p>
        </form>

        <EmptyState v-if="assigned.length === 0" title="Sin eventos asignados" text="Los eventos asignados determinan qué puede vender y consultar este usuario en taquilla." />
        <ul v-else class="max-w-2xl divide-y divide-bo-line rounded-xl border border-bo-line bg-bo-card">
            <li v-for="event in assigned" :key="event.id" class="flex items-center justify-between gap-4 px-5 py-3">
                <div>
                    <p class="font-medium">{{ event.name }}</p>
                    <p class="tabular text-sm text-bo-slate">{{ event.date }}<span v-if="!event.published"> — no publicado</span></p>
                </div>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium text-bo-sold hover:bg-bo-sold-soft" @click="remove(event)">Quitar</button>
            </li>
        </ul>
    </BackOfficeLayout>
</template>

<script setup>
import { Link, router, useForm } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import EmptyState from "@/components/BoxOffice/EmptyState.vue";

const props = defineProps({
    type: { type: Object, required: true },
    user: { type: Object, required: true },
    assigned: { type: Array, required: true },
    available: { type: Array, required: true },
});

const form = useForm({ evento: null });

function assign() {
    form.post(route("admin.staff.events.store", { tipo: props.type.section, usuario: props.user.id }), { preserveScroll: true, onSuccess: () => form.reset() });
}

function remove(event) {
    if (!window.confirm(`¿Quitar ${event.name} a ${props.user.name}?`)) return;
    router.delete(route("admin.staff.events.destroy", { tipo: props.type.section, usuario: props.user.id, evento: event.id }), { preserveScroll: true });
}
</script>
