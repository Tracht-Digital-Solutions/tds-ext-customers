# AGENTS.md — tds-ext-customers

The **customer/company directory** panel extension: the panel's canonical
`customer` list. Read `tds-panel-contract`'s AGENTS.md first (extensions
implement that contract); `tds-ext-lexware` / `tds-ext-support-tickets` are the
worked references for the container-first Module + RBAC pattern.

> Status (2026-07-20): **published @0.1.1** (GitHub Packages `@latest`, tag `v0.1.1`).
> Remaining go-live: wire into the admin product's `astro.config` (dep `^0.1.1` + the
> extensions array) — this ext's `/admin/customers` then replaces the legacy
> `tds-customer-api` company-list call the panel user-management uses. See the root
> `MIGRATION-STATUS.md` (issue #3).

## What it does

Admin-facing directory (`customers:read` / `customers:write`) with one page + a
count widget. It owns the canonical **`customer`** table (id/name/email/phone/note)
and exposes:

- CRUD: `GET/POST /customers`, `GET/PATCH/DELETE /customers/{id}` (email uniqueness
  → 409).
- `GET /customers/summary` — widget count.
- **`GET /admin/customers`** — the admin-only `{customers:[{id,name}]}` list the
  **base user-management** consumes for company-membership editing (replacing the
  legacy `tds-customer-api` endpoint the new panel still calls today).

## Why it exists / migration role

Replaces the customer/company directory that never got ported off `tds-customer-api`
— the new `tds-core-panel-frontend` user editor reads the company list live from
that legacy service. This extension is that list's new home and the foundation the
billing / projects / documents / messages extensions build on. See the org's
migration epic.

**Cutover notes:**
- `tds-auth-api` `app_user_customer.customer_id` references these ids — when
  migrating, preserve existing customer ids (data migration), and repoint the
  frontend's `CUSTOMER_API_URL` to this extension's `GET /admin/customers`.
- The table is deliberately named `customer` (canonical), distinct from
  `tds-ext-lexware`'s own `lx_customer` billing directory — no collision.

## Conventions (from the template — don't regress)

- Contract dep is the **published** `^1.0.0` via the public **VCS** repo (no path
  repo — CI fatals on a missing one); npm from GitHub Packages (`.npmrc` +
  `NPM_TOKEN` from `PACKAGE_TOKEN`).
- CI installs with **`npm install --no-package-lock`**; prune steps are
  `continue-on-error`. Release bumps `package.json` + `composer.json` in lockstep;
  the pushed **annotated** tag is the Composer release ref.
- Migration class prefix `Customers*` (globally unique — shared in-process migrator);
  migration **versions** must also be unique across extensions (shared `phinxlog`).

## Commands

```bash
composer install && composer test    # phpunit: Module RBAC + validation (DB-free)
npm install --no-package-lock && npm run type-check && npm run build
```

Register `new CustomersModule()` in `tds-core-panel-api`'s `Modules::enabled()` and
add the manifest to the admin target's `panelHost({ extensions })`.
