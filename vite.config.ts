import { defineConfig } from "vite"

/**
 * Builds the Blade builder's script into dist/ for apps that don't build
 * javaabu/dynamic-menubuilder with their own Vite. javaabu/manifest finds it through
 * dist/.vite/manifest.json, keyed by the entry path. dist/ is committed; CI fails when
 * it's out of date.
 *
 * The React components and pages aren't prebuilt: apps build them from resources/js.
 */
export const entries = ["resources/js/blade/menu-builder.ts"]

export default defineConfig({
  // Relative URLs, as javaabu/manifest serves dist/ from a sub-path.
  base: "./",
  publicDir: false,
  build: {
    outDir: "dist",
    emptyOutDir: true,
    manifest: true,
    target: "es2020",
    sourcemap: false,
    reportCompressedSize: false,
    rollupOptions: { input: entries },
  },
})
