/**
 * The stable set and the comparison against it.
 *
 * `bun run screenshots:approve` copies the current shots into .output/stable/;
 * every later run compares its fresh shot against that copy, pixel by pixel,
 * and writes the difference to .output/diff/ for the gallery to show. Nothing
 * here fails a test: the point is to look at what moved, not to gate on it.
 */

import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';
import { outputDir } from './config';

export const stableDir = path.join(outputDir, 'stable');
export const diffDir = path.join(outputDir, 'diff');
const approvalsPath = path.join(stableDir, 'approved.json');

export type VisualDiff = {
  /** Relative to .output, like every path the gallery reads. */
  stable: string;
  /** Null when nothing changed. */
  diff: string | null;
  changedPixels: number;
  /** changedPixels over the larger of the two images, 0..1. */
  ratio: number;
  /** First and last changed row, so the viewer can scroll straight to them. */
  changedRows: [number, number] | null;
  size: [number, number];
  stableSize: [number, number];
  approvedAt: string | null;
};

/** `<site>/<page>/<viewport>.png` -- the same relative path in all three trees. */
export function shotPath(site: string, page: string, project: string): string {
  return `${site}/${page}/${project}.png`;
}

export function readApprovals(): Record<string, string> {
  try {
    return JSON.parse(readFileSync(approvalsPath, 'utf8')) as Record<string, string>;
  } catch {
    return {};
  }
}

export function writeApprovals(approvals: Record<string, string>): void {
  mkdirSync(stableDir, { recursive: true });
  writeFileSync(approvalsPath, JSON.stringify(approvals, null, 2));
}

/**
 * Compares .output/<shot> with .output/stable/<shot>; null when the shot has
 * never been approved.
 *
 * Full-page shots of the same page rarely keep their height when anything
 * changes, and pixelmatch needs equal sizes, so both are laid onto a canvas the
 * size of the larger one. The strip only one of them covers then counts as
 * changed -- which is right: the page did get longer or shorter.
 */
export function compareWithStable(shot: string): VisualDiff | null {
  const stableFile = path.join(stableDir, shot);
  if (!existsSync(stableFile)) return null;

  const current = PNG.sync.read(readFileSync(path.join(outputDir, shot)));
  const stable = PNG.sync.read(readFileSync(stableFile));

  const width = Math.max(current.width, stable.width);
  const height = Math.max(current.height, stable.height);
  const a = onCanvas(stable, width, height);
  const b = onCanvas(current, width, height);
  const output = new PNG({ width, height });

  // threshold 0.1 and no anti-aliasing: font smoothing and image resampling
  // differ by a shade between identical loads, and counting those would mark
  // every shot as changed.
  const changedPixels = pixelmatch(a.data, b.data, output.data, width, height, {
    threshold: 0.1,
    includeAA: false,
    alpha: 0.2,
    diffColor,
  });

  const diffFile = path.join(diffDir, shot);
  if (changedPixels) {
    mkdirSync(path.dirname(diffFile), { recursive: true });
    writeFileSync(diffFile, PNG.sync.write(output));
  } else {
    rmSync(diffFile, { force: true });
  }

  return {
    stable: `stable/${shot}`,
    diff: changedPixels ? `diff/${shot}` : null,
    changedPixels,
    ratio: changedPixels / (width * height),
    changedRows: changedPixels ? changedRows(output) : null,
    size: [current.width, current.height],
    stableSize: [stable.width, stable.height],
    approvedAt: readApprovals()[shot] ?? null,
  };
}

/** The colour pixelmatch paints a changed pixel in; changedRows() looks for it. */
const diffColor: [number, number, number] = [255, 0, 0];

function changedRows(diff: PNG): [number, number] | null {
  let first = -1;
  let last = -1;

  for (let y = 0; y < diff.height; y += 1) {
    for (let x = 0; x < diff.width; x += 1) {
      const i = (y * diff.width + x) * 4;
      if (diff.data[i] === diffColor[0] && diff.data[i + 1] === diffColor[1] && diff.data[i + 2] === diffColor[2]) {
        if (first < 0) first = y;
        last = y;
        break;
      }
    }
  }

  return first < 0 ? null : [first, last];
}

function onCanvas(image: PNG, width: number, height: number): PNG {
  if (image.width === width && image.height === height) return image;

  const canvas = new PNG({ width, height, fill: true });
  PNG.bitblt(image, canvas, 0, 0, image.width, image.height, 0, 0);

  return canvas;
}
