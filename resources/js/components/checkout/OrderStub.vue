<template>
    <section class="stub" aria-label="Resumen de tu compra">
        <header class="stub-head">
            <p class="stub-event">{{ event.name }}</p>
            <p v-if="when" class="stub-meta">{{ when }}</p>
            <p v-if="event.venue" class="stub-meta">{{ event.venue }}<template v-if="event.city">, {{ event.city }}</template></p>
        </header>

        <div class="stub-perforation" aria-hidden="true" />

        <div class="stub-body">
            <ul class="stub-lines">
                <li v-for="(line, index) in quote.lines" :key="`${line.label}-${index}`">
                    <span class="line-main">
                        <span class="line-seat">{{ line.label }}</span>
                        <span class="line-zone">{{ line.locality }}<template v-if="line.type && line.type !== 'Regular'"> · {{ line.type }}</template></span>
                    </span>
                    <span class="line-price">{{ line.courtesy ? "Cortesía" : money(line.price) }}</span>
                </li>
            </ul>

            <dl class="stub-breakdown">
                <div><dt>Boletos</dt><dd>{{ money(quote.subtotal) }}</dd></div>
                <div><dt>Cargo por servicio</dt><dd>{{ money(quote.service) }}</dd></div>
                <div><dt>{{ quote.installments ? `Cargo ${quote.installments} meses sin intereses` : "Cargo por pago en línea" }}</dt><dd>{{ money(quote.payment) }}</dd></div>
                <div v-if="quote.parking"><dt>{{ quote.parking.label }}</dt><dd>{{ money(quote.parking.amount) }}</dd></div>
                <div><dt>Envío digital</dt><dd>{{ money(quote.delivery) }}</dd></div>
                <div v-if="quote.defender > 0"><dt>Ticket Defender</dt><dd>{{ money(quote.defender) }}</dd></div>
                <div v-if="quote.discount > 0" class="is-discount">
                    <dt>{{ quote.coupon ? `Código ${quote.coupon.code}` : "Descuento" }}</dt>
                    <dd>−{{ money(quote.discount) }}</dd>
                </div>
            </dl>

            <slot />

            <div class="stub-total">
                <span>Total</span>
                <strong>{{ money(quote.total) }}</strong>
            </div>

            <p v-if="quote.installments" class="stub-note">
                {{ quote.installments }} pagos de {{ money(quote.total / quote.installments) }}
            </p>

            <slot name="footer" />
        </div>
    </section>
</template>

<script setup>
import { computed } from "vue";
import { money } from "./api.js";

const props = defineProps({
    event: { type: Object, required: true },
    func: { type: Object, default: null },
    quote: { type: Object, required: true },
});

const when = computed(() => [props.func?.date, props.func?.time].filter(Boolean).join(" · "));
</script>

<style scoped>
.stub {
    --notch: 14px;
    background: var(--color-surface-1);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.stub-head {
    padding: 20px 22px 18px;
    background:
        linear-gradient(135deg, var(--color-brand-dim), transparent 70%),
        var(--color-surface-2);
}

.stub-event {
    font-size: var(--text-xl);
    font-weight: 800;
    line-height: 1.2;
    text-wrap: balance;
}

.stub-meta {
    margin-top: 4px;
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.stub-perforation {
    position: relative;
    height: calc(var(--notch) * 2);
    margin-block: calc(var(--notch) * -1);
    background:
        radial-gradient(circle at 0 50%, var(--color-bg) var(--notch), transparent calc(var(--notch) + 0.5px)) left / 51% 100% no-repeat,
        radial-gradient(circle at 100% 50%, var(--color-bg) var(--notch), transparent calc(var(--notch) + 0.5px)) right / 51% 100% no-repeat;
}

.stub-perforation::after {
    content: "";
    position: absolute;
    inset: 50% calc(var(--notch) + 6px) auto;
    border-top: 2px dashed var(--color-border-strong);
}

.stub-body {
    padding: 22px;
}

.stub-lines {
    list-style: none;
    margin: 0 0 16px;
    padding: 0;
    display: grid;
    gap: 10px;
}

.stub-lines li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: baseline;
}

.line-main {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.line-seat {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.line-zone {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
}

.line-price {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.stub-breakdown {
    display: grid;
    gap: 6px;
    padding-top: 14px;
    border-top: 1px solid var(--color-border);
    font-size: var(--text-sm);
}

.stub-breakdown div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.stub-breakdown dt {
    color: var(--color-text-secondary);
}

.stub-breakdown dd {
    font-variant-numeric: tabular-nums;
}

.stub-breakdown .is-discount dd,
.stub-breakdown .is-discount dt {
    color: var(--color-brand);
}

.stub-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border-strong);
}

.stub-total span {
    font-weight: 700;
}

.stub-total strong {
    font-size: var(--text-3xl);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.stub-note {
    margin-top: 4px;
    text-align: right;
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}
</style>
