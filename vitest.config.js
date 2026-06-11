import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['Tests/JavaScript/**/*.test.js'],
    },
});
