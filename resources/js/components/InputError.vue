<script setup>
defineProps({
    message: String,
});
</script>

<template>
    <div v-show="message">
        <p class="text-sm" style="color: var(--color-danger);">
            {{ message }}
        </p>
    </div>
</template>
