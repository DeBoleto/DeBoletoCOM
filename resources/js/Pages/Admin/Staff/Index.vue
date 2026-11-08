<template>
    <BackOfficeLayout :title="type.plural">
        <template #actions>
            <Link
                v-if="canManage && !filters.inactivos"
                :href="route('admin.staff.create', type.section)"
                class="inline-flex items-center gap-2 rounded-lg bg-bo-green px-3 py-2 text-sm font-semibold text-white hover:bg-bo-green-dark"
            >
                <BoIcon name="plus" :size="16" />
                Nuevo {{ type.singular }}
            </Link>
        </template>

        <div class="mb-5 flex flex-wrap items-center gap-3">
            <form class="relative w-full sm:w-80" @submit.prevent="filter({ buscar: search })">
                <label class="sr-only" for="search">Buscar</label>
                <BoIcon name="search" :size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-bo-muted" />
                <input id="search" v-model.trim="search" type="search" placeholder="Nombre o correo" class="block w-full rounded-lg border-bo-line bg-bo-card py-2.5 pl-10 focus:border-bo-green focus:ring-bo-green" />
            </form>
            <div role="tablist" class="inline-flex rounded-lg border border-bo-line bg-bo-card p-1 text-sm">
                <button
                    v-for="tab in [{ value: false, label: 'Activos' }, { value: true, label: 'Desactivados' }]"
                    :key="tab.label"
                    type="button"
                    role="tab"
                    :aria-selected="filters.inactivos === tab.value"
                    class="rounded-md px-3 py-1.5 font-medium"
                    :class="filters.inactivos === tab.value ? 'bg-bo-ink text-white' : 'text-bo-slate hover:text-bo-ink'"
                    @click="filter({ inactivos: tab.value ? 1 : undefined })"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <EmptyState v-if="users.data.length === 0" :title="`Sin ${type.plural.toLowerCase()}`" :text="filters.buscar ? 'Nadie coincide con la búsqueda.' : 'Todavía no hay registros en esta sección.'" />

        <div v-else class="overflow-x-auto rounded-xl border border-bo-line bg-bo-card">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b border-bo-line text-bo-slate">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Nombre</th>
                        <th scope="col" class="px-5 py-3 font-medium">Correo</th>
                        <th scope="col" class="px-5 py-3 font-medium">Teléfono</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-bo-line">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="px-5 py-3 font-medium">{{ user.name }}</td>
                        <td class="px-5 py-3">{{ user.email }}</td>
                        <td class="tabular px-5 py-3">{{ user.phone }}</td>
                        <td class="px-5 py-3">
                            <div v-if="filters.inactivos" class="flex justify-end">
                                <button v-if="canManage" type="button" class="rounded-lg border border-bo-line px-3 py-1.5 font-medium hover:border-bo-slate" @click="restore(user)">Reactivar</button>
                            </div>
                            <div v-else class="flex justify-end gap-1">
                                <Link :href="route('admin.staff.events', { tipo: type.section, usuario: user.id })" class="rounded-lg px-2.5 py-1.5 font-medium hover:bg-bo-paper">Eventos</Link>
                                <Link :href="route('admin.staff.privileges', { tipo: type.section, usuario: user.id })" class="rounded-lg px-2.5 py-1.5 font-medium hover:bg-bo-paper">Privilegios</Link>
                                <Link v-if="type.isAdmin" :href="route('admin.documents.index', user.id)" class="rounded-lg px-2.5 py-1.5 font-medium hover:bg-bo-paper">Documentos</Link>
                                <template v-if="canManage">
                                    <Link :href="route('admin.staff.edit', { tipo: type.section, usuario: user.id })" class="rounded-lg px-2.5 py-1.5 font-medium hover:bg-bo-paper">Editar</Link>
                                    <button type="button" class="rounded-lg px-2.5 py-1.5 font-medium text-bo-sold hover:bg-bo-sold-soft" @click="deactivate(user)">Desactivar</button>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :page="users" />
    </BackOfficeLayout>
</template>

<script setup>
import { ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import EmptyState from "@/components/BoxOffice/EmptyState.vue";
import Pagination from "@/components/BoxOffice/Pagination.vue";

const props = defineProps({
    type: { type: Object, required: true },
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const search = ref(props.filters.buscar ?? "");

function filter(changes) {
    const params = { buscar: props.filters.buscar || undefined, inactivos: props.filters.inactivos ? 1 : undefined, ...changes };
    router.get(route("admin.staff.index", props.type.section), params, { preserveState: true, replace: true });
}

function deactivate(user) {
    if (!window.confirm(`¿Desactivar a ${user.name}? Ya no podrá iniciar sesión.`)) return;
    router.delete(route("admin.staff.destroy", { tipo: props.type.section, usuario: user.id }), { preserveScroll: true });
}

function restore(user) {
    router.post(route("admin.staff.restore", { tipo: props.type.section, usuario: user.id }), {}, { preserveScroll: true });
}
</script>
