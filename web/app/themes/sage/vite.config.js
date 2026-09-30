import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin'
import webfontDownload from 'vite-plugin-webfont-dl'
import { wordpressPlugin, wordpressThemeJson } from '@roots/vite-plugin';

// Set APP_URL if it doesn't exist for Laravel Vite plugin
if (!process.env.APP_URL) {
  process.env.APP_URL = 'http://localhost:8080';
}

const appOrigin = new URL(process.env.APP_URL).origin;
const devServerOrigin = process.env.VITE_DEV_SERVER_URL ?? 'http://localhost:5174';
const devServerHost = process.env.VITE_HMR_HOST ?? new URL(devServerOrigin).hostname;
const devServerPort = Number.parseInt(
  process.env.VITE_PORT ?? new URL(devServerOrigin).port ?? '5174',
  10,
);

export default defineConfig({
  base: '/app/themes/sage/public/build/',
  server: {
    host: '0.0.0.0',
    port: Number.isNaN(devServerPort) ? 5174 : devServerPort,
    strictPort: true,
    origin: devServerOrigin,
    cors: {
      origin: appOrigin,
    },
    hmr: {
      host: devServerHost,
    },
  },
  plugins: [
    tailwindcss(),
    laravel({
      hotFile: 'public/hot',
      buildDirectory: 'build',
      input: [
        'resources/js/app.ts',
        'resources/css/editor.css',
      ],
      refresh: true,
      assets: ['resources/images/**', 'resources/fonts/**'],
    }),

    wordpressPlugin(),

    // Generate the theme.json file in the public/build/assets directory
    // based on the Tailwind config and the theme.json file from base theme folder
    wordpressThemeJson({
      disableTailwindColors: false,
      disableTailwindFonts: false,
      disableTailwindFontSizes: false,
      disableTailwindBorderRadius: false,
    }),

    webfontDownload([], {
      assetsSubfolder: 'fonts',
      subsetsAllowed: ['latin','latin-ext']
    }),
  ],
  resolve: {
    alias: {
      '@': '/resources/js',
      '@scripts': '/resources/js',
      '@styles': '/resources/css',
      '@fonts': '/resources/fonts',
      '@images': '/resources/images',
    },
  },
})
