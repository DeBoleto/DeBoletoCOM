<template>
    <div class="race">
        <TransitionGroup tag="ol" name="runner" class="runner-list">
            <li
                v-for="(participant, index) in participants"
                :id="`participante-${index}`"
                :key="participant.key"
                class="runner"
                :style="{ '--accent': accentFor(participant.localidad) }"
            >
                <span class="runner-accent" aria-hidden="true" />
                <header class="runner-head">
                    <span class="runner-number" aria-hidden="true">{{ index + 1 }}</span>
                    <h3 class="runner-title">{{ titleFor(participant, index) }}</h3>
                    <button
                        v-if="participants.length > 1"
                        type="button"
                        class="runner-remove"
                        :aria-label="`Quitar al participante ${index + 1}`"
                        @click="emit('remove', index)"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                        Quitar
                    </button>
                </header>

                <div v-if="questions.length" class="runner-fields">
                    <label
                        v-for="question in questions"
                        :key="question.id"
                        class="field"
                        :class="{ 'field--wide': question.type === 'texto' && wideFields.includes(question.field), 'has-error': errorFor(index, question.id) }"
                    >
                        <span class="field-label">
                            {{ question.label }}
                            <span v-if="question.required" class="field-required" aria-hidden="true">*</span>
                        </span>

                        <select
                            v-if="question.type === 'seleccion'"
                            v-model="participant.respuestas[question.id]"
                            :required="question.required"
                            :aria-invalid="Boolean(errorFor(index, question.id))"
                        >
                            <option value="">Elige una opción</option>
                            <option v-for="option in question.options" :key="option.id" :value="String(option.id)">{{ option.label }}</option>
                        </select>
                        <input
                            v-else
                            v-model.trim="participant.respuestas[question.id]"
                            :type="inputType(question)"
                            :inputmode="question.type === 'numero' ? 'numeric' : undefined"
                            :autocomplete="autocomplete(question, index)"
                            :max="question.type === 'fecha' ? today : undefined"
                            :maxlength="question.type === 'texto' ? 200 : undefined"
                            :required="question.required"
                            :aria-invalid="Boolean(errorFor(index, question.id))"
                        />

                        <span v-if="errorFor(index, question.id)" class="field-error">{{ errorFor(index, question.id) }}</span>
                    </label>
                </div>

                <footer class="runner-foot" :class="{ 'has-error': errorFor(index, 'localidad') }">
                    <label class="zone">
                        <span class="field-label">Zona del participante <span class="field-required" aria-hidden="true">*</span></span>
                        <select v-model.number="participant.localidad" required :aria-invalid="Boolean(errorFor(index, 'localidad'))">
                            <option :value="null" disabled>Elige la zona</option>
                            <option
                                v-for="locality in localities"
                                :key="locality.id"
                                :value="locality.id"
                                :disabled="isFull(locality, participant)"
                            >
                                {{ locality.name }} · {{ money(locality.price) }}{{ isFull(locality, participant) ? " · Agotado" : "" }}
                            </option>
                        </select>
                    </label>
                    <span v-if="participant.localidad" class="zone-price">{{ money(priceFor(participant.localidad)) }}</span>
                    <span v-if="errorFor(index, 'localidad')" class="field-error">{{ errorFor(index, 'localidad') }}</span>
                </footer>
            </li>
        </TransitionGroup>

        <button type="button" class="runner-add" :disabled="participants.length >= maxTickets || allFull" @click="emit('add')">
            <span class="runner-add-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
            </span>
            <span>
                <strong>Agregar participante</strong>
                <small v-if="participants.length >= maxTickets">Llegaste al máximo de {{ maxTickets }} boletos por compra</small>
                <small v-else-if="allFull">Ya no quedan lugares disponibles</small>
                <small v-else>Cada participante es un boleto</small>
            </span>
        </button>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { money } from "@/components/checkout/api.js";

const props = defineProps({
    participants: { type: Array, required: true },
    questions: { type: Array, default: () => [] },
    localities: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    maxTickets: { type: Number, required: true },
    palette: { type: Array, required: true },
});

const emit = defineEmits(["add", "remove"]);

const wideFields = ["nombre", "nombre_emergencia", "correo"];
const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

const nameQuestion = computed(() => props.questions.find((question) => question.field === "nombre"));

const allFull = computed(() => props.localities.every((locality) => countIn(locality.id) >= locality.remaining));

function countIn(localityId) {
    return props.participants.filter((participant) => participant.localidad === localityId).length;
}

function isFull(locality, participant) {
    return participant.localidad !== locality.id && countIn(locality.id) >= locality.remaining;
}

