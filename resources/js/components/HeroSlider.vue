<template>

<section class="hero-slider-section">
    <div
      class="hero-slider"
      :aria-label="type === 'banners' ? 'Banners promocionales' : 'Eventos destacados'"
      @mouseenter="pause"
      @mouseleave="resume"
      @focusin="pause"
      @focusout="resume"
      @touchstart.passive="onTouchStart"
      @touchend.passive="onTouchEnd"
    >
      <div class="slides-wrapper" aria-live="polite">
        <template v-if="type === 'banners'">
          <div
            v-for="(banner, index) in banners"
            :key="index"
            class="slide"
            :class="{
              'slide--active': index === currentIndex,
              'slide--leaving': index === previousIndex,
            }"
            :aria-hidden="index !== currentIndex && index !== previousIndex"
          >
            <img
              :src="banner.image"
              alt="Banner promocional"
              class="slide-bg"
              fetchpriority="high"
            />

            <div class="slide-content slide-content--banner">
              <div class="slide-footer">
                <a :href="banner.url" class="slide-cta">Ver más</a>
              </div>
            </div>
          </div>
        </template>

        <template v-else>
          <article
            v-for="(event, index) in events"
            :key="event.id"
            class="slide"
            :class="{
              'slide--active': index === currentIndex,
              'slide--leaving': index === previousIndex,
            }"
            :aria-hidden="index !== currentIndex && index !== previousIndex"
          >
            <picture class="slide-picture">
              <source :srcset="event.image.replace('.png', '.webp')" type="image/webp" />
              <source :srcset="event.image.replace('.png', '.avif')" type="image/avif" />
              <img
                :src="event.image"
                :alt="`Fondo: ${event.title}`"
                class="slide-bg"
                fetchpriority="high"
              />
            </picture>

            <div class="slide-content">
              <div class="slide-text">
                <span
                  class="slide-category"
                  :class="`slide-category--${event.categoryColor}`"
                >
                  {{ event.category }}
                </span>
                <h2 class="slide-title">{{ event.title }}</h2>
                <p v-if="event.artist" class="slide-artist">{{ event.artist }}</p>
              </div>

              <div class="slide-footer">
                <a :href="`/evento/${event.slug}`" class="slide-cta">Comprar boletos</a>
              </div>
            </div>
          </article>
        </template>
      </div>
    </div>
  </section>

</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  events: {
    type: Array,
    default: () => [],
  },
  banners: {
    type: Array,
    default: () => [],
  },
  type: {
    type: String,
    default: 'events',
  },
})

const currentIndex = ref(0)
const previousIndex = ref(-1)
const isTransitioning = ref(false)
const slides = computed(() => props.type === 'banners' ? props.banners : props.events)
const totalSlides = computed(() => slides.value.length)

let intervalId = null
let isPaused = false

function startAutoPlay() {
  stopAutoPlay()
  intervalId = setInterval(() => {
    if (!isPaused && !isTransitioning.value) {
      next()
    }
  }, 5000)
}

function stopAutoPlay() {
  if (intervalId) {
    clearInterval(intervalId)
    intervalId = null
  }
}

function pause() { isPaused = true }
function resume() { isPaused = false }

function next() {
  if (isTransitioning.value) return
  goTo((currentIndex.value + 1) % totalSlides.value)
}

function prev() {
  if (isTransitioning.value) return
  goTo((currentIndex.value - 1 + totalSlides.value) % totalSlides.value)
}

function goTo(index) {
  if (isTransitioning.value || index === currentIndex.value) return
  isTransitioning.value = true
  previousIndex.value = currentIndex.value
  currentIndex.value = index

  setTimeout(() => {
    previousIndex.value = -1
    isTransitioning.value = false
  }, 800)
}

let touchStartX = 0
function onTouchStart(e) {
  touchStartX = e.changedTouches[0].screenX
}
function onTouchEnd(e) {
  const diff = touchStartX - e.changedTouches[0].screenX
  if (Math.abs(diff) > 50) {
    if (diff > 0) next()
    else prev()
  }
}

function onKeyDown(e) {
  if (e.key === 'ArrowLeft') { e.preventDefault(); prev() }
  if (e.key === 'ArrowRight') { e.preventDefault(); next() }
}

onMounted(() => {
  startAutoPlay()
  document.addEventListener('keydown', onKeyDown)
})

onUnmounted(() => {
  stopAutoPlay()
  document.removeEventListener('keydown', onKeyDown)
})
</script>

