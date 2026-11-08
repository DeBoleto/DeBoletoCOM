<template>
    <BackOfficeLayout title="Reenviar boletos por correo">
        <div class="max-w-xl">
            <p class="mb-6 text-sm text-bo-slate">
                Envía de nuevo el PDF de una compra de tus eventos. El folio aparece en la pantalla de venta registrada y en cada boleto.
            </p>

            <form class="space-y-5 rounded-xl border border-bo-line bg-bo-card p-6" @submit.prevent="submit">
                <div>
                    <label for="folio" class="mb-1.5 block text-sm font-medium">Folio de compra</label>
                    <input
                        id="folio"
                        v-model="form.folio"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        class="tabular block w-full rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green"
                        :aria-invalid="!!form.errors.folio"
                    />
                    <p v-if="form.errors.folio" class="mt-1.5 text-sm text-bo-sold">{{ form.errors.folio }}</p>
                </div>
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium">Correo del cliente</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        class="block w-full rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green"
                        :aria-invalid="!!form.errors.email"
                    />
                    <p v-if="form.errors.email" class="mt-1.5 text-sm text-bo-sold">{{ form.errors.email }}</p>
                </div>
                <button
                    type="submit"
                    class="w-full rounded-lg bg-bo-green px-4 py-3 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50"
                    :disabled="form.processing || !form.folio || !form.email"
                >
                    {{ form.processing ? "Enviando…" : "Enviar boletos" }}
                </button>
            </form>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { useForm } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";

const form = useForm({ folio: "", email: "" });

function submit() {
    form.post(route("boxoffice.sales.mail.send"), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>
