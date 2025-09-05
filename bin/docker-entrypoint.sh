#!/usr/bin/env bash
set -e
TARGET_DIR="/app/pkgroot/$(uname -m)-linux"
BACKUP_DIR="/app/pkgroot-private"
# copy private pkgroot to pkgroot if pkgroot is empty
if [ ! -d "$TARGET_DIR" ] || [ -z "$(ls -A "$TARGET_DIR")" ]; then
    echo "* Copying private pkgroot to pkgroot ..."
    rm -rf "$TARGET_DIR"
    cp -r "$BACKUP_DIR" "$TARGET_DIR"
fi
exec "$@"