function accentFor(localityId) {
    const index = props.localities.findIndex((locality) => locality.id === localityId);
    return index === -1 ? "var(--color-border-strong)" : props.palette[index % props.palette.length];
}

function priceFor(localityId) {
    return props.localities.find((locality) => locality.id === localityId)?.price ?? 0;
}

function titleFor(participant, index) {
    const name = nameQuestion.value ? participant.respuestas[nameQuestion.value.id] : "";
    return name || `Participante ${index + 1}`;
}

function errorFor(index, key) {
    return key === "localidad" ? props.errors[`participantes.${index}.localidad`] : props.errors[`participantes.${index}.respuestas.${key}`];
}

function inputType(question) {
    if (question.type === "numero") return "number";
    if (question.type === "fecha") return "date";
    if (question.field === "correo") return "email";
    if (question.field === "telefono" || question.field === "telefono_emergencia") return "tel";
    return "text";
}

function autocomplete(question, index) {
    if (index > 0 && question.field !== null) return "off";
    return { nombre: "name", correo: "email", telefono: "tel", fecha_nacimiento: "bday" }[question.field] ?? "off";
}
</script>

<style scoped>
.race {
    display: grid;
    gap: 12px;
}

.runner-list {
    display: grid;
    gap: 12px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.runner {
    position: relative;
    display: grid;
    gap: 14px;
    padding: 16px 16px 16px 22px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-lg);
    background: var(--color-surface-2);
    overflow: hidden;
    transition: border-color var(--transition-base);
}

.runner-accent {
    position: absolute;
    inset: 0 auto 0 0;
    width: 6px;
    background: var(--accent);
    transition: background var(--transition-base);
}

.runner-head {
    display: flex;
    align-items: center;
    gap: 10px;
}

.runner-number {
    display: grid;
    place-items: center;
    flex: none;
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: var(--accent);
    color: #0b0b10;
    font-size: var(--text-sm);
    font-weight: 800;
}

.runner-title {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    font-size: var(--text-base);
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.runner-remove {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 36px;
    padding: 0 12px;
    border: 1px solid var(--color-border-strong);
    border-radius: 999px;
    background: transparent;
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
    font-weight: 700;
    cursor: pointer;
    transition: color var(--transition-base), border-color var(--transition-base);
}

.runner-remove:hover {
    border-color: #fca5a5;
    color: #fca5a5;
}

.runner-fields {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.field,
.zone {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.field-label {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
    font-weight: 700;
}

.field-required {
    color: var(--color-brand);
}

.field input,
.field select,
.zone select {
    width: 100%;
    min-height: 44px;
    padding: 0 12px;
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-md);
    background: var(--color-surface-1);
    color: var(--color-text-primary);
    font: inherit;
    color-scheme: dark;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}

.field input:focus,
.field select:focus,
.zone select:focus {
    border-color: var(--color-brand);
    outline: none;
    box-shadow: 0 0 0 3px rgba(78, 203, 160, 0.25);
}

.has-error input,
.has-error select {
    border-color: #f87171;
}

.field-error {
    color: #fca5a5;
    font-size: var(--text-xs);
    font-weight: 600;
}

.runner-foot {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: end;
    gap: 6px 12px;
    padding-top: 14px;
    border-top: 1px dashed var(--color-border-strong);
}

.runner-foot .field-error {
    grid-column: 1 / -1;
}

.zone-price {
    min-height: 44px;
    display: grid;
    align-items: center;
    color: var(--color-brand);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.runner-add {
    display: flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 16px;
    border: 1.5px dashed var(--color-border-strong);
    border-radius: var(--radius-lg);
    background: transparent;
    color: var(--color-text-primary);
    text-align: left;
    cursor: pointer;
    transition: border-color var(--transition-base), background var(--transition-base);
}

.runner-add:hover:not(:disabled) {
    border-color: var(--color-brand);
    background: rgba(78, 203, 160, 0.06);
}

.runner-add:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.runner-add-icon {
    display: grid;
    place-items: center;
    flex: none;
    width: 36px;
    height: 36px;
    border-radius: 999px;
    background: rgba(78, 203, 160, 0.15);
    color: var(--color-brand);
}

.runner-add span:last-child {
    display: grid;
    gap: 2px;
}

.runner-add small {
    color: var(--color-text-secondary);
    font-size: var(--text-xs);
}

.runner-enter-active,
.runner-leave-active {
    transition: opacity 220ms ease, transform 220ms ease;
}

.runner-enter-from,
.runner-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

@media (min-width: 640px) {
    .runner-fields {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .field--wide {
        grid-column: 1 / -1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .runner-enter-active,
    .runner-leave-active {
        transition: none;
    }
}
</style>
