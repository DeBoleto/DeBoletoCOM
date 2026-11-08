<template>
    <BackOfficeLayout title="Actualizar compra">
        <form class="mb-6 flex max-w-md gap-3" @submit.prevent="router.get(route('admin.purchases.index'), { folio: value.replace(/\D/g, '') }, { preserveState: true })">
            <label class="flex-1">
                <span class="sr-only">Folio de compra</span>
                <input v-model.trim="value" type="text" inputmode="numeric" placeholder="Folio de compra" class="tabular block w-full rounded-lg border-bo-line py-2.5 focus:border-bo-green focus:ring-bo-green" />
            </label>
            <button type="submit" class="rounded-lg bg-bo-ink px-4 font-semibold text-white hover:bg-bo-ink-2" :disabled="!value">Buscar</button>
        </form>

        <p v-if="folio && !purchase" class="text-sm text-bo-sold" role="alert">No existe la compra {{ folio }}.</p>

        <article v-if="purchase" class="max-w-3xl rounded-xl border border-bo-line bg-bo-card">
            <header class="flex flex-wrap items-start justify-between gap-4 p-5">
                <div>
                    <p class="tabular text-sm text-bo-slate">Compra {{ purchase.id }} — {{ purchase.date }}</p>
                    <h2 class="text-lg font-semibold">{{ purchase.event }}</h2>
                    <p class="text-sm text-bo-slate">{{ purchase.payment }} — total {{ money(purchase.total) }}, comisión {{ money(purchase.commission) }}</p>
                </div>
            </header>
            <ul class="divide-y divide-bo-line border-t border-bo-line text-sm">
                <li v-for="ticket in purchase.tickets" :key="ticket.id" class="flex justify-between px-5 py-2.5">
                    <span><span class="tabular font-medium">{{ ticket.id }}</span> <span class="text-bo-slate">— {{ ticket.locality }}{{ ticket.seat ? `, ${ticket.seat}` : "" }}</span></span>
                    <span class="tabular">{{ money(ticket.price) }} <span v-if="ticket.printed" class="ml-2 text-xs text-bo-muted">impreso</span></span>
                </li>
            </ul>
            <footer class="flex flex-wrap justify-end gap-2 border-t border-bo-line p-5">
                <a :href="route('admin.purchases.digital', purchase.id)" target="_blank" rel="noopener" class="rounded-lg border border-bo-line px-3 py-2 text-sm font-medium hover:border-bo-slate">Ver PDF digital</a>
                <button type="button" class="rounded-lg border border-bo-line px-3 py-2 text-sm font-medium hover:border-bo-slate" @click="post('admin.purchases.reprint', '¿Permitir que estos boletos se vuelvan a imprimir?')">Habilitar reimpresión</button>
                <button v-if="!purchase.isCash" type="button" class="rounded-lg bg-bo-ink px-3 py-2 text-sm font-semibold text-white hover:bg-bo-ink-2" @click="post('admin.purchases.cash', '¿Registrar esta compra como pagada en efectivo? Se recalcularán sus totales.')">Convertir a efectivo</button>
            </footer>
        </article>
    </BackOfficeLayout>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import { money } from "@/components/BoxOffice/format.js";

const props = defineProps({
    folio: { type: Number, default: null },
    purchase: { type: Object, default: null },
});

const value = ref(props.folio ? String(props.folio) : "");

function post(name, question) {
    if (!window.confirm(question)) return;
    router.post(route(name, props.purchase.id), {}, { preserveScroll: true });
}
</script>
