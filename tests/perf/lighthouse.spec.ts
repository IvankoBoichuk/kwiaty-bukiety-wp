/**
 * The gate: one test per template, each comparing a fresh Lighthouse median
 * against the committed baseline for the current environment.
 *
 * Run it with `bun run perf` from the repo root. `bun run perf:update` accepts
 * the current numbers as the new baseline -- do that deliberately, after
 * looking at why they moved.
 */

import { chromium, expect, test, type Browser } from '@playwright/test';
import { measurePage } from './audit';
import { baselinePath, compare, formatComparison, loadBaseline, regressions, writePageBaseline } from './baseline';
import { baseUrl, cdpPort, envName, reportOnly, runs, updateBaseline } from './config';
import { targetsFor } from './targets';

const targets = targetsFor(envName);

// The runs are already serialised by `workers: 1` in the config -- two
// Lighthouse passes sharing a CPU measure the contention, not the site. What is
// deliberately *not* used here is describe-level serial mode: it aborts the
// remaining pages as soon as one fails, and a regression on the home page is no
// reason to stop reporting the other three.
let browser: Browser;

test.beforeAll(async () => {
  // Our own browser rather than the `browser` fixture, because Lighthouse
  // connects over CDP and needs a port it can be told about up front.
  // --no-sandbox is for the CI container, which runs as root.
  browser = await chromium.launch({
    args: [`--remote-debugging-port=${cdpPort}`, '--no-sandbox'],
  });
});

test.afterAll(async () => {
  await browser?.close();
});

test.describe(`lighthouse [${envName}] ${baseUrl}`, () => {
  for (const target of targets) {
    test(`${target.label} — ${target.path}`, async ({}, testInfo) => {
      const url = `${baseUrl}${target.path}`;
      const measurement = await measurePage(target, url);

      await testInfo.attach(`lighthouse-${target.id}.html`, {
        path: measurement.reportPath,
        contentType: 'text/html',
      });

      if (updateBaseline) {
        writePageBaseline(envName, baseUrl, target.id, measurement);
        console.log(`\n${target.label}: baseline captured from ${runs} run(s) -> ${baselinePath(envName)}`);

        return;
      }

      const previous = loadBaseline(envName)?.pages[target.id];
      const rows = compare(measurement, previous);
      const table = formatComparison(rows);

      console.log(`\n${target.label} (${url})\n${table}\n`);
      await testInfo.attach('comparison.txt', { body: table, contentType: 'text/plain' });

      const failed = regressions(rows);

      if (reportOnly) {
        const summary = failed.length
          ? `${failed.length} regression(s) not failing the run: ${failed.map((row) => row.label).join(', ')}`
          : 'no regressions';
        console.log(`  report-only — ${summary}`);

        return;
      }

      expect(
        previous,
        `No baseline for "${target.id}" in ${baselinePath(envName)}. ` +
          `Capture one with: PERF_BASE_URL=${baseUrl} bun run perf:update`,
      ).toBeDefined();

      expect(
        failed.map((row) => row.label),
        `Performance regression on ${target.label}:\n${table}`,
      ).toEqual([]);
    });
  }
});
