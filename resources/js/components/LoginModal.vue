<script setup>
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/components/DialogModal.vue';
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import TextInput from '@/components/TextInput.vue';
import Checkbox from '@/components/Checkbox.vue';

defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'switch-to-register']);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
        preserveState: (page) => Object.keys(page.props.errors).length > 0,
    });
};
</script>

<style scoped>
:deep(.dialog-content) {
  padding: var(--space-6) var(--space-8);
}
:deep(.dialog-footer) {
  padding: var(--space-4) var(--space-8);
}
</style>

<template>
    <DialogModal :show="show" max-width="lg" @close="emit('close')">
        <template #title>
            <div class="flex items-center justify-between">
                <span>Iniciar sesión</span>
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
            <div class="flex justify-center mb-6 sm:mb-8">
                <img src="/logo_blanco.png" alt="Boleto" class="h-8 w-auto">
            </div>
            <form @submit.prevent="submit" class="modal-form">
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="username"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mt-4 sm:mt-5">
                    <InputLabel for="password" value="Contraseña" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="current-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-0 mt-4 sm:mt-5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <Checkbox v-model:checked="form.remember" name="remember" />
                        <span class="text-sm text-[var(--color-text-secondary)]">Recordarme</span>
                    </label>

                    <a
                        :href="route('password.request')"
                        class="text-sm text-[var(--color-text-muted)] hover:text-[var(--color-brand)] transition-colors"
                    >
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>

                <div class="mt-5 sm:mt-6">
                    <button
                        type="submit"
                        class="btn-brand w-full min-h-[44px]"
                        :class="{ 'opacity-50': form.processing }"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Entrando…' : 'Iniciar sesión' }}
                    </button>
                </div>
            </form>
        </template>

        <template #footer>
            <button
                type="button"
                class="text-sm text-[var(--color-text-muted)] hover:text-[var(--color-brand)] transition-colors underline"
                @click="emit('switch-to-register')"
            >
                ¿No tienes cuenta? Regístrate
            </button>
        </template>
    </DialogModal>
</template>
