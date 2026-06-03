import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'node',
    include: ['test/**/*.test.js'],
    // Integration tests open a DB pool + session store and mutate shared rows,
    // so run files serially to avoid cross-file races.
    fileParallelism: false,
    testTimeout: 20000,
    hookTimeout: 30000,
  },
});
