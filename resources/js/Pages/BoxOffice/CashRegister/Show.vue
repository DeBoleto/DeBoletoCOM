<template>
    <BackOfficeLayout :title="register.open ? 'Cerrar caja' : 'Corte de caja'">
        <template #actions>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-bo-line bg-bo-card px-3 py-2 text-sm font-semibold hover:border-bo-slate print:hidden"
                @click="print"
            >
                <BoIcon name="printer" :size="16" />
                Imprimir
            </button>
        </template>

        <div class="mx-auto flex max-w-5xl flex-col gap-6">
            <section class="grid gap-4 rounded-xl border border-bo-line bg-bo-card p-5 sm:grid-cols-2 lg:grid-cols-4" aria-label="Datos de la caja">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-bo-slate uppercase">Caja</p>
                    <p class="font-semibold">{{ register.name || `#${register.id}` }}</p>
                    <span
                        class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="register.open ? 'bg-bo-green-soft text-bo-green-dark' : 'bg-bo-line text-bo-slate'"
                    >
                        {{ register.open ? "Abierta" : "Cerrada" }}
                    </span>
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-bo-slate uppercase">Cajero</p>
                    <p class="font-semibold">{{ register.cashier?.name }}</p>
                    <p class="text-sm text-bo-slate">{{ register.cashier?.email }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-bo-slate uppercase">Apertura</p>
                    <p class="tabular font-semibold">{{ register.openedAt }}</p>
                    <p class="tabular text-sm text-bo-slate">Fondo {{ money(register.openingAmount) }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-bo-slate uppercase">Cierre</p>
                    <template v-if="register.open">
                        <p class="text-sm text-bo-pending">Pendiente</p>
                    </template>
                    <template v-else>
                        <p class="tabular font-semibold">{{ register.closedAt }}</p>
                        <p class="text-sm text-bo-slate">Autorizó {{ register.authorizer?.name }}</p>
                    </template>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Totales">
                <div v-for="card in cards" :key="card.label" class="rounded-xl border bg-bo-card p-4" :class="card.strong ? 'border-bo-green' : 'border-bo-line'">
                    <p class="text-sm text-bo-slate">{{ card.label }}</p>
                    <p class="tabular mt-1 text-2xl font-semibold">{{ money(card.value) }}</p>
                    <p v-if="card.hint" class="text-xs text-bo-slate">{{ card.hint }}</p>
                </div>
            </section>

            <section class="overflow-x-auto rounded-xl border border-bo-line bg-bo-card" aria-labelledby="purchases-title">
                <div class="flex items-center justify-between border-b border-bo-line px-5 py-3">
                    <h2 id="purchases-title" class="font-semibold">Compras de la caja</h2>
                    <p class="tabular text-sm text-bo-slate">{{ totals.purchases }} compras · {{ totals.tickets }} boletos</p>
                </div>
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-bo-line text-bo-slate">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Folio</th>
                            <th scope="col" class="px-5 py-3 font-medium">Hora</th>
                            <th scope="col" class="px-5 py-3 font-medium">Evento</th>
                            <th scope="col" class="px-5 py-3 font-medium">Forma de pago</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Boletos</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-bo-line">
                        <tr v-for="purchase in purchases" :key="purchase.id">
                            <td class="tabular px-5 py-3 font-medium">{{ purchase.id }}</td>
                            <td class="tabular px-5 py-3 whitespace-nowrap">{{ purchase.date }}</td>
                            <td class="px-5 py-3">{{ purchase.event }}</td>
                            <td class="px-5 py-3">{{ purchase.typeLabel }}</td>
                            <td class="tabular px-5 py-3 text-right">
                                {{ purchase.tickets }}
                                <span v-if="purchase.courtesies" class="text-xs text-bo-slate">({{ purchase.courtesies }} cortesía{{ purchase.courtesies === 1 ? "" : "s" }})</span>
                            </td>
                            <td class="tabular px-5 py-3 text-right font-semibold">{{ money(purchase.total) }}</td>
                        </tr>
                        <tr v-if="!purchases.length">
                            <td colspan="6" class="px-5 py-8 text-center text-bo-slate">Todavía no hay ventas en esta caja.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="purchases.length" class="border-t border-bo-line font-semibold">
                        <tr>
                            <td colspan="4" class="px-5 py-3">Total vendido</td>
                            <td class="tabular px-5 py-3 text-right">{{ totals.tickets }}</td>
                            <td class="tabular px-5 py-3 text-right">{{ money(totals.sales) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <section v-if="!register.open" class="grid gap-3 rounded-xl border border-bo-line bg-bo-card p-5 sm:grid-cols-3" aria-label="Resultado del cierre">
                <div>
                    <p class="text-sm text-bo-slate">Efectivo esperado</p>
                    <p class="tabular text-xl font-semibold">{{ money(totals.expectedCash) }}</p>
                </div>
                <div>
                    <p class="text-sm text-bo-slate">Efectivo contado</p>
                    <p class="tabular text-xl font-semibold">{{ money(register.countedCash) }}</p>
                </div>
                <div>
                    <p class="text-sm text-bo-slate">Diferencia</p>
                    <p class="tabular text-xl font-semibold" :class="differenceClass(register.difference)">{{ signedMoney(register.difference) }}</p>
                </div>
                <p v-if="register.closingComment" class="text-sm text-bo-slate sm:col-span-3">Comentario: {{ register.closingComment }}</p>
            </section>

            <form v-else class="flex flex-col gap-5 rounded-xl border border-bo-line bg-bo-card p-5 print:hidden" @submit.prevent="close">
                <div>
                    <h2 class="text-lg font-semibold">Cierre de caja</h2>
                    <p class="text-sm text-bo-slate">Cuenta el efectivo físico de la caja. El cierre lo autoriza un supervisor con su correo y contraseña.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="flex flex-col gap-1.5 text-sm font-semibold">
                        Efectivo contado
                        <span class="relative">
                            <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-bo-slate">$</span>
                            <input
                                v-model="form.efectivo_contado"
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="decimal"
                                required
                                class="tabular w-full rounded-lg border-bo-line py-2.5 pl-7 text-base font-normal focus:border-bo-green focus:ring-bo-green"
                                :aria-invalid="!!form.errors.efectivo_contado"
                            />
                        </span>
                        <span v-if="form.errors.efectivo_contado" class="text-xs font-normal text-bo-sold" role="alert">{{ form.errors.efectivo_contado }}</span>
                    </label>
                    <div class="flex flex-col gap-1.5 text-sm">
                        <span class="font-semibold">Efectivo esperado</span>
                        <span class="tabular py-2.5 text-base">{{ money(totals.expectedCash) }}</span>
                    </div>
                    <div class="flex flex-col gap-1.5 text-sm">
                        <span class="font-semibold">Diferencia</span>
                        <span class="tabular py-2.5 text-base font-semibold" :class="differenceClass(difference)">
                            {{ form.efectivo_contado === "" ? "—" : signedMoney(difference) }}
                        </span>
                    </div>
                </div>

                <label class="flex flex-col gap-1.5 text-sm font-semibold">
                    Comentario <span class="font-normal text-bo-slate">(opcional)</span>
                    <textarea v-model="form.comentario" rows="2" maxlength="500" class="rounded-lg border-bo-line text-sm font-normal focus:border-bo-green focus:ring-bo-green" />
                </label>

                <fieldset class="grid gap-4 rounded-lg border border-bo-line p-4 sm:grid-cols-2">
                    <legend class="px-1 text-sm font-semibold">Autorización del supervisor</legend>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold">
                        Correo
                        <input
                            v-model="form.autoriza_correo"
                            type="email"
                            autocomplete="off"
                            required
                            class="rounded-lg border-bo-line py-2.5 text-sm font-normal focus:border-bo-green focus:ring-bo-green"
                            :aria-invalid="!!form.errors.autoriza_correo"
                        />
                        <span v-if="form.errors.autoriza_correo" class="text-xs font-normal text-bo-sold" role="alert">{{ form.errors.autoriza_correo }}</span>
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold">
                        Contraseña
                        <input
                            v-model="form.autoriza_password"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="rounded-lg border-bo-line py-2.5 text-sm font-normal focus:border-bo-green focus:ring-bo-green"
                            :aria-invalid="!!form.errors.autoriza_password"
                        />
                        <span v-if="form.errors.autoriza_password" class="text-xs font-normal text-bo-sold" role="alert">{{ form.errors.autoriza_password }}</span>
                    </label>
                </fieldset>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                    <Link :href="route('boxoffice.tickets.index')" class="rounded-lg border border-bo-line px-4 py-2.5 text-center font-semibold hover:border-bo-slate">
                        Seguir vendiendo
                    </Link>
                    <button
                        type="submit"
                        class="rounded-lg bg-bo-ink px-5 py-2.5 font-semibold text-white hover:bg-bo-ink-2 disabled:opacity-50"
                        :disabled="form.processing || form.efectivo_contado === ''"
                    >
                        {{ form.processing ? "Cerrando…" : "Cerrar caja" }}
                    </button>
                </div>
            </form>

            <div v-if="!register.open" class="flex justify-end print:hidden">
                <Link :href="route('boxoffice.cash.open')" class="rounded-lg bg-bo-green px-5 py-2.5 font-semibold text-white hover:bg-bo-green-dark">
                    Abrir nueva caja
                </Link>
            </div>
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
    register: { type: Object, required: true },
    purchases: { type: Array, required: true },
    totals: { type: Object, required: true },
});

const form = useForm({ efectivo_contado: "", comentario: "", autoriza_correo: "", autoriza_password: "" });

const cards = computed(() => [
    { label: "Efectivo", value: props.totals.cash },
    { label: "Terminal", value: props.totals.terminal },
    { label: "Transferencia", value: props.totals.transfer },
    { label: "Efectivo esperado en caja", value: props.totals.expectedCash, hint: "Fondo inicial + ventas en efectivo", strong: true },
]);

const difference = computed(() => Math.round((Number(form.efectivo_contado || 0) - props.totals.expectedCash) * 100) / 100);

function signedMoney(value) {
    const amount = Number(value ?? 0);
    return `${amount > 0 ? "+" : amount < 0 ? "−" : ""}${money(Math.abs(amount))}`;
}

function differenceClass(value) {
    const amount = Number(value ?? 0);
    return amount === 0 ? "text-bo-green-dark" : amount < 0 ? "text-bo-sold" : "text-bo-pending";
}

function close() {
    form.post(route("boxoffice.cash.close"), {
        preserveScroll: true,
        onFinish: () => form.reset("autoriza_password"),
    });
}

function print() {
    window.print();
}
</script>
