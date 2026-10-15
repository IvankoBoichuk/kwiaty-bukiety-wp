/**
 * Settings shared by the Playwright config and the spec.
 */

import path from 'node:path';
import { inferEnv } from '../perf/config';

export const screenshotsDir = import.meta.dirname;
export const outputDir = path.join(screenshotsDir, '.output');

/** The devcontainer's WordPress (.devcontainer/docker-compose.yml). */
const devcontainerUrl = 'http://localhost:8000';

export const baseUrl = (process.env.SCREENSHOTS_BASE_URL ?? devcontainerUrl).replace(/\/+$/, '');

/**
 * Picks the page paths from tests/perf/targets.ts, which differ per env.
 *
 * The devcontainer counts as dev, not local: post-create.sh seeds it from the
 * dev database dump, so it has dev's Polish permalink bases and dated posts,
 * and the "local" paths would all 301 or 404 there.
 */
export const envName = process.env.SCREENSHOTS_ENV ?? (baseUrl === devcontainerUrl ? 'dev' : inferEnv(baseUrl));

/**
 * The folder a site's shots go in. Per site rather than per env: the
 * devcontainer and the deployed dev site share the "dev" paths, and their
 * shots would otherwise overwrite each other.
 */
export const siteSlug = new URL(baseUrl).host.replace(/[^a-z0-9.-]+/gi, '-');

/**
 * 1 by default, so a shot is as wide in pixels as the viewport it shows and a
 * full set stays small enough to page through. `SCREENSHOTS_DPR=2` for
 * retina-sharp images when checking icons or image quality.
 */
export const deviceScaleFactor = Number(process.env.SCREENSHOTS_DPR ?? 1);
