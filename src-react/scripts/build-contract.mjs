// Builds the versioned admin extension declaration artifact (design.md
// D12/task 12.3): a single, flat, types-only .d.ts bundling contract/src's
// public surface — narrow by construction, since rollup-plugin-dts inlines
// only the types actually reachable from that one entry point, not Free's
// whole source tree. Pro consumes the result as a pinned local package
// (contract/package.json) instead of aliasing into Free's own src/ or
// node_modules (the exact thing this task replaces).
import { rollup } from 'rollup'
import dts from 'rollup-plugin-dts'
import { readFileSync, mkdirSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const root = dirname(fileURLToPath(import.meta.url)) + '/..'
const contractDir = resolve(root, 'contract')
const outDir = resolve(contractDir, 'dist')

const bundle = await rollup({
  input: resolve(contractDir, 'src/index.ts'),
  external: (id) => /^react(?:\/|$)/.test(id),
  plugins: [dts({ respectExternal: true, tsconfig: resolve(root, 'tsconfig.app.json') })],
})

mkdirSync(outDir, { recursive: true })
await bundle.write({ file: resolve(outDir, 'index.d.ts'), format: 'es' })
await bundle.close()

// The contract's own package.json declares its version independently of
// ELEMACY_VERSION (the plugin release) — same reasoning as
// AdminScripts::CONTRACT_VERSION on the runtime bridge side: the type
// surface can stay stable across many releases, or need to change within
// one. Keep the two in sync by convention; nothing auto-derives one from
// the other today because they change for different reasons.
const pkg = JSON.parse(readFileSync(resolve(contractDir, 'package.json'), 'utf8'))
console.log(`[build-contract] wrote ${outDir}/index.d.ts for @elemacy/admin-contract@${pkg.version}`)
