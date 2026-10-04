import path from 'node:path';
import { defineConfig } from '@playwright/test';
import { patchChromiumLibraryPath, perfDir, reportDir, runs, warmupRuns } from './config';

patchChromiumLibraryPath();

export default defineConfig({
  testDir: perfDir,
  // The only spec here; keeps `playwright test` from picking up the Pest suite.
  testMatch: /lighthouse\.spec\.ts$/,

  // Performance numbers are only comparable when nothing else is on the CPU.
  fullyParallel: false,
  workers: 1,

  // No retries on purpose. A retry on a perf gate re-rolls the dice until the
  // noise comes out favourable, which is the opposite of what this measures.
  retries: 0,
  forbidOnly: !!process.env.CI,

  // The discarded warm-up passes plus `runs` measured ones, at roughly a minute
  // each on a cold local stack, with room for a slow one.
  timeout: (runs + warmupRuns + 1) * 120_000,
  globalTimeout: 90 * 60 * 1000,

  // Every test here is slow by design; the warning is pure noise.
  reportSlowTests: null,

  outputDir: path.join(reportDir, 'test-results'),
  reporter: [['list'], ['html', { outputFolder: path.join(reportDir, 'playwright'), open: 'never' }]],
});
