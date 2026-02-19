---
name: wc-smooth-generator-code-review
description: Project-specific code review checklist for WooCommerce Smooth Generator focusing on common pitfalls and WordPress/WooCommerce standards
trigger: Use this skill after implementing features, before creating PRs, or when the user requests a code review
---

# WooCommerce Smooth Generator - Code Review Checklist

This skill provides a comprehensive code review checklist specific to this project.

## Critical Checks (MUST PASS)

### 1. File Location Verification

**Check that NO changes were made to:**
- [ ] WordPress core files (`wp-admin/`, `wp-includes/`)
- [ ] WooCommerce core files (`wp-content/plugins/woocommerce/`)
- [ ] Other plugins (only `wp-content/plugins/wc-smooth-generator/` should be modified)

**Check that these files are NOT staged for commit:**
- [ ] `CLAUDE.md` (unless explicitly requested)
- [ ] `AGENTS.md` (unless explicitly requested)
- [ ] `.claude/settings.local.json`
- [ ] `.claude/plans/` directory (unless explicitly updating PROGRESS.md)
- [ ] `changelog.txt` (should ONLY be updated during releases)

### 2. PHP Coding Standards

Run phpcs on all changed files:
```bash
composer run phpcs
```

Or for specific file:
```bash
vendor/bin/phpcs path/to/file.php
```

**Requirements:**
- [ ] No phpcs errors or warnings
- [ ] Code follows WooCommerce-Core ruleset
- [ ] Text domain is `wc-smooth-generator` for all translatable strings
- [ ] Proper inline documentation (DocBlocks for classes, methods, properties)

**Common issues:**
- Missing parameter/return type documentation
- Incorrect text domain in translation functions
- Improper spacing around operators
- Line length exceeds 120 characters

**Auto-fix when possible:**
```bash
composer run phpcbf
```

### 3. Term Generation Specific Checks

If code touches term generation (`includes/Generator/Term.php` or term-related code):

**MUST verify:**
- [ ] Taxonomy existence check BEFORE generation:
  ```php
  if ( ! taxonomy_exists( $taxonomy ) ) {
      // Handle error
  }
  ```

- [ ] Term cache clearing AFTER batch generation:
  ```php
  // After generating terms in batch
  clean_term_cache( $taxonomy );
  ```

**Why this matters:**
- Missing taxonomy checks cause silent failures
- Missing cache clearing makes new terms invisible in queries
- Recent fixes: commits 1ee6564 (cache clearing) and eb5c8ac (taxonomy validation)

### 4. Order Attribution Checks

If code touches order generation (`includes/Generator/Order.php` or order attribution):

**MUST verify:**
- [ ] Respects 2024-01-09 cutoff date for order attribution
- [ ] Orders with creation date before 2024-01-09 do NOT get attribution metadata
- [ ] Attribution metadata only generated when `--skip-order-attribution` is not set

**Why this matters:**
- Order attribution feature did not exist in WooCommerce before 2024-01-09
- Generating attribution for older dates is historically inaccurate

### 5. Ratio Distribution Checks

If code touches ratio-based generation (coupons, refunds):

**MUST verify:**
- [ ] Uses selection without replacement for batch mode (>1 item)
- [ ] Guarantees exact distribution in batch mode
- [ ] Uses probabilistic fallback for single item mode
- [ ] Memory usage is O(1) - no storing all indices in array

**Test with:**
```bash
# Should produce EXACTLY 50 orders with coupons
wp wc generate orders 100 --coupon-ratio=0.5

# Should produce EXACTLY 30 refunds (15 full, 7 partial, 8 multi-partial)
wp wc generate orders 100 --status=completed --refund-ratio=0.3
```

## WordPress/WooCommerce Standards

### 6. Namespace and Autoloading

- [ ] All classes in `includes/` use `WC\SmoothGenerator\` namespace
- [ ] All test classes in `tests/Unit/` use `WC\SmoothGenerator\Tests\` namespace
- [ ] File structure matches PSR-4 namespace structure
- [ ] No manual `require` or `include` statements (use autoloader)

### 7. WordPress Hooks and Filters

- [ ] Use WordPress hooks/filters, not direct modification of core behavior
- [ ] Hook priority and parameter count are correct
- [ ] Hooks are properly documented with `@hook` tags
- [ ] No hooks are used that don't exist in minimum WordPress version (5.0)

### 8. WooCommerce HPOS Compatibility

If code touches orders:

- [ ] Uses `wc_get_order()` instead of direct post queries
- [ ] Uses order methods (e.g., `$order->get_status()`) instead of post meta
- [ ] Compatible with both HPOS and legacy post storage
- [ ] No assumptions about `$order->ID` being a post ID

### 9. Security

- [ ] All user input is sanitized using WordPress functions
- [ ] All output is escaped using WordPress functions
- [ ] Database queries use `$wpdb->prepare()` for dynamic values
- [ ] Nonces are used for form submissions (if any)
- [ ] Capability checks for admin-only functionality

## Testing Requirements

### 10. Unit Tests

If new functionality was added:

- [ ] Unit tests exist for new functionality
- [ ] Tests are in `tests/Unit/` directory
- [ ] Test class names match file names
- [ ] Tests use PHPUnit assertions

**Run tests:**
```bash
composer run test-unit
```

**Test coverage check:**
- New generators should have generator tests
- New utilities should have utility tests
- Bug fixes should have regression tests

### 11. Manual Testing

For generator changes:

**Test with various parameters:**
```bash
# Test small batch (edge case)
wp wc generate <type> 1

