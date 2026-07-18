<template>
  <section class="categories-strip" aria-labelledby="categories-heading">
    <div class="container">
      <h2 id="categories-heading" class="categories-title">Categorías</h2>
      <div class="cat-wrapper">
        <a
          href="javascript:void(0)"
          class="cat-arrow cat-arrow-prev"
          aria-label="Anterior"
          @click.prevent="scrollPrev"
        >
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M10 12L6 8L10 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
        <div
          ref="catScrollRef"
          class="cat-scroll"
          @mousedown="onMouseDown"
          @mousemove="onMouseMove"
          @mouseup="onMouseUp"
          @mouseleave="onMouseUp"
          @click.prevent="onClick"
        >
          <ul class="cat-track">
            <li
              v-for="cat in categories"
              :key="cat.slug"
              :data-categoria-slug="cat.slug"
            >
              <a
                :href="`/evento/categorias/${cat.slug}`"
                :title="cat.name"
                draggable="false"
              >
                <img
                  :src="`https://deboleto.com/images/categoria/${cat.image}`"
                  :alt="cat.name"
                  draggable="false"
                >
                <h3>{{ cat.name }}</h3>
              </a>
            </li>
          </ul>
        </div>
        <a
          href="javascript:void(0)"
          class="cat-arrow cat-arrow-next"
          aria-label="Siguiente"
          @click.prevent="scrollNext"
        >
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M6 4L10 8L6 12" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  categories: {
    type: Array,
    default: () => [
      { name: 'OLMECAS',     slug: 'olmecas',      image: 'olmecas.jpg' },
      { name: 'JAGUARES',    slug: 'jaguares',     image: 'jaguares.jpg' },
      { name: 'CARRERAS',    slug: 'carreras',     image: 'carreras.jpg' },
      { name: 'NAPOLI',      slug: 'napoli',       image: 'napoli.jpg' },
      { name: 'TEATRO',      slug: 'teatro',       image: 'teatro.jpg' },
      { name: 'CONCIERTOS',  slug: 'conciertos',   image: 'conciertos.jpg' },
      { name: 'CONFERENCIAS',slug: 'conferencias', image: 'conferencias.jpg' },
      { name: 'INFANTILES',  slug: 'infantiles',   image: 'infantiles.jpg' },
    ],
  },
})

const catScrollRef = ref(null)

let isDown = false
let startX = 0
let startScroll = 0
let moved = false

function onMouseDown(e) {
  if (!catScrollRef.value) return
  isDown = true
  moved = false
  startX = e.pageX
  startScroll = catScrollRef.value.scrollLeft
  catScrollRef.value.classList.add('dragging')
}

function onMouseMove(e) {
  if (!isDown || !catScrollRef.value) return
  const dx = e.pageX - startX
  if (Math.abs(dx) > 3) moved = true
  catScrollRef.value.scrollLeft = startScroll - dx
}

function onMouseUp() {
  if (!isDown || !catScrollRef.value) return
  isDown = false
  catScrollRef.value.classList.remove('dragging')
}

function onClick(e) {
  if (moved) {
    e.preventDefault()
    e.stopPropagation()
  }
}

function getCatStep() {
  if (!catScrollRef.value) return 440
  const item = catScrollRef.value.querySelector('.cat-track li')
  const w = item ? item.getBoundingClientRect().width : 220
  return (w + 6) * 2
}

function scrollPrev() {
  if (!catScrollRef.value) return
  catScrollRef.value.scrollBy({ left: -getCatStep(), behavior: 'smooth' })
}

function scrollNext() {
  if (!catScrollRef.value) return
  catScrollRef.value.scrollBy({ left: getCatStep(), behavior: 'smooth' })
}
</script>

<style scoped>
.categories-strip {
  background: #000;
  padding: 18px 0 34px;
}

.categories-title {
  color: #fff;
  text-align: center;
  font-weight: 800;
  letter-spacing: 1px;
  margin: 0 0 18px;
  text-transform: uppercase;
  font-size: 1.5rem;
}

.cat-wrapper {
  position: relative;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 18px;
}

.cat-scroll {
  overflow-x: auto;
  overflow-y: hidden;
  scroll-snap-type: x proximity;
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  -ms-overflow-style: none;
  cursor: grab;
}

.cat-scroll::-webkit-scrollbar {
  display: none;
}

.cat-scroll.dragging {
  cursor: grabbing;
  scroll-behavior: auto;
}

.cat-track {
  display: flex;
  gap: 6px;
  margin: 0;
  padding: 4px 0;
  list-style: none;
}

.cat-track li {
  flex: 0 0 auto;
  width: 220px;
  scroll-snap-align: start;
  border-radius: 12px;
  position: relative;
}

.cat-track li a {
  position: relative;
  display: block;
  transition: transform .35s ease, box-shadow .35s ease;
  transform-style: preserve-3d;
  outline: none;
  -webkit-tap-highlight-color: transparent;
}

.cat-track li a:focus,
.cat-track li a:active {
  outline: none;
}

.cat-track li a::after {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 12px;
  background: linear-gradient(to top, rgba(0,0,0,.65), transparent 60%);
  pointer-events: none;
}

.cat-track li a img {
  border-radius: 12px;
  display: block;
  width: 100%;
  pointer-events: none;
}

.cat-track li a:hover {
  transform: perspective(700px) rotateY(7deg) scale(1.06) translateZ(10px);
  box-shadow: 0 14px 30px rgba(0,0,0,.55);
}

.cat-track li a h3 {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 10px;
  z-index: 2;
  color: #fff;
  font-weight: 800;
  margin: 0;
  text-align: center;
  font-size: 1rem;
}

.cat-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 5;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0,0,0,.45);
  border-radius: 50%;
  transition: background .2s ease;
}

.cat-arrow:hover {
  background: rgba(0,0,0,.8);
}

.cat-arrow-prev {
  left: 0;
}

.cat-arrow-next {
  right: 0;
}

@media (max-width: 767px) {
  .cat-track li {
    width: 150px;
  }

  .cat-arrow {
    display: none;
  }

  .cat-wrapper {
    padding: 0 12px;
  }
}
</style>
