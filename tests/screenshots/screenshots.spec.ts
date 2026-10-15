/**
 * A full-page screenshot of every template at every viewport in viewports.ts.
 *
 * The pages are the perf gate's targets (tests/perf/targets.ts): they cover
 * every layout the theme ships, and their per-env paths already account for the
 * permalink differences between local and the deployed sites.
 *
 * Each shot lands in .output/<site>/<page>/<viewport>.png, so one page's sizes
 * sit side by side, and is attached to the HTML report too. Once a set has been
 * approved (approve.ts), each shot is also compared against its stable copy. On top of the
 * picture, each test checks the one responsive bug a screenshot hides well: a
 * page wider than the screen, which on a phone means sideways scrolling.
 *
 * Run it with `bun run screenshots`; the README has the filters.
 */

import path from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { targetsFor } from '../perf/targets';
import { baseUrl, envName, outputDir, siteSlug } from './config';
import type { Viewport } from './viewports';
import { compareWithStable, shotPath } from './visual';

/**
 * Every run sees the same moment, so the header's delivery countdown
 * (resources/js/modules/delivery-timer.ts) reads the same and does not show up
 * as a change against the stable set. A weekday morning, outside any holiday,
 * so it shows the countdown rather than a "next delivery" message.
 * setFixedTime only pins Date: timers still run, so lazy loading and the
 * scroll below behave as usual.
 */
const frozenNow = new Date('2026-10-14T10:00:00+02:00');

const targets = targetsFor(envName);

for (const target of targets) {
  test(`${target.label} — ${target.path}`, async ({ page }, testInfo) => {
    const viewport = testInfo.project.metadata.viewport as Viewport;
    testInfo.annotations.push({ type: 'viewport', description: `${viewport.width}x${viewport.height} ${viewport.label}` });
    // Read by gallery-reporter.ts, which needs the page even when no shot was taken.
    testInfo.annotations.push({ type: 'page', description: JSON.stringify(target) });

    await page.clock.setFixedTime(frozenNow);
    const response = await page.goto(target.path, { waitUntil: 'load' });

    expect(response?.status(), `${baseUrl}${target.path} did not answer 200`).toBe(200);
    // Same trap as in the perf gate: a redirect would photograph some other template.
    expect(stripTrailingSlash(page.url()), 'the page redirected; fix the path in tests/perf/targets.ts').toBe(
      stripTrailingSlash(`${baseUrl}${target.path}`),
    );

    await hideCookieBanner(page);
    await loadEverything(page);
    await freezeRandomContent(page);

    const shot = shotPath(siteSlug, target.id, testInfo.project.name);
    const file = path.join(outputDir, shot);
    await page.screenshot({
      path: file,
      fullPage: true,
      animations: 'disabled',
      caret: 'hide',
    });
    await testInfo.attach(`${target.id}-${testInfo.project.name}.png`, { path: file, contentType: 'image/png' });

    // Here rather than in the reporter: decoding two full-page PNGs is the slow
    // part, and the workers do it in parallel.
    const visual = compareWithStable(shot);
    if (visual) testInfo.annotations.push({ type: 'visual-diff', description: JSON.stringify(visual) });

    // Soft, so the screenshot above is kept either way -- it is what shows *where* the overflow is.
    expect.soft(await overflowingElements(page), 'elements wider than the viewport (page scrolls sideways)').toEqual([]);

    // A warning rather than a failure: locally these come from the dev server's
    // throttling (see loadEverything), not from the page. The gallery flags them.
    const broken = await brokenImages(page);
    if (broken.length) testInfo.annotations.push({ type: 'broken-images', description: JSON.stringify(broken) });
  });
}

/**
 * Swaps out the parts of a page that differ on every load by design, so the
 * comparison with the stable set shows changes to the page, not a re-roll.
 *
 * - "Podobne produkty" on a product: WooCommerce shuffles related products on
 *   each request. Masking the grid is not enough -- the titles wrap to a
 *   different number of lines, so its height and everything below it moves
 *   too. It becomes a fixed-height placeholder; the heading stays, so the shot
 *   still shows the block is there.
 */
