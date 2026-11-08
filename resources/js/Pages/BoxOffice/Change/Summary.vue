<template>
    <BackOfficeLayout :title="`Cambio — ${event.name}`">
        <div class="mx-auto grid max-w-5xl gap-8 lg:grid-cols-[1fr_360px]">
            <article class="bo-stub border border-bo-line" :style="{ '--stub-cut': 'calc(100% - 196px)' }" aria-label="Detalle del cambio">
                <header class="p-6">
                    <h2 class="text-xl font-semibold">{{ event.name }}</h2>
                    <p class="tabular text-sm text-bo-slate">{{ functionInfo.date }} a las {{ functionInfo.time }} h</p>
                </header>
                <div class="border-t border-bo-line px-6 py-4 text-sm">
                    <p class="mb-2 text-bo-slate">
                        Boleto original <span class="tabular font-medium text-bo-ink">{{ oldTicket.id }}</span>
                        <template v-if="oldTicket.seat">, asiento {{ oldTicket.seat }}</template>
                        — <span class="tabular">{{ money(oldTicket.price) }}</span>
                    </p>
                    <ul class="divide-y divide-bo-line">
                        <li v-for="(line, index) in lines" :key="index" class="flex justify-between py-2">
                            <span><span class="font-medium">{{ line.label }}</span> <span class="text-bo-slate">— {{ line.locality }}</span></span>
                            <span class="tabular">{{ money(line.price) }}</span>
                        </li>
                    </ul>
                </div>
                <div class="bo-perforation" />
                <dl class="tabular space-y-1.5 px-6 py-5 text-sm">
                    <div class="flex justify-between"><dt class="text-bo-slate">Boletos nuevos</dt><dd>{{ money(quote.newTotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-bo-slate">Diferencia</dt><dd>{{ money(quote.difference) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-bo-slate">Cargo por servicio</dt><dd>{{ money(quote.service) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-bo-slate">Cargo bancario</dt><dd>{{ money(quote.card) }}</dd></div>
                    <div class="flex items-baseline justify-between pt-2">
                        <dt class="font-semibold">Total a cobrar</dt>
                        <dd class="text-3xl font-semibold">{{ money(Math.max(quote.total, 0)) }}</dd>
                    </div>
                </dl>
            </article>

            <form class="space-y-5" @submit.prevent="form.post(route('boxoffice.change.confirm', event.id))">
                <section class="rounded-xl border border-bo-line bg-bo-card p-5">
                    <p class="text-sm text-bo-slate">Forma de pago</p>
                    <p class="text-lg font-semibold">{{ payment }}</p>
                    <p v-if="quote.difference < 0" class="mt-2 text-sm text-bo-pending">Los boletos nuevos valen menos que el original; no hay diferencia a cobrar.</p>
                </section>
                <label class="flex items-start gap-3 rounded-xl border border-bo-line bg-bo-card p-4 text-sm">
                    <input v-model="form.acepto" type="checkbox" class="mt-0.5 size-5 rounded border-bo-line text-bo-green focus:ring-bo-green" />
                    <span>Revisé el cambio y el cobro con el cliente.</span>
                </label>
                <p v-if="form.errors.acepto" class="text-sm text-bo-sold">{{ form.errors.acepto }}</p>
                <button
                    type="submit"
                    class="w-full rounded-lg bg-bo-green px-4 py-3.5 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-50"
                    :disabled="!form.acepto || form.processing"
                >
                    {{ form.processing ? "Registrando…" : "Registrar cambio" }}
                </button>
                <Link :href="route('boxoffice.change.selection', event.id)" class="inline-flex items-center gap-1 text-sm font-medium text-bo-slate hover:text-bo-ink">
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
import { money } from "@/components/BoxOffice/format.js";

const props = defineProps({
    event: { type: Object, required: true },
    function: { type: Object, required: true },
    oldTicket: { type: Object, required: true },
    lines: { type: Array, required: true },
    payment: { type: String, required: true },
    quote: { type: Object, required: true },
    confirmToken: { type: String, required: true },
});

const functionInfo = computed(() => props.function);
const form = useForm({ acepto: false, token: props.confirmToken });
</script>
