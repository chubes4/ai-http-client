#!/bin/bash

# AI HTTP Client Production Build Script
# Creates optimized .zip for production use with all production dependencies bundled

set -e  # Exit on any error

# Configuration
PROJECT_NAME="ai-http-client"
BUILD_DIR="build"
TEMP_DIR="${BUILD_DIR}/temp_${PROJECT_NAME}"

echo "🚀 Starting production build for ${PROJECT_NAME}..."

# Clean previous builds
echo "🧹 Cleaning previous builds..."
rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}"

# Create temporary directory for build
echo "📁 Creating build workspace..."
mkdir -p "${TEMP_DIR}"

# Copy all files except excluded ones
echo "📋 Copying production files..."
rsync -av --exclude-from=<(cat <<EOF
.git/
.gitignore
docs/
.claude/
CLAUDE.md
README.md
build/
build.sh
composer.lock
package-lock.json
.DS_Store
.phpunit.result.cache
EOF
) ./ "${TEMP_DIR}/"

# Install production dependencies only
echo "📦 Installing production dependencies..."
cd "${TEMP_DIR}"
composer install --no-dev --optimize-autoloader
cd ../..

# Create production ZIP
echo "📦 Creating production ZIP..."
cd "${BUILD_DIR}"
zip -r "${PROJECT_NAME}.zip" "temp_${PROJECT_NAME}"/*
cd ..

# Clean up temporary directory
echo "🧹 Cleaning up..."
rm -rf "${TEMP_DIR}"

echo "✅ Production build complete!"
echo "📁 Build output: ${BUILD_DIR}/${PROJECT_NAME}.zip"
echo ""
echo "📊 Build contents:"
unzip -l "${BUILD_DIR}/${PROJECT_NAME}.zip" | tail -10