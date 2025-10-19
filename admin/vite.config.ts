import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: 4007,
    host: '0.0.0.0'
  },
  base: '/admin/',
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
  },
})