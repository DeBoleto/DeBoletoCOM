<template>
    <BackOfficeLayout title="Abrir caja">
        <div class="mx-auto max-w-lg">
            <form class="flex flex-col gap-5 rounded-xl border border-bo-line bg-bo-card p-6" @submit.prevent="submit">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-bo-green-soft text-bo-green-dark">
                        <BoIcon name="cash" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold">Apertura de caja</h2>
                        <p class="text-sm text-bo-slate">Registra el fondo inicial en efectivo para empezar a vender. Cajero: {{ cashier.name }}.</p>
                    </div>
                </div>

                <label class="flex flex-col gap-1.5 text-sm font-semibold">
                    Monto de apertura
                    <span class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-bo-slate">$</span>
                        <input
                            v-model="form.monto_apertura"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            required
                            class="tabular w-full rounded-lg border-bo-line py-2.5 pl-7 text-base font-normal focus:border-bo-green focus:ring-bo-green"
                            :aria-invalid="!!form.errors.monto_apertura"
                        />
                    </span>
                    <span v-if="form.errors.monto_apertura" class="text-xs font-normal text-bo-sold" role="alert">{{ form.errors.monto_apertura }}</span>
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-semibold">
                    Caja <span class="font-normal text-bo-slate">(opcional)</span>
                    <input
                        v-model="form.caja"
                        type="text"
                        maxlength="50"
                        placeholder="Ej. Taquilla 1"
                        class="rounded-lg border-bo-line py-2.5 text-sm font-normal focus:border-bo-green focus:ring-bo-green"
                    />
                    <span v-if="form.errors.caja" class="text-xs font-normal text-bo-sold" role="alert">{{ form.errors.caja }}</span>
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-semibold">
                    Comentario <span class="font-normal text-bo-slate">(opcional)</span>
                    <textarea
                        v-model="form.comentario"
                        rows="2"
                        maxlength="500"
                        class="rounded-lg border-bo-line text-sm font-normal focus:border-bo-green focus:ring-bo-green"
                    />
                </label>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link v-if="lastClosed" :href="route('boxoffice.cash.closed', lastClosed)" class="text-sm font-semibold text-bo-slate hover:text-bo-ink">
                        Ver mi último corte
                    </Link>
                    <span v-else />
                    <button
                        type="submit"
                        class="rounded-lg bg-bo-green px-5 py-2.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50"
                        :disabled="form.processing || form.monto_apertura === ''"
                    >
                        {{ form.processing ? "Abriendo…" : "Abrir caja" }}
                    </button>
                </div>
            </form>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";

defineProps({
    cashier: { type: Object, required: true },
    lastClosed: { type: Number, default: null },
});

const form = useForm({ monto_apertura: "", caja: "", comentario: "" });

function submit() {
    form.post(route("boxoffice.cash.store"));
}
</script>
