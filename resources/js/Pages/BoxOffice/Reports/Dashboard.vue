<template>
    <BackOfficeLayout :title="`Dashboard · ${event.name}`">
        <template #actions>
            <Link :href="route('boxoffice.reports.index')" class="inline-flex items-center gap-1.5 rounded-lg border border-bo-line px-3 py-2 text-sm font-medium hover:border-bo-slate">
                <BoIcon name="file" :size="16" />
                Reportes
            </Link>
        </template>

        <div class="grid gap-4">
            <header class="rounded-xl border-l-4 border-bo-green bg-bo-ink px-5 py-4 text-white">
                <p class="text-xs font-semibold tracking-widest text-white/60 uppercase">Desempeño de ventas</p>
                <h2 class="mt-1 text-xl font-semibold">{{ event.name }}</h2>
                <p class="mt-0.5 text-sm text-white/70">
                    {{ [event.venue, event.city].filter(Boolean).join(", ") }}<template v-if="event.date"> · <span class="tabular">{{ event.date }}</span></template>
                    · Actualizado {{ generatedAt }}
                </p>
            </header>

            <div class="grid gap-4 lg:grid-cols-3">
                <section class="grid content-start gap-1 rounded-xl border border-bo-line bg-bo-card p-5" aria-labelledby="total-title">
                    <h3 id="total-title" class="text-xs font-semibold tracking-widest text-bo-slate uppercase">Boletos vendidos</h3>
                    <p class="tabular text-4xl font-semibold">{{ number(totals.tickets) }}</p>
                    <p class="tabular text-lg text-bo-slate">{{ money(totals.amount) }}</p>
                    <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-bo-line pt-4 text-sm">
                        <div>
                            <dt class="text-bo-muted">Zonas con venta</dt>
                            <dd class="tabular font-semibold">{{ zones.length }}</dd>
                        </div>
                        <div>
                            <dt class="text-bo-muted">Precio promedio</dt>
                            <dd class="tabular font-semibold">{{ money(totals.tickets ? totals.amount / totals.tickets : 0) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-xl border border-bo-line bg-bo-card p-5 lg:col-span-2" aria-labelledby="zones-title">
                    <h3 id="zones-title" class="text-xs font-semibold tracking-widest text-bo-slate uppercase">Zonas con más ventas</h3>

                    <p v-if="!zones.length" class="py-10 text-center text-sm text-bo-slate">Este evento todavía no tiene boletos vendidos.</p>

                    <div v-else class="mt-4 grid items-center gap-6 sm:grid-cols-[200px_1fr]">
                        <svg viewBox="0 0 200 200" class="mx-auto w-full max-w-[200px]" role="img" :aria-label="`Ventas por zona: ${slices.map((slice) => `${slice.name} ${percent(slice.share)}`).join(', ')}`">
                            <circle cx="100" cy="100" :r="RADIUS" fill="none" :stroke="'var(--color-bo-line)'" :stroke-width="STROKE" />
                            <circle
                                v-for="slice in slices"
                                :key="slice.name"
                                cx="100"
                                cy="100"
                                :r="RADIUS"
                                fill="none"
                                :stroke="slice.color"
                                :stroke-width="STROKE"
                                :stroke-dasharray="`${slice.length} ${CIRCUMFERENCE - slice.length}`"
                                :stroke-dashoffset="-slice.offset"
                                transform="rotate(-90 100 100)"
                            />
                            <template v-for="slice in slices" :key="`${slice.name}-label`">
                                <text v-if="slice.share >= 0.06" :x="slice.labelX" :y="slice.labelY" text-anchor="middle" dominant-baseline="central" fill="#ffffff" font-size="11" font-weight="600">
                                    {{ percent(slice.share) }}
                                </text>
                            </template>
                        </svg>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-bo-slate">
                                        <th class="pb-2 font-semibold">Zona</th>
                                        <th class="pb-2 text-right font-semibold">Boletos</th>
                                        <th class="pb-2 text-right font-semibold">%</th>
                                        <th class="pb-2 text-right font-semibold">Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="slice in slices" :key="slice.name" class="border-t border-bo-line/60">
                                        <td class="py-1.5">
                                            <span class="flex items-center gap-2">
                                                <span class="size-2.5 shrink-0 rounded-full" :style="{ background: slice.color }" aria-hidden="true" />
                                                {{ slice.name }}
                                            </span>
                                        </td>
                                        <td class="tabular py-1.5 text-right">{{ number(slice.tickets) }}</td>
                                        <td class="tabular py-1.5 text-right text-bo-slate">{{ percent(slice.share) }}</td>
                                        <td class="tabular py-1.5 text-right">{{ money(slice.amount) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-bo-ink font-semibold">
                                        <td class="pt-2">Total</td>
                                        <td class="tabular pt-2 text-right">{{ number(totals.tickets) }}</td>
                                        <td class="tabular pt-2 text-right">100%</td>
                                        <td class="tabular pt-2 text-right">{{ money(totals.amount) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <section v-for="card in trendCards" :key="card.key" class="grid content-start gap-1 rounded-xl border border-bo-line bg-bo-card p-5" :aria-labelledby="`${card.key}-title`">
                    <h3 :id="`${card.key}-title`" class="text-xs font-semibold tracking-widest text-bo-slate uppercase">{{ card.title }}</h3>
                    <p class="tabular text-3xl font-semibold">{{ card.value }}</p>
                    <svg viewBox="0 0 300 90" preserveAspectRatio="none" class="mt-3 h-24 w-full" role="img" :aria-label="`${card.title} por día`">
                        <line v-for="y in [10, 45, 80]" :key="y" x1="0" x2="300" :y1="y" :y2="y" stroke="var(--color-bo-line)" stroke-width="1" vector-effect="non-scaling-stroke" />
                        <path :d="area(card.series)" fill="var(--color-bo-green-soft)" />
                        <path :d="line(card.series)" fill="none" stroke="var(--color-bo-green)" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                    </svg>
                    <div class="flex justify-between text-xs text-bo-muted">
                        <span class="tabular">{{ shortDate(recent.dates[0]) }}</span>
                        <span class="tabular">{{ shortDate(recent.dates[recent.dates.length - 1]) }}</span>
                    </div>
                    <p v-if="card.delta !== undefined" class="mt-2 flex items-baseline justify-between border-t border-bo-line pt-2 text-xs text-bo-slate">
                        <span>vs. {{ period }} días anteriores</span>
                        <span class="tabular text-sm font-semibold" :class="card.delta === null ? 'text-bo-muted' : card.delta >= 0 ? 'text-bo-green' : 'text-bo-sold'">
                            {{ card.delta === null ? "—" : `${card.delta >= 0 ? "+" : ""}${Math.round(card.delta * 100)}%` }}
                        </span>
                    </p>
                    <p v-else class="mt-2 flex items-baseline justify-between border-t border-bo-line pt-2 text-xs text-bo-slate">
                        <span>Mejor día</span>
                        <span class="tabular text-sm font-semibold text-bo-ink">{{ bestDay }}</span>
                    </p>
                </section>
            </div>

            <section class="rounded-xl border border-bo-line bg-bo-card p-5" aria-labelledby="daily-title">
                <h3 id="daily-title" class="text-xs font-semibold tracking-widest text-bo-slate uppercase">Ventas por zona · últimos {{ period }} días</h3>

                <p v-if="!recent.zones.length" class="py-8 text-center text-sm text-bo-slate">No hubo ventas en los últimos {{ period }} días.</p>

                <div v-else class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="text-bo-slate">
                                <th class="pb-2 text-left font-semibold">Zona</th>
                                <th v-for="date in recent.dates" :key="date" class="tabular pb-2 text-right font-semibold">{{ shortDate(date) }}</th>
                                <th class="pb-2 pl-3 text-right font-semibold">Boletos</th>
                                <th class="pb-2 text-right font-semibold">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="zone in recent.zones" :key="zone.name" class="border-t border-bo-line/60">
                                <td class="py-1.5 pr-3">
                                    <span class="flex items-center gap-2">
                                        <span class="size-2.5 shrink-0 rounded-full" :style="{ background: colorFor(zone.name) }" aria-hidden="true" />
                                        {{ zone.name }}
                                    </span>
                                </td>
                                <td v-for="(count, index) in zone.days" :key="index" class="tabular py-1.5 text-right" :class="count ? '' : 'text-bo-blocked'">{{ count || "·" }}</td>
                                <td class="tabular py-1.5 pl-3 text-right font-semibold">{{ number(zone.tickets) }}</td>
                                <td class="tabular py-1.5 text-right">{{ money(zone.amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-bo-ink font-semibold">
                                <td class="pt-2">Total</td>
                                <td v-for="(count, index) in recent.days" :key="index" class="tabular pt-2 text-right">{{ count }}</td>
                                <td class="tabular pt-2 pl-3 text-right">{{ number(recent.tickets) }}</td>
                                <td class="tabular pt-2 text-right">{{ money(recent.amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import { money } from "@/components/BoxOffice/format.js";

const props = defineProps({
    event: { type: Object, required: true },
    period: { type: Number, required: true },
    generatedAt: { type: String, required: true },
    totals: { type: Object, required: true },
    zones: { type: Array, required: true },
    recent: { type: Object, required: true },
});

const COLORS = ["#2bb39a", "#e0533d", "#8b5cf6", "#f08a4b", "#e6b43c", "#a3b83a", "#2f86c6", "#ec4899", "#64748b"];
const MAX_SLICES = 8;
const RADIUS = 70;
const STROKE = 46;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const OTHERS = "Otras zonas";

const integer = new Intl.NumberFormat("es-MX");
const number = (value) => integer.format(value || 0);
const percent = (share) => `${(share * 100).toFixed(1)}%`;

const slices = computed(() => {
    const total = props.totals.tickets || 1;
    const top = props.zones.slice(0, MAX_SLICES);
    const rest = props.zones.slice(MAX_SLICES);
    const list = rest.length
        ? [...top, { name: OTHERS, tickets: rest.reduce((sum, zone) => sum + zone.tickets, 0), amount: rest.reduce((sum, zone) => sum + zone.amount, 0) }]
        : top;

    let offset = 0;

    return list.map((zone, index) => {
        const share = zone.tickets / total;
        const length = share * CIRCUMFERENCE;
        const angle = ((offset + length / 2) / CIRCUMFERENCE) * 2 * Math.PI - Math.PI / 2;
        const slice = {
            ...zone,
            share,
            length,
            offset,
            color: zone.name === OTHERS ? COLORS[COLORS.length - 1] : COLORS[index % (COLORS.length - 1)],
            labelX: 100 + RADIUS * Math.cos(angle),
            labelY: 100 + RADIUS * Math.sin(angle),
        };
        offset += length;
        return slice;
    });
});

function colorFor(name) {
    return slices.value.find((slice) => slice.name === name)?.color ?? COLORS[COLORS.length - 1];
}

function change(current, previous) {
    return previous ? (current - previous) / previous : null;
}

const bestDay = computed(() => {
    const max = Math.max(0, ...props.recent.days);
    if (!max) return "—";
    return `${shortDate(props.recent.dates[props.recent.days.indexOf(max)])} · ${number(max)}`;
});

const trendCards = computed(() => [
    {
        key: "tickets",
        title: `Boletos · últimos ${props.period} días`,
        value: number(props.recent.tickets),
        series: props.recent.days,
        delta: change(props.recent.tickets, props.recent.previousTickets),
    },
    {
        key: "amount",
        title: `Ingresos · últimos ${props.period} días`,
        value: money(props.recent.amount),
        series: props.recent.amounts,
        delta: change(props.recent.amount, props.recent.previousAmount),
    },
    {
        key: "average",
        title: "Promedio diario",
        value: `${number(Math.round(props.recent.tickets / props.period))} boletos`,
        series: props.recent.days,
    },
]);

function points(series) {
    const max = Math.max(1, ...series);
    const step = series.length > 1 ? 300 / (series.length - 1) : 300;
    return series.map((value, index) => [index * step, 85 - (value / max) * 75]);
}

function line(series) {
    return points(series).map(([x, y], index) => `${index ? "L" : "M"}${x.toFixed(1)},${y.toFixed(1)}`).join(" ");
}

function area(series) {
    return `${line(series)} L300,90 L0,90 Z`;
}

function shortDate(iso) {
    if (!iso) return "";
    const [, month, day] = iso.split("-");
    return `${day}/${month}`;
}
</script>
