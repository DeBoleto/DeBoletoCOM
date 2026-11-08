<template>
    <nav v-if="page.last_page > 1" class="mt-4 flex items-center justify-between text-sm" aria-label="Paginación">
        <p class="tabular text-bo-slate">{{ page.from }}–{{ page.to }} de {{ page.total }}</p>
        <div class="flex gap-2">
            <Link
                :href="page.prev_page_url ?? ''"
                preserve-scroll
                class="rounded-lg border border-bo-line px-3 py-1.5 font-medium"
                :class="page.prev_page_url ? 'hover:border-bo-slate' : 'pointer-events-none opacity-40'"
                :aria-disabled="!page.prev_page_url"
            >
                Anterior
            </Link>
            <Link
                :href="page.next_page_url ?? ''"
                preserve-scroll
                class="rounded-lg border border-bo-line px-3 py-1.5 font-medium"
                :class="page.next_page_url ? 'hover:border-bo-slate' : 'pointer-events-none opacity-40'"
                :aria-disabled="!page.next_page_url"
            >
                Siguiente
            </Link>
        </div>
    </nav>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({ page: { type: Object, required: true } });
</script>
