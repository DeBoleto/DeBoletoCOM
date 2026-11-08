<template>
    <BoxOfficeLayout title="Canje de boletos">
        <div class="max-w-3xl">
            <p class="mb-5 text-sm text-bo-slate">Imprime en taquilla los boletos de una compra hecha en línea o en otro punto de venta.</p>

            <form class="flex gap-3" @submit.prevent="search">
                <label class="flex-1">
                    <span class="sr-only">Folio de compra</span>
                    <input
                        v-model.trim="value"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        autofocus
                        placeholder="Folio de compra"
                        class="tabular block w-full rounded-lg border-bo-line py-3 text-lg focus:border-bo-green focus:ring-bo-green"
                    />
                </label>
                <button type="submit" class="rounded-lg bg-bo-ink px-5 font-semibold text-white hover:bg-bo-ink-2" :disabled="!value">Buscar</button>
            </form>

            <p v-if="purchase && !purchase.found" class="mt-6 rounded-lg border border-bo-sold/30 bg-bo-sold-soft px-4 py-3 text-sm text-bo-sold" role="alert">
                {{ purchase.message }}
            </p>

            <article v-if="purchase?.found" class="mt-6 rounded-xl border border-bo-line bg-bo-card">
                <header class="flex flex-wrap items-start justify-between gap-4 p-5">
                    <div>
                        <p class="tabular text-sm text-bo-slate">Folio {{ purchase.id }}</p>
                        <h2 class="text-xl font-semibold">{{ purchase.event }}</h2>
                        <p class="text-sm text-bo-slate">{{ purchase.function }} — {{ purchase.payment }}</p>
                    </div>
                    <span
                        class="rounded px-2 py-1 text-xs font-semibold"
                        :class="purchase.allPrinted ? 'bg-bo-sold-soft text-bo-sold' : 'bg-bo-green-soft text-bo-green-dark'"
                    >
                        {{ purchase.allPrinted ? "Ya impresos" : "Listos para canjear" }}
                    </span>
                </header>
                <ul class="divide-y divide-bo-line border-t border-bo-line text-sm">
                    <li v-for="ticket in purchase.tickets" :key="ticket.id" class="flex items-center justify-between px-5 py-2.5">
                        <span>
                            <span class="tabular font-medium">{{ ticket.id }}</span>
                            <span class="text-bo-slate"> — {{ ticket.locality }}{{ ticket.seat ? `, asiento ${ticket.seat}` : "" }}</span>
                        </span>
                        <span v-if="ticket.courtesy" class="text-xs text-bo-green-dark">Cortesía</span>
                    </li>
                </ul>
                <footer class="flex flex-wrap justify-end gap-3 border-t border-bo-line p-5">
                    <a
                        :href="route('boxoffice.exchange.digital', purchase.id)"
                        class="inline-flex items-center gap-2 rounded-lg border border-bo-line px-4 py-2.5 font-medium hover:border-bo-slate"
                    >
                        <BoIcon name="download" :size="18" />
                        PDF digital
                    </a>
                    <a
                        v-if="!purchase.allPrinted"
                        :href="route('boxoffice.exchange.print', purchase.id)"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 rounded-lg bg-bo-green px-4 py-2.5 font-semibold text-white hover:bg-bo-green-dark"
                    >
                        <BoIcon name="printer" :size="18" />
                        Imprimir boletos
                    </a>
                </footer>
            </article>
        </div>
    </BoxOfficeLayout>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import BoxOfficeLayout from "@/Layouts/BoxOfficeLayout.vue";
import BoIcon from "@/Components/BoxOffice/BoIcon.vue";

const props = defineProps({
    folio: { type: Number, default: null },
    purchase: { type: Object, default: null },
});

const value = ref(props.folio ? String(props.folio) : "");

function search() {
    router.get(route("boxoffice.exchange.index"), { folio: value.value.replace(/\D/g, "") }, { preserveState: true });
}
</script>
