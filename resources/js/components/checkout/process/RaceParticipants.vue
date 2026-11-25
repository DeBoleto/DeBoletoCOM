<template>
    <div class="race">
        <TransitionGroup tag="ol" name="runner" class="runner-list">
            <li
                v-for="(participant, index) in participants"
                :id="`participante-${index}`"
                :key="participant.key"
                class="runner"
                :class="{ 'is-complete': isComplete(participant) }"
                :style="{ '--accent': accentFor(participant.localidad) }"
            >
                <header class="runner-head">
                    <span class="runner-number" aria-hidden="true">
                        <svg v-if="isComplete(participant)" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <div class="runner-heading">
                        <h3 class="runner-title">{{ titleFor(participant, index) }}</h3>
                        <p class="runner-progress">
                            <template v-if="isComplete(participant)">Datos completos</template>
                            <template v-else>{{ answered(participant) }} de {{ totalSteps }} datos</template>
                        </p>
                    </div>
                    <button
                        v-if="participants.length > 1"
                        type="button"
                        class="runner-remove"
                        :aria-label="`Quitar al participante ${index + 1}`"
                        @click="emit('remove', index)"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" /></svg>
                        <span>Quitar</span>
                    </button>
                </header>
                <div class="runner-meter" aria-hidden="true">
                    <span :style="{ width: `${(answered(participant) / totalSteps) * 100}%` }" />
                </div>

                <div class="runner-body">
                    <section v-for="group in groups" :key="group.title" class="runner-section">
                        <h4 class="section-title">{{ group.title }}</h4>
                        <div class="runner-fields">
                            <template v-for="question in group.questions" :key="question.id">
                                <fieldset
                                    v-if="asChips(question)"
                                    class="field field--wide"
                                    :class="{ 'has-error': errorFor(index, question.id) }"
                                >
                                    <legend class="field-label">
                                        {{ question.label }}
                                        <span v-if="!question.required" class="field-optional">Opcional</span>
                                    </legend>
                                    <div class="chips">
                                        <label v-for="option in question.options" :key="option.id" class="chip">
                                            <input
                                                v-model="participant.respuestas[question.id]"
                                                type="radio"
                                                :name="`p${participant.key}-q${question.id}`"
                                                :value="String(option.id)"
                                            />
                                            <span>{{ option.label }}</span>
                                        </label>
                                    </div>
                                    <span v-if="errorFor(index, question.id)" class="field-error">{{ errorFor(index, question.id) }}</span>
                                </fieldset>

                                <label
                                    v-else
                                    class="field"
                                    :class="{ 'field--wide': isWide(question), 'has-error': errorFor(index, question.id) }"
                                >
                                    <span class="field-label">
                                        {{ question.label }}
                                        <span v-if="!question.required" class="field-optional">Opcional</span>
                                    </span>
                                    <select
                                        v-if="question.type === 'seleccion'"
                                        v-model="participant.respuestas[question.id]"
                                        :required="question.required"
                                        :aria-invalid="Boolean(errorFor(index, question.id))"
                                    >
                                        <option value="" disabled>Elige una opción</option>
                                        <option v-for="option in question.options" :key="option.id" :value="String(option.id)">{{ option.label }}</option>
                                    </select>
                                    <input
                                        v-else
                                        v-model.trim="participant.respuestas[question.id]"
                                        v-digits="numeric(question)"
                                        :type="inputType(question)"
                                        :inputmode="inputMode(question)"
                                        :autocomplete="autocomplete(question, index)"
                                        :placeholder="placeholder(question)"
                                        :max="question.type === 'fecha' ? today : undefined"
                                        :maxlength="question.type === 'texto' ? 200 : undefined"
                                        :required="question.required"
                                        :aria-invalid="Boolean(errorFor(index, question.id))"
                                    />
                                    <span v-if="errorFor(index, question.id)" class="field-error">{{ errorFor(index, question.id) }}</span>
                                </label>
                            </template>
                        </div>
                    </section>

                    <fieldset class="runner-section category" :class="{ 'has-error': errorFor(index, 'localidad') }">
                        <legend class="section-title">Categoría del participante</legend>
                        <div class="category-grid">
                            <label
                                v-for="(locality, position) in localities"
                                :key="locality.id"
                                class="category-option"
                                :class="{ 'is-full': isFull(locality, participant) }"
                                :style="{ '--option': palette[position % palette.length] }"
                            >
                                <input
                                    v-model.number="participant.localidad"
                                    type="radio"
                                    :name="`p${participant.key}-categoria`"
                                    :value="locality.id"
                                    :disabled="isFull(locality, participant)"
                                />
                                <span class="category-dot" aria-hidden="true" />
                                <span class="category-text">
                                    <strong>{{ locality.name }}</strong>
                                    <small v-if="isFull(locality, participant)">Agotado</small>
                                    <small v-else-if="spotsLeft(locality) <= 20">Quedan {{ spotsLeft(locality) }}</small>
                                </span>
                                <span class="category-price">{{ money(locality.price) }}</span>
                            </label>
                        </div>
                        <span v-if="errorFor(index, 'localidad')" class="field-error">{{ errorFor(index, 'localidad') }}</span>
                    </fieldset>
                </div>
            </li>
        </TransitionGroup>

        <button type="button" class="runner-add" :disabled="participants.length >= maxTickets || allFull" @click="emit('add')">
            <span class="runner-add-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
            </span>
            <span class="runner-add-text">
                <strong>Agregar otro participante</strong>
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

