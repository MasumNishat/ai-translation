# TASK 12: CI/CD Pipeline & Deployment

**Priority:** P1 (Critical)
**Total Estimated Time:** 10-14 hours
**Dependencies:** TASK_03 (Testing)
**Status:** ⏳ Pending

---

## Overview

Set up comprehensive CI/CD pipeline with automated testing, code quality checks, security scanning, and deployment automation.

---

## Subtasks

### P1-T12-S01: GitHub Actions Workflow

**Estimated Time:** 3-4 hours
**Priority:** P1
**Dependencies:** TASK_03

#### Description
Create GitHub Actions workflows for automated testing, linting, and quality checks.

#### Implementation

**1. Main Test Workflow**

```yaml
# .github/workflows/tests.yml

name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest

    strategy:
      fail-fast: false
      matrix:
        php: [8.2, 8.3, 8.4]
        laravel: [11.*, 12.*]
        dependency-version: [prefer-lowest, prefer-stable]

    name: PHP ${{ matrix.php }} - Laravel ${{ matrix.laravel }} - ${{ matrix.dependency-version }}

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, gd
          coverage: xdebug
          tools: composer:v2

      - name: Get Composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache Composer dependencies
        uses: actions/cache@v3
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install dependencies
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}" --no-interaction --no-update
          composer update --${{ matrix.dependency-version }} --prefer-dist --no-interaction

      - name: Create SQLite database
        run: |
          mkdir -p database
          touch database/database.sqlite

      - name: Run migrations
        run: vendor/bin/testbench migrate:fresh
        env:
          DB_CONNECTION: sqlite
          DB_DATABASE: database/database.sqlite

      - name: Execute tests (Pest)
        run: vendor/bin/pest --coverage --coverage-clover coverage.xml

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
          flags: unittests
          name: codecov-umbrella
          fail_ci_if_error: false

  lint:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
          extensions: dom, curl, libxml, mbstring, zip
          tools: composer:v2

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Run PHP CS Fixer
        run: vendor/bin/php-cs-fixer fix --dry-run --diff

      - name: Run PHPStan
        run: vendor/bin/phpstan analyse --memory-limit=2G

      - name: Run Psalm
        run: vendor/bin/psalm --show-info=true
```

**2. Code Quality Workflow**

```yaml
# .github/workflows/code-quality.yml

name: Code Quality

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  phpstan:
    name: PHPStan Analysis
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
          coverage: none

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Run PHPStan
        run: vendor/bin/phpstan analyse --error-format=github

  pint:
    name: Laravel Pint
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
          coverage: none

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Run Laravel Pint
        run: vendor/bin/pint --test

  security:
    name: Security Check
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
          coverage: none

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Security check
        run: composer audit
```

**3. Dependency Update Workflow**

```yaml
# .github/workflows/dependabot.yml

name: Dependabot Auto-merge

on:
  pull_request:
    types: [opened, synchronize]

jobs:
  auto-merge:
    runs-on: ubuntu-latest
    if: github.actor == 'dependabot[bot]'

    steps:
      - name: Dependabot metadata
        id: metadata
        uses: dependabot/fetch-metadata@v1
        with:
          github-token: "${{ secrets.GITHUB_TOKEN }}"

      - name: Auto-merge for patch updates
        if: steps.metadata.outputs.update-type == 'version-update:semver-patch'
        run: gh pr merge --auto --squash "$PR_URL"
        env:
          PR_URL: ${{ github.event.pull_request.html_url }}
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

#### Acceptance Criteria
- [ ] Tests run on push and PR
- [ ] Multiple PHP/Laravel versions tested
- [ ] Code coverage reported
- [ ] Linting checks pass
- [ ] Static analysis runs
- [ ] Fast execution (< 10 minutes)
- [ ] Clear failure messages

---

### P1-T12-S02: Code Quality Tools Configuration

**Estimated Time:** 2-3 hours
**Priority:** P1
**Dependencies:** None

#### Description
Configure code quality tools including PHPStan, Pint, and PHP CS Fixer.

#### Implementation

**1. PHPStan Configuration**

```neon
# phpstan.neon

includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - src
    level: 8
    ignoreErrors:
        - '#Unsafe usage of new static#'
    excludePaths:
        - tests/Pest.php
    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false
