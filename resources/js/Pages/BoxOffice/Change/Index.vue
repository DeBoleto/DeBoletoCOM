<template>
    <BoxOfficeLayout title="Cambio de boletos">
        <div class="max-w-xl">
            <p class="mb-5 text-sm text-bo-slate">
                Cambia un boleto de un evento cancelado por boletos de otro evento. Se cobra la diferencia y el boleto original queda registrado como cambiado.
            </p>
            <form class="rounded-xl border border-bo-line bg-bo-card p-6" @submit.prevent="form.post(route('boxoffice.change.lookup'))">
                <label for="folio" class="mb-1.5 block text-sm font-medium">Folio del boleto a cambiar</label>
                <input
                    id="folio"
                    v-model.trim="form.folio"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    autofocus
                    class="tabular block w-full rounded-lg border-bo-line py-3 text-lg focus:border-bo-green focus:ring-bo-green"
                    :aria-invalid="!!form.errors.folio"
                />
                <p v-if="form.errors.folio" class="mt-1.5 text-sm text-bo-sold">{{ form.errors.folio }}</p>
                <button
                    type="submit"
                    class="mt-5 w-full rounded-lg bg-bo-green px-4 py-3 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50"
                    :disabled="!form.folio || form.processing"
                >
                    Continuar
                </button>
            </form>
        </div>
    </BoxOfficeLayout>
</template>

<script setup>
import { useForm } from "@inertiajs/vue3";
import BoxOfficeLayout from "@/Layouts/BoxOfficeLayout.vue";

const form = useForm({ folio: "" });
</script>
