<template>
    <div class="rounded-xl border border-dashed border-bo-line bg-bo-card px-6 py-12 text-center">
        <p class="font-semibold">{{ title }}</p>
        <p class="mx-auto mt-1 max-w-md text-sm text-bo-slate">{{ text }}</p>
        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    text: { type: String, default: "" },
});
</script>
