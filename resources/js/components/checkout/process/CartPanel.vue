<template>
    <div class="cart">
        <header class="cart-head">
            <h2 :id="titleId" class="cart-title">Resumen de la compra</h2>
            <button v-if="count > 0" type="button" class="cart-clear" @click="emit('clear')">Vaciar</button>
        </header>

        <Transition name="fade" mode="out-in">
            <div v-if="count === 0" key="empty" class="cart-empty">
                <span class="cart-empty-icon" aria-hidden="true">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a3 3 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a3 3 0 0 0 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1z" /><path d="M14 5v2M14 11v2M14 17v2" /></svg>
                </span>
                <p class="cart-empty-title">Aún no eliges boletos</p>
                <p class="cart-empty-text">{{ emptyHint }}</p>
            </div>

            <div v-else key="full" class="cart-body">
                <TransitionGroup tag="div" name="group" class="cart-groups">
                    <section v-for="group in groups" :key="group.key" class="cart-group">
                        <button type="button" class="group-head" @click="emit('focus', group)">
                            <span class="group-swatch" :style="{ background: group.color }" aria-hidden="true" />
                            <span class="group-name">{{ group.name }}</span>
                            <span class="group-count">{{ group.tickets.length }} × {{ money(group.unitPrice) }}</span>
                        </button>

                        <TransitionGroup tag="ul" name="ticket" class="ticket-list">
                            <li v-for="ticket in group.tickets" :key="ticket.id" class="ticket">
                                <div class="ticket-row">
                                    <span class="ticket-seat">{{ ticket.label }}</span>
                                    <span v-if="ticket.sub" class="ticket-sub">{{ ticket.sub }}</span>
                                    <span class="ticket-price">{{ money(ticket.price) }}</span>
                                    <button
                                        type="button"
                                        class="ticket-remove"
                                        :aria-label="`Quitar ${ticket.label} de ${group.name}`"
                                        @click="emit('remove', group, ticket)"
                                    >
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                                    </button>
                                </div>
                                <label v-if="ticket.specialsAllowed" class="ticket-select">
                                    <span class="visually-hidden">Tipo de boleto para {{ ticket.label }}</span>
                                    <select :value="ticket.special ?? ''" @change="emit('special', group, ticket, $event.target.value ? Number($event.target.value) : null)">
                                        <option value="">Regular · {{ money(group.unitPrice) }}</option>
                                        <option v-for="special in group.specials" :key="special.id" :value="special.id">
                                            {{ special.name }} · {{ money(special.price) }}
                                        </option>
                                    </select>
                                </label>
                                <label v-if="ticket.extraChairAvailable" class="ticket-check">
                                    <input type="checkbox" :checked="ticket.extraChair" @change="emit('extra', group, ticket, $event.target.checked)" />
                                    Silla adicional (+{{ money(group.extraChairPrice) }})
                                </label>
                            </li>
                        </TransitionGroup>
                    </section>
                </TransitionGroup>

                <label v-if="parking" class="addon" :class="{ 'is-on': parkingSelected }">
                    <input type="checkbox" :checked="parkingSelected" @change="emit('parking', $event.target.checked)" />
                    <span class="addon-icon" aria-hidden="true">P</span>
                    <span class="addon-text">
                        <strong>Agregar {{ parking.label.toLowerCase() }}</strong>
                        <span>{{ money(parking.price) }} más cargos</span>
                    </span>
                </label>
            </div>
        </Transition>

        <footer class="cart-foot">
            <div class="meter" :aria-label="`${count} de ${maxTickets} boletos permitidos`">
                <div class="meter-bar"><span :style="{ width: `${Math.min(100, (count / maxTickets) * 100)}%` }" /></div>
                <span class="meter-text">{{ count }}/{{ maxTickets }} boletos</span>
            </div>

            <div class="totals">
                <span>Subtotal</span>
                <strong aria-live="polite">{{ money(shownTotal) }}</strong>
            </div>
            <p class="totals-hint">Los cargos por servicio y promociones se calculan en el pago.</p>

            <button type="button" class="cta" :disabled="count === 0 || submitting" @click="emit('continue')">
                <span v-if="submitting" class="spinner" aria-hidden="true" />
                {{ submitting ? "Apartando tus lugares…" : "Continuar al pago" }}
                <svg v-if="!submitting" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </button>
            <button type="button" class="cancel" :disabled="submitting" @click="emit('cancel')">Cancelar compra</button>
        </footer>
    </div>
