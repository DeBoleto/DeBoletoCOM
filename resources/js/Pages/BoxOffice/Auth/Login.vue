<template>
    <Head :title="`${areaLabel} — Iniciar sesión`">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link href="https://fonts.bunny.net/css?family=archivo:400,500,600,700&display=swap" rel="stylesheet" />
    </Head>

    <div class="bo-root grid min-h-dvh lg:grid-cols-[1.1fr_1fr]">
        <section class="relative hidden overflow-hidden bg-bo-ink p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-3">
                <img src="/deboletoIcono.png" alt="" class="size-10 rounded-lg" />
                <span class="text-lg font-semibold">DeBoleto</span>
            </div>

            <div class="bo-stub max-w-md p-0 text-bo-ink" style="--stub-cut: calc(100% - 92px)">
                <div class="p-8">
                    <p class="text-sm text-bo-slate">{{ areaLabel }}</p>
                    <p class="mt-1 text-3xl leading-tight font-semibold">{{ tagline }}</p>
                </div>
                <div class="bo-perforation" />
                <div class="flex items-center justify-between px-8 py-6 text-sm text-bo-slate">
                    <span>{{ areaLabel }}</span>
                    <span>{{ footnote }}</span>
                </div>
            </div>

            <p class="text-sm text-white/40">¿Problemas para entrar? Pide a tu administrador que revise tus permisos.</p>
        </section>

        <section class="flex items-center justify-center px-6 py-12">
            <form class="w-full max-w-sm" novalidate @submit.prevent="submit">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <img src="/deboletoIcono.png" alt="" class="size-9 rounded-lg" />
                    <span class="text-lg font-semibold">DeBoleto {{ areaLabel }}</span>
                </div>

                <h1 class="text-2xl font-semibold">Inicia sesión</h1>
                <p class="mt-1 text-sm text-bo-slate">Usa tu cuenta de {{ areaLabel.toLowerCase() }}.</p>

                <div class="mt-8 space-y-5">
                    <div>
                        <label for="correo" class="mb-1.5 block text-sm font-medium">Correo electrónico</label>
                        <input
                            id="correo"
                            v-model="form.correo"
                            type="email"
                            autocomplete="username"
                            required
                            autofocus
                            class="block w-full rounded-lg border-bo-line bg-white px-3.5 py-2.5 text-[15px] focus:border-bo-green focus:ring-bo-green"
                            :aria-invalid="!!form.errors.correo"
                            aria-describedby="correo-error"
                        />
                        <p v-if="form.errors.correo" id="correo-error" class="mt-1.5 text-sm text-bo-sold">{{ form.errors.correo }}</p>
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium">Contraseña</label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                class="block w-full rounded-lg border-bo-line bg-white px-3.5 py-2.5 pr-20 text-[15px] focus:border-bo-green focus:ring-bo-green"
                                :aria-invalid="!!form.errors.password"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 px-3 text-sm font-medium text-bo-slate hover:text-bo-ink"
                                :aria-pressed="showPassword"
                                @click="showPassword = !showPassword"
                            >
                                {{ showPassword ? "Ocultar" : "Mostrar" }}
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-sm text-bo-sold">{{ form.errors.password }}</p>
                    </div>
                </div>

                <button
                    type="submit"
                    class="mt-8 flex w-full items-center justify-center rounded-lg bg-bo-green px-4 py-3 font-semibold text-white hover:bg-bo-green-dark disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? "Entrando…" : "Entrar" }}
                </button>
            </form>
        </section>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";

const props = defineProps({
    areaLabel: { type: String, default: "Taquilla" },
    tagline: { type: String, default: "Vende, imprime y cierra caja desde un solo lugar." },
    footnote: { type: String, default: "Corte de caja al día" },
    submitRoute: { type: String, default: "boxoffice.login.store" },
});

const showPassword = ref(false);

const form = useForm({
    correo: "",
    password: "",
});

function submit() {
    form.post(route(props.submitRoute), {
        onFinish: () => form.reset("password"),
    });
}
</script>
