#!/bin/bash

# Build Release Script for Ocean Shiatsu Booking
# Strips unused Google API services to reduce package size

set -e

echo "=== Ocean Shiatsu Booking: Build Release ==="

# 1. Verify we're in the right directory
if [ ! -f "ocean-shiatsu-booking.php" ]; then
    echo "Error: Run this script from the plugin root directory."
    exit 1
fi

# 2. Install dependencies (no dev) - use composer.phar if available
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
    # Count before
    BEFORE=$(du -sh "$SERVICES_DIR" | cut -f1)
    
    # Keep only Calendar.php and Calendar/ directory
    cd "$SERVICES_DIR"
    
    # Delete all PHP files except Calendar.php
    find . -maxdepth 1 -type f -name "*.php" ! -name "Calendar.php" -delete
    
    # Delete all directories except Calendar
    find . -maxdepth 1 -type d ! -name "." ! -name "Calendar" -exec rm -rf {} +
    
    cd - > /dev/null
    
    AFTER=$(du -sh "$SERVICES_DIR" | cut -f1)
    echo "   Google Services: $BEFORE -> $AFTER"
else
    echo "Warning: Google Services directory not found."
fi

# 4. Create Zip excluding dev files
echo "[3/4] Creating release zip..."
ZIP_NAME="ocean-shiatsu-booking.zip"
rm -f "$ZIP_NAME"

zip -rq "$ZIP_NAME" . \
    -x "*.git*" \
    -x "node_modules/*" \
    -x ".DS_Store" \
    -x "composer.json" \
    -x "composer.lock" \
    -x "composer.phar" \
    -x "README.md" \
    -x "LICENSE" \
    -x ".gitignore" \
    -x "build_release.sh" \
    -x "git_check/*" \
    -x "*.zip"

# 5. Report
echo "[4/4] Done!"
ZIP_SIZE=$(du -h "$ZIP_NAME" | cut -f1)
echo ""
echo "=== Release Package Created ==="
echo "   File: $ZIP_NAME"
echo "   Size: $ZIP_SIZE"
echo ""
echo "Next: Run 'gh release create vX.X.X $ZIP_NAME --title \"vX.X.X\" --notes \"...\"'"
