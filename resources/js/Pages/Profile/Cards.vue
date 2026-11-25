<template>
    <ProfileShell title="Métodos de pago">
        <p class="lead">Tarjetas que guardaste al pagar. Las usamos solo cuando tú las eliges en una compra; DeBoleto no guarda el número completo.</p>

        <ul v-if="cards.length" class="cards" role="list">
            <li v-for="card in cards" :key="card.id">
                <article class="card" :class="`card--${brandKey(card.brand)}`">
                    <div class="card-top">
                        <span class="card-brand">{{ brandLabel(card.brand) }}</span>
                        <span v-if="card.bank" class="card-bank">{{ card.bank }}</span>
                    </div>
                    <p class="card-number" :aria-label="`Tarjeta terminación ${card.last4}`">•••• •••• •••• {{ card.last4 }}</p>
                    <div class="card-bottom">
                        <div>
                            <span class="card-caption">Titular</span>
                            <span class="card-value">{{ card.holder || "—" }}</span>
                        </div>
                        <div>
                            <span class="card-caption">Vence</span>
                            <span class="card-value">{{ card.expiration }}</span>
                        </div>
                    </div>
                </article>
                <button type="button" class="remove" :disabled="removing === card.id" @click="remove(card)">
                    {{ removing === card.id ? "Eliminando…" : "Eliminar tarjeta" }}
                </button>
            </li>
        </ul>

        <div v-else class="empty">
            <p class="empty-title">No tienes tarjetas guardadas</p>
            <p class="empty-text">Al pagar con tarjeta puedes marcar "Guardar esta tarjeta" para usarla en tus próximas compras.</p>
        </div>
    </ProfileShell>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import ProfileShell from "@/components/profile/ProfileShell.vue";

defineProps({
    cards: { type: Array, default: () => [] },
});

const BRANDS = { visa: "Visa", mastercard: "Mastercard", american_express: "American Express", carnet: "Carnet" };

const removing = ref(null);

function brandKey(brand) {
    return String(brand ?? "").toLowerCase().replace(/[^a-z]/g, "") || "card";
}

function brandLabel(brand) {
    return BRANDS[String(brand ?? "").toLowerCase()] ?? "Tarjeta";
}

function remove(card) {
    if (!window.confirm(`¿Eliminar la tarjeta terminación ${card.last4}?`)) return;

    removing.value = card.id;
    router.delete(route("profile.cards.delete", card.id), {
        preserveScroll: true,
        onFinish: () => (removing.value = null),
    });
}
</script>

<style scoped>
.lead {
    max-width: 640px;
    margin-bottom: var(--space-6);
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr));
    gap: var(--space-6);
    list-style: none;
}

.cards li {
    display: grid;
    gap: 10px;
}

.card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    aspect-ratio: 1.586;
    padding: var(--space-5);
    border-radius: var(--radius-lg);
    background: linear-gradient(135deg, #1f2937, #0b0f19);
    box-shadow: var(--shadow-md);
    color: #fff;
}

.card--visa {
    background: linear-gradient(135deg, #1a3a8f, #0b1a45);
}

.card--mastercard {
    background: linear-gradient(135deg, #3b1d0f, #111827);
}

.card--americanexpress {
    background: linear-gradient(135deg, #0e5f6b, #08303a);
}

.card-top,
.card-bottom {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.card-brand {
    font-size: var(--text-lg);
    font-weight: 800;
    letter-spacing: 0.02em;
}

.card-bank {
    color: rgba(255, 255, 255, 0.7);
    font-size: var(--text-xs);
    font-weight: 600;
    text-transform: uppercase;
}

.card-number {
    font-size: var(--text-xl);
    font-weight: 700;
    letter-spacing: 0.08em;
    font-variant-numeric: tabular-nums;
}

.card-caption {
    display: block;
    color: rgba(255, 255, 255, 0.6);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.card-value {
    font-size: var(--text-sm);
    font-weight: 700;
    text-transform: uppercase;
}

.remove {
    justify-self: start;
    padding: 6px 0;
    border: none;
    background: none;
    color: #fca5a5;
    font: inherit;
    font-size: var(--text-sm);
    font-weight: 700;
    cursor: pointer;
}

.remove:disabled {
    opacity: 0.6;
}

.remove:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.empty {
    display: grid;
    gap: 6px;
    padding: var(--space-10) var(--space-6);
    border: 1px dashed var(--color-border-strong);
    border-radius: var(--radius-xl);
    text-align: center;
}

.empty-title {
    font-size: var(--text-lg);
    font-weight: 800;
}

.empty-text {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}
</style>
