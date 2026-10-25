#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

# Define a function to clean up Docker containers
cleanup() {
    echo "Stopping Docker containers..."
    docker compose -f compose.testing.yml down --volumes --remove-orphans
}

# Set a trap to call the cleanup function on script exit
trap cleanup EXIT

# Clean up previous runs
echo "Cleaning up previous test runs..."
rm -rf .phpunit.cache # <-- DIESE ZEILE HINZUFÜGEN
docker compose -f compose.testing.yml down --volumes --remove-orphans

echo "Setting up temporary directory permissions..."
mkdir -p tests/temp
chmod 777 tests/temp

# Start Docker containers
echo "Starting Docker containers for testing..."
docker compose -f compose.testing.yml up -d --wait

# Run the tests
echo ""
echo "Running tests..."
vendor/bin/pest
