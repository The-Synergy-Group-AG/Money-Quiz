#!/usr/bin/env bash

# Money Quiz Plugin Test Runner
# This script runs the PHPUnit tests for the plugin

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the directory of this script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PLUGIN_DIR="$( dirname "$DIR" )"

echo -e "${GREEN}Money Quiz Plugin Test Runner${NC}"
echo "=============================="
echo ""

# Check if composer dependencies are installed
if [ ! -d "$PLUGIN_DIR/vendor" ]; then
    echo -e "${YELLOW}Installing composer dependencies...${NC}"
    cd "$PLUGIN_DIR"
    composer install --no-interaction --prefer-dist
fi

# Check if WordPress test suite is installed
if [ -z "$WP_TESTS_DIR" ]; then
    export WP_TESTS_DIR="/tmp/wordpress-tests-lib"
fi

if [ ! -d "$WP_TESTS_DIR" ]; then
    echo -e "${RED}WordPress test suite not found!${NC}"
    echo "Please run: ./bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host]"
    exit 1
fi

# Run specific test suite if provided
if [ "$1" = "unit" ]; then
    echo -e "${YELLOW}Running unit tests...${NC}"
    ./vendor/bin/phpunit --testsuite=unit
elif [ "$1" = "integration" ]; then
    echo -e "${YELLOW}Running integration tests...${NC}"
    ./vendor/bin/phpunit --testsuite=integration
elif [ "$1" = "security" ]; then
    echo -e "${YELLOW}Running security tests...${NC}"
    ./vendor/bin/phpunit --testsuite=security
elif [ "$1" = "coverage" ]; then
    echo -e "${YELLOW}Running tests with coverage...${NC}"
    ./vendor/bin/phpunit --coverage-html coverage --coverage-text
elif [ "$1" = "watch" ]; then
    echo -e "${YELLOW}Running tests in watch mode...${NC}"
    while true; do
        clear
        ./vendor/bin/phpunit
        echo -e "\n${GREEN}Watching for changes...${NC}"
        inotifywait -q -e modify,create,delete -r "$PLUGIN_DIR/src" "$PLUGIN_DIR/tests"
    done
else
    echo -e "${YELLOW}Running all tests...${NC}"
    ./vendor/bin/phpunit
fi

# Check exit code
if [ $? -eq 0 ]; then
    echo -e "\n${GREEN}✓ All tests passed!${NC}"
else
    echo -e "\n${RED}✗ Some tests failed!${NC}"
    exit 1
fi