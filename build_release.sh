#!/bin/bash

# Build Release Script for Ocean Shiatsu Booking
# Creates a clean zip with "ocean-shiatsu-booking/" top-level directory

set -e

echo "=== Ocean Shiatsu Booking: Build Release ==="

# 1. Verify we're in the right directory
if [ ! -f "ocean-shiatsu-booking.php" ]; then
    echo "Error: Run this script from the plugin root directory."
    exit 1
fi

# 2. Install dependencies (no dev)
echo "[1/4] Installing production dependencies..."
if [ -f "composer.phar" ]; then
    php composer.phar install --no-dev --optimize-autoloader --quiet
elif command -v composer &> /dev/null; then
    composer install --no-dev --optimize-autoloader --quiet
else
    echo "Warning: Composer not found, skipping dependency install."
fi

# 3. Cleanup Google Services (Keep only Calendar)
echo "[2/4] Removing unused Google API Services..."
SERVICES_DIR="vendor/google/apiclient-services/src"
if [ -d "$SERVICES_DIR" ]; then
    # Delete all PHP files EXCEPT Calendar.php
    find "$SERVICES_DIR" -maxdepth 1 -type f -name "*.php" ! -name "Calendar.php" -delete 2>/dev/null || true
    # Delete all subdirectories EXCEPT Calendar (which contains resource classes)
    find "$SERVICES_DIR" -maxdepth 1 -mindepth 1 -type d ! -name "Calendar" -exec rm -rf {} + 2>/dev/null || true
fi

# 4. Prepare Staging Directory
echo "[3/4] creating Staging Directory..."
BUILD_DIR="build_artifact"
PLUGIN_SLUG="ocean-shiatsu-booking"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"

# Copy files
echo "   Copying files..."
rsync -av --exclude="$BUILD_DIR" \
    --exclude=".*" \
    --exclude="node_modules" \
    --exclude="reviews" \
    --exclude="plans" \
    --exclude="requirements" \
    --exclude="backup_conversations" \
    --exclude="tests" \
    --exclude="*.zip" \
    --exclude="build_release.sh" \
    --exclude="composer.json" \
    --exclude="composer.lock" \
    --exclude="composer.phar" \
    --exclude="README.md" \
    . "$BUILD_DIR/$PLUGIN_SLUG" > /dev/null

# 5. Create Zip
echo "[4/4] Zipping..."
ZIP_NAME="ocean-shiatsu-booking.zip"
rm -f "$ZIP_NAME"
cd "$BUILD_DIR"
zip -rq "../$ZIP_NAME" "$PLUGIN_SLUG"
cd ..

# Cleanup
rm -rf "$BUILD_DIR"

ZIP_SIZE=$(du -h "$ZIP_NAME" | cut -f1)
echo ""
echo "=== Release Package Created ==="
echo "   File: $ZIP_NAME"
echo "   Size: $ZIP_SIZE"
echo ""
echo "Next: Renaming and Uploading..."
