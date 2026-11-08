<template>
    <BackOfficeLayout :title="`Documentos de ${user.name}`">
        <Link :href="route('admin.staff.index', 'administradores')" class="mb-5 inline-flex items-center gap-1 text-sm font-medium text-bo-slate hover:text-bo-ink">
            <BoIcon name="chevronLeft" :size="16" />
            Administradores
        </Link>

        <div class="grid gap-6 xl:grid-cols-[1fr_380px] xl:items-start">
            <section aria-label="Documentos">
                <EmptyState v-if="documents.length === 0" title="Sin documentos" text="Agrega identificaciones, contratos o constancias del administrador." />
                <ul v-else class="divide-y divide-bo-line rounded-xl border border-bo-line bg-bo-card">
                    <li v-for="document in documents" :key="document.id" class="flex items-start justify-between gap-4 px-5 py-3.5">
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ document.name }}
                                <span v-if="document.private" class="ml-2 rounded bg-bo-paper px-1.5 py-0.5 text-xs text-bo-slate">Privado</span>
                                <span v-if="document.expired" class="ml-2 rounded bg-bo-sold-soft px-1.5 py-0.5 text-xs text-bo-sold">Vencido</span>
                            </p>
                            <p v-if="document.description" class="text-sm text-bo-slate">{{ document.description }}</p>
                            <p class="tabular text-xs text-bo-muted">
                                <template v-if="document.issued">Expedido {{ document.issued }}</template>
                                <template v-if="document.expires"> — vence {{ document.expires }}</template>
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <a :href="route('admin.documents.download', { usuario: user.id, documento: document.id })" class="rounded-lg px-2.5 py-1.5 text-sm font-medium hover:bg-bo-paper">Descargar</a>
                            <button type="button" class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-bo-sold hover:bg-bo-sold-soft" @click="remove(document)">Eliminar</button>
                        </div>
                    </li>
                </ul>
            </section>

            <form class="space-y-4 rounded-xl border border-bo-line bg-bo-card p-5" @submit.prevent="upload">
                <h2 class="font-semibold">Agregar documento</h2>
                <label class="block text-sm font-medium">
                    <span class="mb-1.5 block">Nombre</span>
                    <input v-model="form.documento" type="text" required class="block w-full rounded-lg border-bo-line py-2 focus:border-bo-green focus:ring-bo-green" />
                </label>
                <p v-if="form.errors.documento" class="text-sm text-bo-sold">{{ form.errors.documento }}</p>
                <label class="block text-sm font-medium">
                    <span class="mb-1.5 block">Descripción (opcional)</span>
                    <textarea v-model="form.descripcion" rows="2" class="block w-full rounded-lg border-bo-line text-sm focus:border-bo-green focus:ring-bo-green" />
                </label>
                <label class="block text-sm font-medium">
                    <span class="mb-1.5 block">Archivo (PDF, JPG o PNG, máx. 10 MB)</span>
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" required class="block w-full text-sm" @input="form.archivo = $event.target.files[0]" />
                </label>
                <p v-if="form.errors.archivo" class="text-sm text-bo-sold">{{ form.errors.archivo }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm font-medium">
                        <span class="mb-1.5 block">Expedición</span>
                        <input v-model="form.expedicion_fecha" type="date" class="block w-full rounded-lg border-bo-line py-2 focus:border-bo-green focus:ring-bo-green" />
                    </label>
                    <label class="text-sm font-medium">
                        <span class="mb-1.5 block">Vencimiento</span>
                        <input v-model="form.vencimiento_fecha" type="date" :disabled="!form.vigencia" class="block w-full rounded-lg border-bo-line py-2 focus:border-bo-green focus:ring-bo-green disabled:opacity-50" />
                    </label>
                </div>
                <p v-if="form.errors.vencimiento_fecha" class="text-sm text-bo-sold">{{ form.errors.vencimiento_fecha }}</p>
                <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                    <label class="flex items-center gap-2"><input v-model="form.vigencia" type="checkbox" class="rounded border-bo-line text-bo-green focus:ring-bo-green" /> Tiene vigencia</label>
                    <label class="flex items-center gap-2"><input v-model="form.privado" type="checkbox" class="rounded border-bo-line text-bo-green focus:ring-bo-green" /> Privado</label>
                    <label class="flex items-center gap-2"><input v-model="form.notificar" type="checkbox" class="rounded border-bo-line text-bo-green focus:ring-bo-green" /> Avisar al vencer</label>
                </div>
                <button type="submit" class="w-full rounded-lg bg-bo-green px-4 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50" :disabled="form.processing || !form.archivo || !form.documento">
                    {{ form.processing ? "Subiendo…" : "Agregar documento" }}
                </button>
            </form>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { Link, router, useForm } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import EmptyState from "@/components/BoxOffice/EmptyState.vue";

const props = defineProps({
    user: { type: Object, required: true },
    documents: { type: Array, required: true },
});

const form = useForm({ documento: "", descripcion: "", archivo: null, expedicion_fecha: "", vencimiento_fecha: "", vigencia: false, privado: false, notificar: false });

function upload() {
    form.post(route("admin.documents.store", props.user.id), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
}

function remove(document) {
    if (!window.confirm(`¿Eliminar ${document.name}?`)) return;
    router.delete(route("admin.documents.destroy", { usuario: props.user.id, documento: document.id }), { preserveScroll: true });
}
</script>
