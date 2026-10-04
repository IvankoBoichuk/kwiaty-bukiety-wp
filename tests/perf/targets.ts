/**
 * The four templates the Lighthouse gate watches. One page each, because a
 * single run is ~50s and the whole point is to be runnable before a commit:
 * these four cover every layout the theme actually ships (front page, single
 * product, product archive, single post), so a regression in shared assets
 * shows up on all four and a template-local one shows up on exactly one.
 *
 * Paths are per environment on purpose. Permalink settings differ between the
 * local Bedrock install and the deployed sites -- local serves /product/<slug>/
 * and a dateless post base, dev serves the Polish /produkt/ and /kategoria-produktu/
 * bases with dated posts -- so a single hardcoded path would 301 or 404 somewhere
 * and Lighthouse would happily score the redirect target instead.
 */

export type TargetId = 'home' | 'product' | 'category' | 'post';

export type Target = {
  id: TargetId;
  /** Shown in the test title and in the comparison table. */
  label: string;
  path: string;
};

/**
 * Keyed by the env name that config.ts resolves from PERF_BASE_URL. An env with
 * no entry here is a hard error rather than a fallback: scoring the wrong URL
 * silently is worse than refusing to run.
 *
 * The slugs must exist on the target site. They are content, not code, so a
 * deleted product is the one maintenance this file needs -- the run fails with
 * the HTTP status it got, not with a mystery score drop.
 */
export const targetsByEnv: Record<string, Target[]> = {
  local: [
    { id: 'home', label: 'Home', path: '/' },
    { id: 'product', label: 'Product', path: '/product/bukiet-morska-bryza/' },
    { id: 'category', label: 'Product category', path: '/product-category/wience-i-wiazanki-pogrzebowe/' },
    { id: 'post', label: 'Blog post', path: '/kwiaty-w-astrologia-bukiet-idealny-dla-kazdego-znaku-zodiaku/' },
  ],
  dev: [
    { id: 'home', label: 'Home', path: '/' },
    { id: 'product', label: 'Product', path: '/produkt/bukiet-morska-bryza/' },
    { id: 'category', label: 'Product category', path: '/kategoria-produktu/wieniec-pogrzebowa/' },
    { id: 'post', label: 'Blog post', path: '/2025/09/12/kwiaty-w-astrologia-bukiet-idealny-dla-kazdego-znaku-zodiaku/' },
  ],
};

export function targetsFor(env: string): Target[] {
  const targets = targetsByEnv[env];

  if (!targets) {
    throw new Error(
      `No perf targets defined for env "${env}". Add an entry to targetsByEnv in tests/perf/targets.ts, ` +
        `or pass PERF_ENV to reuse an existing one.`,
    );
  }

  return targets;
}
