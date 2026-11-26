<template>
    <component
        :is="icon"
        v-if="icon"
        :size="size"
        :stroke-width="2"
        aria-hidden="true"
    />
    <svg
        v-else
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path d="M20 6 9 17l-5-5" />
    </svg>
</template>

<script setup>
// Ícono de un elemento del kit de una carrera. El organizador guarda el nombre del ícono
// de Lucide (kits_elementos.icono) de su lista curada (organizador apps/catalogos/iconos.py,
// misma versión de Lucide); sin ícono, o uno que no esté aquí, se muestra la palomita.
import { computed } from "vue";
import {
    Apple,
    Award,
    Backpack,
    BadgeCheck,
    Bike,
    Candy,
    Coffee,
    Cookie,
    Cpu,
    CupSoda,
    Droplets,
    FileText,
    Flag,
    Footprints,
    Gift,
    GlassWater,
    Glasses,
    Hash,
    HeartPulse,
    Map,
    Medal,
    Milk,
    Package,
    Percent,
    Ribbon,
    Sandwich,
    Shirt,
    ShieldCheck,
    ShoppingBag,
    Sticker,
    Sun,
    Tag,
    Ticket,
    Timer,
    Trophy,
    Utensils,
    Watch,
} from "@lucide/vue";

const ICONS = {
    apple: Apple,
    award: Award,
    backpack: Backpack,
    "badge-check": BadgeCheck,
    bike: Bike,
    candy: Candy,
    coffee: Coffee,
    cookie: Cookie,
    cpu: Cpu,
    "cup-soda": CupSoda,
    droplets: Droplets,
    "file-text": FileText,
    flag: Flag,
    footprints: Footprints,
    gift: Gift,
    "glass-water": GlassWater,
    glasses: Glasses,
    hash: Hash,
    "heart-pulse": HeartPulse,
    map: Map,
    medal: Medal,
    milk: Milk,
    package: Package,
    percent: Percent,
    ribbon: Ribbon,
    sandwich: Sandwich,
    shirt: Shirt,
    "shield-check": ShieldCheck,
    "shopping-bag": ShoppingBag,
    sticker: Sticker,
    sun: Sun,
    tag: Tag,
    ticket: Ticket,
    timer: Timer,
    trophy: Trophy,
    utensils: Utensils,
    watch: Watch,
};

const props = defineProps({
    name: { type: String, default: "" },
    size: { type: Number, default: 16 },
});

const icon = computed(() => ICONS[props.name] ?? null);
</script>
