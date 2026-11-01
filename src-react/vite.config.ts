import path from "path"
import fs from "node:fs"
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

function readWpConfigDevServer() {
  const wpConfigPath = path.resolve(__dirname, '../../../..', 'wp-config.php')

  if (!fs.existsSync(wpConfigPath)) {
    return undefined
  }

  const wpConfig = fs.readFileSync(wpConfigPath, 'utf8')
  const match = wpConfig.match(/define\(\s*['"]ELEMACY_VITE_DEV_SERVER['"]\s*,\s*['"]([^'"]+)['"]\s*\)/)

  return match?.[1]
}

// Translation calls must reach the shipped bundle as `wp.i18n.__(...)`: the
// WordPress.org language-pack extractor and `wp i18n make-pot` read the built
// admin.js, and a minifier renames an imported `__` to a short local name they
// cannot recognise. So in the app's own sources, `__(` and `_n(` become calls
// on WordPress's runtime (the same instance the alias below re-exports) and
// their imports are dropped. Type checking (`tsc -b`) still sees the imports.
function wpI18nCalls() {
  const calls = ['__', '_n']
  return {
    name: 'elemacy-wp-i18n-calls',
    enforce: 'pre' as const,
    transform(code: string, id: string) {
      if (!id.startsWith(path.resolve(__dirname, 'src')) || !/\.(t|j)sx?$/.test(id) || !/from\s*['"]@wordpress\/i18n['"]/.test(code)) {
        return null
      }
      let out = code.replace(/import\s*\{([^}]*)\}\s*from\s*(['"])@wordpress\/i18n\2;?/g, (_match, names: string) => {
        const kept = names.split(',').map(name => name.trim()).filter(name => name && !calls.includes(name))
        return kept.length ? `import { ${kept.join(', ')} } from '@wordpress/i18n';` : ''
      })
      out = out.replace(/(?<![\w$.])(__|_n)\(/g, 'wp.i18n.$1(')
      return { code: out, map: null }
    },
  }
}

const DEV_SERVER_URL = process.env.ELEMACY_VITE_DEV_SERVER ?? readWpConfigDevServer() ?? 'http://localhost:5173'
const DEV_SERVER = new URL(DEV_SERVER_URL)

// https://vite.dev/config/
export default defineConfig({
  base: './',
  server: {
    origin: DEV_SERVER_URL,
    host: DEV_SERVER.hostname,
    port: Number(DEV_SERVER.port) || 5173,
    strictPort: true,
    cors: true,
    hmr: {
      host: DEV_SERVER.hostname
    }
  },
  plugins: [wpI18nCalls(), react(), tailwindcss()],
  // No static public/ assets belong in the WP build output (kills vite.svg).
  publicDir: false,
  resolve: {
    dedupe: ["react", "react-dom"],
    alias: {
      "@": path.resolve(__dirname, "./src"),
      // Use WordPress's enqueued wp-i18n (window.wp.i18n) instead of bundling a
      // second copy, so wp_set_script_translations actually feeds the app.
      "@wordpress/i18n": path.resolve(__dirname, "./src/lib/wp-i18n.ts"),
    },
  },
  build: {
    outDir: '../assets/admin',
    // Keep hand-authored admin assets (admin-global.*, sidebar-sync.js,
    // dependency-notice.css) that live alongside the build output.
    emptyOutDir: false,
    rollupOptions: {
      // Entry is the module itself, not index.html, so the build emits only
      // js/admin.js + css/admin.css (no dead index.html in dist).
      input: {
        admin: path.resolve(__dirname, 'src/main.tsx'),
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: 'css/[name].[ext]',
      }
    }
  }
})
