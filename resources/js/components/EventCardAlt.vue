<template>
    <a
        :href="`/evento/${event.slug}`"
        class="event-card-alt"
        :aria-label="`Ver evento: ${event.title}`"
    >
        <picture class="card-image-wrap">
            <source :srcset="webpSrc" type="image/webp" />
            <source :srcset="avifSrc" type="image/avif" />
            <img
                :src="image"
                :alt="`Imagen del evento: ${event.title}`"
                class="card-image"
                loading="lazy"
                decoding="async"
            />
        </picture>

        <div v-if="dateBadge" class="date-badge" aria-hidden="true">
            <span class="d">{{ dateBadge.day }}</span>
            <span class="m">{{ dateBadge.month }}</span>
        </div>

        <span
            v-if="promoLabel"
            class="promo-pill"
            :style="promotionStyle(promoLabel)"
            :title="`Promoción ${promoLabel}`"
            >{{ promoLabel }}</span
        >

        <div class="card-meta">
            <h3 class="meta-title">{{ event.title }}</h3>
            <p v-if="locationLine" class="meta-location">{{ locationLine }}</p>
            <p v-if="event.priceFormatted" class="meta-price">
                Desde: <strong>{{ event.priceFormatted }}</strong>
            </p>
        </div>
    </a>
</template>

<script setup>
import { computed } from "vue";
import { promotionStyle } from "./promotionTone.js";

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
});

const image = computed(() => props.event.image || "");
const webpSrc = computed(() => image.value.replace(".png", ".webp"));
const avifSrc = computed(() => image.value.replace(".png", ".avif"));

const promoLabel = computed(() => {
    if (props.event.promotion) {
        return props.event.promotion;
    }

    const p = props.event.promociones;
    const value = Array.isArray(p) ? p[0] : p;
    return value ? String(value) : "";
});

const dateBadge = computed(() => {
    const parts = String(props.event.date ?? "").trim().split(/\s+/);
    const day = (parts[0] ?? "").split(/[–-]/)[0];
    return day ? { day, month: (parts[1] ?? "").toUpperCase() } : null;
});

const locationLine = computed(() =>
    [props.event.city, props.event.estado, props.event.hora]
        .filter((v) => typeof v === "string" && v.trim() !== "")
        .join(" / "),
);
</script>

<style scoped>
.event-card-alt {
    position: relative;
    display: block;
    overflow: hidden;
    aspect-ratio: 3 / 4;
    border-radius: var(--radius-xl);
    text-decoration: none;
    color: inherit;
    cursor: pointer;
    transition:
        transform var(--transition-base),
        border-color var(--transition-base),
        box-shadow var(--transition-base);
}

.event-card-alt:hover {
    transform: scale(1.04) translateY(-6px);
    text-decoration: none;
    color: inherit;
}

.event-card-alt:focus-visible {
    outline: 2px solid var(--color-brand);
    outline-offset: 2px;
}

.card-image-wrap {
    position: absolute;
    inset: 0;
}

.card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.event-card-alt:hover .card-image {
    transform: scale(1.05);
}

.event-card-alt::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        to bottom,
        rgba(0, 0, 0, 0) 50%,
        rgba(0, 0, 0, 0.55) 66%,
        rgba(0, 0, 0, 0.88) 82%,
        rgba(0, 0, 0, 0.96) 100%
    );
    pointer-events: none;
}

.date-badge {
    position: absolute;
    top: var(--space-3);
    left: var(--space-3);
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: var(--space-2) var(--space-3);
    border-radius: var(--radius-lg);
    background: rgba(10, 10, 15, 0.85);
    line-height: 1;
}

.date-badge .d {
    color: #fff;
    font-size: 20px;
    font-weight: 800;
}

.date-badge .m {
    margin-top: 2px;
    color: var(--color-brand);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
}

.promo-pill {
    position: absolute;
    top: var(--space-3);
    right: var(--space-3);
    z-index: 1;
    max-width: calc(100% - 88px);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-full);
    background: #c026d3;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.card-meta {
    position: absolute;
    inset-inline: 0;
    bottom: 0;
    z-index: 1;
    display: flex;
    flex-direction: column;
    gap: var(--space-1);
    padding: var(--space-4);
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.85);
}

.meta-title {
    font-size: var(--text-base);
    font-weight: 800;
    line-height: 1.25;
    color: #fff;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.meta-location {
    font-size: var(--text-xs);
    color: rgba(255, 255, 255, 0.85);
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.meta-price {
    font-size: var(--text-xs);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(255, 255, 255, 0.75);
    font-weight: 600;
}

.meta-price strong {
    font-weight: 800;
    color: var(--color-brand);
    letter-spacing: -0.02em;
}
</style>
