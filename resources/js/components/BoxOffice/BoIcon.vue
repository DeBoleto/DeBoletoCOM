<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
    >
        <path v-for="(d, index) in paths" :key="index" :d="d" />
    </svg>
</template>

<script setup>
import { computed } from "vue";

const ICONS = {
    home: ["M3 10.5 12 3l9 7.5", "M5 9.5V21h14V9.5", "M10 21v-6h4v6"],
    ticket: [
        "M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4Z",
        "M14 6v2",
        "M14 11v2",
        "M14 16v2",
    ],
    chart: ["M4 20V4", "M4 20h16", "M8 16v-5", "M12 16V8", "M16 16v-3"],
    cash: ["M3 6h18v12H3z", "M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z", "M6 9v.01", "M18 15v.01"],
    user: ["M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z", "M4 21a8 8 0 0 1 16 0"],
    calendar: ["M4 6h16v15H4z", "M4 10h16", "M8 3v4", "M16 3v4"],
    file: ["M6 3h8l4 4v14H6z", "M14 3v4h4", "M9 13h6", "M9 17h6"],
    grid: ["M4 4h7v7H4z", "M13 4h7v7h-7z", "M4 13h7v7H4z", "M13 13h7v7h-7z"],
    logout: ["M15 4h4v16h-4", "M10 8l-4 4 4 4", "M6 12h10"],
    menu: ["M4 7h16", "M4 12h16", "M4 17h16"],
    close: ["M6 6l12 12", "M18 6 6 18"],
    search: ["M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z", "M20 20l-4-4"],
    pin: ["M12 21s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z", "M12 11.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"],
    venue: ["M3 21h18", "M5 21V9l7-5 7 5v12", "M9 21v-6h6v6"],
    clock: ["M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z", "M12 7v5l3 2"],
    printer: ["M7 9V3h10v6", "M7 17H4v-7h16v7h-3", "M7 14h10v7H7z"],
    download: ["M12 4v11", "M7 10l5 5 5-5", "M5 20h14"],
    phone: ["M7 3h10v18H7z", "M11 18h2"],
    mail: ["M3 6h18v12H3z", "M3 7l9 6 9-6"],
    check: ["M5 12.5 10 17l9-10"],
    alert: ["M12 3 2 20h20Z", "M12 10v4", "M12 17v.01"],
    chevronLeft: ["M15 6l-6 6 6 6"],
    chevronRight: ["M9 6l6 6-6 6"],
    plus: ["M12 5v14", "M5 12h14"],
    minus: ["M5 12h14"],
    zoomIn: ["M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z", "M20 20l-4-4", "M8 11h6", "M11 8v6"],
    zoomOut: ["M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z", "M20 20l-4-4", "M8 11h6"],
    refresh: ["M20 11a8 8 0 0 0-14.9-3", "M4 4v4h4", "M4 13a8 8 0 0 0 14.9 3", "M20 20v-4h-4"],
};

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 20 },
});

const paths = computed(() => ICONS[props.name] ?? ICONS.grid);
</script>
