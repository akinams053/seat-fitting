# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A [SeAT](https://github.com/eveseat/seat) 5.x plugin (Composer package `akinams053/seat-fitting`, a fork of upstream `cryptatech/seat-fitting`) that stores EVE Online ship fittings, lets managers maintain skill requirements, and lets members/corporation staff check character skills against those requirements. It is **not** a standalone application — it has no `app/`, no `bootstrap/`, no `artisan`. It only runs inside a host SeAT install and is registered through `CryptaTech\Seat\Fitting\FittingServiceProvider` (declared in `composer.json` under `extra.laravel.providers`).

Target PHP: 8.3 (per CI). Dependencies pin to `eveseat/{services,eveapi,web} ^5.0`; the upstream price-provider dependency has been removed in this fork.

## Common commands

There is no test suite, no build step, and no local runtime in this repo. The only first-party tooling is the linter, and most "running" happens inside the host SeAT install that has consumed this package.

```bash
# Lint / autoformat (matches the GitHub Action that auto-commits "Fixes coding style")
composer global require laravel/pint   # one-time
pint                                    # format whole tree
pint src/Models/Fitting.php             # format a single file
pint --test                             # check only, no writes

# Static analysis & automated refactors are declared in require-dev but have no
# config file checked in — they run with tool defaults if invoked.
vendor/bin/phpstan analyse src
vendor/bin/rector process src --dry-run

# Inside a SeAT install that has required this package, the plugin exposes
# one artisan command (defined in src/Commands/UpgradeFits.php):
php artisan cryptatech:fittings:upgrade  # migrate pre-v5 fits via the Old* models
```

To exercise changes end-to-end you need a working SeAT host. Typical loop: `composer require akinams053/seat-fitting` (or a path repository pointing at this checkout) in the SeAT install, then `php artisan vendor:publish --force --all && php artisan migrate`. JS/CSS in `src/resources/assets/` is published to `public/web/{js,css}` and must be re-published after edits.

### SSH helper (`scripts/ssh-seat`)

There is no local SeAT runtime; the host SeAT install lives on a remote server. `scripts/ssh-seat` (bash wrapper → `scripts/ssh_seat.py`, paramiko) is the standard way to run things over there. It reads `.creds[.<target>]` from the project root — 4 non-comment lines: host / port / user / password. `.creds.example` is the template; `.creds*` is gitignored except the example.

```bash
scripts/ssh-seat 'php artisan route:list | grep fitting'   # → .creds       (prod)
scripts/ssh-seat -t test 'php artisan migrate:status'      # → .creds.test  (test server)
```

Adding another machine (e.g. staging): `cp .creds.example .creds.staging && chmod 600 .creds.staging && nano .creds.staging`, then `scripts/ssh-seat -t staging 'cmd'`. No script or gitignore edit needed — the `.creds*` glob already covers it.

Treat the prod target as production: read-only / low-impact commands are safe to run directly; anything that writes (migrate, publish, queue restart, file edits) needs explicit user confirmation first. Use `-t test` freely.

## Architecture

### Service provider is the only entry point

`src/FittingServiceProvider.php` is what SeAT auto-discovers. It wires up:
- routes (`src/Http/routes.php`), views (`fitting::` namespace), translations (`en`, `fr`, `zh-CN`), the `UpgradeFits` command
- migrations from `src/database/migrations/` and asset/config publications
- the sidebar entry (merged into `package.sidebar` from `Config/fitting.sidebar.php`)
- the abilities under `fitting.*` (`view`, `create`, `doctrineview`, `reportview`, plus newer `manage`, `corporation_report`, `fleet_review`, all in the `military` division), declared in `Config/Permissions/fitting.permissions.php`
- a `registerSdeTables(...)` call that opts this plugin into SeAT's EVE Static Data Export (SDE) for `dgmAttributeTypes`, `dgmTypeAttributes`, `dgmEffects`, `dgmTypeEffects`, `invFlags` — **the plugin will not work without these SDE tables being imported by the host**.

### Two route groups, two roles

`src/Http/routes.php` defines:
- `/api/v2/fitting/web/*` — JSON endpoints (`ApiFittingController`) that simply call static methods on `FittingController`. SeAT's REST API.
- `/fitting/*` — web UI (`FittingController` delegates skill checks/reports to services under `src/Services`). Every route is gated by one of the `fitting.*` abilities via `middleware: 'can:fitting.<x>'`.

### Domain model

