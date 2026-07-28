/**
 * Vitest config — the project's first JS test runner. Standalone (does not
 * merge vite.config.js) so the Laravel Vite plugin stays out of unit tests.
 * Node environment: current tests are pure functions with no DOM.
 *
 * Mirrors the `@` -> resources/js alias from vite.config.js so source files
 * that use `@/...` imports (rather than relative imports) resolve under
 * vitest too.
 */
import { defineConfig } from 'vitest/config'
import { fileURLToPath } from 'node:url'

export default defineConfig({
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
})