async function freezeRandomContent(page: Page): Promise<void> {
  const related = page
    .locator('section', { has: page.locator('h2', { hasText: /Podobne produkty|Similar products/i }) })
    .locator('.grid');

  await related.evaluateAll((grids) => {
    for (const grid of grids) {
      grid.replaceChildren();
      grid.setAttribute(
        'style',
        'display:flex;align-items:center;justify-content:center;height:320px;border:2px dashed #b9b9b0;border-radius:16px;color:#8a8a80;font:14px system-ui',
      );
      grid.textContent = 'Podobne produkty — losowe, ukryte w zrzutach';
    }
  });
}

/**
 * Hidden rather than accepted: it would otherwise cover the bottom of every
 * mobile shot, and clicking "accept" would switch on the tracking scripts it
 * gates, on every run, in the analytics of whichever site this points at.
 */
async function hideCookieBanner(page: Page): Promise<void> {
  await page.addStyleTag({
    content: '.cky-consent-container, .cky-modal, .cky-overlay, .cky-btn-revisit-wrapper { display: none !important; }',
  });
}

/**
 * Scrolls the page through once, top to bottom, then waits for the images and
 * fonts that brought in. A full-page shot taken straight after `load` shows
 * grey boxes below the fold: the product grids and category tiles use
 * `loading="lazy"`, and those only start loading once they near the viewport.
 */
async function loadEverything(page: Page): Promise<void> {
  await page.evaluate(async () => {
    const step = Math.max(window.innerHeight / 2, 200);

    for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await new Promise((resolve) => setTimeout(resolve, 100));
    }

    window.scrollTo(0, 0);
  });

  await waitForImages(page);

  // The devcontainer has no uploads: its .htaccess 302s every /app/uploads/ hit
  // to the dev server, which stalls part of them when a whole run asks at once
  // (a page loaded on its own has none broken). One more ask catches most of
  // those; more only adds load and runs into the test timeout.
  if ((await brokenImages(page)).length) {
    await page.evaluate(() => {
      for (const image of document.images) {
        if (!image.complete || image.naturalWidth) continue;
        // Re-setting the attributes restarts the load, srcset selection included.
        if (image.srcset) image.srcset = image.srcset;
        image.src = image.src;
      }
    });
    await waitForImages(page);
  }

  await page.evaluate(() => document.fonts.ready);
}

/** An image that never loads should not hang the run; brokenImages() names it instead. */
async function waitForImages(page: Page): Promise<void> {
  await page
    .waitForFunction(() => [...document.images].every((image) => image.complete), undefined, { timeout: 15_000 })
    .catch(() => {});
}

/**
 * Images that finished loading with nothing to show -- a 404, a reset
 * connection. SVGs are left out: one without intrinsic dimensions reports a
 * naturalWidth of 0 even when it renders fine.
 */
async function brokenImages(page: Page): Promise<string[]> {
  return page.evaluate(() =>
    [...document.images]
      .filter((image) => image.complete && !image.naturalWidth && (image.currentSrc || image.src))
      .map((image) => image.currentSrc || image.src)
      .filter((url) => !/\.svg(\?|$)/i.test(url))
      .map((url) => url.replace(location.origin, '')),
  );
}

/**
 * The elements whose right edge sticks out past the viewport, as short
 * selectors -- the outermost ones only, since every child of an overflowing
 * row overflows too and listing them all buries the culprit. Anything inside a
 * box that clips its overflow is skipped: that is how a carousel parks its
 * off-screen slides, and it scrolls nothing.
 */
async function overflowingElements(page: Page): Promise<string[]> {
  return page.evaluate(() => {
    const width = document.documentElement.clientWidth;

    if (document.documentElement.scrollWidth <= width) return [];

    const describe = (element: Element) =>
      element.tagName.toLowerCase() +
      (element.id ? `#${element.id}` : '') +
      [...element.classList].slice(0, 3).map((name) => `.${name}`).join('');

    const clipped = (element: Element) => {
      for (let parent = element.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
        if (getComputedStyle(parent).overflowX !== 'visible') return true;
      }

      return false;
    };

    const offenders = [...document.body.querySelectorAll('*')].filter((element) => {
      const rect = element.getBoundingClientRect();

      return rect.width > 0 && rect.right > width + 1 && !clipped(element);
    });

    return offenders
      .filter((element) => !offenders.some((other) => other !== element && other.contains(element)))
      .map(describe);
  });
}

function stripTrailingSlash(url: string): string {
  return url.replace(/\/+$/, '');
}