Core tables (`crypta_tech_seat_*`):
- `Fitting` (`crypta_tech_seat_fittings`) — has many `FittingItem`, has many `FittingSkillRequirement`, hasOne `InvType` as ship. Primary key is `fitting_id` (not `id`).
- `FittingItem` (`crypta_tech_seat_fitting_items`) — `type_id`, `quantity`, and a critical `flag` column that encodes **where** the item sits on the ship using EVE's `invFlags` numbering (low slots 11–18, mid 19–26, high 27–34, rigs 92+, subsystems 125+, drone bay 87, fighter bay 158, cargo 5, implant 89, skill 7). These magic numbers live as constants on `Fitting`.
- `FittingSkillRequirement` (`crypta_tech_seat_fitting_skill_requirements`) — persistent minimum/advanced skill requirements per fitting. `source` is `calculated`, `manual`, or `custom`; `tier` is `minimum` or `advanced`.
- `Doctrine` (`crypta_tech_seat_fitting_doctrine`) — retained as the DB-level fitting-group concept for compatibility; it `belongsToMany` `Fitting` through `crypta_tech_seat_doctrine_fitting`. UI copy should say fitting group / corporation skill check, not doctrine.

`OldFitting` / `OldDoctrine` are read-only models pointing at the pre-v5 schema; they exist exclusively so `UpgradeFits` can migrate legacy installs by re-parsing each old fit's stored EFT text through the new `Fitting::createFromEve()`.

### EFT parsing is a state machine

`Fitting::createFromEve(string $eft, ?int $existing_id)` (bottom of `Models/Fitting.php`) is the import path used by both the UI and the upgrade command. The format is human-authored, ambiguous, and not strictly delimited, so parsing uses the `STATE` enum (LOWS → MIDS → HIGHS → RIGS → SUBS → DRONES → CARGO) defined in the same file. For each module the parser asks `$state->validInvType()` — which queries `dgmTypeEffects` for the effect ID that gates that slot kind (low=11, mid=13, high=12, rig=2663, sub=3772) — and advances state whenever the current line doesn't fit. Anything that overflows or doesn't match falls into cargo. After the main pass it does a second pass to move fighters out of cargo into the fighter bay on fighter-capable hulls (detected via `dgmTypeAttributes.attributeID == 2055`). Touching this code without understanding the state-machine contract will silently misplace modules.

### Skill calculation and requirements are services

`Services/SkillRequirementCalculator.php` is the stateless SDE skill calculator. It collects every `type_id` in the fit plus the hull, recursively follows `dgmTypeAttributes` required-skill attributes from `CalculateConstants`, and returns skill names/levels. `SkillRequirementSyncService` persists calculated minimum requirements after a fitting save without overwriting manual/custom rows. `PersonalSkillCheckService` and `CorporationSkillReportService` build the member-facing and corporation-facing skill-check payloads.

### Frontend

Server-rendered Blade in `src/resources/views/` (`fitting`, `doctrine`, `doctrinereport`) plus partials under `includes/`. Interactivity is plain jQuery in `resources/assets/js/fitting.js` and `fitting-jquery.js`, which talk to the `/fitting/*` endpoints. Remember that asset edits require `vendor:publish --force` on the host to take effect.

Current UI constraints to preserve:
- In personal fitting check, the concrete fitting detail belongs under the left-side personal check / fitting management card, not under the right-side skill check card.
- Ordinary personal check should not feature EFT text as the main view; EFT remains a management/export concern.
- Skill names and skill group names should come from EVE SDE official English `typeName` / `groupName`. The current SeAT SDE import has no `trn*` or translation tables and `invTypes` has no localized name fields, so do not machine-translate skills.
- Corporation skill check includes a nickname column sourced from `character_infos.title`.

## Conventions

- CI runs `pint` on every push and auto-commits as `Fixes coding style` (see `.github/workflows/lint.yml`). Run pint locally before pushing to avoid the bot creating an extra commit on your branch.
- The Composer package name (`akinams053/seat-fitting`) and the PSR-4 namespace (`CryptaTech\Seat\Fitting\`) intentionally do not match — the namespace, route prefixes (`cryptafitting::*`), DB table prefix (`crypta_tech_seat_*`), and artisan command (`cryptatech:fittings:upgrade`) all retain the upstream `cryptatech` branding so this fork can be installed over an existing upstream install without DB migration. `getPackageRepositoryUrl()` in `FittingServiceProvider` is the source of truth for the repo URL. Do not "normalize" the namespace to match the new vendor.
- New permissions must be added to **both** `Config/Permissions/fitting.permissions.php` **and** the `can:fitting.<x>` middleware on the relevant route. Lang keys live under `src/lang/{en,fr,zh-CN}/config.php`.
- New tables must be added as a migration under `src/database/migrations/`; the service provider auto-loads everything in that directory — no manual registration needed.
