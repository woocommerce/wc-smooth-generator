# WooCommerce Smooth Generator - AI Agent Configuration

This file provides essential context for AI agents working on this project. It contains information that may be difficult for agents to discover automatically.

## Project Overview

**Name:** WooCommerce Smooth Generator
**Purpose:** A smooth product, order, customer, coupon, and term generator for WooCommerce
**Primary Interface:** WP-CLI (with limited WP Admin UI)
**Repository:** https://github.com/woocommerce/wc-smooth-generator

### Tech Stack

- **PHP:** 7.4+ (required)
- **WordPress:** tested up to 6.5
- **WooCommerce:** 5.0.0+ (tested up to 9.1.0)
- **HPOS:** Compatible with WooCommerce High-Performance Order Storage
- **Node.js:** 14+ (recommended: v16 via nvm)

### Key Dependencies

**Runtime:**
- `fakerphp/faker` (^1.24.0) - Generate fake data
- `jdenticon/jdenticon` (^2.0.0) - Generate product images
- `mbezhanov/faker-provider-collection` (^2.0.1) - Additional faker providers
- `psr/container` (1.0.0) - PSR-11 container interface

**Development:**
- `woocommerce/woocommerce-sniffs` - PHP_CodeSniffer ruleset (extends WordPress standards)
- `phpunit/phpunit` (^9.5 || ^10.0 || ^11.0) - Unit testing
- `husky` (^8.0.0) - Git hooks

## Project Structure

```
wc-smooth-generator/
├── includes/              # Main source code (PSR-4: WC\SmoothGenerator\)
│   ├── Generator/         # Generator classes (Order, Product, Coupon, Customer, Term)
│   ├── Admin/            # WP Admin UI (Settings, AsyncJob, BatchProcessor)
│   ├── Util/             # Utilities (RandomRuntimeCache)
│   ├── CLI.php           # WP-CLI command registration
│   ├── Plugin.php        # Main plugin class
│   └── Router.php        # Request routing
├── tests/
│   └── Unit/             # PHPUnit tests (PSR-4: WC\SmoothGenerator\Tests\)
├── bin/                  # Build scripts
├── vendor/               # Composer dependencies
├── .github/workflows/    # CI/CD (PHP unit tests)
├── .husky/               # Git hooks (pre-commit)
└── wc-smooth-generator.php  # Main plugin file (entry point)
```

**PSR-4 Namespaces:**
- Main code: `WC\SmoothGenerator\` → `includes/`
- Tests: `WC\SmoothGenerator\Tests\` → `tests/Unit/`

## Commands

### Development Setup

```bash
# Install dependencies and setup Git hooks
npm run setup

# Switch to Node v16 (if using nvm)
nvm use
```

**IMPORTANT:** After running `npm run build` for releases, you MUST run `npm run setup` again to restore dev dependencies.

### Linting

```bash
# Run full phpcs on entire codebase
composer run phpcs
vendor/bin/phpcs         # Direct execution

# Auto-fix coding standards
composer run phpcbf
vendor/bin/phpcbf        # Direct execution

# Lint only unstaged changes
composer run lint

# Lint only staged changes (runs in pre-commit hook)
composer run lint-staged

# Lint branch changes against base branch
composer run lint-branch
```

**Pre-commit Hook:**
- Location: `.husky/pre-commit`
- Runs: `composer run lint-staged` automatically
- Installed via: `npm run setup` (runs `husky install`)

### Testing

```bash
# Run PHPUnit tests
composer run test-unit
vendor/bin/phpunit       # Direct execution
```

**CI/CD:**
- Workflow: `.github/workflows/php-unit-tests.yml`
- Matrix: PHP 7.4, 8.0, 8.2, 8.4 × WordPress latest
- Triggers: Push to trunk, PRs, manual dispatch

### Building

```bash
# Create release zip file
npm run build
```

**What `npm run build` does:**
1. Installs production-only composer dependencies
2. Creates zip archive excluding dev files (see `composer.json` archive section)
3. Removes dev dependencies after completion

**Archive excludes:** `.github/`, `.husky/`, `bin/`, `node_modules/`, `composer.*`, `package*.json`, `.phpcs*`, all dotfiles

## WP-CLI Commands

All generators are accessible via `wp wc generate <subcommand>`.

### Products

```bash
# Generate products (default: random mix of simple/variable)
wp wc generate products 100

