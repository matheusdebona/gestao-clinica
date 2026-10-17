#!/usr/bin/env node
/**
 * Rasterize HOF Pay home-screen icons from the square brand mark.
 *
 * White mark + gold accent on opaque #5956A6. No rounded corners (iOS
 * applies its own mask). One-off: do not add sharp as a runtime dep.
 *
 *   npm install --prefix /tmp/hofpay-icon-gen sharp
 *   node scripts/generate-app-icons.mjs
 */

import { existsSync } from 'node:fs'
import { mkdtemp, copyFile, mkdir, readFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

async function loadSharp() {
  try {
    return (await import('sharp')).default
  } catch {
    const fallbacks = [
      join(tmpdir(), 'hofpay-icon-gen/node_modules/sharp/dist/index.mjs'),
      join('/tmp/hofpay-icon-gen/node_modules/sharp/dist/index.mjs'),
    ]
    for (const fallback of fallbacks) {
      if (existsSync(fallback)) {
        return (await import(pathToFileURL(fallback).href)).default
      }
    }
    throw new Error(
      'sharp is not installed. Generate with:\n  npm install --prefix /tmp/hofpay-icon-gen sharp\n  node scripts/generate-app-icons.mjs',
    )
  }
}

const sharp = await loadSharp()

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..')
const MARK_PATH = join(ROOT, 'apps/web/public/brand/hofpay-mark-icon.svg')
const BRAND_PURPLE = '#5956A6'
const MARK_WHITE = '#FFFFFF'
const GOLD_ON_PURPLE = '#E8C9A0'

/** Padding around the 1220×1220 mark tile (landscape mark is already inset vertically). */
const ANY_PADDING = 0.12
const MASKABLE_PADDING = 0.22

const OUTPUTS = [
  { file: 'apple-touch-icon.png', size: 180, padding: ANY_PADDING },
  { file: 'icon-192.png', size: 192, padding: ANY_PADDING },
  { file: 'icon-512.png', size: 512, padding: ANY_PADDING },
  { file: 'icon-512-maskable.png', size: 512, padding: MASKABLE_PADDING },
]

const PUBLIC_DIRS = [join(ROOT, 'apps/web/public'), join(ROOT, 'apps/site/public')]

function bakeMarkSvg(source) {
  return source
    .replace(/\scurrentColor/g, ` ${MARK_WHITE}`)
    .replace(/fill="currentColor"/g, `fill="${MARK_WHITE}"`)
    .replace(/fill="#BB9562"/gi, `fill="${GOLD_ON_PURPLE}"`)
    .replace(/<svg\b([^>]*)>/, (match, attrs) => {
      if (/\bwidth=/.test(attrs) && /\bheight=/.test(attrs)) return match
      return `<svg width="1220" height="1220"${attrs}>`
    })
}

async function renderIcon({ size, padding, dest }) {
  const pad = Math.round(size * padding)
  const markSize = size - pad * 2
  const markPng = await sharp(markSvgBuffer, { density: 384 })
    .resize(markSize, markSize, {
      fit: 'contain',
      background: { r: 0, g: 0, b: 0, alpha: 0 },
    })
    .png()
    .toBuffer()

  await sharp({
    create: {
      width: size,
      height: size,
      channels: 3,
      background: BRAND_PURPLE,
    },
  })
    .composite([{ input: markPng, top: pad, left: pad }])
    .flatten({ background: BRAND_PURPLE })
    .removeAlpha()
    .png({ compressionLevel: 9 })
    .toFile(dest)
}

const markSvgBuffer = Buffer.from(bakeMarkSvg(await readFile(MARK_PATH, 'utf8')))

const staging = await mkdtemp(join(tmpdir(), 'hofpay-app-icons-'))

for (const spec of OUTPUTS) {
  const dest = join(staging, spec.file)
  await renderIcon({ ...spec, dest })
  const meta = await sharp(dest).metadata()
  if (meta.width !== spec.size || meta.height !== spec.size) {
    throw new Error(`${spec.file}: expected ${spec.size}×${spec.size}, got ${meta.width}×${meta.height}`)
  }
  if (meta.hasAlpha) {
    throw new Error(`${spec.file}: must be opaque (no alpha)`)
  }
  console.log(`wrote ${spec.file} ${meta.width}×${meta.height} ${meta.channels}ch`)
}

for (const dir of PUBLIC_DIRS) {
  await mkdir(dir, { recursive: true })
  for (const spec of OUTPUTS) {
    await copyFile(join(staging, spec.file), join(dir, spec.file))
  }
  console.log(`copied icons → ${dir}`)
}
