/**
 * .output/manifest.json and the gallery built from it -- shared by the
 * reporter, which records each run, and approve.ts, which marks shots stable.
 */

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import type { TestResult } from '@playwright/test/reporter';
import type { Target } from '../perf/targets';
import { outputDir, screenshotsDir } from './config';
import type { Viewport } from './viewports';
import type { VisualDiff } from './visual';

export type GalleryEntry = {
  env: string;
  baseUrl: string;
  /** The folder under .output/ the shot lives in. */
  site: string;
  page: Target;
  /** Position in tests/perf/targets.ts, so the gallery lists pages in that order. */
  pageIndex: number;
  project: string;
  viewport: Viewport;
  status: TestResult['status'];
  /** Relative to .output, so the gallery works when opened straight from disk. */
  image: string | null;
  errors: string[];
  /** Images that never loaded; a warning, not a failure (see screenshots.spec.ts). */
  brokenImages: string[];
  /** Against the stable set; null when this shot was never approved. */
  visual: VisualDiff | null;
  capturedAt: string;
};

export type Manifest = Record<string, GalleryEntry>;

const manifestPath = path.join(outputDir, 'manifest.json');
export const galleryPath = path.join(outputDir, 'gallery.html');
const templatePath = path.join(screenshotsDir, 'gallery.html');

export function readManifest(): Manifest {
  try {
    return JSON.parse(readFileSync(manifestPath, 'utf8')) as Manifest;
  } catch {
    return {};
  }
}

export function writeManifest(manifest: Manifest): void {
  // A shot deleted by hand (or a wiped .output/<site>) should drop out, not show as a broken image.
  for (const [key, entry] of Object.entries(manifest)) {
    if (entry.image && !existsSync(path.join(outputDir, entry.image))) delete manifest[key];
  }

  mkdirSync(outputDir, { recursive: true });
  writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));

  // `<` escaped so a page title or error message cannot close the script tag.
  const data = JSON.stringify(Object.values(manifest)).replace(/</g, '\\u003c');
  writeFileSync(galleryPath, readFileSync(templatePath, 'utf8').replace('/*__GALLERY_DATA__*/[]', data));
}
