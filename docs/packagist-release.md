# Packagist Release Guide

Prefab is developed as a monorepo but published as independent Composer packages.

## Published package repositories

Create these empty public GitHub repositories once:

- `tihloh/prefab-database`
- `tihloh/prefab-users`
- `tihloh/prefab-auth`
- `tihloh/prefab-permissions`
- `tihloh/prefab-logs`

Do not develop directly in the split repositories. `tihloh/prefab-php` remains the source of truth.

## One-time GitHub setup

Create a fine-grained personal access token with **Contents: Read and write** permission for the five split repositories.

Add that token to the `tihloh/prefab-php` repository as an Actions secret named:

```text
PREFAB_SPLIT_TOKEN
```

The workflow `.github/workflows/split-packages.yml` uses that secret to push each `packages/<module>` subtree to the matching standalone repository.

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

After `develop` is tested and merged into `main`, create/push the tag on the monorepo:

```bash
git checkout main
git pull origin main
git tag -a v0.1.0 -m "Prefab PHP v0.1.0"
git push origin v0.1.0
```

Pushing a `v*` tag automatically runs **Split Prefab Packages**. For each module it:

1. creates a subtree history from `packages/<module>`;
2. updates the split repository `main` branch;
3. applies the same release tag to the split commit.

The workflow may also be run manually from GitHub Actions. An optional `release_tag` input can publish a version tag during a manual run.

## Packagist setup

After the first successful split, submit each standalone repository to Packagist once:

```text
https://github.com/tihloh/prefab-database
https://github.com/tihloh/prefab-users
https://github.com/tihloh/prefab-auth
https://github.com/tihloh/prefab-permissions
https://github.com/tihloh/prefab-logs
```

Each repository has its own root `composer.json`, README and MIT LICENSE after splitting, so Packagist sees it as an ordinary independent Composer library.

Enable Packagist/GitHub automatic updates for each repository so later tags are discovered automatically.

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

## Important source-of-truth rule

Never fix a split package only in its generated repository. Make the change in `tihloh/prefab-php`, test it there, then run the split workflow again. This prevents the package mirrors from drifting away from the monorepo.
