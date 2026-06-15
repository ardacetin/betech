#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

echo "Deploying Betech from branch: main"

git pull origin main

composer install --no-dev --optimize-autoloader

if [ -d "$ROOT_DIR/var/cache" ]; then
    echo "Clearing application cache..."
    rm -rf "$ROOT_DIR/var/cache/"*
fi

echo "Deployment completed successfully."
