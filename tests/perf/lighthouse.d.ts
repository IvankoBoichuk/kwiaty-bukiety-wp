// Lighthouse ships its types under types/ but declares no "types" entry in
// package.json, so a bare `import lighthouse from 'lighthouse'` has no
// declaration to resolve. Nothing in this directory is typechecked by CI, but
// editors complain without this, and the shape below is all the gate uses.
declare module 'lighthouse' {
  type Lhr = {
    lighthouseVersion: string;
    requestedUrl: string;
    finalDisplayedUrl: string;
    runtimeError?: { code: string; message: string };
    runWarnings: string[];
    categories: Record<string, { id: string; title: string; score: number | null }>;
    audits: Record<
      string,
      {
        id: string;
        score: number | null;
        numericValue?: number;
        details?: { items?: unknown[] };
      }
    >;
  };

  type Flags = {
    port?: number;
    output?: 'json' | 'html' | 'csv';
    logLevel?: 'silent' | 'error' | 'warn' | 'info' | 'verbose';
    onlyCategories?: string[];
    formFactor?: 'mobile' | 'desktop';
    maxWaitForLoad?: number;
  };

  export default function lighthouse(
    url: string,
    flags?: Flags,
    config?: unknown,
  ): Promise<{ lhr: Lhr; report: string } | undefined>;
}