const EMERGENCY = ["nombre_emergencia", "telefono_emergencia"];
const PLACEHOLDERS = {
    nombre: "Como aparece en su identificación",
    correo: "nombre@correo.com",
    telefono: "10 dígitos",
    nombre_emergencia: "¿A quién avisamos?",
    telefono_emergencia: "10 dígitos",
};

const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

const nameQuestion = computed(() => props.questions.find((question) => question.field === "nombre"));
const required = computed(() => props.questions.filter((question) => question.required));
const totalSteps = computed(() => required.value.length + 1);

const groups = computed(() => {
    const emergency = props.questions.filter((question) => EMERGENCY.includes(question.field) || /emergencia/i.test(question.label));
    const rest = props.questions.filter((question) => !emergency.includes(question));

    return [
        { title: "Datos del participante", questions: rest },
        { title: "Contacto de emergencia", questions: emergency },
    ].filter((group) => group.questions.length > 0);
});

const allFull = computed(() => props.localities.every((locality) => spotsLeft(locality) <= 0));

function countIn(localityId) {
    return props.participants.filter((participant) => participant.localidad === localityId).length;
}

function spotsLeft(locality) {
    return locality.remaining - countIn(locality.id);
}

function isFull(locality, participant) {
    return participant.localidad !== locality.id && spotsLeft(locality) <= 0;
}

function answered(participant) {
    const answers = required.value.filter((question) => String(participant.respuestas[question.id] ?? "").trim() !== "").length;
    return answers + (participant.localidad ? 1 : 0);
}

function isComplete(participant) {
    return answered(participant) === totalSteps.value;
}

function accentFor(localityId) {
    const index = props.localities.findIndex((locality) => locality.id === localityId);
    return index === -1 ? "var(--color-brand)" : props.palette[index % props.palette.length];
}

function titleFor(participant, index) {
    const name = nameQuestion.value ? participant.respuestas[nameQuestion.value.id] : "";
    return name || `Participante ${index + 1}`;
}

function errorFor(index, key) {
    return key === "localidad" ? props.errors[`participantes.${index}.localidad`] : props.errors[`participantes.${index}.respuestas.${key}`];
}

function asChips(question) {
    return question.type === "seleccion" && question.options.length > 0 && question.options.length <= 8 && question.options.every((option) => option.label.length <= 16);
}

function isWide(question) {
    return question.type === "texto" && ["nombre", "correo"].includes(question.field);
}

function inputType(question) {
    if (question.type === "numero") return "number";
    if (question.type === "fecha") return "date";
    if (question.field === "correo") return "email";
    if (question.field === "telefono" || question.field === "telefono_emergencia") return "tel";
    return "text";
}

function numeric(question) {
    return question.type === "numero" || question.field === "telefono" || question.field === "telefono_emergencia";
}

function inputMode(question) {
    if (question.type === "numero") return "numeric";
    if (question.field === "telefono" || question.field === "telefono_emergencia") return "tel";
    return undefined;
}

function placeholder(question) {
    if (question.type === "fecha") return undefined;
    if (question.type === "numero") return "0";
    return PLACEHOLDERS[question.field] ?? "Escribe tu respuesta";
}

function autocomplete(question, index) {
    if (index > 0) return "off";
    return { nombre: "name", correo: "email", telefono: "tel", fecha_nacimiento: "bday" }[question.field] ?? "off";
}
</script>

<style scoped>
.race {
    display: grid;
    gap: 16px;
}

.runner-list {
    display: grid;
    gap: 16px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.runner {
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-xl);
    background: #f8fafc;
    color: #0f172a;
    overflow: hidden;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
}

.runner-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 18px 12px;
    background: #ffffff;
}

.runner-number {
    display: grid;
    place-items: center;
    flex: none;
    width: 36px;
    height: 36px;
    border-radius: 999px;
    background: var(--accent);
    color: #0b0b10;
    font-weight: 800;
    transition: background var(--transition-base);
}

.runner-heading {
    flex: 1;
    min-width: 0;
}