```

**2. Laravel Pint Configuration**

```json
{
    "preset": "laravel",
    "rules": {
        "binary_operator_spaces": {
            "default": "single_space"
        },
        "blank_line_after_opening_tag": true,
        "blank_line_before_statement": {
            "statements": ["return", "throw", "try"]
        },
        "braces": {
            "allow_single_line_closure": true
        },
        "concat_space": {
            "spacing": "one"
        },
        "declare_equal_normalize": true,
        "function_typehint_space": true,
        "include": true,
        "lowercase_cast": true,
        "method_argument_space": {
            "on_multiline": "ensure_fully_multiline"
        },
        "native_function_casing": true,
        "no_blank_lines_after_class_opening": true,
        "no_blank_lines_after_phpdoc": true,
        "no_empty_phpdoc": true,
        "no_empty_statement": true,
        "no_extra_blank_lines": {
            "tokens": [
                "extra",
                "throw",
                "use"
            ]
        },
        "no_leading_import_slash": true,
        "no_leading_namespace_whitespace": true,
        "no_mixed_echo_print": {
            "use": "echo"
        },
        "no_multiline_whitespace_around_double_arrow": true,
        "no_short_bool_cast": true,
        "no_singleline_whitespace_before_semicolons": true,
        "no_spaces_around_offset": true,
        "no_trailing_comma_in_singleline_array": true,
        "no_unneeded_control_parentheses": true,
        "no_unused_imports": true,
        "no_whitespace_before_comma_in_array": true,
        "normalize_index_brace": true,
        "object_operator_without_whitespace": true,
        "ordered_imports": {
            "sort_algorithm": "alpha"
        },
        "phpdoc_indent": true,
        "phpdoc_inline_tag_normalizer": true,
        "phpdoc_no_access": true,
        "phpdoc_no_package": true,
        "phpdoc_no_useless_inheritdoc": true,
        "phpdoc_scalar": true,
        "phpdoc_single_line_var_spacing": true,
        "phpdoc_summary": false,
        "phpdoc_to_comment": false,
        "phpdoc_trim": true,
        "phpdoc_types": true,
        "phpdoc_var_without_name": true,
        "self_accessor": true,
        "short_scalar_cast": true,
        "single_blank_line_before_namespace": true,
        "single_quote": true,
        "space_after_semicolon": true,
        "standardize_not_equals": true,
        "ternary_operator_spaces": true,
        "trailing_comma_in_multiline": true,
        "trim_array_spaces": true,
        "unary_operator_spaces": true,
        "whitespace_after_comma_in_array": true
    }
}
```

**3. Psalm Configuration**

```xml
<!-- psalm.xml -->
<?xml version="1.0"?>
<psalm
    errorLevel="4"
    resolveFromConfigFile="true"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns="https://getpsalm.org/schema/config"
    xsi:schemaLocation="https://getpsalm.org/schema/config vendor/vimeo/psalm/config.xsd"
>
    <projectFiles>
        <directory name="src" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>

    <issueHandlers>
        <UndefinedMagicMethod>
            <errorLevel type="suppress">
                <referencedMethod name="Illuminate\Database\Eloquent\Model::*" />
            </errorLevel>
        </UndefinedMagicMethod>
    </issueHandlers>
</psalm>
```

**4. Add Composer Scripts**

```json
{
    "scripts": {
        "test": "vendor/bin/pest",
        "test:coverage": "vendor/bin/pest --coverage",
        "test:unit": "vendor/bin/pest --group=unit",
        "test:feature": "vendor/bin/pest --group=feature",
        "analyse": "vendor/bin/phpstan analyse",
        "format": "vendor/bin/pint",
        "format:test": "vendor/bin/pint --test",
        "psalm": "vendor/bin/psalm",
        "quality": [
            "@format:test",
            "@analyse",
            "@psalm",
            "@test"
        ]
    }
}
```

#### Acceptance Criteria
- [ ] PHPStan configured and passing
- [ ] Pint/CS Fixer configured
- [ ] Psalm configured
- [ ] Composer scripts work
- [ ] All tools integrated in CI
- [ ] Documentation for running locally

---

### P1-T12-S03: Automated Release Management

**Estimated Time:** 2-3 hours
**Priority:** P2
**Dependencies:** P1-T12-S01

#### Description
Automate version bumping, changelog generation, and package releases.

#### Implementation

**1. Release Workflow**

```yaml
# .github/workflows/release.yml

name: Release

on:
  push:
    tags:
      - 'v*.*.*'

jobs:
  release:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
          coverage: none

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction --no-dev

      - name: Get the version
        id: get_version
        run: echo "VERSION=${GITHUB_REF#refs/tags/v}" >> $GITHUB_OUTPUT

      - name: Generate changelog
        id: changelog
        uses: metcalfc/changelog-generator@v4
        with:
          myToken: ${{ secrets.GITHUB_TOKEN }}

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v1
        with:
          tag_name: ${{ github.ref }}
          name: Release ${{ steps.get_version.outputs.VERSION }}
          body: ${{ steps.changelog.outputs.changelog }}
          draft: false
          prerelease: false
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

      - name: Publish to Packagist
        run: |
          # Packagist webhook automatically triggered by GitHub tag
          echo "Package published to Packagist"
```

**2. Changelog Template**

```markdown
# .github/CHANGELOG_TEMPLATE.md

## [Unreleased]

### Added
- New features

### Changed
- Changes in existing functionality

### Deprecated
- Soon-to-be removed features

### Removed
- Removed features

### Fixed
- Bug fixes

### Security
- Security fixes
```

**3. Version Bump Script**

```bash
#!/bin/bash
# scripts/bump-version.sh

set -e

