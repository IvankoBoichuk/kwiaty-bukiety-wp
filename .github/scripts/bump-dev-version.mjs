import { readFile, writeFile } from 'node:fs/promises';

const token = process.env.GITHUB_TOKEN;
const prNumber = process.env.PR_NUMBER;
const repo = process.env.REPO;
const baseRef = process.env.BASE_REF || 'dev';

if (!token || !prNumber || !repo) {
    throw new Error('Missing required environment variables: GITHUB_TOKEN, PR_NUMBER, REPO');
}

const [owner, repoName] = repo.split('/');

if (!owner || !repoName) {
    throw new Error(`Invalid REPO value: ${repo}`);
}

const COMPOSER_PATH = 'composer.json';
const STYLE_PATH = 'web/app/themes/sage/style.css';
const STYLE_VERSION_PATTERN = /^(Version:\s*).+$/m;

async function githubRequest(path) {
    const response = await fetch(`https://api.github.com${path}`, {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/vnd.github+json',
            'User-Agent': 'kwiaty-bukiety-versioning',
            'X-GitHub-Api-Version': '2022-11-28',
        },
    });

    if (!response.ok) {
        const body = await response.text();
        throw new Error(`GitHub API request failed (${response.status}) for ${path}: ${body}`);
    }

    return response.json();
}

async function fetchPullRequestCommits() {
    const commits = [];
    let page = 1;

    while (true) {
        const batch = await githubRequest(`/repos/${owner}/${repoName}/pulls/${prNumber}/commits?per_page=100&page=${page}`);

        commits.push(...batch);

        if (batch.length < 100) {
            break;
        }

        page += 1;
    }

    return commits;
}

/**
 * The version the branch is bumped *from*. It has to come from the base branch
 * rather than the checked-out tree: this runs again on every push to the PR,
 * and reading the tree would compound its own earlier bump -- three pushes
 * would turn one `feat:` into three minor releases.
 */
async function fetchBaseVersion() {
    const file = await githubRequest(
        `/repos/${owner}/${repoName}/contents/${COMPOSER_PATH}?ref=${encodeURIComponent(baseRef)}`,
    );

    const composerJson = JSON.parse(Buffer.from(file.content, file.encoding || 'base64').toString('utf8'));

    if (!composerJson.version) {
        throw new Error(`${COMPOSER_PATH} on ${baseRef} is missing a version field`);
    }

    return composerJson.version;
}

function detectBump(messages) {
    let level = 0;

    for (const message of messages) {
        // Both markers have to sit at the start of a line: `BREAKING CHANGE:`
        // is a footer per the convention, and matching it anywhere would let a
        // commit body that merely describes the rules trigger a major release.
        if (/(^|\n)[a-z]+(\([^)]+\))?!: /i.test(message) || /(^|\n)BREAKING[ -]CHANGE: /i.test(message)) {
            level = Math.max(level, 3);
            continue;
        }

        if (/(^|\n)feat(\([^)]+\))?: /i.test(message)) {
            level = Math.max(level, 2);
            continue;
        }

        if (/(^|\n)fix(\([^)]+\))?: /i.test(message)) {
            level = Math.max(level, 1);
        }
    }

    return level;
}

function incrementVersion(version, bumpLevel) {
    const match = version.match(/^(\d+)\.(\d+)\.(\d+)$/);

    if (!match) {
        throw new Error(`Unsupported version format: ${version}`);
    }

    const major = Number(match[1]);
    const minor = Number(match[2]);
    const patch = Number(match[3]);

    if (bumpLevel === 3) {
        return `${major + 1}.0.0`;
    }

    if (bumpLevel === 2) {
        return `${major}.${minor + 1}.0`;
    }

    if (bumpLevel === 1) {
        return `${major}.${minor}.${patch + 1}`;
    }

    return version;
}

async function main() {
    const commits = await fetchPullRequestCommits();
    const messages = commits.map((commit) => commit.commit.message.trim()).filter(Boolean);
    const bumpLevel = detectBump(messages);

    if (bumpLevel === 0) {
        console.log('No feat/fix/breaking commits found. Skipping version bump.');
        return;
    }

    const baseVersion = await fetchBaseVersion();
    const nextVersion = incrementVersion(baseVersion, bumpLevel);

    if (nextVersion === baseVersion) {
        console.log(`Version remains unchanged at ${baseVersion}.`);
        return;
    }

    const composerRaw = await readFile(COMPOSER_PATH, 'utf8');
    const composerJson = JSON.parse(composerRaw);
    const styleRaw = await readFile(STYLE_PATH, 'utf8');

    if (!STYLE_VERSION_PATTERN.test(styleRaw)) {
        throw new Error(`Failed to find a Version header in ${STYLE_PATH}`);
    }

    composerJson.version = nextVersion;
    await writeFile(COMPOSER_PATH, `${JSON.stringify(composerJson, null, 2)}\n`);
    await writeFile(STYLE_PATH, styleRaw.replace(STYLE_VERSION_PATTERN, `$1${nextVersion}`));

    // The workflow commits only when this actually changed something, so a
    // re-run that lands on the same number is a no-op rather than an error.
    console.log(`Bumped version ${baseVersion} (${baseRef}) -> ${nextVersion} for PR #${prNumber}`);
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
