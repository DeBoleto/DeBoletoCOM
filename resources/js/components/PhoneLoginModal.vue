<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/components/DialogModal.vue';
import InputError from '@/components/InputError.vue';

defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'switch-to-login', 'switch-to-register', 'code-sent']);

const codeForm = useForm({
    lada: '52',
    telefono: '',
    canal: 'whatsapp',
});

const telefonoValido = computed(() => codeForm.telefono.length === 10);

const handleSubmit = () => {
    emit('code-sent', {
        lada: codeForm.lada,
        telefono: codeForm.telefono,
        canal: codeForm.canal,
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
.login-select {
    background-color: var(--color-surface-2);
    border: 1.5px solid var(--color-border-strong);
    border-radius: 12px;
    color: var(--color-text-primary);
    height: 48px;
    padding: 0 24px 0 16px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    width: 100%;
    outline: none;
    transition: all 0.25s ease;
}
.login-select:focus {
    border-color: var(--color-brand);
    box-shadow: 0 0 0 3px rgba(78, 203, 160, 0.25);
}
.login-select-wrapper {
    position: relative;
    width: 100%;
}
.login-select-arrow {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    pointer-events: none;
    font-size: 11px;
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
.canal-toggle-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 20px;
    margin-bottom: 25px;
}
.canal-option-row {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-radius: 12px;
    border: 1.5px solid var(--color-border-strong);
    background-color: rgba(25, 22, 54, 0.4);
    cursor: pointer;
    transition: all 0.25s ease;
    position: relative;
}
.canal-option-row:hover {
    border-color: rgba(78, 203, 160, 0.5);
    background-color: rgba(25, 22, 54, 0.7);
}
.canal-option-row.is-active {
    border-color: var(--color-brand);
    background-color: rgba(78, 203, 160, 0.08);
}
.canal-option-row input[type="radio"] {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}
.radio-indicator {
    height: 18px;
    width: 18px;
    border: 2px solid var(--color-text-muted);
    border-radius: 50%;
    display: inline-block;
    margin-right: 12px;
    position: relative;
    flex-shrink: 0;
    transition: all 0.2s ease;
}
.canal-option-row.is-active .radio-indicator {
    border-color: var(--color-brand);
}
.radio-indicator::after {
    content: "";
    position: absolute;
    display: none;
    top: 3px;
    left: 3px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--color-brand);
}
.canal-option-row.is-active .radio-indicator::after {
    display: block;
}
.radio-label {
    font-size: 13.5px;
    color: var(--color-text-secondary);
    font-weight: 500;
    flex-grow: 1;
    text-align: left;
}
.canal-option-row.is-active .radio-label {
    color: var(--color-text-primary);
    font-weight: 600;
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
            <p class="login-subtitle">Ingresa tu número de teléfono para iniciar sesión o registrarte</p>

            <form @submit.prevent="handleSubmit">
                <div class="flex gap-3">
                    <div class="w-[90px] shrink-0">
                        <label class="login-label">Código</label>
                        <div class="login-select-wrapper">
                            <select v-model="codeForm.lada" class="login-select">
                                <option value="52">+52</option>
                                <option value="1">+1</option>
                            </select>
                            <span class="login-select-arrow">▼</span>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="login-label">Número de teléfono</label>
                        <input
                            v-model="codeForm.telefono"
                            type="text"
                            maxlength="10"
                            class="login-input"
                            placeholder="1234567890"
                            required
                        />
                        <InputError class="mt-2" :message="codeForm.errors.telefono" />
                    </div>
                </div>

                <div class="canal-toggle-group">
                    <label class="canal-option-row" :class="{ 'is-active': codeForm.canal === 'whatsapp' }">
                        <input type="radio" name="canal" value="whatsapp" v-model="codeForm.canal" />
                        <span class="radio-indicator"></span>
                        <span class="radio-label">Recibir código por WhatsApp</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" class="text-[#25d366]">
                            <path d="M12 0C5.373 0 0 5.373 0 12c0 2.302.651 4.456 1.778 6.294L0 24l5.926-1.718A11.97 11.97 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.6c-1.917 0-3.787-.542-5.358-1.557l-.384-.234-3.517 1.02 1.023-3.43-.25-.398A9.54 9.54 0 012.4 12c0-5.304 4.296-9.6 9.6-9.6s9.6 4.296 9.6 9.6-4.296 9.6-9.6 9.6zm5.205-7.308c-.285-.142-1.687-.832-1.947-.927-.26-.095-.45-.142-.64.142-.19.285-.732.927-.898 1.117-.166.19-.332.214-.617.072-.285-.143-1.204-.444-2.293-1.416-.848-.757-1.42-1.692-1.586-1.977-.166-.285-.018-.44.125-.58.128-.128.285-.333.428-.5.142-.166.19-.285.285-.476.095-.19.048-.357-.024-.5-.071-.143-.642-1.547-.88-2.118-.23-.555-.466-.48-.642-.49-.166-.008-.356-.01-.547-.01s-.5.072-.762.357c-.261.285-.998.975-.998 2.379s1.023 2.76 1.165 2.95c.143.19 2.013 3.072 4.877 4.309.68.294 1.213.47 1.628.6.683.218 1.306.187 1.798.113.548-.082 1.687-.69 1.925-1.356.238-.666.238-1.237.166-1.356-.071-.119-.261-.19-.547-.333z"/>
                        </svg>
                    </label>
                    <label class="canal-option-row" :class="{ 'is-active': codeForm.canal === 'sms' }">
                        <input type="radio" name="canal" value="sms" v-model="codeForm.canal" />
                        <span class="radio-indicator"></span>
                        <span class="radio-label">Recibir código por SMS</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-[#00bcd4]">
                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                        </svg>
                    </label>
                </div>

                <div class="mt-5 sm:mt-6">
                    <button
                        type="submit"
                        class="btn-login"
                        :disabled="codeForm.processing || !telefonoValido"
                    >
                        {{ codeForm.processing ? 'Enviando…' : 'CONTINUAR' }}
                    </button>
                </div>
            </form>
        </template>

        <template #footer>
            <button
                type="button"
                class="login-footer-link"
                @click="emit('switch-to-login')"
            >
                Acceso con correo electrónico
            </button>
            <button
                type="button"
                class="login-footer-link"
                @click="emit('switch-to-register')"
            >
                ¿No tienes cuenta? Regístrate aquí
            </button>
        </template>
    </DialogModal>
</template>
