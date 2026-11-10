<script setup>
import { ref, watch } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import DialogModal from "@/components/DialogModal.vue";
import InputError from "@/components/InputError.vue";

const props = defineProps({
    show: { type: Boolean, default: false },
    lada: { type: String, default: "52" },
    telefono: { type: String, default: "" },
    canal: { type: String, default: "whatsapp" },
});

const emit = defineEmits([
    "close",
    "switch-to-login",
    "switch-to-register",
    "verified",
    "resend",
]);

const page = usePage();
const needsProfile = ref(false);

const verifyForm = useForm({
    codigo: "",
});

const profileForm = useForm({
    nombre: "",
    apellido_paterno: "",
    apellido_materno: "",
    correo: "",
});

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            needsProfile.value = false;
            verifyForm.clearErrors();
            profileForm.clearErrors();
        }
    },
);

const handleSubmit = () => {
    verifyForm.post(route("login.verify"), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (page.props.flash?.registration_required) {
                needsProfile.value = true;
                return;
            }
            emit("verified");
        },
        onFinish: () => verifyForm.reset("codigo"),
    });
};

const submitProfile = () => {
    profileForm.post(route("login.complete"), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            needsProfile.value = false;
            emit("verified");
        },
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
    margin-bottom: 15px;
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
    margin-top: 15px;
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
.login-action-link {
    color: var(--color-brand);
    font-weight: 600;
    transition: color 0.2s;
}
.login-action-link:hover {
    color: var(--color-brand-hover);
    text-decoration: underline;
}
.login-code-group {
    display: flex;
    align-items: center;
    background-color: var(--color-surface-2);
    border: 1.5px solid var(--color-border-strong);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.25s ease;
}
.login-code-group:focus-within {
    border-color: var(--color-brand);
    box-shadow: 0 0 0 3px rgba(78, 203, 160, 0.25);
}
.login-code-addon {
    padding: 12px 16px;
    color: var(--color-text-muted);
    font-size: 16px;
    border-right: 1.5px solid var(--color-border-strong);
    background-color: rgba(255, 255, 255, 0.02);
    display: flex;
    align-items: center;
}
.login-code-input {
    background-color: transparent;
    border: none;
    color: var(--color-text-primary);
    height: 48px;
    padding: 10px 16px;
    font-size: 16px;
    width: 100%;
    outline: none;
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
.profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.profile-field--full {
    grid-column: 1 / -1;
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
}
.login-input:focus {
    border-color: var(--color-brand);
}
</style>

<template>
    <DialogModal :show="show" max-width="md" @close="emit('close')">
        <template #title>
            <button
                class="close-btn"
                @click="emit('close')"
                aria-label="Cerrar"
            >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                >
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
            <img src="/logo_blanco.png" alt="Boleto" class="login-logo" />
        </template>

        <template #content>
            <form v-if="needsProfile" @submit.prevent="submitProfile">
                <p class="login-subtitle">
                    Tu número quedó verificado. Completa tus datos para crear tu cuenta.
                </p>
                <div class="profile-grid">
                    <div class="profile-field profile-field--full">
                        <label class="login-label" for="perfil-nombre">Nombre</label>
                        <input id="perfil-nombre" v-model="profileForm.nombre" class="login-input" autocomplete="given-name" required />
                        <InputError class="mt-2" :message="profileForm.errors.nombre" />
                    </div>
                    <div class="profile-field">
                        <label class="login-label" for="perfil-paterno">Apellido paterno</label>
                        <input id="perfil-paterno" v-model="profileForm.apellido_paterno" class="login-input" autocomplete="family-name" required />
                        <InputError class="mt-2" :message="profileForm.errors.apellido_paterno" />
                    </div>
                    <div class="profile-field">
                        <label class="login-label" for="perfil-materno">Apellido materno</label>
                        <input id="perfil-materno" v-model="profileForm.apellido_materno" class="login-input" />
                    </div>
                    <div class="profile-field profile-field--full">
                        <label class="login-label" for="perfil-correo">Correo electrónico</label>
                        <input id="perfil-correo" v-model="profileForm.correo" type="email" class="login-input" autocomplete="email" required />
                        <InputError class="mt-2" :message="profileForm.errors.correo" />
                    </div>
                </div>
                <div class="mt-5 sm:mt-6">
                    <button type="submit" class="btn-login" :disabled="profileForm.processing">
                        {{ profileForm.processing ? "Creando cuenta…" : "CREAR MI CUENTA" }}
                    </button>
                </div>
            </form>

            <template v-else>
            <p class="login-subtitle">
                Ingresa el código de verificación que te hemos enviado
            </p>

            <form @submit.prevent="handleSubmit">
                <label class="login-label">Código de verificación</label>
                <div class="login-code-group">
                    <div class="login-code-addon">
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                        >
                            <rect
                                x="3"
                                y="11"
                                width="18"
                                height="11"
                                rx="2"
                                ry="2"
                            />
                            <path d="M7 11V7a5 5 0 0110 0v4" />
                        </svg>
                    </div>
                    <input
                        v-model="verifyForm.codigo"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        class="login-code-input"
                        placeholder="Ingresa el código"
                        required
                        autocomplete="one-time-code"
                    />
                </div>
                <InputError class="mt-2" :message="verifyForm.errors.codigo" />

                <p
                    class="mt-3 text-xs"
                    style="color: var(--color-text-muted); line-height: 1.5"
                >
                    Enviamos un código por
                    {{ canal === "sms" ? "SMS" : "WhatsApp" }} al número +{{
                        lada
                    }}
                    {{ telefono }}.
                    <br />
                    <button
                        type="button"
                        @click="emit('resend')"
                        class="login-action-link bg-transparent border-none p-0 cursor-pointer text-xs"
                    >
                        ¿No te llegó? Reenviar código
                    </button>
                </p>

                <div class="mt-5 sm:mt-6">
                    <button
                        type="submit"
                        class="btn-login"
                        :disabled="
                            verifyForm.processing ||
                            verifyForm.codigo.length === 0
                        "
                    >
                        {{
                            verifyForm.processing
                                ? "Verificando…"
                                : "VERIFICAR CÓDIGO"
                        }}
                    </button>
                </div>
            </form>
            </template>
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
