<script setup>
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/components/DialogModal.vue';
import InputError from '@/components/InputError.vue';

defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'switch-to-login']);

const form = useForm({
    nombre: '',
    apellido_paterno: '',
    apellido_materno: '',
    phone: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
        preserveState: (page) => Object.keys(page.props.errors).length > 0,
    });
};
</script>

<style scoped>
:deep(.dialog-content) {
    position: relative;
    padding: 30px 24px;
}
:deep(.dialog-footer) {
    border-top: 1px solid var(--color-border);
    padding: 15px 0 0;
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    background: transparent;
}

.login-logo {
    max-height: 24px;
    width: auto;
    margin: 10px auto 20px;
    display: block;
}
.login-subtitle {
    font-size: 13.5px;
    color: var(--color-text-secondary);
    line-height: 1.5;
    text-align: center;
    margin: 0 auto 25px;
    max-width: 320px;
}
.login-label {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 8px;
    display: inline-block;
    letter-spacing: 0.8px;
    color: var(--color-text-secondary);
}
.login-input {
    background-color: var(--color-surface-2);
    border: 1.5px solid var(--color-border-strong);
    border-radius: 12px;
    color: var(--color-text-primary);
    height: 48px;
    padding: 10px 16px;
    font-size: 15px;
    width: 100%;
    outline: none;
    box-sizing: border-box;
    transition: all 0.25s ease;
}
.login-input:focus {
    border-color: var(--color-brand);
    box-shadow: 0 0 0 3px rgba(78, 203, 160, 0.25);
}
.login-input::placeholder {
    color: rgba(255, 255, 255, 0.25);
}
.btn-login {
    background-color: var(--color-brand);
    color: #ffffff;
    border: none;
    border-radius: 6px;
    height: 48px;
    font-size: 14.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(78, 203, 160, 0.25);
    width: 100%;
    cursor: pointer;
}
.btn-login:hover:not(:disabled) {
    background-color: var(--color-brand-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(78, 203, 160, 0.4);
}
.btn-login:active:not(:disabled) {
    transform: translateY(0);
}
.btn-login:disabled {
    background-color: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.25);
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}
.login-footer-link {
    color: var(--color-text-muted);
    font-size: 12.5px;
    font-weight: 500;
    transition: color 0.2s;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    text-decoration: none;
}
.login-footer-link:hover {
    color: var(--color-brand);
}
.close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    z-index: 10;
    color: var(--color-text-primary);
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.6;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    padding: 0;
}
.close-btn:hover {
    opacity: 1;
    background-color: rgba(255, 255, 255, 0.2);
}
</style>

<template>
    <DialogModal :show="show" max-width="md" @close="emit('close')">
        <template #title>
            <button class="close-btn" @click="emit('close')" aria-label="Cerrar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
            <img src="/logo_blanco.png" alt="Boleto" class="login-logo">
        </template>

        <template #content>
            <p class="login-subtitle">Crea tu cuenta de DeBoleto para continuar</p>

            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="login-label">Nombre(s)</label>
                    <input
                        v-model="form.nombre"
                        type="text"
                        class="login-input"
                        placeholder="Ingresa tu nombre"
                        required
                        autofocus
                        autocomplete="name"
                    />
                    <InputError class="mt-2" :message="form.errors.nombre" />
                </div>

                <div class="flex gap-3 mb-4">
                    <div class="flex-1 min-w-0">
                        <label class="login-label">Apellido paterno</label>
                        <input
                            v-model="form.apellido_paterno"
                            type="text"
                            class="login-input"
                            placeholder="Ingresa tu apellido paterno"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.apellido_paterno" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="login-label">Apellido materno</label>
                        <input
                            v-model="form.apellido_materno"
                            type="text"
                            class="login-input"
                            placeholder="Ingresa tu apellido materno"
                        />
                        <InputError class="mt-2" :message="form.errors.apellido_materno" />
                    </div>
                </div>

                <div class="mb-4">
                    <label class="login-label">Teléfono</label>
                    <input
                        v-model="form.phone"
                        type="text"
                        maxlength="10"
                        class="login-input"
                        placeholder="Ingresa tu teléfono"
                        required
                        autocomplete="tel"
                    />
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <div class="mb-4">
                    <label class="login-label">Correo electrónico</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="login-input"
                        placeholder="Ingresa tu correo electrónico"
                        required
                        autocomplete="username"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mb-4">
                    <label class="login-label">Contraseña</label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="login-input"
                        placeholder="Ingresa una contraseña"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="mb-4">
                    <label class="login-label">Confirmar contraseña</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        class="login-input"
                        placeholder="Confirma la contraseña"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <div class="mt-5 sm:mt-6">
                    <button
                        type="submit"
                        class="btn-login"
                        :class="{ 'opacity-50': form.processing }"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Creando cuenta…' : 'CREAR CUENTA!' }}
                    </button>
                </div>
            </form>
        </template>

        <template #footer>
            <span class="login-footer-link" style="display: inline;">
                ¿Ya estás registrado?
                <button
                    type="button"
                    class="login-footer-link underline"
                    style="display: inline; text-decoration: underline;"
                    @click="emit('switch-to-login')"
                >
                    Accede Aquí!
                </button>
            </span>
        </template>
    </DialogModal>
</template>