<style scoped>
.hero-slider-section {
  max-width: 100%;
  padding-block: var(--space-8);
}

.hero-slider {
  position: relative;
  height: min(100vh, 560px);
  overflow: hidden;
  background: var(--color-bg);
  border-radius: var(--radius-xl);
}

.slides-wrapper {
  position: relative;
  width: 100%;
  height: 100%;
}

.slide {
  position: absolute;
  inset: 0;
  opacity: 0;
  clip-path: circle(0% at center);
  transition: opacity 0.6s ease, clip-path 0.8s ease;
  pointer-events: none;
  display: flex;
  align-items: center;
  min-width: 0;
}

.slide--active {
  opacity: 1;
  clip-path: circle(100% at center);
  pointer-events: auto;
}

.slide--leaving {
  opacity: 0;
  clip-path: circle(0% at center);
  pointer-events: none;
}

.slide-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

.slide-picture {
  position: absolute;
  inset: 0;
}

.slide-overlay {
  position: absolute;
  inset: 0;

}

.slide-content {
  position: relative;
  z-index: 1;
  width: 100%;
  padding-inline: var(--space-12);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.slide-content--banner {
  height: 100%;
  justify-content: flex-end;
  padding-bottom: var(--space-3);
}

@media (max-width: 768px) {
  .slide-content {
    padding-inline: var(--space-6);
  }
}

@media (max-width: 540px) {
  .slide-content {
    padding-inline: var(--space-4);
  }
}

.slide-text {
  max-width: 560px;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

@media (min-width: 1400px) {
  .slide-text  { max-width: 640px; }
  .hero-slider { height: min(65vh, 600px); }
}

@media (min-width: 1600px) {
  .slide-text  { max-width: 720px; }
  .hero-slider { height: min(60vh, 640px); }
}

@media (min-width: 1920px) {
  .slide-text  { max-width: 800px; }
  .hero-slider { height: min(55vh, 680px); }
}

.slide-category {
  display: inline-flex;
  align-self: flex-start;
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-full);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.slide-category--concert    { background: rgba(229, 53, 171, 0.9); color: #fff; }
.slide-category--festival   { background: rgba(249, 115, 22, 0.9);  color: #fff; }
.slide-category--conference { background: rgba(59, 130, 246, 0.9);  color: #fff; }
.slide-category--theater    { background: rgba(168, 85, 247, 0.9);  color: #fff; }
.slide-category--sports     { background: rgba(34, 197, 94, 0.9);   color: #fff; }
.slide-category--edm        { background: rgba(6, 182, 212, 0.9);   color: #fff; }

.slide-title {
  font-size: clamp(var(--text-3xl), 4vw, var(--text-5xl));
  font-weight: 900;
  line-height: 1.1;
  letter-spacing: -0.03em;
  color: var(--color-text-primary);
  text-wrap: balance;
}

.slide-artist {
  font-size: var(--text-lg);
  color: var(--color-text-secondary);
  font-weight: 500;
}

.slide-footer {
  display: flex;
  justify-content: center;
}

.slide-cta {
  display: inline-flex;
  align-items: center;
  padding: var(--space-3) var(--space-6);
  border-radius: var(--radius-xs);
  font-size: var(--text-sm);
  font-weight: 700;
  background: linear-gradient(135deg, var(--color-brand), var(--color-accent));
  color: #fff;
  transition: opacity var(--transition-fast), transform var(--transition-fast);
}

.slide-cta:hover {
  opacity: 0.88;
  transform: translateY(-2px);
}

 @media (min-width: 769px) {
     .hero-slider-section {
    width: 90%;
    max-width: 90%;
	 margin-inline: auto;
	 padding-block: 0;

     }

  .hero-slider {
    border-radius: var(--radius-xl);
  }
}

@media (max-width: 768px) {
  .hero-slider {
    height: min(100vh, 420px);
  }

  .slide-overlay {
    background: linear-gradient(
      to bottom,
      rgba(10, 10, 15, 0.3) 0%,
      rgba(10, 10, 15, 0.75) 60%,
      rgba(10, 10, 15, 0.9) 100%
    );
  }

  .slide-text {
    max-width: 100%;
    gap: var(--space-3);
  }

  .slide-title {
    font-size: var(--text-2xl);
  }

  .slide-footer {
    gap: var(--space-3);
  }

}

@media (max-width: 540px) {
  .hero-slider {
    height: min(50vh, 360px);
  }

  .slide-content {
    padding-bottom: var(--space-10);
  }
}
</style>
