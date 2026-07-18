<template>
  <section class="categories-circle-section" aria-labelledby="circle-categories-heading">
    <div class="container">
      <h2 id="circle-categories-heading" class="circle-categories-title">Categorías</h2>
      <ul class="circle-grid">
        <li v-for="cat in categories" :key="cat.slug">
          <a
            :href="`/evento/categorias/${cat.slug}`"
            class="circle-card"
            :title="cat.name"
          >
            <div class="circle-img-wrapper">
              <img
                :src="`https://deboleto.com/images/categoria/${cat.image}`"
                :alt="cat.name"
              >
              <div class="circle-overlay"></div>
              <h3 class="circle-name">{{ cat.name }}</h3>
            </div>
          </a>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup>
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
</script>

<style scoped>
.categories-circle-section {
  background: #111;
  padding: 18px 0 34px;
}

.circle-categories-title {
  color: #fff;
  text-align: center;
  font-weight: 800;
  letter-spacing: 1px;
  margin: 0 0 18px;
  text-transform: uppercase;
  font-size: 1.5rem;
}

.circle-grid {
  list-style: none;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 24px;
  margin: 0;
  padding: 0;
  justify-items: center;
}

.circle-grid li {
  width: 100%;
  display: flex;
  justify-content: center;
}

.circle-card {
  display: block;
  width: 240px;
  height: 240px;
  border-radius: 50%;
  overflow: hidden;
  text-decoration: none;
  transition: transform .35s ease, box-shadow .35s ease;
  outline: none;
  -webkit-tap-highlight-color: transparent;
}

.circle-card:focus-visible {
  outline: 3px solid var(--prop-teal, #2ba17c);
  outline-offset: 4px;
}

.circle-card:hover {
  transform: scale(1.08);
  box-shadow: 0 0 0 4px #4ecba0, 0 0 16px 6px rgba(78,203,160,.45), 0 0 40px 12px rgba(43,161,124,.18);
}

.circle-img-wrapper {
  position: relative;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  overflow: hidden;
}

.circle-img-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.circle-overlay {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: linear-gradient(to top, rgba(0,0,0,.7) 0%, transparent 50%);
  pointer-events: none;
}

.circle-name {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 28px;
  z-index: 2;
  color: #fff;
  font-weight: 800;
  margin: 0;
  text-align: center;
  font-size: .9rem;
  letter-spacing: .5px;
  text-transform: uppercase;
  padding: 0 12px;
}

@media (max-width: 767px) {
  .circle-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 18px;
  }

  .circle-card {
    width: 160px;
    height: 160px;
  }

  .circle-name {
    font-size: .75rem;
    bottom: 20px;
  }
}

@media (max-width: 400px) {
  .circle-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
  }

  .circle-card {
    width: 140px;
    height: 140px;
  }

  .circle-name {
    font-size: .7rem;
    bottom: 16px;
  }
}
</style>
