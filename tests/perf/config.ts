/**
 * Settings and thresholds for the Lighthouse gate.
 *
 * Everything the gate measures is read from one Lighthouse run per iteration,
 * mobile form factor with Lighthouse's own simulated throttling -- that is the
 * profile Google ranks on, and simulated throttling is far steadier than
 * applied throttling on a loaded dev box.
 */

import { homedir } from 'node:os';
import path from 'node:path';
import { existsSync } from 'node:fs';

export const perfDir = import.meta.dirname;
export const baselineDir = path.join(perfDir, 'baseline');
export const reportDir = path.join(perfDir, '.reports');

export const baseUrl = (process.env.PERF_BASE_URL ?? 'http://localhost:8084').replace(/\/+$/, '');

/**
 * Which baseline file to compare against. Derived from the host so that
 * `PERF_BASE_URL=https://dev...` automatically picks baseline/dev.json instead
 * of silently diffing a remote site against localhost numbers -- the two are
 * nowhere near comparable (LiteSpeed + a real CDN vs. PHP-FPM in Docker).
 */
export const envName = process.env.PERF_ENV ?? inferEnv(baseUrl);

/** Lighthouse runs per page. Odd, so the median is an actual observed run. */
export const runs = Number(process.env.PERF_RUNS ?? 3);

/**
 * Lighthouse passes whose results are thrown away, before the measured ones.
 *
 * An HTTP warm-up only warms the HTML. The *browser* loads are what make
 * LiteSpeed generate the WebP variants of every image and settle on its
 * optimised markup, and it does not get there in one hit. Measured on the dev
 * home page, successive loads went 1688 KB, 1688 KB, 371 KB, 191 KB, 191 KB,
 * with the DOM dropping from 839 elements to 739 along the way -- the page
 * converges on a steady state from about the fourth load. Three discarded
 * passes put the measured ones past that point.
 *
 * Locally there is no such layer, one pass is enough, and the deployed default
 * would add minutes per page for nothing. Raising Lighthouse's
 * networkQuietThresholdMs was tried first and changed nothing: the steady state
 * is the same with a 3s quiet window, it just takes those loads to reach.
 *
 * The CI step pays this in full on every run, by design -- the deploy purges
 * the LiteSpeed and Hostinger caches a few steps earlier, so the site it
 * measures is always cold.
 */
export const warmupRuns = Number(process.env.PERF_WARMUP_RUNS ?? (envName === 'local' ? 1 : 3));

/**
 * Fixed CDP port. Safe because the config pins workers to 1 -- concurrent
 * Lighthouse runs would compete for the same CPU and make every number
 * meaningless, so there is never a second browser wanting this port.
 */
export const cdpPort = Number(process.env.PERF_CDP_PORT ?? 9222);

/** Capture mode: overwrite the baseline for whichever pages the run covers. */
export const updateBaseline = process.env.PERF_UPDATE_BASELINE === '1';

/**
 * Print the comparison but never fail on it.
 *
 * This exists for one specific reason, and it is not a convenience. The
 * deployed sites do not serve a stable document: measured on dev, every one of
 * the four pages flips between a heavy variant and a stripped one whose images
 * never load -- 144 KB against 1685 KB on the home page, 737 KB against 67 KB
 * on the blog post -- and it flips in *both* directions between runs minutes
 * apart, carrying the Best-practices and SEO scores with it. That is the
 * hosting layer (LiteSpeed optimisation, lazy-loading, edge-cache state)
 * serving two different pages, not measurement noise, so no number captured
 * there is comparable to the next one and no amount of warm-up fixes it.
 *
 * Until that is sorted out, the CI step reports and the local run gates. Drop
 * PERF_REPORT_ONLY from .woodpecker/ci.yaml to make it a gate again -- that is
 * the only change needed.
 */
export const reportOnly = process.env.PERF_REPORT_ONLY === '1';

function inferEnv(url: string): string {
  const { hostname } = new URL(url);

  if (hostname === 'localhost' || hostname === '127.0.0.1' || hostname.endsWith('.local')) return 'local';
  if (hostname.startsWith('dev.')) return 'dev';

  return 'prod';
}

