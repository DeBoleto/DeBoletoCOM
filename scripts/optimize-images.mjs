import sharp from 'sharp'
import { readdirSync, existsSync } from 'fs'
import { join, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const publicDir = join(__dirname, '..', 'public')

const pngFiles = []

for (const folder of ['events', '.']) {
  const dir = join(publicDir, folder)
  if (!existsSync(dir)) continue
  const files = readdirSync(dir).filter(f => f.endsWith('.png'))
  for (const file of files) {
    pngFiles.push(join(dir, file))
  }
}

for (const input of pngFiles) {
  const rel = input.replace(publicDir, '').replace(/^[\\/]/, '')
  const webp = input.replace(/\.png$/, '.webp')
  const avif = input.replace(/\.png$/, '.avif')

  console.log(`→ ${rel}`)

  await sharp(input).webp({ quality: 80, effort: 4 }).toFile(webp)
  await sharp(input).avif({ quality: 65, effort: 4 }).toFile(avif)
}

console.log('\n✓ Done — WebP and AVIF copies generated.')