# Generate specific type
wp wc generate products 50 --type=simple
wp wc generate products 50 --type=variable
```

### Orders

```bash
# Generate orders for current date
wp wc generate orders 100

# Generate with date range
wp wc generate orders 100 --date-start=2018-04-01
wp wc generate orders 100 --date-start=2018-04-01 --date-end=2018-04-24

# Generate with specific status
wp wc generate orders 100 --status=completed

# Apply coupons to exact percentage of orders (0.0-1.0)
# Batch mode: deterministic exact distribution
# Single order: probabilistic
wp wc generate orders 100 --coupon-ratio=0.5

# Refund exact percentage of completed orders (0.0-1.0)
# Distribution: 50% full, 25% single partial, 25% multi-partial
wp wc generate orders 100 --status=completed --refund-ratio=0.3

# Skip order attribution metadata generation
wp wc generate orders 100 --skip-order-attribution
```

### Coupons

```bash
# Generate coupons
wp wc generate coupons 10

# Generate with discount constraints
wp wc generate coupons 10 --min=5 --max=50

# Generate with specific discount type
wp wc generate coupons 10 --discount_type=percent --min=5 --max=25
```

### Customers

```bash
# Generate customers
wp wc generate customers 50
```

### Terms

```bash
# Generate product categories (flat structure)
wp wc generate terms product_cat 20

# Generate hierarchical categories with max depth
wp wc generate terms product_cat 20 --max-depth=5

# Generate child terms of existing category
wp wc generate terms product_cat 10 --parent=123

