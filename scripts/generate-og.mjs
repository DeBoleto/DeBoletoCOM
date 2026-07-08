import sharp from 'sharp'

const logo = 'public/logo_blanco.png'
const output = 'public/og-image.png'

const svgGradient = `<svg width="1200" height="630">
  <rect width="1200" height="630" fill="url(#g)"/>
  <defs>
    <radialGradient id="g" cx="80%" cy="80%" r="70%">
      <stop offset="0%" stop-color="#7c3aed" stop-opacity="0.15"/>
      <stop offset="100%" stop-color="#0a0a0f" stop-opacity="0"/>
    </radialGradient>
  </defs>
</svg>`

const svgTagline = `<svg width="1200" height="630">
  <text x="600" y="290" text-anchor="middle" font-family="sans-serif" font-size="24" fill="#a1a1aa" letter-spacing="4">TU PLATAFORMA DE BOLETOS</text>
  <text x="600" y="410" text-anchor="middle" font-family="sans-serif" font-size="14" fill="#52525b" letter-spacing="2">CONCIERTOS · FESTIVALES · TEATRO · CONFERENCIAS</text>
</svg>`

sharp({
  create: {
    width: 1200,
    height: 630,
    channels: 4,
    background: { r: 10, g: 10, b: 15, alpha: 1 },
  },
})
  .composite([
    { input: Buffer.from(svgGradient), top: 0, left: 0 },
    { input: logo, top: 160, left: 450 },
    { input: Buffer.from(svgTagline), top: 0, left: 0 },
  ])
  .png()
  .toFile(output)
  .then(() => console.log('OG image created:', output))
  .catch(err => { console.error(err); process.exit(1) })
