<template>
    <Head :title="title">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link href="https://fonts.bunny.net/css?family=archivo:400,500,600,700&display=swap" rel="stylesheet" />
    </Head>

    <div class="bo-root min-h-dvh lg:grid lg:grid-cols-[248px_1fr]">
        <a
            href="#contenido"
            class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2"
        >
            Ir al contenido
        </a>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-[248px] flex-col bg-bo-ink text-white/80 transition-transform duration-200 lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0"
            :class="menuOpen ? 'translate-x-0' : '-translate-x-full'"
            aria-label="Menú de taquilla"
        >
            <div class="flex h-16 items-center justify-between px-5">
                <Link :href="route(config.home)" class="flex items-center gap-2.5 text-white">
                    <img src="/deboletoIcono.png" alt="" class="size-8 rounded-md" />
                    <span class="text-[15px] leading-tight font-semibold">
                        DeBoleto
                        <span class="block text-xs font-normal text-white/50">{{ config.label }}</span>
                    </span>
                </Link>
                <button type="button" class="rounded p-1.5 text-white/60 hover:text-white lg:hidden" aria-label="Cerrar menú" @click="menuOpen = false">
                    <BoIcon name="close" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 pb-4">
                <Link
                    :href="route(config.home)"
                    class="mb-1 flex items-center gap-3 rounded-lg px-3 py-2 text-sm"
                    :class="isActive(`/${area}/inicio`) ? 'bg-white/10 text-white' : 'hover:bg-white/5 hover:text-white'"
                >
                    <BoIcon name="home" :size="18" />
                    Inicio
                </Link>

                <div v-for="group in menu" :key="group.label" class="mt-5">
                    <p class="mb-1.5 flex items-center gap-2 px-3 text-xs font-medium text-white/40">
                        <BoIcon :name="iconForMenu(group.icon)" :size="14" />
                        {{ group.label }}
                    </p>
                    <Link
                        v-for="item in group.items"
                        :key="item.url"
                        :href="item.url"
                        class="flex items-center rounded-lg px-3 py-1.5 pl-9 text-sm"
                        :class="isActive(item.url) ? 'bg-white/10 text-white' : 'hover:bg-white/5 hover:text-white'"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </nav>

            <div class="border-t border-white/10 p-3">
                <div class="mb-2 px-3 text-sm">
                    <p class="truncate font-medium text-white">{{ user?.name }}</p>
                    <p class="truncate text-xs text-white/50">{{ user?.email }}</p>
                </div>
                <Link
                    :href="route(config.logout)"
                    method="post"
                    as="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-white/5 hover:text-white"
                >
                    <BoIcon name="logout" :size="18" />
                    Cerrar sesión
                </Link>
            </div>
        </aside>

        <div v-if="menuOpen" class="fixed inset-0 z-30 bg-bo-ink/40 lg:hidden" @click="menuOpen = false" />

        <div class="min-w-0">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-bo-line bg-bo-paper/90 px-4 backdrop-blur sm:px-8">
                <button type="button" class="-ml-1 rounded p-1.5 text-bo-slate lg:hidden" aria-label="Abrir menú" @click="menuOpen = true">
                    <BoIcon name="menu" />
                </button>
                <div class="min-w-0 flex-1">
                    <slot name="header">
                        <h1 class="truncate text-lg font-semibold">{{ title }}</h1>
                    </slot>
                </div>
                <slot name="actions" />
            </header>

            <main id="contenido" class="mx-auto w-full max-w-[1280px] px-4 py-6 sm:px-8 sm:py-8">
                <div
                    v-if="flash.success || flash.error"
                    role="status"
                    class="mb-6 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm"
                    :class="flash.error ? 'border-bo-sold/30 bg-bo-sold-soft text-bo-sold' : 'border-bo-green/30 bg-bo-green-soft text-bo-green-dark'"
                >
                    <BoIcon :name="flash.error ? 'alert' : 'check'" :size="18" class="mt-0.5 shrink-0" />
                    <p class="flex-1">{{ flash.error || flash.success }}</p>
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, onUnmounted, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import { iconForMenu } from "@/components/BoxOffice/format.js";

defineProps({
    title: { type: String, required: true },
});

const page = usePage();
const menuOpen = ref(false);

const AREAS = {
    boxoffice: {
        label: "Taquilla",
        home: "boxoffice.dashboard",
        logout: "boxoffice.logout",
        sections: [
            "inicio",
            "tickets",
            "ventas",
            "boletos-venta",
            "tickets-courtesy",
            "tickets-courtesy-create",
            "tickets-exchange",
            "tickets-count",
            "tickets-change",
            "reportes",
            "reportes-por-dia",
            "reportes-contador-entradas",
            "reportes-por-categoria",
            "escenarios",
        ],
    },
    admin: {
        label: "Administración",
        home: "admin.home",
        logout: "admin.logout",
        sections: [
            "inicio",
            "administradores",
            "monitores",
            "empresarios",
            "puntoventa",
            "usuarios",
            "eventos",
            "escenarios",
            "tickets-unlock",
            "actualizar",
        ],
    },
};

const area = computed(() => page.props.backoffice?.area ?? "boxoffice");
const config = computed(() => AREAS[area.value]);
const user = computed(() => page.props.backoffice?.user);
const flash = computed(() => page.props.flash ?? {});

const menu = computed(() =>
    (page.props.backoffice?.menu ?? [])
        .map((group) => ({ ...group, items: group.items.filter((item) => config.value.sections.includes(section(item.url))) }))
        .filter((group) => group.items.length),
);

function section(url) {
    return url.replace(/^\/(boxoffice|admin)\/?/, "").split(/[/?]/)[0];
}

function isActive(url) {
    return section(page.url) === section(url);
}

const stopListening = router.on("navigate", () => {
    menuOpen.value = false;
});

onUnmounted(stopListening);
</script>
