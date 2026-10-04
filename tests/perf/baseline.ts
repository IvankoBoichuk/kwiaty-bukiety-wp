/**
 * Baseline storage and the regression check itself.
 *
 * One JSON file per environment under baseline/, committed to git, so a diff on
 * that file is the record of "we accepted this performance change" -- and a
 * reviewer can see it happen.
 */

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { baselineDir, categoryThresholds, metricThresholds, runs, type Threshold, type Unit } from './config';

export type Aggregate = {
  median: number;
  min: number;
  max: number;
  samples: number;
};

export type PageMeasurement = {
  url: string;
  lighthouseVersion: string;
  scores: Record<string, Aggregate>;
  metrics: Record<string, Aggregate>;
  reportPath: string;
};

export type PageBaseline = {
  url: string;
  capturedAt: string;
  runs: number;
  scores: Record<string, Aggregate>;
  metrics: Record<string, Aggregate>;
};

export type Baseline = {
  env: string;
  baseUrl: string;
  lighthouseVersion: string;
  pages: Record<string, PageBaseline>;
};

export function baselinePath(env: string): string {
  return path.join(baselineDir, `${env}.json`);
}

export function loadBaseline(env: string): Baseline | undefined {
  const file = baselinePath(env);

  if (!existsSync(file)) return undefined;

  return JSON.parse(readFileSync(file, 'utf8')) as Baseline;
}

/**
 * Read-modify-write of the whole file, one page at a time. Safe only because
 * the Playwright config pins workers to 1; it is also what lets
 * `--grep product` re-capture a single page without discarding the others.
 */
export function writePageBaseline(env: string, baseUrl: string, pageId: string, measurement: PageMeasurement): void {
  const existing = loadBaseline(env);
  const baseline: Baseline = {
    env,
    baseUrl,
    lighthouseVersion: measurement.lighthouseVersion,
    pages: existing?.pages ?? {},
  };

  baseline.pages[pageId] = {
    url: measurement.url,
    capturedAt: new Date().toISOString(),
    runs,
    scores: measurement.scores,
    metrics: measurement.metrics,
  };

  mkdirSync(baselineDir, { recursive: true });
  writeFileSync(baselinePath(env), `${JSON.stringify(baseline, null, 2)}\n`);
}

export type Verdict = 'ok' | 'regression' | 'improved' | 'new';

export type ComparisonRow = {
  label: string;
  unit: Unit;
  baseline?: number;
  current: number;
  /**
   * current - baseline, in the metric's own unit: what a reader expects to see.
   * The verdict is decided separately, on a delta normalised so that positive
   * means "worse" whichever way the metric points -- printing that normalised
   * number instead would show a score falling 68 -> 65 as "+3".
   */
  change?: number;
  allowed?: number;
  verdict: Verdict;
};

/**
 * How much movement is tolerated: the configured absolute floor, the configured
 * relative allowance, or the spread the baseline itself showed across its runs
 * -- whichever is largest.
 *
 * That last term is what keeps the gate usable. The local stack swings LCP by
 * seconds between identical runs, so a 20% threshold alone would fire on noise
 * every time; on dev, where LiteSpeed serves the same bytes every run, the
 * spread collapses to near zero and the configured thresholds take over.
 */
function allowance(threshold: Threshold, baseline: Aggregate): number {
  return Math.max(threshold.abs, threshold.pct * Math.abs(baseline.median), baseline.max - baseline.min);
}

function compareGroup(
  thresholds: Threshold[],
  current: Record<string, Aggregate>,
  previous: Record<string, Aggregate> | undefined,
  higherIsBetter: boolean,
): ComparisonRow[] {
  const rows: ComparisonRow[] = [];

  for (const threshold of thresholds) {
    const now = current[threshold.key];
    if (!now) continue;

    const before = previous?.[threshold.key];

    if (!before) {
      rows.push({ label: threshold.label, unit: threshold.unit, current: now.median, verdict: 'new' });
      continue;
    }

    // Normalise so that a positive delta is always a regression, whichever way
    // the metric points.
    const delta = higherIsBetter ? before.median - now.median : now.median - before.median;
    const allowed = allowance(threshold, before);

    rows.push({
      label: threshold.label,
      unit: threshold.unit,
      baseline: before.median,
      current: now.median,
      change: now.median - before.median,
      allowed,
      verdict: delta > allowed ? 'regression' : delta < -allowed ? 'improved' : 'ok',
    });
  }

  return rows;
}

export function compare(measurement: PageMeasurement, previous: PageBaseline | undefined): ComparisonRow[] {
  return [
    ...compareGroup(categoryThresholds, measurement.scores, previous?.scores, true),
    ...compareGroup(metricThresholds, measurement.metrics, previous?.metrics, false),
  ];
}

function format(value: number, unit: Unit): string {
  switch (unit) {
    case 'ms':
      return `${Math.round(value)} ms`;
    case 'bytes':
      return `${(value / 1024).toFixed(1)} KB`;
    case 'ratio':
      return value.toFixed(3);
    case 'points':
      return String(Math.round(value));
    default:
      return String(Math.round(value));
  }
}

function signed(value: number, unit: Unit): string {
  const sign = value > 0 ? '+' : value < 0 ? '-' : '';

  return `${sign}${format(Math.abs(value), unit)}`;
}

const marks: Record<Verdict, string> = {
  ok: '  ok',
  regression: 'FAIL',
  improved: '  up',
  new: ' new',
};

/** Fixed-width table, because this is read in a terminal far more often than in a report. */
export function formatComparison(rows: ComparisonRow[]): string {
  const columns = rows.map((row) => [
    marks[row.verdict],
    row.label,
    row.baseline === undefined ? '-' : format(row.baseline, row.unit),
    format(row.current, row.unit),
    row.change === undefined ? '-' : signed(row.change, row.unit),
    row.allowed === undefined ? '-' : `±${format(row.allowed, row.unit)}`,
  ]);

  const header = ['', 'metric', 'baseline', 'current', 'change', 'allowed'];
  const widths = header.map((_, index) => Math.max(...[header, ...columns].map((row) => row[index].length)));
  const line = (row: string[]) => row.map((cell, index) => cell.padEnd(widths[index])).join('  ').trimEnd();

  return [line(header), line(widths.map((width) => '-'.repeat(width))), ...columns.map(line)].join('\n');
}

export function regressions(rows: ComparisonRow[]): ComparisonRow[] {
  return rows.filter((row) => row.verdict === 'regression');
}
