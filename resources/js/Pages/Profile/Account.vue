<template>
    <ProfileShell title="Mis datos">
        <form class="panel" novalidate @submit.prevent="save">
            <p class="panel-lead">Usamos estos datos para enviarte tus boletos y avisarte de cambios en tus eventos.</p>

            <div class="fields">
                <label v-for="field in fields" :key="field.key" class="field" :class="{ 'field--wide': field.wide }">
                    <span class="field-label">{{ field.label }}<span v-if="!field.optional" aria-hidden="true"> *</span></span>
                    <input
                        v-if="field.digits"
                        v-model="form[field.key]"
                        v-digits
                        :type="field.type"
                        :autocomplete="field.autocomplete"
                        :maxlength="field.maxlength"
                        :aria-invalid="!!form.errors[field.key]"
                        :required="!field.optional"
                    />
                    <input
                        v-else
                        v-model="form[field.key]"
                        :type="field.type"
                        :autocomplete="field.autocomplete"
                        :maxlength="field.maxlength"
                        :max="field.type === 'date' ? today : undefined"
                        :aria-invalid="!!form.errors[field.key]"
                        :required="!field.optional"
                    />
                    <span v-if="form.errors[field.key]" class="field-error">{{ form.errors[field.key] }}</span>
                </label>
            </div>

            <div class="panel-actions">
                <button type="submit" class="btn-save" :disabled="form.processing || !form.isDirty">
                    {{ form.processing ? "Guardando…" : "Guardar cambios" }}
                </button>
                <span v-if="form.recentlySuccessful" class="saved" role="status">Guardado</span>
            </div>
        </form>
    </ProfileShell>
</template>

<script setup>
import { useForm } from "@inertiajs/vue3";
import ProfileShell from "@/components/profile/ProfileShell.vue";

const props = defineProps({
    account: { type: Object, required: true },
});

const today = new Date().toISOString().slice(0, 10);

const fields = [
    { key: "nombre", label: "Nombre", type: "text", autocomplete: "given-name", maxlength: 100 },
    { key: "apellido_paterno", label: "Apellido paterno", type: "text", autocomplete: "family-name", maxlength: 100 },
    { key: "apellido_materno", label: "Apellido materno", type: "text", autocomplete: "additional-name", maxlength: 100, optional: true },
    { key: "nacimiento_fecha", label: "Fecha de nacimiento", type: "date", autocomplete: "bday", optional: true },
    { key: "correo", label: "Correo electrónico", type: "email", autocomplete: "email", maxlength: 150 },
    { key: "telefono", label: "Teléfono (10 dígitos)", type: "tel", autocomplete: "tel-national", maxlength: 10, digits: true },
];

const form = useForm({ ...props.account });

function save() {
    form.put(route("profile.account.update"), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}
</script>

<style scoped>
.panel {
    max-width: 760px;
    padding: var(--space-6);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    background: var(--color-surface-1);
}

.panel-lead {
    margin-bottom: var(--space-5);
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
}

.fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-4);
}

.field {
    display: grid;
    gap: 6px;
}

.field-label {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
    font-weight: 700;
}

.field input {
    min-height: 46px;
    padding: 0 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface-2);
    color: var(--color-text-primary);
    font: inherit;
    color-scheme: dark;
}

.field input:focus {
    border-color: var(--color-brand);
    outline: none;
}

.field input[aria-invalid="true"] {
    border-color: #f87171;
}

.field-error {
    color: #fca5a5;
    font-size: var(--text-xs);
}

.panel-actions {
    display: flex;
    align-items: center;
    gap: var(--space-4);
    margin-top: var(--space-6);
}

.btn-save {
    min-height: 48px;
    padding: 0 22px;
    border: none;
    border-radius: var(--radius-md);
    background: var(--color-brand);
    color: #06291d;
    font: inherit;
    font-weight: 800;
    cursor: pointer;
}

.btn-save:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.btn-save:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.saved {
    color: var(--color-brand);
    font-size: var(--text-sm);
    font-weight: 700;
}

@media (max-width: 640px) {
    .panel {
        padding: var(--space-4);
    }

    .fields {
        grid-template-columns: 1fr;
    }
}
</style>
