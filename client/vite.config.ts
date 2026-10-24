import { fileURLToPath } from 'node:url'

import { defineConfig } from 'vite'
import react, { reactCompilerPreset } from '@vitejs/plugin-react'
import babel from '@rolldown/plugin-babel'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    babel({ presets: [reactCompilerPreset()] })
  ],
  resolve: {
    alias: {
      '@ui': fileURLToPath(new URL('./src/ui', import.meta.url)),
    },
  },
  build: {
    rolldownOptions: {
      output: {
        // Librerías en chunks propios: cambian poco entre despliegues y el navegador las conserva en caché aunque
        // cambie el código de la app. Las de gráficos, calendario y fechas solo se descargan con la pantalla que las usa.
        codeSplitting: {
          groups: [
            { name: 'react', test: /node_modules[\\/](react|react-dom|react-router|scheduler)[\\/]/, priority: 30 },
            { name: 'charts', test: /node_modules[\\/](apexcharts|react-apexcharts)[\\/]/, priority: 20 },
            { name: 'calendar', test: /node_modules[\\/]@fullcalendar[\\/]/, priority: 20 },
            { name: 'realtime', test: /node_modules[\\/](laravel-echo|pusher-js)[\\/]/, priority: 20 },
            { name: 'dates', test: /node_modules[\\/]flatpickr[\\/]/, priority: 20 },
          ],
        },
      },
    },
  },
})
