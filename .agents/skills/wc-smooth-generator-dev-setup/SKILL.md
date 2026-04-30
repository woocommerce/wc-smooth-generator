---
name: wc-smooth-generator-dev-setup
description: Development environment setup for WooCommerce Smooth Generator with troubleshooting for common setup issues
trigger: Use this skill when setting up the development environment, troubleshooting setup issues, or when the user asks about development requirements
---

# WooCommerce Smooth Generator - Development Setup

This skill guides you through setting up a complete development environment for wc-smooth-generator.

## Prerequisites

### Required Software

1. **WordPress 6.7+**
   - Installed and running locally
   - Any local WordPress environment works (DDEV, Local, MAMP, etc.)

2. **WooCommerce 10.3+**
   - Must be installed and activated
   - Download from: https://wordpress.org/plugins/woocommerce/

3. **PHP 7.4+**
   - Check version: `php --version`
   - Recommended: PHP 8.0 or higher
   - Must have extensions: `curl`, `gd`, `mbstring`, `openssl`, `zip`

4. **Node.js v16**
   - Check version: `node --version`
   - Download from: https://nodejs.org/
   - Recommended: Use [nvm](https://github.com/nvm-sh/nvm) for version management

5. **Composer v2+**
   - Check version: `composer --version`
   - Download from: https://getcomposer.org/

6. **Git**
   - Check version: `git --version`
   - Required for version control and Git hooks

7. **WP-CLI** (optional but recommended)
   - Check: `wp --version`
   - Download from: https://wp-cli.org/
   - Required for testing generator commands

## Setup Steps

### 1. Clone Repository

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/woocommerce/wc-smooth-generator.git
cd wc-smooth-generator
```

Or if you already have the repository:
```bash
cd /path/to/wc-smooth-generator
git checkout trunk
git pull origin trunk
```

### 2. Install Node.js v16 (if using nvm)

```bash
# Install nvm (if not already installed)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash

# Restart terminal, then:
nvm install 16
nvm use 16

# Or just use the project's .nvmrc:
nvm use
```

**Verify Node version:**
```bash
node --version
# Should output: v16.x.x
```

### 3. Run Setup Script

This is the primary setup command - it handles everything:

```bash
npm run setup
```

**What this does:**
1. Runs `npm install` - installs Node.js dependencies (Husky)
2. Runs `composer install` - installs PHP dependencies (Faker, Jdenticon, phpcs, PHPUnit)
3. Runs `husky install` - sets up Git hooks for pre-commit linting

### 4. Verify Installation

**Check that dependencies were installed:**
```bash
# Check vendor directory exists
ls -la vendor/

# Check node_modules exists
ls -la node_modules/

# Check Git hooks installed
ls -la .husky/
```

**Verify phpcs is working:**
```bash
vendor/bin/phpcs --version
# Should output: PHP_CodeSniffer version 3.x.x

composer run phpcs
# Should run phpcs on the codebase
```

**Verify tests can run:**
```bash
composer run test-unit
# Should execute PHPUnit tests
```

### 5. Activate Plugin in WordPress

Via WP Admin:
1. Go to Plugins → Installed Plugins
2. Find "WooCommerce Smooth Generator"
3. Click "Activate"

Via WP-CLI:
```bash
wp plugin activate wc-smooth-generator
```

### 6. Test WP-CLI Commands

```bash
# Test that the plugin's CLI commands are registered
wp help wc generate

# Generate test products
wp wc generate products 5

# Verify products were created in WP Admin → Products
```

## Troubleshooting

### Node.js Version Issues

**Problem:** "The engine "node" is incompatible with this module"

**Solution:**
```bash
# Install Node v16
nvm install 16
nvm use 16

# Verify version
node --version

# Run setup again
npm run setup
```

**Problem:** `nvm: command not found`

**Solution:**
```bash
# Install nvm first
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash

# Restart terminal
# Then install Node v16
nvm install 16
nvm use 16
```

### Composer Issues

**Problem:** "composer: command not found"

**Solution:**
```bash
# Install Composer globally (macOS/Linux)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer

# Verify
composer --version
```

**Problem:** "Your requirements could not be resolved"

**Solution:**
```bash
# Update Composer
composer self-update

# Clear cache
composer clear-cache

# Try install again
composer install
```

**Problem:** Composer v1 instead of v2

**Solution:**
```bash
# Update to v2
composer self-update --2

# Verify version
composer --version
# Should show: Composer version 2.x.x
```

### Git Hooks Issues

**Problem:** Pre-commit hook not running

**Solution:**
```bash
# Reinstall hooks
npm run setup

# Verify hook exists
cat .husky/pre-commit

# Test hook manually
composer run lint-staged
```

**Problem:** "husky - command not found"

**Solution:**
```bash
# Install husky
npm install

# Install hooks
npx husky install

# Verify
ls -la .husky/
```

**Problem:** Pre-commit hook blocks valid commits

**Solution:**
```bash
# Run phpcs manually to see errors
composer run phpcs

# Auto-fix issues
composer run phpcbf

# Try commit again
git commit
```

### PHP Issues

**Problem:** "PHP version not supported"

**Solution:**
```bash
# Check PHP version
php --version

# If < 7.4, install newer PHP
# On macOS with Homebrew:
brew install php@8.0

# Verify
php --version
```

**Problem:** Missing PHP extensions

**Solution:**
```bash
# Check loaded extensions
php -m

# Install missing extensions (Ubuntu/Debian)
sudo apt-get install php-curl php-gd php-mbstring php-zip

# Install missing extensions (macOS with Homebrew)
brew install php@8.0
# Extensions are typically included
```

### WP-CLI Issues

**Problem:** "wp: command not found"

**Solution:**
```bash
# Install WP-CLI (macOS/Linux)
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# Verify
wp --version
```

**Problem:** "Error: This does not seem to be a WordPress installation"

**Solution:**
```bash
# Make sure you're in WordPress root directory, not plugin directory
cd /path/to/wordpress

# Then run WP-CLI commands
wp plugin list
```

**Problem:** WP-CLI commands hang or timeout

**Solution:**
```bash
# Increase PHP memory limit
wp wc generate products 100 --allow-root --debug

# Or edit wp-config.php:
define( 'WP_MEMORY_LIMIT', '256M' );
```

### Plugin Activation Issues

**Problem:** "Plugin missing required WooCommerce dependency"

**Solution:**
1. Install WooCommerce via Plugins → Add New
2. Activate WooCommerce
3. Complete WooCommerce setup wizard (or skip it)
4. Then activate wc-smooth-generator

**Problem:** "Fatal error: Class not found"

**Solution:**
```bash
# Regenerate autoloader
cd wp-content/plugins/wc-smooth-generator
composer dump-autoload

# Deactivate and reactivate plugin
wp plugin deactivate wc-smooth-generator
wp plugin activate wc-smooth-generator
```

## Development Workflow

### Making Code Changes

1. **Create feature branch:**
   ```bash
   git checkout trunk
   git pull origin trunk
   git checkout -b feature/my-feature
   ```

2. **Make changes to code**

3. **Run linter:**
   ```bash
   # Lint all files
   composer run phpcs

   # Auto-fix issues
   composer run phpcbf
   ```

4. **Run tests:**
   ```bash
   composer run test-unit
   ```

5. **Test manually:**
   ```bash
   wp wc generate products 10
   # Verify in WP Admin
   ```

6. **Commit changes:**
   ```bash
   git add .
   git commit -m "Add feature description"
   # Pre-commit hook will run phpcs automatically
   ```

### Daily Development

**Start of day:**
```bash
# Switch to Node v16 (if using nvm)
nvm use

# Pull latest changes
git checkout trunk
git pull origin trunk
```

**During development:**
```bash
# Run phpcs frequently
composer run phpcs

# Auto-fix issues
composer run phpcbf

# Run specific test
vendor/bin/phpunit tests/Unit/Generator/ProductTest.php

# Generate test data
wp wc generate products 10
wp wc generate orders 20
```

**Before committing:**
```bash
# Lint only changed files
composer run lint

# Run all tests
composer run test-unit

# Check git status
git status
```

## Available Commands Reference

### npm Scripts

```bash
npm run setup          # Install dependencies and Git hooks
npm run build          # Create production zip file
```

### Composer Scripts

```bash
composer run phpcs           # Lint entire codebase
composer run phpcbf          # Auto-fix coding standards
composer run lint            # Lint only unstaged changes
composer run lint-staged     # Lint only staged changes (pre-commit)
composer run lint-branch     # Lint branch changes vs base branch
composer run test-unit       # Run PHPUnit tests
```

### WP-CLI Commands

```bash
wp wc generate products <nr>    # Generate products
wp wc generate orders <nr>      # Generate orders
wp wc generate customers <nr>   # Generate customers
wp wc generate coupons <nr>     # Generate coupons
wp wc generate terms <tax> <nr> # Generate terms

# See all options
wp help wc generate
wp help wc generate products
```

## Environment Checklist

Before starting development:

- [ ] WordPress 6.7+ installed and running
- [ ] WooCommerce 10.3+ installed and activated
- [ ] PHP 7.4+ with required extensions
- [ ] Node.js v16 installed (use `nvm use`)
- [ ] Composer v2+ installed
- [ ] Git installed
- [ ] WP-CLI installed (recommended)
- [ ] Repository cloned
- [ ] `npm run setup` completed successfully
- [ ] Plugin activated in WordPress
- [ ] WP-CLI commands working (`wp help wc generate`)
- [ ] phpcs working (`composer run phpcs`)
- [ ] Tests passing (`composer run test-unit`)
- [ ] Pre-commit hook working (blocks commits with phpcs errors)

## Common Setup Paths

### macOS (Homebrew)

```bash
# Install prerequisites
brew install php@8.0
brew install node@16
brew install composer

# Install nvm for Node version management
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
nvm install 16

# Install WP-CLI
brew install wp-cli

# Setup project
cd /path/to/wc-smooth-generator
nvm use
npm run setup
```

### Ubuntu/Debian

```bash
# Install prerequisites
sudo apt-get update
sudo apt-get install php php-cli php-mbstring php-curl php-zip unzip
sudo apt-get install nodejs npm
sudo apt-get install composer

# Install nvm for Node version management
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
nvm install 16

# Install WP-CLI
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# Setup project
cd /path/to/wc-smooth-generator
nvm use
npm run setup
```

### Windows (WSL recommended)

Use WSL (Windows Subsystem for Linux) and follow Ubuntu/Debian instructions above.

## Next Steps

After setup is complete:

1. Read `AGENTS.md` for project architecture and conventions
2. Review `README.md` for WP-CLI command reference
3. Check `phpcs.xml.dist` to understand coding standards
4. Review existing code in `includes/` to understand patterns
5. Run test generators to see plugin in action

## Getting Help

If you encounter issues not covered here:

1. Check GitHub issues: https://github.com/woocommerce/wc-smooth-generator/issues
2. Review CI workflow: `.github/workflows/php-unit-tests.yml`
3. Ask in WooCommerce Community Slack: https://woocommerce.com/community-slack/

## Success Criteria

Setup is complete when:

- ✅ All prerequisites installed
- ✅ `npm run setup` completed without errors
- ✅ Plugin activated in WordPress
- ✅ `wp help wc generate` shows plugin commands
- ✅ `composer run phpcs` runs successfully
- ✅ `composer run test-unit` passes all tests
- ✅ Pre-commit hook blocks commits with linting errors
- ✅ Can generate test data: `wp wc generate products 5`

## Notes

- Always use Node v16 (use `nvm use` in project directory)
- Pre-commit hooks are essential - don't skip `npm run setup`
- After `npm run build`, always run `npm run setup` again
- WP-CLI is technically optional but highly recommended
- Keep dependencies updated: `composer update`, `npm update`