# Generate product tags
wp wc generate terms product_tag 30
```

## Coding Conventions

### PHP_CodeSniffer

- **Config:** `phpcs.xml.dist`
- **Ruleset:** WooCommerce-Core (extends WordPress-Coding-Standards)
- **Text Domain:** `wc-smooth-generator`
- **Minimum WP Version:** 5.0
- **Test PHP Version:** 7.1 (for compatibility checks)
- **Parallel:** 8 files simultaneously

**Exclusions from WooCommerce-Core:**
- `WordPress.Files.FileName` - File naming conventions
- `WordPress.NamingConventions.ValidVariableName` - Variable naming
- `WordPress.DateTime.RestrictedFunctions.date_date` - Date function usage
- `PEAR.Functions.FunctionCallSignature.*` - Function call formatting

### Commit Conventions

- Use concise, one-line commit messages
- Make incremental, bite-sized commits
- Follow repository's existing commit style (check `git log`)
- NEVER mention "Claude" or "AI assistant" in commit messages

### Pull Request Conventions

- Check for PR templates in `/.github/` (upper/lower case)
- Keep descriptions concise, not overly verbose
- Run `composer run lint-branch` before opening PR
- Ensure tests pass locally: `composer run test-unit`

## Key Features & Architecture

### 1. Exact Ratio Distribution (CRITICAL)

**Algorithm:** Selection without replacement (O(1) memory)

**Behavior:**
- **Batch mode (>1 item):** Deterministic exact distribution
  - Example: 100 orders at 0.5 ratio = exactly 50 with feature
  - Odd numbers: Uses PHP `round()` (11 orders at 0.5 = 6 with feature)
- **Single item:** Probabilistic fallback

**Applies to:**
- `--coupon-ratio=0.5` in order generation
- `--refund-ratio=0.3` in order generation

**Implementation:** `includes/Generator/Order.php`

### 2. Order Attribution

**Purpose:** Track order source/origin metadata

**Critical Details:**
- **Date Cutoff:** 2024-01-09 (feature not available in WooCommerce before this date)
- **Behavior:** Orders with creation date before cutoff will NOT have attribution metadata
- **Skip Flag:** `--skip-order-attribution` to disable generation

**Why it matters:** AI agents should respect this date cutoff and not generate attribution data for historical orders before this date.

### 3. Refund Distribution

**Types (when `--refund-ratio` is used):**
- 50% Full refunds (changes order status to 'refunded')
- 25% Single partial refunds
- 25% Multi-partial refunds (two partial refunds)

**Only applies to:** Orders with status 'completed'

### 4. Term Generation

**Taxonomies supported:**
- `product_cat` (hierarchical)
- `product_tag` (flat)
- Custom taxonomies (if registered)

**Hierarchical options:**
- `--max-depth=5` - Create nested categories up to 5 levels
- `--parent=123` - Create all terms as children of term ID 123

**CRITICAL:** See "Term Generation" section in Common Pitfalls below.

### 5. Generator Architecture

All generators extend `includes/Generator.php` base class:
- Use Faker library for data generation
- Support batch processing
- Implement error handling and validation
- Provide progress tracking

## Common Pitfalls (CRITICAL - READ CAREFULLY)

### WordPress/WooCommerce Core Files

- **NEVER edit WordPress core files** (wp-admin/, wp-includes/)
- **NEVER edit WooCommerce core files** (wp-content/plugins/woocommerce/)
- **Plugin code ONLY lives in:** `wp-content/plugins/wc-smooth-generator/`

### Version Control - Files to NEVER Commit

Unless explicitly requested by the user:
- **NEVER commit `CLAUDE.md`** (or `AGENTS.md`)
- **NEVER commit `.claude/settings.local.json`**
- **NEVER commit `.claude/plans/` directory**
- **NEVER commit `changelog.txt` updates** (only during releases)

### Code Quality - MUST DO Before Pushing

- **MUST run `vendor/bin/phpcs` before pushing** to catch all issues
- Pre-commit hook only catches staged changes, not all changes
- Use `composer run lint-branch` before opening PR
- **NEVER use bash cat/grep/find instead of Read/Grep/Glob tools**

### GitHub Operations

- **GitHub CLI commands frequently fail** due to SSL/network issues
- If command hangs for 15+ seconds, cancel and retry
- This is a known issue, not a code problem

### Term Generation (CRITICAL)

**MUST clear term caches after batch generation:**
```php
// After generating terms in batch; $term_ids is the array of generated term IDs
clean_term_cache( $term_ids, $taxonomy );
```

**MUST validate taxonomy exists before generation:**
```php
if ( ! taxonomy_exists( $taxonomy ) ) {
    // Handle error
}
```

**Why:** Without cache clearing, newly generated terms won't appear in queries. Without validation, invalid taxonomies cause silent failures.

**Recent fixes:** See commits 1ee6564 (cache clearing) and eb5c8ac (taxonomy validation).

### Order Attribution

- **Respect 2024-01-09 cutoff date** - do not generate attribution for orders before this
- Orders before cutoff date should NOT have attribution metadata
- This is a WooCommerce feature limitation, not a bug

### Ratio Distribution

**Understand the difference:**
- **Batch mode (multiple items):** Deterministic - exact ratios guaranteed
- **Single item mode:** Probabilistic - ratios are chances, not guarantees

**Example:** With `--coupon-ratio=0.5`:
- 100 orders = exactly 50 with coupons
- 1 order = 50% chance of having coupon

### Testing

- **Run tests locally before pushing:** `composer run test-unit`
- WooCommerce must be installed for tests to run
- Tests require WordPress test suite setup
- See `.github/workflows/php-unit-tests.yml` for CI setup

### Build & Release

- **After `npm run build`, MUST run `npm run setup` again** to restore dev dependencies
- Release builds remove dev dependencies (composer install --no-dev)
- Without running setup again, pre-commit hooks won't work

## AI Agent Skills

This project uses a **dual-location approach** for AI agent skills (procedures):

**Skills vs AGENTS.md:**
- **AGENTS.md:** Context (WHAT/WHERE) - project structure, commands, conventions, pitfalls
- **Skills:** Procedures (HOW) - step-by-step instructions for complex tasks

**Directory structure:**
- **`.agents/skills/`** - Source of truth (committed to repository, industry standard)
  - Contains actual skill content
  - Supports Codex, Gemini CLI, OpenCode, Amp, Windsurf, Warp
- **`.claude/skills/`** - Thin wrappers for Claude Code (committed to repository)
  - Each file contains `@../../../.agents/skills/{skill-name}/SKILL.md`
  - Enables Claude Code auto-discovery
  - Avoids symlink issues

**Available skills:**
1. **wc-smooth-generator-release** - Complete release process
   - Source: `.agents/skills/wc-smooth-generator-release/SKILL.md`
   - Wrapper: `.claude/skills/wc-smooth-generator-release/SKILL.md`
   - When to use: Creating releases, publishing new versions
   - Covers: Changelog updates, version bumping, building, testing, GitHub release creation

2. **wc-smooth-generator-code-review** - Project-specific code review
   - Source: `.agents/skills/wc-smooth-generator-code-review/SKILL.md`
   - Wrapper: `.claude/skills/wc-smooth-generator-code-review/SKILL.md`
   - When to use: After implementing features, before creating PRs
   - Covers: Critical checks, phpcs compliance, term generation validation, order attribution checks

3. **wc-smooth-generator-dev-setup** - Development environment setup
   - Source: `.agents/skills/wc-smooth-generator-dev-setup/SKILL.md`
   - Wrapper: `.claude/skills/wc-smooth-generator-dev-setup/SKILL.md`
   - When to use: Setting up development environment, troubleshooting setup issues
   - Covers: Prerequisites, setup steps, troubleshooting common issues

**When to use skills:**
- Complex multi-step procedures that aren't part of every iteration
- Tasks that require specific sequence of steps
- Processes that have common pitfalls or gotchas
- Operations that need comprehensive checklists

**Adding new skills:**
1. Create skill content in `.agents/skills/{skill-name}/SKILL.md`
2. Create thin wrapper in `.claude/skills/{skill-name}/SKILL.md`:
   ```
   @../../../.agents/skills/{skill-name}/SKILL.md
   ```
3. Commit both files to repository

## Additional Documentation

- **README.md** - Installation, WP-CLI command reference, release process
- **TESTING.md** - Detailed test cases for ratio distribution feature
- **phpcs.xml.dist** - PHP_CodeSniffer configuration with comments
- **.github/workflows/php-unit-tests.yml** - CI/CD pipeline

## Project-Specific Context

### Why This Plugin Exists

WooCommerce needs realistic test data for development and testing. This plugin generates:
- Realistic products with images (using Jdenticon)
- Orders with varied dates, statuses, coupons, and refunds
- Customers with addresses and metadata
- Hierarchical category structures
- All with configurable parameters via WP-CLI

### What Makes This Plugin Special

1. **Exact ratio distribution** - Not just random chances, but guaranteed exact distributions
2. **O(1) memory usage** - Can generate millions of orders without memory issues
3. **Historical accuracy** - Respects feature availability dates (order attribution)
4. **HPOS compatible** - Works with WooCommerce's modern order storage
5. **Extensible** - Generator base class for adding new generators

### Common Use Cases

- QA testing with realistic data
- Performance testing with large datasets
- Demo sites with believable content
- Development environment setup

## Summary Checklist for AI Agents

Before making changes:
- [ ] Read this AGENTS.md file completely
- [ ] Check if task requires a skill (complex multi-step procedure)
- [ ] Understand phpcs ruleset and exclusions
- [ ] Know which files should never be committed

Before pushing:
- [ ] Run `vendor/bin/phpcs` on changed files
- [ ] Run `composer run test-unit` if code changed
- [ ] Verify no WordPress core files were modified
- [ ] Verify no forbidden files were committed
- [ ] Check commit messages are concise and one-line

For term generation changes:
- [ ] Verify taxonomy existence check is present
- [ ] Verify term cache clearing after batch operations
- [ ] Review recent commits for context (1ee6564, eb5c8ac)

For order generation changes:
- [ ] Respect order attribution 2024-01-09 cutoff
- [ ] Understand batch vs single mode ratio behavior
- [ ] Test with various `--coupon-ratio` and `--refund-ratio` values

---

**Last Updated:** February 18, 2026
**Maintained by:** WooCommerce Team
