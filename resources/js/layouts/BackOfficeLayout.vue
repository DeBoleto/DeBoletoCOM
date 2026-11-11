<template>
    <Head :title="title">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link href="https://fonts.bunny.net/css?family=archivo:400,500,600,700&display=swap" rel="stylesheet" />
    </Head>

    <div class="bo-root min-h-dvh" :style="{ '--bo-rail': collapsed ? '76px' : '272px' }">
        <a
            href="#contenido"
            class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2"
        >
            Ir al contenido
        </a>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-[272px] flex-col bg-bo-ink text-white/75 transition-[width,translate] duration-200 ease-out lg:translate-x-0"
            :class="[
                menuOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full',
                compact ? 'lg:w-[76px]' : 'lg:w-[272px]',
                collapsed && peek ? 'lg:shadow-[8px_0_32px_rgba(10,16,24,0.35)]' : '',
            ]"
            aria-label="Menú principal"
            @mouseenter="peek = true"
            @mouseleave="peek = false"
            @focusin="peek = true"
            @focusout="onFocusOut"
        >
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/5 px-4">
                <Link :href="route(config.home)" class="flex min-w-0 flex-1 items-center gap-3 text-white">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/5 ring-1 ring-white/10">
                        <img src="/deboletoIcono.png" alt="" class="size-7 rounded-md" />
                    </span>
                    <span class="min-w-0 leading-tight transition-opacity duration-150" :class="compact ? 'lg:pointer-events-none lg:opacity-0' : ''">
                        <span class="block truncate text-[15px] font-semibold">DeBoleto</span>
                        <span class="block truncate text-xs text-white/45">{{ config.label }}</span>
                    </span>
                </Link>
                <button type="button" class="rounded-lg p-1.5 text-white/60 hover:bg-white/5 hover:text-white lg:hidden" aria-label="Cerrar menú" @click="menuOpen = false">
                    <BoIcon name="close" />
                </button>
            </div>

            <nav class="bo-scroll flex-1 overflow-x-hidden overflow-y-auto px-3 py-4" aria-label="Secciones">
                <Link
                    :href="route(config.home)"
                    class="group relative mb-1 flex h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors"
                    :class="isActive(`/${config.path}/inicio`) ? 'bg-white/10 text-white' : 'hover:bg-white/5 hover:text-white'"
                    :aria-current="isActive(`/${config.path}/inicio`) ? 'page' : undefined"
                    :title="compact ? 'Inicio' : undefined"
                >
                    <span v-if="isActive(`/${config.path}/inicio`)" class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-bo-green" />
                    <BoIcon name="home" :size="19" class="shrink-0" />
                    <span class="truncate transition-opacity duration-150" :class="compact ? 'lg:opacity-0' : ''">Inicio</span>
                </Link>

                <div v-for="group in menu" :key="group.label" class="mt-5">
                    <p class="mb-1.5 flex h-5 items-center px-3 text-[11px] font-semibold tracking-[0.12em] text-white/35 uppercase">
                        <span class="truncate transition-opacity duration-150" :class="compact ? 'lg:opacity-0' : ''">{{ group.label }}</span>
                    </p>
                    <Link
                        v-for="item in group.items"
                        :key="item.url"
                        :href="item.url"
                        class="group relative flex h-10 items-center gap-3 rounded-lg px-3 text-sm transition-colors"
                        :class="isActive(item.url) ? 'bg-white/10 font-medium text-white' : 'hover:bg-white/5 hover:text-white'"
                        :aria-current="isActive(item.url) ? 'page' : undefined"
                        :title="compact ? item.label : undefined"
                    >
                        <span v-if="isActive(item.url)" class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-bo-green" />
                        <BoIcon :name="iconForItem(section(item.url), group.icon)" :size="19" class="shrink-0" :class="isActive(item.url) ? 'text-bo-green' : 'text-white/55 group-hover:text-white'" />
                        <span class="truncate transition-opacity duration-150" :class="compact ? 'lg:opacity-0' : ''">{{ item.label }}</span>
                    </Link>
                </div>
            </nav>

            <div class="shrink-0 border-t border-white/5 p-3">
                <button
                    type="button"
                    class="mb-2 hidden h-10 w-full items-center gap-3 rounded-lg px-3 text-sm text-white/60 transition-colors hover:bg-white/5 hover:text-white lg:flex"
                    :aria-pressed="!collapsed"
                    :title="collapsed ? 'Fijar menú abierto' : 'Contraer menú'"
                    @click="togglePinned"
                >
                    <BoIcon :name="collapsed ? 'chevronRight' : 'chevronLeft'" :size="19" class="shrink-0" />
                    <span class="truncate transition-opacity duration-150" :class="compact ? 'lg:opacity-0' : ''">
                        {{ collapsed ? "Fijar menú abierto" : "Contraer menú" }}
                    </span>
                </button>

                <div class="flex items-center gap-3 rounded-xl bg-white/5 p-2">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-bo-green text-sm font-bold text-white" aria-hidden="true">
                        {{ initials }}
                    </span>
                    <div class="min-w-0 flex-1" :class="compact ? 'lg:hidden' : ''">
                        <p class="truncate text-sm font-medium text-white">{{ user?.name }}</p>
                        <p class="truncate text-xs text-white/45">{{ user?.email }}</p>
                    </div>
                    <Link
                        :href="route(config.logout)"
                        method="post"
                        as="button"
                        class="shrink-0 rounded-lg p-2 text-white/55 transition-colors hover:bg-white/10 hover:text-white"
                        :class="compact ? 'lg:hidden' : ''"
                        aria-label="Cerrar sesión"
                        title="Cerrar sesión"
                    >
                        <BoIcon name="logout" :size="18" />
                    </Link>
                </div>
            </div>
        </aside>

        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div v-if="menuOpen" class="fixed inset-0 z-30 bg-bo-ink/50 backdrop-blur-[2px] lg:hidden" @click="menuOpen = false" />
        </Transition>

        <div class="min-w-0 transition-[padding] duration-200 ease-out lg:pl-(--bo-rail)">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-bo-line bg-bo-paper/85 px-4 backdrop-blur-md sm:px-8">
                <button type="button" class="-ml-1 rounded-lg p-2 text-bo-slate hover:bg-bo-card lg:hidden" aria-label="Abrir menú" @click="menuOpen = true">
                    <BoIcon name="menu" />
                </button>
                <button
                    type="button"
                    class="-ml-2 hidden rounded-lg p-2 text-bo-slate transition-colors hover:bg-bo-card hover:text-bo-ink lg:inline-flex"
                    :aria-label="collapsed ? 'Fijar menú abierto' : 'Contraer menú'"
                    :title="collapsed ? 'Fijar menú abierto' : 'Contraer menú'"
                    @click="togglePinned"
                >
                    <BoIcon name="sidebar" />
                </button>
                <div class="min-w-0 flex-1">
                    <slot name="header">
                        <h1 class="truncate text-lg font-semibold">{{ title }}</h1>
                    </slot>
                </div>
                <slot name="actions" />
            </header>

            <main id="contenido" class="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-8 sm:py-8">
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
import { computed, onUnmounted, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";
import { iconForItem } from "@/components/BoxOffice/format.js";

defineProps({
    title: { type: String, required: true },
});

const page = usePage();
const menuOpen = ref(false);
const peek = ref(false);

const AREAS = {
    boxoffice: {
        label: "Taquilla",
        path: "boxoffice",
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
        path: "dashboard",
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

const storageKey = computed(() => `bo-sidebar:${area.value}:${user.value?.email ?? "anon"}`);
const collapsed = ref(readPreference());
const compact = computed(() => collapsed.value && !peek.value && !menuOpen.value);

const initials = computed(() =>
    String(user.value?.name ?? "")
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join("") || "DB",
);

const menu = computed(() =>
    (page.props.backoffice?.menu ?? [])
        .map((group) => ({ ...group, items: group.items.filter((item) => config.value.sections.includes(section(item.url))) }))
        .filter((group) => group.items.length),
);

function readPreference() {
    try {
        return window.localStorage.getItem(storageKey.value) === "collapsed";
    } catch {
        return false;
    }
}

function togglePinned() {
    collapsed.value = !collapsed.value;
    peek.value = false;
}

watch(collapsed, (value) => {
    try {
        window.localStorage.setItem(storageKey.value, value ? "collapsed" : "pinned");
    } catch {
        return;
    }
});

watch(storageKey, () => {
    collapsed.value = readPreference();
});

function onFocusOut(event) {
    if (!event.currentTarget.contains(event.relatedTarget)) peek.value = false;
}

function section(url) {
    return url.replace(/^\/(boxoffice|dashboard)\/?/, "").split(/[/?]/)[0];
}

function isActive(url) {
    return section(page.url) === section(url);
}

const stopListening = router.on("navigate", () => {
    menuOpen.value = false;
    peek.value = false;
});

onUnmounted(stopListening);
</script>
