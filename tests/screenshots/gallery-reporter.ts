/**
 * Writes .output/gallery.html: every screenshot on one page, grouped by page
 * and device class, instead of one click per test in Playwright's report.
 *
 * Results go through .output/manifest.json first, merged with whatever earlier
 * runs left there, so a filtered run (`--project 'mobile-*'`, `--grep Home`)
 * refreshes its own shots without dropping the rest from the gallery.
 */

import type { FullResult, Reporter, TestCase, TestResult } from '@playwright/test/reporter';
import { targetsFor, type Target } from '../perf/targets';
import { baseUrl, envName, siteSlug } from './config';
import { galleryPath, readManifest, writeManifest, type GalleryEntry } from './manifest';
import type { Viewport } from './viewports';
import { shotPath, type VisualDiff } from './visual';

export default class GalleryReporter implements Reporter {
  private readonly entries: GalleryEntry[] = [];

  onTestEnd(test: TestCase, result: TestResult): void {
    // Pushed at run time, so they arrive on the result; test.annotations only carries declared ones.
    const annotations = [...(result.annotations ?? []), ...test.annotations];
    const read = (type: string) => annotations.find((annotation) => annotation.type === type)?.description;
    const page = read('page');
    const broken = read('broken-images');
    const visual = read('visual-diff');
    const viewport = test.parent.project()?.metadata?.viewport as Viewport | undefined;

    if (!page || !viewport) return;

    const target = JSON.parse(page) as Target;
    const project = test.parent.project()!.name;

    // A retry calls this again; the later attempt simply replaces the entry.
    this.entries.push({
      env: envName,
      baseUrl,
      site: siteSlug,
      page: target,
      pageIndex: targetsFor(envName).findIndex((candidate) => candidate.id === target.id),
      project,
      viewport,
      status: result.status,
      // Where the spec saves the shot -- not the attachment's path, which is a
      // copy inside test-results/ that Playwright wipes at the start of every
      // run. And only when this run took one: a test that died first (a 404, a
      // timeout) may still find an older shot on disk.
      image: result.attachments.some((attachment) => attachment.contentType === 'image/png')
        ? shotPath(siteSlug, target.id, project)
        : null,
      errors: result.errors.map((error) => stripAnsi(error.message ?? String(error.value ?? ''))),
      brokenImages: broken ? (JSON.parse(broken) as string[]) : [],
      visual: visual ? (JSON.parse(visual) as VisualDiff) : null,
      capturedAt: new Date().toISOString(),
    });
  }

  onEnd(_result: FullResult): void {
    if (!this.entries.length) return;

    const manifest = readManifest();

    for (const entry of this.entries) {
      manifest[`${entry.baseUrl}/${entry.page.id}/${entry.project}`] = entry;
    }

    writeManifest(manifest);

    const changed = this.entries.filter((entry) => entry.visual?.changedPixels).length;
    const compared = this.entries.filter((entry) => entry.visual).length;
    const summary = compared
      ? `  ${changed} of ${compared} shot(s) differ from the stable set`
      : '  no stable set yet -- mark this run stable with: bun run screenshots:approve';

    console.log(`\nGallery: ${galleryPath}\n${summary}\n  open it with: bun run screenshots:gallery\n`);
  }

  printsToStdio(): boolean {
    return false;
  }
}

function stripAnsi(text: string): string {
  // eslint-disable-next-line no-control-regex
  return text.replace(/\u001b\[[0-9;]*m/g, '');
}
