#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# --- Helpers ---

die() {
	echo "Error: $*" >&2
	exit 1
}

confirm() {
	read -r -p "$1 [y/N] " response
	[[ "$response" =~ ^[Yy]$ ]] || die "Aborted."
}

# --- Validate arguments ---

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
	echo "Usage: bin/release.sh <version>"
	echo "  e.g. bin/release.sh 1.4.0"
	exit 1
fi

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	die "Version must be in X.Y.Z format (got '$VERSION')."
fi

# --- Preconditions ---

cd "$PROJECT_DIR"

CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$CURRENT_BRANCH" != "main" ]]; then
	die "Must be on the main branch (currently on '$CURRENT_BRANCH')."
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
	die "Working tree is dirty. Commit or stash changes first."
fi

git fetch origin --tags
if git rev-parse "refs/tags/$VERSION" &>/dev/null; then
	die "Tag '$VERSION' already exists."
fi

# --- Check changelog ---

CHANGELOG=$(awk -v ver="$VERSION" '
	/^= / { if (found) exit; if ($0 == "= " ver " =") found=1; next }
	found { print }
' readme.txt)

if [[ -z "$CHANGELOG" ]]; then
	die "No changelog entry found for version $VERSION in readme.txt. Add one before releasing."
fi

echo ""
echo "=== Changelog for $VERSION ==="
echo "$CHANGELOG"
echo "=============================="
echo ""

# --- Show what will change ---

CURRENT_VERSION=$(sed -n 's/.*"version": "\(.*\)".*/\1/p' package.json | head -1)
echo "Version bump: $CURRENT_VERSION → $VERSION"
echo ""
echo "Files to update:"
echo "  - package.json"
echo "  - package-lock.json (version will be updated automatically by npm)"
echo "  - class-opengraph-fallback-embed.php"
echo "  - readme.txt"
echo "  - src/blocks/og-embed/block.json"
echo ""

confirm "Proceed with release $VERSION?"

# --- Bump versions ---

# package.json
sed -i '' "s/\"version\": \".*\"/\"version\": \"$VERSION\"/" package.json

# package-lock.json
npm install --package-lock-only

# class-opengraph-fallback-embed.php
sed -i '' "s/\* Version: .*/\* Version: $VERSION/" class-opengraph-fallback-embed.php

# readme.txt
sed -i '' "s/Stable tag: .*/Stable tag: $VERSION/" readme.txt

# src/blocks/og-embed/block.json
sed -i '' "s/\"version\": \".*\"/\"version\": \"$VERSION\"/" src/blocks/og-embed/block.json

# --- Commit, tag, push ---

git add package.json package-lock.json class-opengraph-fallback-embed.php readme.txt src/blocks/og-embed/block.json
git commit -m "chore: bump version to $VERSION."

git tag "$VERSION"

echo ""
echo "Version $VERSION committed and tagged."
echo ""
confirm "Push to origin?"

git push origin main
git push origin "$VERSION"

echo ""
echo "Release $VERSION pushed. The deploy workflow will create the GitHub release."
