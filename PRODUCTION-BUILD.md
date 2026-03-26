# Production Build Guide

How to prepare **GHL CRM Integration** (free) and **GHL CRM Integration Pro** for release.

> **This file is excluded from production zips.**

---

## Prerequisites

- PHP 7.4+
- Composer 2.x
- `zip` CLI (macOS/Linux built-in)

---

## 1. Bump Version Numbers

### Free Plugin (`ghl-crm-integration/`)

Update the version in **three** places:

| File | Location |
|------|----------|
| `gohighlevel-crm-integration.php` | Plugin header `Version:` line |
| `gohighlevel-crm-integration.php` | `define( 'GHL_CRM_VERSION', '...' );` |
| `README.md` | `Stable tag:` and badge URL |

### Pro Plugin (`ghl-crm-integration-pro/`)

Update the version in **two** places:

| File | Location |
|------|----------|
| `ghl-crm-integration-pro.php` | Plugin header `Version:` line |
| `ghl-crm-integration-pro.php` | `define( 'GHL_CRM_PRO_VERSION', '...' );` |

---

## 2. Update Changelogs

Update **four** changelog files:

- `ghl-crm-integration/CHANGELOG.md`
- `ghl-crm-integration/DEV_CHANGELOG.md`
- `ghl-crm-integration-pro/CHANGELOG.md`
- `ghl-crm-integration-pro/DEV_CHANGELOG.md`

Add a new section at the top with the version, date, and categorised changes (Added, Changed, Fixed, Improved).

---

## 3. Minify Assets

Both plugins use `matthiasmullie/minify` (a dev dependency) via a `build-minify.php` script.

```bash
# Free plugin
cd ghl-crm-integration
composer install          # installs dev deps (needed for minify)
composer build            # runs: php build-minify.php

# Pro plugin
cd ../ghl-crm-integration-pro
composer install
composer build
```

The build script minifies all `.css` and `.js` files that don't already have a `.min.` counterpart, writing `*.min.css` / `*.min.js` alongside the originals.

---

## 4. Strip Dev Dependencies

```bash
# Free plugin
cd ghl-crm-integration
composer install --no-dev --optimize-autoloader

# Pro plugin
cd ../ghl-crm-integration-pro
composer install --no-dev --optimize-autoloader
```

This removes PHPUnit, PHP_CodeSniffer, Minify, Mockery, and all other dev-only packages from `vendor/`.

---

## 5. Create Production Zips

### Free Plugin

From the `plugins/` directory:

```bash
cd /path/to/wp-content/plugins
zip -r ~/Desktop/ghl-crm-integration.zip ghl-crm-integration/ \
  -x@ghl-crm-integration/zip-exclude.txt
```

The `zip-exclude.txt` file lists all dev-only files/folders to exclude (`.git`, `tests/`, `composer.json`, `build-minify.php`, dev vendor packages, `PRODUCTION-BUILD.md`, `DEV_CHANGELOG.md`, `TODO.md`, `FEATURES-AND-BENEFITS.md`, etc.).

### Pro Plugin

```bash
zip -r ~/Desktop/ghl-crm-integration-pro.zip ghl-crm-integration-pro/ \
  -x "ghl-crm-integration-pro/.git/*" \
  -x "ghl-crm-integration-pro/.gitignore" \
  -x "ghl-crm-integration-pro/.phpunit.result.cache" \
  -x "ghl-crm-integration-pro/composer.json" \
  -x "ghl-crm-integration-pro/composer.lock" \
  -x "ghl-crm-integration-pro/phpunit.xml.dist" \
  -x "ghl-crm-integration-pro/build-minify.php" \
  -x "ghl-crm-integration-pro/DEV_CHANGELOG.md" \
  -x "ghl-crm-integration-pro/PRODUCTION-BUILD.md" \
  -x "ghl-crm-integration-pro/tests/*" \
  -x "ghl-crm-integration-pro/vendor/bin/*" \
  -x "*.DS_Store"
```

---

## 6. Restore Dev Environment (optional)

After zipping, re-install dev dependencies so you can continue development:

```bash
cd ghl-crm-integration && composer install
cd ../ghl-crm-integration-pro && composer install
```

---

## Quick Reference — Full Build Sequence

```bash
# From the plugins directory
cd /path/to/wp-content/plugins

# --- Free Plugin ---
cd ghl-crm-integration
composer install
composer build
composer install --no-dev --optimize-autoloader
cd ..
zip -r ~/Desktop/ghl-crm-integration.zip ghl-crm-integration/ \
  -x@ghl-crm-integration/zip-exclude.txt

# --- Pro Plugin ---
cd ghl-crm-integration-pro
composer install
composer build
composer install --no-dev --optimize-autoloader
cd ..
zip -r ~/Desktop/ghl-crm-integration-pro.zip ghl-crm-integration-pro/ \
  -x "ghl-crm-integration-pro/.git/*" \
  -x "ghl-crm-integration-pro/.gitignore" \
  -x "ghl-crm-integration-pro/.phpunit.result.cache" \
  -x "ghl-crm-integration-pro/composer.json" \
  -x "ghl-crm-integration-pro/composer.lock" \
  -x "ghl-crm-integration-pro/phpunit.xml.dist" \
  -x "ghl-crm-integration-pro/build-minify.php" \
  -x "ghl-crm-integration-pro/DEV_CHANGELOG.md" \
  -x "ghl-crm-integration-pro/PRODUCTION-BUILD.md" \
  -x "ghl-crm-integration-pro/tests/*" \
  -x "ghl-crm-integration-pro/vendor/bin/*" \
  -x "*.DS_Store"

# --- Restore dev deps ---
cd ghl-crm-integration && composer install
cd ../ghl-crm-integration-pro && composer install
```
