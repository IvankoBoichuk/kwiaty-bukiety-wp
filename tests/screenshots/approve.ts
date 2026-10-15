/**
 * Marks the current shots as the stable set the next runs compare against.
 *
 *   bun run screenshots:approve                          everything in the gallery
 *   bun run screenshots:approve -- home                  one page, every viewport and site
 *   bun run screenshots:approve -- localhost-8000/home/mobile-390x844
 *   bun run screenshots:approve -- --force               include failed / broken-image shots
 *
 * A filter matches anywhere in `<site>/<page>/<viewport>`. Shots whose test
 * failed or whose images did not all load are skipped unless --force: a stable
 * copy with holes in it would show the holes as "fixed" on every later run.
 */

import { copyFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { outputDir } from './config';
import { galleryPath, readManifest, writeManifest } from './manifest';
import { readApprovals, stableDir, writeApprovals } from './visual';

const args = process.argv.slice(2);
const force = args.includes('--force');
const filters = args.filter((arg) => !arg.startsWith('--'));

const manifest = readManifest();
const approvals = readApprovals();
const now = new Date().toISOString();

const approved: string[] = [];
const skipped: string[] = [];

for (const entry of Object.values(manifest)) {
  if (!entry.image) continue;

  const shot = entry.image;
  if (filters.length && !filters.some((filter) => shot.includes(filter))) continue;

  const problem =
    entry.status !== 'passed'
      ? 'test failed'
      : entry.brokenImages?.length
        ? `${entry.brokenImages.length} image(s) did not load`
        : null;

  if (problem && !force) {
    skipped.push(`${shot.replace(/\.png$/, '')}  (${problem})`);
    continue;
  }

  const stableFile = path.join(stableDir, shot);
  mkdirSync(path.dirname(stableFile), { recursive: true });
  copyFileSync(path.join(outputDir, shot), stableFile);
  approvals[shot] = now;

  // The shot is now its own baseline; the next run fills in a real comparison.
  const [width, height] = entry.visual?.size ?? [entry.viewport.width, 0];
  entry.visual = {
    stable: `stable/${shot}`,
    diff: null,
    changedPixels: 0,
    ratio: 0,
    changedRows: null,
    size: [width, height],
    stableSize: [width, height],
    approvedAt: now,
  };

  approved.push(shot);
}

writeApprovals(approvals);
writeManifest(manifest);

console.log(`Approved ${approved.length} shot(s) as stable -> ${stableDir}`);
if (skipped.length) {
  console.log(`\nSkipped ${skipped.length} (re-run them, or pass --force to approve anyway):`);
  for (const line of skipped) console.log(`  ${line}`);
}
if (!approved.length && !skipped.length) {
  console.log(filters.length ? `Nothing matches ${filters.join(', ')}.` : 'No shots yet. Run bun run screenshots first.');
}
console.log(`\nGallery: ${galleryPath}`);
