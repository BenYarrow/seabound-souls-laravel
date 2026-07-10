/**
 * Vitest config — the project's first JS test runner. Standalone (does not
 * merge vite.config.js) so the Laravel Vite plugin stays out of unit tests.
 * Node environment: current tests are pure functions with no DOM.
 */
import { defineConfig } from 'vitest/config'

export default defineConfig({
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
})
