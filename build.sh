#!/usr/bin/env bash
# Build distributable ZIP packages for WP Canary
# Usage: ./build.sh [version]
# Outputs: dist/wp-canary-{version}.zip
#          dist/wp-canary-lite-{version}.zip  (free tier only)

set -euo pipefail

VERSION="${1:-$(grep "Version:" wp-canary/wp-canary.php | awk '{print $NF}')}"
DIST="dist"
BUILD_DIR="/tmp/wpc-build-$$"
PLUGIN_SLUG="wp-canary"

echo "Building WP Canary v${VERSION}..."

mkdir -p "$DIST"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

# ── Full / Pro package ────────────────────────────────────────────────────────
cp -r "$PLUGIN_SLUG" "$BUILD_DIR/$PLUGIN_SLUG"

# Remove dev-only files
find "$BUILD_DIR/$PLUGIN_SLUG" -name "*.map" -delete
find "$BUILD_DIR/$PLUGIN_SLUG" -name ".DS_Store" -delete
find "$BUILD_DIR/$PLUGIN_SLUG" -name "Thumbs.db" -delete

rm -f "${DIST}/${PLUGIN_SLUG}-${VERSION}.zip"
cd "$BUILD_DIR"
zip -rq "${OLDPWD}/${DIST}/${PLUGIN_SLUG}-${VERSION}.zip" "$PLUGIN_SLUG"
cd - > /dev/null

echo "  ✔ ${DIST}/${PLUGIN_SLUG}-${VERSION}.zip"

# ── Lite / Free package (WordPress.org) ──────────────────────────────────────
LITE_DIR="$BUILD_DIR/${PLUGIN_SLUG}-lite"
cp -r "$PLUGIN_SLUG" "$LITE_DIR"

# Strip pro-only modules
rm -rf "$LITE_DIR/includes/pro"

# Patch main plugin file: disable pro loader lines
sed -i "s/wpc_is_pro()/false/g" "$LITE_DIR/wp-canary.php"

# Strip pro admin views (leave stubs showing upgrade prompts)
for view in monitor-404.php plugin-watchdog.php reports.php; do
  cat > "$LITE_DIR/admin/views/$view" <<'PHP'
<?php
// This feature is available in WP Canary Pro.
// Visit https://wpsiteguardian.com to upgrade.
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="notice notice-info">
  <p><?php esc_html_e( 'This feature requires WP Canary Pro.', 'wp-canary' ); ?>
  <a href="https://wpsiteguardian.com" target="_blank"><?php esc_html_e( 'Upgrade now →', 'wp-canary' ); ?></a></p>
</div>
PHP
done

# Update readme stable tag
sed -i "s/Stable tag: .*/Stable tag: ${VERSION}/" "$LITE_DIR/readme.txt"

rm -f "${DIST}/${PLUGIN_SLUG}-lite-${VERSION}.zip"
cd "$BUILD_DIR"
zip -rq "${OLDPWD}/${DIST}/${PLUGIN_SLUG}-lite-${VERSION}.zip" "${PLUGIN_SLUG}-lite"
cd - > /dev/null

echo "  ✔ ${DIST}/${PLUGIN_SLUG}-lite-${VERSION}.zip  (WordPress.org / free tier)"

# Cleanup
rm -rf "$BUILD_DIR"

echo ""
echo "Done. Files in ./${DIST}/:"
ls -lh "${DIST}/${PLUGIN_SLUG}-${VERSION}.zip" "${DIST}/${PLUGIN_SLUG}-lite-${VERSION}.zip"
