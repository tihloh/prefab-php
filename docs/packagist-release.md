# Packagist Release Guide

Prefab is a **single development monorepo**. `tihloh/prefab-php` is the source of truth for every module, all tests, examples, documentation and release work.

Composer/Packagist expects an independently published package to expose that package's `composer.json` at the root of its VCS repository. Because Prefab intentionally keeps each module independently installable, release automation generates one **distribution mirror** per module from the monorepo. These mirrors are publication outputs only, not separate development repositories.

```text
prefab-php (development monorepo)
        │
        ├── packages/database ──────► prefab-database mirror ──────► Packagist
        ├── packages/users ─────────► prefab-users mirror ─────────► Packagist
        ├── packages/auth ──────────► prefab-auth mirror ──────────► Packagist
        ├── packages/permissions ───► prefab-permissions mirror ───► Packagist
        └── packages/logs ──────────► prefab-logs mirror ──────────► Packagist
```

## Source-of-truth rule

All changes must be made in:

```text
tihloh/prefab-php
```

Never develop directly in a generated package mirror. If a published module needs a fix, change its source under `packages/<module>` in the monorepo, run CI, then republish the mirrors.

## Distribution mirrors

Create these empty public GitHub repositories once:

- `tihloh/prefab-database`
- `tihloh/prefab-users`
- `tihloh/prefab-auth`
- `tihloh/prefab-permissions`
- `tihloh/prefab-logs`

They are intentionally thin publication endpoints. Their `main` branches and release tags are generated from the corresponding monorepo subtrees.

## One-time GitHub setup

Create a fine-grained personal access token with **Contents: Read and write** permission for the five distribution mirrors.

Add that token to the `tihloh/prefab-php` repository as an Actions secret named:

```text
PREFAB_SPLIT_TOKEN
```

The workflow `.github/workflows/split-packages.yml` uses that secret to publish each `packages/<module>` subtree to its matching mirror.

## Before a release

Work normally on `develop`, then run or wait for Prefab CI. The CI verifies:

- all five package `composer.json` files with `composer validate --strict`;
- PHP syntax on PHP 8.1, 8.2, 8.3 and 8.4;
- package installation through Composer;
- synchronization of embedded `prefab.php` and `database.php` interoperability files;
- a SQLite full-stack integration smoke test.

Before merging/releasing, the working tree should also be clean locally:

```bash
php tools/sync-prefab-bootstrap.php
git diff --exit-code
```

## First release

The recommended first public version is:

```text
v0.1.0
```

After `develop` is tested and merged into `main`, create/push the tag on the **monorepo**:

```bash
git checkout main
git pull origin main
git tag -a v0.1.0 -m "Prefab PHP v0.1.0"
git push origin v0.1.0
```

Pushing a `v*` tag runs the package publishing workflow. For each module it:

1. creates a subtree history from `packages/<module>`;
2. updates the generated mirror's `main` branch;
3. applies the same release tag to the mirror commit.

The workflow may also be run manually from GitHub Actions. An optional `release_tag` input can publish a version tag during a manual run.

## Packagist setup

After the first successful mirror publication, submit each generated mirror to Packagist once:

```text
https://github.com/tihloh/prefab-database
https://github.com/tihloh/prefab-users
https://github.com/tihloh/prefab-auth
https://github.com/tihloh/prefab-permissions
https://github.com/tihloh/prefab-logs
```

Each mirror has its own root `composer.json`, README and MIT LICENSE after publication, so Packagist sees it as an ordinary independent Composer library while the actual development remains centralized in the monorepo.

Enable Packagist/GitHub automatic updates for each mirror so later tags are discovered automatically.

## Installation after publication

Projects can install only what they need:

```bash
composer require tihloh/prefab-database
composer require tihloh/prefab-users
composer require tihloh/prefab-auth
composer require tihloh/prefab-permissions
composer require tihloh/prefab-logs
```

Modules list compatible Prefab packages under Composer `suggest`, not `require`, so installing one block never forces unrelated Prefab blocks into the application.

## Versioning policy

Use semantic versioning:

- `0.1.x` — early public API; bug fixes and incremental hardening;
- `0.x` minor releases may still contain carefully documented API changes while Prefab matures;
- `1.0.0` — only after the public contracts and interoperability behavior are considered stable.

Because Prefab modules share interoperability contracts, release the five modules with the same version tag while those contracts are evolving. This makes compatibility easier to understand during the pre-1.0 period.

## Why mirrors are necessary

Prefab remains a monorepo whether or not publication mirrors exist. The mirrors solve only one packaging constraint: Packagist/Composer package discovery expects the package metadata at the repository root. Without mirrors, the alternative would be to publish one large `tihloh/prefab` package containing every module, which would prevent true independent installation of `tihloh/prefab-users`, `tihloh/prefab-auth`, and the other Lego blocks.