/**
 * Chromium needs libnspr4/libnss3/libasound2, which are not installed
 * system-wide on every dev box here and cannot be without root. Where the
 * documented no-root prefix exists (see README), point the loader at it before
 * Playwright spawns the browser; elsewhere -- CI images, in particular -- this
 * is a no-op.
 */
export function patchChromiumLibraryPath(): void {
  const prefix = process.env.PERF_CHROME_LIB_PATH ?? path.join(homedir(), '.local/share/chrome-deps/usr/lib/x86_64-linux-gnu');

  if (!existsSync(prefix)) return;

  process.env.LD_LIBRARY_PATH = [prefix, process.env.LD_LIBRARY_PATH].filter(Boolean).join(':');
}

export type Unit = 'ms' | 'bytes' | 'count' | 'points' | 'ratio';

export type Threshold = {
  key: string;
  label: string;
  unit: Unit;
  /** Regression allowed as a fraction of the baseline median. */
  pct: number;
  /** Regression allowed in absolute units, whichever is larger. */
  abs: number;
};

/**
 * Lighthouse category scores, 0-100. Deterministic apart from performance,
 * which is derived from the timings below and inherits all of their noise --
 * hence the only non-zero allowance here.
 */
export const categoryThresholds: Threshold[] = [
  { key: 'performance', label: 'Performance', unit: 'points', pct: 0, abs: 5 },
  { key: 'accessibility', label: 'Accessibility', unit: 'points', pct: 0, abs: 0 },
  { key: 'best-practices', label: 'Best practices', unit: 'points', pct: 0, abs: 0 },
  { key: 'seo', label: 'SEO', unit: 'points', pct: 0, abs: 0 },
];

export const categoryIds = categoryThresholds.map((threshold) => threshold.key);

/**
 * Audit numericValues, plus `requests`, which audit.ts derives from the
 * network-requests table because Lighthouse exposes no number for it.
 *
 * The weight group at the bottom is what makes this gate worth running on a
 * noisy machine: transfer size, unused bytes, DOM size and request count barely
 * move between runs, so a tight threshold there catches the regressions a
 * frontend change actually causes (a stray library in the Vite bundle, a
 * template that stopped lazy-loading) long before the timings agree.
 */
export const metricThresholds: Threshold[] = [
  { key: 'first-contentful-paint', label: 'FCP', unit: 'ms', pct: 0.2, abs: 200 },
  { key: 'largest-contentful-paint', label: 'LCP', unit: 'ms', pct: 0.2, abs: 300 },
  { key: 'speed-index', label: 'Speed Index', unit: 'ms', pct: 0.2, abs: 300 },
  { key: 'total-blocking-time', label: 'TBT', unit: 'ms', pct: 0.3, abs: 100 },
  { key: 'cumulative-layout-shift', label: 'CLS', unit: 'ratio', pct: 0.5, abs: 0.02 },
  // TTFB is dominated by server and cache state rather than by anything in the
  // theme, and locally it swings by seconds. The wide relative allowance only
  // loosens the gate where the median is already large -- i.e. locally; on dev,
  // where TTFB sits around 45 ms on a LiteSpeed hit, the 200 ms floor is what
  // applies, and that still catches a real PHP regression.
  { key: 'server-response-time', label: 'TTFB', unit: 'ms', pct: 0.75, abs: 200 },
  { key: 'bootup-time', label: 'JS bootup', unit: 'ms', pct: 0.3, abs: 200 },
  { key: 'mainthread-work-breakdown', label: 'Main-thread work', unit: 'ms', pct: 0.3, abs: 300 },
  { key: 'total-byte-weight', label: 'Transfer size', unit: 'bytes', pct: 0.1, abs: 50 * 1024 },
  { key: 'unused-javascript', label: 'Unused JS', unit: 'bytes', pct: 0.15, abs: 50 * 1024 },
  { key: 'unused-css-rules', label: 'Unused CSS', unit: 'bytes', pct: 0.15, abs: 20 * 1024 },
  { key: 'dom-size-insight', label: 'DOM elements', unit: 'count', pct: 0.1, abs: 50 },
  { key: 'requests', label: 'Requests', unit: 'count', pct: 0.1, abs: 3 },
];

export const metricKeys = metricThresholds.map((threshold) => threshold.key);
