#!/usr/bin/env bash
# =============================================================================
# build-release.sh — SellingPools Plugins Release Builder
#
# Usage:
#   ./build-release.sh <version> <github-token>
#
# Example:
#   ./build-release.sh 1.2.0 ghp_YourTokenHere
#
# What it does:
#   1. Creates a zip file for each of the 5 plugins.
#   2. Creates a GitHub release tagged v<version>.
#   3. Uploads all 5 zips as release assets.
#   4. Updates each update-manifests/*.json with the new version and download URL.
#
# After running this script:
#   1. Verify the JSON files look correct.
#   2. git add update-manifests/ && git commit -m "Bump manifests to v<version>"
#   3. git push origin main
#
# Requirements: curl, zip (both standard on macOS)
# =============================================================================

set -euo pipefail

# --------------------------------------------------------------------------- #
# Arguments
# --------------------------------------------------------------------------- #

VERSION="${1:-}"
TOKEN="${2:-}"
REPO="RogerLightening/sellingpools-plugins"

if [[ -z "$VERSION" || -z "$TOKEN" ]]; then
    echo "Usage: $0 <version> <github-token>"
    echo "Example: $0 1.2.0 ghp_YourTokenHere"
    exit 1
fi

TAG="v${VERSION}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="${SCRIPT_DIR}/.build-tmp"
PLUGINS=(
    "bk-pools-core"
    "bk-agent-panel"
    "bk-agent-matcher"
    "bk-estimate-generator"
    "bk-performance-tracker"
)

echo "==> Building release ${TAG} for sellingpools-plugins"

# --------------------------------------------------------------------------- #
# Step 1: Create zip files
# --------------------------------------------------------------------------- #

rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}"

for SLUG in "${PLUGINS[@]}"; do
    echo "--> Zipping ${SLUG}..."
    ZIP_FILE="${BUILD_DIR}/${SLUG}.zip"

    # Create zip: each plugin folder at the root of the archive.
    # Exclude common dev/build artifacts.
    (cd "${SCRIPT_DIR}" && zip -r "${ZIP_FILE}" "${SLUG}/" \
        --exclude "*.git*" \
        --exclude "*/.DS_Store" \
        --exclude "*/node_modules/*" \
        --exclude "*/.env" \
        > /dev/null)

    echo "    Created: ${ZIP_FILE} ($(du -sh "${ZIP_FILE}" | cut -f1))"
done

# --------------------------------------------------------------------------- #
# Step 2: Create GitHub release
# --------------------------------------------------------------------------- #

echo "--> Creating GitHub release ${TAG}..."

RELEASE_RESPONSE=$(curl -s -X POST \
    -H "Authorization: token ${TOKEN}" \
    -H "Content-Type: application/json" \
    -d "{
        \"tag_name\": \"${TAG}\",
        \"name\": \"SellingPools Plugins ${TAG}\",
        \"body\": \"Plugin update ${TAG}. Install via WordPress Dashboard → Updates.\",
        \"draft\": false,
        \"prerelease\": false
    }" \
    "https://api.github.com/repos/${REPO}/releases")

RELEASE_ID=$(echo "${RELEASE_RESPONSE}" | python3 -c "import sys,json; print(json.load(sys.stdin)['id'])" 2>/dev/null || echo "")

if [[ -z "${RELEASE_ID}" ]]; then
    echo "ERROR: Failed to create release. Response:"
    echo "${RELEASE_RESPONSE}"
    exit 1
fi

echo "    Release created. ID: ${RELEASE_ID}"

# --------------------------------------------------------------------------- #
# Step 3: Upload release assets
# --------------------------------------------------------------------------- #

for SLUG in "${PLUGINS[@]}"; do
    ZIP_FILE="${BUILD_DIR}/${SLUG}.zip"
    echo "--> Uploading ${SLUG}.zip..."

    UPLOAD_URL="https://uploads.github.com/repos/${REPO}/releases/${RELEASE_ID}/assets?name=${SLUG}.zip"

    UPLOAD_RESPONSE=$(curl -s -X POST \
        -H "Authorization: token ${TOKEN}" \
        -H "Content-Type: application/zip" \
        --data-binary "@${ZIP_FILE}" \
        "${UPLOAD_URL}")

    ASSET_URL=$(echo "${UPLOAD_RESPONSE}" | python3 -c "import sys,json; print(json.load(sys.stdin).get('browser_download_url',''))" 2>/dev/null || echo "")

    if [[ -z "${ASSET_URL}" ]]; then
        echo "    WARNING: Upload may have failed. Response:"
        echo "${UPLOAD_RESPONSE}"
    else
        echo "    Uploaded: ${ASSET_URL}"
    fi
done

# --------------------------------------------------------------------------- #
# Step 4: Update update-manifests JSON files
# --------------------------------------------------------------------------- #

TODAY=$(date -u '+%Y-%m-%d %H:%M:%S')

for SLUG in "${PLUGINS[@]}"; do
    JSON_FILE="${SCRIPT_DIR}/update-manifests/${SLUG}.json"

    if [[ ! -f "${JSON_FILE}" ]]; then
        echo "WARNING: JSON manifest not found: ${JSON_FILE}"
        continue
    fi

    DOWNLOAD_URL="https://github.com/${REPO}/releases/download/${TAG}/${SLUG}.zip"

    # Use python3 to update JSON in-place.
    python3 - "${JSON_FILE}" "${VERSION}" "${DOWNLOAD_URL}" "${TODAY}" <<'PYEOF'
import sys, json

path, version, download_url, last_updated = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]

with open(path, 'r') as f:
    data = json.load(f)

data['version'] = version
data['download_url'] = download_url
data['last_updated'] = last_updated

with open(path, 'w') as f:
    json.dump(data, f, indent=4)
    f.write('\n')

print(f"    Updated: {path}")
PYEOF

done

# --------------------------------------------------------------------------- #
# Done
# --------------------------------------------------------------------------- #

rm -rf "${BUILD_DIR}"

echo ""
echo "==> Release ${TAG} complete!"
echo ""
echo "Next steps:"
echo "  1. git add update-manifests/"
echo "  2. git commit -m 'Bump plugin manifests to ${TAG}'"
echo "  3. git push origin main"
echo "  4. On the live site: Dashboard → Updates — the new version should appear."
echo ""