</template>

<script setup>
import { onUnmounted, ref, watch } from "vue";
import { money } from "@/components/checkout/api.js";

const props = defineProps({
    titleId: { type: String, default: "cart-title" },
    groups: { type: Array, required: true },
    count: { type: Number, required: true },
    total: { type: Number, required: true },
    maxTickets: { type: Number, required: true },
    parking: { type: Object, default: null },
    parkingSelected: { type: Boolean, default: false },
    submitting: { type: Boolean, default: false },
    emptyHint: { type: String, default: "Toca una zona del mapa para elegir tus lugares." },
});

const emit = defineEmits(["remove", "special", "extra", "parking", "continue", "cancel", "clear", "focus"]);

const shownTotal = ref(props.total);
let frame;

watch(
    () => props.total,
    (target) => {
        cancelAnimationFrame(frame);

        if (window.matchMedia?.("(prefers-reduced-motion: reduce)").matches) {
            shownTotal.value = target;
            return;
        }

        const start = shownTotal.value;
        const began = performance.now();
        const step = (now) => {
            const progress = Math.min(1, (now - began) / 420);
            shownTotal.value = start + (target - start) * (1 - Math.pow(1 - progress, 3));
            if (progress < 1) frame = requestAnimationFrame(step);
        };
        frame = requestAnimationFrame(step);
    },
);

onUnmounted(() => cancelAnimationFrame(frame));
</script>

<style scoped>
.cart {
    display: flex;
    flex-direction: column;
    min-height: 0;
    height: 100%;
}

.cart-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.cart-title {
    font-size: var(--text-lg);
    font-weight: 800;
}

.cart-clear {
    border: none;
    background: none;
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
    font-weight: 700;
    text-decoration: underline;
    cursor: pointer;
}

.cart-clear:hover {
    color: var(--color-danger);
}

.cart-empty {
    display: grid;
    justify-items: center;
    gap: 6px;
    padding: 28px 12px;
    border: 1px dashed var(--color-border-strong);
    border-radius: var(--radius-lg);
    text-align: center;
}

.cart-empty-icon {
    display: grid;
    place-items: center;
    width: 64px;
    height: 64px;
    margin-bottom: 4px;
    border-radius: var(--radius-full);
    background: var(--color-brand-dim);
    color: var(--color-brand);
    animation: float 3.2s ease-in-out infinite;
}

.cart-empty-title {
    font-weight: 800;
}

.cart-empty-text {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.cart-body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    margin-right: -6px;
    padding-right: 6px;
}

.cart-groups {
    display: grid;
    gap: 12px;
}

.cart-group {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface-2);
    overflow: hidden;
}

.group-head {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 10px 12px;
    border: none;
    border-bottom: 1px solid var(--color-border);
    background: rgba(255, 255, 255, 0.02);
    color: var(--color-text-primary);
    text-align: left;
    cursor: pointer;
}

.group-head:hover {
    background: rgba(78, 203, 160, 0.08);
}

.group-swatch {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    flex-shrink: 0;
}

.group-name {
    flex: 1;
    font-weight: 800;
    font-size: var(--text-sm);
}

.group-count {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
    font-variant-numeric: tabular-nums;
}

.ticket-list {
    list-style: none;
    margin: 0;
    padding: 4px 0;
}

.ticket {
    padding: 6px 12px;
}

.ticket-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ticket-seat {
    display: inline-flex;
    align-items: center;
    min-height: 26px;
    padding: 0 9px;
    border-radius: 6px;
    background: var(--color-brand);
    color: #06291d;
    font-weight: 800;
    font-size: var(--text-xs);
    font-variant-numeric: tabular-nums;
}

.ticket-sub {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
}

