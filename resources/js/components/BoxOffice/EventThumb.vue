<template>
    <div class="overflow-hidden rounded-lg bg-bo-ink-2" :class="sizeClass">
        <img
            v-if="src && !failed"
            :src="src"
            :alt="alt"
            class="h-full w-full object-cover"
            loading="lazy"
            @error="failed = true"
        />
        <div v-else class="flex h-full w-full items-center justify-center text-white/40">
            <BoIcon name="ticket" :size="28" />
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import BoIcon from "./BoIcon.vue";

const props = defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: "" },
    size: { type: String, default: "md" },
});

const failed = ref(false);

const sizeClass = computed(
    () =>
        ({
            sm: "size-14 shrink-0",
            md: "aspect-[4/3] w-full",
            lg: "aspect-[3/4] w-full",
        })[props.size],
);
</script>