CURRENT_VERSION=$(git describe --tags --abbrev=0 2>/dev/null || echo "v0.0.0")
CURRENT_VERSION=${CURRENT_VERSION#v}

echo "Current version: $CURRENT_VERSION"
echo "Select bump type:"
echo "1) Patch (x.x.X)"
echo "2) Minor (x.X.0)"
echo "3) Major (X.0.0)"
read -p "Enter choice [1-3]: " choice

IFS='.' read -r -a parts <<< "$CURRENT_VERSION"
major=${parts[0]}
minor=${parts[1]}
patch=${parts[2]}

case $choice in
    1) patch=$((patch + 1)) ;;
    2) minor=$((minor + 1)); patch=0 ;;
    3) major=$((major + 1)); minor=0; patch=0 ;;
    *) echo "Invalid choice"; exit 1 ;;
esac

NEW_VERSION="$major.$minor.$patch"

echo "New version will be: v$NEW_VERSION"
read -p "Continue? (y/n): " confirm

if [ "$confirm" != "y" ]; then
    echo "Cancelled"
    exit 0
fi

# Update version in files
sed -i "s/\"version\": \".*\"/\"version\": \"$NEW_VERSION\"/" composer.json

# Commit and tag
git add composer.json
git commit -m "Bump version to v$NEW_VERSION"
git tag -a "v$NEW_VERSION" -m "Release v$NEW_VERSION"

echo "Version bumped to v$NEW_VERSION"
echo "Run 'git push && git push --tags' to publish"
```

#### Acceptance Criteria
- [ ] Automated releases on tag push
- [ ] Changelog generated automatically
- [ ] GitHub releases created
- [ ] Packagist updated automatically
- [ ] Version bump script works
- [ ] Semantic versioning followed

---

### P1-T12-S04: Docker Support

**Estimated Time:** 2-3 hours
**Priority:** P3
**Dependencies:** None

#### Description
Create Docker configuration for development and testing environments.

#### Implementation

**1. Dockerfile for Testing**

```dockerfile
# Dockerfile

FROM php:8.4-cli-alpine

# Install dependencies
RUN apk add --no-cache \
    git \
    zip \
    unzip \
    sqlite-dev \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader

# Copy application files
COPY . .

# Generate autoloader
RUN composer dump-autoload

# Run tests by default
CMD ["vendor/bin/pest"]
```

**2. Docker Compose for Development**

```yaml
# docker-compose.yml

version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - .:/app
    environment:
      - DB_CONNECTION=sqlite
      - DB_DATABASE=/app/database/database.sqlite

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: translator_test
    ports:
      - "3306:3306"

  postgres:
    image: postgres:15-alpine
    environment:
      POSTGRES_PASSWORD: secret
      POSTGRES_DB: translator_test
    ports:
      - "5432:5432"

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
```

**3. Usage Scripts**

```bash
# scripts/docker-test.sh

#!/bin/bash
docker-compose run --rm app vendor/bin/pest
```

#### Acceptance Criteria
- [ ] Docker image builds successfully
- [ ] Tests run in Docker
- [ ] Multiple database support
- [ ] Quick setup for development
- [ ] Documentation for Docker usage

---

### P1-T12-S05: Pre-commit Hooks

**Estimated Time:** 1-2 hours
**Priority:** P2
**Dependencies:** P1-T12-S02

#### Description
Set up Git pre-commit hooks for local quality checks.

#### Implementation

**1. Install Husky (or PHP alternative)**

```bash
composer require --dev brainmaestro/composer-git-hooks
```

**2. Configure Hooks**

```json
{
    "extra": {
        "hooks": {
            "pre-commit": [
                "vendor/bin/pint --test",
                "vendor/bin/phpstan analyse --memory-limit=2G",
                "vendor/bin/pest --bail"
            ],
            "pre-push": [
                "vendor/bin/pest --coverage --min=80"
            ],
            "post-merge": "composer install"
        }
    }
}
```

**3. Setup Script**

```bash
# scripts/setup-hooks.sh

#!/bin/bash

echo "Setting up Git hooks..."

# Install hooks
composer cghooks update

echo "✓ Git hooks installed successfully"
echo ""
echo "Hooks configured:"
echo "  - pre-commit: Format check, static analysis, tests"
echo "  - pre-push: Coverage check (min 80%)"
echo "  - post-merge: Auto composer install"
```

#### Acceptance Criteria
- [ ] Pre-commit hooks prevent bad commits
- [ ] Hooks run quickly (< 30 seconds)
- [ ] Can be bypassed if needed
- [ ] Easy setup for contributors
- [ ] Documented in CONTRIBUTING.md

---

## Definition of Done

- [ ] All 5 subtasks completed
- [ ] All acceptance criteria met
- [ ] CI/CD pipeline runs on every PR
- [ ] Code quality checks automated
- [ ] Releases automated
- [ ] Docker support working
- [ ] Pre-commit hooks configured
- [ ] Documentation complete
- [ ] Team trained on workflows

---

## Notes

- Consider adding deployment workflows for Laravel Forge/Vapor
- Add performance benchmarking to CI
- Consider matrix testing across databases
- Add mutation testing for critical paths
- Set up branch protection rules
- Configure CODEOWNERS file
- Add pull request templates
