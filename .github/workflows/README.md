# CI/CD Pipeline

This project uses three GitHub Actions workflows. Here's what each one does, when it runs, and how they fit together.

---

## Overview

```
Daily work
  └── Open PR to develop or main
        └── ci.yml runs automatically (tests must pass)
              └── PR merged to main
                    └── No deploy yet — code sits on main
                          └── Lead dev pushes a version tag (e.g. v1.2.3)
                                ├── deploy.yml → ships code to production server
                                └── release.yml → builds zip + creates GitHub Release
```

---

## Workflows

### `ci.yml` — Continuous Integration

**Triggers:** Every pull request to `develop` or `main`

**What it does:**
- Sets up PHP 8.1
- Installs Composer dependencies (cached for speed)
- Runs the full PHPUnit test suite with 512MB memory limit

**Purpose:** Validates that code works before it can be merged. No code reaches `main` without passing tests.

**Does it deploy?** No. Never.

---

### `deploy.yml` — Production Deploy

**Triggers:**
- Push of a version tag matching `v*.*.*` (e.g. `v1.2.3`)
- Manual trigger via the "Run workflow" button in the GitHub Actions UI

**What it does:**
- SSHes into the production server using stored credentials
- Runs `git pull` to fetch the tagged commit
- Runs `composer install --no-dev` to update PHP dependencies

**How to trigger a deploy:**
```bash
git tag v1.2.3 && git push origin v1.2.3
```

**Rollback options:**
1. Re-tag an older commit and push (triggers a new deploy to that version)
2. SSH directly into the server and run `git checkout v1.2.2` for an instant fix
3. Revert the bad commit, push to main, create a new tag

**Required GitHub Secrets:**
| Secret | Description |
|---|---|
| `SSH_PRIVATE_KEY` | Private key for server SSH access |
| `SSH_HOST` | Server hostname or IP |
| `SSH_USER` | SSH username |
| `SSH_PATH` | Absolute path to plugin on server |
| `GH_TOKEN` | GitHub personal access token (for git pull on server) |

---

### `release.yml` — GitHub Release + Zip

**Triggers:** Push of a version tag matching `v*.*.*` (runs in parallel with `deploy.yml`)

**What it does:**
- Sets up PHP 8.1 and installs production Composer dependencies (`--no-dev`)
- Builds the distributable zip with the `syncly` plugin slug
- Creates a GitHub Release with the zip attached and auto-generated changelog

**Purpose:** Produces a downloadable `syncly-vX.X.X.zip` that can be installed manually on any WordPress site. After the zip is validated, the `deploy-wporg` job automatically pushes to WordPress.org SVN trunk and creates a version tag.

**Required GitHub Permissions:** `contents: write`

**Required GitHub Secrets (WordPress.org deploy):**
| Secret | Description |
|---|---|
| `WPORG_USERNAME` | WordPress.org account username |
| `WPORG_PASSWORD` | WordPress.org application password (generate at wordpress.org → Profile → Application Passwords) |

**WordPress.org assets** (banner, icon, screenshots) should be placed in a `.wordpress-org/` folder at the repo root. The deploy action reads from `ASSETS_DIR: .wordpress-org`.

---

## Branching Strategy

| Branch | Purpose | Deploy |
|---|---|---|
| `develop` | Active development, PRs merged here daily | No |
| `main` | Stable, release-ready code | Only on version tag |
| `fix/*`, `feat/*` | Feature/fix branches | No |

**Rule:** Never push directly to `main`. Always open a PR so CI runs first.

---

## Versioning

Plugin version is defined in two places — both must match the git tag:

- `ghl-crm-integration.php` header: `* Version: 1.2.3`
- `ghl-crm-integration.php` constant: `define( 'GHL_CRM_VERSION', '1.2.3' )`

The version is used as the cache-busting string for all enqueued CSS/JS assets.

**Steps to ship a release:**
1. Update version in `ghl-crm-integration.php` (both places)
2. Commit: `git commit -m "chore: bump version to 1.2.3"`
3. Push: `git push origin main`
4. Tag and deploy: `git tag v1.2.3 && git push origin v1.2.3`

---

## Server Info

- **Host:** highlevelsync.com
- **Plugin path:** `/home/highlevelsync/public_html/wp-content/plugins/syncly`
- **Setup:** Full `git clone` of this repo, SSH key in `~/.ssh/authorized_keys`
- **Deploy method:** `appleboy/ssh-action` — SSHes in, runs `git pull` + `composer install`