# Test medium batch
wp wc generate <type> 10

# Test large batch
wp wc generate <type> 100

# Test with various flags
wp wc generate <type> 10 --<relevant-flag>
```

**Verify:**
- [ ] Generated data appears in WordPress admin
- [ ] No PHP errors or warnings in debug log
- [ ] Memory usage is reasonable
- [ ] Performance is acceptable

## Documentation

### 12. Code Documentation

- [ ] All public methods have DocBlocks with description, `@param`, `@return`
- [ ] Complex algorithms have inline comments explaining logic
- [ ] Magic numbers are explained or converted to named constants
- [ ] `@since` tags are present for new public methods

### 13. README Updates

If user-facing behavior changed:

- [ ] `README.md` updated with new commands/options
- [ ] Examples provided for new functionality
- [ ] `AGENTS.md` updated if AI agents need to know about changes

**Do NOT update:**
- [ ] `changelog.txt` (only during releases)

## Commit Quality

### 14. Commit Messages

- [ ] Concise, one-line messages
- [ ] Imperative mood ("Fix bug" not "Fixed bug")
- [ ] No mention of "Claude" or "AI assistant"
- [ ] Follows existing commit style in `git log`

**Good examples:**
- "Add taxonomy existence check before term generation"
- "Clear term cache after batch generation"
- "Fix order attribution cutoff date check"

**Bad examples:**
- "Updated some files"
- "Fix bug that Claude found"
- "Various changes to improve code quality"

### 15. Commit Size

- [ ] Small, focused commits (not too many changes at once)
- [ ] Each commit is a logical unit of work
- [ ] Related changes are grouped together
- [ ] Unrelated changes are in separate commits

## Pre-PR Checklist

Before creating a pull request:

- [ ] All critical checks passed
- [ ] `composer run lint-branch` executed and passing
- [ ] `composer run test-unit` executed and passing
- [ ] Manual testing completed
- [ ] Documentation updated as needed
- [ ] Commits are clean and well-organized

**Run final checks:**
```bash
# Lint only branch changes
composer run lint-branch

# Run unit tests
composer run test-unit

# Check git status
git status
```

## Common Mistakes to Avoid

1. **Editing WordPress/WooCommerce core files** - Always work in plugin directory only
2. **Committing CLAUDE.md or settings.local.json** - These should stay local
3. **Updating changelog.txt outside releases** - Only update during release process
4. **Forgetting term cache clearing** - Always clear after batch term generation
5. **Missing taxonomy existence checks** - Always validate before term generation
6. **Ignoring order attribution cutoff** - Respect 2024-01-09 date limit
7. **Breaking exact ratio distribution** - Maintain O(1) memory, deterministic behavior
8. **Skipping phpcs** - Always run before committing
9. **Large, unfocused commits** - Keep commits small and logical
10. **Missing test coverage** - Add tests for new functionality

## Reference Commits

When reviewing similar changes, refer to these commits:

- **Term cache clearing:** 1ee6564
- **Taxonomy validation:** eb5c8ac
- **Term existence checks:** eb5c8ac
- **Brand assignment tests:** 0edc4dc
- **Test cache isolation:** 36c8058

## Success Criteria

All critical checks must pass:
- ✅ No WordPress/WooCommerce core modifications
- ✅ No forbidden files committed
- ✅ phpcs passing on all changed files
- ✅ Term generation checks passed (if applicable)
- ✅ Order attribution checks passed (if applicable)
- ✅ Ratio distribution checks passed (if applicable)
- ✅ Tests passing
- ✅ Manual testing completed
- ✅ Documentation updated
- ✅ Commits are clean and well-organized

## Notes

- This checklist is specifically for wc-smooth-generator project
- General WordPress/WooCommerce best practices also apply
- When in doubt, check existing code for patterns
- Ask for clarification rather than guessing at requirements