.ticket-price {
    margin-left: auto;
    font-size: var(--text-sm);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.ticket-remove {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border: none;
    border-radius: var(--radius-full);
    background: transparent;
    color: var(--color-text-muted);
    cursor: pointer;
    transition: background var(--transition-fast), color var(--transition-fast);
}

.ticket-remove:hover,
.ticket-remove:focus-visible {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
    outline: none;
}

.ticket-select select {
    width: 100%;
    margin-top: 6px;
    min-height: 36px;
    padding: 0 10px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-sm);
    background: var(--color-surface-3);
    color: var(--color-text-primary);
    font-size: var(--text-xs);
}

.ticket-check {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 6px;
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
    cursor: pointer;
}

.ticket-check input,
.addon input {
    accent-color: var(--color-brand);
}

.addon {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 12px;
    padding: 12px;
    border: 1px dashed var(--color-border-strong);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: border-color var(--transition-fast), background var(--transition-fast);
}

.addon.is-on {
    border-style: solid;
    border-color: var(--color-brand);
    background: var(--color-brand-dim);
}

.addon input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.addon:focus-within {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.addon-icon {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: #2563eb;
    color: #fff;
    font-weight: 900;
}

.addon-text {
    display: flex;
    flex-direction: column;
    font-size: var(--text-sm);
}

.addon-text span {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
}

.cart-foot {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid var(--color-border);
}

.meter {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.meter-bar {
    flex: 1;
    height: 6px;
    border-radius: var(--radius-full);
    background: var(--color-surface-3);
    overflow: hidden;
}

.meter-bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--color-brand), var(--color-accent));
    transition: width 400ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.meter-text {
    color: var(--color-text-muted);
    font-size: var(--text-xs);
    font-variant-numeric: tabular-nums;
}

.totals {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
}

.totals span {
    color: var(--color-text-secondary);
}

.totals strong {
    font-size: var(--text-2xl);
    font-weight: 900;
    font-variant-numeric: tabular-nums;
}

.totals-hint {
    margin-top: 2px;
    color: var(--color-text-muted);
    font-size: var(--text-xs);
}

.cta {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    min-height: 52px;
    margin-top: 14px;
    border: none;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
    color: #06291d;
    font-size: var(--text-base);
    font-weight: 900;
    cursor: pointer;
    overflow: hidden;
    transition: transform var(--transition-fast), filter var(--transition-fast), box-shadow var(--transition-base);
}

.cta:not(:disabled) {
    box-shadow: 0 10px 28px rgba(78, 203, 160, 0.28);
}

.cta:not(:disabled)::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, 0.35) 50%, transparent 70%);
    transform: translateX(-100%);
    animation: shine 2.8s ease-in-out infinite;
}

.cta:hover:not(:disabled) {
    filter: brightness(1.06);
    transform: translateY(-1px);
}

.cta:disabled {
    background: var(--color-surface-3);
    color: var(--color-text-muted);
    cursor: not-allowed;
}

.cta:focus-visible,
.cancel:focus-visible,
.group-head:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.cancel {
    width: 100%;
    margin-top: 8px;
    min-height: 40px;
    border: none;
    background: none;
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
    cursor: pointer;
}

.cancel:hover:not(:disabled) {
    color: var(--color-text-primary);
}

.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(6, 41, 29, 0.3);
    border-top-color: #06291d;
    border-radius: 50%;
    animation: spin 700ms linear infinite;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 180ms ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.ticket-enter-active {
    transition: all 320ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.ticket-leave-active {
    transition: all 200ms ease;
}

.ticket-enter-from {
    opacity: 0;
    transform: translateX(16px) scale(0.96);
}

.ticket-leave-to {
    opacity: 0;
    transform: translateX(-16px);
}

.group-enter-active,
.group-leave-active {
    transition: all 260ms ease;
}

.group-enter-from,
.group-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

@keyframes float {
    50% {
        transform: translateY(-5px) rotate(-6deg);
    }
}

@keyframes shine {
    60%,
    100% {
        transform: translateX(100%);
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cart-empty-icon,
    .cta::after,
    .spinner {
        animation: none;
    }

    .ticket-enter-active,
    .ticket-leave-active,
    .group-enter-active,
    .group-leave-active,
    .meter-bar span,
    .cta {
        transition: none;
    }
}
</style>
