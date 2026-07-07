<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

interface Proposal {
  id: string
  name: string
  desc: string
  url: string
}

defineProps<{
  proposals: Proposal[]
}>()
</script>

<template>
  <Head title="Inicio" />

  <div class="min-h-screen" style="background:#0a0e14; font-family:'Poppins',sans-serif;">

    <!-- Header -->
    <header style="padding:32px 5% 0; display:flex; align-items:center; justify-content:space-between;">
      <div style="font-family:'Righteous',cursive; font-size:26px; color:#3dc994; letter-spacing:0.5px;">
        De<span style="color:#e8eef5;">Boleto</span><span
          style="font-family:'Poppins',sans-serif; font-size:13px; font-weight:700;
                 background:#2ba17c; color:#fff; padding:2px 8px; border-radius:5px; margin-left:6px;">MX</span>
      </div>
      <div style="font-size:13px; color:#7e8fa3;">v2 — Selección de propuesta UI</div>
    </header>

    <!-- Hero text -->
    <div style="padding:52px 5% 40px; text-align:center;">
      <h1 style="font-size:clamp(24px,4vw,40px); font-weight:800; color:#e8eef5; margin-bottom:12px;">
        Elige la propuesta de diseño
      </h1>
      <p style="font-size:16px; color:#7e8fa3; max-width:520px; margin:0 auto;">
        Tres propuestas listas para revisar. Abre cada una, prueba el carrusel, las cards y las animaciones — luego decides.
      </p>
    </div>

    <!-- Proposal cards -->
    <div style="padding:0 5% 80px; display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:22px; max-width:1100px; margin:0 auto;">

      <a
        v-for="p in proposals"
        :key="p.id"
        :href="p.url"
        target="_blank"
        style="display:block; background:#111820; border:1px solid rgba(43,161,124,0.18);
               border-radius:16px; overflow:hidden; text-decoration:none;
               transition:transform 0.25s, box-shadow 0.25s, border-color 0.25s;"
        onmouseenter="this.style.transform='translateY(-7px)';this.style.boxShadow='0 20px 48px rgba(0,0,0,0.5),0 0 24px rgba(43,161,124,0.2)';this.style.borderColor='rgba(43,161,124,0.45)'"
        onmouseleave="this.style.transform='';this.style.boxShadow='';this.style.borderColor='rgba(43,161,124,0.18)'"
      >
        <!-- Letter badge -->
        <div style="padding:28px 28px 0;">
          <div style="width:52px; height:52px; border-radius:14px;
                      background:linear-gradient(135deg,#2ba17c,#3dc994);
                      display:flex; align-items:center; justify-content:center;
                      font-size:24px; font-weight:900; color:#fff; margin-bottom:18px;">
            {{ p.id.toUpperCase() }}
          </div>
          <div style="font-size:17px; font-weight:700;
                      background:linear-gradient(120deg,#2ba17c,#3dc994,#a8f0dc);
                      -webkit-background-clip:text; -webkit-text-fill-color:transparent;
                      background-clip:text; margin-bottom:8px;">
            {{ p.name }}
          </div>
          <div style="font-size:13px; color:#7e8fa3; line-height:1.6;">
            {{ p.desc }}
          </div>
        </div>
        <!-- CTA -->
        <div style="padding:20px 28px 24px; margin-top:16px; border-top:1px solid rgba(255,255,255,0.06);
                    display:flex; align-items:center; justify-content:space-between;">
          <span style="font-size:13px; color:#2ba17c; font-weight:600;">Ver propuesta</span>
          <span style="color:#3dc994; font-size:18px;">→</span>
        </div>
      </a>

    </div>

  </div>
</template>
