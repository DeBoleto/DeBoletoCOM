<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import DialogModal from '@/components/DialogModal.vue';
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import TextInput from '@/components/TextInput.vue';
import Checkbox from '@/components/Checkbox.vue';

defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'switch-to-login']);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
        preserveState: (page) => Object.keys(page.props.errors).length > 0,
    });
};

const hasTerms = usePage().props.jetstream?.hasTermsAndPrivacyPolicyFeature ?? false;
</script>

<template>
    <DialogModal :show="show" max-width="lg" @close="emit('close')">
        <template #title>
            <div class="flex items-center justify-between">
                <span>Crear cuenta</span>
                <button
                    type="button"
                    class="modal-close"
                    @click="emit('close')"
                    aria-label="Cerrar"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
        </template>

        <template #content>
            <div class="flex justify-center mb-8">
                <img src="/logo_blanco.png" alt="Boleto" class="h-8 w-auto">
            </div>
            <form @submit.prevent="submit" class="modal-form">
                <div>
                    <InputLabel for="name" value="Nombre" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="name"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="mt-5">
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autocomplete="username"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mt-5">
                    <InputLabel for="password" value="Contraseña" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="mt-5">
                    <InputLabel for="password_confirmation" value="Confirmar contraseña" />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <div v-if="hasTerms" class="mt-5">
                    <InputLabel for="terms">
                        <div class="flex items-center gap-2">
                            <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />
                            <span class="text-sm text-[var(--color-text-secondary)]">
                                Acepto los <a target="_blank" :href="route('terms.show')" class="text-[var(--color-brand)] hover:underline">Términos de servicio</a> y la <a target="_blank" :href="route('policy.show')" class="text-[var(--color-brand)] hover:underline">Política de privacidad</a>
                            </span>
                        </div>
                        <InputError class="mt-2" :message="form.errors.terms" />
                    </InputLabel>
                </div>

                <div class="mt-6">
                    <button
                        type="submit"
                        class="btn-brand w-full"
                        :class="{ 'opacity-50': form.processing }"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Creando cuenta…' : 'Crear cuenta' }}
                    </button>
                </div>
            </form>
        </template>

        <template #footer>
            <button
                type="button"
                class="text-sm text-[var(--color-text-muted)] hover:text-[var(--color-brand)] transition-colors underline"
                @click="emit('switch-to-login')"
            >
                ¿Ya tienes cuenta? Inicia sesión
            </button>
        </template>
    </DialogModal>
</template>

<style scoped>
.modal-form :deep(.text-gray-700) {
    color: var(--color-text-primary);
}

.modal-form :deep(input) {
    background-color: var(--color-surface-2);
    border-color: var(--color-border);
    color: var(--color-text-primary);
}

.modal-form :deep(input:focus) {
    border-color: var(--color-brand);
    --tw-ring-color: var(--color-brand);
}

.modal-form :deep(.text-indigo-600) {
    color: var(--color-brand);
}

.modal-form :deep(.border-gray-300) {
    border-color: var(--color-border);
}

.modal-form :deep(.focus\:border-indigo-500) {
    --tw-ring-color: var(--color-brand);
    border-color: var(--color-brand);
}

.modal-form :deep(.focus\:ring-indigo-500) {
    --tw-ring-color: var(--color-brand);
}

.modal-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    color: var(--color-text-muted);
    transition: color var(--transition-fast), background var(--transition-fast);
}

.modal-close:hover {
    color: var(--color-text-primary);
    background: var(--color-surface-2);
}

.btn-brand {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.625rem 1.25rem;
    font-size: var(--text-sm);
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
    border: none;
    border-radius: var(--radius-full);
    transition: opacity var(--transition-fast), transform var(--transition-fast);
    box-shadow: var(--shadow-brand);
    cursor: pointer;
}

.btn-brand:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

:deep(.bg-white) {
    background-color: var(--color-surface-1);
}

:deep(.text-lg.font-medium) {
    color: var(--color-text-primary);
}

:deep(.mt-4.text-sm) {
    color: var(--color-text-secondary);
}
</style>
