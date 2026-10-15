/**
 * The screen sizes the screenshot suite renders every page at.
 *
 * Picked from the most common CSS viewports in StatCounter's screen-resolution
 * share for Poland and Europe, so a page is seen the way most visitors see it
 * rather than at a handful of round numbers. Mobile carries the most entries
 * because that is where most of the traffic -- and most of the layout bugs --
 * are.
 *
 * `device` borrows the user agent, touch and isMobile flags from a Playwright
 * descriptor, so WooCommerce and the theme take their mobile code paths (the
 * user agent alone is what `wp_is_mobile()` looks at). The viewport itself is
 * overridden with the size below, and every shot runs in Chromium: the point
 * is the layout at a width, not a WebKit rendering check.
 */

import { devices } from '@playwright/test';

export type ViewportGroup = 'mobile' | 'tablet' | 'desktop';

export type Viewport = {
  group: ViewportGroup;
  width: number;
  height: number;
  /** A key of Playwright's `devices`, for the user agent and mobile flags. */
  device: keyof typeof devices;
  /** What the size is, for the README and the test title. */
  label: string;
};

export const viewports: Viewport[] = [
  { group: 'mobile', width: 360, height: 800, device: 'Galaxy S24', label: 'Android, Samsung Galaxy S/A' },
  { group: 'mobile', width: 375, height: 667, device: 'iPhone SE (3rd gen)', label: 'iPhone SE / 8' },
  { group: 'mobile', width: 390, height: 844, device: 'iPhone 13', label: 'iPhone 12–14' },
  { group: 'mobile', width: 393, height: 852, device: 'iPhone 15', label: 'iPhone 15–16' },
  { group: 'mobile', width: 412, height: 915, device: 'Pixel 7', label: 'Android, large' },
  { group: 'mobile', width: 430, height: 932, device: 'iPhone 15 Pro Max', label: 'iPhone Pro Max / Plus' },

  { group: 'tablet', width: 768, height: 1024, device: 'iPad Mini', label: 'iPad Mini, portrait' },
  { group: 'tablet', width: 800, height: 1280, device: 'Galaxy Tab S4', label: 'Android tablet, portrait' },
  { group: 'tablet', width: 820, height: 1180, device: 'iPad (gen 7)', label: 'iPad Air / iPad 10, portrait' },
  { group: 'tablet', width: 1024, height: 768, device: 'iPad Mini landscape', label: 'iPad, landscape' },

  { group: 'desktop', width: 1280, height: 720, device: 'Desktop Chrome', label: 'HD laptop' },
  { group: 'desktop', width: 1366, height: 768, device: 'Desktop Chrome', label: 'Budget laptop' },
  { group: 'desktop', width: 1440, height: 900, device: 'Desktop Chrome', label: 'MacBook Air' },
  { group: 'desktop', width: 1536, height: 864, device: 'Desktop Chrome', label: 'Full HD laptop at 125%' },
  { group: 'desktop', width: 1920, height: 1080, device: 'Desktop Chrome', label: 'Full HD' },
  { group: 'desktop', width: 2560, height: 1440, device: 'Desktop Chrome', label: 'QHD' },
];

/** `mobile-390x844` -- the Playwright project name, so `--project 'mobile-*'` works. */
export function viewportName(viewport: Viewport): string {
  return `${viewport.group}-${viewport.width}x${viewport.height}`;
}
