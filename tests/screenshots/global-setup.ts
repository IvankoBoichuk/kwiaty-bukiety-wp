/**
 * Fails the run once, up front, when the site is not there -- otherwise every
 * page at every viewport goes red with the same connection error and the one
 * line that matters is buried under 64 copies of it.
 */

import { baseUrl } from './config';

export default async function globalSetup(): Promise<void> {
  try {
    await fetch(baseUrl, { redirect: 'manual' });
  } catch (error) {
    const reason = error instanceof Error ? (error.cause as Error | undefined)?.message ?? error.message : String(error);

    throw new Error(
      `${baseUrl} is not answering (${reason}). Start the devcontainer, or point the run at another site: ` +
        'SCREENSHOTS_BASE_URL=https://dev.kwiaty-bukiety.com.pl bun run screenshots',
    );
  }
}
