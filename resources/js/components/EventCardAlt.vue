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

        <span v-if="hasPromos" class="promo-pill" aria-label="Promoción">{{
            promoLabel
        }}</span>

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

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
});

const image = computed(() => props.event.image || "");
const webpSrc = computed(() => image.value.replace(".png", ".webp"));
const avifSrc = computed(() => image.value.replace(".png", ".avif"));

const hasPromos = computed(() => {
    const p = props.event.promociones;
    return Array.isArray(p) ? p.length > 0 : Boolean(p);
});

const promoLabel = computed(() => {
    const p = props.event.promociones;
    const value = Array.isArray(p) ? p[0] : p;
    return value ? String(value).toUpperCase() : "PROMOCIÓN";
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
    box-shadow:
        0 0 0 3px #4ecba0,
        0 0 12px 4px rgba(78, 203, 160, 0.6),
        0 0 30px 10px rgba(60, 175, 135, 0.22),
        0 0 60px 18px rgba(43, 161, 124, 0.07),
        0 10px 30px rgba(0, 0, 0, 0.45);
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

.promo-pill {
    position: absolute;
    top: var(--space-3);
    left: var(--space-3);
    z-index: 1;
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-full);
    background: #0066ff;
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
    padding: var(--space-5);
    background: linear-gradient(
        to top,
        rgba(0, 0, 0, 0.9) 0%,
        rgba(0, 0, 0, 0.45) 60%,
        rgba(0, 0, 0, 0) 100%
    );
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
