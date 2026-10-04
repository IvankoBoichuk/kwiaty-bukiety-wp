/**
 * Drives Lighthouse against the browser Playwright launched, and reduces the
 * repeated runs to one number per metric.
 */

import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import lighthouse from 'lighthouse';
import { categoryIds, cdpPort, envName, metricKeys, reportDir, runs, warmupRuns } from './config';
import type { Aggregate, PageMeasurement } from './baseline';
import type { Target } from './targets';

type Lhr = NonNullable<Awaited<ReturnType<typeof lighthouse>>>['lhr'];

/**
 * Separates "this pass went wrong" from "this target is wrong". Only the former
 * is worth another attempt: a redirect means targets.ts points at the wrong
 * URL and every retry would land in the same place.
 */
class RunError extends Error {
  constructor(
    message: string,
    readonly retryable: boolean,
  ) {
    super(message);
  }
}

type Sample = {
  scores: Record<string, number>;
  metrics: Record<string, number>;
  reportPath: string;
  lighthouseVersion: string;
};

/**
 * One Lighthouse pass. Lighthouse opens its own tab over CDP rather than
 * reusing a Playwright page: it needs full control of the navigation, the
 * cache and the throttling, and a page Playwright has already touched would
 * carry state into the trace.
 *
 * `agentic-browsing` is left out of onlyCategories deliberately -- it is new in
 * Lighthouse 13, adds ~15s per run, and says nothing about page speed.
 */
async function runOnce(url: string, target: Target, iteration: number | null): Promise<Sample> {
  const result = await lighthouse(url, {
    port: cdpPort,
    output: 'html',
    logLevel: 'error',
    formFactor: 'mobile',
    onlyCategories: categoryIds,
  });

  if (!result) throw new RunError(`Lighthouse returned no result for ${url}`, true);

  const { lhr, report } = result;

  // A runtimeError means the page never loaded well enough to score -- scoring
  // the resulting zeros against a baseline would read as a catastrophic
  // regression when the real problem is a 500 or a dead container.
  if (lhr.runtimeError) {
    throw new RunError(
      `Lighthouse could not audit ${url}: [${lhr.runtimeError.code}] ${lhr.runtimeError.message}`,
      true,
    );
  }

  // Following a redirect would quietly score a different template, which is
  // exactly the trap the per-env paths in targets.ts exist to avoid.
  if (stripTrailingSlash(lhr.finalDisplayedUrl) !== stripTrailingSlash(url)) {
    throw new RunError(`${url} redirected to ${lhr.finalDisplayedUrl}; fix the path in tests/perf/targets.ts`, false);
  }

  // A discarded warm-up pass (iteration null) gets no report file -- it is not
  // a measurement of anything anyone wants to read.
  let reportPath = '';
  if (iteration !== null) {
    const dir = path.join(reportDir, envName);
    mkdirSync(dir, { recursive: true });
    reportPath = path.join(dir, `${target.id}-run${iteration}.html`);
    writeFileSync(reportPath, report);
  }

  const scores: Record<string, number> = {};
  for (const id of categoryIds) {
    const score = lhr.categories[id]?.score;
    if (typeof score === 'number') scores[id] = Math.round(score * 100);
  }

  const metrics: Record<string, number> = {};
  for (const key of metricKeys) {
    const value = readMetric(lhr, key);
    if (value !== undefined) metrics[key] = value;
  }

  return { scores, metrics, reportPath, lighthouseVersion: lhr.lighthouseVersion };
}

/**
 * Most keys are an audit's numericValue. `requests` is the exception: the
 * request count is the single most stable signal of a frontend regression here
 * and Lighthouse only exposes it as the length of a table.
 */
function readMetric(lhr: Lhr, key: string): number | undefined {
  if (key === 'requests') return lhr.audits['network-requests']?.details?.items?.length;

  const value = lhr.audits[key]?.numericValue;

  return typeof value === 'number' ? value : undefined;
}

function stripTrailingSlash(url: string): string {
  return url.replace(/\/+$/, '');
}

/**
 * Retried on purpose, the same way the deploy pipeline's smoke-test retries its
 * curl: the deployed sites sit behind Hostinger's edge, which answers the
 * occasional 5xx to a cold request, and losing a ten-minute run to one of those
 * is worse than waiting two seconds.
 *
 * `redirect: 'manual'` so a 301 surfaces here as a failed status rather than
 * warming -- and later scoring -- some other URL.
 */
