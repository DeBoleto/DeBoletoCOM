<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0" style="background-color: var(--color-bg);">
        <div>
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 shadow-lg overflow-hidden sm:rounded-lg" style="background-color: var(--color-surface-1);">
            <slot />
        </div>
    </div>
</template>
