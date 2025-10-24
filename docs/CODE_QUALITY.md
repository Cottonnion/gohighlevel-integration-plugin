# Code Quality & Standards

This document explains how to use the code quality tools in this project.

## Overview

The project uses **PHP_CodeSniffer** with **WordPress Coding Standards** to ensure consistent code quality across the plugin.

## Quick Start

```bash
# Check all files
./check

# Check specific file or directory
./check src/Core/Loader.php
./check src/API/

# Fix automatically fixable issues
./fix

# Fix specific file or directory
./fix src/Core/Loader.php
./fix src/API/
```

## Tools Installed

- **PHP_CodeSniffer (phpcs)** - Detects coding standard violations
- **PHP Code Beautifier and Fixer (phpcbf)** - Automatically fixes violations
- **WordPress Coding Standards** - Official WordPress rules
- **Dealerdirect PHPCS Composer Installer** - Auto-configures standards

## Configuration

### phpcs.xml

The project includes a `phpcs.xml` configuration file with:

- **WordPress** coding standards as base
- **WordPress-Extra** for additional checks  
- **WordPress-Docs** for documentation standards
- Custom exclusions for modern PHP practices
- Specific rules for this plugin

### Key Settings

```xml
<!-- Allow modern short array syntax -->
<exclude name="Universal.Arrays.DisallowShortArraySyntax"/>

<!-- Allow PSR-4 class names (not snake_case files) -->
<exclude name="WordPress.Files.FileName.NotHyphenatedLowercase"/>

<!-- Plugin-specific text domain -->
<property name="text_domain" value="ghl-crm-integration"/>

<!-- Plugin-specific prefixes -->
<property name="prefixes" value="ghl_crm,GHL_CRM"/>
```

## Shell Scripts

### ./check

**Purpose:** Lint code for standards violations

**Usage:**
```bash
./check                    # Check all files
./check src/Core/         # Check directory
./check src/API/Client.php # Check single file
```

**Features:**
- Colorized output
- Progress indicator
- Shows sniff codes
- Parallel processing (8 threads)
- Automatic fallback to WordPress standards if no config

### ./fix

**Purpose:** Automatically fix coding standards violations

**Usage:**
```bash
./fix                      # Fix all files
./fix src/Core/           # Fix directory  
./fix src/API/Client.php  # Fix single file
```

**Features:**
- Interactive confirmation (prevents accidental changes)
- Colorized output
- Shows what was fixed
- Safe operation (only fixes non-destructive issues)

## Composer Scripts

Alternative to shell scripts:

```bash
# Via composer
composer run check         # Same as ./check
composer run fix           # Same as ./fix
composer run standards     # List available standards

# Check specific files via stdin
echo "src/Core/Loader.php" | composer run check-file
echo "src/Core/Loader.php" | composer run fix-file
```

## What Gets Checked

### Included Files
- `src/` - All source code
- `gohighlevel-crm-integration.php` - Main plugin file
- `templates/` - PHP template files

### Excluded Files
- `vendor/` - Composer dependencies
- `node_modules/` - NPM dependencies
- `assets/admin/js/` - JavaScript files
- `assets/admin/css/` - CSS files

### Coding Standards Applied

1. **WordPress Core** - Basic WordPress conventions
2. **WordPress Extra** - Additional WordPress-specific rules
3. **WordPress Docs** - Documentation standards
4. **PSR-12** compatibility where appropriate
5. **Security** checks (sanitization, escaping, nonces)

## Common Issues & Fixes

### Automatically Fixable

- ✅ Indentation (tabs vs spaces)
- ✅ Array alignment
- ✅ Whitespace cleanup
- ✅ Brace placement
- ✅ Function call formatting

### Manual Fixes Required

- ❌ Missing documentation
- ❌ Security issues (sanitization, escaping)
- ❌ Text domain problems
- ❌ Naming convention violations
- ❌ Complex logic issues

### Example Workflow

```bash
# 1. Check current status
./check

# 2. Auto-fix what can be fixed
./fix

# 3. Check again to see remaining issues
./check

# 4. Manually fix remaining issues

# 5. Final check
./check
```

## CI/CD Integration

For continuous integration, you can use:

```bash
# In CI pipeline
composer install --dev --no-interaction
./check

# Or via composer
composer run check
```

**Exit codes:**
- `0` - All files pass
- `1` - Violations found (fixable)
- `2` - Violations found (not fixable)
- `3` - Configuration error

## Customization

### Adding Exclusions

Edit `phpcs.xml` to exclude specific rules:

```xml
<rule ref="WordPress">
    <exclude name="WordPress.Security.EscapeOutput"/>
</rule>
```

### Adding Custom Rules

Add additional rulesets:

```xml
<rule ref="PSR12"/>
<rule ref="Security"/>
```

### File-Specific Rules

Exclude rules for specific files:

```xml
<rule ref="WordPress.WP.GlobalVariablesOverride">
    <exclude-pattern>*/templates/*</exclude-pattern>
</rule>
```

## IDE Integration

### VS Code

Install the **phpcs** extension:
1. Install "PHP Sniffer & Beautifier" extension
2. Configure in `settings.json`:

```json
{
    "phpSniffer.standard": "./phpcs.xml",
    "phpSniffer.executablesFolder": "./vendor/bin/",
    "phpSniffer.autoDetect": true
}
```

### PhpStorm

1. Go to **Settings → PHP → Quality Tools → PHP_CodeSniffer**
2. Set **Configuration:** `Local`
3. Set **PHP_CodeSniffer path:** `vendor/bin/phpcs`
4. Set **Coding standard:** `Custom`
5. Set **Path to ruleset:** `phpcs.xml`

## Troubleshooting

### Standards Not Found

```bash
# Check available standards
./vendor/bin/phpcs -i

# Should show: WordPress, WordPress-Core, WordPress-Docs, WordPress-Extra
```

### Permission Denied

```bash
# Make scripts executable
chmod +x check fix
```

### Path Issues (macOS)

```bash
# If paths with spaces cause issues
cd "/Users/macair/Local Sites/plugin-name"
./check
```

### Memory Issues

Edit `phpcs.xml` to reduce parallelism:

```xml
<!-- Reduce from 8 to 4 -->
<arg name="parallel" value="4"/>
```

## Best Practices

1. **Run checks frequently** during development
2. **Fix auto-fixable issues** immediately with `./fix`
3. **Check before commits** to maintain quality
4. **Use IDE integration** for real-time feedback
5. **Customize rules** to match team preferences
6. **Document exceptions** when excluding rules

## WordPress.org Compliance

The configuration ensures compliance with WordPress.org requirements:

- ✅ GPL v2+ licensing
- ✅ No obfuscated code
- ✅ Proper sanitization/escaping
- ✅ WordPress coding standards
- ✅ Security best practices
- ✅ Internationalization support

---

## Summary

With these tools, you can:

- ✅ **Maintain consistent code quality**
- ✅ **Catch issues early** 
- ✅ **Automate fixes** where possible
- ✅ **Ensure WordPress.org compliance**
- ✅ **Improve code maintainability**

Use `./check` regularly and `./fix` to keep your code clean and standards-compliant! 🚀
