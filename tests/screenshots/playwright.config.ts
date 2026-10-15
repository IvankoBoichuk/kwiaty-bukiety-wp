import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';
import { patchChromiumLibraryPath } from '../perf/config';
import { baseUrl, deviceScaleFactor, outputDir, screenshotsDir } from './config';
import { viewportName, viewports } from './viewports';

patchChromiumLibraryPath();

export default defineConfig({
  testDir: screenshotsDir,
  testMatch: /screenshots\.spec\.ts$/,
  globalSetup: path.join(screenshotsDir, 'global-setup.ts'),

  // Unlike the perf gate nothing here is timed, so the shots run in parallel.
  fullyParallel: true,
  retries: process.env.CI ? 1 : 0,
  forbidOnly: !!process.env.CI,
  timeout: 90_000,

  outputDir: path.join(outputDir, 'test-results'),
  reporter: [
    ['list'],
    ['html', { outputFolder: path.join(outputDir, 'report'), open: 'never' }],
    // Every shot on one page, grouped -- .output/gallery.html.
    [path.join(screenshotsDir, 'gallery-reporter.ts')],
  ],

  use: {
    baseURL: baseUrl,
    // Visitors from Poland; keeps any locale-dependent formatting stable.
    locale: 'pl-PL',
    timezoneId: 'Europe/Warsaw',
  },

  projects: viewports.map((viewport) => ({
    name: viewportName(viewport),
    use: {
      ...devices[viewport.device],
      defaultBrowserType: 'chromium',
      viewport: { width: viewport.width, height: viewport.height },
      screen: { width: viewport.width, height: viewport.height },
      deviceScaleFactor,
    },
    metadata: { viewport },
  })),
});