.runner-title {
    overflow: hidden;
    color: #0f172a;
    font-size: var(--text-lg);
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.runner-progress {
    color: #64748b;
    font-size: var(--text-xs);
    font-weight: 600;
}

.runner.is-complete .runner-progress {
    color: #059669;
}

.runner-remove {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 40px;
    padding: 0 14px;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #ffffff;
    color: #475569;
    font-size: var(--text-sm);
    font-weight: 700;
    cursor: pointer;
    transition: color var(--transition-base), border-color var(--transition-base), background var(--transition-base);
}

.runner-remove:hover {
    border-color: #fecaca;
    background: #fef2f2;
    color: #dc2626;
}

.runner-meter {
    height: 4px;
    background: #e2e8f0;
}

.runner-meter span {
    display: block;
    height: 100%;
    background: var(--accent);
    transition: width 300ms ease, background var(--transition-base);
}

.runner-body {
    display: grid;
    gap: 22px;
    padding: 18px;
}

.runner-section {
    display: grid;
    gap: 12px;
    min-width: 0;
    margin: 0;
    padding: 0;
    border: none;
}

.section-title {
    padding: 0;
    color: #334155;
    font-size: var(--text-xs);
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.runner-fields {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}

.field {
    display: grid;
    gap: 6px;
    min-width: 0;
    margin: 0;
    padding: 0;
    border: none;
}

.field-label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0;
    color: #1e293b;
    font-size: var(--text-sm);
    font-weight: 700;
}

.field-optional {
    padding: 1px 8px;
    border-radius: 999px;
    background: #e2e8f0;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
}

.field input,
.field select {
    width: 100%;
    min-height: 48px;
    padding: 0 14px;
    border: 1.5px solid #cbd5e1;
    border-radius: var(--radius-md);
    background: #ffffff;
    color: #0f172a;
    font: inherit;
    font-size: 16px;
    color-scheme: light;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}

.field input::placeholder {
    color: #94a3b8;
}

.field input:hover,
.field select:hover {
    border-color: #94a3b8;
}

.field input:focus,
.field select:focus {
    border-color: #10b981;
    outline: none;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.18);
}

.has-error input,
.has-error select {
    border-color: #ef4444;
    background: #fffafa;
}

.field-error {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #dc2626;
    font-size: var(--text-xs);
    font-weight: 600;
}

.chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.chip {
    position: relative;
    cursor: pointer;
}

.chip input,
.category-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.chip span {
    display: inline-flex;
    align-items: center;
    min-height: 42px;
    padding: 0 16px;
    border: 1.5px solid #cbd5e1;
    border-radius: 999px;
    background: #ffffff;
    color: #334155;
    font-size: var(--text-sm);
    font-weight: 700;
    transition: border-color var(--transition-base), background var(--transition-base), color var(--transition-base);
}

.chip:hover span {
    border-color: #94a3b8;
}

.chip input:checked + span {
    border-color: #10b981;
    background: #ecfdf5;
    color: #047857;
}

.chip input:focus-visible + span,
.category-option input:focus-visible ~ .category-dot {
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.25);
}

.has-error .chip span {
    border-color: #fca5a5;
}

.category {
    padding-top: 18px;
    border-top: 1px dashed #cbd5e1;
}

.category-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.category-option {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 60px;
    padding: 12px 16px;
    border: 1.5px solid #cbd5e1;
    border-radius: var(--radius-lg);
    background: #ffffff;
    cursor: pointer;
    transition: border-color var(--transition-base), box-shadow var(--transition-base), background var(--transition-base);
}

.category-option:hover:not(.is-full) {
    border-color: #94a3b8;
}

.category-option:has(input:checked) {
    border-color: var(--option);
    background: color-mix(in srgb, var(--option) 8%, #ffffff);
    box-shadow: inset 0 0 0 1px var(--option);
}

.category-option.is-full {
    opacity: 0.5;
    cursor: not-allowed;
}

.category-dot {
    position: relative;
    flex: none;
    width: 22px;
    height: 22px;
    border: 2px solid #94a3b8;
    border-radius: 999px;
    background: #ffffff;
    transition: border-color var(--transition-base);
}

.category-option input:checked ~ .category-dot {
    border-color: var(--option);
}

.category-option input:checked ~ .category-dot::after {
    content: "";
    position: absolute;
    inset: 3px;
    border-radius: 999px;
    background: var(--option);
}

.category-text {
    display: grid;
    flex: 1;
    min-width: 0;
}

.category-text strong {
    color: #0f172a;
    font-weight: 800;
}

.category-text small {
    color: #c2410c;
    font-size: var(--text-xs);
    font-weight: 700;
}

.category-price {
    color: #047857;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.has-error .category-option:not(.is-full) {
    border-color: #fca5a5;
}

.runner-add {
    display: flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 18px;
    border: 2px dashed rgba(78, 203, 160, 0.5);
    border-radius: var(--radius-xl);
    background: rgba(78, 203, 160, 0.06);
    color: var(--color-text-primary);
    text-align: left;
    cursor: pointer;
    transition: border-color var(--transition-base), background var(--transition-base);
}

.runner-add:hover:not(:disabled) {
    border-color: var(--color-brand);
    background: rgba(78, 203, 160, 0.12);
}

.runner-add:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.runner-add-icon {
    display: grid;
    place-items: center;
    flex: none;
    width: 42px;
    height: 42px;
    border-radius: 999px;
    background: var(--color-brand);
    color: #06291d;
}

.runner-add-text {
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

    .category-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .runner-head,
    .runner-body {
        padding-inline: 22px;
    }
}

@media (max-width: 479px) {
    .runner-remove span {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
    }

    .runner-remove {
        width: 40px;
        padding: 0;
        justify-content: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    .runner-enter-active,
    .runner-leave-active,
    .runner-meter span {
        transition: none;
    }
}
</style>
