# OpenGraph Fallback Embed

A WordPress plugin that provides an embed block and auto-embed fallback based on a site's OpenGraph tags when no other embed handler matches the URL.

## Features

- **Custom OpenGraph Embed block** — dedicated block for embedding any page as a rich link card.
- **Auto-embed fallback** — when a pasted URL has no oEmbed provider, the classic editor auto-embed path renders an OG card instead of a bare link.
- **Embed block fallback** — when the core Embed block can't resolve a URL (no oEmbed provider found), the plugin automatically provides an OG card as the embed result, both in the editor and on the frontend.

## Requirements

- WordPress 6.3+
- PHP 7.4+

## Development

### Prerequisites

- Node.js 20+
- Composer
- Docker (for tests via wp-env)

### Setup

```bash
npm install
composer install
```

### Build

```bash
npm run build       # Production build
npm run start       # Development build with watch
```

### Lint

```bash
composer phpcs      # PHP coding standards
npm run lint:js     # JavaScript lint
npm run lint:css    # CSS lint
```

### Test

```bash
npx wp-env start    # Start WordPress test environment
npm test            # Run PHPUnit tests
```

### Release

```bash
bin/release.sh <version>
```

The release script will:

1. Validate the version format (X.Y.Z) and preconditions (clean tree, on `main`, tag doesn't exist).
2. Check that `readme.txt` has a changelog entry for the version.
3. Bump the version in `package.json`, `class-opengraph-fallback-embed.php`, `readme.txt`, and `src/blocks/og-embed/block.json`.
4. Commit, tag, and push. The deploy workflow then builds the zip and creates the GitHub release with notes extracted from the changelog.
