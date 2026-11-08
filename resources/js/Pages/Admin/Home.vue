<template>
    <BackOfficeLayout title="Administración">
        <section class="mb-8 grid gap-4 sm:grid-cols-2" aria-label="Eventos">
            <div class="rounded-xl border border-bo-line bg-bo-card p-5">
                <p class="text-sm text-bo-slate">Eventos publicados</p>
                <p class="tabular mt-1 text-4xl font-semibold">{{ events.published }}</p>
            </div>
            <div class="rounded-xl border border-bo-line bg-bo-card p-5">
                <p class="text-sm text-bo-slate">Eventos en borrador</p>
                <p class="tabular mt-1 text-4xl font-semibold">{{ events.drafts }}</p>
            </div>
        </section>

        <h2 class="mb-3 font-semibold">Personal del back-office</h2>
        <ul class="divide-y divide-bo-line overflow-hidden rounded-xl border border-bo-line bg-bo-card">
            <li v-for="group in staff" :key="group.section">
                <Link :href="route('admin.staff.index', group.section)" class="flex items-center justify-between px-5 py-3.5 hover:bg-bo-paper">
                    <span class="font-medium">{{ group.label }}</span>
                    <span class="flex items-center gap-3">
                        <span class="tabular text-bo-slate">{{ group.total }} activos</span>
                        <BoIcon name="chevronRight" :size="16" class="text-bo-muted" />
                    </span>
                </Link>
            </li>
        </ul>
    </BackOfficeLayout>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";

defineProps({
    events: { type: Object, required: true },
    staff: { type: Array, required: true },
});
</script>
