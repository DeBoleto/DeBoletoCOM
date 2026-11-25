<template>
    <Head :title="title" />

    <div class="page-layout">
        <SiteHeader />

        <main id="main-content" class="container profile-page">
            <header class="profile-head">
                <div>
                    <p class="profile-kicker">Hola, {{ customer?.name ?? "" }}</p>
                    <h1 class="profile-title">{{ title }}</h1>
                </div>
                <nav class="profile-tabs" aria-label="Secciones del perfil">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.route"
                        :href="route(tab.route)"
                        class="profile-tab"
                        :class="{ 'is-active': route().current(tab.match) }"
                        :aria-current="route().current(tab.match) ? 'page' : undefined"
                    >
                        {{ tab.label }}
                    </Link>
                </nav>
            </header>

            <p v-if="flash.success" class="profile-flash profile-flash--ok" role="status">{{ flash.success }}</p>
            <p v-if="flash.error" class="profile-flash profile-flash--bad" role="alert">{{ flash.error }}</p>

            <slot />
        </main>

        <SiteFooter />
        <MobileBottomNav />
    </div>
</template>

<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import SiteHeader from "@/components/SiteHeader.vue";
import SiteFooter from "@/components/SiteFooter.vue";
import MobileBottomNav from "@/components/MobileBottomNav.vue";

defineProps({
    title: { type: String, required: true },
});

const page = usePage();
const customer = computed(() => page.props.customer);
const flash = computed(() => page.props.flash ?? {});

const tabs = [
    { route: "profile.purchases", match: "profile.purchases", label: "Mis compras" },
    { route: "profile.account", match: "profile.account", label: "Mis datos" },
    { route: "profile.cards", match: "profile.cards", label: "Métodos de pago" },
];
</script>

<style scoped>
.page-layout {
    display: flex;
    flex-direction: column;
    min-height: 100dvh;
}

.profile-page {
    flex: 1;
    width: 100%;
    padding-block: var(--space-8) var(--space-16);
}

.profile-head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}

.profile-kicker {
    color: var(--color-brand);
    font-size: var(--text-sm);
    font-weight: 700;
}

.profile-title {
    font-size: var(--text-3xl);
    font-weight: 800;
    letter-spacing: -0.02em;
}

.profile-tabs {
    display: flex;
    gap: 4px;
    padding: 4px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-full);
    background: var(--color-surface-1);
    overflow-x: auto;
    scrollbar-width: none;
}

.profile-tab {
    flex-shrink: 0;
    padding: 8px 16px;
    border-radius: var(--radius-full);
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
    font-weight: 700;
    white-space: nowrap;
    transition:
        background var(--transition-fast),
        color var(--transition-fast);
}

.profile-tab:hover {
    color: var(--color-text-primary);
}

.profile-tab.is-active {
    background: var(--color-brand);
    color: #06291d;
}

.profile-tab:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.profile-flash {
    margin-bottom: var(--space-5);
    padding: 12px 16px;
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
    font-weight: 600;
}

.profile-flash--ok {
    border: 1px solid rgba(78, 203, 160, 0.4);
    background: rgba(78, 203, 160, 0.12);
    color: var(--color-brand);
}

.profile-flash--bad {
    border: 1px solid rgba(248, 113, 113, 0.4);
    background: rgba(248, 113, 113, 0.12);
    color: #fca5a5;
}

@media (max-width: 767px) {
    .profile-page {
        padding-bottom: calc(var(--bottom-nav-height) + var(--space-10));
    }

    .profile-tabs {
        width: 100%;
    }

    .profile-tab {
        flex: 1;
        text-align: center;
    }
}
</style>
