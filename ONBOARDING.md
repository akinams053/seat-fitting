# ONBOARDING — akinams053/seat-fitting

Handoff snapshot at HEAD `cb7f193` (tag `v1.0.0`). Read this once end-to-end before doing anything; the section ordering goes from "what this is" → "what's already deployed" → "how to keep iterating". For deep architectural context (state machines, EVE slot encoding, namespaces), read `CLAUDE.md` after this.

---

## 1. What this fork is

`akinams053/seat-fitting` is a downstream fork of [`cryptatech/seat-fitting`](https://github.com/eveseat-plugins/seat-fitting), branched at their `5.0.11`. The fork's own version numbering starts fresh at `v1.0.0`.

**Deltas from upstream (already landed in `v1.0.0`):**

- ➖ Removed price provider integration: dropped the `recursivetree/seat-prices-core` dependency, plus the Settings page, the `/getfittingcostbyid/{id}` route, `FittingItem::IPriceable`, the `getEstimatedPriceAttribute` accessors, and every related i18n key.
- ➖ Removed About page (route + controller method + view + sidebar entry + i18n).
- ➖ Removed the maintainer footer (also removes the `snoopy` anonymous-usage-tracking ping that was wired into it).
- ➕ Added the SSH deploy helper (`scripts/ssh-seat` + `scripts/ssh_seat.py` paramiko).
- ➕ Added zh-CN translations alongside en/fr (74 keys, full parity).
- ➕ Added `CLAUDE.md` (architecture + commands + SSH usage reference).

**Deliberately NOT renamed**, to preserve drop-in DB compatibility with an existing upstream install:

| Concern | Value (unchanged) |
|---|---|
| PSR-4 namespace | `CryptaTech\Seat\Fitting\` |
| DB table prefix | `crypta_tech_seat_*` |
| Sidebar / route names | `cryptafitting::*` |
| Artisan command | `cryptatech:fittings:upgrade` |

Only the **Composer package name** (`akinams053/seat-fitting`) and three FittingServiceProvider metadata methods (`getPackageRepositoryUrl`, `getPackagistVendorName`, `getPackagistPackageName`) changed for the rebrand.

---

## 2. Repo & publication state

| Item | Value |
|---|---|
| GitHub repo | `https://github.com/akinams053/seat-fitting` |
| Branches | `master` only (old `more_skills`, `multi-corp-reports` branches deleted) |
| HEAD | `cb7f193` — `v1.0.0` tag points at this commit |
| Upstream tags (`5.0.0`–`5.0.11`) | Deleted from origin so Packagist stopped exposing them as versions |
| Packagist page | https://packagist.org/packages/akinams053/seat-fitting |
| Packagist versions | `v1.0.0` (stable) + `dev-master` (tracks the master branch HEAD) |
| GitHub → Packagist webhook | Configured, "Last delivery was successful" — auto-sync on every push |
| Working tree | clean |

The five commits introduced by the initial fork work (most recent first):

```
cb7f193  docs(readme): rewrite for fork — drop original-author personal contact, add fork notice and credits
572811c  feat: rebrand fork as akinams053/seat-fitting for Packagist
db50d7c  feat: add zh-CN translations
bcd46bb  feat: remove price provider integration and about page
b44fffa  feat: add SSH helper and project guide for plugin dev
```

---

## 3. The two SeAT hosts

| Role | Hostname | Creds file | Auth | SeAT path | OS / PHP |
|---|---|---|---|---|---|
| **Production** | VM-0-3-ubuntu (Tencent Cloud) | `.creds` | Password | `/var/www/seat` (`ubuntu`) | Linux 5.15 / unspecified PHP |
| **Test** | snapshot-371044933-ubuntu-4gb-nbg1-1 (Hetzner nbg1) | `.creds.test` + `scripts/test.key` | Private key | `/var/www/seat` (`root` login) | Linux 5.15 / **PHP 8.4.18** |

Both creds files (and `scripts/test.key`) are gitignored. `.creds.example` is the template and IS in git.

**SSH usage from the repo root:**

```bash
scripts/ssh-seat 'cmd'              # → production (.creds)
scripts/ssh-seat -t test 'cmd'      # → test server (.creds.test)
```

`.creds` format: 4 non-comment lines = `host` / `port` / `user` / `password-or-keypath`. The 4th line is auto-detected as a key path when it contains `/` or starts with `~`. Relative paths resolve from the project root, which is why `.creds.test`'s 4th line is just `scripts/test.key`.

Test server's SeAT web UI: **http://ylxh.de** (HTTP only, `APP_ENV=local`, `APP_DEBUG=true` — stack traces are visible).

---

## 4. What is deployed where, right now

### Test server (snapshot-...) — fully updated

| Check | Status |
|---|---|
| Installed package | `akinams053/seat-fitting v1.0.0` |
| Old `cryptatech/seat-fitting` | Cleanly removed; `vendor/cryptatech/seat-fitting/` gone |
| Business data preserved | 2 fittings, 23 fitting_items, 2 doctrines |
| Routes registered | 17 web + 4 API (no `/about`, no `/settings`, no `/getfittingcostbyid`) |
| Published assets | `config/fitting.exportlinks.php` + `public/web/{css,js}/fitting*` all owner=www-data, content synced to v1.0.0 |
| Horizon | Running, 0 pending jobs |
| Composer | Upgraded to **2.9.8** at `/usr/local/bin/composer` (PATH order makes it supersede the apt-installed `/usr/bin/composer`) |
| **Browser UI walkthrough** | ❌ **NOT done** — see Section 6 |

### Production server — UNTOUCHED

No write operations were performed against production this session. It still runs the upstream `cryptatech/seat-fitting` `5.0.11`. Don't deploy there without first verifying its PHP/Composer versions and confirming no orphan settings exist (the same kind of checks Section 5 prescribes).

---

## 5. The standard test-server deploy loop

After landing changes locally:

```bash
# Local — push code; tag a stable release when you want one
git push origin master
git tag -a v1.X.Y -m "release notes"
git push origin v1.X.Y
# Packagist syncs within ~30s via the GitHub webhook.

# Test server — let composer pull the new version
scripts/ssh-seat -t test 'cd /var/www/seat && \
  sudo -u www-data php artisan down && \
  sudo -u www-data composer update akinams053/seat-fitting --no-interaction && \
  sudo -u www-data php artisan migrate --force && \
  sudo -u www-data php artisan vendor:publish --force --provider="CryptaTech\\Seat\\Fitting\\FittingServiceProvider" && \
  sudo -u www-data php artisan cache:clear && \
  sudo -u www-data php artisan config:clear && \
  sudo -u www-data php artisan view:clear && \
  sudo -u www-data php artisan route:clear && \
  sudo -u www-data php artisan horizon:terminate && \
  sudo -u www-data php artisan up'
```

**Notes:**

- Plain `composer` resolves to `/usr/local/bin/composer` 2.9.8+ on the test server, so no `php -d error_reporting=8191` flag is needed anymore (the PHP 8.4 vs old-Composer gotcha that bit the first deploy has been resolved by the upgrade).
- The CI lint hook (`.github/workflows/lint.yml`) runs `pint` on push and may auto-commit `Fixes coding style` back to master. If you see your branch fall one commit behind origin after pushing, that's it — `git pull` and continue.
- If you only changed `dev-master` content (no new tag), the `composer update` line above still works because `composer.json` on the host pins `akinams053/seat-fitting: ^1.0` — it'll prefer the latest matching stable tag. To force the dev branch instead: switch the constraint on the host to `dev-master`.
- For dev-without-tag rapid iteration, you can also add a path repository on the host pointing at a clone of this repo, but that's not how it's set up right now.

---

## 6. What the next person should do first

| Priority | Task | Why |
|---|---|---|
| 🔴 **Must** | Click through the test server UI end-to-end | Fittings list / Doctrine list / Doctrine Report were not visually verified. Submit a fresh EFT to verify the state machine parser still works after the controller surgery. Run a Doctrine Report against any alliance/corp combination. |
| 🟡 Should | Decide on the 284 stale `failed_jobs` on test server | Dated 2026-03-22, all `Seat\Eveapi\Jobs\Killmails\Character\Recent` and `Seat\Eveapi\Jobs\Character\Roles` — unrelated to this plugin but they clutter Horizon's failed view |
| 🟢 Nice | Delete `src/resources/views/includes/doctrine-add.blade.php.orig` | Leftover `.orig` from an old git merge, dead file |
| 🟢 Nice | `composer audit` on the test server | The 2.9.8 update flagged 7 security advisories across 2 packages; identify and decide whether to act |

---

## 7. Local memory annotations

Persistent notes at `/Users/akina/.claude/projects/-Users-akina-project-seat-fitting/memory/`:

- `MEMORY.md` — index
- `test_server_deploy_gotchas.md` — two operational gotchas. Gotcha 1 (PHP 8.4 vs apt Composer's `ErrorHandler::register()` deprecation→fatal) is **resolved** by installing Composer 2.9.8 to `/usr/local/bin/composer`. Gotcha 2 (root-owned published assets blocking `www-data` re-publish) is still a watch-out — chown the destinations to `www-data:www-data` before any `vendor:publish --force`.

Future Claude Code sessions in this project will load `MEMORY.md` automatically. A human picking this up should read `MEMORY.md` once.

---

## 8. Open or untouched

- **Production deploy**: not done. When you do it, expect to repeat the discovery work for that box (PHP version, Composer version, orphan settings inside `global_settings`, published-asset ownership).
- **CI auto-formatter**: as noted in §5, may add a commit on top of yours after each push.
- **No tags beyond v1.0.0**: every next release needs a tag if you want it on Packagist as stable.
- **No Chinese-locale switch on the test server**: zh-CN translations are installed but the running locale is whatever the user/system default is. Switch via SeAT user menu or `.env`'s `APP_LOCALE=zh-CN` if you want to eyeball the translations.

---

## 9. Where to look for what

| If you want to know… | Read |
|---|---|
| What this plugin actually does (architecture, state machine, table layout) | `CLAUDE.md` |
| How to install / package metadata / fork attribution | `README.md` |
| How an upstream user gets here (what we forked from) | https://github.com/eveseat-plugins/seat-fitting |
| Operational gotchas | `~/.claude/projects/-Users-akina-project-seat-fitting/memory/MEMORY.md` |
| Why a specific bit of code looks the way it does | `git log -p <file>` — commit messages are descriptive |
