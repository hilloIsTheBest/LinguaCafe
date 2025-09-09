#!/usr/bin/env bash

# Check if PHP is installed and available in the PATH.
if ! command -v php >/dev/null 2>&1; then
    echo "PHP not found. Please install PHP (e.g., on Ubuntu: sudo apt-get install php, or on macOS: brew install php) and ensure it is in your PATH."
    exit 1
fi

echo "PHP is available."
