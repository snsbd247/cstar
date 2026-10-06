import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

const backend = 'http://127.0.0.1:8000'

// Dev: Vite proxies /api and /sanctum to Laravel so the SPA and API share one origin
// (Sanctum cookie auth, no CORS). Build: output goes into Laravel's public/spa folder,
// so production needs no Node.js — Laravel/Apache serve the static files.
// VITE_APP_BASE is the sub-folder the site lives in ('' on a domain root, '/cstar' on XAMPP —
// see .env.xampp and `npm run build:xampp`).
export default defineConfig(({ command, mode }) => {
  const appBase = (loadEnv(mode, process.cwd(), 'VITE_').VITE_APP_BASE ?? '').replace(/\/$/, '')

  return {
    base: command === 'build' ? `${appBase}/spa/` : '/',
    plugins: [react(), tailwindcss()],
    server: {
      port: 5173,
      proxy: {
        '/api': backend,
        '/sanctum': backend,
        '/storage': backend,
      },
    },
    build: {
      outDir: '../backend/public/spa',
      emptyOutDir: true,
    },
  }
})
