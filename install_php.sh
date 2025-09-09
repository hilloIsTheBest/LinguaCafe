#!/usr/bin/env bash

# Check if PHP is installed
if ! command -v php >/dev/null 2>&1; then
    echo "PHP not found. Installing PHP..."
    # For Ubuntu/Debian
    sudo apt-get update && sudo apt-get install php
    # For macOS with Homebrew
    brew install php
    # Verify installation
    if ! command -v php >/dev/null 2>&1; then
        echo "PHP installation failed. Please check your system."
        exit 1
    fi
fi

echo "PHP is installed and available."
