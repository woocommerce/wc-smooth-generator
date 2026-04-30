---
name: wc-smooth-generator-release
description: Complete release process for WooCommerce Smooth Generator including changelog, version bump, build, testing, and GitHub release creation
trigger: Use this skill when the user asks to create a release, publish a new version, or perform release-related tasks
---

# WooCommerce Smooth Generator - Release Process

This skill guides you through the complete release process for wc-smooth-generator.

## Prerequisites

Before starting the release:
- All changes must be committed and pushed
- All tests must be passing (`composer run test-unit`)
- Code must pass linting (`composer run phpcs`)
- You must have the new version number from the user

## Release Steps

### 1. Create Release Branch

```bash
git checkout trunk
git pull origin trunk
git checkout -b release-x.x.x
```

Replace `x.x.x` with the actual version number.

### 2. Update Changelog

Edit `changelog.txt`:
- Add new entry at the top following existing format
- Use format: `= x.x.x - YYYY-MM-DD =`
- List all changes since last release under appropriate headings:
  - `* Fix -` for bug fixes
  - `* Add -` for new features
  - `* Update -` for improvements
  - `* Dev -` for development changes
- Review git log since last release for changes: `git log --oneline <last-version-tag>..HEAD`

### 3. Update Plugin Version

Update version in TWO files:

**wc-smooth-generator.php** (plugin header):
```php
 * Version: x.x.x
```

**package.json**:
```json
"version": "x.x.x"
```

Also check if these need updating in **wc-smooth-generator.php**:
- `Tested up to:` - WordPress version
- `WC tested up to:` - WooCommerce version

### 4. Build Release Package

```bash
npm run build
```

This will:
- Install production dependencies only
- Create `wc-smooth-generator.zip` in project root
- Remove dev dependencies afterward

**CRITICAL:** After build completes, run:
```bash
npm run setup
```

This restores dev dependencies and reinstalls Git hooks (required for future commits).

### 5. Test Release Package

1. Install the generated zip file in a test WordPress site
2. Verify version number shows correctly in Plugins list
3. Test at least one WP-CLI command: `wp wc generate products 5`
4. Verify no errors in WordPress debug log

### 6. Commit and Push

```bash
git add changelog.txt wc-smooth-generator.php package.json package-lock.json
git commit -m "Prepare release x.x.x"
git push origin release-x.x.x
```

### 7. Create Pull Request

```bash
gh pr create --title "Release x.x.x" --body "$(cat <<'EOF'
Release version x.x.x

## Changes
[Paste changelog entry here]

## Pre-release Checklist
- [x] Changelog updated
- [x] Version bumped in wc-smooth-generator.php
- [x] Version bumped in package.json
- [x] Build tested (zip file works)
- [x] Tests passing
- [x] Linting passing
EOF
)"
```

### 8. Merge Pull Request

After PR is approved:
```bash
gh pr merge --squash
```

Or merge via GitHub UI.

### 9. Create GitHub Release

```bash
git checkout trunk
git pull origin trunk
git tag x.x.x
git push origin x.x.x
```

Then via GitHub UI:
1. Go to https://github.com/woocommerce/wc-smooth-generator/releases
2. Click "Draft a new release"
3. Click "Choose a tag" → Select the tag you just created (x.x.x)
4. Set release title: "Version x.x.x"
5. Release description format:
   ```
   Brief summary of highlights (1-2 sentences).

   ## Changelog
   [Paste the new changelog entry]
   ```
6. Upload `wc-smooth-generator.zip` file
7. Click "Publish release"

### 10. Post-Release Cleanup

```bash
git checkout trunk
git pull origin trunk
git branch -d release-x.x.x
```

## Common Issues

### Build script fails
- Ensure Node.js v16 is active: `nvm use`
- Ensure composer v2+ is installed: `composer --version`
- Check for syntax errors: `vendor/bin/phpcs`

### Git hooks stop working after release
- This is expected - `npm run build` removes dev dependencies
- Always run `npm run setup` after building
- Verify hooks work: `git commit` should run phpcs on staged files

### Zip file missing vendor directory
- This indicates composer install failed during build
- Check for composer.json syntax errors
- Ensure you have write permissions in project directory

### GitHub release upload fails
- GitHub UI sometimes has issues with large files
- Try refreshing page and uploading again
- Maximum file size is 2GB (this plugin is ~5MB, so not an issue)

## Success Criteria

- [ ] Release branch created
- [ ] Changelog updated with all changes
- [ ] Version bumped in both files (wc-smooth-generator.php, package.json)
- [ ] Tested up to values current (if needed)
- [ ] Build successful (zip created)
- [ ] Zip tested in WordPress site
- [ ] `npm run setup` run after build
- [ ] Changes committed and pushed
- [ ] PR created and merged to trunk
- [ ] Git tag created and pushed
- [ ] GitHub release created with zip file
- [ ] Post-release cleanup complete

## Notes

- Always test the zip file before publishing the GitHub release
- Never skip `npm run setup` after build - this will break Git hooks
- The zip file should be approximately 5MB (exact size varies)
- Ensure the changelog entry is accurate - users rely on this