async function warmUp(url: string, attempts = 3): Promise<void> {
  let last = '';

  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    try {
      const response = await fetch(url, { redirect: 'manual' });
      await response.arrayBuffer();

      if (response.ok) return;

      last = `HTTP ${response.status}`;
    } catch (error) {
      last = error instanceof Error ? error.message : String(error);
    }

    if (attempt < attempts) await new Promise((resolve) => setTimeout(resolve, attempt * 2000));
  }

  // Fail here rather than let Lighthouse score a 404 or the maintenance page.
  throw new Error(`${url} did not answer successfully after ${attempts} attempts (${last}); cannot measure it`);
}

/**
 * Retries a pass that errored out, never one that merely scored badly.
 *
 * The distinction is the whole point. Re-running a *result* until the noise
 * falls favourably is cheating, which is why the Playwright config sets
 * `retries: 0`. But a pass that died on NO_FCP produced no result at all, and
 * taking that as a verdict on the code would be worse than useless -- the
 * deployed site sits behind an edge cache that occasionally serves one slowly
 * enough for Lighthouse to give up.
 */
async function runWithRetry(url: string, target: Target, iteration: number, attempts = 2): Promise<Sample> {
  for (let attempt = 1; ; attempt += 1) {
    try {
      return await runOnce(url, target, iteration);
    } catch (error) {
      const retryable = error instanceof RunError && error.retryable;

      if (!retryable || attempt >= attempts) throw error;

      console.log(`  run ${iteration} failed, retrying: ${(error as Error).message}`);
    }
  }
}

/**
 * HTTP warm-up, then `warmupRuns` discarded Lighthouse passes (see config.ts
 * for what those are for), then `runs` measured ones.
 *
 * The HTTP warm-up is not optional, and it is a plain request on purpose.
 * Lighthouse resets storage and loads with the HTTP cache disabled, so there is
 * nothing to warm inside the browser -- what needs warming is the server: PHP
 * opcache, WooCommerce transients and, on the deployed sites, the LiteSpeed
 * page cache the deploy pipeline has just purged. Locally that first TTFB runs
 * seconds slower than the next one and would dominate the median, making the
 * baseline a measurement of cache state rather than of the code.
 *
 * Warming through a Playwright page instead leaves the browser with no open
 * window once that page closes, and the tab Lighthouse then opens never paints
 * -- the run dies with NO_FCP.
 */
export async function measurePage(target: Target, url: string): Promise<PageMeasurement> {
  await warmUp(url);

  // A warm-up pass that blows up is not worth failing the run over -- its
  // result is thrown away either way, and the measured passes below report any
  // problem that is actually persistent.
  for (let pass = 0; pass < warmupRuns; pass += 1) {
    try {
      await runOnce(url, target, null);
    } catch (error) {
      console.log(`  warm-up pass failed, continuing: ${error instanceof Error ? error.message : error}`);
    }
  }

  const samples: Sample[] = [];
  for (let iteration = 1; iteration <= runs; iteration += 1) {
    samples.push(await runWithRetry(url, target, iteration));
  }

  return {
    url,
    lighthouseVersion: samples[0].lighthouseVersion,
    scores: aggregate(samples.map((sample) => sample.scores)),
    metrics: aggregate(samples.map((sample) => sample.metrics)),
    reportPath: representativeReport(samples),
  };
}

/**
 * Per-metric median, plus the observed min and max. The spread is stored
 * because the comparison uses it as a noise floor: on a machine where LCP
 * swings by seconds between identical runs, a fixed threshold either never
 * fires or fires constantly, and the baseline's own spread is the only honest
 * estimate of how much movement means nothing. Median per metric rather than
 * one median run, so a single slow iteration cannot drag unrelated metrics.
 */
function aggregate(samples: Record<string, number>[]): Record<string, Aggregate> {
  const keys = [...new Set(samples.flatMap((sample) => Object.keys(sample)))];
  const result: Record<string, Aggregate> = {};

  for (const key of keys) {
    const values = samples.map((sample) => sample[key]).filter((value): value is number => typeof value === 'number');
    if (!values.length) continue;

    const sorted = [...values].sort((a, b) => a - b);

    // Rounded, so a baseline diff in a pull request is readable rather than a
    // wall of float noise. Two decimals keeps CLS meaningful.
    result[key] = {
      median: round(sorted[Math.floor((sorted.length - 1) / 2)]),
      min: round(sorted[0]),
      max: round(sorted[sorted.length - 1]),
      samples: values.length,
    };
  }

  return result;
}

function round(value: number): number {
  return Math.round(value * 100) / 100;
}

/** The run whose performance score is the median one -- the report worth reading. */
function representativeReport(samples: Sample[]): string {
  const ranked = [...samples].sort((a, b) => (a.scores.performance ?? 0) - (b.scores.performance ?? 0));

  return ranked[Math.floor((ranked.length - 1) / 2)].reportPath;
}
