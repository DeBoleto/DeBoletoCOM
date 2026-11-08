<template>
    <BackOfficeLayout :title="`Confirmar cortesías — ${event.name}`">
        <div class="mx-auto grid max-w-4xl gap-8 lg:grid-cols-[1fr_340px]">
            <article class="bo-stub border border-bo-line" :style="{ '--stub-cut': 'calc(100% - 76px)' }" aria-label="Cortesías a crear">
                <header class="p-6">
                    <h2 class="text-xl font-semibold">{{ event.name }}</h2>
                    <p class="tabular text-sm text-bo-slate">{{ functionInfo.date }} a las {{ functionInfo.time }} h</p>
                </header>
                <ul class="max-h-96 divide-y divide-bo-line overflow-y-auto border-t border-bo-line px-6 text-sm">
                    <li v-for="(line, index) in lines" :key="index" class="flex justify-between py-2">
                        <span class="font-medium">{{ line.label }}</span>
                        <span class="text-bo-slate">{{ line.locality }}</span>
                    </li>
                </ul>
                <div class="bo-perforation" />
                <div class="flex items-baseline justify-between px-6 py-5">
                    <span class="text-sm text-bo-slate">Cortesías</span>
                    <span class="tabular text-3xl font-semibold">{{ lines.length }}</span>
                </div>
            </article>

            <form class="space-y-5" @submit.prevent="submit">
                <div class="rounded-xl border border-bo-line bg-bo-card p-5">
                    <label for="reason" class="mb-1.5 block text-sm font-medium">Motivo</label>
                    <textarea
                        id="reason"
                        v-model="form.comentario"
                        rows="3"
                        maxlength="255"
                        required
                        placeholder="Ej. Invitados del patrocinador"
                        class="block w-full rounded-lg border-bo-line text-sm focus:border-bo-green focus:ring-bo-green"
                    />
                    <p v-if="form.errors.comentario" class="mt-1 text-sm text-bo-sold">{{ form.errors.comentario }}</p>

                    <label for="cost" class="mt-4 mb-1.5 block text-sm font-medium">Costo impreso en el boleto</label>
                    <input
                        id="cost"
                        v-model="form.costo"
                        type="number"
                        min="0"
                        step="0.01"
                        class="tabular block w-full rounded-lg border-bo-line focus:border-bo-green focus:ring-bo-green"
                    />
                    <p class="mt-1 text-xs text-bo-slate">Déjalo en 0 si el boleto no debe mostrar precio.</p>
                    <p v-if="form.errors.costo" class="mt-1 text-sm text-bo-sold">{{ form.errors.costo }}</p>
                </div>

                <label class="flex items-start gap-3 rounded-xl border border-bo-line bg-bo-card p-4 text-sm">
                    <input v-model="form.acepto" type="checkbox" class="mt-0.5 size-5 rounded border-bo-line text-bo-green focus:ring-bo-green" />
                    <span>Confirmo la creación de estas cortesías.</span>
                </label>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-bo-green px-4 py-3.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50"
                    :disabled="!form.acepto || form.comentario.trim().length < 3 || form.processing"
                >
                    {{ form.processing ? "Creando…" : `Crear ${lines.length} cortesías` }}
                </button>
                <Link :href="route(backRoute, event.id)" class="inline-flex items-center gap-1 text-sm font-medium text-bo-slate hover:text-bo-ink">
                    <BoIcon name="chevronLeft" :size="16" />
                    Cambiar asientos
                </Link>
            </form>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { computed } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";

const props = defineProps({
    event: { type: Object, required: true },
    function: { type: Object, required: true },
    lines: { type: Array, required: true },
    confirmToken: { type: String, required: true },
    storeRoute: { type: String, default: "boxoffice.courtesies.create.store" },
    backRoute: { type: String, default: "boxoffice.courtesies.create.selection" },
});

const functionInfo = computed(() => props.function);
const form = useForm({ comentario: "", costo: 0, acepto: false, token: props.confirmToken });

function submit() {
    form.post(route(props.storeRoute, props.event.id));
}
</script>
